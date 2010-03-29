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
require_once('include/maintenances.inc.php');
require_once('include/forms.inc.php');

$page['title'] = 'S_HOSTS';
$page['file'] = 'hosts.php';
$page['hist_arg'] = array('groupid','hostid');

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
//ARRAYS
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
// host
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			null),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			'isset({form})&&({form}=="update")'),
		'host'=>			array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,		'isset({save})'),
		'proxy_hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			'isset({save})'),
		'dns'=>				array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({save})'),
		'useip'=>			array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),		'isset({save})'),
		'ip'=>				array(T_ZBX_IP,  O_OPT, NULL,	NULL,			'isset({save})'),
		'port'=>			array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({save})'),
		'status'=>			array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,3'),		'isset({save})'),

		'newgroup'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'templates'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	NULL),
		'templates_rem'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'clear_templates'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

		'useipmi'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,				NULL),
		'ipmi_ip'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})'),
		'ipmi_port'=>		array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({useipmi})'),
		'ipmi_authtype'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-1,6),		'isset({useipmi})'),
		'ipmi_privilege'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(1,5),		'isset({useipmi})'),
		'ipmi_username'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})'),
		'ipmi_password'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})'),

		'useprofile'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'devicetype'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'name'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'os'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'serialno'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'tag'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'macaddress'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'hardware'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'software'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'contact'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'location'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'notes'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		'host_profile'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,   NULL,   NULL),

		'useprofile_ext'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'ext_host_profiles'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,   NULL,   NULL),

		'macros_rem'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'macros'=>				array(T_ZBX_STR, O_OPT, P_SYS,   NULL,	NULL),
		'macro_new'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	'isset({macro_add})'),
		'value_new'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	'isset({macro_add})'),
		'macro_add' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'macros_del' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
// mass update
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'visible'=>			array(T_ZBX_STR, O_OPT,	null, 	null,	null),
// actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'add_to_group'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),
		'delete_from_group'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),
		'unlink'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'unlink_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'masssave'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'full_clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,			NULL,	NULL),
// other
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

// OUTER DATA
	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');


// PERMISSIONS
	if(get_request('groupid', 0) > 0){
		$groupids = available_groups($_REQUEST['groupid'], 1);
		if(empty($groupids)) access_deny();
	}

	if(get_request('hostid', 0) > 0){
		$hostids = available_hosts($_REQUEST['hostid'], 1);
		if(empty($hostids)) access_deny();
	}
?>
<?php

/************ ACTIONS FOR HOSTS ****************/
// REMOVE MACROS
	if(isset($_REQUEST['macros_del']) && isset($_REQUEST['macros_rem'])){
		$macros_rem = get_request('macros_rem', array());
		foreach($macros_rem as $macro)
			unset($_REQUEST['macros'][$macro]);
	}
// ADD MACRO
	if(isset($_REQUEST['macro_add'])){
		$macro_new = get_request('macro_new');
		$value_new = get_request('value_new', null);

		$currentmacros = array_keys(get_request('macros', array()));

		if(!CUserMacro::validate(zbx_toObject($macro_new, 'macro'))){
			error(S_WRONG_MACRO.' : '.$macro_new);
			show_messages(false, '', S_CANNOT_ADD_MACRO);
		}
		else if(zbx_empty($value_new)){
			error(S_EMPTY_MACRO_VALUE);
			show_messages(false, '', S_CANNOT_ADD_MACRO);
		}
		else if(str_in_array($macro_new, $currentmacros)){
			error(S_MACRO_EXISTS.' : '.$macro_new);
			show_messages(false, '', S_CANNOT_ADD_MACRO);
		}
		else if(zbx_strlen($macro_new) > 64){
			error(S_MACRO_TOO_LONG.' : '.$macro_new);
			show_messages(false, '', S_CANNOT_ADD_MACRO);
		}
		else if(zbx_strlen($value_new) > 255){
			error(S_MACRO_VALUE_TOO_LONG.' : '.$value_new);
			show_messages(false, '', S_CANNOT_ADD_MACRO);
		}
		else{
			$_REQUEST['macros'][$macro_new]['macro'] = $macro_new;
			$_REQUEST['macros'][$macro_new]['value'] = $value_new;
			unset($_REQUEST['macro_new']);
			unset($_REQUEST['value_new']);
		}
	}
