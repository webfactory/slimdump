<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PDOMySqlDriver;
use Doctrine\DBAL\DriverManager;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\ConfigBuilder;
use Webfactory\Slimdump\Database\CsvOutputFormatDriver;
use Webfactory\Slimdump\Database\MysqlOutputFormatDriver;
use Webfactory\Slimdump\Database\OutputFormatDriverInterface;
use Webfactory\Slimdump\Doctrine\DummyTypeRegistrationEventSubscriber;

final class SlimdumpCommand extends Command
{
    public const OUTPUT_CSV = 'output-csv';

    protected function configure(): void
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
                self::OUTPUT_CSV,
                null,
                InputOption::VALUE_REQUIRED,
                'Output .csv files. Requires a directory path where files will be created (no stdout).'
            )
            ->addOption(
                'buffer-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Maximum length of a single SQL statement generated. Defaults to 100MB.'
            )
            ->addOption(
                'no-progress',
                null,
                InputOption::VALUE_NONE,
                'Don\'t print progress information while dumping tables.'
            )
            ->addOption(
                'single-line-insert-statements',
                null,
                InputOption::VALUE_NONE,
                'Write each whole INSERT INTO statement into one single line instead of creating a new line for each row.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progressOutput = $this->createProgressOutput($input, $output);

        $connection = $this->createConnection($input);
        $connection->getEventManager()->addEventSubscriber(new DummyTypeRegistrationEventSubscriber($connection->getSchemaManager()));
        $this->setMaxExecutionTimeUnlimited($connection, $progressOutput);

        $config = ConfigBuilder::createConfigurationFromConsecutiveFiles($input->getArgument('config'));

        if ($dir = $input->getOption(self::OUTPUT_CSV)) {
            $outputFormatDriver = $this->createOutputDriverCsv($dir, $connection);
            $progressOutput->writeln('<comment>CSV output format is still experimental – format details may change at any time</comment>');
        } else {
            $outputFormatDriver = $this->createOutputDriverMysql($input, $output, $connection);
        }

        $dumptask = new DumpTask($connection, $config, $outputFormatDriver, $progressOutput);
        $dumptask->dump();

        return 0;
    }

    private function createOutputDriverMysql(InputInterface $input, OutputInterface $output, Connection $connection): OutputFormatDriverInterface
    {
        $singleLineInsertStatements = $input->getOption('single-line-insert-statements') ? true : false;
        $bufferSize = $this->parseBufferSize($input->getOption('buffer-size'));

        return new MysqlOutputFormatDriver($output, $connection, $bufferSize, $singleLineInsertStatements);
    }

    private function createOutputDriverCsv(string $directory, Connection $connection): OutputFormatDriverInterface
    {
        return new CsvOutputFormatDriver($directory, $connection);
    }

    private function parseBufferSize(?string $bufferSize): ?int
    {
        if (null === $bufferSize) {
            return null;
        }

        $match = preg_match('/^(\d+)(KB|MB|GB)?$/', $bufferSize, $matches);
        if (false === $match || 0 === $match) {
            throw new RuntimeException('The buffer size must be an unsigned integer, optionally ending with KB, MB or GB.');
        }
        $bufferSize = (int) $matches[1];
        $bufferFactor = 1;

        switch ($matches[2]) {
            case 'GB':
                $bufferFactor *= 1024;
                // no break
            case 'MB':
                $bufferFactor *= 1024;
                // no break
            case 'KB':
                $bufferFactor *= 1024;
        }

        return $bufferSize * $bufferFactor;
    }

    private function setMaxExecutionTimeUnlimited(Connection $connection, OutputInterface $output): void
    {
        $maxExecutionTimeInfo = $connection->fetchAssociative('SHOW VARIABLES LIKE "max_execution_time"');

        if ($maxExecutionTimeInfo && 0 != $maxExecutionTimeInfo['Value']) {
            $connection->executeStatement('SET SESSION max_execution_time = 0');
            $output->writeln('<info>The MySQL "max_execution_time" timeout setting has been disabled for the current database connection.</info>');
        }
    }

    private function createConnection(InputInterface $input): Connection
    {
        $dsn = $input->getArgument('dsn');

        if ('-' === $dsn) {
            $dsn = getenv('MYSQL_DSN');
        }

        $mysqliIndependentDsn = preg_replace('_^mysqli:_', 'mysql:', $dsn);
        $connection = DriverManager::getConnection(
            ['url' => $mysqliIndependentDsn, 'charset' => 'utf8', 'driverClass' => PDOMySqlDriver::class]
        );

        return $connection;
    }

    private function createProgressOutput(InputInterface $input, OutputInterface $output): OutputInterface
    {
        if (!$output instanceof ConsoleOutputInterface || $input->getOption('no-progress')) {
            return new NullOutput();
        }

        return $output->getErrorOutput();
    }
}
