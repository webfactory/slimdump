<?php
namespace Webfactory\Slimdump\Config;

class Column
{
    public function __construct($config)
    {
        $attr = $config->attributes();
        $this->selector = (string) $attr->name;
        $this->dump = (string) $attr->dump;

        if (!in_array($this->dump, array('masked'))) {
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
}
