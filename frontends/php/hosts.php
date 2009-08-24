<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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

	$page['title'] = "S_HOSTS";
	$page['file'] = 'hosts.php';
	$page['hist_arg'] = array('groupid','config','hostid');

include_once('include/page_header.php');

	$_REQUEST['config'] = get_request('config','hosts.php');

	$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE);

	if(isset($_REQUEST['groupid']) && ($_REQUEST['groupid']>0) && !isset($available_groups[$_REQUEST['groupid']])){
		access_deny();
	}
	if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0) && !isset($available_hosts[$_REQUEST['hostid']])) {
		access_deny();
	}
	if(isset($_REQUEST['apphostid']) && ($_REQUEST['apphostid']>0) && !isset($available_hosts[$_REQUEST['apphostid']])) {
		access_deny();
	}

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
//  NEW  templates.php; hosts.php; items.php; triggers.php; graphs.php; maintenances.php;
// 	OLD  0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications; 5 - Proxies; 6 - maintenance
		'config'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,	NULL),

//ARRAYS
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),

// host
		'groupid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			null),
		'hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,			'isset({form})&&({form}=="update")'),
		'host'=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,		'isset({save})&&!isset({massupdate})'),
		'proxy_hostid'=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,	'isset({save})&&!isset({massupdate})'),
		'dns'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({save})&&!isset({massupdate})'),
		'useip'=>		array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'isset({save})&&!isset({massupdate})'),
		'ip'=>			array(T_ZBX_IP, O_OPT, NULL,	NULL,		'isset({save})&&!isset({massupdate})'),
		'port'=>		array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({save})&&!isset({massupdate})'),
		'status'=>		array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,3'),		'isset({save})&&!isset({massupdate})'),

		'newgroup'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'templates'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	NULL),
		'clear_templates'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

		'useipmi'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,				NULL),
		'ipmi_ip'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_port'=>		array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_authtype'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-1,6),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_privilege'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(1,5),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_username'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_password'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,				'isset({useipmi})&&!isset({massupdate})'),

		'useprofile'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'devicetype'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'name'=>			array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'os'=>				array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'serialno'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'tag'=>				array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'macaddress'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'hardware'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'software'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'contact'=>			array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'location'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'notes'=>			array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),

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
		'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'full_clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,			NULL,	NULL),
		
/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('host',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php

/************ ACTIONS FOR HOSTS ****************/
/* REMOVE MACROS */
	if(isset($_REQUEST['macros_del']) && isset($_REQUEST['macros_rem'])){
		$macros_rem = get_request('macros_rem', array());
		foreach($macros_rem as $macro)
			unset($_REQUEST['macros'][$macro]);
	}
/* ADD MACRO */
	if(isset($_REQUEST['macro_add'])){
		$macro_new = get_request('macro_new');
		$value_new = get_request('value_new', null);
		
		$currentmacros = array_keys(get_request('macros', array()));
		
		if(!CUserMacro::validate($macro_new)){
			error(S_WRONG_MACRO.' : '.$macro_new);
			show_messages(false, '', S_MACROS);
		}
		else if(zbx_empty($value_new)){
			error(S_EMPTY_MACRO_VALUE);
			show_messages(false, '', S_MACROS);
		}
		else if(str_in_array($macro_new, $currentmacros)){
			error(S_MACRO_EXISTS.' : '.$macro_new);
			show_messages(false, '', S_MACROS);
		}
		else{
			$_REQUEST['macros'][$macro_new]['macro'] = $macro_new;
			$_REQUEST['macros'][$macro_new]['value'] = $value_new;
			unset($_REQUEST['macro_new']);
			unset($_REQUEST['value_new']);			
		}
	}
/* UNLINK HOST */
	if((isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear']))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		if(isset($_REQUEST['unlink'])){
			$unlink_templates = array_keys($_REQUEST['unlink']);
		}
		else{
			$unlink_templates = array_keys($_REQUEST['unlink_and_clear']);
			$_REQUEST['clear_templates'] = zbx_array_merge($_REQUEST['clear_templates'],$unlink_templates);
		}
		foreach($unlink_templates as $id)
			unset($_REQUEST['templates'][$id]);
	}
/* CLONE HOST */
	else if(isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
/* FULL CLONE HOST */
	else if(isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])){
		$_REQUEST['form'] = 'full_clone';
	}
