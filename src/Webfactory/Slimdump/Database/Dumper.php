<?php

namespace Webfactory\Slimdump\Database;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Webfactory\Slimdump\Config\Table;

class Dumper
{
    
    /** @var OutputInterface */
    protected $output;
    
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function exportAsUTF8()
    {
        $this->output->writeln("SET NAMES utf8;");
    }

    public function disableForeignKeys()
    {
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n");
    }

    public function enableForeignKeys()
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;");
    }

    /**
     * @param string $table
     * @param \Zend_Db_Adapter_Abstract $db
     */
    public function dumpSchema($table, $db)
    {
        $this->output->writeln("-- BEGIN STRUCTURE $table");
        $this->output->writeln("DROP TABLE IF EXISTS `$table`;");
        $this->output->writeln($db->query("SHOW CREATE TABLE `$table`")->fetchColumn(1) . ";\n");

        $progress = new ProgressBar($this->output, 1);
        $format = "Dumping schema <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</>";
        $progress->setFormat($format);
        $progress->setOverwrite(true);
        $progress->setRedrawFrequency(1);
        $progress->start();
        $progress->setFormat("Dumping schema <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
        $progress->finish();
        if ($this->output instanceof \Symfony\Component\Console\Output\ConsoleOutput) {
            $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
        }
    }

    /**
     * @param string $table
     * @param Table $tableConfig
     * @param \Zend_Db_Adapter_Abstract $db
     */
    public function dumpData($table, Table $tableConfig, $db)
    {
        $cols = $this->cols($table, $db);

        $s = "SELECT ";
        $first = true;
        foreach (array_keys($cols) as $name) {
            $isBlobColumn = $this->isBlob($name, $cols);

            if (!$first) {
                $s .= ', ';
            }

            $s .= $tableConfig->getSelectExpression($name, $isBlobColumn);
            $s .= " AS `$name`";

            $first = false;
        }
        $s .= " FROM `$table`";

        $this->output->writeln("-- BEGIN DATA $table");

        $bufferSize = 0;
        $max = 100 * 1024 * 1024; // 100 MB
        $numRows = $db->fetchOne("SELECT COUNT(*) FROM $table");

        if ($numRows == 0) {
            // Fail fast: No data to dump.
            return;
        }
        
        $progress = new ProgressBar($this->output, $numRows);
        $progress->setFormat("Dumping data <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</> %remaining%/%estimated%");
        $progress->setOverwrite(true);
        $progress->setRedrawFrequency(max($numRows / 100, 1));
        $progress->start();

        $db->getConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        foreach ($db->query($s) as $row) {

            $b = $this->rowLengthEstimate($row);

            // Start a new statement to ensure that the line does not get too long.
            if ($bufferSize && $bufferSize + $b > $max) {
                $this->output->writeln(";");
                $bufferSize = 0;
            }

            if ($bufferSize == 0) {
                $this->output->write($this->insertValuesStatement($table, $cols));
            } else {
                $this->output->write(",");
            }

            $firstCol = true;
            $this->output->write("\n(");

            foreach ($row as $name => $value) {
                $isBlobColumn = $this->isBlob($name, $cols);

                if (!$firstCol) {
                    $this->output->write(", ");
                }

                $this->output->write($tableConfig->getStringForInsertStatement($name, $value, $isBlobColumn, $db));
                $firstCol = false;
            }
            $this->output->write(")");
            $bufferSize += $b;
            $progress->advance();
        }
        $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
        $progress->finish();
        if ($this->output instanceof \Symfony\Component\Console\Output\ConsoleOutput) {
            $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
        }

        $db->getConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($bufferSize) {
            $this->output->writeln(";");
        }

        $this->output->writeln('');
    }

    /**
     * @param string $table
     * @param \Zend_Db_Adapter_Abstract $db
     * @return array
     */
    protected function cols($table, $db)
    {
        $c = array();
        foreach ($db->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
            $c[$row['Field']] = $row['Type'];
        }
        return $c;
    }

    /**
     * @param string $table
     * @param array(string=>mixed) $cols
     * @return string
     */
    protected function insertValuesStatement($table, $cols)
    {
        return "INSERT INTO `$table` (`" . implode(array_keys($cols), '`, `') . "`) VALUES ";
    }

    /**
     * @param string $col
     * @param array $definitions
     * @return bool
     */
    protected function isBlob($col, array $definitions)
    {
        return stripos($definitions[$col], 'blob') !== false;
    }

    /**
     * @param array $row
     * @return int
     */
    protected function rowLengthEstimate(array $row)
    {
        $l = 0;
        foreach ($row as $value) {
            $l += strlen($value);
        }
        return $l;
    }

}