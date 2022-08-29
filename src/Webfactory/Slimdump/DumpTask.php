<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Database\Dumper;
use Webfactory\Slimdump\Database\MysqlOutputFormatDriver;

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
        $sqlDumper = new MysqlOutputFormatDriver($this->output, $this->connection, $this->bufferSize, $this->singleLineInsertStatements);
        $dumper = new Dumper($this->output, $this->connection, $sqlDumper);
        $dumper->beginDump();

        $db = $this->connection;

        $manager = $db->getSchemaManager();

        foreach (array_merge($manager->listTables(), $manager->listViews()) as $asset) {
            $tableConfig = $this->config->findTable($asset->getName());

            if (null === $tableConfig || !$tableConfig->isSchemaDumpRequired()) {
                continue;
            }

            $dumper->dumpAsset($asset, $tableConfig, $this->noProgress);
        }

        $dumper->endDump();
    }
}
