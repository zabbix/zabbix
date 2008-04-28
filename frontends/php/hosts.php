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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_HOSTS";
	$page["file"] = "hosts.php";
	$page['hist_arg'] = array('groupid','config','hostid');

include_once "include/page_header.php";

	$_REQUEST["config"] = get_request("config",get_profile("web.hosts.config",0));
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);
	if(isset($_REQUEST["hostid"]) && $_REQUEST["hostid"] > 0 && !uint_in_array($_REQUEST["hostid"], $available_hosts)) {
		access_deny();
	}
	if(isset($_REQUEST["apphostid"]) && $_REQUEST["apphostid"] > 0 && !uint_in_array($_REQUEST["apphostid"], $available_hosts)) {
		access_deny();
	}

	if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"] > 0){
		if(!uint_in_array($_REQUEST["groupid"], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))){
			access_deny();
		}
	}

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		// 0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications; 5 - Proxies
		"config"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2,3,4,5"),	NULL), 

/* ARAYS */
		"hosts"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		"groups"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		"applications"=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
/* host */
		"hostid"=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'isset({config})&&({config}==0||{config}==5||{config}==2)&&isset({form})&&({form}=="update")'),
		"host"=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==0||{config}==3||{config}==5)&&isset({save})'),
		'proxy_hostid'=>array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,		'isset({config})&&({config}==0)&&isset({save})'),
		"dns"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})'),
		"useip"=>	array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'(isset({config})&&({config}==0))&&isset({save})'),
		"ip"=>		array(T_ZBX_IP, O_OPT, NULL,	NULL,		'(isset({config})&&({config}==0))&&isset({save})'),
		"port"=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),'(isset({config})&&({config}==0))&&isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1,3"),	'(isset({config})&&({config}==0))&&isset({save})'),

		"newgroup"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		"templates"=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	NULL),
		"clear_templates"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,	NULL),

		"useprofile"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
		"devicetype"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"name"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"os"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"serialno"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"tag"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"macaddress"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"hardware"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"software"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"contact"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"location"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),
		"notes"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	'isset({useprofile})'),		
/* group */
		"groupid"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==1))&&(isset({form})&&({form}=="update"))'),
		"gname"=>	array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==1))&&isset({save})'),

/* application */
		"applicationid"=>array(T_ZBX_INT,O_OPT,	P_SYS,	DB_ID,		'(isset({config})&&({config}==4))&&(isset({form})&&({form}=="update"))'),
		"appname"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'(isset({config})&&({config}==4))&&isset({save})'),
		"apphostid"=>	array(T_ZBX_INT, O_OPT, NULL,	DB_ID.'{}>0',	'(isset({config})&&({config}==4))&&isset({save})'),
		"apptemplateid"=>array(T_ZBX_INT,O_OPT,	NULL,	DB_ID,	NULL),
		
/* host linkage form */
		"tname"=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'isset({config})&&({config}==2)&&isset({save})'),

/* actions */
		"activate"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),	
		"disable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),	

		"add_to_group"=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),	
		"delete_from_group"=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT, DB_ID, NULL),	

		"unlink"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),
		"unlink_and_clear"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,   NULL,	NULL),

		"save"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"clone"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete_and_clear"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),

/* other */
		"form"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder();
	
	if($_REQUEST["config"]==4)
		validate_group_with_host(PERM_READ_WRITE,array("always_select_first_host","only_current_node"),'web.last.conf.groupid', 'web.last.conf.hostid');
	else if($_REQUEST["config"]==0 || $_REQUEST["config"]==3)
		validate_group(PERM_READ_WRITE,array(),'web.last.conf.groupid');

	update_profile("web.hosts.config",$_REQUEST["config"]);
?>
<?php

/************ ACTIONS FOR HOSTS ****************/
// Original mod by scricca@vipsnet.net
// Modified by Aly
/* this code menages operations to unlink 1 template from multiple hosts */
	if ($_REQUEST["config"]==2 && (isset($_REQUEST["unlink"]))){
		$hosts = get_request("hosts",array());
		if(isset($_REQUEST["hostid"])){
			$templateid=$_REQUEST["hostid"];
			$result = false;

// Permission check			
			$hosts = array_intersect($hosts,$available_hosts);
//--
			DBstart();
			foreach($hosts as $id => $hostid){
				$result=unlink_template($hostid,$templateid);				
			}
			$result = DBend();
			
			show_messages($result, S_UNLINK_FROM_TEMPLATE, S_CANNOT_UNLINK_FROM_TEMPLATE);
			if($result){
				$host=get_host_by_hostid($templateid);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
					'Host ['.$host['host'].'] '.
					'Mass Linkage '.
					'Status ['.$host['status'].']');
			}
		
			unset($_REQUEST["unlink"]);
			unset($_REQUEST["hostid"]);
			unset($_REQUEST["form"]);
		}
	}
