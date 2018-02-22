<?php

namespace Webfactory\Slimdump;

use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Config\ConfigBuilder;
use Webfactory\Slimdump\Database\Dumper;
use Doctrine\DBAL\Connection;

class Task
{
    public function __construct($dsn, $configFile, OutputInterface $output)
    {
        $config = ConfigBuilder::createConfigurationFromConsecutiveFiles($configFile);
        $db = $this->connect($dsn);

        $this->dump($config, $db, $output);
    }

    private function connect($dsn)
    {
        try {
            return \Doctrine\DBAL\DriverManager::getConnection(
                array('url' => $dsn, 'charset' => 'utf8', 'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver')
            );
        } catch (Exception $e) {
            $msg = "Database error: " . $e->getMessage();
            fwrite(STDERR, "$msg\n");
            exit(1);
        }
    }

    /**
     * @param Config $config
     * @param Connection $db
     * @param OutputInterface $output
     * @throws \Doctrine\DBAL\DBALException
     */
    private function dump(Config $config, Connection $db, OutputInterface $output)
    {
        $dumper = new Dumper($output);
        $dumper->exportAsUTF8();
        $dumper->disableForeignKeys();

        $platform = $db->getDatabasePlatform();

        $fetchTablesResult = $db->query($platform->getListTablesSQL());

        while ($tableName = $fetchTablesResult->fetchColumn(0)) {
            $tableConfig = $config->findTable($tableName);

            if (null === $tableConfig) {
                continue;
            }

            if ($tableConfig->isSchemaDumpRequired()) {
                $dumper->dumpSchema($tableName, $db, $tableConfig->keepAutoIncrement());

                if ($tableConfig->isDataDumpRequired()) {
                    $dumper->dumpData($tableName, $tableConfig, $db);
                }

                if ($tableConfig->isTriggerDumpRequired()) {
                    $dumper->dumpTriggers($db, $tableName, $tableConfig->getDumpTriggersLevel());
                }
            }
        }
        $dumper->enableForeignKeys();
    }
}
