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

	$srctbl		= get_request("srctbl",  '');	// source table name

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
		case 'logitems':
			$page['title'] = 'S_ITEMS_BIG';
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
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'dstfrm' =>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'dstact'=>			array(T_ZBX_STR, O_OPT,	null,	null,	'isset({multiselect})'),
		'dstfld1'=>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'dstfld2'=>			array(T_ZBX_STR, O_OPT,P_SYS,	null,		null),
		'srctbl' =>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'srcfld1'=>			array(T_ZBX_STR, O_MAND,P_SYS,	NOT_EMPTY,	null),
		'srcfld2'=>			array(T_ZBX_STR, O_OPT,P_SYS,	null,		null),
		'nodeid'=>			array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'groupid'=>			array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'screenid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'templates'=>		array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'host_templates'=>	array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'existed_templates'=>array(T_ZBX_STR, O_OPT,	null,	NOT_EMPTY,	null),
		'multiselect'=>		array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL),
		'submit'=>			array(T_ZBX_STR,O_OPT,	null,	null,	null),

		'excludeids'=>		array(T_ZBX_STR, O_OPT,	null,	null,	null),
		'only_hostid'=>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'monitored_hosts'=>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'real_hosts'=>		array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
		'itemtype'=>		array(T_ZBX_INT, O_OPT, null,   null,		null),

		'reference'=>		array(T_ZBX_STR, O_OPT, null,   null,		null),
		'writeonly'=>		array(T_ZBX_STR, O_OPT, null,   null,		null),

//dmaps
		'sysmapid'=>	array(T_ZBX_INT, O_OPT, null,   DB_ID,		null),
		'cmapid'=>		array(T_ZBX_INT, O_OPT, null,   null,		null),
		'sid'=>			array(T_ZBX_INT, O_OPT, null,   null,		'isset({reference})'),
		'ssid'=>		array(T_ZBX_INT, O_OPT, null,   null,		null),

		'select'=>		array(T_ZBX_STR,	O_OPT,	P_SYS|P_ACT,	null,	null)
	);

	$allowed_item_types = array(ITEM_TYPE_ZABBIX,ITEM_TYPE_ZABBIX_ACTIVE,ITEM_TYPE_SIMPLE,ITEM_TYPE_INTERNAL,ITEM_TYPE_AGGREGATE);

	if(isset($_REQUEST['itemtype']) && !str_in_array($_REQUEST['itemtype'], $allowed_item_types))
			unset($_REQUEST['itemtype']);

	check_fields($fields);

	$dstfrm		= get_request('dstfrm',  '');	// destination form
	$dstfld1	= get_request('dstfld1', '');	// output field on destination form
	$dstfld2	= get_request('dstfld2', '');	// second output field on destination form
	$srcfld1	= get_request('srcfld1', '');	// source table field [can be different from fields of source table]
	$srcfld2	= get_request('srcfld2', null);	// second source table field [can be different from fields of source table]
	$multiselect = get_request('multiselect', 0); //if create popup with checkboxes
	$dstact 	= get_request('dstact', '');
	$writeonly = get_request('writeonly');

	$existed_templates = get_request('existed_templates', null);
	$excludeids = get_request('excludeids', null);


	$real_hosts			= get_request('real_hosts', 0);
	$monitored_hosts	= get_request('monitored_hosts', 0);
	$templated_hosts	= get_request('templated_hosts', 0);
	$only_hostid		= get_request('only_hostid', null);

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

	$frmTitle->addVar('dstfrm', $dstfrm);
	$frmTitle->addVar('dstact', $dstact);
	$frmTitle->addVar('dstfld1', $dstfld1);
	$frmTitle->addVar('dstfld2', $dstfld2);
	$frmTitle->addVar('srctbl', $srctbl);
	$frmTitle->addVar('srcfld1', $srcfld1);
	$frmTitle->addVar('srcfld2', $srcfld2);
	$frmTitle->addVar('multiselect', $multiselect);
	$frmTitle->addVar('writeonly', $writeonly);
	if(!is_null($existed_templates))
		$frmTitle->addVar('existed_templates', $existed_templates);
	if(!is_null($excludeids))
		$frmTitle->addVar('excludeids', $excludeids);


