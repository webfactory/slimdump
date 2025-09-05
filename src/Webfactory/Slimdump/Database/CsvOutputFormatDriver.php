<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema;
use InvalidArgumentException;
use Webfactory\Slimdump\Config\Table;

class CsvOutputFormatDriver implements OutputFormatDriverInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $directory;

    /**
     * @var ?resource
     */
    private $outputFile;

    /**
     * @var bool
     */
    private $firstLine = true;

    public function __construct(string $directory, Connection $connection)
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException(\sprintf('The directoy "%s" does not exist or is not writeable', $directory));
        }

        $this->directory = $directory;
        $this->connection = $connection;
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
        $this->firstLine = true;
        $this->outputFile = fopen($this->directory.\DIRECTORY_SEPARATOR.$asset->getName().'.csv', 'w');
    }

    public function endTableDataDump(Schema\Table $asset, Table $config): void
    {
        fclose($this->outputFile);
    }

    public function dumpTableStructure(Schema\Table $asset, Table $config): void
    {
    }

    public function dumpTableRow(array $row, Schema\Table $asset, Table $config): void
    {
        if ($this->firstLine) {
            fputcsv($this->outputFile, array_keys($row));
            $this->firstLine = false;
        }

        foreach ($row as $columnName => $value) {
            if ($column = $config->findColumn($columnName)) {
                $row[$columnName] = $column->processRowValue($value);
            }
        }

        fputcsv($this->outputFile, $row);
    }

    public function dumpTriggerDefinition(Schema\Table $asset, Table $config): void
    {
    }
}
