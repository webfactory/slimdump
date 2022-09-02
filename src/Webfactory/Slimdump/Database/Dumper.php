<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema as Schema;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\BlobType;
use InvalidArgumentException;
use PDO;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class Dumper
{
    /** @var OutputInterface */
    protected $output;

    /** @var bool */
    protected $singleLineInsertStatements = false;

    /** @var OutputFormatDriverInterface */
    private $outputFormatDriver;

    /** @var Connection */
    private $db;

    public function __construct(OutputInterface $output, Connection $db, OutputFormatDriverInterface $sqlDumper)
    {
        $this->output = $output;
        $this->db = $db;
        $this->outputFormatDriver = $sqlDumper;
    }

    public function beginDump(): void
    {
        $this->outputFormatDriver->beginDump();
    }

    public function endDump(): void
    {
        $this->outputFormatDriver->endDump();
    }

    public function dumpAsset(AbstractAsset $asset, Table $config, bool $noProgress = false): void
    {
        if ($asset instanceof Schema\Table) {
            $this->dumpTable($asset, $config, $noProgress);
        } elseif ($asset instanceof Schema\View) {
            $this->dumpView($asset, $config, $noProgress);
        } else {
            throw new InvalidArgumentException();
        }
    }

    private function dumpTable(Schema\Table $asset, Table $config, bool $noProgress = false): void
    {
        $table = $asset->getName();
        $this->outputFormatDriver->dumpTableStructure($asset, $config);

        if (!$noProgress) {
            $progress = new ProgressBar($this->output, 1);
            $format = "Dumping schema <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</>";
            $progress->setFormat($format);
            $progress->setOverwrite(true);
            $progress->setRedrawFrequency(1);
            $progress->start();
            $progress->setFormat("Dumping schema <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
            $progress->finish();
            if ($this->output instanceof ConsoleOutput) {
                $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
            }
        }

        if ($config->isDataDumpRequired()) {
            $this->dumpData($asset, $config, $noProgress);
        }
    }

    private function dumpView(Schema\View $asset, Table $config, bool $noProgress = false): void
    {
        $this->outputFormatDriver->dumpViewDefinition($asset, $config);
    }

    private function dumpData(Schema\Table $asset, Table $tableConfig, bool $noProgress): void
    {
        $table = $asset->getName();
        $columnOrder = array_map(function (array $columnInfo): string { return $columnInfo['Field']; }, $this->db->fetchAllAssociative(sprintf('SHOW COLUMNS FROM `%s`', $asset->getName())));

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

        $numRows = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM `$table`".$tableConfig->getCondition());

        if (0 === $numRows) {
            // Fail fast: No data to dump.
            return;
        }

        if (!$noProgress) {
            $progress = new ProgressBar($this->output, $numRows);
            $progress->setFormat("Dumping data <fg=cyan>$table</>: <fg=yellow>%percent:3s%%</> %remaining%/%estimated%");
            $progress->setOverwrite(true);
            $progress->setRedrawFrequency(max($numRows / 100, 1));
            $progress->start();
        } else {
            $progress = null;
        }

        /** @var PDOConnection $wrappedConnection */
        $wrappedConnection = $this->db->getWrappedConnection();
        $wrappedConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $actualRows = 0;

        $this->outputFormatDriver->beginTableDataDump($asset, $tableConfig);

        foreach ($this->db->executeQuery($s) as $row) {
            $this->outputFormatDriver->dumpTableRow($row, $asset, $tableConfig);

            if (null !== $progress) {
                $progress->advance();
            }

            ++$actualRows;
        }

        if (null !== $progress) {
            $progress->setFormat("Dumping data <fg=green>$table</>: <fg=green>%percent:3s%%</> Took: %elapsed%");
            $progress->finish();
            if ($this->output instanceof ConsoleOutput) {
                $this->output->getErrorOutput()->write("\n"); // write a newline after the progressbar.
            }
        }

        if ($actualRows !== $numRows) {
            $this->output->getErrorOutput()->writeln(sprintf('<error>Expected %d rows, actually processed %d – verify results!</error>', $numRows, $actualRows));
        }

        $wrappedConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $this->outputFormatDriver->endTableDataDump($asset, $tableConfig);
    }

    public static function isBlob(Schema\Column $column): bool
    {
        $type = $column->getType();

        return $type instanceof BlobType || $type instanceof BinaryType;
    }
}
