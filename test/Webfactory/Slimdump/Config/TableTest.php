<?php

namespace Webfactory\Slimdump\Config;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

class TableTest extends TestCase
{
    public function testGetSelectExpressionWithFullTableConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals('`testColumnName`', $table->getSelectExpression('testColumnName', false));
    }

    public function testGetSelectExpressionWithNoBlobConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="noblob" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals('NULL', $table->getSelectExpression('testColumnName', true));
    }

    public function testGetSelectExpressionWithBlobColumnAndFullConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertStringContainsString('HEX', $table->getSelectExpression('testColumnName', true));
    }

    public function testGetSelectExpressionWithSchemaConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="schema" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertFalse($table->isDataDumpRequired());
        $this->assertTrue($table->isSchemaDumpRequired());
    }

    public function testInvalidConfiguration()
    {
        $this->expectException(InvalidDumpTypeException::class);

        $xml = '<?xml version="1.0" ?>
                <table name="test" dump="xxx" />';

        $xmlElement = new SimpleXMLElement($xml);

        new Table($xmlElement);
    }

    public function testSelectCondition()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" condition="`first_name` LIKE \'foo%\'" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals(' WHERE `first_name` LIKE \'foo%\'', $table->getCondition());
    }

    public function testSelectConditionWhenConditionIsEmpty()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" condition="   " />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->isDataDumpRequired());
        $this->assertEquals('', $table->getCondition());
    }

    public function testKeepAutoIncrementWhenNotSet()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertTrue($table->keepAutoIncrement());
    }

    public function testKeepAutoIncrementWhenSet()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="dicht*" dump="full" keep-auto-increment="false" />';

        $xmlElement = new SimpleXMLElement($xml);

        $table = new Table($xmlElement);

        $this->assertFalse($table->keepAutoIncrement());
    }

    public function testDumpTriggerDefaultSetting()
    {
        $xml = '<?xml version="1.0" ?>
                <table name="*" dump="full" />';

        $xmlElement = new SimpleXMLElement($xml);
        $table = new Table($xmlElement);

        $this->assertEquals(Table::DEFINER_NO_DEFINER, $table->getDumpTriggersLevel());
        $this->assertTrue($table->isTriggerDumpRequired());
    }

    /**
     * @dataProvider  dumpTriggerAttributeValues
     *
     * @param string $value
     *
     * @throws mixed InvalidDumpTypeException
     */
    public function testDumpTriggerAttribute($value, $expected)
    {
        $xml = '<?xml version="1.0" ?>
                <table name="*" dump="full" dump-triggers="'.$value.'" />';

        $xmlElement = new SimpleXMLElement($xml);
        $table = new Table($xmlElement);

        $this->assertEquals($table->getDumpTriggersLevel(), $expected);
        $this->assertEquals($table->isTriggerDumpRequired(), Table::TRIGGER_SKIP !== $expected);
    }

    public function dumpTriggerAttributeValues()
    {
        return [
            ['true', Table::DEFINER_NO_DEFINER],
            ['false', Table::TRIGGER_SKIP],
            ['none', Table::TRIGGER_SKIP],
            ['no-definer', Table::DEFINER_NO_DEFINER],
            ['keep-definer', Table::DEFINER_KEEP_DEFINER],
        ];
    }
}