/* this code menages operations to link 1 template to multiple hosts */
	if($_REQUEST["config"]==2 && (isset($_REQUEST["save"]))){
		if(isset($_REQUEST['hostid'])){
			$hosts = get_request('hosts',array());
			$templateid=$_REQUEST['hostid'];
			$result = false;
// Permission check
			$tmp_hosts = array_diff($hosts,$available_hosts);
			$hosts = array_diff($hosts,$tmp_hosts);
			unset($tmp_hosts);
//--
			
			$template_name=DBfetch(DBselect('SELECT host FROM hosts WHERE hostid='.$templateid));			
			DBstart();
			foreach($hosts as $id => $hostid){
			
				$host_groups=array();
				$db_hosts_groups = DBselect('SELECT groupid FROM hosts_groups WHERE hostid='.$hostid);
				while($hg = DBfetch($db_hosts_groups)) $host_groups[] = $hg['groupid'];

				$host=get_host_by_hostid($hostid);
				
				$templates_tmp=get_templates_by_hostid($hostid);
				$templates_tmp[$templateid]=$template_name["host"];
				
				$result=update_host($hostid,
								$host["host"],$host["port"],$host["status"],$host["useip"],$host["dns"],
								$host["ip"],$host['proxy_hostid'],$templates_tmp,null,$host_groups);
			}
			$result = DBend();
			
			show_messages($result, S_LINK_TO_TEMPLATE, S_CANNOT_LINK_TO_TEMPLATE);
			if($result){
				$host=get_host_by_hostid($templateid);
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
					'Host ['.$host['host'].'] '.
					'Mass Linkage '.
					'Status ['.$host['status'].']');
			}

			unset($_REQUEST["save"]);
			unset($_REQUEST["hostid"]);
			unset($_REQUEST["form"]);
		}
	}
//---------  END MOD ------------
/* UNLINK HOST */
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (isset($_REQUEST["unlink"]) || isset($_REQUEST["unlink_and_clear"]))){
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		if(isset($_REQUEST["unlink"])){
			$unlink_templates = array_keys($_REQUEST["unlink"]);
		}
		else{
			$unlink_templates = array_keys($_REQUEST["unlink_and_clear"]);
			$_REQUEST['clear_templates'] = array_merge($_REQUEST['clear_templates'],$unlink_templates);
		}
		foreach($unlink_templates as $id) unset($_REQUEST['templates'][$id]);
	}
/* CLONE HOST */
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["clone"]) && isset($_REQUEST["hostid"])){
		unset($_REQUEST["hostid"]);
		$_REQUEST["form"] = "clone";
	}
/* SAVE HOST */
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["save"])){
		$useip = get_request("useip",0);
		$groups=get_request("groups",array());
		
		if(count($groups) > 0){
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY);
			foreach($groups as $gid){
				if(isset($accessible_groups[$gid])) continue;
				access_deny();
			}
		}
		else{
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
				access_deny();
		}

		$templates = get_request('templates', array());
		
		$_REQUEST['proxy_hostid'] = get_request('proxy_hostid',0);

		if(isset($_REQUEST['hostid'])){
			if(isset($_REQUEST['clear_templates'])) {
				foreach($_REQUEST['clear_templates'] as $id){
					unlink_template($_REQUEST["hostid"], $id, false);
				}
			}

			DBstart();
			update_host($_REQUEST["hostid"],
				$_REQUEST["host"],$_REQUEST["port"],$_REQUEST["status"],$useip,$_REQUEST["dns"],
				$_REQUEST["ip"],$_REQUEST["proxy_hostid"],$templates,$_REQUEST["newgroup"],$groups);
			$result = DBend();

			$msg_ok 	= S_HOST_UPDATED;
			$msg_fail 	= S_CANNOT_UPDATE_HOST;
			$audit_action 	= AUDIT_ACTION_UPDATE;

			$hostid = $_REQUEST["hostid"];
		} 
		else {
			DBstart();
			$hostid = add_host(
				$_REQUEST["host"],$_REQUEST["port"],$_REQUEST["status"],$useip,$_REQUEST["dns"],
				$_REQUEST["ip"],$_REQUEST["proxy_hostid"],$templates,$_REQUEST["newgroup"],$groups);
			$result	= DBend()?$hostid:false;
			
			$msg_ok 	= S_HOST_ADDED;
			$msg_fail 	= S_CANNOT_ADD_HOST;
			$audit_action 	= AUDIT_ACTION_ADD;
		}

		if($result){
			update_profile("HOST_PORT",$_REQUEST['port']);

			DBstart();
			delete_host_profile($hostid);
						
			if(get_request("useprofile","no") == "yes"){
				add_host_profile($hostid,
					$_REQUEST["devicetype"],$_REQUEST["name"],$_REQUEST["os"],
					$_REQUEST["serialno"],$_REQUEST["tag"],$_REQUEST["macaddress"],
					$_REQUEST["hardware"],$_REQUEST["software"],$_REQUEST["contact"],
					$_REQUEST["location"],$_REQUEST["notes"]);
			}
			$result = DBend();
		}

		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($audit_action,AUDIT_RESOURCE_HOST,
				"Host [".$_REQUEST["host"]."] IP [".$_REQUEST["ip"]."] ".
				"Status [".$_REQUEST["status"]."]");

			unset($_REQUEST["form"]);
			unset($_REQUEST["hostid"]);
		}
		unset($_REQUEST["save"]);
	}

