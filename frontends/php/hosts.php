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
	require_once('include/config.inc.php');
	require_once('include/hosts.inc.php');
	require_once('include/maintenances.inc.php');
	require_once('include/forms.inc.php');

	$page['title'] = "S_HOSTS";
	$page['file'] = 'hosts.php';
	$page['hist_arg'] = array('groupid','config','hostid');
	$page['scripts'] = array('menu_scripts.js','calendar.js');
	
include_once('include/page_header.php');

	$_REQUEST['config'] = get_request('config',get_profile('web.hosts.config',0));
	
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
		// 0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications; 5 - Proxies; 6 - maintenance
		'config'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1,2,3,4,5,6'),	NULL), 

/* ARRAYS */
		'hosts'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groups'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'hostids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'groupids'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		'applications'=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
/* host */
		'hostid'=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({config})&&({config}==0||{config}==5||{config}==2)&&isset({form})&&({form}=="update")'),
		'host'=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==0||{config}==3||{config}==5)&&isset({save})&&!isset({massupdate})'),
		'proxy_hostid'=>array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		'isset({config})&&({config}==0)&&isset({save})&&!isset({massupdate})'),
		'dns'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'useip'=>	array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'ip'=>		array(T_ZBX_IP, O_OPT, NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'port'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),
		'status'=>	array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,3'),	'(isset({config})&&({config}==0))&&isset({save})&&!isset({massupdate})'),

		'newgroup'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'templates'=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	NULL),
		'clear_templates'=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

		'useipmi'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			NULL),
		'ipmi_ip'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_port'=>		array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),	'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_authtype'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-1,6),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_privilege'=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(1,5),		'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_username'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),
		'ipmi_password'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({useipmi})&&!isset({massupdate})'),

		'useprofile'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'devicetype'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'name'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'os'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'serialno'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'tag'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'macaddress'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'hardware'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'software'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'contact'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'location'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),
		'notes'=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})&&!isset({massupdate})'),	

		'useprofile_ext'=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		'ext_host_profiles'=> 	array(T_ZBX_STR, O_OPT, P_UNSET_EMPTY,   NULL,   NULL),

/* mass update*/
		'massupdate'=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'visible'=>			array(T_ZBX_STR, O_OPT,	null, 	null,	null),
		
/* group */
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==1))&&(isset({form})&&({form}=="update"))'),
		'gname'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==1))&&isset({save})'),

/* application */
		'applicationid'=>	array(T_ZBX_INT,O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==4))&&(isset({form})&&({form}=="update"))'),
		'appname'=>			array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==4))&&isset({save})'),
		'apphostid'=>		array(T_ZBX_INT, O_OPT, NULL,	DB_ID.'{}>0',	'(isset({config})&&({config}==4))&&isset({save})'),
		'apptemplateid'=>	array(T_ZBX_INT,O_OPT,	NULL,	DB_ID,	NULL),
		
/* host linkage form */
		'tname'=>			array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==2)&&isset({save})'),
		
// maintenance
		'maintenanceid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==6))&&(isset({form})&&({form}=="update"))'),
		'maintenanceids'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		NULL),
		'mname'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==6))&&isset({save})'),
		'maintenance_type'=>	array(T_ZBX_INT, O_OPT,  null,	null,		'(isset({config})&&({config}==6))&&isset({save})'),

		'description'=>			array(T_ZBX_STR, O_OPT,	NULL,	null,					'(isset({config})&&({config}==6))&&isset({save})'),
		'active_since'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,time()*2),	'(isset({config})&&({config}==6))&&isset({save})'),
		'active_till'=>			array(T_ZBX_INT, O_OPT,  null,	BETWEEN(1,time()*2),	'(isset({config})&&({config}==6))&&isset({save})'),
	
		'new_timeperiod'=>		array(T_ZBX_STR, O_OPT, null,	null,		'isset({add_timeperiod})'),
		
		'timeperiods'=>			array(T_ZBX_STR, O_OPT, null,	null, null),
		'g_timeperiodid'=>		array(null, O_OPT, null, null, null),
		
		'edit_timeperiodid'=>	array(null, O_OPT, P_ACT,	DB_ID,	null),
		
/* actions */
		'add_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, 	null, null),
		'del_timeperiod'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_timeperiod'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		
		'activate'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),	
		'disable'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),	

		'add_to_group'=>		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),	
		'delete_from_group'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),	

		'unlink'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		'unlink_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),

		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'full_clone'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'delete_and_clear'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		'form'=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		'form_refresh'=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('h.host',ZBX_SORT_UP);

	update_profile('web.hosts.config',$_REQUEST['config'], PROFILE_TYPE_INT);
?>
<?php

/************ ACTIONS FOR HOSTS ****************/
/* this code menages operations to unlink 1 template from multiple hosts */
	if($_REQUEST['config']==2 && (isset($_REQUEST['save']))){
		$hosts = get_request('hosts',array());
		if(isset($_REQUEST['hostid'])){
			$templateid=$_REQUEST['hostid'];
			$result = true;

// Permission check			
			$hosts = array_intersect($hosts,$available_hosts);
//-- unlink --
			DBstart();
	
			$linked_hosts = array();
			$db_childs = get_hosts_by_templateid($templateid);
			while($db_child = DBfetch($db_childs)){
				$linked_hosts[$db_child['hostid']] = $db_child['hostid'];
			}

			$unlink_hosts = array_diff($linked_hosts,$hosts);
						
			foreach($unlink_hosts as $id => $value){
				$result &= unlink_template($value, $templateid, false);
			}
//----------
//-- link --
			$link_hosts = array_diff($hosts,$linked_hosts);
			
			$template_name=DBfetch(DBselect('SELECT host FROM hosts WHERE hostid='.$templateid));			

			foreach($link_hosts as $id => $hostid){
			
				$host_groups=array();
				$db_hosts_groups = DBselect('SELECT groupid FROM hosts_groups WHERE hostid='.$hostid);
				while($hg = DBfetch($db_hosts_groups)) $host_groups[] = $hg['groupid'];

				$host=get_host_by_hostid($hostid);
				
				$templates_tmp=get_templates_by_hostid($hostid);
				$templates_tmp[$templateid]=$template_name['host'];
				
				$result &= update_host($hostid,
								$host['host'],$host['port'],$host['status'],$host['useip'],$host['dns'],
								$host['ip'],$host['proxy_hostid'],$templates_tmp,$host['useipmi'],$host['ipmi_ip'],
								$host['ipmi_port'],$host['ipmi_authtype'],$host['ipmi_privilege'],$host['ipmi_username'],
								$host['ipmi_password'],null,$host_groups);
			}
//----------
			$result = DBend($result);
			
			show_messages($result, S_LINK_TO_TEMPLATE, S_CANNOT_LINK_TO_TEMPLATE);
/*			if($result){
				$host=get_host_by_hostid($templateid);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
					'Host ['.$host['host'].'] '.
					'Mass Linkage '.
					'Status ['.$host['status'].']');
			}*/
//---		
			unset($_REQUEST['save']);
			unset($_REQUEST['hostid']);
			unset($_REQUEST['form']);
		}
	}