/* HOST MASS UPDATE */
	else if($_REQUEST['config']==0 && isset($_REQUEST['massupdate']) && isset($_REQUEST['save'])){
		$hosts = get_request('hosts',array());
		$visible = get_request('visible',array());

		$_REQUEST['groups'] = get_request('groups',array());

		$_REQUEST['newgroup'] = get_request('newgroup','');

		$_REQUEST['proxy_hostid'] = get_request('proxy_hostid',0);
		$_REQUEST['templates'] = get_request('templates', array());

		if(count($_REQUEST['groups']) > 0){
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);
			foreach($_REQUEST['groups'] as $gid){
				if(!isset($accessible_groups[$gid])) access_deny();
			}
		}

		$result = true;

		DBstart();
		foreach($hosts as $id => $hostid){

			$db_host = get_host_by_hostid($hostid);
			$db_templates = get_templates_by_hostid($hostid);

			foreach($db_host as $key => $value){
				if(isset($visible[$key])){
					if ($key == 'useipmi')
						$db_host[$key] = get_request('useipmi', 'no');
					else
						$db_host[$key] = $_REQUEST[$key];
				}
			}

			if(isset($visible['groups'])){
				$db_host['groups'] = $_REQUEST['groups'];
			}
			else{
				$db_host['groups'] = get_groupids_by_host($hostid);
			}

			if(isset($visible['template_table'])){
				foreach($db_templates as $templateid => $name){
					$result &= unlink_template($hostid, $templateid, false);
				}
				$db_host['templates'] = $_REQUEST['templates'];
			}
			else{
				$db_host['templates'] = $db_templates;
			}

			$result &= (bool) update_host($hostid,
				$db_host['host'],$db_host['port'],$db_host['status'],$db_host['useip'],$db_host['dns'],
				$db_host['ip'],$db_host['proxy_hostid'],$db_host['templates'],$db_host['useipmi'],$db_host['ipmi_ip'],
				$db_host['ipmi_port'],$db_host['ipmi_authtype'],$db_host['ipmi_privilege'],$db_host['ipmi_username'],
				$db_host['ipmi_password'],$_REQUEST['newgroup'],$db_host['groups']);


			if($result && isset($visible['useprofile'])){

				$host_profile=DBfetch(DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$hostid));
				$host_profile_fields = array('devicetype', 'name', 'os', 'serialno', 'tag',
					'macaddress', 'hardware', 'software', 'contact', 'location', 'notes');

				delete_host_profile($hostid);

				if(get_request('useprofile','no') == 'yes'){
					foreach($host_profile_fields as $field){
						if(isset($visible[$field]))
							$host_profile[$field] = $_REQUEST[$field];
						elseif(!isset($host_profile[$field]))
							$host_profile[$field] = '';
					}

					$result &= add_host_profile($hostid,
						$host_profile['devicetype'],$host_profile['name'],$host_profile['os'],
						$host_profile['serialno'],$host_profile['tag'],$host_profile['macaddress'],
						$host_profile['hardware'],$host_profile['software'],$host_profile['contact'],
						$host_profile['location'],$host_profile['notes']);
				}
			}

