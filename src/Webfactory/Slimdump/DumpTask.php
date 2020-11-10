<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\DBALException;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Database\Dumper;
use Doctrine\DBAL\Connection;


final class DumpTask
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var bool
     */
    private $noProgress;

    /**
     * @var bool
     */
    private $singleLineInsertStatements;

    /**
     * @var int|null
     */
    private $bufferSize;

    /**
     * @throws DBALException
     */
    public function __construct(Connection $connection, Config $config, bool $noProgress, bool $singleLineInsertStatements, ?int $bufferSize, OutputInterface $output)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->noProgress = $noProgress;
        $this->singleLineInsertStatements = $singleLineInsertStatements;
        $this->bufferSize = $bufferSize;
        $this->output = $output;
    }

    public function dump()
    {
        $dumper = new Dumper($this->output, $this->bufferSize);
        $dumper->setSingleLineInsertStatements($this->singleLineInsertStatements);
        $dumper->exportAsUTF8();
        $dumper->disableForeignKeys();

        $db = $this->connection;

        $platform = $db->getDatabasePlatform();

        $fetchTablesResult = $db->query($platform->getListTablesSQL());

        while ($tableName = $fetchTablesResult->fetchColumn(0)) {
            $tableConfig = $this->config->findTable($tableName);

            if (null === $tableConfig || !$tableConfig->isSchemaDumpRequired()) {
                continue;
            }

            $dumper->dumpSchema($tableName, $db, $tableConfig->keepAutoIncrement(), $this->noProgress);

            if ($tableConfig->isDataDumpRequired()) {
                $dumper->dumpData($tableName, $tableConfig, $db, $this->noProgress);
            }

            if ($tableConfig->isTriggerDumpRequired()) {
                $dumper->dumpTriggers($db, $tableName, $tableConfig->getDumpTriggersLevel());
            }
        }

        $fetchViewsResult = $db->query($platform->getListViewsSQL($db->getDatabase()));

        while ($viewName = $fetchViewsResult->fetchColumn(2)) {
            $tableConfig = $this->config->findTable($viewName);

            if (null === $tableConfig || !$tableConfig->isSchemaDumpRequired()) {
                continue;
            }

            $dumper->dumpView($db, $viewName, $tableConfig->getViewDefinerLevel());
        }

        $dumper->enableForeignKeys();
    }
}
