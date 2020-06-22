<?php

namespace Webfactory\Slimdump\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleXMLElement;
use stdClass;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class DumperTest extends TestCase
{
    /** @var Connection|MockObject */
    protected $dbMock;

    /** @var OutputInterface */
    protected $outputBuffer;

    /** @var Dumper */
    protected $dumper;

    /**
     * @before
     */
    protected function setUp(): void
    {
        $this->outputBuffer = new BufferedOutput();
        $this->dumper = new Dumper($this->outputBuffer);

        $this->dbMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDumpSchemaWithNormalConfiguration()
    {
        $this->dbMock->method('fetchColumn')->willReturn('CREATE TABLE statement');

        $this->dumper->dumpSchema('test', $this->dbMock);
        $output = $this->outputBuffer->fetch();

        $this->assertStringContainsString('DROP TABLE IF EXISTS', $output);
        $this->assertStringContainsString('CREATE TABLE statement', $output);
    }

    public function testDumpDataWithFullConfiguration()
    {
        $pdoMock = $this->getMockBuilder(stdClass::class)->addMethods(['setAttribute'])->getMock();

        $this->dbMock->method('getWrappedConnection')->willReturn($pdoMock);

        $this->dbMock
            ->method('fetchColumn')
            ->willReturn(2);
        $this->dbMock
            ->method('fetchAll')
            ->willReturnCallback(
                static function ($query) {
                    if (false !== strpos($query, 'SHOW COLUMNS')) {
                        return [
                        [
                            'Field' => 'col1',
                            'Type' => 'varchar',
                        ],
                        [
                            'Field' => 'col2',
                            'Type' => 'blob',
                        ],
                    ];
                    }

                    throw new RuntimeException('Unexpected fetchAll-call: '.$query);
                });
        $this->dbMock
            ->method('quote')
            ->willReturnCallback(
                static function ($value) {
                    return $value;
                });

        $this->dbMock->method('query')->willReturn([
            [
                'col1' => 'value1.1',
                'col2' => 'value1.2',
            ],
            [
                'col1' => 'value2.1',
                'col2' => 'value2.2',
            ],
        ]);

        $table = new Table(new SimpleXMLElement('<table name="test" dump="full" />'));

        $this->dumper->dumpData('test', $table, $this->dbMock, false);
        $output = $this->outputBuffer->fetch();

        $this->assertStringContainsString('INSERT INTO', $output);
    }

    public function testQueriesAllTriggers()
    {
        $this->dbMock->expects($this->at(0))
            ->method('quote')
            ->with('test')
            ->willReturn("'test'");

        $this->dbMock->expects($this->at(1))
            ->method('fetchAll')
            ->with("SHOW TRIGGERS LIKE 'test'")
            ->willReturn([['Trigger' => 'trigger1'], ['Trigger' => 'trigger2']]);

        $this->dbMock->expects($this->at(2))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger1`');

        $this->dbMock->expects($this->at(3))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger2`');

        $this->dumper->dumpTriggers($this->dbMock, 'test');
    }

    public function testDumpsTriggerWithDefiner()
    {
        $this->dbMock->expects($this->at(0))
            ->method('quote')
            ->with('test')
            ->willReturn("'test'");

        $this->dbMock->expects($this->at(1))
            ->method('fetchAll')
            ->with("SHOW TRIGGERS LIKE 'test'")
            ->willReturn([['Trigger' => 'trigger1']]);

        $this->dbMock->expects($this->at(2))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger1`')
            ->willReturn('CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger1 ...');

        $this->dumper->dumpTriggers($this->dbMock, 'test', Table::DEFINER_KEEP_DEFINER);

        $output = $this->outputBuffer->fetch();
        $this->assertStringContainsString('CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger1 ...;', $output);
    }

    public function testDumpsTriggerWithoutDefiner()
    {
        $this->dbMock->expects($this->at(0))
            ->method('quote')
            ->with('test')
            ->willReturn("'test'");

        $this->dbMock->expects($this->at(1))
            ->method('fetchAll')
            ->with("SHOW TRIGGERS LIKE 'test'")
            ->willReturn([['Trigger' => 'trigger1']]);

        $this->dbMock->expects($this->at(2))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger1`')
            ->willReturn('CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger1 ...');

        $this->dumper->dumpTriggers($this->dbMock, 'test', Table::DEFINER_NO_DEFINER);

        $output = $this->outputBuffer->fetch();
        $this->assertStringContainsString('CREATE TRIGGER trigger1 ...;', $output);
    }
}