//HOSTS PROFILE EXTANDED Section
			if($result && isset($visible['useprofile_ext'])){

				$host_profile_ext=DBfetch(DBselect('SELECT * FROM hosts_profiles_ext WHERE hostid='.$hostid));
				$host_profile_ext_fields = array('device_alias','device_type','device_chassis','device_os','device_os_short',
					'device_hw_arch','device_serial','device_model','device_tag','device_vendor','device_contract',
					'device_who','device_status','device_app_01','device_app_02','device_app_03','device_app_04',
					'device_app_05','device_url_1','device_url_2','device_url_3','device_networks','device_notes',
					'device_hardware','device_software','ip_subnet_mask','ip_router','ip_macaddress','oob_ip',
					'oob_subnet_mask','oob_router','date_hw_buy','date_hw_install','date_hw_expiry','date_hw_decomm','site_street_1',
					'site_street_2','site_street_3','site_city','site_state','site_country','site_zip','site_rack','site_notes',
					'poc_1_name','poc_1_email','poc_1_phone_1','poc_1_phone_2','poc_1_cell','poc_1_screen','poc_1_notes','poc_2_name',
					'poc_2_email','poc_2_phone_1','poc_2_phone_2','poc_2_cell','poc_2_screen','poc_2_notes');

				delete_host_profile_ext($hostid);
//ext_host_profiles
				$useprofile_ext = get_request('useprofile_ext',false);
				$ext_host_profiles = get_request('ext_host_profiles',array());

				if($useprofile_ext && !empty($ext_host_profiles)){
					$ext_host_profiles = get_request('ext_host_profiles',array());

					foreach($host_profile_ext_fields as $field){
						if(isset($visible[$field])){
							$host_profile_ext[$field] = $ext_host_profiles[$field];
						}
					}

					$result &= add_host_profile_ext($hostid,$host_profile_ext);
				}
			}
		}

		$result = DBend($result);

		$msg_ok 	= S_HOSTS.SPACE.S_UPDATED;
		$msg_fail 	= S_CANNOT_UPDATE.SPACE.S_HOSTS;

		show_messages($result, $msg_ok, $msg_fail);

		if($result){
			unset($_REQUEST['massupdate']);
			unset($_REQUEST['form']);
			unset($_REQUEST['hosts']);
		}

		unset($_REQUEST['save']);
	}
/* SAVE HOST */
	else if(isset($_REQUEST['save'])){
		$useip = get_request('useip',0);
		$groups= get_request('groups',array());
		$useipmi = get_request('useipmi','no');

		if(count($groups) > 0){
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);
			foreach($groups as $gid){
				if(!isset($accessible_groups[$gid])) access_deny();
			}
		}
		else{
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();
		}

		$templates = get_request('templates', array());

		$_REQUEST['proxy_hostid'] = get_request('proxy_hostid',0);

		$clone_hostid = false;
		if($_REQUEST['form'] == 'full_clone'){
			$clone_hostid = $_REQUEST['hostid'];
			unset($_REQUEST['hostid']);
		}

		$result = true;

		DBstart();
		if(isset($_REQUEST['hostid'])){
			if(isset($_REQUEST['clear_templates'])) {
				foreach($_REQUEST['clear_templates'] as $id){
					$result &= unlink_template($_REQUEST['hostid'], $id, false);
				}
			}

			$result = update_host($_REQUEST['hostid'],
				$_REQUEST['host'],$_REQUEST['port'],$_REQUEST['status'],$useip,$_REQUEST['dns'],
				$_REQUEST['ip'],$_REQUEST['proxy_hostid'],$templates,$useipmi,$_REQUEST['ipmi_ip'],
				$_REQUEST['ipmi_port'],$_REQUEST['ipmi_authtype'],$_REQUEST['ipmi_privilege'],$_REQUEST['ipmi_username'],
				$_REQUEST['ipmi_password'],$_REQUEST['newgroup'],$groups);

			$msg_ok 	= S_HOST_UPDATED;
			$msg_fail 	= S_CANNOT_UPDATE_HOST;

			$hostid = $_REQUEST['hostid'];
		}
		else {
			$hostid = $result = add_host(
				$_REQUEST['host'],$_REQUEST['port'],$_REQUEST['status'],$useip,$_REQUEST['dns'],
				$_REQUEST['ip'],$_REQUEST['proxy_hostid'],$templates,$useipmi,$_REQUEST['ipmi_ip'],
				$_REQUEST['ipmi_port'],$_REQUEST['ipmi_authtype'],$_REQUEST['ipmi_privilege'],$_REQUEST['ipmi_username'],
				$_REQUEST['ipmi_password'],$_REQUEST['newgroup'],$groups);

			$msg_ok 	= S_HOST_ADDED;
			$msg_fail 	= S_CANNOT_ADD_HOST;
		}

		if(!zbx_empty($hostid) && $hostid && $clone_hostid && ($_REQUEST['form'] == 'full_clone')){
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
				$result &= copy_item_to_host($db_item['itemid'], $hostid, true);
			}

