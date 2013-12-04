<?php
define('ZBX_RPC_REQUEST', 1);
global $ZBX_CONFIGURATION_FILE;
$ZBX_CONFIGURATION_FILE = '../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../include/config.inc.php';

// Allow regular zabbix sessions to pass-through to perform auth.
if ( strlen($_POST['key']) != 32 ) {
	exit;
}
else {
	CWebUser::$data['sessionid'] = $_POST['key'];
}


echo json_encode($data);
