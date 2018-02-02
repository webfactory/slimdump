<?php

namespace Webfactory\Slimdump;
/**
 * @param string $dsn
 * @return \Doctrine\DBAL\Driver
 */
function connect($dsn)
{
    try {
        return \Doctrine\DBAL\DriverManager::getConnection(
            array('url' => $dsn, 'charset' => 'utf8', 'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver')
        );
    } catch (Exception $e) {
        fail("Database error: " . $e->getMessage());
    }
}

function fail($msg)
{
    fwrite(STDERR, "$msg\n");
    exit(1);
}

function warn($msg)
{
    fwrite(STDERR, "Warn: $msg\n");
}

function rglob($pattern, $flags = 0, $path = '')
{
    if (!$path && ($dir = dirname($pattern)) != '.') {
        if ($dir == '\\' || $dir == '/') {
            $dir = '';
        }
        return rglob(basename($pattern), $flags, $dir . '/');
    }
    $paths = glob($path . '*', GLOB_ONLYDIR | GLOB_NOSORT);
    $files = glob($path . $pattern, $flags);
    foreach ($paths as $p) {
        $files = array_merge($files, rglob($pattern, $flags, $p . '/'));
    }
    return $files;
}
