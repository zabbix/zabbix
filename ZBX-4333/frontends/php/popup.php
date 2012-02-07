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
	require_once('include/hosts.inc.php');
	require_once('include/triggers.inc.php');
	require_once('include/items.inc.php');
	require_once('include/users.inc.php');
	require_once('include/nodes.inc.php');
	require_once('include/js.inc.php');
	require_once('include/discovery.inc.php');

	$srctbl	= get_request('srctbl','');	// source table name

	switch($srctbl){
		case 'host_templates':
		case 'templates':
			$page['title'] = 'S_TEMPLATES_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			$templated_hosts = true;
			break;
		case 'hosts_and_templates':
			$page['title'] = 'S_HOSTS_AND_TEMPLATES_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
		break;
		case 'hosts':
			$page['title'] = 'S_HOSTS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'proxies':
			$page['title'] = 'S_PROXIES_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'applications':
			$page['title'] = 'S_APPLICATIONS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'host_group':
			$page['title'] = 'S_HOST_GROUPS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'triggers':
			$page['title'] = 'S_TRIGGERS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'usrgrp':
			$page['title'] = 'S_GROUPS';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'users':
			$page['title'] = 'S_USERS';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'items':
			$page['title'] = 'S_ITEMS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'help_items':
			$page['title'] = 'S_STANDARD_ITEMS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'screens':
			$page['title'] = 'S_SCREENS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'slides':
			$page['title'] = 'S_SLIDESHOWS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'graphs':
			$page['title'] = 'S_GRAPHS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'simple_graph':
			$page['title'] = 'S_SIMPLE_GRAPH_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'sysmaps':
			$page['title'] = 'S_MAPS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_USER;
			break;
		case 'plain_text':
			$page['title'] = 'S_PLAIN_TEXT_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'screens2':
			$page['title'] = 'S_SCREENS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'overview':
			$page['title'] = 'S_OVERVIEW_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'host_group_scr':
			$page['title'] = 'S_HOST_GROUPS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'nodes':
			if(ZBX_DISTRIBUTED){
				$page['title'] = 'S_NODES_BIG';
				$min_user_type = USER_TYPE_ZABBIX_USER;
				break;
			}
		case 'drules':
			$page['title'] = 'S_DISCOVERY_RULES_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		case 'dchecks':
			$page['title'] = 'S_DISCOVERY_CHECKS_BIG';
			$min_user_type = USER_TYPE_ZABBIX_ADMIN;
			break;
		default:
			$page['title'] = 'S_ERROR';
			$error = true;
			break;
	}

	$page['file'] = 'popup.php';
	$page['scripts'] = array();

	define('ZBX_PAGE_NO_MENU', 1);

include_once('include/page_header.php');

	if(isset($error)){
		invalid_url();
	}

	if(defined($page['title']))     $page['title'] = constant($page['title']);
