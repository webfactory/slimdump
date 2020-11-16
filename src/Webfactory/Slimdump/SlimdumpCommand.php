<?php

namespace Webfactory\Slimdump;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\PDOMySql\Driver as PDOMySqlDriver;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\ConfigBuilder;

final class SlimdumpCommand extends Command
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
                'Maximum length of a single SQL statement generated. Defaults to 100MB.'
            )
            ->addOption(
                'no-progress',
                '',
                InputOption::VALUE_NONE,
                'Don\'t print progress information while dumping tables.'
            )
            ->addOption(
                'single-line-insert-statements',
                '',
                InputOption::VALUE_NONE,
                'Write each whole INSERT INTO statement into one single line instead of creating a new line for each row.'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dsn = $input->getArgument('dsn');

        if ('-' === $dsn) {
            $dsn = getenv('MYSQL_DSN');
        }

        $mysqliIndependentDsn = preg_replace('_^mysqli:_', 'mysql:', $dsn);
        $connection = DriverManager::getConnection(
            ['url' => $mysqliIndependentDsn, 'charset' => 'utf8', 'driverClass' => PDOMySqlDriver::class]
        );

        $noProgress = $input->getOption('no-progress') ? true : false;
        $singleLineInsertStatements = $input->getOption('single-line-insert-statements') ? true : false;
        $bufferSize = $this->parseBufferSize($input->getOption('buffer-size'));
        $config = ConfigBuilder::createConfigurationFromConsecutiveFiles($input->getArgument('config'));

        $dumptask = new DumpTask($connection, $config, $noProgress, $singleLineInsertStatements, $bufferSize, $output);
        $dumptask->dump();

        return 0;
    }

    private function parseBufferSize(?string $bufferSize): ?int
    {
        if ($bufferSize === null) {
            return null;
        }

        $match = preg_match('/^(\d+)(KB|MB|GB)?$/', $bufferSize, $matches);
        if ($match === false || $match === 0) {
            throw new \RuntimeException('The buffer size must be an unsigned integer, optionally ending with KB, MB or GB.');
        }
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
    }
}
