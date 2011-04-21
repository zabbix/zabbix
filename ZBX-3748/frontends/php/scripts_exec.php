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

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'hostid'=>				array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'isset({execute})'),
	'scriptid'=>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	'isset({execute})'),
	'execute'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
);
check_fields($fields);

if(isset($_REQUEST['execute'])){
	$scriptid = $_REQUEST['scriptid'];
	$hostid = $_REQUEST['hostid'];

	$sql = 'SELECT name '.
			' FROM scripts '.
			' WHERE scriptid='.$scriptid;
	$script_info = DBfetch(DBselect($sql));

	$result = CScript::execute(array('hostid' => $hostid, 'scriptid' => $scriptid));
	if($result === false){
		show_messages(false, '', S_SCRIPT_ERROR);
	}
	else{
		$message = $result['value'];
		if($result['response'] == 'failed'){
			error($message);
			show_messages(false, '', S_SCRIPT_ERROR);
			$message = '';
		}

		$frmResult = new CFormTable($script_info['name'].': '.script_make_command($scriptid, $hostid));
		$frmResult->addRow(S_RESULT, new CTextArea('message', $message, 100, 25, 'yes'));
		$frmResult->addItemToBottomRow(new CButton('close', S_CLOSE, 'window.close();'));
		$frmResult->show();
	}

}

?>
<?php
include_once "include/page_footer.php";
?>
