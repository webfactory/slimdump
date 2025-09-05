<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use Webfactory\Slimdump\Config\Table;

final class CsvOutputFormatDriverTest extends TestCase
{
    private const OUTPUT_DIRECTORY = __DIR__.'/../../../../tmp';

    private CsvOutputFormatDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new CsvOutputFormatDriver(self::OUTPUT_DIRECTORY, $this->createMock(Connection::class));
    }

    /**
     * @param array<string, string> $tableRow
     */
    #[Test]
    #[DataProvider('provideDataFor_dumpTableRow')]
    public function dumpTableRow(string $tableConfigAsXml, array $tableRow, string $expectedValue): void
    {
        $csvData = $this->dumpTableAsCsv($tableConfigAsXml, $tableRow);

        $this->assertSame($expectedValue, $csvData[1][0]);
    }

    public static function provideDataFor_dumpTableRow(): Generator
    {
        yield 'can dump row as is' => [
            '<table dump="full" />',
            ['my-column' => 'my-original-value'],
            'my-original-value',
        ];

        yield 'can dump with configured replacement' => [
            '<table dump="full"><column name="my-column" dump="replace" replacement="my-replacing-value"/></table>',
            ['my-column' => 'my-original-value'],
            'my-replacing-value',
        ];
    }

    /**
     * @param array<string, string> $tableRow
     *
     * @return array<int, array<string>> the dumped CSV data as array of rows, each row being an array of columns
     */
    private function dumpTableAsCsv(string $tableConfigAsXml, array $tableRow): array
    {
        $tableSchema = new \Doctrine\DBAL\Schema\Table('my-table');
        $tableConfig = new Table(
            new SimpleXMLElement($tableConfigAsXml)
        );

        $this->driver->beginTableDataDump($tableSchema, $tableConfig);
        $this->driver->dumpTableRow($tableRow, $tableSchema, $tableConfig);
        $this->driver->endTableDataDump($tableSchema, $tableConfig);

        $outputFile = self::OUTPUT_DIRECTORY.'/my-table.csv';
        $this->assertFileExists($outputFile);

        return array_map('str_getcsv', file($outputFile));
    }
}
