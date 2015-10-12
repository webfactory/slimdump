<?php

namespace Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Exception\InvalidXmlException;

class Config
{
    const NONE = 1;
    const SCHEMA = 2;
    const NOBLOB = 3;
    const MASKED = 4;
    const FULL = 5;
    const BLANK = 6;

    private $tables = array();

    /**
     * @param string $file
     */
    public function load($file)
    {
        $xml = file_get_contents($file);

        $this->parseXmlString($xml);
    }

    /**
     * @param string $xmlString
     * @throws InvalidXmlException
     */
    public function parseXmlString($xmlString) {
        libxml_use_internal_errors(true);
        $xmlElement = simplexml_load_string($xmlString);

        foreach(libxml_get_errors() as $error) {
            /** @var \LibXMLError $error */
            throw new InvalidXmlException("Invalid XML!");
        }

        $this->parseXml($xmlElement);
    }

    /**
     * @param \SimpleXMLElement $xml
     */
    public function parseXml(\SimpleXMLElement $xml)
    {
        foreach ($xml->table as $tableConfig) {
            /** @var \SimpleXMLElement $tableConfig */
            $table = new Table($tableConfig);
            $this->tables[$table->getSelector()] = $table;
        }
    }

    /**
     * @param string $tableName
     * @return Table
     */
    public function findTable($tableName)
    {
        return self::findBySelector($this->tables, $tableName);
    }

    /**
     * @param array $haystack
     * @param string $needle
     * @return mixed
     */
    public static function findBySelector(array $haystack, $needle)
    {
        krsort($haystack);

        foreach ($haystack as $selector => $config) {
            $pattern = str_replace(array('*', '?'), array('(.*)', '.'), $selector);
            if (preg_match("/^$pattern$/i", $needle)) {
                return $config;
            }
        }
    }

}
