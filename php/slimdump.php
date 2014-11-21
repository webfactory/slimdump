<?php

require(__DIR__ . '/../vendor/autoload.php');

array_shift($_SERVER['argv']);

if (!$_SERVER['argv']) {
    fail("Usage: slimdump {DSN} {config.xml ...}");
}

$db = connect(array_shift($_SERVER['argv']));

print "SET NAMES utf8;\n";
print "SET FOREIGN_KEY_CHECKS = 0;\n\n";

while ($config = array_shift($_SERVER['argv'])) {
    processConfig($config, $db);
}

/**
 * @param string $file
 * @param Zend_Db_Adapter_Abstract $db
 */
function processConfig($file, $db)
{
    $modes = array('none' => 0, 'schema' => 1, 'noblob' => 2, 'full' => 3);
    $config = parseConfig($file);

    foreach ($db->listTables() as $table) {
        $m = $modes[findMode($table, $config)];

        if ($m >= $modes['schema']) {
            dumpSchema($table, $db);
        }

        if ($m >= $modes['noblob']) {
            dumpData($table, $db, $m == $modes['noblob']);
        }
    }
}

/**
 * @param string $table
 * @param Zend_Db_Adapter_Abstract $db
 */
function dumpSchema($table, $db)
{
    print "-- BEGINN STRUKTUR $table \n";
    print "DROP TABLE IF EXISTS `$table`;\n";
    print $db->query("SHOW CREATE TABLE `$table`")->fetchColumn(1) . ";\n\n";
}

function parseConfig($file)
{
    $config = array();
    $xml = simplexml_load_file($file);

    foreach ($xml->table as $t) {
        $a = $t->attributes();
        $n = $a->name->__toString();
        $config[str_replace(array('*', '?'), array('(.*)', '.'), $n)] = $a->dump->__toString();
    }

    krsort($config);
    return $config;
}

function findMode($table, $config)
{
    foreach ($config as $pattern => $type) {
        if (preg_match("/^$pattern$/i", $table)) {
            return $type;
        }
    }
    return 'none';
}

/**
 * @param string $table
 * @param Zend_Db_Adapter_Abstract $db
 * @return array
 */
function cols($table, $db)
{
    $c = array();
    foreach ($db->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
        $c[$row['Field']] = $row['Type'];
    }
    return $c;
}

function insertValuesStatement($table, $db, $cols)
{
    print "INSERT INTO `$table` (`" . implode(array_keys($cols), '`, `') . "`) VALUES ";
}

function isBlob($col, $definitions)
{
    return stripos($definitions[$col], 'blob') !== false;
}

function rowLengthEstimate($row)
{
    $l = 0;
    foreach ($row as $value) {
        $l += strlen($value);
    }
    return $l;
}

/**
 * @param string $table
 * @param Zend_Db_Adapter_Abstract $db
 * @param bool $nullBlob
 */
function dumpData($table, $db, $nullBlob = false)
{
    $cols = cols($table, $db);

    $s = "SELECT ";
    $first = true;
    foreach (array_keys($cols) as $name) {
        if (!$first) {
            $s .= ', ';
        }
        if (isBlob($name, $cols)) {
            if ($nullBlob) {
                $s .= "NULL";
            } else {
                $s .= "IF(ISNULL(`$name`), 'NULL', IF(`$name`='', '\"\"', CONCAT('0x', HEX(`$name`))))";
            }
            $s .= " AS `$name`";
        } else {
            $s .= "`$name`";
        }
        $first = false;
    }
    $s .= " FROM `$table`";

    print "-- BEGINN DATEN $table \n";

    $firstRow = true;
    $bufferSize = 0;
    $max = 100 * 1024 * 1024; // 100 MB willk�rliche Grenze
    $numRows = $db->fetchOne("SELECT COUNT(*) FROM $table");
    $count = 0;

    $db->getConnection()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

    foreach ($db->query($s) as $row) {

        fprintf(STDERR, "\rDumping $table: %3u%%", (100 * ++$count) / $numRows);

        $b = rowLengthEstimate($row);

        // Diese Zeile zu printen wuerde das Statement zu
        // gro� machen. Neues Statement anfangen.
        if ($bufferSize && $bufferSize + $b > $max) {
            print ";\n";
            $bufferSize = 0;
        }

        if ($bufferSize == 0) {
            print insertValuesStatement($table, $db, $cols);
        } else {
            print ",";
        }

        $firstCol = true;
        print "\n(";

        foreach ($row as $name => $value) {
            if (!$firstCol) {
                print ", ";
            }
            if ($value === null) {
                print "NULL";
            } else {
                if (isBlob($name, $cols)) {
                    print $value;
                } else {
                    print $db->quote($value);
                }
            }
            $firstCol = false;
        }
        print ")";
        $bufferSize += $b;
    }

    $db->getConnection()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

    if ($bufferSize) {
        print ";\n";
    }

    fputs(STDERR, "\n");

    print "\n";
}

print "\nSET FOREIGN_KEY_CHECKS = 1;\n";
