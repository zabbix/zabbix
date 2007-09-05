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
	require_once "include/hosts.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_HOSTS";
	$page["file"] = "hosts.php";

include_once "include/page_header.php";

	$_REQUEST["config"] = get_request("config",get_profile("web.hosts.config",0));
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,PERM_RES_IDS_ARRAY,get_current_nodeid());
	if(isset($_REQUEST["hostid"]) && $_REQUEST["hostid"] > 0 && !in_array($_REQUEST["hostid"], $available_hosts)) 
	{
		access_deny();
	}
	if(isset($_REQUEST["apphostid"]) && $_REQUEST["apphostid"] > 0 && !in_array($_REQUEST["apphostid"], $available_hosts)) 
	{
		access_deny();
	}

	if(count($available_hosts) == 0) $available_hosts = array(-1);
	$available_hosts = implode(',', $available_hosts);

	if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"] > 0)
	{
		if(!in_array($_REQUEST["groupid"], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,null,
			PERM_RES_IDS_ARRAY,get_current_nodeid())))
		{
			access_deny();
		}
	}

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		// 0 - hosts; 1 - groups; 2 - linkages; 3 - templates; 4 - applications
		"config"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1,2,3,4"),	NULL), 

/* ARAYS */
		"hosts"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		"groups"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
		"applications"=>array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, NULL),
/* agent control */
		"command"=>	array(T_ZBX_STR, O_OPT, NULL,   NULL,	NULL),
/* host */
		"hostid"=>	array(T_ZBX_INT, O_OPT,	P_SYS,  DB_ID,		'{config}==0&&{form}=="update"'),
		"host"=>	array(T_ZBX_STR, O_OPT,	NULL,   NOT_EMPTY,	'({config}==0||{config}==3)&&isset({save})'),
		"dns"=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'{config}==0&&isset({save})'),
		"useip"=>	array(T_ZBX_STR, O_OPT, NULL,	IN('0,1'),	'{config}==0&&isset({save})'),
		"ip"=>		array(T_ZBX_IP, O_OPT, NULL,	NULL,		'{config}==0&&isset({save})'),
		"port"=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535),'{config}==0&&isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,	NULL,	IN("0,1,3"),	'{config}==0&&isset({save})'),

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
		"groupid"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'{config}==1&&{form}=="update"'),
		"gname"=>	array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'{config}==1&&isset({save})'),

/* application */
		"applicationid"=>array(T_ZBX_INT,O_OPT,	P_SYS,	DB_ID,		'{config}==4&&{form}=="update"'),
		"appname"=>	array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'{config}==4&&isset({save})'),
		"apphostid"=>	array(T_ZBX_INT, O_OPT, NULL,	DB_ID.'{}>0',	'{config}==4&&isset({save})'),
		"apptemplateid"=>array(T_ZBX_INT,O_OPT,	NULL,	DB_ID,	NULL),

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

	$_REQUEST["config"] = get_request("config",get_profile("web.host.config",0));

	check_fields($fields);

	if($_REQUEST["config"]==4)
		validate_group_with_host(PERM_READ_WRITE,array("always_select_first_host"),'web.last.conf.groupid', 'web.last.conf.hostid');
	elseif($_REQUEST["config"]==0 || $_REQUEST["config"]==3)
		validate_group(PERM_READ_WRITE,array(),'web.last.conf.groupid');

	update_profile("web.hosts.config",$_REQUEST["config"]);
?>
<?php
/************ ACTIONS FOR HOSTS ****************/
/* AGENT CONTROL */
	if(isset($_REQUEST["command"]) && isset($_REQUEST["hostid"]) && defined('ZBX_AGENT_CONTROL_SCRIPT'))
	{
		$row=DBfetch(DBselect('select dns,ip,useip from hosts where hostid='.$_REQUEST["hostid"]));

		if($row)
		{
			$host=($row["useip"]==1)?$row["ip"]:$row["dns"];
			$f=popen(ZBX_AGENT_CONTROL_SCRIPT." $host ".$_REQUEST["command"],'r');
			$data=fread($f,1024);
			show_messages(TRUE, S_COMMAND_WAS_EXECUTED, S_FAIL);
		}
		
	}