/* UNLINK HOST */
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && (isset($_REQUEST['unlink']) || isset($_REQUEST['unlink_and_clear']))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		if(isset($_REQUEST['unlink'])){
			$unlink_templates = array_keys($_REQUEST['unlink']);
		}
		else{
			$unlink_templates = array_keys($_REQUEST['unlink_and_clear']);
			$_REQUEST['clear_templates'] = array_merge($_REQUEST['clear_templates'],$unlink_templates);
		}
		foreach($unlink_templates as $id) unset($_REQUEST['templates'][$id]);
	}
/* CLONE HOST */
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
/* FULL CLONE HOST */
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && isset($_REQUEST['full_clone']) && isset($_REQUEST['hostid'])){
//		unset($_REQUEST['hostid']);
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
				if(isset($accessible_groups[$gid])) continue;
				access_deny();
			}
		}
		else{
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();
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

			$result = update_host($hostid,
				$db_host['host'],$db_host['port'],$db_host['status'],$db_host['useip'],$db_host['dns'],
				$db_host['ip'],$db_host['proxy_hostid'],$db_host['templates'],$db_host['useipmi'],$db_host['ipmi_ip'],
				$db_host['ipmi_port'],$db_host['ipmi_authtype'],$db_host['ipmi_privilege'],$db_host['ipmi_username'],
				$db_host['ipmi_password'],$_REQUEST['newgroup'],$db_host['groups']);

		
			if($result && isset($visible['useprofile'])){
				
				$host_profile=DBfetch(DBselect('SELECT * FROM hosts_profiles WHERE hostid='.$hostid));
				
				delete_host_profile($hostid);			
				
				if(get_request('useprofile','no') == 'yes'){				
					foreach($host_profile as $key => $value){
						if(isset($visible[$key])){
							$host_profile[$key] = $_REQUEST[$key];
						}
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

				delete_host_profile_ext($hostid);
//ext_host_profiles
				$useprofile_ext = get_request('useprofile_ext',false);
				$ext_host_profiles = get_request('ext_host_profiles',array());
				
				if($useprofile_ext && !empty($ext_host_profiles)){
					$result = add_host_profile_ext($hostid, $ext_host_profiles);
				}
				$result = DBend($result);
				
				if($useprofile_ext && !empty($ext_host_profiles)){
					$ext_host_profiles = get_request('ext_host_profiles',array());
					
					foreach($host_profile_ext as $key => $value){
						if(isset($visible[$key])){
							$host_profile_ext[$key] = $ext_host_profiles[$key];
						}
					}

					$result &= add_host_profile_ext($hostid,$host_profile_ext);
				}
			}
//HOSTS PROFILE EXTANDED Section		
			
/*			if($result){
				add_audit(
					AUDIT_ACTION_UPDATE,
					AUDIT_RESOURCE_HOST,
					'Host ['.$db_host['host'].'] IP ['.$db_host['ip'].'] '.'Status ['.$db_host['status'].']'
				);
			}*/
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
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && isset($_REQUEST['save'])){
		$useip = get_request('useip',0);
		$groups= get_request('groups',array());
		$useipmi = get_request('useipmi','no');
		
		if(count($groups) > 0){
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);
			foreach($groups as $gid){
				if(isset($accessible_groups[$gid])) continue;
				access_deny();
			}
		}
		else{
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();
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
/*			$audit_action 	= AUDIT_ACTION_UPDATE;*/

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
/*			$audit_action 	= AUDIT_ACTION_ADD;*/
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
//HOSTS PROFILE EXTANDED Section		
		
		show_messages($result, $msg_ok, $msg_fail);
		
		if($result){
/*			add_audit($audit_action,AUDIT_RESOURCE_HOST,
				'Host ['.$_REQUEST['host'].'] IP ['.$_REQUEST['ip'].'] '.
				'Status ['.$_REQUEST['status'].']');*/

			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		}
		unset($_REQUEST['save']);
	}

/* DELETE HOST */ 
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && (isset($_REQUEST['delete']) || isset($_REQUEST['delete_and_clear']))){
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
/*				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,'Host ['.$host['host'].']');*/

				unset($_REQUEST['form']);
				unset($_REQUEST['hostid']);
			}
		} 
		else {
/* group operations */
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
		unset($_REQUEST['delete']);
	}
/* ADD / REMOVE HOSTS FROM GROUP*/
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && (inarr_isset(array('add_to_group','hostid')))){
//		if(!uint_in_array($_REQUEST['add_to_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))){
		if(!isset($available_groups[$_REQUEST['add_to_group']])){
			access_deny();
		}

		DBstart();
			$result = add_host_to_group($_REQUEST['hostid'], $_REQUEST['add_to_group']);
		$result = DBend($result);
		
		show_messages($result,S_HOST_UPDATED,S_CANNOT_UPDATE_HOST);
	}
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && (inarr_isset(array('delete_from_group','hostid')))){
//		if(!uint_in_array($_REQUEST['delete_from_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))){
		if(!isset($available_groups[$_REQUEST['delete_from_group']])){
			access_deny();
		}

		DBstart();
			$result = delete_host_from_group($_REQUEST['hostid'], $_REQUEST['delete_from_group']);
		$result = DBend($result);
		
		show_messages($result, S_HOST_UPDATED, S_CANNOT_UPDATE_HOST);
	}
/* ACTIVATE / DISABLE HOSTS */
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && (isset($_REQUEST['activate'])||isset($_REQUEST['disable']))){
	
		$result = true;
		$status = isset($_REQUEST['activate']) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		
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
/*			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,'Host ['.$db_host['host'].']. Old status ['.$db_host['status'].'] '.'New status ['.$status.']');*/
		}

		$result = update_host_status($act_hosts,$status);
		$result = DBend($result);
		
		show_messages($result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
		unset($_REQUEST['activate']);
	}
	else if(($_REQUEST['config']==0 || $_REQUEST['config']==3) && isset($_REQUEST['chstatus']) && isset($_REQUEST['hostid'])){
	
		$host=get_host_by_hostid($_REQUEST['hostid']);
		
		DBstart();
			$result = update_host_status($_REQUEST['hostid'],$_REQUEST['chstatus']);
		$result = DBend($result);
		
		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
/*		if($result){
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,'Host ['.$db_host['host'].']. Old status ['.$host['status'].'] New status ['.$_REQUEST['chstatus'].']');
		}*/
		unset($_REQUEST['chstatus']);
		unset($_REQUEST['hostid']);
	}