// Host triggers
			$available_triggers = get_accessible_triggers(PERM_READ_ONLY, array($clone_hostid), PERM_RES_IDS_ARRAY);

			$sql = 'SELECT DISTINCT t.triggerid, t.description '.
					' FROM triggers t, items i, functions f'.
					' WHERE i.hostid='.$clone_hostid.
						' AND f.itemid=i.itemid '.
						' AND t.triggerid=f.triggerid '.
						' AND '.DBcondition('t.triggerid', $available_triggers).
						' AND t.templateid=0 '.
					' ORDER BY t.description';

			$res = DBselect($sql);
			while($db_trig = DBfetch($res)){
				$result &= copy_trigger_to_host($db_trig['triggerid'], $hostid, true);
			}

// Host graphs
			$available_graphs = get_accessible_graphs(PERM_READ_ONLY, array($clone_hostid), PERM_RES_IDS_ARRAY);

			$sql = 'SELECT DISTINCT g.graphid, g.name '.
						' FROM graphs g, graphs_items gi,items i '.
						' WHERE '.DBcondition('g.graphid',$available_graphs).
							' AND gi.graphid=g.graphid '.
							' AND g.templateid=0 '.
							' AND i.itemid=gi.itemid '.
							' AND i.hostid='.$clone_hostid.
						' ORDER BY g.name';

			$res = DBselect($sql);
			while($db_graph = DBfetch($res)){
				$result &= copy_graph_to_host($db_graph['graphid'], $hostid, true);
			}

			$_REQUEST['hostid'] = $clone_hostid;
		}

		$result	= DBend($result);

		if($result){
			update_profile('HOST_PORT',$_REQUEST['port'], PROFILE_TYPE_INT);

			DBstart();
			delete_host_profile($hostid);

			if(get_request('useprofile','no') == 'yes'){
				add_host_profile($hostid,
					$_REQUEST['devicetype'],$_REQUEST['name'],$_REQUEST['os'],
					$_REQUEST['serialno'],$_REQUEST['tag'],$_REQUEST['macaddress'],
					$_REQUEST['hardware'],$_REQUEST['software'],$_REQUEST['contact'],
					$_REQUEST['location'],$_REQUEST['notes']);
			}

			$result	= DBend($result);
		}

//HOSTS PROFILE EXTANDED Section
		if($result){
			update_profile('HOST_PORT',$_REQUEST['port'], PROFILE_TYPE_INT);

			DBstart();
			delete_host_profile_ext($hostid);

			$useprofile_ext = get_request('useprofile_ext','no');
			$ext_host_profiles = get_request('ext_host_profiles',array());

			if(($useprofile_ext == 'yes') && !empty($ext_host_profiles)){
				$result = add_host_profile_ext($hostid, $ext_host_profiles);
			}
			$result = DBend($result);
		}
//-------------

// MACROS {
	if($result){
		$macros = get_request('macros', array());
		
		$macrostoadd = array('hostid' => $hostid, 'macros' => array());
		
		foreach($macros as $macro){
			if(!CUserMacro::validate($macro['macro'])){
				$result = false;
				break;
			}
			$macrostoadd['macros'][] = $macro;
		}

		$result = CUserMacro::update($macrostoadd);
		
		if(!$result) 
			error('S_ERROR_ADDING_MACRO');
	}
// } MACROS

		show_messages($result, $msg_ok, $msg_fail);

		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		unset($_REQUEST['save']);
	}

/* DELETE HOST */
	else if((isset($_REQUEST['delete']) || isset($_REQUEST['delete_and_clear']))){
		$unlink_mode = false;
		if(isset($_REQUEST['delete'])){
			$unlink_mode =  true;
		}

		if(isset($_REQUEST['hostid'])){
			$host=get_host_by_hostid($_REQUEST['hostid']);

			DBstart();
				$result = delete_host($_REQUEST['hostid'], $unlink_mode);
			$result=DBend($result);

			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
			if($result){
				unset($_REQUEST['form']);
				unset($_REQUEST['hostid']);
			}
		}
		unset($_REQUEST['delete']);
	}
	else if(isset($_REQUEST['chstatus']) && isset($_REQUEST['hostid'])){

		$host=get_host_by_hostid($_REQUEST['hostid']);

		DBstart();
			$result = update_host_status($_REQUEST['hostid'],$_REQUEST['chstatus']);
		$result = DBend($result);

		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);

		unset($_REQUEST['chstatus']);
		unset($_REQUEST['hostid']);
	}

// -------- GO ---------------

