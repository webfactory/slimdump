<?php

namespace Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

/**
 * Class Column
 * @package Webfactory\Slimdump\Config
 *
 * This is a class representation of a configured column.
 * This is _not_ a representation of a database column.
 */
class Column
{

    private $config = null;
    private $fakerReplacer;

    /**
     * Column constructor.
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
            throw new InvalidDumpTypeException(sprintf("Invalid dump type %s for column %s.", $attr->dump, $this->selector));
        }

        $this->fakerReplacer = new FakerReplacer();
    }

    /**
     * @return string
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return mixed
     */
    public function getDump() {
        return $this->dump;
    }

    /**
     * @param string $value
     * @return mixed|string
     */
    public function processRowValue($value) {
        if ($this->dump == Config::MASKED) {
            return preg_replace('/[a-z0-9]/i', 'x', $value);
        }

        if ($this->dump == Config::REPLACE) {
            /** @var \SimpleXMLElement $replacement */
            $replacementName = (string)$this->config->attributes()->replacement;



            if ($this->fakerReplacer->isFakerColumn($replacementName)) {

                return $this->fakerReplacer->generateReplacement($replacementName);
            }

            return $this->config->attributes()->replacement;
        }

        if ($this->dump == Config::BLANK) {
            return '';
        }

        return $value;
    }
}
