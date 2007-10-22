<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	include_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/scripts.inc.php";
	require_once "include/forms.inc.php";

	$page['title'] = "S_SCRIPTS";
	$page['file'] = 'scripts_exec.php';
	
	define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";

//---------------------------------- CHECKS ------------------------------------

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

	$fields=array(
		'hostid'=>				array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'isset({execute})'),
		'scriptid'=>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'isset({execute})'),
		'execute'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),		
	);
	

check_fields($fields);

if(isset($_REQUEST['execute'])){
	if($script = get_script_by_scriptid($_REQUEST['scriptid'])){
		if($script['host_access'] == SCRIPT_HOST_ACCESS_WRITE){
			$hosts_read_write = explode(',',get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid()));			
		
			if(in_array($_REQUEST['hostid'],$hosts_read_write)){
//SDI('WRITE: '.$_REQUEST['scriptid'].' : '.$_REQUEST['hostid']);
//				$result = execute_script($_REQUEST['scriptid'],$_REQUEST['hostid']);
//				insert_command_result_form($result["flag"],$result["message"]);
				insert_command_result_form($_REQUEST['scriptid'],$_REQUEST['hostid']);
/*				echo nl2br(htmlspecialchars($result));*/
			}
		}
		else{
			$hosts_read_only  = explode(',',get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,null,null,get_current_nodeid()));
			
			if(in_array($_REQUEST['hostid'],$hosts_read_only)){
//SDI('READ: '.$_REQUEST['scriptid'].' : '.$_REQUEST['hostid']);
//				$result = execute_script($_REQUEST['scriptid'],$_REQUEST['hostid']);
//				insert_command_result_form($result["flag"],$result["message"]);
				insert_command_result_form($_REQUEST['scriptid'],$_REQUEST['hostid']);
/*				echo nl2br(htmlspecialchars($result));*/
			}
		}
	}
}
?>
<?php
include_once "include/page_footer.php";
?>
