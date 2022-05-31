<?php

namespace Webfactory\Slimdump\Database;

use Symfony\Component\Console\Output\OutputInterface;

class SqlDumper
{
    /** @var OutputInterface */
    private $output;

    public function __construct(OutputInterface $output, $bufferSize = null)
    {
        $this->output = $output;
    }

    public function exportAsUTF8(): void
    {
        $this->output->writeln('SET NAMES utf8;', OutputInterface::OUTPUT_RAW);
    }

    public function disableForeignKeys(): void
    {
        $this->output->writeln("SET FOREIGN_KEY_CHECKS = 0;\n", OutputInterface::OUTPUT_RAW);
    }

    public function enableForeignKeys(): void
    {
        $this->output->writeln("\nSET FOREIGN_KEY_CHECKS = 1;", OutputInterface::OUTPUT_RAW);
    }
}