// UNLINK HOST
	if(isset($_REQUEST['templates_rem']) && (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear']))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		$unlink_templates = array_keys($_REQUEST['templates_rem']);
		if(isset($_REQUEST['unlink_and_clear'])){
			$_REQUEST['clear_templates'] = zbx_array_merge($_REQUEST['clear_templates'], $unlink_templates);
		}

		foreach($unlink_templates as $id)
			unset($_REQUEST['templates'][$id]);
	}
// CLONE HOST
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
// FULL CLONE HOST
	else if(isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])){
		$_REQUEST['form'] = 'full_clone';
	}
// HOST MASS UPDATE
	else if(isset($_REQUEST['go']) && ($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['masssave'])){
		$hosts = get_request('hosts', array());
		$visible = get_request('visible', array());
		$_REQUEST['groups'] = get_request('groups', array());
		$_REQUEST['newgroup'] = get_request('newgroup', '');
		$_REQUEST['proxy_hostid'] = get_request('proxy_hostid', 0);
		$_REQUEST['templates'] = get_request('templates', array());

		if(count($_REQUEST['groups']) > 0){
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY);
			foreach($_REQUEST['groups'] as $gid){
				if(!isset($accessible_groups[$gid])) access_deny();
			}
		}

		try{
			DBstart();

			$hosts = array('hosts' => zbx_toObject($hosts, 'hostid'));

			$properties = array('port', 'useip', 'dns',	'ip', 'proxy_hostid', 'useipmi', 'ipmi_ip', 'ipmi_port', 'ipmi_authtype',
				'ipmi_privilege', 'ipmi_username', 'ipmi_password', 'status');
			$new_values = array();
			foreach($properties as $property){
				if(isset($visible[$property])){
					if($property == 'useipmi')
						$new_values[$property] = isset($_REQUEST['useipmi']) ? 1 : 0;
					else
						$new_values[$property] = $_REQUEST[$property];
				}
			}
				
// PROFILES {{{
			if(isset($visible['useprofile'])){
				$host_profile = get_request('host_profile', array());
				if(get_request('useprofile', false) && !empty($host_profile)){
					$new_values['profile'] = $host_profile;
				}
				else{
					$new_values['profile'] = array();
				}
			}
			
			if(isset($visible['useprofile_ext'])){
				$ext_host_profiles = get_request('ext_host_profiles', array());
				if(get_request('useprofile_ext', false) && !empty($ext_host_profiles)){
					$new_values['extendedProfile'] = $ext_host_profiles;
				}
				else{
					$new_values['extendedProfile'] = array();
				}
			}
// }}} PROFILES

			$templates = array();
			if(isset($visible['template_table']) || isset($visible['template_table_r'])){
				$tplids = array_keys($_REQUEST['templates']);
				$templates = zbx_toObject($tplids, 'templateid');
			}
		
			if(isset($visible['groups'])){
				$hosts['groups'] = zbx_toObject($_REQUEST['groups'], 'groupid');
			}
			if(isset($visible['template_table_r'])){
				$hosts['templates'] = $templates;
			}
			$result = CHost::massUpdate(array_merge($hosts, $new_values));
			if($result === false) throw new Exception();

			
			$groups = array();
			if(isset($visible['newgroup']) && !empty($_REQUEST['newgroup'])){
				$result = CHostGroup::create(array('name' => $_REQUEST['newgroup']));
				$options = array(
					'groupids' => $result['groupids'],
					'output' => API_OUTPUT_EXTEND
				);
				$groups = CHostGroup::get($options);
				if($groups === false) throw new Exception();
			}

			
			
			$add = array();
			if(!empty($templates) && isset($visible['template_table'])){
				$add['templates'] = $templates;
			}
			if(!empty($groups))
				$add['groups'] = $groups;
			if(!empty($add)){
				$add['hosts'] = $hosts['hosts'];
				
				$result = CHost::massAdd($add);
				if($result === false) throw new Exception();
			}

			DBend(true);

			show_messages(true, S_HOSTS.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HOSTS);

			unset($_REQUEST['massupdate']);
			unset($_REQUEST['form']);
			unset($_REQUEST['hosts']);

			$url = new CUrl();
			$path = $url->getPath();
			insert_js('cookie.eraseArray("'.$path.'")');
		}
		catch(Exception $e){
			DBend(false);
			show_messages(false, S_HOSTS.SPACE.S_UPDATED, S_CANNOT_UPDATE.SPACE.S_HOSTS);
		}

		unset($_REQUEST['save']);
	}