/* DELETE HOST */
	else if($_REQUEST['go'] == 'delete'){
		$unlink_mode =  true;

		$result = true;
		$hosts = get_request('hosts',array());
		$del_hosts = array();
		$sql = 'SELECT host,hostid '.
				' FROM hosts '.
				' WHERE '.DBin_node('hostid').
					' AND '.DBcondition('hostid',$hosts).
					' AND '.DBcondition('hostid',$available_hosts);
		$db_hosts=DBselect($sql);

		DBstart();
		while($db_host=DBfetch($db_hosts)){
			$del_hosts[$db_host['hostid']] = $db_host['hostid'];
/*				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,'Host ['.$db_host['host'].']');*/
		}

		$result = delete_host($del_hosts, $unlink_mode);
		$result = DBend($result);

		show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
	}
// ACTIVATE/DISABLE HOSTS
	else if(str_in_array($_REQUEST['go'], array('activate','disable'))){

		$result = true;
		$status = ($_REQUEST['go'] == 'activate')?HOST_STATUS_MONITORED:HOST_STATUS_NOT_MONITORED;

		$hosts = get_request('hosts',array());
		$act_hosts = array();
		$sql = 'SELECT host,hostid,status '.
				' FROM hosts '.
				' WHERE '.DBin_node('hostid').
					' AND '.DBcondition('hostid',$hosts).
					' AND '.DBcondition('hostid',$available_hosts);
		$db_hosts=DBselect($sql);

		DBstart();
		while($db_host=DBfetch($db_hosts)){
			$act_hosts[$db_host['hostid']] = $db_host['hostid'];
		}

		$result = update_host_status($act_hosts,$status);
		$result = DBend($result);

		show_messages($result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
	}
?>
<?php
	$params = array();
	$options = array('only_current_node');
	if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');

	foreach($options as $option) $params[$option] = 1;
	$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);

	validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);

	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
?>
<?php

	$frmForm = new CForm();
	$frmForm->setMethod('get');

// Config
	$cmbConf = new CComboBox('config','hosts.php','javascript: submit()');
	$cmbConf->setAttribute('onchange','javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('templates.php',S_TEMPLATES);
		$cmbConf->addItem('hosts.php',S_HOSTS);
		$cmbConf->addItem('items.php',S_ITEMS);
		$cmbConf->addItem('triggers.php',S_TRIGGERS);
		$cmbConf->addItem('graphs.php',S_GRAPHS);
		$cmbConf->addItem('applications.php',S_APPLICATIONS);

	$frmForm->addItem($cmbConf);

	$frmForm->addVar('groupid',get_request('groupid',0));
	if(!isset($_REQUEST['form'])){
//		$frmForm->addItem(SPACE);
		$frmForm->addItem(new CButton('form',S_CREATE_HOST));
	}

	show_table_header(S_CONFIGURATION_OF_HOSTS, $frmForm);
?>
<?php

	echo SBR;

	if(($_REQUEST['go'] == 'massupdate') && isset($_REQUEST['hosts'])){
		insert_mass_update_host_form();
	}
	else if(isset($_REQUEST['form'])){
		insert_host_form(false);
	}
	else{
		$hosts_wdgt = new CWidget();

		$frmForm = new CForm();
		$frmForm->setMethod('get');

		$frmForm->addVar('config',$_REQUEST['config']);

		$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
		}

		$frmForm->addItem(array(S_GROUP.SPACE,$cmbGroups));

		$numrows = new CDiv();
		$numrows->setAttribute('name','numrows');

		$hosts_wdgt->addHeader(S_HOSTS_BIG, $frmForm);
		$hosts_wdgt->addHeader($numrows);

/* table HOSTS */
		$options = array(
					'extendoutput' => 1,
					'select_templates' => 1,
					'select_items' => 1,
					'select_triggers' => 1,
					'select_graphs' => 1,
					'select_applications' => 1,
					'editable' => 1,
//					'sortfield' => getPageSortField('host'),
//					'sortorder' => getPageSortOrder(),
					'limit' => ($config['search_limit']+1)
				);

		if(($PAGE_GROUPS['selected'] > 0) || empty($PAGE_GROUPS['groupids'])){
			$options['groupids'] = $PAGE_GROUPS['selected'];
		}

		$hosts = CHost::get($options);

		$form = new CForm();
		$form->setName('hosts');
		$form->addVar('config',get_request('config',0));

		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_hosts',NULL,"checkAll('".$form->getName()."','all_hosts','hosts');"),
			make_sorting_header(S_NAME,'host'),
			S_APPLICATIONS,
			S_ITEMS,
			S_TRIGGERS,
			S_GRAPHS,
			make_sorting_header(S_DNS,'dns'),
			make_sorting_header(S_IP,'ip'),
			S_PORT,
			S_TEMPLATES,
			make_sorting_header(S_STATUS,'status'),
			S_AVAILABILITY,
			S_ERROR
		));

