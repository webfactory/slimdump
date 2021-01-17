<?php

namespace Webfactory\Slimdump\Config;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;
use Webfactory\Slimdump\Exception\InvalidReplacementStrategy;

class ColumnTest extends TestCase
{
    public function testProcessRowValueWithNormalConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="full" />';

        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('test value', $columnConfig->processRowValue('test value'));
    }

    public function testProcessRowValueWithBlankConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="blank" />';

        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEmpty($columnConfig->processRowValue('test value'));
    }

    public function testProcessRowValueWithMaskedConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="masked" />';

        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('xxxx@xxxx.xxx', $columnConfig->processRowValue('test@fest.com'));
    }

    public function testProcessRowValueFakerReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="FAKER_NAME" />';
        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);

        $fakedName = $columnConfig->processRowValue('original user name');
        $this->assertNotSame('original user name', $fakedName);
    }

    public function testProcessRowValueStandardReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="ANON" />';
        $xmlElement = new SimpleXMLElement($xml);

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

        $xmlElement = new SimpleXMLElement($xml);

        new Column($xmlElement);
    }

    public function testReplaceColumn()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="xxx" />';

        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('xxx', $columnConfig->processRowValue('test value'));
    }

    public function testReplaceColumnWithoutReplacement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" />';

        $xmlElement = new SimpleXMLElement($xml);

        $columnConfig = new Column($xmlElement);
        $this->assertEquals('', $columnConfig->processRowValue('test value'));
    }

    public function testReplaceColumnWithUniqueValue()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace" replacement="FAKER_unique->randomDigit" />';
        $xmlElement = new SimpleXMLElement($xml);
        $columnConfig = new Column($xmlElement);

        $firstGeneratedValue = $columnConfig->processRowValue('test value');
        $secondGeneratedValue = $columnConfig->processRowValue('test value');

        $this->assertNotEquals(
            $firstGeneratedValue,
            $secondGeneratedValue,
            'FAKER_unique->randomDigit generated the same value: "'.$firstGeneratedValue.'" twice'
        );
    }

    public function testReplaceColumnWithReplacementElement()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement value="updated"/>
                </column>';

        $columnConfig = new Column(new SimpleXMLElement($xml));

        $this->assertEquals('updated', $columnConfig->processRowValue('should replace'));
    }

    public function testReplaceColumnWithReplacementElementUsingRegexStrategy()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement strategy="regex" constraint="/^should replace$/" value="updated"/>
                </column>';

        $columnConfig = new Column(new SimpleXMLElement($xml));

        $this->assertEquals('original', $columnConfig->processRowValue('original'));
        $this->assertEquals('updated', $columnConfig->processRowValue('should replace'));
    }

    public function testReplaceColumnWithReplacementElementUsingRegexStrategyThrowsIfMissingConstraintAttr()
    {
        $this->expectException(InvalidReplacementStrategy::class);

        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement strategy="regex" value="updated"/>
                </column>';

        new Column(new SimpleXMLElement($xml));
    }

    public function testReplaceColumnWithReplacementElementUsingEqualsStrategy()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement strategy="eq" constraint="should replace" value="updated"/>
                </column>';

        $columnConfig = new Column(new SimpleXMLElement($xml));

        $this->assertEquals('original', $columnConfig->processRowValue('original'));
        $this->assertEquals('updated', $columnConfig->processRowValue('should replace'));
    }

    public function testReplaceColumnWithReplacementElementUsingNotEqualsStrategy()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement strategy="neq" constraint="original" value="updated"/>
                </column>';

        $columnConfig = new Column(new SimpleXMLElement($xml));

        $this->assertEquals('original', $columnConfig->processRowValue('original'));
        $this->assertEquals('updated', $columnConfig->processRowValue('should replace'));
    }

    public function testReplaceColumnWithReplacementElementsUsingMultipleStrategies()
    {
        $xml = '<?xml version="1.0" ?>
                <column name="test" dump="replace">
                    <replacement strategy="regex" constraint="/^pattern$/" value="updatedRegEx"/>
                    <replacement strategy="eq" constraint="something" value="updatedEQ"/>
                    <replacement strategy="neq" constraint="other" value="updatedNEQ"/>
                    <replacement value="fallback"/>
                </column>';

        $columnConfig = new Column(new SimpleXMLElement($xml));

        $this->assertEquals('updatedRegEx', $columnConfig->processRowValue('pattern'));
        $this->assertEquals('updatedEQ', $columnConfig->processRowValue('something'));
        $this->assertEquals('updatedNEQ', $columnConfig->processRowValue('something else'));
        $this->assertEquals('fallback', $columnConfig->processRowValue('other'));
    }
}