// SAVE HOST
	else if(isset($_REQUEST['save'])){
		$useipmi = isset($_REQUEST['useipmi']) ? 1 : 0;
		$templates = get_request('templates', array());
		$templates_clear = get_request('clear_templates', array());
		$proxy_hostid = get_request('proxy_hostid', 0);
		$groups = get_request('groups', array());

		$result = true;

		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();


		$clone_hostid = false;
		if($_REQUEST['form'] == 'full_clone'){
			$clone_hostid = $_REQUEST['hostid'];
			unset($_REQUEST['hostid']);
		}

		$templates = array_keys($templates);
		$templates = zbx_toObject($templates, 'templateid');
		$templates_clear = zbx_toObject($templates_clear, 'templateid');

// START SAVE TRANSACTION {{{
		DBstart();

		if(isset($_REQUEST['hostid'])){
			$msg_ok = S_HOST_UPDATED;
			$msg_fail = S_CANNOT_UPDATE_HOST;
		}
		else{
			$msg_ok = S_HOST_ADDED;
			$msg_fail = S_CANNOT_ADD_HOST;
		}

		$groups = zbx_toObject($groups, 'groupid');
		if(!empty($_REQUEST['newgroup'])){
			$result = CHostGroup::create(array('name' => $_REQUEST['newgroup']));
			if($result){
				$options = array(
					'groupids' => $result['groupids'],
					'output' => API_OUTPUT_EXTEND
				);
				$newgroup = CHostGroup::get($options);
				$groups = array_merge($groups, $newgroup);
			}
			else{
				$result = false;
			}
		}

		if($result){
			$host = array(
				'host' => $_REQUEST['host'],
				'port' => $_REQUEST['port'],
				'status' => $_REQUEST['status'],
				'useip' => $_REQUEST['useip'],
				'dns' => $_REQUEST['dns'],
				'ip' => $_REQUEST['ip'],
				'proxy_hostid' => $proxy_hostid,
				'templates' => $templates,
				'useipmi' => $useipmi,
				'ipmi_ip' => $_REQUEST['ipmi_ip'],
				'ipmi_port' => $_REQUEST['ipmi_port'],
				'ipmi_authtype' => $_REQUEST['ipmi_authtype'],
				'ipmi_privilege' => $_REQUEST['ipmi_privilege'],
				'ipmi_username' => $_REQUEST['ipmi_username'],
				'ipmi_password' => $_REQUEST['ipmi_password'],
				'groups' => $groups,
				'templates' => $templates,
				'macros' => get_request('macros', array()),
			);

			if(isset($_REQUEST['hostid'])){
				$host['hostid'] = $_REQUEST['hostid'];
				$host['templates_clear'] = $templates_clear;
				$result = CHost::update($host);

				$hostid = $_REQUEST['hostid'];
			}
			else{
				$host = CHost::create($host);

				$result &= (bool) $host;
				if($result){
					$hostid = reset($host['hostids']);
				}
			}

		}

// FULL CLONE {{{
		if($result && $clone_hostid && ($_REQUEST['form'] == 'full_clone')){
// Host applications
			$sql = 'SELECT * FROM applications WHERE hostid='.$clone_hostid.' AND templateid=0';
			$res = DBselect($sql);
			while($db_app = DBfetch($res)){
				add_application($db_app['name'], $hostid, 0);
			}

// Host items
			$sql = 'SELECT DISTINCT i.itemid, i.description '.
					' FROM items i '.
					' WHERE i.hostid='.$clone_hostid.
						' AND i.templateid=0 '.
					' ORDER BY i.description';

			$res = DBselect($sql);
			while($db_item = DBfetch($res)){
				$result &= (bool) copy_item_to_host($db_item['itemid'], $hostid, true);
			}

// Host triggers
			$result &= copy_triggers($clone_hostid, $hostid);
			
			// $triggers = CTrigger::get(array('hostids' => $clone_hostid, 'inherited' => 0));
			// $triggers = zbx_objectValues($triggers, 'triggerid');
			// foreach($triggers as $trigger){
				// $result &= (bool) copy_trigger_to_host($trigger, $hostid, true);
			// }

// Host graphs
			$graphs = CGraph::get(array('hostids' => $clone_hostid, 'inherited' => 0));

			foreach($graphs as $graph){
				$result &= (bool) copy_graph_to_host($graph['graphid'], $hostid, true);
			}

			$_REQUEST['hostid'] = $clone_hostid;
		}

// }}} FULL CLONE

//HOSTS PROFILE Section
		if($result){
			CProfile::update('HOST_PORT', $_REQUEST['port'], PROFILE_TYPE_INT);

			if(isset($_REQUEST['hostid'])){
				delete_host_profile($hostid);
			}

			if(get_request('useprofile', 'no') == 'yes'){
				$result = add_host_profile($hostid,
					$_REQUEST['devicetype'],$_REQUEST['name'],$_REQUEST['os'],
					$_REQUEST['serialno'],$_REQUEST['tag'],$_REQUEST['macaddress'],
					$_REQUEST['hardware'],$_REQUEST['software'],$_REQUEST['contact'],
					$_REQUEST['location'],$_REQUEST['notes']);
			}
		}

//HOSTS PROFILE EXTANDED Section
		if($result){
			if(isset($_REQUEST['hostid'])){
				delete_host_profile_ext($hostid);
			}

			$ext_host_profiles = get_request('ext_host_profiles', array());
			if((get_request('useprofile_ext', 'no') == 'yes') && !empty($ext_host_profiles)){
				$result = add_host_profile_ext($hostid, $ext_host_profiles);
			}
		}

// }}} SAVE TRANSACTION
		$result	= DBend($result);

		show_messages($result, $msg_ok, $msg_fail);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}

		unset($_REQUEST['save']);
	}
