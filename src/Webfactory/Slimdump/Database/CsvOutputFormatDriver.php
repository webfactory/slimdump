<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class CsvOutputFormatDriver implements OutputFormatDriverInterface
{
    /** @var OutputInterface */
    private $output;

    /** @var Connection */
    private $db;

    public function __construct(OutputInterface $output, Connection $db)
    {
        $this->output = $output;
        $this->db = $db;
    }

    public function dumpTableStructure(Schema\Table $asset, Table $config): void
    {
        $cols = $this->cols($asset->getName());

        $this->output->writeln(implode(';', $cols), OutputInterface::OUTPUT_RAW);
    }

    public function dumpTableRow(array $row, Schema\Table $asset, Table $config): void
    {
        $firstCol = true;
        foreach ($row as $columnName => $value) {
            $isBlobColumn = Dumper::isBlob($asset->getColumn($columnName));

            if (!$firstCol) {
                $this->output->write(';', false, OutputInterface::OUTPUT_RAW);
            }

            $this->output->write($this->getStringForInsertStatement($columnName, $config, $value, $isBlobColumn), false, OutputInterface::OUTPUT_RAW);
            $firstCol = false;
        }

        $this->output->write("\n", false, OutputInterface::OUTPUT_RAW);
    }

    private function cols(string $tableName): array
    {
        $c = [];
        foreach ($this->db->fetchAll("SHOW COLUMNS FROM `$tableName`") as $row) {
            $c[] = $row['Field'];
        }

        return $c;
    }

    private function getStringForInsertStatement(string $columnName, Table $config, ?string $value, bool $isBlobColumn): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        if ($column = $config->findColumn($columnName)) {
            return $this->db->quote($column->processRowValue($value));
        }

        if ($isBlobColumn) {
            return $value;
        }

        return $this->db->quote($value);
    }

    public function beginDump(): void
    {
    }

    public function endDump(): void
    {
    }

    public function dumpViewDefinition(Schema\View $asset, Table $config): void
    {
    }

    public function beginTableDataDump(Schema\Table $asset, Table $config): void
    {
    }

    public function endTableDataDump(Schema\Table $asset, Table $config): void
    {
    }
}
