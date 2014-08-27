<?php

// we mostly just need to require autoloader and save test directory
define('ZABBIX_NEW_TEST_DIR', __DIR__);
require __DIR__.'/vendor/autoload.php';

require_once __DIR__.'/../../include/defines.inc.php';
require_once __DIR__.'/../../include/func.inc.php';

// register autoloader
require_once __DIR__.'/../../include/classes/core/CAutoloader.php';

$autoloader = new CAutoloader(array(
	__DIR__.'/../../include/classes/core',
	__DIR__.'/../../include/classes/db',
));
$autoloader->register();
