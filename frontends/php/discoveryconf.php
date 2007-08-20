<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/forms.inc.php";
	require_once "include/discovery.inc.php";

	$page["title"]	= "S_CONFIGURATION_OF_DISCOVERY";
	$page["file"]	= "discoveryconf.php";

include_once "include/page_header.php";
	
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"druleid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,		'{form}=="update"'),
		"name"=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		"iprange"=>	array(T_ZBX_IP_RANGE, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		"delay"=>	array(T_ZBX_INT, O_OPT,	 null,	null, 		'isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,	 null,	IN("0,1"), 	'isset({save})'),

		"g_druleid"=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),

		"dchecks"=>	array(null, O_OPT, null, null, null),
		"selected_checks"=>	array(T_ZBX_INT, O_OPT, null, null, null),

		"new_check_type"=>	array(T_ZBX_INT, O_OPT,  null,	
			IN(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2)),
										'isset({add_check})'),

		"new_check_ports"=>	array(T_ZBX_PORTS, O_OPT,  null,	NOT_EMPTY,	'isset({add_check})'),
		"new_check_key"=>	array(T_ZBX_STR, O_OPT,  null,	null,	'isset({add_check})'),
		"new_check_snmp_community"=>	array(T_ZBX_STR, O_OPT,  null,	null,	'isset({add_check})'),

		"type_changed"=>	array(T_ZBX_INT, O_OPT, null, IN(1), null),

/* actions */
		"add_check"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete_ckecks"=> 	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_enable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_disable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_delete"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"clone"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	check_fields($fields);
	$_REQUEST['dchecks'] = get_request('dchecks', array());
	
?>
<?php
	if(inarr_isset(array('add_check', 'new_check_type', 'new_check_ports', 'new_check_key', 'new_check_snmp_community')))
	{
		$new_dcheck = array(
			'type' => $_REQUEST['new_check_type'],
			'ports'=> $_REQUEST['new_check_ports'],
			'key'=> $_REQUEST['new_check_key'],
			'snmp_community'=> $_REQUEST['new_check_snmp_community']
			);
		if( !in_array($new_dcheck, $_REQUEST['dchecks']))
			$_REQUEST['dchecks'][] = $new_dcheck;
	}
	else if(inarr_isset(array('delete_ckecks', 'selected_checks')))
	{
		foreach($_REQUEST['selected_checks'] as $chk_id)
			unset($_REQUEST['dchecks'][$chk_id]);
	}
	else if(inarr_isset('save'))
	{
		if(inarr_isset('druleid'))
		{ /* update */
			$msg_ok = S_DISCOVERY_RULE_UPDATED;
			$msg_fail = S_CANNOT_UPDATE_DISCOVERY_RULE;

			$result = update_discovery_rule($_REQUEST["druleid"], $_REQUEST['name'], $_REQUEST['iprange'], 
				$_REQUEST['delay'], $_REQUEST['status'], $_REQUEST['dchecks']);

			$druleid = $_REQUEST["druleid"];
		}
		else
		{ /* add new */
			$msg_ok = S_DISCOVERY_RULE_ADDED;
			$msg_fail = S_CANNOT_ADD_DISCOVERY_RULE;

			$druleid = add_discovery_rule($_REQUEST['name'], $_REQUEST['iprange'],
				$_REQUEST['delay'], $_REQUEST['status'], $_REQUEST['dchecks']);

			$result = $druleid;
		}
		
		show_messages($result, $msg_ok, $msg_fail);

		if($result) // result - OK
		{
			add_audit(!isset($_REQUEST["druleid"]) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$_REQUEST['name']);

			unset($_REQUEST["form"]);
		}
	}
	else if(inarr_isset(array('clone','druleid')))
	{
		unset($_REQUEST["druleid"]);
		$_REQUEST["form"] = "clone";
	}
	else if(inarr_isset(array('delete', 'druleid')))
	{
		$result = delete_discovery_rule($_REQUEST['druleid']);
		show_messages($result,S_DISCOVERY_RULE_DELETED,S_CANNOT_DELETE_DISCOVERY_RULE);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_DISCOVERY_RULE,
				'['.$_REQUEST['druleid'].']');
			unset($_REQUEST['form']);
			unset($_REQUEST['druleid']);
		}

	}
	else if(inarr_isset('g_druleid'))
	{
		if( inarr_isset('group_disable') || inarr_isset('group_enable') )
		{
			$status = DRULE_STATUS_ACTIVE;
			if(isset($_REQUEST['group_disable'])) $status = DRULE_STATUS_DISABLED;

			$result = false;
			foreach($_REQUEST['g_druleid'] as $drid)
			{
				if(set_discovery_rule_status($drid,$status))
				{
					$rule_data = get_discovery_rule_by_druleid($drid);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_DISCOVERY_RULE,
						'['.$drid.'] '.$rule_data['name']);
					$result = true;
				}
			}
			show_messages($result,S_DISCOVERY_RULES_UPDATED);
		}
		else if( inarr_isset('group_delete') )
		{
			$result = false;
			foreach($_REQUEST['g_druleid'] as $drid)
			{
				if(delete_discovery_rule($drid))
				{
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_DISCOVERY_RULE,
						'['.$drid.']');
					$result = true;
				}
			}
			show_messages($result,S_DISCOVERY_RULES_DELETED);
		}
	}
