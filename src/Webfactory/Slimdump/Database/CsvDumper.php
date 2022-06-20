<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class CsvDumper
{
    public const COL_DELIMITER = ';';

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
    }

    public function disableForeignKeys(): void
    {
    }

    public function enableForeignKeys(): void
    {
    }

    public function writeDumpHeadContent(string $table, Connection $db, bool $keepAutoIncrement): void
    {
        $cols = $this->cols($table, $db);

        $this->output->writeln(implode(self::COL_DELIMITER, $cols), OutputInterface::OUTPUT_RAW);
    }

    public function dumpTriggers(Connection $db, string $tableName, int $level = Table::DEFINER_NO_DEFINER): void
    {
    }

    public function dumpView(Connection $db, string $viewName, int $level = Table::DEFINER_NO_DEFINER): void
    {
    }

    public function writeNewDataLineStart(int $rowLength, string $table, array $cols): void
    {
    }

    public function writeNewDataLineEnd(int $rowLength): void
    {
        $this->output->write("\n", false, OutputInterface::OUTPUT_RAW);
    }

    public function writeDataDumpBegin(string $table): void
    {
    }

    public function writeDataDumpEnd(string $table): void
    {
    }

    public function getCurrentBufferSize(): int
    {
        return $this->currentBufferSize;
    }

    private function cols(string $table, Connection $db): array
    {
        $c = [];
        foreach ($db->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
            $c[] = $row['Field'];
        }

        return $c;
    }
}
