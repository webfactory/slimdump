<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class Dumper
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var OutputFormatDriverInterface
     */
    private $outputFormatDriver;

    /**
     * @var OutputInterface
     */
    private $progressOutput;

    public function __construct(Connection $connection, OutputFormatDriverInterface $outputFormatDriver, OutputInterface $progressOutput)
    {
        $this->connection = $connection;
        $this->outputFormatDriver = $outputFormatDriver;
        $this->progressOutput = $progressOutput;
    }

    public function beginDump(): void
    {
        $this->outputFormatDriver->beginDump();
    }

    public function endDump(): void
    {
        $this->outputFormatDriver->endDump();
    }

    public function dumpAsset(AbstractAsset $asset, Table $config): void
    {
        if ($asset instanceof Schema\Table) {
            $this->dumpTable($asset, $config);
        } elseif ($asset instanceof Schema\View) {
            $this->dumpView($asset, $config);
        } else {
            throw new InvalidArgumentException();
        }
    }

    private function dumpTable(Schema\Table $asset, Table $config): void
    {
        $table = $asset->getName();
        $this->outputFormatDriver->dumpTableStructure($asset, $config);

        $progress = new ProgressBar($this->progressOutput, 1);
        $format = "Dumping schema <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</>";
        $progress->setFormat($format);
        $progress->setOverwrite(true);
        $progress->setRedrawFrequency(1);
        $progress->start();
        $progress->setFormat("Dumping schema <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
        $progress->finish();
        $this->progressOutput->write("\n"); // write a newline after the progressbar.

        if ($config->isDataDumpRequired()) {
            $this->dumpData($asset, $config);
        }

        if ($config->isTriggerDumpRequired()) {
            $this->dumpTriggers($asset, $config);
        }
    }

    private function dumpView(Schema\View $asset, Table $config): void
    {
        $this->outputFormatDriver->dumpViewDefinition($asset, $config);
    }

    private function dumpTriggers(Schema\Table $asset, Table $config): void
    {
        $this->outputFormatDriver->dumpTriggerDefinition($asset, $config);
    }

    private function dumpData(Schema\Table $asset, Table $tableConfig): void
    {
        $table = $asset->getName();
        $columnOrder = array_map(function (array $columnInfo): string {
            return $columnInfo['Field'];
        }, $this->connection->fetchAllAssociative(sprintf('SHOW COLUMNS FROM `%s`', $asset->getName())));

        $s = 'SELECT ';
        $first = true;
        foreach ($columnOrder as $columnName) {
            if (!$first) {
                $s .= ', ';
            }
            $first = false;

            $s .= $tableConfig->getSelectExpression($columnName, self::isBlob($asset->getColumn($columnName)))." AS `$columnName`";
        }
        $s .= " FROM `$table`";
        $s .= $tableConfig->getCondition();

        $numRows = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM `$table`".$tableConfig->getCondition());

        if (0 === $numRows) {
            // Fail fast: No data to dump.
            return;
        }

        $progress = new ProgressBar($this->progressOutput, $numRows);
        $progress->setFormat("Dumping data <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</> %remaining%/%estimated%");
        $progress->setOverwrite(true);
        $progress->setRedrawFrequency((int) max($numRows / 100, 1));
        $progress->start();

        $wrappedConnection = $this->connection->getWrappedConnection();
        if ($wrappedConnection instanceof \PDO) {
            $pdo = $wrappedConnection;
        } elseif ($wrappedConnection instanceof \Doctrine\DBAL\Driver\PDO\Connection) {
            $pdo = $wrappedConnection->getWrappedConnection();
        } else {
            throw new RuntimeException('failed to obtain the wrapped PDO object from the DBAL connection');
        }
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $actualRows = 0;

        $this->outputFormatDriver->beginTableDataDump($asset, $tableConfig);

        $result = $this->connection->executeQuery($s);
        while (($row = $result->fetchAssociative()) !== false) {
            $this->outputFormatDriver->dumpTableRow($row, $asset, $tableConfig);

            $progress->advance();
            ++$actualRows;
        }

        $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
        $progress->finish();
        $this->progressOutput->write("\n"); // write a newline after the progressbar.

        if ($actualRows !== $numRows) {
            $this->progressOutput->writeln(sprintf('<error>Expected %d rows, actually processed %d – verify results!</error>', $numRows, $actualRows));
        }

        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $this->outputFormatDriver->endTableDataDump($asset, $tableConfig);
    }

    public static function isBlob(Schema\Column $column): bool
    {
        $type = $column->getType();

        return $type instanceof BlobType || $type instanceof BinaryType;
    }
}
