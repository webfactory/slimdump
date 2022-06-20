<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class SqlDumper
{
    /** @var OutputInterface */
    private $output;

    /** @var Connection */
    private $db;

    /** @var int */
    private $maxBufferSize;

    /** @var int */
    private $currentBufferSize = 0;

    /** @var bool */
    private $singleLineInsertStatements;

    /**
     * @param int|null $maxBufferSize Default buffer size is 100MB
     */
    public function __construct(OutputInterface $output, Connection $db, int $maxBufferSize = null, bool $singleLineInsertStatements = false)
    {
        $this->output = $output;
        $this->db = $db;
        $this->maxBufferSize = $maxBufferSize ?: 104857600;
        $this->singleLineInsertStatements = $singleLineInsertStatements;
    }

    public function beginDump(): void
    {
        $this->output->writeln('SET NAMES utf8;', OutputInterface::OUTPUT_RAW);
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n", OutputInterface::OUTPUT_RAW);
    }

    public function endDump(): void
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;", OutputInterface::OUTPUT_RAW);
    }

    public function dumpTableStructure(string $tableName, Table $config): void
    {
        $this->output->writeln("-- BEGIN STRUCTURE $tableName", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("DROP TABLE IF EXISTS `$tableName`;", OutputInterface::OUTPUT_RAW);

        $tableCreationCommand = $this->db->fetchColumn("SHOW CREATE TABLE `$tableName`", [], 1);

        if (!$config->keepAutoIncrement()) {
            $tableCreationCommand = preg_replace('/ AUTO_INCREMENT=\d*/', '', $tableCreationCommand);
        }

        $this->output->writeln($tableCreationCommand.";\n", OutputInterface::OUTPUT_RAW);

        if ($config->isTriggerDumpRequired()) {
            $this->dumpTriggers($tableName, $config->getDumpTriggersLevel());
        }
    }

    /**
     * @param int $level One of the Table::TRIGGER_* constants
     */
    private function dumpTriggers(string $tableName, int $level = Table::DEFINER_NO_DEFINER): void
    {
        $triggers = $this->db->fetchAll(sprintf('SHOW TRIGGERS LIKE %s', $this->db->quote($tableName)));

        if (!$triggers) {
            return;
        }

        $this->output->writeln("-- BEGIN TRIGGERS $tableName", OutputInterface::OUTPUT_RAW);

        $this->output->writeln("DELIMITER ;;\n");

        foreach ($triggers as $row) {
            $createTriggerCommand = $this->db->fetchColumn("SHOW CREATE TRIGGER `{$row['Trigger']}`", [], 2);

            if (Table::DEFINER_NO_DEFINER === $level) {
                $createTriggerCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createTriggerCommand);
            }

            $this->output->writeln($createTriggerCommand.";;\n", OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln('DELIMITER ;');
    }

    public function dumpView(string $viewName, int $level = Table::DEFINER_NO_DEFINER): void
    {
        $this->output->writeln("-- BEGIN VIEW $viewName", OutputInterface::OUTPUT_RAW);

        $createViewCommand = $this->db->fetchColumn("SHOW CREATE VIEW `{$viewName}`", [], 1);

        if (Table::DEFINER_NO_DEFINER === $level) {
            $createViewCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createViewCommand);
        }

        $this->output->writeln($createViewCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    public function writeNewDataLineStart(int $rowLength, string $tableName, array $cols): void
    {
        // Start a new statement to ensure that the line does not get too long.
        if ($this->currentBufferSize && $this->currentBufferSize + $rowLength > $this->maxBufferSize) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
            $this->currentBufferSize = 0;
        }

        if (0 === $this->currentBufferSize) {
            $this->output->write($this->insertValuesStatement($tableName, $cols), false, OutputInterface::OUTPUT_RAW);
        } else {
            $this->output->write(',', false, OutputInterface::OUTPUT_RAW);
        }

        if (!$this->singleLineInsertStatements) {
            $this->output->write("\n", false, OutputInterface::OUTPUT_RAW);
        }

        $this->output->write('(', false, OutputInterface::OUTPUT_RAW);
    }

    public function writeNewDataLineEnd(int $rowLength): void
    {
        $this->output->write(')', false, OutputInterface::OUTPUT_RAW);

        $this->incrementCurrentBufferSize($rowLength);
    }

    public function insertValuesStatement(string $tableName, array $cols): string
    {
        return "INSERT INTO `$tableName` (`".implode('`, `', array_keys($cols)).'`) VALUES ';
    }

    public function writeDataDumpBegin(string $tableName): void
    {
        $this->currentBufferSize = 0;
        $this->output->writeln("-- BEGIN DATA $tableName", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("LOCK TABLES `$tableName` WRITE;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("ALTER TABLE `$tableName` DISABLE KEYS;", OutputInterface::OUTPUT_RAW);
    }

    public function writeDataDumpRow(array $row, string $tableName, Table $config, array $cols): void
    {
        $rowLength = $this->rowLengthEstimate($row);

        $this->writeNewDataLineStart($rowLength, $tableName, $cols);

        $firstCol = true;
        foreach ($row as $name => $value) {
            $isBlobColumn = Dumper::isBlob($name, $cols);

            if (!$firstCol) {
                $this->output->write(', ', false, OutputInterface::OUTPUT_RAW);
            }

            $this->output->write($this->getStringForInsertStatement($name, $config, $value, $isBlobColumn), false, OutputInterface::OUTPUT_RAW);
            $firstCol = false;
        }

        $this->writeNewDataLineEnd($rowLength);
    }

    public function writeDataDumpEnd(string $tableName): void
    {
        if ($this->currentBufferSize) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln("ALTER TABLE `$tableName` ENABLE KEYS;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln('UNLOCK TABLES;', OutputInterface::OUTPUT_RAW);
        $this->output->writeln('', OutputInterface::OUTPUT_RAW);
    }

    private function incrementCurrentBufferSize(int $bufferSize): void
    {
        $this->currentBufferSize += $bufferSize;
    }

    private function rowLengthEstimate(array $row): int
    {
        $l = 0;
        foreach ($row as $value) {
            $l += \strlen($value);
        }

        return $l;
    }

    private function getStringForInsertStatement(string $columnName, Table $config, ?string $value, bool $isBlobColumn): string
    {
        if (null === $value) {
            return 'NULL';
        }

        if ('' === $value) {
            return '""';
        }

        if ($column = $config->findColumn($columnName)) {
            return $this->db->quote($column->processRowValue($value));
        }

        if ($isBlobColumn) {
            return $value;
        }

        return $this->db->quote($value);
    }
}
