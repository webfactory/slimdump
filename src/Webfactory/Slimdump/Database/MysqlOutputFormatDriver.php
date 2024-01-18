<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class MysqlOutputFormatDriver implements OutputFormatDriverInterface
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
        $this->dumpCharacterSetConnection();
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n", OutputInterface::OUTPUT_RAW);
    }

    public function endDump(): void
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;", OutputInterface::OUTPUT_RAW);
    }

    public function dumpCharacterSetConnection(): void
    {
        $charset = $this->db->fetchNumeric("SHOW VARIABLES LIKE 'character_set_connection'")[1];
        $this->output->writeln(sprintf('SET NAMES %s;', $charset));
    }

    public function dumpTableStructure(Schema\Table $asset, Table $config): void
    {
        $tableName = $asset->getName();

        $this->output->writeln("-- BEGIN STRUCTURE $tableName", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("DROP TABLE IF EXISTS `$tableName`;", OutputInterface::OUTPUT_RAW);

        $tableCreationCommand = $this->db->fetchNumeric("SHOW CREATE TABLE `$tableName`")[1];

        if (!$config->keepAutoIncrement()) {
            $tableCreationCommand = preg_replace('/ AUTO_INCREMENT=\d*/', '', $tableCreationCommand);
        }

        $this->output->writeln($tableCreationCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    public function dumpTriggerDefinition(Schema\Table $asset, Table $config): void
    {
        $tableName = $asset->getName();

        $triggers = $this->db->fetchAllAssociative(sprintf('SHOW TRIGGERS LIKE %s', $this->db->quote($tableName)));

        if (!$triggers) {
            return;
        }

        $level = $config->getDumpTriggersLevel();
        $this->output->writeln("-- BEGIN TRIGGERS $tableName", OutputInterface::OUTPUT_RAW);

        $this->output->writeln("DELIMITER ;;\n");

        foreach ($triggers as $row) {
            $createTriggerCommand = $this->db->fetchNumeric("SHOW CREATE TRIGGER `{$row['Trigger']}`")[2];

            if (Table::DEFINER_NO_DEFINER === $level) {
                $createTriggerCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createTriggerCommand);
            }

            $this->output->writeln($createTriggerCommand.";;\n", OutputInterface::OUTPUT_RAW);
        }

        $this->output->writeln('DELIMITER ;');
    }

    public function dumpViewDefinition(Schema\View $asset, Table $config): void
    {
        $viewName = $asset->getName();
        $this->output->writeln("-- BEGIN VIEW $viewName", OutputInterface::OUTPUT_RAW);

        $createViewCommand = $this->db->fetchNumeric("SHOW CREATE VIEW `{$viewName}`")[1];

        if (Table::DEFINER_NO_DEFINER === $config->getViewDefinerLevel()) {
            $createViewCommand = preg_replace('/DEFINER=`[^`]*`@`[^`]*` /', '', $createViewCommand);
        }

        $this->output->writeln($createViewCommand.";\n", OutputInterface::OUTPUT_RAW);
    }

    private function writeInsertStatementBegin(array $row, Schema\Table $asset): void
    {
        if (0 === $this->currentBufferSize) {
            $this->output->write($this->insertValuesStatement($row, $asset), false, OutputInterface::OUTPUT_RAW);
        } else {
            $this->output->write(',', false, OutputInterface::OUTPUT_RAW);
        }

        if (!$this->singleLineInsertStatements) {
            $this->output->write("\n", false, OutputInterface::OUTPUT_RAW);
        }

        $this->output->write('(', false, OutputInterface::OUTPUT_RAW);
    }

    private function writeInsertStatementEnd(): void
    {
        $this->output->write(')', false, OutputInterface::OUTPUT_RAW);
    }

    private function insertValuesStatement(array $row, Schema\Table $asset): string
    {
        return sprintf('INSERT INTO `%s` (`%s`) VALUES ', $asset->getName(), implode('`, `', array_keys($row)));
    }

    public function beginTableDataDump(Schema\Table $asset, Table $config): void
    {
        $tableName = $asset->getName();
        $this->currentBufferSize = 0;
        $this->output->writeln("-- BEGIN DATA $tableName", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("LOCK TABLES `$tableName` WRITE;", OutputInterface::OUTPUT_RAW);
        $this->output->writeln("ALTER TABLE `$tableName` DISABLE KEYS;", OutputInterface::OUTPUT_RAW);
    }

    public function dumpTableRow(array $row, Schema\Table $asset, Table $config): void
    {
        $rowLength = $this->rowLengthEstimate($row);

        $this->endStatementIfBufferSizeExceeded($rowLength);

        $this->writeInsertStatementBegin($row, $asset);
        $this->writeInsertValueList($row, $asset, $config);
        $this->writeInsertStatementEnd();

        $this->incrementCurrentBufferSize($rowLength);
    }

    public function endTableDataDump(Schema\Table $asset, Table $config): void
    {
        $tableName = $asset->getName();
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
            $l += \strlen((string) $value);
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

    private function endStatementIfBufferSizeExceeded(int $rowLength): void
    {
        // Start a new statement to ensure that the line does not get too long.
        if ($this->currentBufferSize && $this->currentBufferSize + $rowLength > $this->maxBufferSize) {
            $this->output->writeln(';', OutputInterface::OUTPUT_RAW);
            $this->currentBufferSize = 0;
        }
    }

    private function writeInsertValueList(array $row, Schema\Table $asset, Table $config): void
    {
        $firstCol = true;
        foreach ($row as $columnName => $value) {
            if (!$firstCol) {
                $this->output->write(', ', false, OutputInterface::OUTPUT_RAW);
            }
            $firstCol = false;

            $isBlobColumn = Dumper::isBlob($asset->getColumn($columnName));

            $this->output->write($this->getStringForInsertStatement($columnName, $config, $value, $isBlobColumn), false, OutputInterface::OUTPUT_RAW);
        }
    }
}
