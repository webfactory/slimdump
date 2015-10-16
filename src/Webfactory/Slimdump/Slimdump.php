<?php

namespace Webfactory\Slimdump;

use Webfactory\Slimdump\Config\Config;
use Webfactory\Slimdump\Database\Dumper;

class Slimdump
{

    /**
     * @param Config $config
     * @param \Zend_Db_Adapter_Abstract $db
     */
    public function dump(Config $config, $db)
    {
        $dumper = new Dumper();
        $dumper->exportAsUTF8();
        $dumper->disableForeignKeys();

        foreach ($db->listTables() as $tableName) {
            $tableConfig = $config->findTable($tableName);

            if (null === $tableConfig) {
                continue;
            }

            if ($tableConfig->isSchemaDumpRequired()) {
                $dumper->dumpSchema($tableName, $db);

                if ($tableConfig->isDataDumpRequired()) {
                    $dumper->dumpData($tableName, $tableConfig, $db);
                }
            }
        }
        $dumper->enableForeignKeys();
    }

}