/****** ACTIONS FOR GROUPS **********/
/* CLONE HOST */
	else if($_REQUEST['config']==1 && isset($_REQUEST['clone']) && isset($_REQUEST['groupid'])){
		unset($_REQUEST['groupid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(($_REQUEST['config']==1) && isset($_REQUEST['save'])){
		$hosts = get_request('hosts',array());
		if(isset($_REQUEST['groupid'])){
			DBstart();
				$result = update_host_group($_REQUEST['groupid'], $_REQUEST['gname'], $hosts);
			$result = DBend($result);
			
/*			$action 	= AUDIT_ACTION_UPDATE;*/
			$msg_ok		= S_GROUP_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_GROUP;
			$groupid = $_REQUEST['groupid'];
		} 
		else {
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();
			
			DBstart();
				$groupid = add_host_group($_REQUEST['gname'], $hosts);
			$result = DBend($groupid);
			
/*			$action 	= AUDIT_ACTION_ADD;*/
			$msg_ok		= S_GROUP_ADDED;
			$msg_fail	= S_CANNOT_ADD_GROUP;
		}
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
/*			add_audit($action,AUDIT_RESOURCE_HOST_GROUP,S_HOST_GROUP.' ['.$_REQUEST['gname'].'] ['.$groupid.']');*/
			unset($_REQUEST['form']);
		}
		unset($_REQUEST['save']);
	}
	
	if(($_REQUEST['config']==1) && isset($_REQUEST['delete'])){
		if(isset($_REQUEST['groupid'])){
			$result = false;
/*			if($group = get_hostgroup_by_groupid($_REQUEST['groupid'])){*/
				DBstart();
				$result = delete_host_group($_REQUEST['groupid']);
				$result = DBend($result);
/*			} */

/*			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,S_HOST_GROUP.' ['.$group['name'].' ] ['.$group['groupid'].']');
			}*/
			
			unset($_REQUEST['form']);

			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			unset($_REQUEST['groupid']);
		} 
		else {
/* group operations */
			$result = true;

			$groups = get_request('groups',array());			
			$db_groups=DBselect('select groupid, name from groups where '.DBin_node('groupid'));
			
			DBstart();
			while($db_group=DBfetch($db_groups)){
				if(!uint_in_array($db_group['groupid'],$groups)) continue;
			
/*				if(!$group = get_hostgroup_by_groupid($db_group['groupid'])) continue;*/
				$result &= delete_host_group($db_group['groupid']);
				
/*				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,
					S_HOST_GROUP.' ['.$group['name'].' ] ['.$group['groupid'].']');
				}*/
			}
			$result = DBend($result);
			show_messages(true, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
		}
		unset($_REQUEST['delete']);
	}

	if(($_REQUEST['config']==1) && (isset($_REQUEST['activate']) || isset($_REQUEST['disable']))){
		$result = true;
		$status = isset($_REQUEST['activate']) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$groups = get_request('groups',array());

		$db_hosts=DBselect('select h.hostid, hg.groupid '.
			' from hosts_groups hg, hosts h'.
			' where h.hostid=hg.hostid '.
				' and h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' and '.DBin_node('h.hostid'));
				
		DBstart();
		while($db_host=DBfetch($db_hosts)){
			if(!uint_in_array($db_host['groupid'],$groups)) continue;
			$host=get_host_by_hostid($db_host['hostid']);

			$result &= update_host_status($db_host['hostid'],$status);
/*			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
				'Old status ['.$host['status'].'] '.'New status ['.$status.']');*/
		}
		$result = DBend($result);
		show_messages($result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
		
		unset($_REQUEST['activate']);
	}

	if($_REQUEST['config']==4 && isset($_REQUEST['save'])){
		DBstart();
		if(isset($_REQUEST['applicationid'])){
			$result = update_application($_REQUEST['applicationid'],$_REQUEST['appname'], $_REQUEST['apphostid']);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_APPLICATION_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_APPLICATION;
			$applicationid = $_REQUEST['applicationid'];
		} 
		else {
			$applicationid = add_application($_REQUEST['appname'], $_REQUEST['apphostid']);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_APPLICATION_ADDED;
			$msg_fail	= S_CANNOT_ADD_APPLICATION;
		}
		$result = DBend($applicationid);		
		
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_APPLICATION,S_APPLICATION.' ['.$_REQUEST['appname'].' ] ['.$applicationid.']');
			unset($_REQUEST['form']);
		}
		unset($_REQUEST['save']);
	}
	else if($_REQUEST['config']==4 && isset($_REQUEST['delete'])){
		if(isset($_REQUEST['applicationid'])){
			$result = false;
			if($app = get_application_by_applicationid($_REQUEST['applicationid'])){
				$host = get_host_by_hostid($app['hostid']);
				
				DBstart();
				$result=delete_application($_REQUEST['applicationid']);
				$result = DBend($result);
			}
			show_messages($result, S_APPLICATION_DELETED, S_CANNOT_DELETE_APPLICATION);
			
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,'Application ['.$app['name'].'] from host ['.$host['host'].']');
			}
			unset($_REQUEST['form']);
			unset($_REQUEST['applicationid']);
		} 
		else {
/* group operations */
			$result = true;
			
			$applications = get_request('applications',array());
			$db_applications = DBselect('SELECT applicationid, name, hostid '.
									' FROM applications '.
									' WHERE '.DBin_node('applicationid'));

			DBstart();
			while($db_app = DBfetch($db_applications)){
				if(!uint_in_array($db_app['applicationid'],$applications))	continue;
				
				$result &= delete_application($db_app['applicationid']);

				if($result){
					$host = get_host_by_hostid($db_app['hostid']);
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,'Application ['.$db_app['name'].'] from host ['.$host['host'].']');
				}
			}
			$result = DBend($result);
			
			show_messages(true, S_APPLICATION_DELETED, NULL);
		}
		unset($_REQUEST['delete']);
	}
	else if(($_REQUEST['config']==4) && (isset($_REQUEST['activate']) || isset($_REQUEST['disable']))){
/* group operations */
		$result = true;
		$applications = get_request('applications',array());

		DBstart();
		foreach($applications as $id => $appid){
	
			$sql = 'SELECT ia.itemid,i.hostid,i.key_'.
					' FROM items_applications ia '.
					  ' LEFT JOIN items i ON ia.itemid=i.itemid '.
					' WHERE ia.applicationid='.$appid.
					  ' AND i.hostid='.$_REQUEST['hostid'].
					  ' AND '.DBin_node('ia.applicationid');

			$res_items = DBselect($sql);
			while($item=DBfetch($res_items)){

					if(isset($_REQUEST['activate'])){
						if($result&=activate_item($item['itemid'])){
/*							$host = get_host_by_hostid($item['hostid']);
							add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].'] '.S_ITEMS_ACTIVATED);*/
						}
					}
					else{
						if($result&=disable_item($item['itemid'])){
/*							$host = get_host_by_hostid($item['hostid']);
							add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].'] '.S_ITEMS_DISABLED);*/
						}
					}
			}
		}
		$result = DBend($result);
		(isset($_REQUEST['activate']))?show_messages($result, S_ITEMS_ACTIVATED, null):show_messages($result, S_ITEMS_DISABLED, null);
	}
	else if($_REQUEST['config']==5 && isset($_REQUEST['save'])){
		$result = true;
		$hosts = get_request('hosts',array());
		
		DBstart();
		if(isset($_REQUEST['hostid'])){
			$result 	= update_proxy($_REQUEST['hostid'], $_REQUEST['host'], $hosts);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_PROXY_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_PROXY;
			$hostid		= $_REQUEST['hostid'];
		} 
		else {
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();
			
			$hostid		= add_proxy($_REQUEST['host'], $hosts);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_PROXY_ADDED;
			$msg_fail	= S_CANNOT_ADD_PROXY;
		}
		$result = DBend($result);
		
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_PROXY,'['.$_REQUEST['host'].' ] ['.$hostid.']');
			unset($_REQUEST['form']);
		}
		unset($_REQUEST['save']);
	}
	else if($_REQUEST['config']==5 && isset($_REQUEST['delete'])){
		$result = false;

		if(isset($_REQUEST['hostid'])){
			if($proxy = get_host_by_hostid($_REQUEST['hostid'])){
				DBstart();
				$result = delete_proxy($_REQUEST['hostid']);
				$result = DBend();
			}
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
			}
			
			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
			unset($_REQUEST['form']);
			unset($_REQUEST['hostid']);
		} 
		else {
			$hosts = get_request('hosts',array());

			foreach($hosts as $hostid){
				$proxy = get_host_by_hostid($hostid);
				
				DBstart();
				$result = delete_proxy($hostid);
				$result = DBend();
				
				if(!$result) break;
				
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,	'['.$proxy['host'].' ] ['.$proxy['hostid'].']');
			}
			
			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
		}
		unset($_REQUEST['delete']);
	}
	else if($_REQUEST['config']==5 && isset($_REQUEST['clone']) && isset($_REQUEST['hostid'])){
		unset($_REQUEST['hostid']);
		$_REQUEST['form'] = 'clone';
	}
	else if($_REQUEST['config']==5 && (isset($_REQUEST['activate']) || isset($_REQUEST['disable']))){
		$result = true;
		
		$status = isset($_REQUEST['activate']) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request('hosts',array());

		DBstart();
		foreach($hosts as $hostid){
			$db_hosts = DBselect('SELECT  hostid,status '.
								' FROM hosts '.
								' WHERE proxy_hostid='.$hostid.
									' AND '.DBin_node('hostid'));
									
			while($db_host = DBfetch($db_hosts)){
				$old_status = $db_host['status'];
				if($old_status == $status) continue;

				$result &= update_host_status($db_host['hostid'], $status);
				if(!$result) continue;

/*				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,'Old status ['.$old_status.'] '.'New status ['.$status.'] ['.$db_host['hostid'].']');*/
			}
		}
		$result = DBend($result && !empty($hosts));
		show_messages($result, S_HOST_STATUS_UPDATED, NULL);

		if(isset($_REQUEST['activate']))
			unset($_REQUEST['activate']);
		else
			unset($_REQUEST['disable']);
	}
	else if($_REQUEST['config'] == 6){
		if(inarr_isset(array('clone','maintenanceid'))){
			unset($_REQUEST['maintenanceid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['cancel_new_timeperiod'])){
			unset($_REQUEST['new_timeperiod']);
		}
		else if(isset($_REQUEST['save'])){
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();	
				
			$maintenance = array('name' => $_REQUEST['mname'],
						'maintenance_type' => $_REQUEST['maintenance_type'],
						'description'=>	$_REQUEST['description'],
						'active_since'=> $_REQUEST['active_since'],
						'active_till' => zbx_empty($_REQUEST['active_till'])?0:$_REQUEST['active_till']
					);
					
			$timeperiods = get_request('timeperiods', array());
			
			DBstart();

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
			if(isset($_REQUEST['maintenanceid'])) delete_timeperiods_by_maintenanceid($_REQUEST['maintenanceid']);
			
			$timeperiodids = array();
			foreach($timeperiods as $id => $timeperiod){
				$timeperiodid = add_timeperiod($timeperiod);
				$timeperiodids[$timeperiodid] = $timeperiodid;
			}
			

			if(isset($_REQUEST['maintenanceid'])){
	
				$maintenanceid=$_REQUEST['maintenanceid'];
					
				$result = update_maintenance($maintenanceid, $maintenance);

				$msg1 = S_MAINTENANCE_UPDATED;
				$msg2 = S_CANNOT_UPDATE_MAINTENANCE;
			} 
			else {
				$result = $maintenanceid = add_maintenance($maintenance);

				$msg1 = S_MAINTENANCE_ADDED;
				$msg2 = S_CANNOT_ADD_MAINTENANCE;
			}
							
			save_maintenances_windows($maintenanceid, $timeperiodids);

			$hostids = get_request('hostids', array());
			save_maintenance_host_links($maintenanceid, $hostids);

			$groupids = get_request('groupids', array());
			save_maintenance_group_links($maintenanceid, $groupids);

			$result = DBend($result);
			show_messages($result,$msg1,$msg2);
			
	
			if($result){ // result - OK
				add_audit(!isset($_REQUEST['maintenanceid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE, 
					AUDIT_RESOURCE_MAINTENANCE, 
					S_NAME.': '.$_REQUEST['mname']);
	
				unset($_REQUEST['form']);
			}
		}
		else if(isset($_REQUEST['delete'])){
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();
			
			$maintenanceids = get_request('maintenanceid', array());
			if(isset($_REQUEST['maintenanceids']))
				$maintenanceids = $_REQUEST['maintenanceids'];
			
			zbx_value2array($maintenanceids);
			
			$maintenances = array();
			foreach($maintenanceids as $id => $maintenanceid){
				$maintenances[$maintenanceid] = get_maintenance_by_maintenanceid($maintenanceid);
			}
			
			DBstart();
			$result = delete_maintenance($maintenanceids);
			$result = DBend($result);
			
			show_messages($result,S_MAINTENANCE_DELETED,S_CANNOT_DELETE_MAINTENANCE);
			if($result){
				foreach($maintenances as $maintenanceid => $maintenance){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MAINTENANCE,'Id ['.$maintenanceid.'] '.S_NAME.' ['.$maintenance['name'].']');
				}
				
				unset($_REQUEST['form']);
				unset($_REQUEST['maintenanceid']);
			}
		}
		else if(inarr_isset(array('add_timeperiod','new_timeperiod'))){
			$new_timeperiod = $_REQUEST['new_timeperiod'];

// START TIME
			$new_timeperiod['start_time'] = ($new_timeperiod['hour'] * 3600) + ($new_timeperiod['minute'] * 60);	
//--

// PERIOD
			$new_timeperiod['period'] = ($new_timeperiod['period_days'] * 86400) + ($new_timeperiod['period_hours'] * 3600);
//--

// DAYSOFWEEK
			if(!isset($new_timeperiod['dayofweek'])){
				$dayofweek = '';
				
				$dayofweek .= (!isset($new_timeperiod['dayofweek_su']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_sa']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_fr']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_th']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_we']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_tu']))?'0':'1';
				$dayofweek .= (!isset($new_timeperiod['dayofweek_mo']))?'0':'1';

				$new_timeperiod['dayofweek'] = bindec($dayofweek);
			}
//--

// MONTHS		
			if(!isset($new_timeperiod['month'])){
				$month = '';

				$month .= (!isset($new_timeperiod['month_dec']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_nov']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_oct']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_sep']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_aug']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_jul']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_jun']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_may']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_apr']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_mar']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_feb']))?'0':'1';
				$month .= (!isset($new_timeperiod['month_jan']))?'0':'1';

				$new_timeperiod['month'] = bindec($month);
			}
