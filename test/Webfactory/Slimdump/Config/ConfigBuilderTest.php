<?php

namespace Webfactory\Slimdump\Config;

use PHPUnit\Framework\TestCase;
use Webfactory\Slimdump\Exception\InvalidXmlException;

class ConfigBuilderTest extends TestCase
{
    public function testInvalidXML()
    {
        $this->expectException(InvalidXmlException::class);

        $xml = '<?xml version="1.0"
            st" dump="XXX"
                slimdump>';

        ConfigBuilder::createFromXmlString($xml);
    }

    public function testConsecutiveConfigurationsDoNotChangeMergeOrder()
    {
        $xml1 = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="test" dump="full" />
                </slimdump>';

        $xml2 = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="test" dump="noblob" />
                </slimdump>';

        $config = ConfigBuilder::createConfigurationFromConsecutiveXmlStrings([$xml1, $xml2]);
        $table = $config->findTable('test');
        $this->assertEquals('NULL', $table->getSelectExpression('testColumnName', true));
    }

    public function testMergeOrderInSameFileOverrides()
    {
        $xml = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="test" dump="noblob" />
                    <table name="xxx" dump="full" />
                    <table name="test" dump="full" />
                </slimdump>';

        $config = ConfigBuilder::createFromXmlString($xml);
        $table = $config->findTable('test');
        $this->assertNotEquals('NULL', $table->getSelectExpression('testColumnName', true));
    }
}