/* DELETE HOST */ 
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (isset($_REQUEST["delete"]) || isset($_REQUEST["delete_and_clear"]))){
		$unlink_mode = false;
		if(isset($_REQUEST["delete"])){
			$unlink_mode =  true;
		}

		if(isset($_REQUEST["hostid"])){
			$host=get_host_by_hostid($_REQUEST["hostid"]);
			
			DBstart();
			delete_host($_REQUEST["hostid"], $unlink_mode);
			$result=DBend();

			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,
					"Host [".$host["host"]."]");

				unset($_REQUEST["form"]);
				unset($_REQUEST["hostid"]);
			}
		} 
		else {
/* group operations */
			$result = 0;
			$hosts = get_request("hosts",array());
			
			DBstart();
			$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
			while($db_host=DBfetch($db_hosts)){			
				$host=get_host_by_hostid($db_host["hostid"]);

				if(!uint_in_array($db_host["hostid"],$hosts)) continue;			
				$result=delete_host($db_host["hostid"], $unlink_mode);
				
				if($result)
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,"Host [".$host["host"]."]");
			}
			$result = DBend();
			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
		}
		unset($_REQUEST["delete"]);
	}
/* ACTIVATE / DISABLE HOSTS */
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (inarr_isset(array('add_to_group','hostid')))){
		global $USER_DETAILS;

		if(!uint_in_array($_REQUEST['add_to_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))){
			access_deny();
		}

		DBstart();
		add_host_to_group($_REQUEST['hostid'], $_REQUEST['add_to_group']);
		$result = DBend();
		
		show_messages($result,S_HOST_UPDATED,S_CANNOT_UPDATE_HOST);
	}
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (inarr_isset(array('delete_from_group','hostid')))){
		global $USER_DETAILS;

		if(!uint_in_array($_REQUEST['delete_from_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))){
			access_deny();
		}

		DBstart();
		delete_host_from_group($_REQUEST['hostid'], $_REQUEST['delete_from_group']);
		$result = DBend();
		
		show_messages($result, S_HOST_UPDATED, S_CANNOT_UPDATE_HOST);
	}
	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (isset($_REQUEST["activate"])||isset($_REQUEST["disable"]))){
	
		$result = 0;
		$status = isset($_REQUEST["activate"]) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request("hosts",array());

		$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
		DBstart();
		while($db_host=DBfetch($db_hosts)){		
			if(!uint_in_array($db_host["hostid"],$hosts)) continue;

			$host=get_host_by_hostid($db_host["hostid"]);
			$res=update_host_status($db_host["hostid"],$status);
			
			if($res){
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"Old status [".$host["status"]."] "."New status [".$status."]");
			}
		}
		$result = DBend();
		
		show_messages($result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
		unset($_REQUEST["activate"]);
	}

	else if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["chstatus"]) && isset($_REQUEST["hostid"])){
	
		$host=get_host_by_hostid($_REQUEST["hostid"]);
		
		update_host_status($_REQUEST["hostid"],$_REQUEST["chstatus"]);
		
		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		if($result){
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"Old status [".$host["status"]."] New status [".$_REQUEST["chstatus"]."]");
		}
		unset($_REQUEST["chstatus"]);
		unset($_REQUEST["hostid"]);
	}