//--	

			if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
				if($new_timeperiod['month_date_type'] > 0){
					$new_timeperiod['day'] = 0;
				}
				else{
					$new_timeperiod['every'] = 0;
					$new_timeperiod['dayofweek'] = 0;
				}
			}

			$_REQUEST['timeperiods'] = get_request('timeperiods',array());
			
			$result = false;
			if($new_timeperiod['period'] < 3600) {
				info(S_INCORRECT_PERIOD);
			}
			else if(($new_timeperiod['hour'] > 23) || ($new_timeperiod['minute'] > 59)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) && ($new_timeperiod['date'] < 1)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else if(($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) && ($new_timeperiod['every'] < 1)){
				info(S_INCORRECT_MAINTENANCE_PERIOD);
			}
			else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
				if(($new_timeperiod['every'] < 1) || ($new_timeperiod['dayofweek'] < 1)){
					info(S_INCORRECT_MAINTENANCE_PERIOD);
				}
				else{
					$result = true;
				}
			}
			else if($new_timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
				if($new_timeperiod['month'] < 1){
					info(S_INCORRECT_MAINTENANCE_PERIOD);
				}
				else if(($new_timeperiod['day'] == 0) && ($new_timeperiod['dayofweek'] < 1)){
					info(S_INCORRECT_MAINTENANCE_PERIOD);
				}
				else if((($new_timeperiod['day'] < 1) || ($new_timeperiod['day'] > 31)) && ($new_timeperiod['dayofweek'] == 0)){
					info(S_INCORRECT_MAINTENANCE_PERIOD);
				}
				else{
					$result = true;
				}
			}
			else{
				$result = true;
			}

			if($result){
				if(!isset($new_timeperiod['id'])){
					if(!str_in_array($new_timeperiod,$_REQUEST['timeperiods']))
						array_push($_REQUEST['timeperiods'],$new_timeperiod);
				}
				else{
					$id = $new_timeperiod['id'];
					unset($new_timeperiod['id']);
					$_REQUEST['timeperiods'][$id] = $new_timeperiod;
				}
	
				unset($_REQUEST['new_timeperiod']);
			}
		}
		else if(inarr_isset(array('del_timeperiod','g_timeperiodid'))){
			$_REQUEST['timeperiods'] = get_request('timeperiods',array());
			foreach($_REQUEST['g_timeperiodid'] as $val){
				unset($_REQUEST['timeperiods'][$val]);
			}
		}
		else if(inarr_isset(array('edit_timeperiodid'))){	
			$_REQUEST['edit_timeperiodid'] = array_keys($_REQUEST['edit_timeperiodid']);
			$edit_timeperiodid = $_REQUEST['edit_timeperiodid'] = array_pop($_REQUEST['edit_timeperiodid']);
			$_REQUEST['timeperiods'] = get_request('timeperiods',array());

			if(isset($_REQUEST['timeperiods'][$edit_timeperiodid])){
				$_REQUEST['new_timeperiod'] = $_REQUEST['timeperiods'][$edit_timeperiodid];
				$_REQUEST['new_timeperiod']['id'] = $edit_timeperiodid;
			}
		}
	}


	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
