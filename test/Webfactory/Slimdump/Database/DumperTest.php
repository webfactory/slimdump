<?php

namespace Webfactory\Slimdump\Database;

use Symfony\Component\Console\Output\BufferedOutput;
use Webfactory\Slimdump\Config\Table;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    /** @var  \PHPUnit_Framework_MockObject_MockObject */
    protected $dbMock;

    /**
     * @before
     */
    public function setup()
    {
        $this->dbMock = $this->getMockBuilder('\Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDumpSchemaWithNormalConfiguration()
    {
        $outputBuffer = new BufferedOutput();
        $dumper = new Dumper($outputBuffer);

        $this->dbMock->method('fetchColumn')->willReturn('CREATE TABLE statement');

        $dumper->dumpSchema('test', $this->dbMock);
        $output = $outputBuffer->fetch();

        $this->assertContains('DROP TABLE IF EXISTS', $output);
        $this->assertContains('CREATE TABLE statement', $output);
    }

    public function testDumpDataWithFullConfiguration()
    {
        $outputBuffer = new BufferedOutput();
        $dumper = new Dumper($outputBuffer);

        $pdoMock = $this->getMock('\stdClass', ['setAttribute']);

        $this->dbMock->expects($this->any())->method('getConnection')->willReturn($pdoMock);

        $this->dbMock->expects($this->any())
            ->method('fetchOne')
            ->willReturn(2);
        $this->dbMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return [
                        [
                            'Field' => 'col1',
                            'Type'  => 'varchar',
                        ],
                        [
                            'Field' => 'col2',
                            'Type'  => 'blob',
                        ],
                    ];
                }

                throw new \RuntimeException('Unexpected fetchAll-call: ' . $query);
            });
        $this->dbMock->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function ($value) {
                return $value;
            });

        $this->dbMock->expects($this->any())->method('query')->willReturn([
            [
                'col1' => 'value1.1',
                'col2' => 'value1.2',
            ],
            [
                'col1' => 'value2.1',
                'col2' => 'value2.2',
            ],
        ]);

        $table = new Table(new \SimpleXMLElement('<table name="test" dump="full" />'));

        $dumper->dumpData('test', $table, $this->dbMock);
        $output = $outputBuffer->fetch();

        $this->assertContains('INSERT INTO', $output);
    }
}
