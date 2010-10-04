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
	require_once('include/forms.inc.php');
	require_once('include/discovery.inc.php');

	$page['title']	= 'S_CONFIGURATION_OF_DISCOVERY';
	$page['file']	= 'discoveryconf.php';
	$page['hist_arg'] = array('');

include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'druleid'=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,		'isset({form})&&{form}=="update"'),
		'name'=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		'proxy_hostid'=>array(T_ZBX_INT, O_OPT,	 null,	DB_ID,	'isset({save})'),
		'iprange'=>	array(T_ZBX_IP_RANGE, O_OPT,  null,	NOT_EMPTY,	'isset({save})'),
		'delay'=>	array(T_ZBX_INT, O_OPT,	 null,	null, 		'isset({save})'),
		'status'=>	array(T_ZBX_INT, O_OPT,	 null,	IN('0,1'), 	'isset({save})'),
		'uniqueness_criteria'=>	array(T_ZBX_INT, O_OPT,  null, NULL,      'isset({save})'),

		'g_druleid'=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),

		'dchecks'=>	array(null, O_OPT, null, null, null),
		'dchecks_deleted'=>	array(null, O_OPT, null, null, null),
		'selected_checks'=>	array(T_ZBX_INT, O_OPT, null, null, null),

		'new_check_type'=>	array(T_ZBX_INT, O_OPT,  null,
			IN(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP, SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP, SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2, SVC_SNMPv3, SVC_ICMPPING)),
										'isset({add_check})'),

		'new_check_ports'=>	array(T_ZBX_PORTS,	O_OPT,  null,	"validate_port_list({})&&",	'isset({add_check})'),
		'new_check_key'=>	array(T_ZBX_STR,	O_OPT,  null,	null,	'isset({add_check})'),
		'new_check_snmp_community'=>		array(T_ZBX_STR, O_OPT,  null,	null,		'isset({add_check})'),
		'new_check_snmpv3_securitylevel'=>	array(T_ZBX_INT, O_OPT,  null,  IN('0,1,2'),	'isset({add_check})'),
		'new_check_snmpv3_securityname'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({add_check})'),
		'new_check_snmpv3_authpassphrase'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({add_check})'),
		'new_check_snmpv3_privpassphrase'=>	array(T_ZBX_STR, O_OPT,  null,  null,		'isset({add_check})'),

		'type_changed'=>	array(T_ZBX_INT, O_OPT, null, IN(1), null),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'add_check'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete_ckecks'=> 	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'save'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	check_fields($fields);
	validate_sort_and_sortorder('d.name',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
	$_REQUEST['dchecks'] = get_request('dchecks', array());
	$_REQUEST['dchecks_deleted'] = get_request('dchecks_deleted', array());

?>
<?php
	if(inarr_isset(array('add_check', 'new_check_type', 'new_check_ports', 'new_check_key', 'new_check_snmp_community',
			'new_check_snmpv3_securitylevel', 'new_check_snmpv3_securityname', 'new_check_snmpv3_authpassphrase',
			'new_check_snmpv3_privpassphrase')))
	{
		$new_dcheck = array(
			'type' => $_REQUEST['new_check_type'],
			'ports'=> $_REQUEST['new_check_ports'],
			'key'=> $_REQUEST['new_check_key'],
			'snmp_community'=> $_REQUEST['new_check_snmp_community'],
			'snmpv3_securitylevel'=> $_REQUEST['new_check_snmpv3_securitylevel'],
			'snmpv3_securityname'=> $_REQUEST['new_check_snmpv3_securityname'],
			'snmpv3_authpassphrase'=> $_REQUEST['new_check_snmpv3_authpassphrase'],
			'snmpv3_privpassphrase'=> $_REQUEST['new_check_snmpv3_privpassphrase']
			);

		$found = false;
		foreach($_REQUEST['dchecks'] as $dbcheck){
			if($dbcheck['type'] === $new_dcheck['type']
				&& $dbcheck['ports'] === $new_dcheck['ports']
				&& $dbcheck['key'] === $new_dcheck['key']
				&& $dbcheck['snmp_community'] === $new_dcheck['snmp_community']
				&& $dbcheck['snmpv3_securityname'] === $new_dcheck['snmpv3_securityname']
				&& $dbcheck['snmpv3_securitylevel'] === $new_dcheck['snmpv3_securitylevel']
				&& $dbcheck['snmpv3_authpassphrase'] === $new_dcheck['snmpv3_authpassphrase']
				&& $dbcheck['snmpv3_privpassphrase'] === $new_dcheck['snmpv3_privpassphrase']
			){
				$found = true;
			}
		}
		if(!$found) $_REQUEST['dchecks'][] = $new_dcheck;
	}
	else if(inarr_isset(array('delete_ckecks', 'selected_checks'))){
		foreach($_REQUEST['selected_checks'] as $chk_id)
		{
			if (isset($_REQUEST['dchecks'][$chk_id]['dcheckid']))
				$_REQUEST['dchecks_deleted'][] = $_REQUEST['dchecks'][$chk_id]['dcheckid'];
			unset($_REQUEST['dchecks'][$chk_id]);
		}
	}
	else if(inarr_isset('save')){
		if(inarr_isset('druleid')){ /* update */
			$msg_ok = S_DISCOVERY_RULE_UPDATED;
			$msg_fail = S_CANNOT_UPDATE_DISCOVERY_RULE;

			$result = update_discovery_rule($_REQUEST["druleid"], $_REQUEST["proxy_hostid"], $_REQUEST['name'],
				$_REQUEST['iprange'], $_REQUEST['delay'], $_REQUEST['status'], $_REQUEST['dchecks'],
				$_REQUEST['uniqueness_criteria'], $_REQUEST['dchecks_deleted']);

			$druleid = $_REQUEST["druleid"];
		}
		else{ /* add new */
			$msg_ok = S_DISCOVERY_RULE_ADDED;
			$msg_fail = S_CANNOT_ADD_DISCOVERY_RULE;

			$druleid = add_discovery_rule($_REQUEST["proxy_hostid"], $_REQUEST['name'], $_REQUEST['iprange'],
				$_REQUEST['delay'], $_REQUEST['status'], $_REQUEST['dchecks'], $_REQUEST['uniqueness_criteria']);

			$result = $druleid;
		}

		show_messages($result, $msg_ok, $msg_fail);

		if($result){ // result - OK
			add_audit(!isset($_REQUEST["druleid"]) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$_REQUEST['name']);

			unset($_REQUEST["form"]);
		}
	}
	else if(inarr_isset(array('clone','druleid'))){
		unset($_REQUEST["druleid"]);
		$dchecks = $_REQUEST['dchecks'];
		foreach($dchecks as $id => $data)
			unset($dchecks[$id]['dcheckid']);
		$_REQUEST["form"] = "clone";
	}
	else if(inarr_isset(array('delete', 'druleid'))){
		$result = delete_discovery_rule($_REQUEST['druleid']);
		show_messages($result,S_DISCOVERY_RULE_DELETED,S_CANNOT_DELETE_DISCOVERY_RULE);
		if($result){
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_DISCOVERY_RULE,
				'['.$_REQUEST['druleid'].']');
			unset($_REQUEST['form']);
			unset($_REQUEST['druleid']);
		}

	}
