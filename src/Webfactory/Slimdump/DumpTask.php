<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Database\Dumper;
use Webfactory\Slimdump\Database\OutputFormatDriverInterface;

final class DumpTask
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OutputFormatDriverInterface
     */
    private $outputFormatDriver;

    /**
     * @var OutputInterface
     */
    private $progressOutput;

    public function __construct(Connection $connection, Config $config, OutputFormatDriverInterface $outputFormatDriver, OutputInterface $progressOutput)
    {
        $this->connection = $connection;
        $this->config = $config;
        $this->outputFormatDriver = $outputFormatDriver;
        $this->progressOutput = $progressOutput;
    }

    public function dump()
    {
        $dumper = new Dumper($this->connection, $this->outputFormatDriver, $this->progressOutput);
        $dumper->beginDump();

        $db = $this->connection;

        $manager = $db->getSchemaManager();

        foreach (array_merge($manager->listTables(), $manager->listViews()) as $asset) {
            $tableConfig = $this->config->findTable($asset->getName());

            if (null === $tableConfig || !$tableConfig->isSchemaDumpRequired()) {
                continue;
            }

            $dumper->dumpAsset($asset, $tableConfig);
        }

        $dumper->endDump();
    }
}
