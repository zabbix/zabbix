<?php
// Zabbix GUI configuration file.
global $DB;

$DB['TYPE']				= '{DBTYPE}';
$DB['SERVER']			= '{DBHOST}';
$DB['PORT']				= '0';
$DB['DATABASE']			= '{DBNAME}';
$DB['USER']				= '{DBUSER}';
$DB['PASSWORD']			= '{DBPASSWORD}';
// Schema name. Used for IBM DB2 and PostgreSQL.
$DB['SCHEMA']			= '';

$ZBX_SERVER				= 'localhost';
$ZBX_SERVER_PORT		= '10051';
$ZBX_SERVER_NAME		= 'TEST_SERVER_NAME';

$IMAGE_FORMAT_DEFAULT	= IMAGE_FORMAT_PNG;

// Runtime error collection block.
if (!file_exists(PHPUNIT_ERROR_LOG)) {
	file_put_contents(PHPUNIT_ERROR_LOG, '');
	chmod(PHPUNIT_ERROR_LOG, 0666);
}

if (!defined('PHPUNIT_BASEDIR')) {
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		// Check if error control operator was used.
		if (error_reporting() & $errno) {
			file_put_contents(PHPUNIT_ERROR_LOG, $errfile.' ('.$errline.'): '.$errstr."\n", FILE_APPEND);
		}

		return false;
	}, E_ALL | E_STRICT);

	set_exception_handler(function ($exception) {
		file_put_contents(PHPUNIT_ERROR_LOG, $exception."\n", FILE_APPEND);
	});
}
?>
