<?php

namespace Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Exception\InvalidXmlException;

/**
 * Class Config.
 * @package Webfactory\Slimdump\Config
 *
 * This is a class representation of the configuration file(s) given.
 */
class Config
{
    const NONE = 1;
    const SCHEMA = 2;
    const NOBLOB = 3;
    const MASKED = 4;
    const FULL = 5;
    const BLANK = 6;
    const REPLACE = 7;

    private $tables = array();

    /**
     * Config constructor.
     * @param \SimpleXMLElement $xml
     */
    public function __construct(\SimpleXMLElement $xml)
    {
        foreach ($xml->table as $tableConfig) {
            /** @var \SimpleXMLElement $tableConfig */
            $table = new Table($tableConfig);
            $this->tables[$table->getSelector()] = $table;
        }
    }

    /**
     * Merge two configurations together.
     * If two configurations specify the same table,
     * the last one wins.
     *
     * @param Config $other
     */
    public function merge(Config $other)
    {
        $this->tables = array_merge($this->tables, $other->getTables());
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

    public function getTables()
    {
        return $this->tables;
    }

}
