<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOConnection;
use PDO;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class Dumper
{
    /** @var OutputInterface */
    protected $output;

    /** @var int */
    protected $bufferSize;

    /** @var bool */
    protected $singleLineInsertStatements = false;

    /**
     * @var SqlDumper
     */
    private $sqlDumper;

    /**
     * @param int|null $bufferSize Default buffer size is 100MB
     */
    public function __construct(OutputInterface $output, $bufferSize = null)
    {
        $this->output = $output;
        $this->bufferSize = $bufferSize ?: 104857600;
        $this->sqlDumper = new SqlDumper($output, $this->bufferSize);
    }

    public function setSingleLineInsertStatements(bool $singleLineInsertStatements): void
    {
        $this->sqlDumper->setSingleLineInsertStatements($singleLineInsertStatements);
    }

    public function exportAsUTF8(): void
    {
        $this->sqlDumper->exportAsUTF8();
    }

    public function disableForeignKeys(): void
    {
        $this->sqlDumper->disableForeignKeys();
    }

    public function enableForeignKeys(): void
    {
        $this->sqlDumper->enableForeignKeys();
    }

    /**
     * @param      $table
     * @param bool $keepAutoIncrement
     *
     * @throws DBALException
     */
    public function dumpSchema($table, Connection $db, $keepAutoIncrement = true, bool $noProgress = false)
    {
        $this->keepalive($db);

        $this->sqlDumper->writeDumpHeadContent($table, $db, $keepAutoIncrement);

        if (!$noProgress) {
            $progress = new ProgressBar($this->output, 1);
            $format = "Dumping schema <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</>";
            $progress->setFormat($format);
            $progress->setOverwrite(true);
            $progress->setRedrawFrequency(1);
            $progress->start();
            $progress->setFormat("Dumping schema <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
            $progress->finish();
            if ($this->output instanceof ConsoleOutput) {
                $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
            }
        }
    }

    /**
     * @param string $tableName
     * @param int    $level     One of the Table::TRIGGER_* constants
     */
    public function dumpTriggers(Connection $db, $tableName, $level = Table::DEFINER_NO_DEFINER)
    {
        $this->sqlDumper->dumpTriggers($db, $tableName, $level);
    }

    public function dumpView(Connection $db, $viewName, $level = Table::DEFINER_NO_DEFINER)
    {
        $this->sqlDumper->dumpView($db, $viewName, $level);
    }

    /**
     * @param $table
     *
     * @throws DBALException
     */
    public function dumpData($table, Table $tableConfig, Connection $db, bool $noProgress)
    {
        $this->keepalive($db);
        $cols = $this->cols($table, $db);

        $s = 'SELECT ';
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

        $this->sqlDumper->writeDataDumpBegin($table);

        $numRows = (int) $db->fetchColumn("SELECT COUNT(*) FROM `$table`".$tableConfig->getCondition());

        if (0 === $numRows) {
            // Fail fast: No data to dump.
            $this->sqlDumper->writeDataDumpEnd($table);

            return;
        }

        if (!$noProgress) {
            $progress = new ProgressBar($this->output, $numRows);
            $progress->setFormat("Dumping data <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</> %remaining%/%estimated%");
            $progress->setOverwrite(true);
            $progress->setRedrawFrequency(max($numRows / 100, 1));
            $progress->start();
        } else {
            $progress = null;
        }

        /** @var PDOConnection $wrappedConnection */
        $wrappedConnection = $db->getWrappedConnection();
        $wrappedConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $actualRows = 0;

        foreach ($db->query($s) as $row) {
            $rowLength = $this->rowLengthEstimate($row);

            $this->sqlDumper->writeNewDataLineStart($rowLength, $table, $cols);

            $firstCol = true;
            foreach ($row as $name => $value) {
                $isBlobColumn = $this->isBlob($name, $cols);

                if (!$firstCol) {
                    $this->output->write($this->sqlDumper::COL_DELIMITER, false, OutputInterface::OUTPUT_RAW);
                }

                $this->output->write($tableConfig->getStringForInsertStatement($name, $value, $isBlobColumn, $db), false, OutputInterface::OUTPUT_RAW);
                $firstCol = false;
            }

            $this->sqlDumper->writeNewDataLineEnd($rowLength);

            if (null !== $progress) {
                $progress->advance();
            }

            ++$actualRows;
        }

        if (null !== $progress) {
            $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
            $progress->finish();
            if ($this->output instanceof ConsoleOutput) {
                $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
            }
        }

        if ($actualRows !== $numRows) {
            $this->output->getErrorOutput()->writeln(sprintf('<error>Expected %d rows, actually processed %d – verify results!</error>', $numRows, $actualRows));
        }

        $wrappedConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($this->sqlDumper->getCurrentBufferSize()) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
        }

        $this->sqlDumper->writeDataDumpEnd($table);
    }

    /**
     * @param string $table
     *
     * @return array
     */
    protected function cols($table, Connection $db)
    {
        $c = [];
        foreach ($db->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
            $c[$row['Field']] = $row['Type'];
        }

        return $c;
    }

    /**
     * @param string $col
     *
     * @return bool
     */
    protected function isBlob($col, array $definitions)
    {
        return (false !== stripos($definitions[$col], 'blob')) || (false !== stripos($definitions[$col], 'binary'));
    }

    /**
     * @return int
     */
    protected function rowLengthEstimate(array $row)
    {
        $l = 0;
        foreach ($row as $value) {
            $l += \strlen($value);
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
