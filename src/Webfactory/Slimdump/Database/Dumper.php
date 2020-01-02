<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Config\Table;

class Dumper
{
    
    /** @var OutputInterface */
    protected $output;

    /** @var integer */
    protected $bufferSize;

    /**
     * Dumper constructor.
     * @param OutputInterface $output
     * @param int|null $bufferSize Default buffer size is 100MB
     */
    public function __construct(OutputInterface $output, $bufferSize = null)
    {
        $this->output = $output;
        $this->bufferSize = $bufferSize ? : 104857600;
    }

    public function exportAsUTF8()
    {
        $this->output->writeln("SET NAMES utf8;", OutputInterface::OUTPUT_RAW);
    }

    public function disableForeignKeys()
    {
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n", OutputInterface::OUTPUT_RAW);
    }

    public function enableForeignKeys()
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;", OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param               $table
     * @param Connection    $db
     * @param boolean       $keepAutoIncrement
     */
    public function dumpSchema($table, Connection $db, $keepAutoIncrement = true)
    {
        $this->keepalive($db);
        $this->output->writeln("-- BEGIN STRUCTURE $table", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("DROP TABLE IF EXISTS `$table`;", OutputInterface::OUTPUT_RAW);

        $tableCreationCommand = $db->fetchColumn("SHOW CREATE TABLE `$table`", array(), 1);

        if (!$keepAutoIncrement) {
            $tableCreationCommand = preg_replace('/ AUTO_INCREMENT=[0-9]*/', '', $tableCreationCommand);
        }

        $this->output->writeln($tableCreationCommand.";\n", OutputInterface::OUTPUT_RAW);

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
     * @param Connection $db
     * @param string     $tableName
     * @param integer    $level One of the Table::TRIGGER_* constants
     */
    public function dumpTriggers(Connection $db, $tableName, $level = Table::TRIGGER_NO_DEFINER)
    {
        $triggers = $db->fetchAll(sprintf('SHOW TRIGGERS LIKE %s', $db->quote($tableName)));

        if (!$triggers) {
            return;
        }

        $this->output->writeln("-- BEGIN TRIGGERS $tableName", OutputInterface::OUTPUT_RAW);

        foreach ($triggers as $row) {
            $createTriggerCommand = $db->fetchColumn("SHOW CREATE TRIGGER `{$row['Trigger']}`", [], 2);

            if ($level == Table::TRIGGER_NO_DEFINER) {
                $createTriggerCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createTriggerCommand);
            }

            $this->output->writeln($createTriggerCommand.";\n", OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * @param            $table
     * @param Table      $tableConfig
     * @param Connection $db
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dumpData($table, Table $tableConfig, Connection $db)
    {
        $this->keepalive($db);
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

        $s .= $tableConfig->getCondition();

        $this->output->writeln("-- BEGIN DATA $table", OutputInterface::OUTPUT_RAW);

        $bufferSize = 0;
        $max = $this->bufferSize;
        $numRows = $db->fetchColumn("SELECT COUNT(*) FROM `$table`".$tableConfig->getCondition());

        if ($numRows == 0) {
            // Fail fast: No data to dump.
            return;
        }

        $progress = new ProgressBar($this->output, $numRows);
        $progress->setFormat("Dumping data <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</> %remaining%/%estimated%");
        $progress->setOverwrite(true);
        $progress->setRedrawFrequency(max($numRows / 100, 1));
        $progress->start();

        /** @var PDOConnection $wrappedConnection */
        $wrappedConnection = $db->getWrappedConnection();
        $wrappedConnection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        foreach ($db->query($s) as $row) {

            $b = $this->rowLengthEstimate($row);

            // Start a new statement to ensure that the line does not get too long.
            if ($bufferSize && $bufferSize + $b > $max) {
                $this->output->writeln(";", OutputInterface::OUTPUT_RAW);
                $bufferSize = 0;
            }

            if ($bufferSize == 0) {
                $this->output->write($this->insertValuesStatement($table, $cols), false, OutputInterface::OUTPUT_RAW);
            } else {
                $this->output->write(",", false, OutputInterface::OUTPUT_RAW);
            }

            $firstCol = true;
            $this->output->write("\n(", false, OutputInterface::OUTPUT_RAW);

            foreach ($row as $name => $value) {
                $isBlobColumn = $this->isBlob($name, $cols);

                if (!$firstCol) {
                    $this->output->write(", ", false, OutputInterface::OUTPUT_RAW);
                }

                $this->output->write($tableConfig->getStringForInsertStatement($name, $value, $isBlobColumn, $db), false, OutputInterface::OUTPUT_RAW);
                $firstCol = false;
            }
            $this->output->write(")", false, OutputInterface::OUTPUT_RAW);
            $bufferSize += $b;
            $progress->advance();
        }
        $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
        $progress->finish();
        if ($this->output instanceof \Symfony\Component\Console\Output\ConsoleOutput) {
            $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
        }

        $wrappedConnection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($bufferSize) {
            $this->output->writeln(";", OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln('', OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param string     $table
     * @param Connection $db
     *
     * @return array
     */
    protected function cols($table, Connection $db)
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
        return "INSERT INTO `$table` (`" . implode('`, `', array_keys($cols)) . "`) VALUES ";
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

    private function keepalive(Connection $db)
    {
        if (false === $db->ping()) {
            $db->close();
            $db->connect();
        }
    }
}
