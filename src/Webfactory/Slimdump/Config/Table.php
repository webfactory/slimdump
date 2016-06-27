<?php

namespace Webfactory\Slimdump\Config;

use Doctrine\DBAL\Connection;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

/**
 * Class Table
 * @package Webfactory\Slimdump\Config
 *
 * This is a class representation of a configured table.
 * This is _not_ a representation of a database table.
 */
class Table
{
    private $selector;
    private $dump;

    /** @var boolean */
    private $keepAutoIncrement;

    /** @var \SimpleXMLElement */
    private $config;

    private $columns = array();

    /**
     * Table constructor.
     * @param \SimpleXMLElement $config
     * @throws InvalidDumpTypeException
     */
    public function __construct(\SimpleXMLElement $config)
    {
        $this->config = $config;

        $attr = $config->attributes();
        $this->selector = (string) $attr->name;

        $const = 'Webfactory\Slimdump\Config\Config::' . strtoupper((string)$attr->dump);

        if (defined($const)) {
            $this->dump = constant($const);
        } else {
            throw new InvalidDumpTypeException(sprintf("Invalid dump type %s for table %s.", $attr->dump, $this->selector));
        }

        $this->keepAutoIncrement = self::attributeToBoolean($attr->{'keep-auto-increment'}, true);

        foreach ($config->column as $columnConfig) {
            $column = new Column($columnConfig);
            $this->columns[$column->getSelector()] = $column;
        }
    }

    /**
     * @param \SimpleXMLElement[]|null $attribute
     * @param boolean $defaultValue
     * @return boolean
     */
    private static function attributeToBoolean($attribute, $defaultValue) {
        if ($attribute == null) {
            return $defaultValue;
        }
        return ($attribute == 'true') ? true : false;
    }

    /**
     * @return string
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return boolean
     */
    public function isSchemaDumpRequired()
    {
        return $this->dump >= Config::SCHEMA;
    }

    /**
     * @return boolean
     */
    public function isDataDumpRequired()
    {
        return $this->dump >= Config::NOBLOB;
    }

    /**
     * @return boolean
     */
    public function keepAutoIncrement()
    {
        return $this->keepAutoIncrement;
    }

    /**
     * @param string $columnName
     * @param boolean $isBlobColumn
     * @return string
     */
    public function getSelectExpression($columnName, $isBlobColumn)
    {
        $dump = $this->dump;

        if ($column = $this->findColumn($columnName)) {
            $dump = $column->getDump();
        }

        if ($isBlobColumn) {
            if ($dump == Config::NOBLOB) {
                return 'NULL';
            } else {
                return "IF(ISNULL(`$columnName`), NULL, IF(`$columnName`='', '', CONCAT('0x', HEX(`$columnName`))))";
            }
        } else {
            return "`$columnName`";
        }
    }

    /**
     * @param string      $columnName
     * @param string|null $value
     * @param boolean     $isBlobColumn
     * @param Connection  $db
     *
     * @return string
     */
    public function getStringForInsertStatement($columnName, $value, $isBlobColumn, Connection $db)
    {
        if ($value === null) {
            return 'NULL';
        } else if ($value === '') {
            return '""';
        } else {
            if ($column = $this->findColumn($columnName)) {
                return $db->quote($column->processRowValue($value));
            }

            if ($isBlobColumn) {
                return $value;
            }

            return $db->quote($value);
        }
    }

    /**
     * @return string - The WHERE condition.
     */
    public function getCondition()
    {
        $condition = (string)$this->config->attributes()->condition;

        if (trim($condition) !== '') {
            return ' WHERE ' . $condition;
        }
    }

    /**
     * @param string $columnName
     *
     * @return Column
     */
    private function findColumn($columnName)
    {
        return Config::findBySelector($this->columns, $columnName);
    }

}
