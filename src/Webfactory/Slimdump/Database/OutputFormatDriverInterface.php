<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Schema;
use Webfactory\Slimdump\Config\Table;

interface OutputFormatDriverInterface
{
    /**
     * Called once at the beginning of the entire slimdump run.
     */
    public function beginDump(): void;

    /**
     * Called once at the very end of the entire slimdump run.
     */
    public function endDump(): void;

    /**
     * Called to dump the structural information for a single table.
     */
    public function dumpTableStructure(Schema\Table $asset, Table $config): void;

    /**
     * Called to dump view definitions.
     */
    public function dumpViewDefinition(Schema\View $asset, Table $config): void;

    /**
     * Called at the beginning when dumping data for a single table.
     */
    public function beginTableDataDump(Schema\Table $asset, Table $config): void;

    /**
     * Called for every table data row.
     */
    public function dumpTableRow(array $row, Schema\Table $asset, Table $config): void;

    /**
     * Called at the end after dumping data for a single table.
     */
    public function endTableDataDump(Schema\Table $asset, Table $config): void;
}
