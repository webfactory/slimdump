<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Config\ConfigBuilder;
use Webfactory\Slimdump\Database\Dumper;

class SlimdumpCommand extends Command
{
    
    protected function configure()
    {
        $this
            ->setName('slimdump:dump')
            ->setDescription('Dump a MySQL database by configuration.')
            ->addArgument(
               'dsn',
               InputArgument::REQUIRED,
               'The Database-DSN to connect to.'
            )
            ->addArgument(
                'config',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Configuration files (at least one).'
            )
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dsn = $input->getArgument('dsn');

        if ($dsn === '-') {
            $dsn = getenv("MYSQL_DSN");
        }

        $db = connect($dsn);

        $config = ConfigBuilder::createConfigurationFromConsecutiveFiles($input->getArgument('config'));
        $this->dump($config, $db, $output);
    }

    /**
     * @param Config $config
     * @param Connection $db
     * @param OutputInterface $output
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dump(Config $config, Connection $db, OutputInterface $output)
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
