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
     * @param int|null $bufferSize Default buffer size is 100MB
     */
    public function __construct(OutputInterface $output, $bufferSize = null)
    {
        $this->output = $output;
        $this->bufferSize = $bufferSize ?: 104857600;
    }

    public function setSingleLineInsertStatements(bool $singleLineInsertStatements): void
    {
        $this->singleLineInsertStatements = $singleLineInsertStatements;
    }

    public function exportAsUTF8()
    {
        $this->output->writeln('SET NAMES utf8;', OutputInterface::OUTPUT_RAW);
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
     * @param      $table
     * @param bool $keepAutoIncrement
     *
     * @throws DBALException
     */
    public function dumpSchema($table, Connection $db, $keepAutoIncrement = true, bool $noProgress = false)
    {
        $this->output->writeln("-- BEGIN STRUCTURE $table", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("DROP TABLE IF EXISTS `$table`;", OutputInterface::OUTPUT_RAW);

        $tableCreationCommand = $db->fetchAssociative("SHOW CREATE TABLE `$table`", [])['Create Table'];

        if (!$keepAutoIncrement) {
            $tableCreationCommand = preg_replace('/ AUTO_INCREMENT=\d*/', '', $tableCreationCommand);
        }

        $this->output->writeln($tableCreationCommand.";\n", OutputInterface::OUTPUT_RAW);

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
        $triggers = $db->fetchAllAssociative(sprintf('SHOW TRIGGERS LIKE %s', $db->quote($tableName)));

        if (!$triggers) {
            return;
        }

        $this->output->writeln("-- BEGIN TRIGGERS $tableName", OutputInterface::OUTPUT_RAW);

        $this->output->writeln("DELIMITER ;;\n");

        foreach ($triggers as $row) {
            $createTriggerCommand = $db->fetchAssociative("SHOW CREATE TRIGGER `{$row['Trigger']}`", [])['SQL Original Statement'];

            if (Table::DEFINER_NO_DEFINER === $level) {
                $createTriggerCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createTriggerCommand);
            }

            $this->output->writeln($createTriggerCommand.";;\n", OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln('DELIMITER ;');
    }

    public function dumpView(Connection $db, $viewName, $level = Table::DEFINER_NO_DEFINER)
    {
        $this->output->writeln("-- BEGIN VIEW $viewName", OutputInterface::OUTPUT_RAW);

        $createViewCommand = $db->fetchAssociative("SHOW CREATE VIEW `{$viewName}`", [])['Create View'];

        if (Table::DEFINER_NO_DEFINER === $level) {
            $createViewCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createViewCommand);
        }

        $this->output->writeln($createViewCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    /**
     * @param $table
     *
     * @throws DBALException
     */
    public function dumpData($table, Table $tableConfig, Connection $db, bool $noProgress)
    {
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

        $this->output->writeln("-- BEGIN DATA $table", OutputInterface::OUTPUT_RAW);
        $this->writeDataDumpBegin($table);

        $bufferSize = 0;
        $max = $this->bufferSize;
        $numRows = (int) $db->fetchOne("SELECT COUNT(*) FROM `$table`".$tableConfig->getCondition());

        if (0 === $numRows) {
            // Fail fast: No data to dump.
            $this->writeDataDumpEnd($table);

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

        $this->setPdoAttribute($db, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        foreach ($db->executeQuery($s)->fetchAllAssociative() as $row) {
            $b = $this->rowLengthEstimate($row);

            // Start a new statement to ensure that the line does not get too long.
            if ($bufferSize && $bufferSize + $b > $max) {
                $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
                $bufferSize = 0;
            }

            if (0 === $bufferSize) {
                $this->output->write($this->insertValuesStatement($table, $cols), false, OutputInterface::OUTPUT_RAW);
            } else {
                $this->output->write(',', false, OutputInterface::OUTPUT_RAW);
            }

            $firstCol = true;

            if (!$this->singleLineInsertStatements) {
                $this->output->write("\n", false, OutputInterface::OUTPUT_RAW);
            }

            $this->output->write('(', false, OutputInterface::OUTPUT_RAW);

            foreach ($row as $name => $value) {
                $isBlobColumn = $this->isBlob($name, $cols);

                if (!$firstCol) {
                    $this->output->write(', ', false, OutputInterface::OUTPUT_RAW);
                }

                $this->output->write($tableConfig->getStringForInsertStatement($name, $value, $isBlobColumn, $db), false, OutputInterface::OUTPUT_RAW);
                $firstCol = false;
            }
            $this->output->write(')', false, OutputInterface::OUTPUT_RAW);
            $bufferSize += $b;
            if (null !== $progress) {
                $progress->advance();
            }
        }

        if (null !== $progress) {
            $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
            $progress->finish();
            if ($this->output instanceof ConsoleOutput) {
                $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
            }
        }

        $this->setPdoAttribute($db, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($bufferSize) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
        }

        $this->writeDataDumpEnd($table);
    }

    /**
     * @param string $table
     *
     * @return array
     */
    protected function cols($table, Connection $db)
    {
        $c = [];
        foreach ($db->fetchAllAssociative("SHOW COLUMNS FROM `$table`") as $row) {
            $c[$row['Field']] = $row['Type'];
        }

        return $c;
    }

    /**
     * @param string               $table
     * @param array(string=>mixed) $cols
     *
     * @return string
     */
    protected function insertValuesStatement($table, $cols)
    {
        return "INSERT INTO `$table` (`".implode('`, `', array_keys($cols)).'`) VALUES ';
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

    protected function writeDataDumpBegin($table): void
    {
        $this->output->writeln("LOCK TABLES `$table` WRITE;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("ALTER TABLE `$table` DISABLE KEYS;", OutputInterface::OUTPUT_RAW);
    }

    protected function writeDataDumpEnd($table): void
    {
        $this->output->writeln("ALTER TABLE `$table` ENABLE KEYS;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln('UNLOCK TABLES;', OutputInterface::OUTPUT_RAW);
        $this->output->writeln('', OutputInterface::OUTPUT_RAW);
    }

    protected function setPdoAttribute(Connection $connection, int $attribute, $value): void
    {
        while (method_exists($connection, 'getWrappedConnection') && !$connection instanceof PDO) {
            $connection = $connection->getWrappedConnection();
        }

        if (!$connection instanceof PDO) {
            return;
        }

        $connection->setAttribute($attribute, $value);
    }
}
