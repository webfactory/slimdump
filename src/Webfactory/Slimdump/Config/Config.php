<?php
namespace Webfactory\Slimdump\Config;

class Config
{
    const NONE = 1;
    const SCHEMA = 2;
    const NOBLOB = 3;
    const FULL = 4;

    private $tables = array();

    public function load($file)
    {
        $xml = simplexml_load_file($file);

        foreach ($xml->table as $tableConfig) {
            $table = new Table($tableConfig);
            $this->tables[$table->getSelector()] = $table;
        }
    }

    /** @return Table */
    public function find($tableName)
    {
        krsort($this->tables);

        foreach ($this->tables as $selector => $config) {
            $pattern = str_replace(array('*', '?'), array('(.*)', '.'), $selector);
            if (preg_match("/^$pattern$/i", $tableName)) {
                return $config;
            }
        }
    }
}
