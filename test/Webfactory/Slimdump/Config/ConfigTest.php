<?php

namespace test\Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Config\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{

    public function testFindTableWithWildcardSelector()
    {
        $xml = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="dicht*" dump="full" />
                </slimdump>';

        $xmlElement = new \SimpleXMLElement($xml);

        $config = new Config();
        $config->parseXml($xmlElement);

        $table = $config->findTable('dichtikowski');
        $this->assertEquals('dicht*', $table->getSelector());
    }

    public function testFindTableWithOneCharWildcardSelector()
    {
        $xml = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="dicht?" dump="full" />
                </slimdump>';

        $xmlElement = new \SimpleXMLElement($xml);

        $config = new Config();
        $config->parseXml($xmlElement);

        $table = $config->findTable('dichtikowski');
        $this->assertNull($table, "One-Char-Wildcard selector should not match 'dichtikowski'!");

        $table2 = $config->findTable('dichta');
        $this->assertEquals('dicht?', $table2->getSelector());
    }

    /**
     * @expectedException \Webfactory\Slimdump\Exception\InvalidDumpTypeException
     */
    public function testInvalidConfiguration()
    {
        $xml = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="test" dump="XXX" />
                </slimdump>';

        $config = new Config();
        $config->parseXmlString($xml);
    }

    /**
     * @expectedException \Webfactory\Slimdump\Exception\InvalidXmlException
     */
    public function testInvalidXML()
    {
        $xml = '<?xml version="1.0"
            st" dump="XXX"
                slimdump>';

        $config = new Config();
        $config->parseXmlString($xml);
    }

}