// DELETE HOST
	else if(isset($_REQUEST['delete']) && isset($_REQUEST['hostid'])){

		DBstart();
			$result = delete_host($_REQUEST['hostid']);
		$result = DBend($result);

		show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		unset($_REQUEST['delete']);
	}
	else if(isset($_REQUEST['chstatus']) && isset($_REQUEST['hostid'])){

		DBstart();
			$result = update_host_status($_REQUEST['hostid'], $_REQUEST['chstatus']);
		$result = DBend($result);

		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);

		unset($_REQUEST['chstatus']);
		unset($_REQUEST['hostid']);
	}

// -------- GO ---------------
// DELETE HOST
	else if($_REQUEST['go'] == 'delete'){
		$hostids = get_request('hosts', array());
		$hosts = zbx_toObject($hostids,'hostid');

		DBstart();
		$options = array(
			'hostids' => $hostids,
			'output' => array('hostid', 'host')
		);
		$delHosts = CHost::get($options);

		$go_result = CHost::delete($hosts);
		foreach($delHosts as $hnum => $host){
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, 'Host ['.$host['host'].']');
		}

		$go_result = DBend($go_result);

		if(!$go_result){
			error(CHost::resetErrors());
		}

		show_messages($go_result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
	}
// ACTIVATE/DISABLE HOSTS
	else if(str_in_array($_REQUEST['go'], array('activate', 'disable'))){

		$status = ($_REQUEST['go'] == 'activate') ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request('hosts', array());

		$act_hosts = available_hosts($hosts, 1);

		DBstart();
		$go_result = update_host_status($act_hosts, $status);
		$go_result = DBend($go_result);

		show_messages($go_result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}
?>
<?php

	$frmForm = new CForm();
	$cmbConf = new CComboBox('config', 'hosts.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('templates.php', S_TEMPLATES);
		$cmbConf->addItem('hosts.php', S_HOSTS);
		$cmbConf->addItem('items.php', S_ITEMS);
		$cmbConf->addItem('triggers.php', S_TRIGGERS);
		$cmbConf->addItem('graphs.php', S_GRAPHS);
		$cmbConf->addItem('applications.php', S_APPLICATIONS);
	$frmForm->addItem($cmbConf);

	if(!isset($_REQUEST['form'])){
		$frmForm->addItem(new CButton('form',S_CREATE_HOST));
	}

	$hosts_wdgt = new CWidget();
	$hosts_wdgt->addPageHeader(S_CONFIGURATION_OF_HOSTS, $frmForm);
	

// TODO: neponjatno pochemu hostid sbrasivaetsja no on nuzhen dlja formi
$thid = get_request('hostid', 0);

	$params=array();
	$options = array('only_current_node');
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group($PAGE_GROUPS,$PAGE_HOSTS);

	$_REQUEST['hostid'] = $thid;
?>
<?php
	// echo SBR;

	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['hosts'])){
		$hosts_wdgt->addItem(insert_mass_update_host_form());
	}
	else if(isset($_REQUEST['form'])){
		$hosts_wdgt->addItem(insert_host_form(false));
	}
	else{
		

		$frmForm = new CForm();
		$frmForm->setMethod('get');

		$cmbGroups = new CComboBox('groupid', $PAGE_GROUPS['selected'], 'javascript: submit();');
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, $name);
		}
		$frmForm->addItem(array(S_GROUP.SPACE, $cmbGroups));

		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$hosts_wdgt->addHeader(S_HOSTS_BIG, $frmForm);
		$hosts_wdgt->addHeader($numrows);

