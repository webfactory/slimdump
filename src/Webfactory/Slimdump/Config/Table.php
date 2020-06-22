<?php

namespace Webfactory\Slimdump\Config;

use Doctrine\DBAL\Connection;
use RuntimeException;
use SimpleXMLElement;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

/**
 * This is a class representation of a configured table.
 * This is _not_ a representation of a database table.
 */
class Table
{
    public const TRIGGER_SKIP = 0;
    public const DEFINER_NO_DEFINER = 1;
    public const DEFINER_KEEP_DEFINER = 2;

    private $selector;
    private $dump;

    /** @var bool */
    private $keepAutoIncrement;

    /** @var int */
    private $dumpTriggers;

    /** @var int */
    private $viewDefiner;

    /** @var SimpleXMLElement */
    private $config;

    private $columns = [];

    public function __construct(SimpleXMLElement $config)
    {
        $this->config = $config;

        $attr = $config->attributes();
        $this->selector = (string) $attr->name;

        $const = 'Webfactory\Slimdump\Config\Config::'.strtoupper((string) $attr->dump);

        if (\defined($const)) {
            $this->dump = \constant($const);
        } else {
            throw new InvalidDumpTypeException(sprintf('Invalid dump type %s for table %s.', $attr->dump, $this->selector));
        }

        $this->keepAutoIncrement = self::attributeToBoolean($attr->{'keep-auto-increment'}, true);
        $this->dumpTriggers = self::parseDumpTriggerAttribute($attr->{'dump-triggers'});
        $this->viewDefiner = self::parseViewDefinerAttribute($attr->{'view-definer'});

        foreach ($config->column as $columnConfig) {
            $column = new Column($columnConfig);
            $this->columns[$column->getSelector()] = $column;
        }
    }

    /**
     * @param SimpleXMLElement[]|string|null $attribute
     * @param bool                           $defaultValue
     *
     * @return bool
     */
    private static function attributeToBoolean($attribute, $defaultValue)
    {
        if (null === $attribute) {
            return $defaultValue;
        }

        return 'true' === $attribute;
    }

    private static function parseDumpTriggerAttribute(?string $value)
    {
        if (null === $value) {
            return self::DEFINER_NO_DEFINER; // default
        }

        if ('true' === $value) {
            return self::DEFINER_NO_DEFINER; // BC
        }

        if ('false' === $value) {
            return self::TRIGGER_SKIP;
        }

        if ('none' === $value) {
            return self::TRIGGER_SKIP;
        }

        if ('no-definer' === $value) {
            return self::DEFINER_NO_DEFINER;
        }

        if ('keep-definer' === $value) {
            return self::DEFINER_KEEP_DEFINER;
        }

        throw new RuntimeException("Unsupported value '$value' for the 'dump-triggers' setting.");
    }

    private static function parseViewDefinerAttribute($value)
    {
        if (null === $value || 'no-definer' === $value) {
            return self::DEFINER_NO_DEFINER;
        }

        if ('keep-definer' === $value) {
            return self::DEFINER_KEEP_DEFINER;
        }

        throw new RuntimeException("Unsupported value '$value' for the 'view-definer' setting.");
    }

    /**
     * @return string
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return bool
     */
    public function isSchemaDumpRequired()
    {
        return $this->dump >= Config::SCHEMA;
    }

    /**
     * @return bool
     */
    public function isDataDumpRequired()
    {
        return $this->dump >= Config::NOBLOB;
    }

    /**
     * @return bool
     */
    public function isTriggerDumpRequired()
    {
        return $this->dumpTriggers > self::TRIGGER_SKIP;
    }

    /**
     * @return int
     */
    public function getDumpTriggersLevel()
    {
        return $this->dumpTriggers;
    }

    /**
     * @return int
     */
    public function getViewDefinerLevel()
    {
        return $this->viewDefiner;
    }

    /**
     * @return bool
     */
    public function keepAutoIncrement()
    {
        return $this->keepAutoIncrement;
    }

    /**
     * @param string $columnName
     * @param bool   $isBlobColumn
     *
     * @return string
     */
    public function getSelectExpression($columnName, $isBlobColumn)
    {
        $dump = $this->dump;

        if ($column = $this->findColumn($columnName)) {
            $dump = $column->getDump();
        }

        if ($isBlobColumn) {
            if (Config::NOBLOB === $dump) {
                return 'NULL';
            }

            return "IF(ISNULL(`$columnName`), NULL, IF(`$columnName`='', '', CONCAT('0x', HEX(`$columnName`))))";
        }

        return "`$columnName`";
    }

    /**
     * @param string      $columnName
     * @param string|null $value
     * @param bool        $isBlobColumn
     * @param Connection  $db
     *
     * @return string
     */
    public function getStringForInsertStatement($columnName, $value, $isBlobColumn, Connection $db)
    {
        if (null === $value) {
            return 'NULL';
        }

        if ('' === $value) {
            return '""';
        }

        if ($column = $this->findColumn($columnName)) {
            return $db->quote($column->processRowValue($value));
        }

        if ($isBlobColumn) {
            return $value;
        }

        return $db->quote($value);
    }

    /**
     * @return string - The WHERE condition.
     */
    public function getCondition()
    {
        $condition = (string) $this->config->attributes()->condition;

        if ('' !== trim($condition)) {
            return ' WHERE '.$condition;
        }

        return null;
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