// ------- GO --------
	else if(str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['g_druleid'])){
		$status = ($_REQUEST['go'] == 'activate')?DRULE_STATUS_ACTIVE:DRULE_STATUS_DISABLED;

		$go_result = false;
		foreach($_REQUEST['g_druleid'] as $drid){
			if(set_discovery_rule_status($drid,$status)){
				$rule_data = get_discovery_rule_by_druleid($drid);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_DISCOVERY_RULE,
					'['.$drid.'] '.$rule_data['name']);
				$go_result = true;
			}
		}
		show_messages($go_result,S_DISCOVERY_RULES_UPDATED);
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['g_druleid'])){
		$go_result = false;
		foreach($_REQUEST['g_druleid'] as $drid){
			if(delete_discovery_rule($drid)){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_DISCOVERY_RULE,
					'['.$drid.']');
				$go_result = true;
			}
		}
		show_messages($go_result,S_DISCOVERY_RULES_DELETED);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
/* header */
	$form = new CForm(null, 'get');

	if(!isset($_REQUEST['form'])){
		$form->addItem(new CButton('form', S_CREATE_RULE));
	}
		
	$dscry_wdgt = new CWidget();
	$dscry_wdgt->addPageHeader(S_CONFIGURATION_OF_DISCOVERY_BIG, $form);

	if(isset($_REQUEST['form'])){
		$dscry_wdgt->addItem(insert_drule_form());
	}
	else{
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$dscry_wdgt->addHeader(S_DISCOVERY_BIG);
//		$dscry_wdgt->addHeader($numrows);
/* table */
		$form = new CForm();
		$form->setName('frmdrules');

		$tblDiscovery = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$tblDiscovery->setHeader(array(
			new CCheckBox('all_drules',null,"checkAll('".$form->GetName()."','all_drules','g_druleid');"),
			make_sorting_header(S_NAME,'d.name'),
			make_sorting_header(S_IP_RANGE,'d.iprange'),
			make_sorting_header(S_DELAY,'d.delay'),
			S_CHECKS,
			S_STATUS));

/* sorting
		order_page_result($applications, 'name');

// PAGING UPPER
		$paging = getPagingLine($applications);
		$dscry_wdgt->addItem($paging);
//-------*/
		$dscry_wdgt->addItem(BR());

		$sql = 'SELECT d.* '.
				' FROM drules d'.
				' WHERE '.DBin_node('druleid').
				order_by('d.name,d.iprange,d.delay','d.druleid');
		$db_rules = DBselect($sql);
		while($rule_data = DBfetch($db_rules)){
			$cheks = array();
			$db_checks = DBselect("select type from dchecks where druleid=".$rule_data["druleid"].
				" order by type,druleid");
			while($check_data = DBfetch($db_checks)){
				$cheks[] = discovery_check_type2str($check_data['type']);
			}

			$status = new CCol(new CLink(discovery_status2str($rule_data["status"]),
				'?g_druleid%5B%5D='.$rule_data['druleid'].
				(($rule_data["status"] == DRULE_STATUS_ACTIVE)?'&go=disable':'&go=activate'),
				discovery_status2style($rule_data["status"])));

			$description = array();

			if ($rule_data["proxy_hostid"]) {
				$proxy = get_host_by_hostid($rule_data["proxy_hostid"]);
				array_push($description, $proxy["host"], ":");
			}

			array_push($description,
					new CLink($rule_data['name'], "?form=update&druleid=".$rule_data['druleid']));

			$tblDiscovery->addRow(array(
				new CCheckBox('g_druleid['.$rule_data["druleid"].']',null,null,$rule_data["druleid"]),
				$description,
				$rule_data['iprange'],
				$rule_data['delay'],
				implode(',', $cheks),
				$status
			));
		}

// PAGING FOOTER
//		$table->addRow(new CCol($paging));
//		$dscry_wdgt->addItem($paging);
//---------

// gobox
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ENABLE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_DISCOVERY_RULES);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_DISCOVERY_RULES);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_DISCOVERY_RULES);
		$goBox->addItem($goOption);

		// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');

		zbx_add_post_js('chkbxRange.pageGoName = "g_druleid";');

		$tblDiscovery->setFooter(new CCol(array($goBox, $goButton)));
//----

		$form->addItem($tblDiscovery);

		$dscry_wdgt->addItem($form);
	}

	$dscry_wdgt->show();

include_once('include/page_footer.php');
?>