/* UNLINK HOST */
	if(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (isset($_REQUEST["unlink"]) || isset($_REQUEST["unlink_and_clear"])))
	{
		$_REQUEST['clear_templates'] = get_request('clear_templates', array());
		if(isset($_REQUEST["unlink"]))
		{
			$unlink_templates = array_keys($_REQUEST["unlink"]);
		}
		else
		{
			$unlink_templates = array_keys($_REQUEST["unlink_and_clear"]);
			$_REQUEST['clear_templates'] = array_merge($_REQUEST['clear_templates'],$unlink_templates);
		}
		foreach($unlink_templates as $id) unset($_REQUEST['templates'][$id]);
	}
/* CLONE HOST */
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["clone"]) && isset($_REQUEST["hostid"]))
	{
		unset($_REQUEST["hostid"]);
		$_REQUEST["form"] = "clone";
	}
/* SAVE HOST */
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["save"]))
	{
		$useip = get_request("useip",0);

		$groups=get_request("groups",array());
		
		if(count($groups) > 0)
		{
			$accessible_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,null,PERM_RES_IDS_ARRAY);
			foreach($groups as $gid)
			{
				if(isset($accessible_groups[$gid])) continue;
				access_deny();
			}
		}
		else
		{
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
				access_deny();

		}

		$templates = get_request('templates', array());

		if(isset($_REQUEST["hostid"]))
		{
			if(isset($_REQUEST['clear_templates'])) 
			{
				foreach($_REQUEST['clear_templates'] as $id)
				{
					unlink_template($_REQUEST["hostid"], $id, false);
				}
			}

			$result = update_host($_REQUEST["hostid"],
				$_REQUEST["host"],$_REQUEST["port"],$_REQUEST["status"],$useip,$_REQUEST["dns"],
				$_REQUEST["ip"],$templates,$_REQUEST["newgroup"],$groups);

			$msg_ok 	= S_HOST_UPDATED;
			$msg_fail 	= S_CANNOT_UPDATE_HOST;
			$audit_action 	= AUDIT_ACTION_UPDATE;

			$hostid = $_REQUEST["hostid"];
		} else {
			$hostid = add_host(
				$_REQUEST["host"],$_REQUEST["port"],$_REQUEST["status"],$useip,$_REQUEST["dns"],
				$_REQUEST["ip"],$templates,$_REQUEST["newgroup"],$groups);

			$msg_ok 	= S_HOST_ADDED;
			$msg_fail 	= S_CANNOT_ADD_HOST;
			$audit_action 	= AUDIT_ACTION_ADD;

			$result		= $hostid;
		}

		if($result){
			delete_host_profile($hostid);

			if(get_request("useprofile","no") == "yes"){
				$result = add_host_profile($hostid,
					$_REQUEST["devicetype"],$_REQUEST["name"],$_REQUEST["os"],
					$_REQUEST["serialno"],$_REQUEST["tag"],$_REQUEST["macaddress"],
					$_REQUEST["hardware"],$_REQUEST["software"],$_REQUEST["contact"],
					$_REQUEST["location"],$_REQUEST["notes"]);
			}
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
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && (isset($_REQUEST["delete"]) || isset($_REQUEST["delete_and_clear"])))
	{
		$unlink_mode = false;
		if(isset($_REQUEST["delete"]))
		{
			$unlink_mode =  true;
		}

		if(isset($_REQUEST["hostid"])){
			$host=get_host_by_hostid($_REQUEST["hostid"]);
			$result=delete_host($_REQUEST["hostid"], $unlink_mode);

			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,
					"Host [".$host["host"]."]");

				unset($_REQUEST["form"]);
				unset($_REQUEST["hostid"]);
			}
		} else {
/* group operations */
			$result = 0;
			$hosts = get_request("hosts",array());
			$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
			while($db_host=DBfetch($db_hosts))
			{
				$host=get_host_by_hostid($db_host["hostid"]);

				if(!in_array($db_host["hostid"],$hosts)) continue;
				if(!delete_host($db_host["hostid"], $unlink_mode))	continue;
				$result = 1;

				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,
					"Host [".$host["host"]."]");
			}
			show_messages($result, S_HOST_DELETED, NULL);
		}
		unset($_REQUEST["delete"]);
	}