// table HOSTS
		$form = new CForm();
		$form->setName('hosts');

		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_hosts', NULL, "checkAll('" . $form->getName() . "','all_hosts','hosts');"),
			make_sorting_header(S_NAME, 'host'),
			S_APPLICATIONS,
			S_ITEMS,
			S_TRIGGERS,
			S_GRAPHS,
			make_sorting_header(S_DNS, 'dns'),
			make_sorting_header(S_IP, 'ip'),
			S_PORT,
			S_TEMPLATES,
			make_sorting_header(S_STATUS, 'status'),
			S_AVAILABILITY
		));


		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);

		if(($PAGE_GROUPS['selected'] > 0) || empty($PAGE_GROUPS['groupids'])){
			$options['groupids'] = $PAGE_GROUPS['selected'];
		}

		$hosts = CHost::get($options);

// sorting && paging
		order_result($hosts, $sortfield, $sortorder);
		$paging = getPagingLine($hosts);
//---------

		$options = array(
			'hostids' => zbx_objectValues($hosts, 'hostid'),
			'output' => API_OUTPUT_EXTEND,
			'select_templates' => array('hostid','host'),
			'select_items' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
			'select_applications' => API_OUTPUT_COUNT,
			'nopermissions' => 1
		);
		$hosts = CHost::get($options);

// sorting && paging
		order_result($hosts, $sortfield, $sortorder);
//---------

// Selecting linked templates to templates linked to hosts
		$templateids = array();
		foreach($hosts as $num => $host){
			$templateids = array_merge($templateids, zbx_objectValues($host['templates'], 'templateid'));
		}
		$templateids = array_unique($templateids);

		$options = array(
			'templateids' => $templateids,
			'select_templates' => array('hostid', 'host')
		);

		$templates = CTemplate::get($options);
		$templates = zbx_toHash($templates, 'templateid');
