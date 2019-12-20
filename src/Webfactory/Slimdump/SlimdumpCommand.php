<?php

namespace Webfactory\Slimdump;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            );
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dsn = $input->getArgument('dsn');
        $configFiles = $input->getArgument('config');
        $noProgress = $input->getOption('no-progress') ? true : false;

        if ($dsn === '-') {
            $dsn = getenv("MYSQL_DSN");
        }

        $dumptask = new DumpTask($dsn, $configFiles, $noProgress, $output);
        $dumptask->dump();
    }

}