// sorting && paging
		order_page_result($hosts, getPageSortField('host'), getPageSortOrder());
		$paging = getPagingLine($hosts);
//---------

		foreach($hosts as $hostid => $row){
			$description = array();

			$applications = array(new CLink(S_APPLICATIONS,'applications.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$row['hostid']),
				' ('.count($row['applications']).')');
			$items = array(new CLink(S_ITEMS,'items.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$row['hostid']),
				' ('.count($row['itemids']).')');
			$triggers = array(new CLink(S_TRIGGERS,'triggers.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$row['hostid']),
				' ('.count($row['triggerids']).')');
			$graphs = array(new CLink(S_GRAPHS,'graphs.php?groupid='.$PAGE_GROUPS['selected'].'&hostid='.$row['hostid']),
				' ('.count($row['graphids']).')');

			if($row['proxy_hostid']){
				$proxy = get_host_by_hostid($row['proxy_hostid']);
				array_push($description,$proxy['host'],':');
			}

			array_push($description, new CLink($row['host'], 'hosts.php?form=update&hostid='.$row['hostid'].url_param('groupid')));

			$dns = empty($row['dns'])?'-':$row['dns'];
			$ip = empty($row['ip'])?'-':$row['ip'];
			$port = empty($row['port'])?'-':$row['port'];

			if(1 == $row['useip']){
				$ip = bold($ip);
			}
			else{
				$dns = bold($dns);
			}

			switch($row['status']){
				case HOST_STATUS_MONITORED:
					$status=new CLink(S_MONITORED,'hosts.php?hosts%5B%5D='.$row['hostid'].'&go=disable'.url_param('groupid'),'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status=new CLink(S_NOT_MONITORED,'hosts.php?hosts%5B%5D='.$row['hostid'].'&go=activate'.url_param('groupid'),'on');
					break;
				default:
					$status=S_UNKNOWN;
			}

			if($row['available'] == HOST_AVAILABLE_TRUE)
				$available=new CCol(S_AVAILABLE,'off');
			else if($row['available'] == HOST_AVAILABLE_FALSE)
				$available=new CCol(S_NOT_AVAILABLE,'on');
			else if($row['available'] == HOST_AVAILABLE_UNKNOWN)
				$available=new CCol(S_UNKNOWN,'unknown');

			if(!zbx_empty($row['error'])){
				$error = new CDiv(SPACE,'iconerror');
				$error->setHint($row['error'], '', 'on');
			}
			else{
				$error = new CDiv(SPACE,'iconok');
			}

			$templates = $row['templates'];
			$templates_linked = array();
			foreach($templates as $templateid => $template){
				$templates_linked[$templateid] = array(empty($templates_linked)?'':', ',$template['host']);
			}

			$table->addRow(array(
				new CCheckBox('hosts['.$row['hostid'].']',NULL,NULL,$row['hostid']),
				$description,
				$applications,
				$items,
				$triggers,
				$graphs,
				$dns,
				$ip,
				$port,
				empty($templates)?'-':$templates_linked,
				$status,
				$available,
				$error
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goBox->addItem('massupdate',S_MASS_UPDATE);
		$goBox->addItem('activate',S_ACTIVATE_SELECTED);
		$goBox->addItem('disable',S_DISABLE_SELECTED);
		$goBox->addItem('delete',S_DELETE_SELECTED);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO.' (0)');
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

		$footer = get_table_header(array($goBox, SPACE, $goButton));
//----

// PAGING FOOTER
		$table = array($paging,$table,$paging,$footer);
//---------

		$form->addItem($table);

		$hosts_wdgt->addItem($form);
		$hosts_wdgt->show();
	}

?>
<?php

include_once('include/page_footer.php');

?>