?>
<?php
	$params = array();	
	switch($_REQUEST['config']){
		case 0:
			$options = array('only_current_node','allow_all','real_hosts');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
			
			validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);
			break;
		case 1:
			$options = array('only_current_node');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['groupids'], $params);

			validate_group($PAGE_GROUPS, $PAGE_HOSTS, $PAGE_HOSTS, false);
			break;
		case 2:
			$options = array('only_current_node','templated_hosts');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);

			$params = array();	
			$options = array('only_current_node','not_proxy_hosts');
			foreach($options as $option) $params[$option] = 1;
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $available_groups, $params);	// more hosts

			validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);
			break;
		case 3:
			$options = array('only_current_node','allow_all','templated_hosts');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
	
			validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);
			break;
		case 5:
			$options = array('only_current_node');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
	
			validate_group($PAGE_GROUPS, $PAGE_HOSTS, false);
			break;
		case 6:
			$options = array('only_current_node','allow_all');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
			
			validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS,false);
			break;
		default:
			$options = array('only_current_node');
			if(isset($_REQUEST['form']) || isset($_REQUEST['massupdate'])) array_push($options,'do_not_select_if_empty');
			
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
			
			validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);
	}

//	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
	$available_groups = $PAGE_GROUPS['groupids'];
	$available_hosts = $PAGE_HOSTS['hostids'];
?>
<?php

	$frmForm = new CForm();
	$frmForm->setMethod('get');
	
	$cmbConf = new CComboBox('config',$_REQUEST['config'],'submit()');
	$cmbConf->AddItem(1,S_HOST_GROUPS);
	$cmbConf->AddItem(0,S_HOSTS);
	$cmbConf->AddItem(3,S_TEMPLATES);
	$cmbConf->AddItem(2,S_TEMPLATE_LINKAGE);
	$cmbConf->AddItem(5,S_PROXIES);
	$cmbConf->AddItem(6,S_MAINTENANCE);
	$cmbConf->AddItem(4,S_APPLICATIONS);


	switch($_REQUEST['config']){
		case 0:
			$btn = new CButton('form',S_CREATE_HOST);
			$frmForm->addVar('groupid',get_request('groupid',0));
			break;
		case 1: 
			$btn = new CButton('form',S_CREATE_GROUP);
			break;
		case 2: 
			break;
		case 3:
			$btn = new CButton('form',S_CREATE_TEMPLATE);
			$frmForm->addVar('groupid',get_request('groupid',0));
			break;
		case 4: 
			$btn = new CButton('form',S_CREATE_APPLICATION);
			$frmForm->addVar('hostid',get_request('hostid',0));
			break;
		case 5:
			$btn = new CButton('form',S_CREATE_PROXY);
			break;
		case 6:
			$btn = new CButton('form',S_CREATE_MAINTENANCE_PERIOD);
			break;
	}

	$frmForm->addItem($cmbConf);
	if(isset($btn) && !isset($_REQUEST['form'])){
		$frmForm->addItem(SPACE);
		$frmForm->addItem($btn);
	}
	
	show_table_header(S_CONFIGURATION_OF_HOSTS_GROUPS_AND_TEMPLATES, $frmForm);
