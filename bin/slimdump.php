<?php

use \Webfactory\Slimdump\Config\Config;
use \Webfactory\Slimdump\Config\ConfigBuilder;
use \Webfactory\Slimdump\Database\Dumper;

$db = connect(array_shift($_SERVER['argv']));

$config = ConfigBuilder::createConfigurationFromConsecutiveFiles($_SERVER['argv']);

processConfig($config, $db);

/**
 * @param Config $config
 * @param Zend_Db_Adapter_Abstract $db
 * @internal param string $file
 */
function processConfig(Config $config, $db)
{
    $dumper = new Dumper();

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
}
