<?php

use \Webfactory\Slimdump\Config\ConfigBuilder;
use \Webfactory\Slimdump\Slimdump;

$db = connect(array_shift($_SERVER['argv']));

$config = ConfigBuilder::createConfigurationFromConsecutiveFiles($_SERVER['argv']);
$slimdump = new Slimdump();
$slimdump->dump($config, $db);