?>
<?php
	$row_count = 0;
	
	if($_REQUEST['config']==0 || $_REQUEST['config']==3){
		echo SBR;
		$show_only_tmp=($_REQUEST['config'] == 3)?1:0;

		if(isset($_REQUEST['massupdate']) && isset($_REQUEST['hosts'])){
			insert_mass_update_host_form();
		}
		else if(isset($_REQUEST['form'])){
			insert_host_form($show_only_tmp);
		}
		else{
		
			$frmForm = new CForm();
			$frmForm->setMethod('get');

			$frmForm->addVar('config',$_REQUEST['config']);

			$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
			foreach($PAGE_GROUPS['groups'] as $groupid => $name){
				$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
			}
			
			$frmForm->addItem(array(S_GROUP.SPACE,$cmbGroups));
			
			$numrows = new CSpan(null,'info');
			$numrows->addOption('name','numrows');
			$header_name = ($show_only_tmp) ? S_TEMPLATES_BIG : S_HOSTS_BIG;
	
			$header = get_table_header(array($header_name,
							new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
							S_FOUND.': ',$numrows,)
							);							
			show_table_header($header, $frmForm);

/* table HOSTS */			
			$form = new CForm();
			
			$form->setName('hosts');
			$form->addVar('config',get_request('config',0));

			$table = new CTableInfo(S_NO_HOSTS_DEFINED);
			$table->setHeader(array(
				array(new CCheckBox('all_hosts',NULL,"CheckAll('".$form->GetName()."','all_hosts');"),
					SPACE,make_sorting_link(S_NAME,'h.host')),
				$show_only_tmp ? NULL : make_sorting_link(S_DNS,'h.dns'),
				$show_only_tmp ? NULL : make_sorting_link(S_IP,'h.ip'),
				$show_only_tmp ? NULL : make_sorting_link(S_PORT,'h.port'),
				S_TEMPLATES,
				$show_only_tmp ? NULL : make_sorting_link(S_STATUS,'h.status'),
				$show_only_tmp ? NULL : make_sorting_link(S_AVAILABILITY,'h.available'),
				$show_only_tmp ? NULL : S_ERROR,
				S_ACTIONS
				));
		

				
			$sql_from = '';
			$sql_where = '';
			if($_REQUEST['groupid'] > 0){
				$sql_from.= ',hosts_groups hg ';
				$sql_where.= ' AND hg.groupid='.$_REQUEST['groupid'].' AND hg.hostid=h.hostid ';
			} 
			
			$sql='SELECT DISTINCT h.* '.
				' FROM hosts h '.$sql_from.
				' WHERE '.DBcondition('h.hostid',$available_hosts).
					$sql_where.
				order_by('h.host,h.port,h.ip,h.status,h.available,h.dns');
			$result=DBselect($sql);
			while($row=DBfetch($result)){
				$description = array();

				if($row['proxy_hostid']){
					$proxy = get_host_by_hostid($row['proxy_hostid']);
					array_push($description,$proxy['host'],':');
				}
			
				array_push($description, new CLink($row['host'], 'hosts.php?form=update&hostid='.$row['hostid'].url_param('groupid').url_param('config'), 'action'));

				$templates = get_templates_by_hostid($row['hostid']);
				
				$host=new CCol(array(
					new CCheckBox('hosts['.$row['hostid'].']',NULL,NULL,$row['hostid']),
					SPACE,
					$description));
		
				
				if($show_only_tmp){
					$dns = NULL;
					$ip = NULL;
					$port = NULL;
					$status = NULL;
					$available = NULL;
					$error = NULL;
				}
				else{
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
							$status=new CLink(S_MONITORED,'hosts.php?hosts%5B%5D='.$row['hostid'].'&disable=1'.url_param('config').url_param('groupid'),'off');
							break;
						case HOST_STATUS_NOT_MONITORED:
							$status=new CLink(S_NOT_MONITORED,'hosts.php?hosts%5B%5D='.$row['hostid'].'&activate=1'.url_param('config').url_param('groupid'),'on');
							break;
						case HOST_STATUS_TEMPLAT:
							$status=new CCol(S_TEMPLATE,'unknown');
							break;
						case HOST_STATUS_DELETED:
							$status=new CCol(S_DELETED,'unknown');
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

					if($row['error'] == '')	$error = new CCol(SPACE,'off');
					else			$error = new CCol($row['error'],'on');

					$row['error'] = trim($row['error']);
					if(empty($row['error']))
						$error = new CCol('-','off');
					else 
						$error = new CCol($row['error'],'on');
				}


				$show = host_js_menu($row['hostid']);

				$templates_linked = array();
				foreach($templates as $templateid => $temp){
					$templates_linked[$templateid] = array(empty($templates_linked)?'':', ',host_js_menu($templateid, $templates[$templateid]));
				}

				$table->addRow(array(
					$host,
					$dns,
					$ip,
					$port,
					empty($templates)?'-':$templates_linked,
					$status,
					$available,
					$error,
					$show));
					
				$row_count++;

				$jsmenu = new CPUMenu(null,270);
				$jsmenu->InsertJavaScript();

				set_hosts_jsmenu_array();
			}

			$footerButtons = array(
				$show_only_tmp ? NULL : new CButtonQMessage('activate',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_HOSTS_Q),
				$show_only_tmp ? NULL : SPACE,
				$show_only_tmp ? NULL : new CButtonQMessage('disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_HOSTS_Q),
				$show_only_tmp ? NULL : SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_HOSTS_Q),
				$show_only_tmp ? NULL : SPACE,
				$show_only_tmp ? NULL : new CButton('massupdate',S_MASS_UPDATE),
				$show_only_tmp ? SPACE : NULL,
				$show_only_tmp ? new CButtonQMessage('delete_and_clear',S_DELETE_SELECTED_WITH_LINKED_ELEMENTS,S_DELETE_SELECTED_HOSTS_Q) : NULL
				);

			$table->SetFooter(new CCol($footerButtons));

			$form->AddItem($table);
			$form->Show();

		}
	}
	else if($_REQUEST['config']==1){
	
		if(isset($_REQUEST['form'])){
			insert_hostgroups_form($_REQUEST['groupid']);
		} 
		else {
		
			$numrows = new CSpan(null,'info');
			$numrows->addOption('name','numrows');	
			$header = get_table_header(array(S_HOST_GROUPS_BIG,
							new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
							S_FOUND.': ',$numrows,)
							);						
			show_table_header($header );

			$form = new CForm('hosts.php');
			
			$form->setName('groups');
			$form->addVar('config',get_request('config',0));

			$table = new CTableInfo(S_NO_HOST_GROUPS_DEFINED);

			$table->setHeader(array(
				array(	new CCheckBox('all_groups',NULL,
						"CheckAll('".$form->GetName()."','all_groups');"),
					SPACE,
					make_sorting_link(S_NAME,'g.name')),
				' # ',
				S_MEMBERS));

			$sql = 'SELECT g.groupid,g.name '.
					' FROM groups g'.
					' WHERE '.DBcondition('g.groupid',$available_groups).
					order_by('g.name');
			$db_groups=DBselect($sql);
			while($db_group=DBfetch($db_groups)){
				$count = 0;
				$hosts = array();

				$sql = 'SELECT DISTINCT h.host, h.hostid, h.status'.
						' FROM hosts h, hosts_groups hg'.
						' WHERE h.hostid=hg.hostid '.
							' AND hg.groupid='.$db_group['groupid'].
							' AND '.DBcondition('h.hostid',$available_hosts).
						' ORDER BY host';
				$db_hosts = DBselect($sql);
				while($db_host=DBfetch($db_hosts)){
					$link = 'hosts.php?form=update&config=0&hostid='.$db_host['hostid'];
					switch($db_host['status']){
						case HOST_STATUS_MONITORED:
							$style = null;
							break;
						case HOST_STATUS_TEMPLATE:
							$style = 'unknown';
							break;
						default:
							$style = 'on';
					}

					array_push($hosts, empty($hosts)?'':', ', new CLink(new CSpan($db_host['host'], $style), $link));
					$count++;
				}

				$table->addRow(array(
					array(
						new CCheckBox('groups['.$db_group['groupid'].']',NULL,NULL,$db_group['groupid']),
						SPACE,
						new CLink(
							$db_group['name'],
							'hosts.php?form=update&groupid='.$db_group['groupid'].
							url_param('config'),'action')
					),
					$count,
					new CCol((empty($hosts)?'-':$hosts),'wraptext')
					));
					$row_count++;
			}
			$table->SetFooter(new CCol(array(
				new CButtonQMessage('activate',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_HOSTS_Q),
				SPACE,
				new CButtonQMessage('disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_HOSTS_Q),
				SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)
			)));

			$form->AddItem($table);
			$form->Show();
		}
	}