//---------
		foreach($hosts as $num => $host){
			$applications = array(new CLink(S_APPLICATIONS, 'applications.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$host['hostid']),
				' ('.$host['applications'].')');
			$items = array(new CLink(S_ITEMS, 'items.php?filter_set=1&hostid='.$host['hostid']),
				' ('.$host['items'].')');
			$triggers = array(new CLink(S_TRIGGERS, 'triggers.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$host['hostid']),
				' ('.$host['triggers'].')');
			$graphs = array(new CLink(S_GRAPHS, 'graphs.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$host['hostid']),
				' ('.$host['graphs'].')');

			$description = array();
			if($host['proxy_hostid']){
				$proxy = CProxy::get(array('proxyids' => $host['proxy_hostid'], 'extendoutput' => 1));
				$proxy = reset($proxy);
				$description[] = $proxy['host'] . ':';
			}
			$description[] = new CLink($host['host'], 'hosts.php?form=update&hostid='.$host['hostid'].url_param('groupid'));

			$dns = empty($host['dns']) ? '-' : $host['dns'];
			$ip = empty($host['ip']) ? '-' : $host['ip'];
			$use = (1 == $host['useip']) ? 'ip' : 'dns';
			$$use = bold($$use);

			switch($host['status']){
				case HOST_STATUS_MONITORED:
					$status = new CLink(S_MONITORED, 'hosts.php?hosts%5B%5D='.$host['hostid'].'&go=disable'.url_param('groupid'), 'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status = new CLink(S_NOT_MONITORED, 'hosts.php?hosts%5B%5D='.$host['hostid'].'&go=activate'.url_param('groupid'), 'on');
					break;
				default:
					$status = S_UNKNOWN;
			}

			switch($host['available']){
				case HOST_AVAILABLE_TRUE:
					$zbx_available = new CDiv(SPACE, 'iconzbxavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$zbx_available = new CDiv(SPACE, 'iconzbxunavailable');
					$zbx_available->setHint($host['error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$zbx_available = new CDiv(SPACE, 'iconzbxunknown');
					break;
			}

			switch($host['snmp_available']){
				case HOST_AVAILABLE_TRUE:
					$snmp_available = new CDiv(SPACE, 'iconsnmpavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$snmp_available = new CDiv(SPACE, 'iconsnmpunavailable');
					$snmp_available->setHint($host['snmp_error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$snmp_available = new CDiv(SPACE, 'iconsnmpunknown');
					break;
			}

			switch($host['ipmi_available']){
				case HOST_AVAILABLE_TRUE:
					$ipmi_available = new CDiv(SPACE, 'iconipmiavailable');
					break;
				case HOST_AVAILABLE_FALSE:
					$ipmi_available = new CDiv(SPACE, 'iconipmiunavailable');
					$ipmi_available->setHint($host['ipmi_error'], '', 'on');
					break;
				case HOST_AVAILABLE_UNKNOWN:
					$ipmi_available = new CDiv(SPACE, 'iconipmiunknown');
					break;
			}

			$av_table = new CTable(null, 'invisible');
			$av_table->addRow(array($zbx_available, $snmp_available, $ipmi_available));

			if(empty($host['templates'])){
				$hostTemplates = '-';
			}
			else{
				$hostTemplates = array();
				foreach($host['templates'] as $htnum => $template){
					$caption = array();
					$caption[] = new CLink($template['host'],'templates.php?form=update&templateid='.$template['hostid'],'unknown');

					if(!empty($templates[$template['templateid']]['templates'])){
						$caption[] = ' (';
						foreach($templates[$template['templateid']]['templates'] as $tnum => $tpl){
							$caption[] = new CLink($tpl['host'],'templates.php?form=update&templateid='.$tpl['hostid'], 'unknown');
							$caption[] = ', ';
						}
						array_pop($caption);

						$caption[] = ')';
					}

					$hostTemplates[] = $caption;
					$hostTemplates[] = ', ';
				}

				if(!empty($hostTemplates)) array_pop($hostTemplates);
			}

			$table->addRow(array(
				new CCheckBox('hosts['.$host['hostid'].']',NULL,NULL,$host['hostid']),
				$description,
				$applications,
				$items,
				$triggers,
				$graphs,
				$dns,
				$ip,
				empty($host['port']) ? '-' : $host['port'],
				new CCol($hostTemplates, 'wraptext'),
				$status,
				$av_table
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('massupdate',S_MASS_UPDATE);

		$goOption = new CComboItem('activate',S_ACTIVATE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE_SELECTED_HOSTS);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE_SELECTED_HOSTS_Q);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE_SELECTED_HOSTS);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO);
		$goButton->setAttribute('id', 'goButton');

		$jsLocale = array(
			'S_CLOSE',
			'S_NO_ELEMENTS_SELECTED'
		);

		zbx_addJSLocale($jsLocale);

		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

		$footer = get_table_header(array($goBox, $goButton));
//----

// PAGING FOOTER
		$table = array($paging, $table, $paging, $footer);
//---------
		$form->addItem($table);
		$hosts_wdgt->addItem($form);
	}
	
	$hosts_wdgt->show();

include_once('include/page_footer.php');
?>
