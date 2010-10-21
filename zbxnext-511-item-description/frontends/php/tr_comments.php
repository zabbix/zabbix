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
require_once('include/triggers.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_TRIGGER_COMMENTS';
$page['file'] = 'tr_comments.php';

include_once('include/page_header.php');

?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'triggerid'=>	array(T_ZBX_INT, O_MAND, P_SYS,	DB_ID, null),
		'description'=>	array(T_ZBX_STR, O_OPT,  null,	null, 'isset({save})'),

		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
/*
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
*/
	);
	check_fields($fields);
?>
<?php

	$options = array(
		'nodeids' => get_current_nodeid(true),
		'triggerids' => $_REQUEST['triggerid'],
		'output' => API_OUTPUT_EXTEND,
		'select_hosts' => API_OUTPUT_EXTEND,
	);
	$trigger = CTrigger::get($options);
	$trigger = reset($trigger);

	if(!$trigger)
		access_deny();


	if(isset($_REQUEST['save'])){
		$result = update_trigger_description($_REQUEST['triggerid'], $_REQUEST['description']);
		show_messages($result, S_NAME_UPDATED, S_CANNOT_UPDATE_NAME);

		if($result){
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_TRIGGER, S_TRIGGER . ' [' .
					$_REQUEST['triggerid'] . '] [' . expand_trigger_name(
				$_REQUEST['triggerid']) . '] ' . S_NAME . ' [' . $_REQUEST['description'] . ']');
		}
	}
	else if(isset($_REQUEST['cancel'])){
		jsRedirect('tr_status.php');
		exit();
	}

	show_table_header(S_TRIGGER.SPACE.S_NAME);

	$host = reset($trigger['hosts']);

	$frmComent = new CFormTable(S_NAME." for ".$host['host']." : \"".expand_trigger_name_by_data($trigger).'"');
	$frmComent->addVar('triggerid', $_REQUEST['triggerid']);
	$frmComent->addRow(S_NAME, new CTextArea('description',$trigger['description'],100,25));
	$frmComent->addItemToBottomRow(new CButton('save', S_SAVE));
	$frmComent->addItemToBottomRow(new CButtonCancel('&triggerid='.$_REQUEST['triggerid']));

	$frmComent->show();

?>
<?php

include_once('include/page_footer.php');

?>