// Original mod by scricca@vipsnet.net
// Modified by Aly
/* this code adds links to Template Names in Template_Linkage page and link them to the form in forms.inc.php */
	else if($_REQUEST['config']==2){
		echo SBR;
		if(isset($_REQUEST['form'])){
			insert_template_form($PAGE_HOSTS['hostids']);
		} 
		else{
			$frmForm = new CForm();
			$frmForm->setMethod('get');

			$frmForm->addVar('config',$_REQUEST['config']);

			$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
			foreach($PAGE_GROUPS['groups'] as $groupid => $name){
				$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
			}
			
			$frmForm->addItem(array(S_GROUP.SPACE,$cmbGroups));
			
			$numrows = new CSpan(null,'info');
			$numrows->addOption('name','numrows');
			$header = get_table_header(array(S_TEMPLATE_LINKAGE_BIG,
							new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
							S_FOUND.': ',$numrows,)
							);							
			show_table_header($header);
		
			$table = new CTableInfo(S_NO_LINKAGES);
			$table->setHeader(array(S_TEMPLATES,S_HOSTS));
		
			$sql = 'SELECT h.* '.
					' FROM hosts h, hosts_groups hg '.
					' WHERE hg.groupid='.$_REQUEST['groupid'].
						' AND h.hostid=hg.hostid '.
						' AND '.DBcondition('h.hostid',$available_hosts).
					' ORDER BY h.host';
			$templates = DBSelect($sql);
			while($template = DBfetch($templates)){
			
				$hosts = DBSelect('SELECT DISTINCT h.host, h.hostid, h.status '.
					' FROM hosts h, hosts_templates ht '.
					' WHERE ht.templateid='.$template['hostid'].
						' AND ht.hostid=h.hostid '.
						' AND '.DBcondition('h.hostid',$available_hosts).
					' ORDER BY h.host');
				$host_list = array();
				while($host = DBfetch($hosts)){
					$link = 'hosts.php?form=update&config=0&hostid='.$host['hostid'];
					switch($host['status']){
						case HOST_STATUS_MONITORED:
							$style = null;
							break;
						case HOST_STATUS_TEMPLATE:
							$style = 'unknown';
							break;
						default:
							$style = 'on';
					}

					array_push($host_list, empty($host_list)?'':', ', new CLink(new CSpan($host['host'], $style), $link));
				}
				
				$table->addRow(array(		
					new CCol(array(
						new CLink($template['host'],'hosts.php?form=update&hostid='.
							$template['hostid'].url_param('groupid').url_param('config'), 'action')
						),'unknown'),
					empty($host_list)?'-':new CCol($host_list,'wraptext')
				));
				$row_count++;
			}
			
			$table->Show();
		}
//----- END MODE -----
	}
	else if($_REQUEST['config']==4){
		echo SBR;
		if(isset($_REQUEST['form'])){
			insert_application_form();
		} 
		else {
	// Table HEADER
			$form = new CForm();
			$form->setMethod('get');
			
			$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
			$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
		
			foreach($PAGE_GROUPS['groups'] as $groupid => $name){
				$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
			}
			foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
				$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
			}
			$form->AddItem($cmbHosts);
			
			$form->addItem(array(S_GROUP.SPACE,$cmbGroups));
			$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
			
			$numrows = new CSpan(null,'info');
			$numrows->addOption('name','numrows');
			$header = get_table_header(array(S_APPLICATIONS_BIG,
							new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
							S_FOUND.': ',$numrows,)
							);							
			show_table_header($header, $form);

/* TABLE */

			$form = new CForm();
			$form->SetName('applications');

			$table = new CTableInfo();
			$table->SetHeader(array(
				array(new CCheckBox('all_applications',NULL,"CheckAll('".$form->GetName()."','all_applications');"),
				SPACE,
				make_sorting_link(S_APPLICATION,'a.name')),
				S_SHOW
				));

			$db_applications = DBselect('SELECT a.* '.
									' FROM applications a'.
									' WHERE a.hostid='.$_REQUEST['hostid'].
									order_by('a.name'));
									
			while($db_app = DBfetch($db_applications)){
				if($db_app['templateid']==0){
					$name = new CLink(
						$db_app['name'],
						'hosts.php?form=update&applicationid='.$db_app['applicationid'].
						url_param('config'),'action');
				}
				else {
					$template_host = get_realhost_by_applicationid($db_app['templateid']);
					$name = array(		
						new CLink($template_host['host'],
							'hosts.php?hostid='.$template_host['hostid'].url_param('config'),
							'action'),
						':',
						$db_app['name']
						);
				}
				$items=get_items_by_applicationid($db_app['applicationid']);
				$rows=0;
				while(DBfetch($items))	$rows++;

				$table->addRow(array(
					array(new CCheckBox('applications['.$db_app['applicationid'].']',NULL,NULL,$db_app['applicationid']),SPACE,$name),
					array(new CLink(S_ITEMS,'items.php?hostid='.$db_app['hostid'],'action'),
					SPACE.'('.$rows.')')
					));
				$row_count++;
			}
			$table->setFooter(new CCol(array(
				new CButtonQMessage('activate',S_ACTIVATE_ITEMS,S_ACTIVATE_ITEMS_FROM_SELECTED_APPLICATIONS_Q),
				SPACE,
				new CButtonQMessage('disable',S_DISABLE_ITEMS,S_DISABLE_ITEMS_FROM_SELECTED_APPLICATIONS_Q),
				SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_APPLICATIONS_Q)
			)));
			$form->addItem($table);
			$form->show();
		}
	}
	else if($_REQUEST["config"]==5){ /* Proxies */
		echo SBR;
		if(isset($_REQUEST["form"])){
			insert_proxies_form(get_request('hostid',NULL));
		} 
		else {		
			$numrows = new CSpan(null,'info');
			$numrows->addOption('name','numrows');
			$header = get_table_header(array(S_PROXIES_BIG,
							new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
							S_FOUND.': ',$numrows,)
							);							
			show_table_header($header);
			
			$form = new CForm('hosts.php');
			$form->setMethod('get');
			
			$form->setName('hosts');
			$form->addVar('config',get_request('config',0));

			$table = new CTableInfo(S_NO_PROXIES_DEFINED);

			$table->setHeader(array(
					array(new CCheckBox('all_hosts',NULL,"CheckAll('".$form->GetName()."','all_hosts');"),
						SPACE,
						make_sorting_link(S_NAME,'g.name')),
						S_LASTSEEN_AGE,
						' # ',
						S_MEMBERS
					));

			$db_proxies=DBselect('SELECT hostid,host,lastaccess '.
								' FROM hosts'.
								' WHERE status IN ('.HOST_STATUS_PROXY.') '.
									' AND '.DBin_node('hostid').
								order_by('host'));
					
			while($db_proxy=DBfetch($db_proxies)){
				$count = 0;
				$hosts = array();
				
				$sql = 'SELECT DISTINCT host,status '.
						' FROM hosts'.
						' WHERE proxy_hostid='.$db_proxy['hostid'].
							' AND '.DBcondition('hostid',$available_hosts).
							' AND status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
						' ORDER BY host';
				$db_hosts = DBselect($sql);
				while($db_host=DBfetch($db_hosts)){
					$style = ($db_host['status']==HOST_STATUS_MONITORED)?NULL:(($db_host['status']==HOST_STATUS_TEMPLATE)?'unknown' :'on');
					array_push($hosts, empty($hosts) ? '' : ', ', new CSpan($db_host['host'], $style));
					$count++;
				}

				if($db_proxy['lastaccess'] != 0)
					$lastclock = zbx_date2age($db_proxy['lastaccess']);
				else
					$lastclock = '-';

				$table->addRow(array(
					array(
						new CCheckBox('hosts['.$db_proxy['hostid'].']', NULL, NULL, $db_proxy['hostid']),
						SPACE,
						new CLink($db_proxy['host'],
								'hosts.php?form=update&hostid='.$db_proxy['hostid'].url_param('config'),
								'action')
					),
					$lastclock,
					$count,
					new CCol((empty($hosts)?'-':$hosts), 'wraptext')
					));
				$row_count++;
			}
			
			$table->setFooter(new CCol(array(
				new CButtonQMessage('activate',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_HOSTS_Q),
				SPACE,
				new CButtonQMessage('disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_HOSTS_Q),
				SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)
			)));

			$form->addItem($table);
			$form->show();
		}
	}
	else if($_REQUEST['config'] == 6){		if(isset($_REQUEST["form"])){
			$frmMaintenance = new CForm('hosts.php','post');
			$frmMaintenance->SetName(S_MAINTENANCE);
			
			$frmMaintenance->AddVar('form',get_request('form',1));
			
			$from_rfr = get_request('form_refresh',0);
			$frmMaintenance->AddVar('form_refresh',$from_rfr+1);
			
			$frmMaintenance->AddVar('config',get_request('config',6));
			
			if(isset($_REQUEST['maintenanceid']))
				$frmMaintenance->AddVar('maintenanceid',$_REQUEST['maintenanceid']);
						
			$left_tab = new CTable();
			$left_tab->SetCellPadding(3);
			$left_tab->SetCellSpacing(3);
			
			$left_tab->AddOption('border',0);
			
			$left_tab->AddRow(create_hat(
					S_MAINTENANCE,
					get_maintenance_form(),//null,
					null,
					'hat_maintenance',
					get_profile('web.hosts.hats.hat_maintenance.state',1)
				));
					
			$left_tab->AddRow(create_hat(
					S_MAINTENANCE_PERIODS,
					get_maintenance_periods(),//null
					null,
					'hat_timeperiods',
					get_profile('web.hosts.hats.hat_timeperiods.state',1)
				));
				
			if(isset($_REQUEST['new_timeperiod'])){
				$new_timeperiod = $_REQUEST['new_timeperiod'];

				$left_tab->AddRow(create_hat(
						(is_array($new_timeperiod) && isset($new_timeperiod['id']))?S_EDIT_MAINTENANCE_PERIOD:S_NEW_MAINTENANCE_PERIOD,
						get_timeperiod_form(),//nulls
						null,
						'hat_new_timeperiod',
						get_profile('web.actionconf.hats.hat_new_timeperiod.state',1)
					));
			}
			
			$right_tab = new CTable();
			$right_tab->SetCellPadding(3);
			$right_tab->SetCellSpacing(3);
			
			$right_tab->AddOption('border',0);
					
			$right_tab->AddRow(create_hat(
					S_HOSTS_IN_MAINTENANCE,
					get_maintenance_hosts_form($frmMaintenance),//null,
					null,
					'hat_host_link',
					get_profile('web.hosts.hats.hat_host_link.state',1)
				));
				
			$right_tab->AddRow(create_hat(
					S_GROUPS_IN_MAINTENANCE,
					get_maintenance_groups_form($frmMaintenance),//null,
					null,
					'hat_group_link',
					get_profile('web.hosts.hats.hat_group_link.state',1)
				));

			
			
			$td_l = new CCol($left_tab);
			$td_l->AddOption('valign','top');
			
			$td_r = new CCol($right_tab);
			$td_r->AddOption('valign','top');
			
			$outer_table = new CTable();
			$outer_table->AddOption('border',0);
			$outer_table->SetCellPadding(1);
			$outer_table->SetCellSpacing(1);
			$outer_table->AddRow(array($td_l,$td_r));
			
			$frmMaintenance->Additem($outer_table);
			
			show_messages();
			$frmMaintenance->Show();
//			insert_maintenance_form();
		} 
		else {
			echo SBR;
	// Table HEADER
			$form = new CForm();
			$form->SetMethod('get');
			
			$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
			$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
		
			foreach($PAGE_GROUPS['groups'] as $groupid => $name){
				$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid).$name);
			}
			foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
				$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid).$name);
			}
			
			$form->addItem(array(S_GROUP.SPACE,$cmbGroups));
			$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
			
			show_table_header(S_MAINTENANCE_PERIODS, $form);
