<?php
// global $logfile;
global $apiurl, $apiver, $zabbix_user, $zabbix_password, $zabbix_token_file;
// global $db_host, $db_name, $db_user, $db_pass;
// global $ldb_host, $ldb_name, $ldb_user, $ldb_pass;
global $db, $ldb, $logger;

$script_name = basename($_SERVER['PHP_SELF']);
$script_name = preg_replace('/\.php$/', '', $script_name);


$logfile = '/tmp/debug-'.$script_name.'.log';
$logger = &Log::singleton('file', $logfile, 'LLNW', array('mode' => 0600, 'timeFormat' => '%X %x'));


// Zabbix API settings
$apiurl = 'http://zabbix-stage.llnw.net/api_jsonrpc.php';
$apiver = '2.0';
$zabbix_user     = 'zabbix-api-llnw-zabbix';
$zabbix_password = "MgIym4h%$'gJroD%eTef80<";
$zabbix_token_file = '/tmp/zbx-api-token_'.$script_name.'.tmp';


// Zabbix DB settings
// $db_host = 'zabbix-dbha.phx2.llnw.net';
// $db_host = 'zabbix-db02.phx2.llnw.net';
$db_host = 'zabbix20-qa-db.llnw.com';
$db_name = 'zabbix20';
$db_user = 'zabbix20_admin';
$db_pass = 'test';

// LLNW DB (custom api) settings
// $ldb_host = 'zabbix-dbha.phx2.llnw.net';
// $ldb_host = 'zabbix-db02.phx2.llnw.net';
$ldb_host = 'zbx-llnw-dev-db-rw';
$ldb_name = 'zbx_llnw';
$ldb_user = 'zbx_llnw_api_rw';
$ldb_pass = 'test';

// for ezSQL help:  http://justinvincent.com/docs/ezsql/ez_sql_help.htm
$db = new ezSQL_mysql($db_user, $db_pass, $db_name, $db_host);
$ldb = new ezSQL_mysql($ldb_user, $ldb_pass, $ldb_name, $ldb_host);