/****** ACTIONS FOR GROUPS **********/
/* CLONE HOST */
	else if($_REQUEST["config"]==1 && isset($_REQUEST["clone"]) && isset($_REQUEST["groupid"])){
		unset($_REQUEST["groupid"]);
		$_REQUEST["form"] = "clone";
	}
	else if($_REQUEST["config"]==1&&isset($_REQUEST["save"])){
		$hosts = get_request("hosts",array());
		if(isset($_REQUEST["groupid"])){
			DBstart();
			$result = update_host_group($_REQUEST["groupid"], $_REQUEST["gname"], $hosts);
			$result = DBend();
			
			$action 	= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_GROUP_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_GROUP;
			$groupid = $_REQUEST["groupid"];
		} 
		else {
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
				access_deny();
			
			DBstart();
			$groupid = add_host_group($_REQUEST["gname"], $hosts);
			$result = DBend();
			
			$action 	= AUDIT_ACTION_ADD;
			$msg_ok		= S_GROUP_ADDED;
			$msg_fail	= S_CANNOT_ADD_GROUP;
		}
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_HOST_GROUP,S_HOST_GROUP." [".$_REQUEST["gname"]." ] [".$groupid."]");
			unset($_REQUEST["form"]);
		}
		unset($_REQUEST["save"]);
	}
	if($_REQUEST["config"]==1&&isset($_REQUEST["delete"])){
		if(isset($_REQUEST["groupid"])){
			$result = false;
			if($group = get_hostgroup_by_groupid($_REQUEST["groupid"])){
				DBstart();
				$result = delete_host_group($_REQUEST["groupid"]);
				$result = DBend();
			} 

			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,S_HOST_GROUP." [".$group["name"]." ] [".$group['groupid']."]");
			}
			
			unset($_REQUEST["form"]);

			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			unset($_REQUEST["groupid"]);
		} 
		else {
/* group operations */
			$groups = get_request("groups",array());

			$db_groups=DBselect('select groupid, name from groups where '.DBin_node('groupid'));
			
			DBstart();
			while($db_group=DBfetch($db_groups)){
				if(!uint_in_array($db_group["groupid"],$groups)) continue;
			
				if(!($group = get_hostgroup_by_groupid($db_group["groupid"]))) continue;
				$result = delete_host_group($db_group["groupid"]);
				
				if($result){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,
					S_HOST_GROUP." [".$group["name"]." ] [".$group['groupid']."]");
				}
			}
			$result = DBend();
			
			show_messages(true, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
		}
		unset($_REQUEST["delete"]);
	}

	if($_REQUEST["config"]==1&&(isset($_REQUEST["activate"])||isset($_REQUEST["disable"]))){
		$result = 0;
		$status = isset($_REQUEST["activate"]) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$groups = get_request("groups",array());

		$db_hosts=DBselect('select h.hostid, hg.groupid '.
			' from hosts_groups hg, hosts h'.
			' where h.hostid=hg.hostid '.
				' and h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' and '.DBin_node('h.hostid'));
		DBstart();
		while($db_host=DBfetch($db_hosts)){
			if(!uint_in_array($db_host["groupid"],$groups)) continue;
			$host=get_host_by_hostid($db_host["hostid"]);
			if(!update_host_status($db_host["hostid"],$status))	continue;

			$result = 1;
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
				"Old status [".$host["status"]."] "."New status [".$status."]");
		}
		$result = DBend();
		
		show_messages($result, S_HOST_STATUS_UPDATED, S_CANNOT_UPDATE_HOST);
		unset($_REQUEST["activate"]);
	}

	if($_REQUEST["config"]==4 && isset($_REQUEST["save"])){
		DBstart();
		if(isset($_REQUEST["applicationid"])){
			$result = update_application($_REQUEST["applicationid"],$_REQUEST["appname"], $_REQUEST["apphostid"]);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_APPLICATION_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_APPLICATION;
			$applicationid = $_REQUEST["applicationid"];
		} 
		else {
			$applicationid = add_application($_REQUEST["appname"], $_REQUEST["apphostid"]);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_APPLICATION_ADDED;
			$msg_fail	= S_CANNOT_ADD_APPLICATION;
		}
		$result = DBend();		
		
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_APPLICATION,S_APPLICATION." [".$_REQUEST["appname"]." ] [".$applicationid."]");
			unset($_REQUEST["form"]);
		}
		unset($_REQUEST["save"]);
	}
	else if($_REQUEST["config"]==4 && isset($_REQUEST["delete"])){
		if(isset($_REQUEST["applicationid"])){
			$result = false;
			if($app = get_application_by_applicationid($_REQUEST["applicationid"])){
				$host = get_host_by_hostid($app["hostid"]);
				
				DBstart();
				$result=delete_application($_REQUEST["applicationid"]);
				$result = DBend();
			}
			show_messages($result, S_APPLICATION_DELETED, S_CANNOT_DELETE_APPLICATION);
			
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,"Application [".$app["name"]."] from host [".$host["host"]."]");
			}
			unset($_REQUEST["form"]);
			unset($_REQUEST["applicationid"]);
		} 
		else {
/* group operations */
			$applications = get_request("applications",array());

			$db_applications = DBselect("select applicationid, name, hostid from applications ".
				'where '.DBin_node('applicationid'));

			DBstart();
			while($db_app = DBfetch($db_applications)){
				if(!uint_in_array($db_app["applicationid"],$applications))	continue;
				
				$result = delete_application($db_app["applicationid"]);

				if($result){
					$host = get_host_by_hostid($db_app["hostid"]);
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,"Application [".$db_app["name"]."] from host [".$host["host"]."]");
				}
			}
			$result = DBend();
			
			show_messages(true, S_APPLICATION_DELETED, NULL);
		}
		unset($_REQUEST["delete"]);
	}
	else if(($_REQUEST["config"]==4) &&(isset($_REQUEST["activate"])||isset($_REQUEST["disable"]))){
/* group operations */
		$result = true;
		$applications = get_request("applications",array());

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

					if(isset($_REQUEST["activate"])){
						if($result&=activate_item($item['itemid'])){
							$host = get_host_by_hostid($item['hostid']);
							add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM.' ['.$item['key_'].'] ['.$id.'] '.S_HOST.' ['.$host['host'].'] '.S_ITEMS_ACTIVATED);
						}
					}
					else{
						if($result&=disable_item($item['itemid'])){
							$host = get_host_by_hostid($item['hostid']);
							add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,S_ITEM." [".$item["key_"]."] [".$id."] ".S_HOST." [".$host['host']."] ".S_ITEMS_DISABLED);
						}
					}
			}
		}
		$result = DBend();
		(isset($_REQUEST["activate"]))?show_messages($result, S_ITEMS_ACTIVATED, null):show_messages($result, S_ITEMS_DISABLED, null);
	}
	else if($_REQUEST["config"]==5 && isset($_REQUEST["save"])){
		$hosts = get_request("hosts",array());
		
		DBstart();
		if(isset($_REQUEST["hostid"])){
			$result = update_proxy($_REQUEST["hostid"], $_REQUEST["host"], $hosts);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_PROXY_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_PROXY;
			$hostid		= $_REQUEST["hostid"];
		} 
		else {
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
				access_deny();
			
			$hostid		= add_proxy($_REQUEST["host"], $hosts);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_PROXY_ADDED;
			$msg_fail	= S_CANNOT_ADD_PROXY;
		}
		$result = DBend();
		
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_PROXY,"[".$_REQUEST["host"]." ] [".$hostid."]");
			unset($_REQUEST["form"]);
		}
		unset($_REQUEST["save"]);
	}
	else if($_REQUEST["config"]==5 && isset($_REQUEST["delete"])){
		$result = false;

		if(isset($_REQUEST["hostid"])){
			if($proxy = get_host_by_hostid($_REQUEST["hostid"])){
				DBstart();
				$result = delete_proxy($_REQUEST["hostid"]);
				$result = DBend();
			}
			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,"[".$proxy["host"]." ] [".$proxy['hostid']."]");
			}
			
			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
			unset($_REQUEST["form"]);
			unset($_REQUEST["hostid"]);
		} 
		else {
			$hosts = get_request("hosts",array());

			foreach($hosts as $hostid){
				$proxy = get_host_by_hostid($hostid);
				
				DBstart();
				$result = delete_proxy($hostid);
				$result = DBend();
				
				if(!$result) break;
				
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_PROXY,	"[".$proxy["host"]." ] [".$proxy['hostid']."]");
			}
			
			show_messages($result, S_PROXY_DELETED, S_CANNOT_DELETE_PROXY);
		}
		unset($_REQUEST["delete"]);
	}
	else if($_REQUEST["config"]==5 && isset($_REQUEST["clone"]) && isset($_REQUEST["hostid"])){
		unset($_REQUEST["hostid"]);
		$_REQUEST["form"] = "clone";
	}
	else if($_REQUEST["config"]==5 && (isset($_REQUEST["activate"]) || isset($_REQUEST["disable"]))){
		$result = false;
		
		$status = isset($_REQUEST["activate"]) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request("hosts",array());

		DBstart();
		foreach($hosts as $hostid){
			$db_hosts = DBselect('SELECT  hostid,status '.
								' FROM hosts '.
								' WHERE proxy_hostid='.$hostid.
									' AND '.DBin_node('hostid'));
									
			while($db_host = DBfetch($db_hosts)){
				$old_status = $db_host["status"];
				if($old_status == $status) continue;

				$result = update_host_status($db_host["hostid"], $status);
				if(!$result) continue;

				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"Old status [".$old_status."] "."New status [".$status."] [".$db_host["hostid"]."]");
			}
		}
		$result = DBend();
		
		show_messages($result, S_HOST_STATUS_UPDATED, NULL);

		if(isset($_REQUEST["activate"]))
			unset($_REQUEST["activate"]);
		else
			unset($_REQUEST["disable"]);
	}


	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,null,AVAILABLE_NOCACHE); /* update available_hosts after ACTIONS */
