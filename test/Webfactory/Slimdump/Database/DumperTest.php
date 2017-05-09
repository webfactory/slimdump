<?php

namespace Webfactory\Slimdump\Database;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webfactory\Slimdump\Config\Table;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    /** @var  \PHPUnit_Framework_MockObject_MockObject */
    protected $dbMock;

    /** @var  OutputInterface */
    protected $outputBuffer;

    /** @var Dumper */
    protected $dumper;

    /**
     * @before
     */
    public function setup()
    {
        $this->outputBuffer = new BufferedOutput();
        $this->dumper = new Dumper($this->outputBuffer);

        $this->dbMock = $this->getMockBuilder('\Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testDumpSchemaWithNormalConfiguration()
    {
        $this->dbMock->expects($this->any())->method('fetchColumn')->willReturn('CREATE TABLE statement');

        $this->dumper->dumpSchema('test', $this->dbMock);
        $output = $this->outputBuffer->fetch();

        $this->assertContains('DROP TABLE IF EXISTS', $output);
        $this->assertContains('CREATE TABLE statement', $output);
    }

    public function testDumpDataWithFullConfiguration()
    {
        $pdoMock = $this->getMock('\stdClass', array('setAttribute'));

        $this->dbMock->expects($this->any())->method('getWrappedConnection')->willReturn($pdoMock);

        $this->dbMock->expects($this->any())
            ->method('fetchColumn')
            ->willReturn(2);
        $this->dbMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return array(
                        array(
                            'Field' => 'col1',
                            'Type'  => 'varchar',
                        ),
                        array(
                            'Field' => 'col2',
                            'Type'  => 'blob',
                        ),
                    );
                }

                throw new \RuntimeException('Unexpected fetchAll-call: ' . $query);
            });
        $this->dbMock->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function ($value) {
                return $value;
            });

        $this->dbMock->expects($this->any())->method('query')->willReturn(array(
            array(
                'col1' => 'value1.1',
                'col2' => 'value1.2',
            ),
            array(
                'col1' => 'value2.1',
                'col2' => 'value2.2',
            ),
        ));

        $table = new Table(new \SimpleXMLElement('<table name="test" dump="full" />'));

        $this->dumper->dumpData('test', $table, $this->dbMock);
        $output = $this->outputBuffer->fetch();

        $this->assertContains('INSERT INTO', $output);
    }

    public function testDumpTriggers()
    {
        $t1 = 'CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger1 ...';
        $t2 = 'CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger2 ...';

        $this->dbMock->expects($this->at(0))
            ->method('fetchAll')
            ->with('SHOW TRIGGERS LIKE ?', ['test'])
            ->willReturn([['Trigger' => 'trigger1'], ['Trigger' => 'trigger2']]);

        $this->dbMock->expects($this->at(1))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger1`')
            ->willReturn('CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger1 ...');

        $this->dbMock->expects($this->at(2))
            ->method('fetchColumn')
            ->with('SHOW CREATE TRIGGER `trigger2`')
            ->willReturn('CREATE DEFINER=`somebody`@`myhost` TRIGGER trigger2 ...');

        $this->dumper->dumpTriggers($this->dbMock, 'test');

        $output = $this->outputBuffer->fetch();
        $this->assertContains("CREATE TRIGGER trigger1 ...;", $output);
        $this->assertContains("CREATE TRIGGER trigger2 ...;", $output);
    }
}
