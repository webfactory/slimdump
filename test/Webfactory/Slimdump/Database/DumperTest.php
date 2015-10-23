<?php

namespace Webfactory\Slimdump\Database;

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
        $this->dbMock = $this->getMockBuilder('\Zend_Db_Adapter_Pdo_Mysql')
                        ->disableOriginalConstructor()
                        ->getMock();
    }

    public function testDumpSchemaWithNormalConfiguration()
    {
        $dumper = new Dumper();

        $statementMock = $this->getMock('\Zend_Db_Statement_Interface');
        $statementMock->expects($this->any())
            ->method('fetchColumn')
            ->willReturn('CREATE TABLE statement');
        $this->dbMock->expects($this->any())->method('query')->willReturn($statementMock);

        ob_start();
        $dumper->dumpSchema('test', $this->dbMock);
        $output = ob_get_clean();

        $this->assertContains('DROP TABLE IF EXISTS', $output);
        $this->assertContains('CREATE TABLE statement', $output);
    }

    public function testDumpDataWithFullConfiguration()
    {
        $dumper = new Dumper();

        $pdoMock = $this->getMock('\stdClass', array('setAttribute'));

        $this->dbMock->expects($this->any())->method('getConnection')->willReturn($pdoMock);

        $this->dbMock->expects($this->any())
            ->method('fetchOne')
            ->willReturn(2);
        $this->dbMock->expects($this->any())
            ->method('fetchAll')
            ->willReturnCallback(function($query) {
                if (strpos($query, 'SHOW COLUMNS') !== false) {
                    return array(
                        array(
                            'Field' => 'col1',
                            'Type' => 'varchar'
                        ),
                        array(
                            'Field' => 'col2',
                            'Type' => 'blob'
                        )
                    );
                }

                throw new \RuntimeException('Unexpected fetchAll-call: ' . $query);
            });
        $this->dbMock->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function($value) {
                return $value;
            });

        $this->dbMock->expects($this->any())->method('query')->willReturn(array(
            array(
                'col1' => 'value1.1',
                'col2' => 'value1.2'
            ),
            array(
                'col1' => 'value2.1',
                'col2' => 'value2.2'
            )
        ));

        $table = new Table(new \SimpleXMLElement('<table name="test" dump="full" />'));

        ob_start();
        $dumper->dumpData('test', $table, $this->dbMock);
        $output = ob_get_clean();

        $this->assertContains('INSERT INTO', $output);
    }
}