?>
<?php
	$frmForm = new CForm();
	$frmForm->SetMethod('get');
	
	$cmbConf = new CComboBox("config",$_REQUEST["config"],"submit()");
	$cmbConf->AddItem(0,S_HOSTS);
	$cmbConf->AddItem(3,S_TEMPLATES);
	$cmbConf->AddItem(5,S_PROXIES);
	$cmbConf->AddItem(1,S_HOST_GROUPS);
	$cmbConf->AddItem(2,S_TEMPLATE_LINKAGE);
	$cmbConf->AddItem(4,S_APPLICATIONS);

	switch($_REQUEST["config"]){
		case 0:
			$btn = new CButton("form",S_CREATE_HOST);
			$frmForm->AddVar("groupid",get_request("groupid",0));
			break;
		case 3:
			$btn = new CButton("form",S_CREATE_TEMPLATE);
			$frmForm->AddVar("groupid",get_request("groupid",0));
			break;
		case 5:
			$btn = new CButton("form",S_CREATE_PROXY);
			break;
		case 1: 
			$btn = new CButton("form",S_CREATE_GROUP);
			break;
		case 4: 
			$btn = new CButton("form",S_CREATE_APPLICATION);
			$frmForm->AddVar("hostid",get_request("hostid",0));
			break;
		case 2: 
			break;
	}

	$frmForm->AddItem($cmbConf);
	if(isset($btn)){
		$frmForm->AddItem(SPACE."|".SPACE);
		$frmForm->AddItem($btn);
	}
	show_table_header(S_CONFIGURATION_OF_HOSTS_GROUPS_AND_TEMPLATES, $frmForm);
	echo SBR;
