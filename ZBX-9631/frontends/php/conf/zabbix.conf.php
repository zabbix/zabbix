<?php
// Zabbix GUI configuration file.
global $DB;

$DB['TYPE']     = 'MYSQL';
$DB['SERVER']   = 'sql';
$DB['PORT']     = '0';
$DB['DATABASE'] = 'g_2_4';
$DB['USER']     = 'root';
$DB['PASSWORD'] = '';

// Schema name. Used for IBM DB2 and PostgreSQL.
$DB['SCHEMA'] = '';

$ZBX_SERVER      = 'dm';
$ZBX_SERVER_PORT = '3820';
$ZBX_SERVER_NAME = '';

$IMAGE_FORMAT_DEFAULT = IMAGE_FORMAT_PNG;
?>