// ----
			$available_maintenances = get_accessible_maintenance_by_user(PERM_READ_WRITE);

			$maintenances = array();
			$maintenanceids = array();

			$sql_from = '';
			$sql_where = '';
			if($_REQUEST['hostid']>0){
				$sql_from = ', maintenances_hosts mh, maintenances_groups mg, hosts_groups hg ';
				$sql_where = ' AND hg.hostid='.$_REQUEST['hostid'].
							' AND ('.
								'(mh.hostid=hg.hostid AND m.maintenanceid=mh.maintenanceid) '.
								' OR (mg.groupid=hg.groupid AND m.maintenanceid=mg.maintenanceid))';
			}
			else if($_REQUEST['groupid']>0){
				$sql_from = ', maintenances_hosts mh, maintenances_groups mg, hosts_groups hg ';
				$sql_where = ' AND hg.groupid='.$_REQUEST['groupid'].
							' AND ('.
								'(mg.groupid=hg.groupid AND m.maintenanceid=mg.maintenanceid) '.
								' OR (mh.hostid=hg.hostid AND m.maintenanceid=mh.maintenanceid))';
			}
			
			$sql = 'SELECT m.* '.
					' FROM maintenances m '.$sql_from.
					' WHERE '.DBin_node('m.maintenanceid').
						' AND '.DBcondition('m.maintenanceid',$available_maintenances).
						$sql_where.
					order_by('m.name');

			$db_maintenances = DBselect($sql);
			while($maintenance = DBfetch($db_maintenances)){
				$maintenances[$maintenance['maintenanceid']] = $maintenance;
				$maintenanceids[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];
			}
			
		
			$form = new CForm(null,'post');
			$form->SetName('maintenances');
			
			$table = new CTableInfo();
			$table->setHeader(array(
				array(
					new CCheckBox('all_maintenances',NULL,"CheckAll('".$form->GetName()."','all_maintenances','group_maintenanceid');"),
					make_sorting_link(S_NAME,'m.name')
				),
				S_TYPE,
				S_STATUS,
				S_DESCRIPTION
				));
				
			foreach($maintenances as $maintenanceid => $maintenance){
				
				if($maintenance['active_till'] < time()) $mnt_status = new CSpan(S_EXPIRED,'red');
				else $mnt_status = new CSpan(S_ACTIVE,'green');
				
				$table->addRow(array(
					array(
						new CCheckBox('maintenanceids['.$maintenance['maintenanceid'].']',NULL,NULL,$maintenance['maintenanceid']),
						new CLink($maintenance['name'],
							'hosts.php?form=update'.url_param('config').
							'&maintenanceid='.$maintenance['maintenanceid'].'#form', 'action')
					),
					$maintenance['maintenance_type']?S_NO_DATA_PROCESSING:S_NORMAL_PROCESSING,
					$mnt_status,
					$maintenance['description']
					));
			}
//			$table->SetFooter(new CCol(new CButtonQMessage('delete_selected',S_DELETE_SELECTED,S_DELETE_SELECTED_USERS_Q)));
			
			$table->SetFooter(new CCol(array(
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_GROUPS_Q)
			)));

			$form->AddItem($table);

			$form->show();
		}
	}

zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');

?>
<?php

include_once 'include/page_footer.php';

?>