?>
<?php

	// allowed 'srcfld1' and 'srcfld2' parameter values for each 'srctbl' value
	$allowedSrcFields = array(
		'hosts'                 => '"name", "host", "hostid"',
		'groups'                => '"groups"',
		'host_group'            => '"groupid", "name"',
		'items'                 => '"itemid", "_key", "description"',
		'help_items'            => '"key_"',
		'triggers'              => '"description", "triggerid", "expression"',
		'graphs'                => '"graphid", "name"',
		'simple_graph'          => '"itemid", "description"',
		'usrgrp'                => '"usrgrpid","name"',
		'users'                 => '"usergrpid", "alias", "userid"',
		'applications'          => '"name"',
		'sysmaps'               => '"sysmapid", "name"',
		'screens'               => '"screenid", "name"',
		'slides'                => '"slideshowid"',
		'drules'                => '"druleid", "name"',
		'dcheckes'              => '"dcheckid", "name"',
		'proxies'               => '"hostid", "host"',
		'nodes'                 => '"nodeid", "name"',
		'host_templates'        => '"hostid", "host"',
		'hosts_and_templates'   => '"hostid", "host"',
		'overview'              => '"groupid", "name"',
		'host_group_scr'        => '"groupid", "name"',
		'plain_text'            => '"itemid", "description"',
		'templates' 			=> '"hostid", "host"',
		'screens2'              => '"screenid", "name"'
	);

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm' =>		array(T_ZBX_STR, O_OPT,P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
		'dstfld1'=>		array(T_ZBX_STR, O_OPT,P_SYS,	NOT_EMPTY,	'!isset({multiselect})'),
		'dstfld2'=>		array(T_ZBX_STR, O_OPT,P_SYS,	null,		null),
		'srctbl' =>		array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'srcfld1'=>		array(T_ZBX_STR, O_MAND,P_SYS,	IN($allowedSrcFields[$_REQUEST['srctbl']]),	null),
		'srcfld2'=>		array(T_ZBX_STR, O_OPT,P_SYS,	IN($allowedSrcFields[$_REQUEST['srctbl']]),		null),
		'nodeid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'groupid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'screenid'=>			array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'templates'=>			array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'host_templates'=>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'existed_templates'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'multiselect'=>			array(T_ZBX_INT, O_OPT,	NULL,	NULL,		NULL),
		'submit'=>				array(T_ZBX_STR,O_OPT,	null,	null,		null),

		'excludeids'=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
		'only_hostid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'monitored_hosts'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'real_hosts'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),

		'itemtype'=>		array(T_ZBX_INT, O_OPT, null,   null,		null),
		'value_types'=>		array(T_ZBX_INT, O_OPT, null,   BETWEEN(0,15),	null),

		'reference'=>		array(T_ZBX_STR, O_OPT, null,   null,		null),
		'writeonly'=>		array(T_ZBX_STR, O_OPT, null,   null,		null),
		'noempty'=>			array(T_ZBX_STR, O_OPT, null,   null,		null),

		'select'=>			array(T_ZBX_STR, O_OPT,	P_SYS|P_ACT,	null,	null),

		'submitParent'=>	array(T_ZBX_INT, O_OPT, null,   IN('0,1'),	null),
	);


	$allowed_item_types = array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,ITEM_TYPE_INTERNAL,ITEM_TYPE_AGGREGATE);

	if(isset($_REQUEST['itemtype']) && !str_in_array($_REQUEST['itemtype'], $allowed_item_types))
			unset($_REQUEST['itemtype']);


	$dstfrm		= get_request('dstfrm',  '');	// destination form
	$dstfld1	= get_request('dstfld1', '');	// output field on destination form
	$dstfld2	= get_request('dstfld2', '');	// second output field on destination form
	$srcfld1	= get_request('srcfld1', '');	// source table field [can be different from fields of source table]
	$srcfld2	= get_request('srcfld2', null);	// second source table field [can be different from fields of source table]
	$multiselect= get_request('multiselect', 0); //if create popup with checkboxes
	$dstact 	= get_request('dstact', '');
	$writeonly	= get_request('writeonly');
	$noempty	= get_request('noempty'); 		// display/hide "Empty" button

	check_fields($fields);

	$existed_templates = get_request('existed_templates', null);
	$excludeids = get_request('excludeids', null);

	$reference = get_request('reference','unknown');

// hosts
	$real_hosts		= get_request('real_hosts', 0);
	$monitored_hosts	= get_request('monitored_hosts', 0);
	$templated_hosts	= get_request('templated_hosts', 0);
	$only_hostid		= get_request('only_hostid', null);

// items
 	$value_types		= get_request('value_types', null);

	$submitParent = get_request('submitParent', 0);

	$host_status = null;
	$templated = null;
	if($real_hosts){
		$templated = 0;
	}
	else if($monitored_hosts){
		$host_status = 'monitored_hosts';
	}
	else if($templated_hosts){
		$templated = 1;
		$host_status = 'templated_hosts';
	}
?>
<?php
	global $USER_DETAILS;

	if($min_user_type > $USER_DETAILS['type']){
		access_deny();
	}

?>
<?php
	function get_window_opener($frame, $field, $value){
//		return empty($field) ? "" : "window.opener.document.forms['".addslashes($frame)."'].elements['".addslashes($field)."'].value='".addslashes($value)."';";
		if(empty($field)) return '';
//						"alert(window.opener.document.getElementById('".addslashes($field)."').value);".
		$script = 'try{'.
						"window.opener.document.getElementById('".addslashes($field)."').value='".addslashes($value)."'; ".
					'} catch(e){'.
						'throw("Error: Target not found")'.
					'}'."\n";

		return $script;
	}
