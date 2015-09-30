<?php
namespace Webfactory\Slimdump\Config;

class Table
{
    private $name;
    private $dump;

    private $columns = array();

    public function __construct($config)
    {
        $attr = $config->attributes();
        $this->name = (string) $attr->name;

        $const = 'Webfactory\Slimdump\Config\Config::' . strtoupper((string)$attr->dump);

        if (defined($const)) {
            $this->dump = constant($const);
        } else {
            throw new \RuntimeException(sprintf("Invalid dump type %s for table %s.", $this->dump, $this->name));
        }

        foreach ($config->column as $columnConfig) {
            $column = new Column($columnConfig);
            $this->columns[$column->getName()] = $column;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getDump()
    {
        return $this->dump;
    }
}
