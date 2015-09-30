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
            $pattern = str_replace(array('*', '?'), array('(.*)', '.'), $table->getName());
            $this->tables[$pattern] = $table;
        }
    }

    public function findTable($tableName)
    {
        krsort($this->tables);

        foreach ($this->tables as $pattern => $table) {
            if (preg_match("/^$pattern$/i", $tableName)) {
                return $table;
            }
        }
    }
}
