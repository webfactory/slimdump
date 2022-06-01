<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class SqlDumper
{
    public const COL_DELIMITER = ', ';

    /** @var OutputInterface */
    private $output;

    /** @var int */
    private $maxBufferSize;

    /** @var int */
    private $currentBufferSize = 0;

    /** @var bool */
    private $singleLineInsertStatements = false;

    public function __construct(OutputInterface $output, $maxBufferSize)
    {
        $this->maxBufferSize = $maxBufferSize;
        $this->output = $output;
    }

    public function setSingleLineInsertStatements(bool $singleLineInsertStatements): void
    {
        $this->singleLineInsertStatements = $singleLineInsertStatements;
    }

    public function exportAsUTF8(): void
    {
        $this->output->writeln('SET NAMES utf8;', OutputInterface::OUTPUT_RAW);
    }

    public function disableForeignKeys(): void
    {
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n", OutputInterface::OUTPUT_RAW);
    }

    public function enableForeignKeys(): void
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;", OutputInterface::OUTPUT_RAW);
    }

    public function writeDumpHeadContent(string $table, Connection $db, bool $keepAutoIncrement): void
    {
        $this->output->writeln("-- BEGIN STRUCTURE $table", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("DROP TABLE IF EXISTS `$table`;", OutputInterface::OUTPUT_RAW);

        $tableCreationCommand = $db->fetchColumn("SHOW CREATE TABLE `$table`", [], 1);

        if (!$keepAutoIncrement) {
            $tableCreationCommand = preg_replace('/ AUTO_INCREMENT=\d*/', '', $tableCreationCommand);
        }

        $this->output->writeln($tableCreationCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    public function dumpTriggers(Connection $db, string $tableName, int $level = Table::DEFINER_NO_DEFINER): void
    {
        $triggers = $db->fetchAll(sprintf('SHOW TRIGGERS LIKE %s', $db->quote($tableName)));

        if (!$triggers) {
            return;
        }

        $this->output->writeln("-- BEGIN TRIGGERS $tableName", OutputInterface::OUTPUT_RAW);

        $this->output->writeln("DELIMITER ;;\n");

        foreach ($triggers as $row) {
            $createTriggerCommand = $db->fetchColumn("SHOW CREATE TRIGGER `{$row['Trigger']}`", [], 2);

            if (Table::DEFINER_NO_DEFINER === $level) {
                $createTriggerCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createTriggerCommand);
            }

            $this->output->writeln($createTriggerCommand.";;\n", OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln('DELIMITER ;');
    }

    public function dumpView(Connection $db, string $viewName, int $level = Table::DEFINER_NO_DEFINER): void
    {
        $this->output->writeln("-- BEGIN VIEW $viewName", OutputInterface::OUTPUT_RAW);

        $createViewCommand = $db->fetchColumn("SHOW CREATE VIEW `{$viewName}`", [], 1);

        if (Table::DEFINER_NO_DEFINER === $level) {
            $createViewCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createViewCommand);
        }

        $this->output->writeln($createViewCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    public function writeNewDataLineStart(int $rowLength, string $table, array $cols): void
    {
        // Start a new statement to ensure that the line does not get too long.
        if ($this->currentBufferSize && $this->currentBufferSize + $rowLength > $this->maxBufferSize) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
            $this->currentBufferSize = 0;
        }

        if (0 === $this->currentBufferSize) {
            $this->output->write($this->insertValuesStatement($table, $cols), false, OutputInterface::OUTPUT_RAW);
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

    public function insertValuesStatement(string $table, array $cols): string
    {
        return "INSERT INTO `$table` (`".implode('`, `', array_keys($cols)).'`) VALUES ';
    }

    public function writeDataDumpBegin(string $table): void
    {
        $this->output->writeln("-- BEGIN DATA $table", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("LOCK TABLES `$table` WRITE;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("ALTER TABLE `$table` DISABLE KEYS;", OutputInterface::OUTPUT_RAW);
    }

    public function writeDataDumpEnd(string $table): void
    {
        $this->output->writeln("ALTER TABLE `$table` ENABLE KEYS;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln('UNLOCK TABLES;', OutputInterface::OUTPUT_RAW);
        $this->output->writeln('', OutputInterface::OUTPUT_RAW);
    }

    private function incrementCurrentBufferSize(int $bufferSize): void
    {
        $this->currentBufferSize += $bufferSize;
    }

    public function getCurrentBufferSize(): int
    {
        return $this->currentBufferSize;
    }
}
