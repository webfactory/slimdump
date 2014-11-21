<?php

	function connect($dsn) {
		try {
			$db = Zend_Db::factory('Pdo_Mysql', parseDSN($dsn));
			$db->query('SET NAMES utf8');
			return $db;
		} catch (Exception $e) {
			fail("Database error: " . $e->getMessage());
		}
	}

	function parseDSN($dsn) {
		if(!preg_match('~^mysql://([^:@]*)(?::([^@]+))?@([^:/]+)(?::(\d+))?/([\w-]+)$~', $dsn, $matches)) throw new Exception('DSN could not be parsed');

		$params = array();

		if(isset($matches[1])) $params['username'] = $matches[1];
		if(isset($matches[2])) $params['password'] = $matches[2];
		if(isset($matches[3])) $params['host'] = $matches[3];
		if(isset($matches[4])) $params['port'] = $matches[4];
		if(isset($matches[5])) $params['dbname'] = $matches[5];

		return  $params;
	}

	function fail($msg) {
		fwrite(STDERR, "$msg\n");
		exit(1);
	}
	
	function warn($msg) {
		fwrite(STDERR, "Warn: $msg\n");
	}

    function rglob($pattern, $flags = 0, $path = '') {
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

