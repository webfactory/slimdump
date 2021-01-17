<?php

namespace Webfactory\Slimdump\Config;

use SimpleXMLElement;
use Webfactory\Slimdump\Exception\InvalidDumpTypeException;

/**
 * This is a class representation of a configured column.
 * This is _not_ a representation of a database column.
 */
class Column
{
    private $config;
    private $fakerReplacer;

    /** @var int */
    private $dump;

    /** @var string */
    private $selector;

    /** @var array */
    private $replacements = [];

    public function __construct(SimpleXMLElement $config)
    {
        $this->config = $config;

        $attr = $config->attributes();
        $this->selector = (string) $attr->name;

        $const = 'Webfactory\Slimdump\Config\Config::'.strtoupper((string) $attr->dump);

        if (\defined($const)) {
            $this->dump = \constant($const);
        } else {
            throw new InvalidDumpTypeException(sprintf('Invalid dump type %s for column %s.', $attr->dump, $this->selector));
        }

        if ($this->dump === Config::REPLACE) {
            if ($replacement = (string) $this->config->attributes()->replacement) {
                $this->replacements[] = Replacement::fromColumnReplacementAttr($replacement);
            } else {
                foreach ($config->replacement as $replacementConfig) {
                    $this->replacements[] = new Replacement($replacementConfig);
                }
            }
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
    public function getDump()
    {
        return $this->dump;
    }

    /**
     * @param string $value
     *
     * @return mixed|string
     */
    public function processRowValue($value)
    {
        if (Config::MASKED === $this->dump) {
            return preg_replace('/[a-z0-9]/i', 'x', $value);
        }

        if (Config::REPLACE === $this->dump) {
            if (empty($this->replacements)) {
                return '';
            }

            /** @var Replacement $replacement */
            foreach ($this->replacements as $replacement) {
                if ($replacement->matchesConstraint($value)) {
                    return $replacement->getReplacement($this->fakerReplacer);
                }
            }

            return $value;
        }

        if (Config::BLANK === $this->dump) {
            return '';
        }

        return $value;
    }
}
