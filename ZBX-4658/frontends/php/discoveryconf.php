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
	$page['scripts'] = array('class.cviewswitcher.js');

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
			add_audit(!isset($_REQUEST['druleid']) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_DISCOVERY_RULE, '['.$druleid.'] '.$_REQUEST['name']);

			unset($_REQUEST['form']);
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
	$form_button = new CForm(null, 'get');
	if(!isset($_REQUEST['form'])){
		$form_button->addItem(new CButton('form', S_CREATE_RULE));
	}

	$dscry_wdgt = new CWidget();
	$dscry_wdgt->addPageHeader(S_CONFIGURATION_OF_DISCOVERY_BIG, $form_button);

	if(isset($_REQUEST['form'])){

		$form = new CFormTable();

		if(isset($_REQUEST['druleid'])){
			$sql = 'SELECT * FROM drules WHERE druleid='.$_REQUEST['druleid'];

			if($rule_data = DBfetch(DBselect($sql))){
				$form->addVar('druleid', $_REQUEST['druleid']);
				$form->setTitle(S_DISCOVERY_RULE.' "'.$rule_data['name'].'"');
			}
		}
		else{
			$form->setTitle(S_DISCOVERY_RULE);
		}

		$uniqueness_criteria = get_request('uniqueness_criteria', -1);
		if(isset($_REQUEST['druleid']) && $rule_data && (!isset($_REQUEST["form_refresh"]))){
			$proxy_hostid = $rule_data['proxy_hostid'];
			$name = $rule_data['name'];
			$iprange = $rule_data['iprange'];
			$delay = $rule_data['delay'];
			$status = $rule_data['status'];

			//TODO init checks
			$dchecks = array();
			$db_checks = DBselect('SELECT dcheckid,type,ports,key_,snmp_community,snmpv3_securityname,'.
						'snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase'.
						' FROM dchecks'.
						' WHERE druleid='.$_REQUEST['druleid']);
			while($check_data = DBfetch($db_checks)){
				$count = array_push($dchecks, array('dcheckid' => $check_data['dcheckid'], 'type' => $check_data['type'],
						'ports' => $check_data['ports'], 'key' => $check_data['key_'],
						'snmp_community' => $check_data['snmp_community'],
						'snmpv3_securityname' => $check_data['snmpv3_securityname'],
						'snmpv3_securitylevel' => $check_data['snmpv3_securitylevel'],
						'snmpv3_authpassphrase' => $check_data['snmpv3_authpassphrase'],
						'snmpv3_privpassphrase' => $check_data['snmpv3_privpassphrase']));
				if ($check_data['dcheckid'] == $rule_data['unique_dcheckid'])
					$uniqueness_criteria = $count - 1;
			}
			$dchecks_deleted = get_request('dchecks_deleted',array());
		}
		else{
			$proxy_hostid = get_request('proxy_hostid', 0);
			$name = get_request('name', '');
			$iprange = get_request('iprange', '192.168.0.1-255');
			$delay = get_request('delay', 3600);
			$status = get_request('status', DRULE_STATUS_ACTIVE);

			$dchecks = get_request('dchecks', array());
			$dchecks_deleted = get_request('dchecks_deleted', array());
		}

		$new_check_type	= get_request('new_check_type', SVC_HTTP);
		$new_check_ports = get_request('new_check_ports', '80');
		$new_check_key = get_request('new_check_key', '');
		$new_check_snmp_community = get_request('new_check_snmp_community', '');
		$new_check_snmpv3_securitylevel = get_request('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
		$new_check_snmpv3_securityname = get_request('new_check_snmpv3_securityname', '');
		$new_check_snmpv3_authpassphrase = get_request('new_check_snmpv3_authpassphrase', '');
		$new_check_snmpv3_privpassphrase = get_request('new_check_snmpv3_privpassphrase', '');

		$form->addRow(S_NAME, new CTextBox('name', $name, 40));


		$cmbProxy = new CComboBox('proxy_hostid', $proxy_hostid);
		$cmbProxy->addItem(0, S_NO_PROXY);
		$sql = 'SELECT hostid, host '.
				' FROM hosts'.
				' WHERE status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.') '.
					' AND '.DBin_node('hostid').
				' ORDER BY host';
		$db_proxies = DBselect($sql);
		while($db_proxy = DBfetch($db_proxies)){
			$cmbProxy->addItem($db_proxy['hostid'], $db_proxy['host']);
		}
		$form->addRow(S_DISCOVERY_BY_PROXY, $cmbProxy);


		$form->addRow(S_IP_RANGE, new CTextBox('iprange', $iprange, 27));
		$form->addRow(S_DELAY.SPACE.S_SECOND_IN_PARENTHESES, new CNumericBox('delay', $delay, 8));

		$form->addVar('dchecks', $dchecks);
		$form->addVar('dchecks_deleted', $dchecks_deleted);

		$cmbUniquenessCriteria = new CComboBox('uniqueness_criteria', $uniqueness_criteria);
		$cmbUniquenessCriteria->addItem(-1, S_IP_ADDRESS);

		foreach($dchecks as $id => $data){
			$dchecks[$id]['name'] = zbx_htmlstr(discovery_check2str($data['type'], $data['snmp_community'], $data['key'], $data['ports']));
		}
		order_result($dchecks, 'name');

		foreach($dchecks as $id => $data){
			$label = new CLabel($data['name'], 'selected_checks['.$id.']');
			$dchecks[$id] = array(new CCheckBox('selected_checks['.$id.']', null, null, $id), $label, BR());

			if(in_array($data['type'], array(SVC_AGENT, SVC_SNMPv1, SVC_SNMPv2, SVC_SNMPv3)))
				$cmbUniquenessCriteria->addItem($id, $data['name']);
		}

		if(count($dchecks)){
			$dchecks[] = new CButton('delete_ckecks', S_DELETE_SELECTED);
			$form->addRow(S_CHECKS, $dchecks);
		}

		$cmbChkType = new CComboBox('new_check_type', $new_check_type, "if(add_variable(this, 'type_changed', 1)) submit()");
		$cmbChkType->addItems(discovery_check_type2str());

		if(isset($_REQUEST['type_changed'])){
			$new_check_ports = svc_default_port($new_check_type);
		}


		$external_param = new CTable();

		if($new_check_type != SVC_ICMPPING){
			$external_param->addRow(array(S_PORTS_SMALL, new CTextBox('new_check_ports', $new_check_ports, 20)));
		}
		switch($new_check_type){
			case SVC_SNMPv1:
			case SVC_SNMPv2:
				$external_param->addRow(array(S_SNMP_COMMUNITY, new CTextBox('new_check_snmp_community', $new_check_snmp_community)));
				$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));

				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
			break;
			case SVC_SNMPv3:
				$form->addVar('new_check_snmp_community', '');

				$external_param->addRow(array(S_SNMP_OID, new CTextBox('new_check_key', $new_check_key)));
				$external_param->addRow(array(S_SNMPV3_SECURITY_NAME, new CTextBox('new_check_snmpv3_securityname', $new_check_snmpv3_securityname)));

				$cmbSecLevel = new CComboBox('new_check_snmpv3_securitylevel', $new_check_snmpv3_securitylevel);
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV,'noAuthNoPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV,'authNoPriv');
				$cmbSecLevel->addItem(ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV,'authPriv');

				$external_param->addRow(array(S_SNMPV3_SECURITY_LEVEL, $cmbSecLevel));

				// adding id to <tr> elements so they could be then hidden by cviewswitcher.js
				$row = new CRow(array(S_SNMPV3_AUTH_PASSPHRASE, new CTextBox('new_check_snmpv3_authpassphrase', $new_check_snmpv3_authpassphrase)));
				$row->setAttribute('id', 'row_snmpv3_authpassphrase');
				$external_param->addRow($row);

				$row = new CRow(array(S_SNMPV3_PRIV_PASSPHRASE, new CTextBox('new_check_snmpv3_privpassphrase', $new_check_snmpv3_privpassphrase)));
				$row->setAttribute('id', 'row_snmpv3_privpassphrase');
				$external_param->addRow($row);
			break;
			case SVC_AGENT:
				$form->addVar('new_check_snmp_community', '');
				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
				$external_param->addRow(array(S_KEY, new CTextBox('new_check_key', $new_check_key), BR()));
			break;
			case SVC_ICMPPING:
				$form->addVar('new_check_ports', '0');
			default:
				$form->addVar('new_check_snmp_community', '');
				$form->addVar('new_check_key', '');
				$form->addVar('new_check_snmpv3_securitylevel', ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV);
				$form->addVar('new_check_snmpv3_securityname', '');
				$form->addVar('new_check_snmpv3_authpassphrase', '');
				$form->addVar('new_check_snmpv3_privpassphrase', '');
		}


		if($external_param->getNumRows() == 0) $external_param = null;
		$form->addRow(S_NEW_CHECK, array(
			$cmbChkType, SPACE,
			new CButton('add_check', S_ADD),
			$external_param
		),'new');

		$form->addRow(S_DEVICE_UNIQUENESS_CRITERIA, $cmbUniquenessCriteria);

		$cmbStatus = new CComboBox("status", $status);
		foreach(array(DRULE_STATUS_ACTIVE, DRULE_STATUS_DISABLED) as $st)
			$cmbStatus->addItem($st, discovery_status2str($st));
		$form->addRow(S_STATUS,$cmbStatus);

		$form->addItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["druleid"])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButton("clone",S_CLONE));
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(S_DELETE_RULE_Q,
				url_param("form").url_param("druleid")));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel());

		$dscry_wdgt->addItem($form);

		// adding javascript, so that auth fields would be hidden if they are not used in specific auth type
		$securityLevelVisibility = array();
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV, 'row_snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_authpassphrase');
		zbx_subarray_push($securityLevelVisibility, ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV, 'row_snmpv3_privpassphrase');
		zbx_add_post_js("var securityLevelSwitcher = new CViewSwitcher('new_check_snmpv3_securitylevel', 'change', ".zbx_jsvalue($securityLevelVisibility, true).");");
	}
	else{
		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$dscry_wdgt->addHeader(S_DISCOVERY_BIG);
		$dscry_wdgt->addHeader($numrows);
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
			S_STATUS
		));

		$sql = 'SELECT d.* '.
				' FROM drules d'.
				' WHERE '.DBin_node('druleid').
				order_by('d.name,d.iprange,d.delay','d.druleid');
		$db_rules = DBselect($sql);

		// Discovery rules will be gathered here, so we can feed this array to pagination function
		$rules_arr = array();

		while($rule_data = DBfetch($db_rules)){
			$rules_arr[] = $rule_data;
		}

		// getting paging element
		$paging = getPagingLine($rules_arr);

		foreach($rules_arr as $rule_data){
			$checks = array();
			$sql = 'SELECT type FROM dchecks WHERE druleid='.$rule_data['druleid'].' ORDER BY type, ports';
			$db_checks = DBselect($sql);
			while($check_data = DBfetch($db_checks)){
				if(!isset($checks[$check_data['type']]))
					$checks[$check_data['type']] = discovery_check_type2str($check_data['type']);
			}
			order_result($checks);

			$status = new CCol(new CLink(discovery_status2str($rule_data["status"]),
				'?g_druleid%5B%5D='.$rule_data['druleid'].
				(($rule_data["status"] == DRULE_STATUS_ACTIVE) ? '&go=disable' : '&go=activate'),
				discovery_status2style($rule_data['status'])
			));

			$description = array();
			if ($rule_data["proxy_hostid"]) {
				$proxy = get_host_by_hostid($rule_data["proxy_hostid"]);
				array_push($description, $proxy["host"], ":");
			}

			array_push($description, new CLink($rule_data['name'], "?form=update&druleid=".$rule_data['druleid']));

			$tblDiscovery->addRow(array(
				new CCheckBox('g_druleid['.$rule_data["druleid"].']',null,null,$rule_data["druleid"]),
				$description,
				$rule_data['iprange'],
				$rule_data['delay'],
				implode(', ', $checks),
				$status
			));
		}

		// pagination at the top and the bottom of the page
		$tblDiscovery->addRow(new CCol($paging));
		$dscry_wdgt->addItem($paging);


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
