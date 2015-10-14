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

        $config = new Config($xmlElement);

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

        $config = new Config($xmlElement);

        $table = $config->findTable('dichtikowski');
        $this->assertNull($table, "One-Char-Wildcard selector should not match 'dichtikowski'!");

        $table2 = $config->findTable('dichta');
        $this->assertEquals('dicht?', $table2->getSelector());
    }

    public function testMerge()
    {
        $xml1 = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="dicht*" dump="full" />
                </slimdump>';

        $config1 = new Config(new \SimpleXMLElement($xml1));

        $xml2 = '<?xml version="1.0" ?>
                <slimdump>
                    <table name="dicht*" dump="noblob" />
                </slimdump>';

        $config2 = new Config(new \SimpleXMLElement($xml2));

        $config1->merge($config2);

        $table = $config1->findTable('dichtikowski');
        $this->assertEquals('NULL', $table->getSelectExpression('testColumnName', true));
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

        $xmlElement = new \SimpleXMLElement($xml);

        new Config($xmlElement);
    }

}
