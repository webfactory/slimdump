<?php

namespace Webfactory\Slimdump\Config;

use SimpleXMLElement;

/**
 * This is a class representation of the configuration file(s) given.
 */
class Config
{
    public const NONE = 1;
    public const SCHEMA = 2;
    public const NOBLOB = 3;
    public const MASKED = 4;
    public const FULL = 5;
    public const BLANK = 6;
    public const REPLACE = 7;

    private $tables = [];

    public function __construct(SimpleXMLElement $xml)
    {
        foreach ($xml->table as $tableConfig) {
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
    public function merge(self $other)
    {
        $this->tables = array_merge($this->tables, $other->getTables());
    }

    /**
     * @param string $tableName
     *
     * @return Table
     */
    public function findTable($tableName)
    {
        return self::findBySelector($this->tables, $tableName);
    }

    /**
     * @param string $needle
     *
     * @return mixed
     */
    public static function findBySelector(array $haystack, $needle)
    {
        krsort($haystack);

        foreach ($haystack as $selector => $config) {
            $pattern = str_replace(['*', '?'], ['(.*)', '.'], $selector);
            if (preg_match("/^$pattern$/i", $needle)) {
                return $config;
            }
        }

        return null;
    }

    public function getTables()
    {
        return $this->tables;
    }
}
