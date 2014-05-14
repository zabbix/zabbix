<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');
require_once('include/setup.inc.php');
require_once('include/requirements.inc.php');

/* ******** */

$page['title'] = "S_INSTALLATION";
$page['file'] = 'setup.php';

if(!defined('PAGE_HEADER_LOADED'))
	define('ZBX_PAGE_NO_MENU', 1);

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		'distributed'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'trouble'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),

		'type'=>		array(T_ZBX_STR, O_OPT,	null,	IN('"MYSQL","POSTGRESQL","ORACLE","IBM_DB2","SQLITE3"'),	null),
		'server'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'port'=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,65535),	null),
		'database'=>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		null),
		'user'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'password'=>		array(T_ZBX_STR, O_OPT,	null,	null, 			null),
		'schema'=>		array(T_ZBX_STR, O_OPT,	null,	null, 			null),

		'zbx_server'=>		array(T_ZBX_STR, O_OPT, null,   null,                   null),
		'zbx_server_name'=>		array(T_ZBX_STR, O_OPT, null,   null,                   null),
		'zbx_server_port'=>	array(T_ZBX_INT, O_OPT, null,	BETWEEN(0,65535),	null),

		'message'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			NULL),

		'nodename'=>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,		null),
		'nodeid'=>		array(T_ZBX_INT, O_OPT,	null,	BETWEEN(0,999),		null),
/* actions */
		'save_config'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'retry'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'finish'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'next'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'back'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields, false);
?>
<?php
	global $ZBX_CONFIG;

	if(isset($_REQUEST['cancel']) || isset($_REQUEST['finish'])){
		zbx_unsetcookie('ZBX_CONFIG');
		redirect('index.php');
	}

	$ZBX_CONFIG = get_cookie('ZBX_CONFIG', null);
	$ZBX_CONFIG = isset($ZBX_CONFIG) ? unserialize($ZBX_CONFIG) : array();

	if(!isset($ZBX_CONFIG['step'])) $ZBX_CONFIG['step'] = 0;
	if(!isset($ZBX_CONFIG['agree'])) $ZBX_CONFIG['agree'] = false;

	$ZBX_CONFIG['allowed_db'] = array();

// MYSQL
	if(zbx_is_callable(array('mysql_pconnect', 'mysql_select_db', 'mysql_error', 'mysql_select_db','mysql_query', 'mysql_fetch_array', 'mysql_fetch_row', 'mysql_data_seek','mysql_insert_id')))
	{
		$ZBX_CONFIG['allowed_db']['MYSQL'] = 'MySQL';
	}

// POSTGRESQL
	if(zbx_is_callable(array('pg_pconnect', 'pg_fetch_array', 'pg_fetch_row', 'pg_exec', 'pg_getlastoid'))){
		$ZBX_CONFIG['allowed_db']['POSTGRESQL'] = 'PostgreSQL';
	}

// ORACLE
	if(zbx_is_callable(array('ocilogon', 'ocierror', 'ociparse', 'ociexecute', 'ocifetchinto'))){
		$ZBX_CONFIG['allowed_db']['ORACLE'] = 'Oracle';
	}

// IBM_DB2
	if(zbx_is_callable(array('db2_connect', 'db2_set_option', 'db2_prepare', 'db2_execute', 'db2_fetch_assoc'))){
		$ZBX_CONFIG['allowed_db']['IBM_DB2'] = 'IBM DB2';
	}

// SQLITE3
	if(zbx_is_callable(array('sqlite3_open', 'sqlite3_close', 'sqlite3_query', 'sqlite3_error', 'sqlite3_fetch_array', 'sqlite3_query_close', 'sqlite3_exec'))){
		$ZBX_CONFIG['allowed_db']['SQLITE3'] = 'SQLite3';
	}

	if(count($ZBX_CONFIG['allowed_db']) == 0){
		$ZBX_CONFIG['allowed_db']['no'] = 'No';
	}
?>
<?php
	global $ZBX_SETUP_WIZARD;

	$ZBX_SETUP_WIZARD = new CSetupWizard($ZBX_CONFIG);

	zbx_set_post_cookie('ZBX_CONFIG', serialize($ZBX_CONFIG));
?>
<?php
include_once('include/page_header.php');

	global	$ZBX_CONFIGURATION_FILE;

	if(file_exists($ZBX_CONFIGURATION_FILE)){
		if(isset($_REQUEST['message']))
			show_error_message($_REQUEST['message']);
	}

	$ZBX_SETUP_WIZARD->show();

	unset($_POST);


include_once('include/page_footer.php');
?>