?>
<?php
	if($_REQUEST["config"]==0 || $_REQUEST["config"]==3){
		$show_only_tmp = 0;
		if($_REQUEST["config"]==3)
			$show_only_tmp = 1;

		if(isset($_REQUEST["form"])){
			insert_host_form($show_only_tmp);
		}
		else {
			if($show_only_tmp==1)
				$status_filter = ' AND h.status IN ('.HOST_STATUS_TEMPLATE.') ';
			else
				$status_filter = ' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') ';
				
			$cmbGroups = new CComboBox("groupid",get_request("groupid",0),"submit()");
			$cmbGroups->AddItem(0,S_ALL_SMALL);

			$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
							' FROM groups g,hosts_groups hg,hosts h '.
							' WHERE '.DBcondition('h.hostid',$available_hosts).
								' AND g.groupid=hg.groupid '.
								' AND h.hostid=hg.hostid'.
								$status_filter.
							' ORDER BY g.name');
			while($row=DBfetch($result)){
				$cmbGroups->AddItem($row["groupid"],$row["name"]);
				if((bccomp($row["groupid"], $_REQUEST["groupid"]) == 0)) $correct_host = 1;
			}
			
			if(!isset($correct_host)){
				$_REQUEST["groupid"] = 0;
				$cmbGroups->SetValue($_REQUEST["groupid"]);
			}

			$frmForm = new CForm();
			$frmForm->SetMethod('get');

			$frmForm->AddVar("config",$_REQUEST["config"]);
			$frmForm->AddItem(S_GROUP.SPACE);
			$frmForm->AddItem($cmbGroups);
			show_table_header($show_only_tmp ? S_TEMPLATES_BIG : S_HOSTS_BIG, $frmForm);

	/* table HOSTS */
			
			if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]==0) unset($_REQUEST["groupid"]);

			$form = new CForm();
			
			$form->SetName('hosts');
			$form->AddVar("config",get_request("config",0));

			$table = new CTableInfo(S_NO_HOSTS_DEFINED);
			$table->setHeader(array(
				array(new CCheckBox("all_hosts",NULL,"CheckAll('".$form->GetName()."','all_hosts');"),
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
		
			$sql='SELECT h.* FROM ';
				
			if(isset($_REQUEST["groupid"])){
				$sql.= ' hosts h,hosts_groups hg ';
				$sql.= ' WHERE hg.groupid='.$_REQUEST['groupid'].
							' AND hg.hostid=h.hostid '.
							' AND';
							
			} 
			else{  
				$sql.= ' hosts h WHERE ';
			}
			
			$sql.= DBcondition('h.hostid',$available_hosts).
				$status_filter.
				order_by('h.host,h.port,h.ip,h.status,h.available,h.dns');

			$result=DBselect($sql);
			while($row=DBfetch($result)){
				$description = array();

				if($row["proxy_hostid"]){
					$proxy = get_host_by_hostid($row["proxy_hostid"]);
					array_push($description,$proxy["host"],":");
				}
			
				array_push($description, new CLink($row["host"], "hosts.php?form=update&hostid=".$row["hostid"].url_param("groupid").url_param("config"), 'action'));

				$add_to = array();
				$delete_from = array();

				$templates = get_templates_by_hostid($row["hostid"]);
				
				$host=new CCol(array(
					new CCheckBox("hosts[]",NULL,NULL,$row["hostid"]),
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
					$port = empty($row['port'])?'-':$row["port"];

					if(1 == $row['useip'])
						$ip = bold($ip);
					else
						$dns = bold($dns);
					
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

					$row["error"] = trim($row["error"]);
					if(empty($row["error"]))
						$error = new CCol('-',"off");
					else 
						$error = new CCol($row["error"],"on");

				}

				$popup_menu_actions = array(
					array(S_SHOW, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))),
					array(S_ITEMS, 'items.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					array(S_TRIGGERS, 'triggers.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					array(S_GRAPHS, 'graphs.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					);

				$db_groups = DBselect('SELECT g.groupid, g.name '.
						' FROM groups g '.
							' LEFT JOIN hosts_groups hg on g.groupid=hg.groupid and hg.hostid='.$row['hostid'].
						' WHERE hostid is NULL '.
						' ORDER BY g.name,g.groupid');
				while($group_data = DBfetch($db_groups))
				{
					$add_to[] = array($group_data['name'], '?'.
							url_param($group_data['groupid'], false, 'add_to_group').
							url_param($row['hostid'], false, 'hostid')
							);
				}

				$db_groups = DBselect('select g.groupid, g.name from groups g, hosts_groups hg '.
						' where g.groupid=hg.groupid and hg.hostid='.$row['hostid'].
						' order by g.name,g.groupid');
						
				while($group_data = DBfetch($db_groups)){
					$delete_from[] = array($group_data['name'], '?'.
							url_param($group_data['groupid'], false, 'delete_from_group').
							url_param($row['hostid'], false, 'hostid')
							);
				}

				if(count($add_to) > 0 || count($delete_from) > 0){
					$popup_menu_actions[] = array(S_GROUPS, null, null,
						array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
				}
				
				if(count($add_to) > 0){
					$popup_menu_actions[] = array_merge(array(S_ADD_TO_GROUP, null, null, 
						array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))), $add_to);
				}
				
				if(count($delete_from) > 0){
					$popup_menu_actions[] = array_merge(array(S_DELETE_FROM_GROUP, null, null, 
						array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))), $delete_from);
				}

				$mnuActions = new CPUMenu($popup_menu_actions);

				$show = new CLink(S_SELECT, '#', 'action', $mnuActions->GetOnActionJS());

				$table->addRow(array(
					$host,
					$dns,
					$ip,
					$port,
					empty($templates)?'-':implode(', ',$templates),
					$status,
					$available,
					$error,
					$show));
			}

			$footerButtons = array(
				$show_only_tmp ? NULL : new CButtonQMessage('activate',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_HOSTS_Q),
				$show_only_tmp ? NULL : SPACE,
				$show_only_tmp ? NULL : new CButtonQMessage('disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_HOSTS_Q),
				$show_only_tmp ? NULL : SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_HOSTS_Q),
				$show_only_tmp ? SPACE : NULL,
				$show_only_tmp ? new CButtonQMessage('delete_and_clear',S_DELETE_SELECTED_WITH_LINKED_ELEMENTS,S_DELETE_SELECTED_HOSTS_Q) : NULL
				);

			$table->SetFooter(new CCol($footerButtons));

			$form->AddItem($table);
			$form->Show();

		}
	}
	else if($_REQUEST["config"]==1){
		if(isset($_REQUEST["form"]))
		{
			insert_hostgroups_form(get_request("groupid",NULL));
		} 
		else {
			show_table_header(S_HOST_GROUPS_BIG);

			$form = new CForm('hosts.php');
			$form->SetMethod('get');
			
			$form->SetName('groups');
			$form->AddVar("config",get_request("config",0));

			$table = new CTableInfo(S_NO_HOST_GROUPS_DEFINED);

			$table->setHeader(array(
				array(	new CCheckBox("all_groups",NULL,
						"CheckAll('".$form->GetName()."','all_groups');"),
					SPACE,
					make_sorting_link(S_NAME,'g.name')),
				' # ',
				S_MEMBERS));

			$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);

			$db_groups=DBselect('SELECT g.groupid,g.name '.
							' FROM groups g'.
							' WHERE g.groupid in ('.$available_groups.')'.
							order_by('g.name'));
			while($db_group=DBfetch($db_groups)){
				$db_hosts = DBselect('SELECT DISTINCT h.host, h.status'.
						' FROM hosts h, hosts_groups hg'.
						' WHERE h.hostid=hg.hostid '.
						' AND hg.groupid='.$db_group['groupid'].
						' AND '.DBcondition('h.hostid',$available_hosts).
						' AND h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.') '.
						' order by host'
						);

				$hosts = array();
				$count = 0;
				while($db_host=DBfetch($db_hosts)){
					$style = $db_host["status"]==HOST_STATUS_MONITORED ? NULL: ( 
						$db_host["status"]==HOST_STATUS_TEMPLATE ? "unknown" :
						"on");

					array_push($hosts, empty($hosts) ? '' : ', ', new CSpan($db_host["host"], $style));
					$count++;
				}

				$table->AddRow(array(
					array(
						new CCheckBox("groups[]",NULL,NULL,$db_group["groupid"]),
						SPACE,
						new CLink(
							$db_group["name"],
							"hosts.php?form=update&groupid=".$db_group["groupid"].
							url_param("config"),'action')
					),
					$count,
					empty($hosts)?'-':$hosts
					));
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
	else if($_REQUEST["config"]==2){
	
		if(isset($_REQUEST["form"])){
			insert_template_form(get_request("hostid",NULL));
		} 
		else{
	
			show_table_header(S_TEMPLATE_LINKAGE_BIG);
		
			$table = new CTableInfo(S_NO_LINKAGES);
			$table->SetHeader(array(S_TEMPLATES,S_HOSTS));
		
			$templates = DBSelect('SELECT h.* FROM hosts h'.
					' WHERE h.status='.HOST_STATUS_TEMPLATE.
						' AND '.DBcondition('h.hostid',$available_hosts).
					' ORDER BY h.host');
			while($template = DBfetch($templates)){
			
				$hosts = DBSelect('SELECT h.* '.
					' FROM hosts h, hosts_templates ht '.
					' WHERE ht.templateid='.$template['hostid'].
						' AND ht.hostid=h.hostid '.
						' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
						' AND '.DBcondition('h.hostid',$available_hosts).
					' ORDER BY host');
				$host_list = array();
				while($host = DBfetch($hosts)){
					$style = ($host["status"] == HOST_STATUS_MONITORED)?NULL:'on';
					array_push($host_list, empty($host_list) ? '' : ', ', new CSpan($host["host"], $style));
				}
				$table->AddRow(array(		
					new CCol(array(
						new CLink($template['host'],'hosts.php?form=update&hostid='.
							$template['hostid'].url_param('hostid').url_param('config'), 'action')
						),'unknown'),
					empty($host_list)?'-':$host_list
				));
			}
			
			$table->Show();
		}
//----- END MODE -----
	}
	else if($_REQUEST["config"]==4){
		if(isset($_REQUEST["form"])){
			insert_application_form();
		} 
		else {
	// Table HEADER
			$form = new CForm();
			$form->SetMethod('get');
			
			$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
			$cmbGroup->AddItem(0,S_ALL_SMALL);

			$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
						' FROM groups g,hosts_groups hg '.
						' WHERE g.groupid=hg.groupid '.
							' AND '.DBcondition('hg.hostid',$available_hosts).
							' ORDER BY name');
							
			while($row=DBfetch($result)){
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
			}
			
			$form->AddItem(S_GROUP.SPACE);
			$form->AddItem($cmbGroup);

			if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]>0){
				$sql='SELECT DISTINCT h.hostid,h.host '.
					' FROM hosts h,hosts_groups hg '.
					' WHERE hg.groupid='.$_REQUEST['groupid'].
						' AND hg.hostid=h.hostid '.
						' AND '.DBcondition('h.hostid',$available_hosts).
//						' AND h.status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
					' GROUP BY h.hostid,h.host '.
					' ORDER BY h.host';
			}
			else{
				$sql='SELECT DISTINCT h.hostid,h.host '.
					' FROM hosts h '.
					' WHERE '.DBcondition('h.hostid',$available_hosts).
//						' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.') '.
						' GROUP BY h.hostid,h.host '.
						' ORDER BY h.host';
			}
			$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

			$result=DBselect($sql);
			while($row=DBfetch($result)){
				$cmbHosts->AddItem($row["hostid"],$row["host"]);
			}

			$form->AddItem(SPACE.S_HOST.SPACE);
			$form->AddItem($cmbHosts);
			
			show_table_header(S_APPLICATIONS_BIG, $form);

