<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption(
                'buffer-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Maximum buffer for the database connected to',
                '100MB'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dsn = $input->getArgument('dsn');

        if ($dsn === '-') {
            $dsn = getenv("MYSQL_DSN");
        }

        $db = $this->connect($dsn);

        $config = ConfigBuilder::createConfigurationFromConsecutiveFiles($input->getArgument('config'));
        $this->dump($config, $db, $output, $this->reformatBufferSize($input->getOption('buffer-size')));
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
     * @return int
     */
    protected function reformatBufferSize($size)
    {
        $bufferSize = $size;

        if ($bufferSize !== null) {
            preg_match('/^(\d+)(KB|MB|GB)?$/', $bufferSize, $matches);
            $bufferSize = (int)$matches[1];
            $bufferFactor = 1;

            switch ($matches[2]) {
                case 'GB':
                    $bufferFactor *= 1024;
                case 'MB':
                    $bufferFactor *= 1024;
                case 'KB':
                    $bufferFactor *= 1024;
            }

            return $bufferSize * $bufferFactor;
        } else {
            // Default 100MB
            return 100 * 1024 * 1024;
        }
    }

    /**
     * @param Config $config
     * @param Connection $db
     * @param OutputInterface $output
     * @param integer $bufferSize
     * @throws \Doctrine\DBAL\DBALException
     */
    public function dump(Config $config, Connection $db, OutputInterface $output, $bufferSize)
    {
        $dumper = new Dumper($output, $bufferSize);
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