?>
<?php
	$frmTitle = new CForm();

	if($monitored_hosts)
		$frmTitle->addVar('monitored_hosts', 1);

	if($real_hosts)
		$frmTitle->addVar('real_hosts', 1);

	if($value_types)
		$frmTitle->addVar('value_types', $value_types);

	// adding param to a form, so that it would remain when page is refreshed
	$frmTitle->addVar('dstfrm', $dstfrm);
	$frmTitle->addVar('dstact', $dstact);
	$frmTitle->addVar('dstfld1', $dstfld1);
	$frmTitle->addVar('dstfld2', $dstfld2);
	$frmTitle->addVar('srctbl', $srctbl);
	$frmTitle->addVar('srcfld1', $srcfld1);
	$frmTitle->addVar('srcfld2', $srcfld2);
	$frmTitle->addVar('multiselect', $multiselect);
	$frmTitle->addVar('writeonly', $writeonly);
	$frmTitle->addVar('reference', $reference);
	$frmTitle->addVar('submitParent', $submitParent);
	$frmTitle->addVar('noempty', $noempty);

	if(!is_null($existed_templates))
		$frmTitle->addVar('existed_templates', $existed_templates);
	if(!is_null($excludeids))
		$frmTitle->addVar('excludeids', $excludeids);


	if(isset($only_hostid)){
		$_REQUEST['hostid'] = $only_hostid;
		$frmTitle->addVar('only_hostid',$only_hostid);
		unset($_REQUEST['groupid'],$_REQUEST['nodeid']);
	}

	$options = array(
		'config' => array('select_latest' => true),
		'groups' => array('nodeids' => get_request('nodeid', get_current_nodeid(false))),
		'hosts' => array('nodeids' => get_request('nodeid', get_current_nodeid(false))),
		'groupid' => get_request('groupid', null),
		'hostid' => get_request('hostid', null),
	);

	if($monitored_hosts){
		$options['groups']['monitored_hosts'] = true;
		$options['hosts']['monitored_hosts'] = true;
	}
	else if($real_hosts){
		$options['groups']['real_hosts'] = true;
	}
	else{
		$options['hosts']['templated_hosts'] = true;
	}

	if(!is_null($writeonly)){
		$options['groups']['editable'] = true;
		$options['hosts']['editable'] = true;
	}
	$pageFilter = new CPageFilter($options);

	$nodeid = get_request('nodeid', get_current_nodeid(false));

	$groupid = null;
	if($pageFilter->groupsSelected){
		if($pageFilter->groupid > 0)
			$groupid = $pageFilter->groupid;
	}
	else{
		$groupid = 0;
	}

	$hostid = null;
	if($pageFilter->hostsSelected){
		if($pageFilter->hostid > 0)
			$hostid = $pageFilter->hostid;
	}
	else{
		$hostid = 0;
	}

	$available_nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST);
	$available_hosts = empty($pageFilter->hosts) ? array() : array_keys($pageFilter->hosts);

	if(isset($only_hostid)){
		$hostid = $_REQUEST['hostid'] = $only_hostid;

		$options = array(
			'hostids' => $hostid,
			'templated_hosts' => 1,
			'output' => array('hostid', 'host'),
			'limit' => 1
		);
		$only_hosts = CHost::get($options);
		$host = reset($only_hosts);

		if(empty($host)) access_deny();

		$cmbHosts = new CComboBox('hostid',$hostid);
		$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$host['host']);
		$cmbHosts->setEnabled('disabled');
		$cmbHosts->setAttribute('title', S_CANNOT_SWITCH_HOSTS);

		$frmTitle->addItem(array(SPACE,S_HOST,SPACE,$cmbHosts));
	}
	else{
		if(str_in_array($srctbl,array('hosts','host_group','triggers','items','simple_graph','applications',
				'screens','slides','graphs', 'sysmaps','plain_text','screens2','overview','host_group_scr'))){
			if(ZBX_DISTRIBUTED){
				$cmbNode = new CComboBox('nodeid', $nodeid, 'submit()');

				$db_nodes = DBselect('SELECT * FROM nodes WHERE '.DBcondition('nodeid',$available_nodes));
				while($node_data = DBfetch($db_nodes)){
					$cmbNode->addItem($node_data['nodeid'], $node_data['name']);
					if((bccomp($nodeid , $node_data['nodeid']) == 0)) $ok = true;
				}
				$frmTitle->addItem(array(SPACE,S_NODE,SPACE,$cmbNode,SPACE));
			}
		}

		if(!isset($ok)) $nodeid = get_current_nodeid();
		unset($ok);

		if(str_in_array($srctbl,array('hosts_and_templates','hosts','templates','triggers','items','applications','host_templates','graphs','simple_graph','plain_text'))){
			$frmTitle->addItem(array(S_GROUP,SPACE, $pageFilter->getGroupsCB(true)));
		}

		if(str_in_array($srctbl,array('help_items'))){
			$itemtype = get_request('itemtype',CProfile::get('web.popup.itemtype',0));
			$cmbTypes = new CComboBox('itemtype',$itemtype,'javascript: submit();');

			foreach($allowed_item_types as $type)
				$cmbTypes->addItem($type, item_type2str($type));
			$frmTitle->addItem(array(S_TYPE,SPACE,$cmbTypes));
		}

		if(str_in_array($srctbl,array('triggers','items','applications','graphs','simple_graph','plain_text'))){
			$frmTitle->addItem(array(SPACE,S_HOST,SPACE,$pageFilter->getHostsCB(true)));
		}

		if(str_in_array($srctbl,array('triggers','hosts','host_group','hosts_and_templates'))){
			if(zbx_empty($noempty)){
				$value1 = (isset($_REQUEST['dstfld1']) && (zbx_strpos($_REQUEST['dstfld1'], 'id') !== false))?0:'';
				$value2 = (isset($_REQUEST['dstfld2']) && (zbx_strpos($_REQUEST['dstfld2'], 'id') !== false))?0:'';

				$epmtyScript = get_window_opener($dstfrm, $dstfld1, $value1);
				$epmtyScript.= get_window_opener($dstfrm, $dstfld2, $value2);
				$epmtyScript.= ' close_window(); return false;';

				$frmTitle->addItem(array(SPACE,new CButton('empty',S_EMPTY, $epmtyScript)));
			}
		}
	}

	show_table_header($page['title'], $frmTitle);
