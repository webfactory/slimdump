<?php

namespace Webfactory\Slimdump\Database;

use Webfactory\Slimdump\Config\Table;

class Dumper
{

    /**
     * @param string $table
     * @param \Zend_Db_Adapter_Abstract $db
     */
    public function dumpSchema($table, $db)
    {
        print "SET NAMES utf8;\n";
        print "-- BEGIN STRUCTURE $table \n";
        print "DROP TABLE IF EXISTS `$table`;\n";
        print $db->query("SHOW CREATE TABLE `$table`")->fetchColumn(1) . ";\n\n";
    }

    /**
     * @param string $table
     * @param Table $tableConfig
     * @param \Zend_Db_Adapter_Abstract $db
     */
    public function dumpData($table, Table $tableConfig, $db)
    {
        print "SET NAMES utf8;\n";
        print "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $cols = $this->cols($table, $db);

        $s = "SELECT ";
        $first = true;
        foreach (array_keys($cols) as $name) {
            $isBlobColumn = $this->isBlob($name, $cols);

            if (!$first) {
                $s .= ', ';
            }

            $s .= $tableConfig->getSelectExpression($name, $isBlobColumn);
            $s .= " AS `$name`";

            $first = false;
        }
        $s .= " FROM `$table`";

        print "-- BEGIN DATA $table \n";

        $bufferSize = 0;
        $max = 100 * 1024 * 1024; // 100 MB
        $numRows = $db->fetchOne("SELECT COUNT(*) FROM $table");
        $count = 0;

        $db->getConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        foreach ($db->query($s) as $row) {

            fprintf(STDERR, "\rDumping $table: %3u%%", (100 * ++$count) / $numRows);

            $b = $this->rowLengthEstimate($row);

            // Start a new statement to ensure that the line does not get too long.
            if ($bufferSize && $bufferSize + $b > $max) {
                print ";\n";
                $bufferSize = 0;
            }

            if ($bufferSize == 0) {
                print $this->insertValuesStatement($table, $cols);
            } else {
                print ",";
            }

            $firstCol = true;
            print "\n(";

            foreach ($row as $name => $value) {
                $isBlobColumn = $this->isBlob($name, $cols);

                if (!$firstCol) {
                    print ", ";
                }

                print $tableConfig->getStringForInsertStatement($name, $value, $isBlobColumn, $db);
                $firstCol = false;
            }
            print ")";
            $bufferSize += $b;
        }

        $db->getConnection()->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if ($bufferSize) {
            print ";\n";
        }

        fputs(STDERR, "\n");

        print "\n";
        print "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    }

    /**
     * @param string $table
     * @param \Zend_Db_Adapter_Abstract $db
     * @return array
     */
    protected function cols($table, $db)
    {
        $c = array();
        foreach ($db->fetchAll("SHOW COLUMNS FROM `$table`") as $row) {
            $c[$row['Field']] = $row['Type'];
        }
        return $c;
    }

    /**
     * @param string $table
     * @param array(string=>mixed) $cols
     * @return string
     */
    protected function insertValuesStatement($table, $cols)
    {
        return "INSERT INTO `$table` (`" . implode(array_keys($cols), '`, `') . "`) VALUES ";
    }

    /**
     * @param string $col
     * @param array $definitions
     * @return bool
     */
    protected function isBlob($col, array $definitions)
    {
        return stripos($definitions[$col], 'blob') !== false;
    }

    /**
     * @param array $row
     * @return int
     */
    protected function rowLengthEstimate(array $row)
    {
        $l = 0;
        foreach ($row as $value) {
            $l += strlen($value);
        }
        return $l;
    }

}