<?php
namespace Webfactory\Slimdump\Config;

class Column
{

    /**
     * Column constructor.
     * @param \SimpleXMLElement $config
     */
    public function __construct(\SimpleXMLElement $config)
    {
        $attr = $config->attributes();
        $this->selector = (string) $attr->name;

        $const = 'Webfactory\Slimdump\Config\Config::' . strtoupper((string)$attr->dump);

        if (defined($const)) {
            $this->dump = constant($const);
        } else {
            throw new \RuntimeException(sprintf("Invalid dump type %s for column %s.", $this->dump, $this->selector));
        }
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

        if ($this->dump == Config::BLANK) {
            return '';
        }

        return $value;
    }
}