?>
<?php

	if($srctbl == 'hosts'){
		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->setHeader(array(S_HOST,S_DNS,S_IP,S_PORT,S_STATUS,S_AVAILABILITY));

		$options = array(
			'nodeids' => $nodeid,
			'groupids'=>$groupid,
			'output' => API_OUTPUT_EXTEND,
			'sortfield'=>'host'
		);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($host_status)) $options[$host_status] = 1;

		$hosts = CHost::get($options);

		foreach($hosts as $hnum => $host){
			$name = new CSpan($host['host'], 'link');
			$action = get_window_opener($dstfrm, $dstfld1, $host[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]) : '');
			$name->setAttribute('onclick', $action.' close_window();');

			if($host['status'] == HOST_STATUS_MONITORED)
				$status = new CSpan(S_MONITORED,'off');
			else if($host['status'] == HOST_STATUS_NOT_MONITORED)
				$status = new CSpan(S_NOT_MONITORED,'on');
			else
				$status=S_UNKNOWN;

			if($host['status'] == HOST_STATUS_TEMPLATE){
				$dns = $ip = $port = $available = '-';
			}
			else{
				$dns = $host['dns'];
				$ip = $host['ip'];

				$tmp = ($host['useip'] == 1) ? 'ip' : 'dns';
				$$tmp = bold($$tmp);

				if($host['available'] == HOST_AVAILABLE_TRUE)
					$available = new CSpan(S_AVAILABLE,'off');
				else if($host['available'] == HOST_AVAILABLE_FALSE)
					$available = new CSpan(S_NOT_AVAILABLE,'on');
				else if($host['available'] == HOST_AVAILABLE_UNKNOWN)
					$available = new CSpan(S_UNKNOWN,'unknown');
			}

			$table->addRow(array(
				$name,
				$dns,
				$ip,
				$host['port'],
				$status,
				$available
				));

			unset($host);
		}
		$table->show();
	}
	else if($srctbl == 'templates'){
		$existed_templates = get_request('existed_templates', array());
		$excludeids = get_request('excludeids', array());

		$templates = get_request('templates', array());
		$templates = $templates + $existed_templates;
		if(!validate_templates(array_keys($templates))){
			show_error_message('Conflict between selected templates');
		}
		else if(isset($_REQUEST['select'])){
			$new_templates = array_diff($templates, $existed_templates);
			$script = '';
			if(count($new_templates) > 0) {
				foreach($new_templates as $id => $name){
					$script .= 'add_variable(null,"templates['.$id.']",'.zbx_jsvalue($name).','.zbx_jsvalue($dstfrm).',window.opener.document);'."\n";
				}
			} // if count new_templates > 0

			$script.= 'var form = window.opener.document.forms['.zbx_jsvalue($dstfrm).'];'.
					' if(form) form.submit();'.
					' close_window();';
			insert_js($script);

			unset($new_templates);
		}

		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
			'nodeids' => $nodeid,
			'groupids' => $groupid,
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => 'host'
		);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$template_list = CTemplate::get($options);
		foreach($template_list as $tnum => $host){

			$chk = new CCheckBox('templates['.$host['hostid'].']', isset($templates[$host['hostid']]), null, $host['host']);
			$chk->setEnabled(!isset($existed_templates[$host['hostid']]) && !isset($excludeids[$host['hostid']]));

			$table->addRow(array(array(
				$chk,
				$host['host'])
			));
		}

		$table->setFooter(new CButton('select',S_SELECT));
		$form = new CForm();
		$form->addVar('existed_templates',$existed_templates);

		if($monitored_hosts)
			$form->addVar('monitored_hosts', 1);

		if($real_hosts)
			$form->addVar('real_hosts', 1);

		$form->addVar('dstfrm',$dstfrm);
		$form->addVar('dstfld1',$dstfld1);
		$form->addVar('srctbl',$srctbl);
		$form->addVar('srcfld1',$srcfld1);
		$form->addVar('srcfld2',$srcfld2);
		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'host_group'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'output' => API_OUTPUT_EXTEND
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		foreach($hostgroups as $gnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid'], true);
			$name = new CSpan($row['name'],'link');

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'host_templates'){
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
			'nodeids' => $nodeid,
			'groupids' => $groupid,
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => 'host'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$templates = CTemplate::get($options);

		foreach($templates as $tnum => $row){
			$name = new CSpan($row['host'],'link');
			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'hosts_and_templates'){
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
			'nodeids' => $nodeid,
			'groupids' => $groupid,
			'output' => API_OUTPUT_EXTEND,
			'sortfield'=>'host'
		);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$templates = CTemplate::get($options);
		foreach($templates as $tnum => $template){
			$templates[$tnum]['hostid'] = $template['templateid'];
		}
		$hosts = CHost::get($options);
		$objects = array_merge($templates, $hosts);

		foreach($objects as $row){
			$name = new CSpan($row['host'], 'link');
			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
			(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'usrgrp'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'output' => API_OUTPUT_EXTEND
			);

		$usergroups = CUserGroup::get($options);
		order_result($usergroups, 'name');

		foreach($usergroups as $tnu => $row){
			$name = new CSpan(get_node_name_by_elid($row['usrgrpid'], null, ': ').$row['name'],'link');

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->onClick($action.' close_window(); return false;');

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'users'){
		$table = new CTableInfo(S_NO_USERS_DEFINED);
		$table->setHeader(array(S_ALIAS, S_NAME, S_SURNAME));

		$options = array(
				'nodeids' => $nodeid,
				'output' => API_OUTPUT_EXTEND
			);

		$users = CUser::get($options);
		order_result($users, 'alias');

		foreach($users as $unum => $row){
			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				(isset($_REQUEST['submit'])? " window.opener.document.getElementsByName('$dstfrm')[0].submit();":'');

			$alias = new CSpan(get_node_name_by_elid($row['userid'], null, ': ').$row['alias'], 'link');
			$alias->onClick($action.' close_window(); return false;');

			$table->addRow(array($alias, $row['name'], $row['surname']));
		}
		$table->show();
	}
	else if($srctbl == 'help_items'){
		$table = new CTableInfo(S_NO_ITEMS);
		$table->setHeader(array(S_KEY,S_DESCRIPTION));

		$result = DBselect("select * from help_items where itemtype=".$itemtype." ORDER BY key_");

		while($row = DBfetch($result)){
			$name = new CSpan($row["key_"],'link');
			$action = get_window_opener($dstfrm, $dstfld1, html_entity_decode($row[$srcfld1])).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick', $action." close_window(); return false;");

			$table->addRow(array(
				$name,
				$row['description']
				));
		}
		$table->show();
	}
	else if($srctbl == 'triggers'){
		$form = new CForm();
		$form->setName('triggerform');
		$form->setAttribute('id', 'triggers');

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		$table->setHeader(array(
			($multiselect ? new CCheckBox("all_triggers", NULL, "javascript: checkAll('".$form->getName()."', 'all_triggers','triggers');") : null),
			S_NAME,
			S_SEVERITY,
			S_STATUS
		));

		$options = array(
			'nodeids' => $nodeid,
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND,
			'select_dependencies' => API_OUTPUT_EXTEND,
			'expandDescription' => 1,
		);
		if(is_null($hostid)) $options['groupids'] = $groupid;
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$triggers = CTrigger::get($options);
		order_result($triggers, 'description');

		foreach($triggers as $tnum => $trigger){
			$host = reset($trigger['hosts']);
			$trigger['host'] = $host['host'];

			$description = new CSpan($trigger['description'], 'link');

			$trigger['description'] = $trigger['host'].':'.$trigger['description'];


			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($trigger[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $trigger[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $trigger[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$description->setAttribute('onclick', $action);

			if(count($trigger['dependencies']) > 0){
				$description = array(
					$description,
					BR(),
					bold(S_DEPENDS_ON),
					BR());

				foreach($trigger['dependencies'] as $val)
					$description[] = array(expand_trigger_description_by_data($val),BR());
			}

			switch($trigger['status']) {
				case TRIGGER_STATUS_DISABLED:
					$status = new CSpan(S_DISABLED, 'disabled');
				break;
				case TRIGGER_STATUS_ENABLED:
					$status = new CSpan(S_ENABLED, 'enabled');
				break;
			}

			$table->addRow(array(
				($multiselect ? new CCheckBox('triggers['.zbx_jsValue($trigger[$srcfld1]).']', NULL, NULL, $trigger['triggerid']) : null),
				$description,
				new CCol(get_severity_description($trigger['priority']), get_severity_style($trigger['priority'])),
				$status
			));
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('triggers', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}

	// items
	elseif ($srctbl == 'items') {
		$form = new CForm();
		$form->setName('itemform');
		$form->setAttribute('id', 'items');

		if ($multiselect) {
			$header = array(
				$pageFilter->hostsAll ? S_HOST : null,
				array(new CCheckBox('all_items', NULL, "javascript: checkAll('".$form->getName()."', 'all_items','items');"), S_DESCRIPTION),
				S_KEY,
				S_TYPE,
				S_TYPE_OF_INFORMATION,
				S_STATUS
			);
		}
		else {
			$header = array(
				$pageFilter->hostsAll ? S_HOST : null,
				S_DESCRIPTION,
				S_KEY,
				S_TYPE,
				S_TYPE_OF_INFORMATION,
				S_STATUS
			);
		}

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->setHeader($header);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		$options = array(
			'nodeids' => $nodeid,
			'webitems' => 1,
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND
		);
		if ($pageFilter->hostsAll) {
			$options['hostids'] = array_keys($pageFilter->hosts);
		}
		else {
			$options['hostids'] = $pageFilter->hostid;
		}
		if (!is_null($writeonly)) $options['editable'] = 1;
		if (!is_null($templated)) $options['templated'] = $templated;
		if (!is_null($value_types)) $options['filter']['value_type'] = $value_types;

		$items = CItem::get($options);
		order_result($items, 'description', ZBX_SORT_UP);

		foreach ($items as $tnum => $row) {
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];
			$row['description'] = item_description($row);
			$description = new CLink($row['description'], '#');
			$row['description'] = $row['host'].':'.$row['description'];

			if ($multiselect) {
				$description->setAttribute('onclick', 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row[$srcfld1]).');');
				$description = new CCol(array(new CCheckBox('items['.zbx_jsValue($row[$srcfld1]).']', NULL, NULL, $row['itemid']), $description));
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
				$description->setAttribute('onclick', $action);
			}

			$table->addRow(array(
				($hostid > 0) ? null : $row['host'],
				$description,
				$row['key_'],
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
			));
		}

		if ($multiselect) {
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('items', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'applications'){
		$table = new CTableInfo(S_NO_APPLICATIONS_DEFINED);
		$table->setHeader(array(
			($hostid>0)?null:S_HOST,
			S_NAME
		));

		$options = array(
			'nodeids' => $nodeid,
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'expandData' => true,
		);
		if(is_null($hostid)) $options['groupids'] = $groupid;
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$apps = CApplication::get($options);
		ArraySorter::sort($apps, array('host', 'name'));

		foreach($apps as $app){
			$name = new CSpan($app['name'], 'link');

			$action = get_window_opener($dstfrm, $dstfld1, $app[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $app[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow(array(($hostid>0)?null:$app['host'], $name));
		}
		$table->show();
	}
	else if($srctbl == "nodes"){
		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->setHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT * FROM nodes WHERE '.DBcondition('nodeid',$available_nodes));
		while($row = DBfetch($result)){
			$name = new CSpan($row["name"],'link');

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'graphs'){
		$form = new CForm();
		$form->setName('graphform');
		$form->setAttribute('id', 'graphs');

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		if($multiselect)
			$header = array(
				array(new CCheckBox("all_graphs", NULL, "javascript: checkAll('".$form->getName()."', 'all_graphs','graphs');"), S_DESCRIPTION),
				S_GRAPH_TYPE
			);
		else
			$header = array(
				S_NAME,
				S_GRAPH_TYPE
			);

		$table->setHeader($header);

		if ($pageFilter->hostsSelected) {
			if ($pageFilter->hostsAll) {
				$hostid = array_keys($pageFilter->hosts);
			}
			else {
				$hostid = $pageFilter->hostid;
			}

			$options = array(
				'hostids' => $hostid,
				'output' => API_OUTPUT_EXTEND,
				'nodeids' => $nodeid,
				'select_hosts' => API_OUTPUT_EXTEND,
			);
			if(!is_null($writeonly)) $options['editable'] = 1;
			if(!is_null($templated)) $options['templated'] = $templated;
			$graphs = CGraph::get($options);
			order_result($graphs, 'name');
		}
		else {
			$graphs = array();
		}

		foreach($graphs as $gnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['node_name'] = get_node_name_by_elid($row['graphid'], null, ': ');
			$name = $row['node_name'].$row['host'].':'.$row['name'];

			$description = new CSpan($row['name'],'link');

			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$description->setAttribute('onclick', $action);

			if($multiselect){
				$description = new CCol(array(new CCheckBox('graphs['.zbx_jsValue($row[$srcfld1]).']', NULL, NULL, $row['graphid']), $description));
			}

			switch($row['graphtype']){
				case  GRAPH_TYPE_STACKED:
					$graphtype = S_STACKED;
					break;
				case  GRAPH_TYPE_PIE:
					$graphtype = S_PIE;
					break;
				case  GRAPH_TYPE_EXPLODED:
					$graphtype = S_EXPLODED;
					break;
				default:
					$graphtype = S_NORMAL;
					break;
			}

			$table->addRow(array(
				$description,
				$graphtype
			));

			unset($description);
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('graphs', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	elseif ($srctbl == 'simple_graph') {
		$form = new CForm();
		$form->setName('itemform');
		$form->setAttribute('id', 'items');

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		if ($pageFilter->hostsSelected) {
			if ($pageFilter->hostsAll) {
				$hostid = array_keys($pageFilter->hosts);
			}
			else {
				$hostid = $pageFilter->hostid;
			}

			$options = array(
				'nodeids' => $nodeid,
				'hostids' => $hostid,
				'monitored' => true,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'webitems' => true,
				'templated' => false,
				'filter' => array(
					'value_type' => array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64),
				)
			);
			if (!is_null($writeonly)) {
				$options['editable'] = 1;
			}
			if (!is_null($templated)) {
				$options['templated'] = $templated;
			}

			$items = CItem::get($options);
			order_result($items, 'description');
		}
		else {
			$items = array();
		}

		if ($multiselect) {
			$header = array(
				is_array($hostid) ? S_HOST : null,
				array(new CCheckBox("all_items", null, "javascript: checkAll('".$form->getName()."', 'all_items','items');"), S_DESCRIPTION),
				S_TYPE,
				S_TYPE_OF_INFORMATION
			);
		}
		else {
			$header = array(
				is_array($hostid) ? S_HOST : null,
				S_DESCRIPTION,
				S_TYPE,
				S_TYPE_OF_INFORMATION
			);
		}

		$table->setHeader($header);

		foreach ($items as $tnum => $row) {
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['description'] = item_description($row);
			$description = new CLink($row['description'], '#');

			$row['description'] = $row['host'].':'.$row['description'];

			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$description->setAttribute('onclick', $action);

			if ($multiselect) {
				$description = new CCol(array(new CCheckBox('items['.zbx_jsValue($row[$srcfld1]).']', null, null, $row['itemid']), $description));
			}

			$table->addRow(array(
				is_array($hostid) ? $row['host'] : null,
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type'])
			));
		}

		if ($multiselect) {
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('items', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'sysmaps'){
		$form = new CForm();
		$form->setName('sysmapform');
		$form->setAttribute('id', 'sysmaps');

		$table = new CTableInfo(S_NO_MAPS_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		if($multiselect)
			$header = array(array(new CCheckBox("all_sysmaps", NULL, "javascript: checkAll('".$form->getName()."', 'all_sysmaps','sysmaps');"), S_NAME));
		else
			$header = array(S_NAME);

		$table->setHeader($header);

		$excludeids = get_request('excludeids', array());
		$excludeids = zbx_toHash($excludeids);

		$options = array(
			'nodeids' => $nodeid,
			'output' => API_OUTPUT_EXTEND
		);
		if(!is_null($writeonly)) $options['editable'] = true;

		$sysmaps = CMap::get($options);
		order_result($sysmaps, 'name');

		foreach($sysmaps as $mnum => $sysmap){
			if(isset($excludeids[$sysmap['sysmapid']])) continue;

			$sysmap['node_name'] = isset($sysmap['node_name']) ? '('.$sysmap['node_name'].') ' : '';
			$name = $sysmap['node_name'].$sysmap['name'];

			$description = new CSpan($sysmap['name'], 'link');

			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($sysmap[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $sysmap[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $sysmap[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$description->setAttribute('onclick', $action);

			if($multiselect){
				$description = new CCol(array(new CCheckBox('sysmaps['.zbx_jsValue($sysmap[$srcfld1]).']', NULL, NULL, $sysmap['sysmapid']), $description));
			}

			$table->addRow($description);

			unset($description);
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('sysmaps', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'plain_text'){

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->setHeader(array(
			($hostid>0)?null:S_HOST,
			S_DESCRIPTION,
			S_KEY,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$options = array(
			'nodeids' => $nodeid,
			'hostids'=> $hostid,
			'output' => API_OUTPUT_EXTEND,
			'select_hosts' => API_OUTPUT_EXTEND,
			'templated' => false,
			'filter' => array(
				'status' => ITEM_STATUS_ACTIVE
			),
			'sortfield'=>'description'
		);
		if(is_null($hostid)) $options['groupids'] = $groupid;
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$items = CItem::get($options);

		foreach($items as $tnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['description'] = item_description($row);
			$description = new CSpan($row['description'],'link');
			$row['description'] = $row['host'].':'.$row['description'];

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);

			$description->setAttribute('onclick',$action.' close_window(); return false;');

			$table->addRow(array(
				($hostid>0)?null:$row['host'],
				$description,
				$row['key_'],
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->show();
	}
	else if($srctbl == 'slides'){
		require_once('include/screens.inc.php');

		$form = new CForm();
		$form->setName('slideform');
		$form->setAttribute('id', 'slides');

		$table = new CTableInfo(S_NO_SLIDES_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		if($multiselect)
			$header = array(
				array(new CCheckBox("all_slides", NULL, "javascript: checkAll('".$form->getName()."', 'all_slides','slides');"), S_NAME),
			);
		else
			$header = array(
				S_NAME
			);

		$table->setHeader($header);

		$result = DBselect('select slideshowid,name from slideshows where '.DBin_node('slideshowid',$nodeid).' ORDER BY name');
		while($row=DBfetch($result)){
			if(!slideshow_accessible($row['slideshowid'], PERM_READ_ONLY))
				continue;

			$name = new CLink($row['name'],'#');

			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$name->setAttribute('onclick', $action);

			if($multiselect){
				$name = new CCol(array(new CCheckBox('slides['.zbx_jsValue($row[$srcfld1]).']', NULL, NULL, $row['slideshowid']), $name));
			}


			$table->addRow($name);
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('slides', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'screens'){
		require_once('include/screens.inc.php');

		$form = new CForm();
		$form->setName('screenform');
		$form->setAttribute('id', 'screens');

		$table = new CTableInfo(S_NO_SCREENS_DEFINED);

		insert_js_function('addSelectedValues');
		insert_js_function('addValue');

		if($multiselect)
			$header = array(
				array(new CCheckBox("all_screens", NULL, "javascript: checkAll('".$form->getName()."', 'all_screens','screens');"), S_NAME),
			);
		else
			$header = array(
				S_NAME
			);

		$table->setHeader($header);

		$options = array(
			'nodeids' => $nodeid,
			'output' => API_OUTPUT_EXTEND
		);

		$screens = CScreen::get($options);
		order_result($screens, 'name');

		foreach($screens as $snum => $row){
			$name = new CSpan($row["name"],'link');

			if ($multiselect) {
				$action = 'javascript: addValue('.zbx_jsvalue($reference).', '.zbx_jsvalue($row[$srcfld1]).');';
			}
			else {
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]);
				if ($srcfld2) {
					$action .= get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
				}
				$action .= ' close_window(); return false;';
			}

			$name->setAttribute('onclick', $action);

			if($multiselect){
				$name = new CCol(array(new CCheckBox('screens['.zbx_jsValue($row[$srcfld1]).']', NULL, NULL, $row['screenid']), $name));
			}

			$table->addRow($name);
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, "javascript: addSelectedValues('screens', ".zbx_jsvalue($reference).");");
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == 'screens2'){
		require_once('include/screens.inc.php');

		$table = new CTableInfo(S_NO_SCREENS_DEFINED);
		$table->setHeader(S_NAME);

		$options = array(
			'nodeids' => $nodeid,
			'output' => API_OUTPUT_EXTEND
		);

		$screens = CScreen::get($options);
		order_result($screens, 'name');

		foreach($screens as $snum => $row){
			$row['node_name'] = get_node_name_by_elid($row['screenid'], true);

			if(check_screen_recursion($_REQUEST['screenid'],$row['screenid'])) continue;

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

			$name = new CLink($row['name'],'#');
			$row['name'] = $row['node_name'].$row['name'];

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->show();
	}
	else if($srctbl == 'overview'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(S_NAME);

		$options = array(
				'nodeids' => $nodeid,
				'monitored_hosts' => 1,
				'output' => API_OUTPUT_EXTEND
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		foreach($hostgroups as $gnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid']);
			$name = new CSpan($row['name'],'link');

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$row['name'] = $row['node_name'].$row['name'];

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->show();
	}
	else if($srctbl == 'host_group_scr'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'output' => API_OUTPUT_EXTEND
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		$all = false;
		foreach($hostgroups as $hgnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid']);

			if(!$all){
				$name = new CLink(bold(S_MINUS_ALL_GROUPS_MINUS),'#');

				$action = get_window_opener($dstfrm, $dstfld1, create_id_by_nodeid(0,$nodeid)).
					get_window_opener($dstfrm, $dstfld2, $row['node_name'].S_MINUS_ALL_GROUPS_MINUS);

				$name->setAttribute('onclick',$action." close_window(); return false;");

				$table->addRow($name);
				$all = true;
			}

			$name = new CLink($row['name'],'#');
			$row['name'] = $row['node_name'].$row['name'];

			$name->setAttribute('onclick',
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				' return close_window();');

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'drules'){
		$table = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$table->setHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT * FROM drules WHERE '.DBin_node('druleid', $nodeid));
		while($row = DBfetch($result)){
			$name = new CSpan($row["name"],'link');

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'dchecks'){
		$table = new CTableInfo(S_NO_DISCOVERY_CHECKS_DEFINED);
		$table->setHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT r.name,c.dcheckid,c.type,c.key_,c.snmp_community,c.ports FROM drules r,dchecks c'.
				' WHERE r.druleid=c.druleid and '.DBin_node('r.druleid', $nodeid));
		while($row = DBfetch($result)){
			$row['name'] = $row['name'].':'.discovery_check2str($row['type'],
					$row['snmp_community'], $row['key_'], $row['ports']);
			$name = new CSpan($row["name"],'link');

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == 'proxies'){
		$table = new CTableInfo(S_NO_PROXIES_DEFINED);
		$table->setHeader(S_NAME);

		$sql = 'SELECT DISTINCT hostid,host '.
				' FROM hosts'.
				' WHERE '.DBin_node('hostid', $nodeid).
					' AND status IN ('.HOST_STATUS_PROXY_ACTIVE.','.HOST_STATUS_PROXY_PASSIVE.')'.
				' ORDER BY host,hostid';
		$result = DBselect($sql);
		while($row = DBfetch($result)){
			$name = new CSpan($row["host"],'link');

			$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
?>
<?php

include_once('include/page_footer.php');

?>