/* TABLE */

			$form = new CForm();
			$form->SetName('applications');

			$table = new CTableInfo();
			$table->SetHeader(array(
				array(new CCheckBox("all_applications",NULL,"CheckAll('".$form->GetName()."','all_applications');"),
				SPACE,
				make_sorting_link(S_APPLICATION,'a.name')),
				S_SHOW
				));

			$db_applications = DBselect('SELECT a.* '.
									' FROM applications a'.
									' WHERE a.hostid='.$_REQUEST['hostid'].
									order_by('a.name'));
									
			while($db_app = DBfetch($db_applications))
			{
				if($db_app["templateid"]==0)
				{
					$name = new CLink(
						$db_app["name"],
						"hosts.php?form=update&applicationid=".$db_app["applicationid"].
						url_param("config"),'action');
				} else {
					$template_host = get_realhost_by_applicationid($db_app["templateid"]);
					$name = array(		
						new CLink($template_host["host"],
							"hosts.php?hostid=".$template_host["hostid"].url_param("config"),
							'action'),
						":",
						$db_app["name"]
						);
				}
				$items=get_items_by_applicationid($db_app["applicationid"]);
				$rows=0;
				while(DBfetch($items))	$rows++;


				$table->AddRow(array(
					array(new CCheckBox("applications[]",NULL,NULL,$db_app["applicationid"]),SPACE,$name),
					array(new CLink(S_ITEMS,"items.php?hostid=".$db_app["hostid"],"action"),
					SPACE."($rows)")
					));
			}
			$table->SetFooter(new CCol(array(
				new CButtonQMessage('activate',S_ACTIVATE_ITEMS,S_ACTIVATE_ITEMS_FROM_SELECTED_APPLICATIONS_Q),
				SPACE,
				new CButtonQMessage('disable',S_DISABLE_ITEMS,S_DISABLE_ITEMS_FROM_SELECTED_APPLICATIONS_Q),
				SPACE,
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_APPLICATIONS_Q)
			)));
			$form->AddItem($table);
			$form->Show();
		}
	}
	else if($_REQUEST["config"]==5) /* Proxies */
	{
		if(isset($_REQUEST["form"]))
		{
			insert_proxies_form(get_request("hostid",NULL));
		} else {
			show_table_header(S_PROXIES_BIG);

			$form = new CForm('hosts.php');
			$form->SetMethod('get');
			
			$form->SetName('hosts');
			$form->AddVar("config",get_request("config",0));

			$table = new CTableInfo(S_NO_PROXIES_DEFINED);

			$table->setHeader(array(
				array(	new CCheckBox("all_hosts",NULL,
						"CheckAll('".$form->GetName()."','all_hosts');"),
					SPACE,
					make_sorting_link(S_NAME,'g.name')),
				' # ',
				S_MEMBERS,S_LASTSEEN_AGE));

			$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE);

			$db_proxies=DBselect('select hostid,host,lastaccess from hosts'.
					' where status in ('.HOST_STATUS_PROXY.') and '.DBin_node('hostid').
					order_by('host'));
			while($db_proxy=DBfetch($db_proxies))
			{
				$db_hosts = DBselect('SELECT DISTINCT host,status'.
						' FROM hosts'.
						' WHERE proxy_hostid='.$db_proxy['hostid'].
							' AND '.DBcondition('hostid',$available_hosts).
							' and status in ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
						' order by host'
						);

				$hosts = array();
				$count = 0;
				while($db_host=DBfetch($db_hosts)){
					$style = $db_host["status"]==HOST_STATUS_MONITORED ? NULL: ( 
						$db_host["status"]==HOST_STATUS_TEMPLATE ? "unknown" :
						"on");
					array_push($hosts, empty($hosts) ? '' : ',', new CSpan($db_host["host"], $style));
					$count++;
				}

				if($db_proxy['lastaccess'] != 0)
					$lastclock = zbx_date2age($db_proxy['lastaccess']);
				else
					$lastclock = new CCol('-', 'center');

				$table->AddRow(array(
					array(
						new CCheckBox("hosts[]", NULL, NULL, $db_proxy["hostid"]),
						SPACE,
						new CLink($db_proxy["host"],
								"hosts.php?form=update&hostid=".$db_proxy["hostid"].url_param("config"),
								'action')
					),
					$count,
					$hosts,
					$lastclock
					));
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
?>
<?php

include_once "include/page_footer.php";

?>
