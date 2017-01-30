<?php

namespace Webfactory\Slimdump\Config;

class ColumnTest extends \PHPUnit_Framework_TestCase
{

    public function testProcessRowValueWithNormalConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="full" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('test value', $columnConfig->processRowValue('test value'));
    }

    public function testProcessRowValueWithBlankConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="blank" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEmpty($columnConfig->processRowValue('test value'));
    }

    public function testProcessRowValueWithMaskedConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="masked" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('xxxx@xxxx.xxx', $columnConfig->processRowValue('test@fest.com'));
    }

    public function testProcessRowValueFakerReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="FAKER_NAME" />';
        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);

        $fakedName = $columnConfig->processRowValue('original user name');
        $this->assertNotSame('original user name', $fakedName);
    }

    public function testProcessRowValueStandardReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="ANON" />';
        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);

        $replacedName = $columnConfig->processRowValue('original user name');
        // note: default replacement returns \SimpleXMLElement - just checking content here using assertEquals
        $this->assertEquals('ANON', $replacedName);
    }

    /**
     * @expectedException \Webfactory\Slimdump\Exception\InvalidDumpTypeException
     */
    public function testInvalidConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="xxx" />';

        $xmlElement = new \SimpleXMLElement($xml);

        new Column($xmlElement);
    }

    public function testReplaceColumn()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="xxx" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('xxx', $columnConfig->processRowValue('test value'));
    }

    public function testReplaceColumnWithoutReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" />';

        $xmlElement = new \SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('', $columnConfig->processRowValue('test value'));
    }

}
