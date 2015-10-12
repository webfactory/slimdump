<?php

namespace Webfactory\Slimdump\Config;

class TableTest extends \PHPUnit_Framework_TestCase
{

    public function testGetSelectExpressionWithFullTableConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals('testColumnName', $table->getSelectExpression('testColumnName', false));
    }

    public function testGetSelectExpressionWithNoBlobConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="noblob" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals('NULL', $table->getSelectExpression('testColumnName', true));
    }

    public function testGetSelectExpressionWithBlobColumnAndFullConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertContains('HEX', $table->getSelectExpression('testColumnName', true));
    }

    public function testGetSelectExpressionWithSchemaConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="schema" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertFalse($table->isDataDumpRequired());
        $this->assertTrue($table->isSchemaDumpRequired());
    }

    /**
     * @expectedException \Webfactory\Slimdump\Exception\InvalidDumpTypeException
     */
    public function testInvalidConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="test" dump="xxx" />';

        $xmlElement = new \SimpleXMLElement($xml);

        new Table($xmlElement);
    }

}