/* ACTIVATE / DISABLE HOSTS */
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && 
		(inarr_isset(array('add_to_group','hostid'))))
	{
		global $USER_DETAILS;

		if(!in_array($_REQUEST['add_to_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,null,
			PERM_RES_IDS_ARRAY,get_current_nodeid())))
		{
			access_deny();
		}

		show_messages(
			add_host_to_group($_REQUEST['hostid'], $_REQUEST['add_to_group']),
			S_HOST_UPDATED,
			S_CANNOT_UPDATE_HOST);
	}
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && 
		(inarr_isset(array('delete_from_group','hostid'))))
	{
		global $USER_DETAILS;

		if(!in_array($_REQUEST['delete_from_group'], get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,null,
			PERM_RES_IDS_ARRAY,get_current_nodeid())))
		{
			access_deny();
		}

		if( delete_host_from_group($_REQUEST['hostid'], $_REQUEST['delete_from_group']) )
		{
			show_messages(true, S_HOST_UPDATED);
		}
	}
	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && 
		(isset($_REQUEST["activate"])||isset($_REQUEST["disable"])))
	{
		$result = 0;
		$status = isset($_REQUEST["activate"]) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$hosts = get_request("hosts",array());

		$db_hosts=DBselect('select hostid from hosts where '.DBin_node('hostid'));
		while($db_host=DBfetch($db_hosts))
		{
			if(!in_array($db_host["hostid"],$hosts)) continue;
			$host=get_host_by_hostid($db_host["hostid"]);
			$res=update_host_status($db_host["hostid"],$status);

			$result = 1;
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
				"Old status [".$host["status"]."] "."New status [".$status."]");
		}
		show_messages($result, S_HOST_STATUS_UPDATED, NULL);
		unset($_REQUEST["activate"]);
	}

	elseif(($_REQUEST["config"]==0 || $_REQUEST["config"]==3) && isset($_REQUEST["chstatus"])
		&& isset($_REQUEST["hostid"]))
	{
		$host=get_host_by_hostid($_REQUEST["hostid"]);
		$result=update_host_status($_REQUEST["hostid"],$_REQUEST["chstatus"]);
		show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
				"Old status [".$host["status"]."] New status [".$_REQUEST["chstatus"]."]");
		}
		unset($_REQUEST["chstatus"]);
		unset($_REQUEST["hostid"]);
	}

