<?php

namespace Webfactory\Slimdump\Config;

use phpDocumentor\Reflection\Types\Array_;
use PHPUnit\Framework\TestCase;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

class ColumnTest extends TestCase
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

    public function testInvalidConfiguration()
    {
        $this->expectException(InvalidDumpTypeException::class);

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

    public function testReplaceColumnWithUniqeValue()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="FAKER_unique->randomDigit" />';
        $xmlElement = new \SimpleXMLElement($xml);
        $columnConfig = new Column($xmlElement);

        $firstGeneratedValue = $columnConfig->processRowValue('test value');
        $secondGeneratedValue = $columnConfig->processRowValue('test value');

        $this->assertNotEquals(
            $firstGeneratedValue,
            $secondGeneratedValue,
            'FAKER_unique->randomDigit generated the same value: "' . $firstGeneratedValue . '" twice'
        );
    }
}
