<?php
require_once __DIR__ . '/vendor/autoload.php';
$base_dir = __DIR__ . '/';

$script_name = basename($_SERVER['PHP_SELF']);
$script_name = preg_replace('/\.php$/', '', $script_name);

$debug = 1;
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

// Circuit Cache DB settings
$cdb_host = "limon-db-rw.llnw.com";
$cdb_name = "limon_prod";
$cdb_user = "limon_user"; // privs: select,insert,update,delete
$cdb_pass = "o+-G7u5pO=W=h0BdHTu-+Rv56rq";

include($base_dir.'toolbox.php');


// for ezSQL help:  http://justinvincent.com/docs/ezsql/ez_sql_help.htm
$db = new ezSQL_mysql($db_user, $db_pass, $db_name, $db_host);
$ldb = new ezSQL_mysql($ldb_user, $ldb_pass, $ldb_name, $ldb_host);
$cdb = new ezSQL_mysql($cdb_user, $cdb_pass, $cdb_name, $cdb_host);

?>