?>
<?php
/* header */
	$form = new CForm();
	$form->AddItem(new CButton('form', S_CREATE_RULE));
	show_table_header(S_CONFIGURATION_OF_DISCOVERY_BIG, $form);
	echo BR;

	if(isset($_REQUEST["form"]))
	{
/* form */
		insert_drule_form();
	}
	else
	{
		show_table_header(S_DISCOVERY_BIG);
/* table */
		$form = new CForm();
		$form->SetName('frmdrules');

		$tblDiscovery = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$tblDiscovery->SetHeader(array(
			array(	new CCheckBox('all_drules',null,"CheckAll('".$form->GetName()."','all_drules');"),
				S_NAME
			),
			S_IP_RANGE,
			S_DELAY,
			S_CHECKS,
			S_STATUS));

		$db_rules = DBselect('select * from drules where '.DBin_node('druleid').
			' order by name, druleid');
		while($rule_data = DBfetch($db_rules))
		{
			$cheks = array();
			$db_checks = DBselect("select * from dchecks where druleid=".$rule_data["druleid"].
				" order by type,druleid");
			while($check_data = DBfetch($db_checks))
			{
				$cheks[] = discovery_check_type2str($check_data['type']);
			}

			$status = new CCol(new CLink(discovery_status2str($rule_data["status"]),
				'?g_druleid%5B%5D='.$rule_data['druleid'].
				($rule_data["status"] == DRULE_STATUS_ACTIVE ? '&group_disable=1' : '&group_enable=1'),
				discovery_status2style($rule_data["status"])));

			$tblDiscovery->AddRow(array(
				array(
					new CCheckBox(
						"g_druleid[]",		/* name */
						null,			/* checked */
						null,			/* action */
						$rule_data["druleid"]),	/* value */
					SPACE,
					new CLink($rule_data['name'],
						"?form=update&druleid=".$rule_data['druleid'],'action'),
					),
				$rule_data['iprange'],
				$rule_data['delay'],
				implode(',', $cheks),
				$status
				));	
		}
		$tblDiscovery->SetFooter(new CCol(array(
			new CButtonQMessage('group_enable',S_ENABLE_SELECTED, S_ENABLE_SELECTED_RULES_Q), SPACE,
			new CButtonQMessage('group_disable',S_DISABLE_SELECTED, S_DISABLE_SELECTED_RULES_Q), SPACE,
			new CButtonQMessage('group_delete',S_DELETE_SELECTED, S_DELETE_SELECTED_RULES_Q)
		)));

		$form->AddItem($tblDiscovery);
		$form->Show();
	}
?>
<?php

	include_once "include/page_footer.php";

?>