/****** ACTIONS FOR GROUPS **********/
/* CLONE HOST */
	elseif($_REQUEST["config"]==1 && isset($_REQUEST["clone"]) && isset($_REQUEST["groupid"]))
	{
		unset($_REQUEST["groupid"]);
		$_REQUEST["form"] = "clone";
	}
	elseif($_REQUEST["config"]==1&&isset($_REQUEST["save"]))
	{
		$hosts = get_request("hosts",array());
		if(isset($_REQUEST["groupid"]))
		{
			$result = update_host_group($_REQUEST["groupid"], $_REQUEST["gname"], $hosts);
			$action 	= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_GROUP_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_GROUP;
			$groupid = $_REQUEST["groupid"];
		} else {
			if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
				access_deny();

			$groupid = add_host_group($_REQUEST["gname"], $hosts);
			$action 	= AUDIT_ACTION_ADD;
			$msg_ok		= S_GROUP_ADDED;
			$msg_fail	= S_CANNOT_ADD_GROUP;
			$result = $groupid;
		}
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_HOST_GROUP,S_HOST_GROUP." [".$_REQUEST["gname"]." ] [".$groupid."]");
			unset($_REQUEST["form"]);
		}
		unset($_REQUEST["save"]);
	}
	if($_REQUEST["config"]==1&&isset($_REQUEST["delete"]))
	{
		if(isset($_REQUEST["groupid"])){
			$result = false;
			if($group = get_hostgroup_by_groupid($_REQUEST["groupid"]))
			{
				$result = delete_host_group($_REQUEST["groupid"]);
			} 

			if($result){
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,
					S_HOST_GROUP." [".$group["name"]." ] [".$group['groupid']."]");
			}
			
			unset($_REQUEST["form"]);

			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			unset($_REQUEST["groupid"]);
		} else {
/* group operations */
			$result = 0;
			$groups = get_request("groups",array());

			$db_groups=DBselect('select groupid, name from groups where '.DBin_node('groupid'));
			while($db_group=DBfetch($db_groups))
			{
				if(!in_array($db_group["groupid"],$groups)) continue;
			
				if(!($group = get_hostgroup_by_groupid($db_group["groupid"]))) continue;

				if(!delete_host_group($db_group["groupid"])) continue

				$result = 1;

				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST_GROUP,
					S_HOST_GROUP." [".$group["name"]." ] [".$group['groupid']."]");
			}
			show_messages($result, S_GROUP_DELETED, NULL);
		}
		unset($_REQUEST["delete"]);
	}

	if($_REQUEST["config"]==1&&(isset($_REQUEST["activate"])||isset($_REQUEST["disable"]))){
		$result = 0;
		$status = isset($_REQUEST["activate"]) ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED;
		$groups = get_request("groups",array());

		$db_hosts=DBselect("select h.hostid, hg.groupid from hosts_groups hg, hosts h".
			" where h.hostid=hg.hostid and h.status<>".HOST_STATUS_DELETED.
			' and '.DBin_node('h.hostid'));
		while($db_host=DBfetch($db_hosts))
		{
			if(!in_array($db_host["groupid"],$groups)) continue;
			$host=get_host_by_hostid($db_host["hostid"]);
			if(!update_host_status($db_host["hostid"],$status))	continue;

			$result = 1;
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,
				"Old status [".$host["status"]."] "."New status [".$status."]");
		}
		show_messages($result, S_HOST_STATUS_UPDATED, NULL);
		unset($_REQUEST["activate"]);
	}

	if($_REQUEST["config"]==4 && isset($_REQUEST["save"]))
	{
		if(isset($_REQUEST["applicationid"]))
		{
			$result = update_application($_REQUEST["applicationid"],$_REQUEST["appname"], $_REQUEST["apphostid"]);
			$action		= AUDIT_ACTION_UPDATE;
			$msg_ok		= S_APPLICATION_UPDATED;
			$msg_fail	= S_CANNOT_UPDATE_APPLICATION;
			$applicationid = $_REQUEST["applicationid"];
		} else {
			$applicationid = add_application($_REQUEST["appname"], $_REQUEST["apphostid"]);
			$action		= AUDIT_ACTION_ADD;
			$msg_ok		= S_APPLICATION_ADDED;
			$msg_fail	= S_CANNOT_ADD_APPLICATION;
			$result = $applicationid;
		}
		show_messages($result, $msg_ok, $msg_fail);
		if($result){
			add_audit($action,AUDIT_RESOURCE_APPLICATION,S_APPLICATION." [".$_REQUEST["appname"]." ] [".$applicationid."]");
			unset($_REQUEST["form"]);
		}
		unset($_REQUEST["save"]);
	}
	elseif($_REQUEST["config"]==4 && isset($_REQUEST["delete"]))
	{
		if(isset($_REQUEST["applicationid"])){
			$result = false;
			if($app = get_application_by_applicationid($_REQUEST["applicationid"]))
			{
				$host = get_host_by_hostid($app["hostid"]);
				$result=delete_application($_REQUEST["applicationid"]);

			}
			show_messages($result, S_APPLICATION_DELETED, S_CANNOT_DELETE_APPLICATION);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,
					"Application [".$app["name"]."] from host [".$host["host"]."]");

			}
			unset($_REQUEST["form"]);
			unset($_REQUEST["applicationid"]);
		} else {
/* group operations */
			$result = 0;
			$applications = get_request("applications",array());

			$db_applications = DBselect("select applicationid, name, hostid from applications ".
				'where '.DBin_node('applicationid'));

			while($db_app = DBfetch($db_applications))
			{
				if(!in_array($db_app["applicationid"],$applications))	continue;
				if(!delete_application($db_app["applicationid"]))	continue;
				$result = 1;

				$host = get_host_by_hostid($db_app["hostid"]);
				
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_APPLICATION,
					"Application [".$db_app["name"]."] from host [".$host["host"]."]");
			}
			show_messages($result, S_APPLICATION_DELETED, NULL);
		}
		unset($_REQUEST["delete"]);
	}
	
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid()); /* update available_hosts after ACTIONS */
?>
<?php
	$frmForm = new CForm();

	$cmbConf = new CComboBox("config",$_REQUEST["config"],"submit()");
	$cmbConf->AddItem(0,S_HOSTS);
	$cmbConf->AddItem(3,S_TEMPLATES);
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
	echo BR;
