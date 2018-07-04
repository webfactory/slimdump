<?php

namespace Webfactory\Slimdump;

use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Config\ConfigBuilder;
use Webfactory\Slimdump\Database\Dumper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

final class DumpTask
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param string $dsn
     * @param string[] $configFiles
     * @param OutputInterface $output
     * @throws \Doctrine\DBAL\DBALException
     */
    public function __construct($dsn, $configFiles, OutputInterface $output)
    {
        $this->output = $output;
        $this->config = ConfigBuilder::class::createConfigurationFromConsecutiveFiles($configFiles);
        $this->db = DriverManager::class::getConnection(
            array('url' => $dsn, 'charset' => 'utf8', 'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver')
        );
    }

    /**
     * @param Config $config
     * @param Connection $db
     * @param OutputInterface $output
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dump()
    {
        $dumper = new Dumper($this->output);
        $dumper->exportAsUTF8();
        $dumper->disableForeignKeys();

        $db = $this->db;

        $platform = $db->getDatabasePlatform();

        $fetchTablesResult = $db->query($platform->getListTablesSQL());

        while ($tableName = $fetchTablesResult->fetchColumn(0)) {
            $tableConfig = $this->config->findTable($tableName);

            if (null === $tableConfig || !$tableConfig->isSchemaDumpRequired()) {
                continue;
            }

            $dumper->dumpSchema($tableName, $db, $tableConfig->keepAutoIncrement());

            if ($tableConfig->isDataDumpRequired()) {
                $dumper->dumpData($tableName, $tableConfig, $db);
            }

            if ($tableConfig->isTriggerDumpRequired()) {
                $dumper->dumpTriggers($db, $tableName, $tableConfig->getDumpTriggersLevel());
            }
        }

        $dumper->enableForeignKeys();
    }
}
