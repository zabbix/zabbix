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
?>