?>

<?php
	if($_REQUEST["config"]==0 || $_REQUEST["config"]==3)
	{
		$show_only_tmp = 0;
		if($_REQUEST["config"]==3)
			$show_only_tmp = 1;

		if(isset($_REQUEST["form"]))
		{
			insert_host_form($show_only_tmp);
		} else {
			$status_filter = " and h.status not in (".HOST_STATUS_DELETED.",".HOST_STATUS_TEMPLATE.") ";
			if($show_only_tmp==1)
				$status_filter = " and h.status in (".HOST_STATUS_TEMPLATE.") ";
				
			$cmbGroups = new CComboBox("groupid",get_request("groupid",0),"submit()");
			$cmbGroups->AddItem(0,S_ALL_SMALL);
			$result=DBselect("select distinct g.groupid,g.name from groups g,hosts_groups hg,hosts h".
					" where h.hostid in (".$available_hosts.") ".
					" and g.groupid=hg.groupid and h.hostid=hg.hostid".$status_filter.
					" order by g.name");
			while($row=DBfetch($result))
			{
				$cmbGroups->AddItem($row["groupid"],$row["name"]);
				if($row["groupid"] == $_REQUEST["groupid"]) $correct_host = 1;
			}
			if(!isset($correct_host))
			{
				$_REQUEST["groupid"] = 0;
				$cmbGroups->SetValue($_REQUEST["groupid"]);
			}

			$frmForm = new CForm();
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
					SPACE.S_NAME),
				$show_only_tmp ? NULL : S_DNS,
				$show_only_tmp ? NULL : S_IP,
				$show_only_tmp ? NULL : S_PORT,
				S_TEMPLATES,
				$show_only_tmp ? NULL : S_STATUS,
				$show_only_tmp ? NULL : S_AVAILABILITY,
				$show_only_tmp ? NULL : S_ERROR,
				S_ACTIONS
				));
		
			$sql="select h.* from";
			if(isset($_REQUEST["groupid"]))
			{
				$sql .= " hosts h,hosts_groups hg where";
				$sql .= " hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and";
			} else  $sql .= " hosts h where";
			$sql .=	" h.hostid in (".$available_hosts.") ".
				$status_filter.
				" order by h.host";

			$result=DBselect($sql);
		
			while($row=DBfetch($result))
			{
				$add_to = array();
				$delete_from = array();

				$templates = get_templates_by_hostid($row["hostid"]);
				
				$host=new CCol(array(
					new CCheckBox("hosts[]",NULL,NULL,$row["hostid"]),
					SPACE,
					new CLink($row["host"],"hosts.php?form=update&hostid=".
						$row["hostid"].url_param("groupid").url_param("config"), 'action')
					));
		
				
				if($show_only_tmp)
				{
					$dns = NULL;
					$ip = NULL;
					$port = NULL;
					$status = NULL;
					$available = NULL;
					$error = NULL;
				}
				else
				{
					$dns = $row['dns'];
					$ip = $row['ip'];
					$port = $row["port"];

					if(1 == $row['useip'])
						$ip = bold($ip);
					else
						$dns = bold($dns);

					if($row["status"] == HOST_STATUS_MONITORED){
						$status=new CLink(S_MONITORED,"hosts.php?hosts%5B%5D=".$row["hostid"].
							"&disable=1".url_param("config").url_param("groupid"),
							"off");
					} else if($row["status"] == HOST_STATUS_NOT_MONITORED) {
						$status=new CLink(S_NOT_MONITORED,"hosts.php?hosts%5B%5D=".$row["hostid"].
							"&activate=1".url_param("config").url_param("groupid"),
							"on");
					} else if($row["status"] == HOST_STATUS_TEMPLATE)
						$status=new CCol(S_TEMPLATE,"unknown");
					else if($row["status"] == HOST_STATUS_DELETED)
						$status=new CCol(S_DELETED,"unknown");
					else
						$status=S_UNKNOWN;

					if($row["available"] == HOST_AVAILABLE_TRUE)	
						$available=new CCol(S_AVAILABLE,"off");
					else if($row["available"] == HOST_AVAILABLE_FALSE)
						$available=new CCol(S_NOT_AVAILABLE,"on");
					else if($row["available"] == HOST_AVAILABLE_UNKNOWN)
						$available=new CCol(S_UNKNOWN,"unknown");

					if($row["error"] == "")	$error = new CCol(SPACE,"off");
					else			$error = new CCol($row["error"],"on");

				}

				$popup_menu_actions = array(
					array(S_SHOW, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))),
					array(S_ITEMS, 'items.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					array(S_TRIGGERS, 'triggers.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					array(S_GRAPHS, 'graphs.php?hostid='.$row['hostid'], array('tw'=>'_blank')),
					);

				$db_groups = DBselect('select g.groupid, g.name from groups g left join hosts_groups hg '.
						' on g.groupid=hg.groupid and hg.hostid='.$row['hostid'].
						' where hostid is NULL order by g.name,g.groupid');
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
				while($group_data = DBfetch($db_groups))
				{
					$delete_from[] = array($group_data['name'], '?'.
							url_param($group_data['groupid'], false, 'delete_from_group').
							url_param($row['hostid'], false, 'hostid')
							);
				}

				if(count($add_to) > 0 || count($delete_from) > 0)
				{
					$popup_menu_actions[] = array(S_GROUPS, null, null,
						array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
				}
				if(count($add_to) > 0)
				{
					$popup_menu_actions[] = array_merge(array(S_ADD_TO_GROUP, null, null, 
						array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))), $add_to);
				}
				if(count($delete_from) > 0)
				{
					$popup_menu_actions[] = array_merge(array(S_DELETE_FROM_GROUP, null, null, 
						array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))), $delete_from);
				}

				$popup_menu_actions = array_merge(
					$popup_menu_actions,
					array(
					array("Agent control", null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))),
					array("Start", 'hosts.php?command=start&hostid='.$row['hostid'], array('tw'=>'_blank')),
					array("Stop", 'hosts.php?command=stop&hostid='.$row['hostid'], array('tw'=>'_blank')),
					array("Restart", 'hosts.php?command=restart&hostid='.$row['hostid'], array('tw'=>'_blank'))
				));

				$mnuActions = new CPUMenu($popup_menu_actions);

				$show = new CLink(S_SELECT, '#', 'action', $mnuActions->GetOnActionJS());

				$table->addRow(array(
					$host,
					$dns,
					$ip,
					$port,
					implode(', ',$templates),
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
	elseif($_REQUEST["config"]==1)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_hostgroups_form(get_request("groupid",NULL));
		} else {
			show_table_header(S_HOST_GROUPS_BIG);

			$form = new CForm('hosts.php');
			$form->SetName('groups');
			$form->AddVar("config",get_request("config",0));

			$table = new CTableInfo(S_NO_HOST_GROUPS_DEFINED);

			$table->setHeader(array(
				array(	new CCheckBox("all_groups",NULL,
						"CheckAll('".$form->GetName()."','all_groups');"),
					SPACE,
					S_NAME),
				S_MEMBERS));

			$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid());

			$db_groups=DBselect("select groupid,name from groups".
					" where groupid in (".$available_groups.")".
					" order by name");
			while($db_group=DBfetch($db_groups))
			{
				$db_hosts = DBselect("select distinct h.host, h.status".
					" from hosts h, hosts_groups hg".
					" where h.hostid=hg.hostid and hg.groupid=".$db_group["groupid"].
					" and h.hostid in (".$available_hosts.")".
					" and h.status not in (".HOST_STATUS_DELETED.") order by host");

				$hosts = array();
				while($db_host=DBfetch($db_hosts)){
					$style = $db_host["status"]==HOST_STATUS_MONITORED ? NULL: ( 
						$db_host["status"]==HOST_STATUS_TEMPLATE ? "unknown" :
						"on");
					array_push($hosts,unpack_object(new CSpan($db_host["host"],$style)));
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
					implode(', ',$hosts)
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
	elseif($_REQUEST["config"]==2)
	{
		show_table_header(S_TEMPLATE_LINKAGE_BIG);

		$table = new CTableInfo(S_NO_LINKAGES);
		$table->SetHeader(array(S_TEMPLATES,S_HOSTS));

		$templates = DBSelect("select * from hosts where status=".HOST_STATUS_TEMPLATE.
			" and hostid in (".$available_hosts.")".
			" order by host");
		while($template = DBfetch($templates))
		{
			$hosts = DBSelect("select h.* from hosts h, hosts_templates ht where ht.templateid=".$template["hostid"].
				" and ht.hostid=h.hostid ".
				" and h.status not in (".HOST_STATUS_TEMPLATE.")".
				" and h.hostid in (".$available_hosts.")".
				" order by host");
			$host_list = array();
			while($host = DBfetch($hosts))
			{
				if($host["status"] == HOST_STATUS_NOT_MONITORED)
				{
					array_push($host_list, unpack_object(new CSpan($host["host"],"on")));
				}
				else
				{
					array_push($host_list, $host["host"]);
				}
			}
			$table->AddRow(array(
				new CSpan($template["host"],"unknown"),
				implode(', ',$host_list)
				));
		}

		$table->Show();
	}
	elseif($_REQUEST["config"]==4)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_application_form();
		} else {
	// Table HEADER
			$form = new CForm();

			$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
			$cmbGroup->AddItem(0,S_ALL_SMALL);

			$result=DBselect("select distinct g.groupid,g.name from groups g,hosts_groups hg".
				" where g.groupid=hg.groupid and hg.hostid in (".$available_hosts.") ".
				" order by name");
			while($row=DBfetch($result))
			{
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
			}
			$form->AddItem(S_GROUP.SPACE);
			$form->AddItem($cmbGroup);

			if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]>0)
			{
				$sql="select distinct h.hostid,h.host from hosts h,hosts_groups hg".
					" where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid ".
					" and h.hostid in (".$available_hosts.") ".
					" and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
			}
			else
			{
				$sql="select distinct h.hostid,h.host from hosts h ".
					" where h.status<>".HOST_STATUS_DELETED.
					" and h.hostid in (".$available_hosts.") ".
					" group by h.hostid,h.host order by h.host";
			}
			$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

			$result=DBselect($sql);
			while($row=DBfetch($result))
			{
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
				array(new CCheckBox("all_applications",NULL,
					"CheckAll('".$form->GetName()."','all_applications');"),
				SPACE,
				S_APPLICATION),
				S_SHOW
				));

			$db_applications = DBselect("select * from applications where hostid=".$_REQUEST["hostid"]);
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
					array(new CCheckBox("applications[]",NULL,NULL,$db_app["applicationid"]),
					SPACE,
					$name),
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
?>
<?php

include_once "include/page_footer.php";

?>
