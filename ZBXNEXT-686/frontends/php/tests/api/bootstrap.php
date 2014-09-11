<?php

// we mostly just need to require autoloader and save test directory
define('ZABBIX_NEW_TEST_DIR', __DIR__);
require __DIR__.'/vendor/autoload.php';

// register autoloader
require_once dirname(__FILE__).'/../../include/classes/core/Z.php';

// mock some of the environment variables to avoid undefined index errors
// TODO: get rid of these mocks
$_SERVER['REMOTE_ADDR'] = '';
$_SERVER['SERVER_SOFTWARE'] = '';

// start a whole zabbix instance so whe can run test with API fixtures
// TODO: check if there's a better way to do that
Z::getInstance()->run(ZBase::EXEC_MODE_COMMAND);

// register the paths for additional test-specific classes
// TODO: merge them with the common include paths
$autoloader = new CAutoloader(array(
	__DIR__.'/include/validators',
));
$autoloader->register();