// Optional
	if(isset($_REQUEST['reference'])){
		$frmTitle->addVar('reference',	get_request('reference','0'));
		$frmTitle->addVar('sysmapid',	get_request('sysmapid','0'));
		$frmTitle->addVar('cmapid',		get_request('cmapid','0'));
		$frmTitle->addVar('sid',		get_request('sid','0'));
		$frmTitle->addVar('ssid',		get_request('ssid','0'));
	}

	if(isset($only_hostid)){
		$_REQUEST['hostid'] = $only_hostid;
		$frmTitle->addVar('only_hostid',$only_hostid);
		unset($_REQUEST['groupid'],$_REQUEST['nodeid']);
	}

	$validation_param = array('deny_all','select_first_group_if_empty','select_first_host_if_empty','select_host_on_group_switch');
	if($monitored_hosts) array_push($validation_param, 'monitored_hosts');
	if($real_hosts) 	array_push($validation_param, 'real_hosts');
//	if(isset($templated_hosts)) array_push($validation_param, 'templated_hosts');

	$nodeid = get_request('nodeid', get_current_nodeid(false));

	$params = array();
	foreach($validation_param as $option) $params[$option] = 1;

	$perm = !is_null($writeonly)?PERM_READ_WRITE:PERM_READ_ONLY;
	$PAGE_GROUPS = get_viewed_groups($perm, $params, $nodeid);
	$PAGE_HOSTS = get_viewed_hosts($perm, $PAGE_GROUPS['selected'], $params, $nodeid);

	if(str_in_array($srctbl, array('graphs','applications','screens','triggers','logitems','items','simple_graph','plain_text'))){
		validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
	}
	else if(str_in_array($srctbl,array('host_group','hosts','templates','host_templates','hosts_and_templates'))){
		validate_group($PAGE_GROUPS, $PAGE_HOSTS);
	}

	$groupid = 0;
	$hostid = 0;

	$available_nodes	= get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST);
	$available_groups	= $PAGE_GROUPS['groupids'];
	$available_hosts	= $PAGE_HOSTS['hostids'];

	if(isset($only_hostid)){
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, $perm);
		if(!isset($available_hosts[$only_hostid])) access_deny();

		$hostid = $_REQUEST['hostid'] = $only_hostid;
	}
	else{
		if(str_in_array($srctbl,array('hosts','host_group','triggers','logitems','items',
									'applications','screens','slides','graphs','simple_graph',
									'sysmaps','plain_text','screens2','overview','host_group_scr')))
		{
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

		if(str_in_array($srctbl,array('hosts_and_templates', 'hosts','templates','triggers','logitems','items','applications','host_templates','graphs','simple_graph','plain_text'))){

			$groupid = $PAGE_GROUPS['selected'];
			$cmbGroups = new CComboBox('groupid',$groupid,'javascript: submit();');
			foreach($PAGE_GROUPS['groups'] as $slct_groupid => $name){
				$cmbGroups->addItem($slct_groupid, get_node_name_by_elid($slct_groupid, null, ': ').$name);
			}

			$frmTitle->addItem(array(S_GROUP,SPACE,$cmbGroups));
			CProfile::update('web.popup.groupid',$groupid,PROFILE_TYPE_ID);
		}

		if(str_in_array($srctbl,array('help_items'))){
			$itemtype = get_request('itemtype',CProfile::get('web.popup.itemtype',0));
			$cmbTypes = new CComboBox('itemtype',$itemtype,'javascript: submit();');
			foreach($allowed_item_types as $type)
				$cmbTypes->addItem($type, item_type2str($type));
			$frmTitle->addItem(array(S_TYPE,SPACE,$cmbTypes));
		}

		if(str_in_array($srctbl,array('triggers','logitems','items','applications','graphs','simple_graph','plain_text'))){
			$hostid = $PAGE_HOSTS['selected'];

			$cmbHosts = new CComboBox('hostid',$hostid,'javascript: submit();');
			foreach($PAGE_HOSTS['hosts'] as $tmp_hostid => $name){
				$cmbHosts->addItem($tmp_hostid, get_node_name_by_elid($tmp_hostid, null, ': ').$name);
			}

			$frmTitle->addItem(array(SPACE,S_HOST,SPACE,$cmbHosts));
			CProfile::update('web.popup.hostid',$hostid,PROFILE_TYPE_ID);
		}

		if(str_in_array($srctbl,array('triggers','hosts','host_group'))){
			$btnEmpty = new CButton('empty',S_EMPTY,
				get_window_opener($dstfrm, $dstfld1, 0).
				get_window_opener($dstfrm, $dstfld2, '').
				((isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard'))?"window.opener.setTimeout('add2favorites();', 1000);":'').
				" close_window(); return false;");

			$frmTitle->addItem(array(SPACE,$btnEmpty));
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
				'extendoutput' => 1,
				'sortfield'=>'host'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($host_status)) $options[$host_status] = 1;

		$hosts = CHost::get($options);

		foreach($hosts as $hnum => $host){

			$name = new CSpan($host['host'],'link');
			if(isset($_REQUEST['reference'])){
				$cmapid = get_request('cmapid',0);
				$sid = get_request('sid',0);

				$action = '';
				if($_REQUEST['reference']=='dashboard'){
					$action = get_window_opener($dstfrm, $dstfld1, $srctbl).
						get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]).
						"window.opener.setTimeout('add2favorites();', 1000);";
				}
				else if($_REQUEST['reference']=='sysmap_element'){
					$action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_selement_option($sid,".
									"[{'key':'elementtype','value':'".SYSMAP_ELEMENT_TYPE_HOST."'},".
										"{'key':'$dstfld1','value':'$host[$srcfld1]'}]);";
				}
				else if($_REQUEST['reference']=='sysmap_link'){
					$action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_link_option($sid,[{'key':'$dstfld1','value':'$host[$srcfld1]'}]);";
				}
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $host[$srcfld1]).
					(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $host[$srcfld2]) : '');
			}

			$name->setAttribute('onclick', $action." close_window();");

			if($host["status"] == HOST_STATUS_MONITORED)
				$status=new CSpan(S_MONITORED,"off");
			else if($host["status"] == HOST_STATUS_NOT_MONITORED)
				$status=new CSpan(S_NOT_MONITORED,"on");
			else
				$status=S_UNKNOWN;

			if($host["status"] == HOST_STATUS_TEMPLATE){
				$dns = $ip = $port = $available = '-';
			}
			else{
				$dns = $host['dns'];
				$ip = $host['ip'];

				if($host["useip"]==1)
					$ip = bold($ip);
				else
					$dns = bold($dns);

				$port = $host["port"];

				if($host["available"] == HOST_AVAILABLE_TRUE)
					$available=new CSpan(S_AVAILABLE,"off");
				else if($host["available"] == HOST_AVAILABLE_FALSE)
					$available=new CSpan(S_NOT_AVAILABLE,"on");
				else if($host["available"] == HOST_AVAILABLE_UNKNOWN)
					$available=new CSpan(S_UNKNOWN,"unknown");

			}

			$table->addRow(array(
				$name,
				$dns,
				$ip,
				$port,
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
					$script .= 'add_variable(null,"templates['.$id.']","'.$name.'","'.$dstfrm.'",window.opener.document);'."\n";
				}


			} // if count new_templates > 0

			$script.= 'var form = window.opener.document.forms["'.$dstfrm.'"];'.
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
				'extendoutput' => 1,
				'sortfield' => 'host'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$template_list = CTemplate::get($options);
		foreach($template_list as $tnum => $host){

			$chk = new CCheckBox('templates['.$host['hostid'].']', isset($templates[$host['hostid']]), null, $host['host']);
			$chk->setEnabled(!isset($existed_templates[$host['hostid']]) && !isset($excludeids[$host['hostid']]));

			$table->addRow(array(
				array(
					$chk,
					$host['host'])
				));

			unset($host);
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
	else if(str_in_array($srctbl,array('host_group'))){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'extendoutput' => 1
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		foreach($hostgroups as $gnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid'], true);
			$name = new CSpan($row['name'],'link');

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$row['name'] = $row['node_name'].$row['name'];

			if(isset($_REQUEST['reference'])){
				$cmapid = get_request('cmapid',0);
				$sid = get_request('sid',0);

				$action = '';
				if($_REQUEST['reference']=='dashboard'){
					$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
						get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
						"window.opener.setTimeout('add2favorites();', 1000);";
				}
				else if($_REQUEST['reference'] =='sysmap_element'){
					$action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_selement_option($sid,".
									"[{'key':'elementtype','value':'".SYSMAP_ELEMENT_TYPE_HOST_GROUP."'},".
										"{'key':'$dstfld1','value':'$row[$srcfld1]'}]);";
				}
				else if($_REQUEST['reference'] =='sysmap_link'){
					$action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_link_option($sid,[{'key':'$dstfld1','value':'$row[$srcfld1]'}]);";
				}
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if(str_in_array($srctbl,array('host_templates'))){
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'groupids'=>$groupid,
				'extendoutput' => 1,
				'sortfield'=>'host'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$templates = CTemplate::get($options);

		foreach($templates as $tnum => $row){
			$name = new CSpan($row['host'],'link');
			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->show();
	}
	else if(str_in_array($srctbl,array('hosts_and_templates'))){
		$table = new CTableInfo(S_NO_TEMPLATES_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
			'nodeids' => $nodeid,
			'groupids' => $groupid,
			'extendoutput' => 1,
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
	else if($srctbl == "usrgrp"){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'extendoutput' => 1
			);

		$usergroups = CUserGroup::get($options);
		order_result($usergroups, 'name');

		foreach($usergroups as $tnu => $row){
			$name = new CSpan(get_node_name_by_elid($row['usrgrpid'], null, ': ').$row['name'],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->onClick($action.' close_window(); return false;');

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == "users"){
		$table = new CTableInfo(S_NO_USERS_DEFINED);
		$table->setHeader(array(S_ALIAS, S_NAME, S_SURNAME));

		$options = array(
				'nodeids' => $nodeid,
				'extendoutput' => 1
			);

		$users = CUser::get($options);
		order_result($users, 'alias');

		foreach($users as $unum => $row){
			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '').
				(isset($_REQUEST['submit'])?" window.opener.document.getElementsByName('$dstfrm')[0].submit();":'');
			}
			$alias = new CSpan(get_node_name_by_elid($row['userid'], null, ': ').$row['alias'], 'link');
			$alias->onClick($action.' close_window(); return false;');

			$table->addRow(array($alias, $row['name'], $row['surname']));
		}
		$table->Show();
	}
	else if($srctbl == "help_items"){
		$table = new CTableInfo(S_NO_ITEMS);
		$table->SetHeader(array(S_KEY,S_DESCRIPTION));

		$result = DBselect("select * from help_items where itemtype=".$itemtype." ORDER BY key_");

		while($row = DBfetch($result)){
			$name = new CSpan($row["key_"],'link');
			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, html_entity_decode($row[$srcfld1])).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

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
		$form->setAttribute('id', S_TRIGGERS);

		$table = new CTableInfo(S_NO_TRIGGERS_DEFINED);

		if($multiselect) {
			insert_js_function('add_selected_values');
			insert_js_function('check_all');
			$header = array(new CCol(array(new CCheckBox("check", NULL, 'check_all("'.S_TRIGGERS.'", this.checked);'), S_NAME)), S_SEVERITY, S_STATUS);
		}
		else {
			insert_js_function('add_value');
			$header = array(S_NAME, S_SEVERITY,	S_STATUS);
		}
		$table->setHeader($header);

		$options = array(
				'nodeids' => $nodeid,
				'hostids' => $hostid,
				'extendoutput' => 1,
				'select_hosts' => 1,
				'select_dependencies' => 1
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$triggers = CTrigger::get($options);
		order_result($triggers, 'description');

		foreach($triggers as $tnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$exp_desc = expand_trigger_description_by_data($row);
			$description = new CSpan($exp_desc, 'link');

			if($multiselect) {
				$js_action = 'add_selected_values("'.S_TRIGGERS.'", "'.$dstfrm.'", "'.$dstfld1.'", "'.$dstact.'", "'.$row["triggerid"].'");';
			}
			else {
				if(isset($_REQUEST['reference'])){
					$cmapid = get_request('cmapid',0);
					$sid = get_request('sid',0);
					$ssid = get_request('ssid',0);

					$js_action = '';
					if($_REQUEST['reference'] == 'sysmap_element'){
						$js_action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_selement_option($sid,".
										"[{'key':'elementtype','value':'".SYSMAP_ELEMENT_TYPE_TRIGGER."'},".
											"{'key':'$dstfld1','value':'".$row[$srcfld1]."'}]);";
					}
					else if($_REQUEST['reference'] =='sysmap_linktrigger'){
						$params = array(array('key'=> $dstfld1, 'value'=> $row[$srcfld1]));

						if($dstfld1 == 'triggerid'){
							$params[] = array('key'=> 'desc_exp', 'value'=> $row['host'].':'.$exp_desc);
						}

						$js_action = "window.opener.ZBX_SYSMAPS[$cmapid].map.update_linktrigger_option($sid,$ssid, ".zbx_jsvalue($params).");";
					}
				}
				else{
					$js_action = 'add_value("'.$dstfld1.'", "'.$dstfld2.'", "'.$row["triggerid"].'", '.zbx_jsvalue($row['host'].':'.$exp_desc).');';
				}
			}

			$description->setAttribute('onclick', $js_action."; close_window(); return false;");

			if(count($row['dependencies']) > 0){
				$description = array(
					$description,
					BR(),BR(),
					bold(S_DEPENDS_ON),
					BR());

				$deps = get_trigger_dependencies_by_triggerid($row['triggerid']);
				foreach($row['dependencies'] as $val)
					$description[] = array(expand_trigger_description_by_data($val),BR());
			}

			switch($row["status"]) {
				case TRIGGER_STATUS_DISABLED:
					$status = new CSpan(S_DISABLED, 'disabled');
				break;
				case TRIGGER_STATUS_UNKNOWN:
					$status = new CSpan(S_UNKNOWN, 'unknown');
				break;
				case TRIGGER_STATUS_ENABLED:
					$status = new CSpan(S_ENABLED, 'enabled');
				break;
			}
			//if($row["status"] != TRIGGER_STATUS_UNKNOWN) $row["error"]=SPACE;
			//if($row["error"]=="") $row["error"]=SPACE;

			if($multiselect){
				$description = new CCol(array(new CCheckBox('trigger['.$row['triggerid'].']', NULL, NULL, $row['triggerid']),	$description));
			}

			$table->addRow(array(
				$description,
				new CCol(get_severity_description($row['priority']), get_severity_style($row['priority'])),
				$status
			));


			unset($description);
			unset($status);
		}

		if($multiselect){
			$button = new CButton('select', S_SELECT, 'add_selected_values("'.S_TRIGGERS.'", "'.$dstfrm.'", "'.$dstfld1.'", "'.$dstact.'")');
			$button->setType('button');
			$table->setFooter(new CCol($button, 'right'));
		}

		$form->addItem($table);
		$form->show();
	}
	else if($srctbl == "logitems"){
		insert_js_function('add_item_variable');

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);

		$table->setHeader(array(
				($hostid>0)?null:S_HOST,
				S_DESCRIPTION,
				S_KEY,nbsp(S_UPDATE_INTERVAL),
				S_STATUS
			));

		$options = array(
				'nodeids' => $nodeid,
				'hostids'=>$hostid,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'filter' => array(
					'value_type' => ITEM_VALUE_TYPE_LOG,
				),
				
				'sortfield'=>'description'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$items = CItem::get($options);

		foreach($items as $inum => $db_item){
			$host = reset($db_item['hosts']);
			$db_item['host'] = $host['host'];

			$description = new CSpan(item_description($db_item),'link');
			$description->onClick("return add_item_variable('".$dstfrm."','".$db_item["itemid"]."');");

			switch($db_item["status"]){
				case 0: $status=new CCol(S_ACTIVE,"enabled");		break;
				case 1: $status=new CCol(S_DISABLED,"disabled");	break;
				case 3: $status=new CCol(S_NOT_SUPPORTED,"unknown");	break;
				default:$status=S_UNKNOWN;
			}

			$table->addRow(array(
				($hostid>0)?null:$db_item['host'],
				$description,
				$db_item["key_"],
				$db_item["delay"],
				$status
				));
		}
		unset($db_items, $db_item);

		$table->Show();
	}
	else if($srctbl == "items"){
		$table = new CTableInfo(S_SELECT_HOST_DOT_DOT_DOT);
		$table->setHeader(array(
			($hostid>0)?null:S_HOST,
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$options = array(
				'nodeids' => $nodeid,
				'hostids' => $hostid,
				'webitems' => 1,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'sortfield'=>'description'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$items = CItem::get($options);

		foreach($items as $tnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row["description"] = item_description($row);

			$description = new CSpan($row["description"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$description->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow(array(
				($hostid>0)?null:$row['host'],
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->show();
	}
	else if($srctbl == 'applications'){
		$table = new CTableInfo(S_NO_APPLICATIONS_DEFINED);
		$table->setHeader(array(
			($hostid>0)?null:S_HOST,
			S_NAME));

		$sql = 'SELECT DISTINCT h.host,a.* '.
				' FROM hosts h,applications a '.
				' WHERE h.hostid=a.hostid '.
					' AND '.DBin_node('a.applicationid', $nodeid).
					' AND '.DBcondition('h.hostid',$available_hosts).
					// ' AND h.status in ('.implode(',', $host_status).')'.
					' AND h.hostid='.$hostid.
				' ORDER BY h.host,a.name';

		$result = DBselect($sql);
		while($row = DBfetch($result)){
			$name = new CSpan($row["name"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow(array(($hostid>0)?null:$row['host'], $name));
		}
		$table->Show();
	}
	else if($srctbl == "nodes"){
		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT * FROM nodes WHERE '.DBcondition('nodeid',$available_nodes));
		while($row = DBfetch($result)){
			$name = new CSpan($row["name"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->Show();
	}
	else if($srctbl == "graphs"){

		$table = new CTableInfo(S_NO_GRAPHS_DEFINED);
		$table->setHeader(array(
			S_NAME,
			S_GRAPH_TYPE
		));

		$options = array(
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => $nodeid,
			'select_hosts' => API_OUTPUT_EXTEND
		);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$graphs = CGraph::get($options);
		order_result($graphs, 'name');

		foreach($graphs as $gnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['node_name'] = get_node_name_by_elid($row['graphid'], null, ': ');
			$name = $row['node_name'].$row['host'].':'.$row['name'];

			$description = new CLink($row['name'],'#');
			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
					get_window_opener($dstfrm, $dstfld2, $name);
			}

			$description->setAttribute('onclick',$action." close_window(); return false;");

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
		$table->Show();
	}
	else if($srctbl == "simple_graph"){

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->setHeader(array(
			($hostid>0)?null:S_HOST,
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$options = array(
				'nodeids' => $nodeid,
				'hostids' => $hostid,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'templated' => 0,
				'filter' => array(
					'value_type' => array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64),
					'status' => ITEM_STATUS_ACTIVE
				),
				'sortfield'=>'description'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$items = CItem::get($options);

		foreach($items as $tnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['description'] = item_description($row);
			$description = new CLink($row['description'],'#');

			$row['description'] = $row['host'].':'.$row['description'];

			if(isset($_REQUEST['reference'])){
				if($_REQUEST['reference'] =='dashboard'){
					$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
						get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
						"window.opener.setTimeout('add2favorites();', 1000);";
				}
				else if($_REQUEST['reference'] =='item_list'){
					$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
						get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
						"window.opener.setTimeout('add2favorites();', 1000);";
				}
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
			}

			$description->setAttribute('onclick',$action.' close_window(); return false;');

			$table->addRow(array(
				($hostid>0)?null:$row['host'],
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->show();
	}
	else if($srctbl == 'sysmaps'){
		$table = new CTableInfo(S_NO_MAPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$excludeids = get_request('excludeids', array());
		$excludeids = zbx_toHash($excludeids);

		$options = array(
			'nodeids' => $nodeid,
			'extendoutput' => 1
		);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$maps = CMap::get($options);
		order_result($maps, 'name');

		foreach($maps as $mnum => $row){
			if(isset($excludeids[$row['sysmapid']])) continue;

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$name = $row['node_name'].$row['name'];

			$description = new CLink($row['name'],'#');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
					get_window_opener($dstfrm, $dstfld2, $name);
			}

			$description->setAttribute('onclick',$action.' close_window(); return false;');

			$table->addRow($description);

			unset($description);
		}
		$table->show();
	}
	else if($srctbl == 'plain_text'){

		$table = new CTableInfo(S_NO_ITEMS_DEFINED);
		$table->SetHeader(array(
			($hostid>0)?null:S_HOST,
			S_DESCRIPTION,
			S_TYPE,
			S_TYPE_OF_INFORMATION,
			S_STATUS
			));

		$options = array(
				'nodeids' => $nodeid,
				'hostids'=> $hostid,
				'output' => API_OUTPUT_EXTEND,
				'select_hosts' => API_OUTPUT_EXTEND,
				'templated' => 0,
				'filter' => array(
					'status' => ITEM_STATUS_ACTIVE
				),
				'sortfield'=>'description'
			);
		if(!is_null($writeonly)) $options['editable'] = 1;
		if(!is_null($templated)) $options['templated'] = $templated;

		$items = CItem::get($options);

		foreach($items as $tnum => $row){
			$host = reset($row['hosts']);
			$row['host'] = $host['host'];

			$row['description'] = item_description($row);
			$description = new CSpan($row['description'],'link');
			$row['description'] = $row['host'].':'.$row['description'];

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srctbl).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]);
			}

			$description->setAttribute('onclick',$action.' close_window(); return false;');

			$table->addRow(array(
				($hostid>0)?null:$row['host'],
				$description,
				item_type2str($row['type']),
				item_value_type2str($row['value_type']),
				new CSpan(item_status2str($row['status']),item_status2style($row['status']))
				));
		}
		$table->Show();
	}
	else if('slides' == $srctbl){
		require_once 'include/screens.inc.php';

		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->setHeader(S_NAME);

		$result = DBselect('select slideshowid,name from slideshows where '.DBin_node('slideshowid',$nodeid).' ORDER BY name');
		while($row=DBfetch($result)){
			if(!slideshow_accessible($row['slideshowid'], PERM_READ_ONLY))
				continue;

			$name = new CLink($row['name'],'#');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->Show();
	}
	else if($srctbl == 'screens'){
		require_once('include/screens.inc.php');

		$table = new CTableInfo(S_NO_NODES_DEFINED);
		$table->setHeader(S_NAME);

		$options = array(
			'nodeids' => $nodeid,
			'output' => API_OUTPUT_EXTEND
		);

		$screens = CScreen::get($options);
		order_result($screens, 'name');

		foreach($screens as $snum => $row){
			$name = new CSpan($row["name"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->Show();
	}
	else if($srctbl == 'screens2'){
		require_once('include/screens.inc.php');

		$table = new CTableInfo(S_NO_NODES_DEFINED);
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

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->Show();
	}
	else if($srctbl == 'overview'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(S_NAME);

		$options = array(
				'nodeids' => $nodeid,
				'monitored_hosts' => 1,
				'extendoutput' => 1
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		foreach($hostgroups as $gnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid']);
			$name = new CSpan($row['name'],'link');

			$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
			$row['name'] = $row['node_name'].$row['name'];

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}

		$table->Show();
	}
	else if($srctbl == 'host_group_scr'){
		$table = new CTableInfo(S_NO_GROUPS_DEFINED);
		$table->setHeader(array(S_NAME));

		$options = array(
				'nodeids' => $nodeid,
				'extendoutput' => 1
			);
		if(!is_null($writeonly)) $options['editable'] = 1;

		$hostgroups = CHostGroup::get($options);
		order_result($hostgroups, 'name');

		$all = false;
		foreach($hostgroups as $hgnum => $row){
			$row['node_name'] = get_node_name_by_elid($row['groupid']);

			if(!$all){
				$name = new CLink(bold(S_MINUS_ALL_GROUPS_MINUS),'#');

				if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
					$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
						get_window_opener($dstfrm, $dstfld2, create_id_by_nodeid(0,$nodeid)).
						"window.opener.setTimeout('add2favorites();', 1000);";
				}
				else{
					$action = get_window_opener($dstfrm, $dstfld1, create_id_by_nodeid(0,$nodeid)).
					get_window_opener($dstfrm, $dstfld2, $row['node_name'].S_MINUS_ALL_GROUPS_MINUS);

				}

				$name->setAttribute('onclick',$action." close_window(); return false;");

				$table->addRow($name);
				$all = true;
			}

			$name = new CLink($row['name'],'#');
			$row['name'] = $row['node_name'].$row['name'];

			$name->setAttribute('onclick',
				get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
				((isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard'))?"window.opener.setTimeout('add2favorites();', 1000);":'').
				' return close_window();');

			$table->addRow($name);
		}
		$table->show();
	}
	else if($srctbl == "drules"){
		$table = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT * FROM drules WHERE '.DBin_node('druleid', $nodeid));
		while($row = DBfetch($result)){
			$name = new CSpan($row["name"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->Show();
	}
	else if($srctbl == "dchecks"){
		$table = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT r.name,c.dcheckid,c.type,c.key_,c.snmp_community,c.ports FROM drules r,dchecks c'.
				' WHERE r.druleid=c.druleid and '.DBin_node('r.druleid', $nodeid));
		while($row = DBfetch($result)){
			$row['name'] = $row['name'].':'.discovery_check2str($row['type'],
					$row['snmp_community'], $row['key_'], $row['ports']);
			$name = new CSpan($row["name"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->Show();
	}
	else if($srctbl == "proxies"){
		$table = new CTableInfo(S_NO_DISCOVERY_RULES_DEFINED);
		$table->SetHeader(S_NAME);

		$result = DBselect('SELECT DISTINCT hostid,host '.
				' FROM hosts'.
				' WHERE '.DBin_node('hostid', $nodeid).
					' AND status='.HOST_STATUS_PROXY.
				' ORDER BY host,hostid');
		while($row = DBfetch($result)){
			$name = new CSpan($row["host"],'link');

			if(isset($_REQUEST['reference']) && ($_REQUEST['reference'] =='dashboard')){
				$action = get_window_opener($dstfrm, $dstfld1, $srcfld2).
					get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]).
					"window.opener.setTimeout('add2favorites();', 1000);";
			}
			else{
				$action = get_window_opener($dstfrm, $dstfld1, $row[$srcfld1]).
				(isset($srcfld2) ? get_window_opener($dstfrm, $dstfld2, $row[$srcfld2]) : '');
			}

			$name->setAttribute('onclick',$action." close_window(); return false;");

			$table->addRow($name);
		}
		$table->Show();
	}
?>
<script language="JavaScript" type="text/javascript">
<!--
function add_trigger(formname, triggerid) {
	var parent_document = window.opener.document;

	if(!parent_document) return close_window();

	add_variable('input', 'new_dependence['+triggerid+']', triggerid, formname, parent_document);
	add_variable('input', 'add_dependence', 1, formname, parent_document);

	parent_document.forms[formname].submit();
	close_window();
}
-->
</script>
<?php

include_once('include/page_footer.php');

?>
