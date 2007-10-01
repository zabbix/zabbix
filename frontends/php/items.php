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
	require_once "include/items.inc.php";
	require_once "include/forms.inc.php";

        $page["title"] = "S_CONFIGURATION_OF_ITEMS";
        $page["file"] = "items.php";

include_once "include/page_header.php";
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"external_filter"=>		array(T_ZBX_STR, O_OPT,	null,	null,		null),
		"selection_mode"=>	array(T_ZBX_INT, O_OPT,	null,	IN("0,1"),		null),

		"type_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"community_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"securityname_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		"securitylevel_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		"authpassphrase_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		"privpassphras_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		"port_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"value_type_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"units_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"formula_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"delay_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"delay_flex_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"history_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"trends_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"status_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"logtimefmt_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"delta_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"valuemapid_visible"=>		array(T_ZBX_STR, O_OPT,  null, null,           null),
		"trapper_hosts_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),
		"applications_visible"=>	array(T_ZBX_STR, O_OPT,  null, null,           null),

		"with_node"=>		array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,	null,		null),
		"with_group"=>		array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,	null,		null),
		"with_host"=>		array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,	null,		null),
		"with_application"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,	null,		null),
		"with_description"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,	null,		null),
		"with_type"=>		array(T_ZBX_INT, O_OPT,  null,  
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_AGGREGATE,ITEM_TYPE_HTTPTEST,ITEM_TYPE_EXTERNAL)),null),
		"with_key"=>		array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null,		null),
		"with_snmp_community"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null,	null),
		"with_snmp_oid"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null,	null),
		"with_snmp_port"=>	array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),	null),
		"with_snmpv3_securityname"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),
		"with_snmpv3_securitylevel"=>	array(T_ZBX_INT, O_OPT,  null,  IN("-1,0,1,2"), null),
		"with_snmpv3_authpassphrase"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),
		"with_snmpv3_privpassphrase"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),
		"with_value_type"=>	array(T_ZBX_INT, O_OPT,  null,  IN("-1,0,1,2,3,4"),null),
		"with_units"=>	array(T_ZBX_STR, O_OPT,  null,  P_UNSET_EMPTY, null, null),
		"with_formula"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),
		"with_delay"=>		array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,86400),null),
		"with_history"=>	array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),null),
		"with_trends"=>		array(T_ZBX_INT, O_OPT,  P_UNSET_EMPTY,  BETWEEN(0,65535),null),
		"with_status"=>		array(T_ZBX_INT, O_OPT,  null,  IN("-1,0,1,3"),null),
		"with_logtimefmt"=>	array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),
		"with_delta"=>	array(T_ZBX_INT, O_OPT,  null,  IN("-1,0,1,2"), null),
		"with_trapper_hosts"=>array(T_ZBX_STR, O_OPT,  P_UNSET_EMPTY,  null, null),

		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,null),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({save})'),

		"add_groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,'{register}=="go"'),
		"action"=>	array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,'{register}=="go"'),

		"copy_type"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0,1"),'isset({copy})'),
		"copy_mode"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0"),null),

		"itemid"=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,'{form}=="update"'),
		"description"=>	array(T_ZBX_STR, O_OPT,  null,	NOT_EMPTY,'isset({save})'),
		"key"=>		array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,'isset({save})'),
		"delay"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),'isset({save})&&{type}!=2'),
		"new_delay_flex"=>	array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  "",'isset({add_delay_flex})&&{type}!=2'),
		"rem_delay_flex"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,86400),null),
		"delay_flex"=>	array(T_ZBX_STR, O_OPT,  null,  "",null),
		"history"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		"type"=>	array(T_ZBX_INT, O_OPT,  null,  
				IN(array(-1,ITEM_TYPE_ZABBIX,ITEM_TYPE_SNMPV1,ITEM_TYPE_TRAPPER,ITEM_TYPE_SIMPLE,
					ITEM_TYPE_SNMPV2C,ITEM_TYPE_INTERNAL,ITEM_TYPE_SNMPV3,ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_AGGREGATE,ITEM_TYPE_HTTPTEST,ITEM_TYPE_EXTERNAL)),'isset({save})'),
		"trends"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})'),
		"value_type"=>	array(T_ZBX_INT, O_OPT,  null,  IN("0,1,2,3,4"),'isset({save})'),
		"valuemapid"=>	array(T_ZBX_INT, O_OPT,	 null,	DB_ID,'isset({save})'),

		"snmp_community"=>array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,'isset({save})&&'.IN("1,4","type")),
		"snmp_oid"=>	array(T_ZBX_STR, O_OPT,  null,  NOT_EMPTY,'isset({save})&&'.IN("1,4,6","type")),
		"snmp_port"=>	array(T_ZBX_INT, O_OPT,  null,  BETWEEN(0,65535),'isset({save})&&'.IN("1,4,6","type")),

		"snmpv3_securitylevel"=>array(T_ZBX_INT, O_OPT,  null,  IN("0,1,2"),'isset({save})&&{type}==6'),
		"snmpv3_securityname"=>array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&{type}==6'),
		"snmpv3_authpassphrase"=>array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&{type}==6'),
		"snmpv3_privpassphrase"=>array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&{type}==6'),

		"trapper_hosts"=>array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&{type}==2'),
		"units"=>	array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&'.IN("0,3","type")),
		"multiplier"=>	array(T_ZBX_INT, O_OPT,  null,  IN("0,1"),'isset({save})&&'.IN("0,3","type")),
		"delta"=>	array(T_ZBX_INT, O_OPT,  null,  IN("0,1,2"),'isset({save})&&'.IN("0,3","type")),

		"formula"=>	array(T_ZBX_DBL, O_OPT,  null,  null,'isset({save})&&{multiplier}==1'),
		"logtimefmt"=>	array(T_ZBX_STR, O_OPT,  null,  null,'isset({save})&&{value_type}==2'),
                 
		"group_itemid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		"copy_targetid"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),
		"filter_groupid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&{copy_type}==0'),
		"applications"=>	array(T_ZBX_INT, O_OPT,	null,	DB_ID, null),

		"showdisabled"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	null),
		
		"del_history"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"add_delay_flex"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_delay_flex"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		"register"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_task"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"clone"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"update"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"copy"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"select"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_mass_update"=>	array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	$_REQUEST["showdisabled"] = get_request("showdisabled", get_profile("web.items.showdisabled", 0));
	
	check_fields($fields);

	echo '<script type="text/javascript" src="js/items.js"></script>';
	
	$showdisabled = get_request("showdisabled", 0);
	
	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,get_current_nodeid());

	if(isset($_REQUEST['hostid']) && !in_array($_REQUEST['hostid'], explode(',',$accessible_hosts)))
	{
		unset($_REQUEST['hostid']);
	}
		
	validate_group_with_host(PERM_READ_WRITE,array("always_select_first_host","only_current_node"),
		'web.last.conf.groupid', 'web.last.conf.hostid');

	update_profile("web.items.showdisabled",$showdisabled);
?>
<?php
	$result = 0;
	if(isset($_REQUEST['external_filter']) && isset($_REQUEST['cancel']))
	{
		update_profile('external_filter', 0);
		unset($_REQUEST['external_filter']);
	}
	if(isset($_REQUEST['del_delay_flex']) && isset($_REQUEST['rem_delay_flex']))
	{
		$_REQUEST['delay_flex'] = get_request('delay_flex',array());
		foreach($_REQUEST['rem_delay_flex'] as $val){
			unset($_REQUEST['delay_flex'][$val]);
		}
	}
	else if(isset($_REQUEST["add_delay_flex"])&&isset($_REQUEST["new_delay_flex"]))
	{
		$_REQUEST['delay_flex'] = get_request("delay_flex", array());
		array_push($_REQUEST['delay_flex'],$_REQUEST["new_delay_flex"]);
	}
	else if(isset($_REQUEST["delete"])&&isset($_REQUEST["itemid"]))
	{
		$result = false;
		if($item = get_item_by_itemid($_REQUEST["itemid"]))
		{
			$result = delete_item($_REQUEST["itemid"]);
		}
		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
		if($result){
			$host = get_host_by_hostid($item["hostid"]);

			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,
				S_ITEM." [".$item["key_"]."] [".$_REQUEST["itemid"]."] ".S_HOST." [".$host['host']."]");
		}
		unset($_REQUEST["itemid"]);
		unset($_REQUEST["form"]);
	}
	else if(isset($_REQUEST["clone"]) && isset($_REQUEST["itemid"]))
	{
		unset($_REQUEST["itemid"]);
		$_REQUEST["form"] = "clone";
	}
	else if(isset($_REQUEST["save"]))
	{
		$applications = get_request("applications",array());
		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = "";
		foreach($delay_flex as $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,";");

		if(isset($_REQUEST["itemid"]))
		{
			$result = smart_update_item($_REQUEST["itemid"],
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],$db_delay_flex,$applications);

			$itemid = $_REQUEST["itemid"];
			$action = AUDIT_ACTION_UPDATE;
			
			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
		}
		else
		{
			$itemid=add_item(
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],$db_delay_flex,$applications);

			$result = $itemid;
			$action = AUDIT_ACTION_ADD;
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}
		if($result){	
			$host = get_host_by_hostid($_REQUEST["hostid"]);

			add_audit($action, AUDIT_RESOURCE_ITEM,
				S_ITEM." [".$_REQUEST["key"]."] [".$itemid."] ".S_HOST." [".$host['host']."]");

			unset($_REQUEST["itemid"]);
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["del_history"])&&isset($_REQUEST["itemid"]))
	{
		$result = false;
		if($item = get_item_by_itemid($_REQUEST["itemid"]))
		{
			$result = delete_history_by_itemid($_REQUEST["itemid"]);
		}
		
		if($result)
		{
			DBexecute("update items set nextcheck=0,lastvalue=null,".
				"lastclock=null,prevvalue=null where itemid=".$_REQUEST["itemid"]);
			
			$host = get_host_by_hostid($_REQUEST["hostid"]);

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
				S_ITEM." [".$item["key_"]."] [".$_REQUEST["itemid"]."] ".S_HOST." [".$host['host']."] ".S_HISTORY_CLEANED);
		}
		show_messages($result, S_HISTORY_CLEANED, S_CANNOT_CLEAN_HISTORY);
		
	}
	elseif(isset($_REQUEST["update"])&&isset($_REQUEST["group_itemid"])&&isset($_REQUEST["form_mass_update"]))
	{
		$applications = get_request("applications",array());

		$delay_flex = get_request('delay_flex',array());
		$db_delay_flex = "";
		foreach($delay_flex as $val)
			$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
		$db_delay_flex = trim($db_delay_flex,";");
		
		$result = false;

		if(!is_null(get_request("formula",null))) $_REQUEST['multiplier']=1;
		if("0" === get_request("formula",null)) $_REQUEST['multiplier']=0;

		$group_itemid = $_REQUEST["group_itemid"];
		foreach($group_itemid as $id)
		{
			$result |= smart_update_item($id,
				null,null,null,get_request("delay"),
				get_request("history"),get_request("status"),get_request("type"),
				get_request("snmp_community"),get_request("snmp_oid"),get_request("value_type"),
				get_request("trapper_hosts"),get_request("snmp_port"),get_request("units"),
				get_request("multiplier"),get_request("delta"),get_request("snmpv3_securityname"),
				get_request("snmpv3_securitylevel"),get_request("snmpv3_authpassphrase"),
				get_request("snmpv3_privpassphrase"),get_request("formula"),get_request("trends"),
				get_request("logtimefmt"),get_request("valuemapid"),$db_delay_flex,$applications);
		}

		show_messages($result, S_ITEMS_UPDATED);

		unset($_REQUEST["group_itemid"], $_REQUEST["form_mass_update"], $_REQUEST["update"]);
	}
	elseif(isset($_REQUEST["copy"])&&isset($_REQUEST["group_itemid"])&&isset($_REQUEST["form_copy_to"]))
	{
		if(isset($_REQUEST['copy_targetid']) && $_REQUEST['copy_targetid'] > 0 && isset($_REQUEST['copy_type']))
		{
			if(0 == $_REQUEST['copy_type'])
			{ /* hosts */
				$hosts_ids = $_REQUEST['copy_targetid'];
			}
			else
			{ /* groups */
				$hosts_ids = array();
				$group_ids = "";
				foreach($_REQUEST['copy_targetid'] as $group_id)
				{
					$group_ids .= $group_id.',';
				}
				$group_ids = trim($group_ids,',');

				$db_hosts = DBselect('select distinct h.hostid from hosts h, hosts_groups hg'.
					' where h.hostid=hg.hostid and hg.groupid in ('.$group_ids.')');
				while($db_host = DBfetch($db_hosts))
				{
					array_push($hosts_ids, $db_host['hostid']);
				}
			}

			foreach($_REQUEST["group_itemid"] as $item_id)
				foreach($hosts_ids as $host_id)
				{
					copy_item_to_host($item_id, $host_id, true);
				}
			unset($_REQUEST["form_copy_to"]);
		}
		else
		{
			error('No target selection.');
		}
		show_messages();
	}
	elseif(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="do")
		{
			if($_REQUEST["action"]=="add to group")
			{
				$applications = get_request("applications",array());
				$delay_flex = get_request('delay_flex',array());
				$db_delay_flex = "";
				foreach($delay_flex as $val)
					$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
				$db_delay_flex = trim($db_delay_flex,";");
				$itemid=add_item_to_group(
					$_REQUEST["add_groupid"],$_REQUEST["description"],$_REQUEST["key"],
					$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],
					$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],
					$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],
					$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],
					$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
					$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
					$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],
					$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],
					$db_delay_flex, $applications);
				show_messages($itemid, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
				if($itemid){
					unset($_REQUEST["form"]);
					unset($_REQUEST["itemid"]);
					unset($itemid);
				}
			}
			if($_REQUEST["action"]=="update in group")
			{
				$applications = get_request("applications",array());
				$delay_flex = get_request('delay_flex',array());
				$db_delay_flex = "";
				foreach($delay_flex as $val)
					$db_delay_flex .= $val['delay'].'/'.$val['period'].';';
				$db_delay_flex = trim($db_delay_flex,";");
				$result=update_item_in_group($_REQUEST["add_groupid"],
					$_REQUEST["itemid"],$_REQUEST["description"],$_REQUEST["key"],
					$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],
					$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],
					$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],
					$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],
					$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
					$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
					$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],
					$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],
					$db_delay_flex, $applications);
				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
				if($result){
					unset($_REQUEST["form"]);
					unset($_REQUEST["itemid"]);
				}
			}
			if($_REQUEST["action"]=="delete from group")
			{
				$result=delete_item_from_group($_REQUEST["add_groupid"],$_REQUEST["itemid"]);
				show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
				if($result){
					unset($_REQUEST["form"]);
					unset($_REQUEST["itemid"]);
				}
			}
		}
	}
	elseif(isset($_REQUEST["group_task"])&&isset($_REQUEST["group_itemid"]))
	{
		if($_REQUEST["group_task"]==S_DELETE_SELECTED)
		{
			$result = false;

			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				if(!($item = get_item_by_itemid($id)))	continue;
				if($item["templateid"]<>0)	continue;
				if(delete_item($id))
				{
					$result = true;
					
					$host = get_host_by_hostid($item["hostid"]);

					add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ITEM,
						S_ITEM." [".$item["key_"]."] [".$id."] ".S_HOST." [".$host['host']."]");
				}
			}
			show_messages($result, S_ITEMS_DELETED, null);
		}
		else if($_REQUEST["group_task"]==S_ACTIVATE_SELECTED)
		{
			$result = false;
			
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				if(!($item = get_item_by_itemid($id)))	continue;
				
				if(activate_item($id))
				{
					$result = true;
					$host = get_host_by_hostid($item["hostid"]);
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
						S_ITEM." [".$item["key_"]."] [".$id."] ".S_HOST." [".$host['host']."] ".S_ITEMS_ACTIVATED);
				}
			}
			show_messages($result, S_ITEMS_ACTIVATED, null);
		}
		elseif($_REQUEST["group_task"]==S_DISABLE_SELECTED)
		{
			$result = false;
			
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				if(!($item = get_item_by_itemid($id)))	continue;

				if(disable_item($id))
				{
					$result = true;				
				
					$host = get_host_by_hostid($item["hostid"]);
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
						S_ITEM." [".$item["key_"]."] [".$id."] ".S_HOST." [".$host['host']."] ".S_ITEMS_DISABLED);
				}
			}
			show_messages($result, S_ITEMS_DISABLED, null);
		}
		elseif($_REQUEST["group_task"]==S_CLEAN_HISTORY_SELECTED_ITEMS)
		{
			$result = false;
			
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				if(!($item = get_item_by_itemid($id)))	continue;

				if(delete_history_by_itemid($id))
				{
					$result = true;
					DBexecute("update items set nextcheck=0,lastvalue=null,".
						"lastclock=null,prevvalue=null where itemid=$id");
					
					$host = get_host_by_hostid($item["hostid"]);
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ITEM,
						S_ITEM." [".$item["key_"]."] [".$id."] ".S_HOST." [".$host['host']."] ".S_HISTORY_CLEANED);
				}
			}
			show_messages($result, S_HISTORY_CLEANED, $result);
		}
	}
?>
<?php

	$form = new CForm();
	$form->SetMethod('get');
	$form->SetName('hdrform');

	$form->AddVar("hostid",$_REQUEST["hostid"]);
	$form->AddVar("groupid",$_REQUEST["groupid"]);

	$form->AddItem(new CButton("form",S_CREATE_ITEM));

	show_table_header(S_CONFIGURATION_OF_ITEMS_BIG, $form);

	if(isset($_REQUEST["form_mass_update"]) && isset($_REQUEST["group_itemid"]))
	{
		echo BR;
		insert_mass_update_item_form("group_itemid");
	} else if(isset($_REQUEST["form_copy_to"]) && isset($_REQUEST["group_itemid"]))
	{
		echo BR;
		insert_copy_elements_to_forms("group_itemid");
	} elseif (!isset($_REQUEST["form"]) ||  !in_array($_REQUEST["form"],array(S_CREATE_ITEM,"update","clone"))) {
		echo BR;
// Table HEADER
		$form = new CForm();
		$form->SetMethod('get');
		
		$where_case = array();
		$from_tables['h'] = 'hosts h';
		$where_case[] = 'i.hostid=h.hostid';
		$where_case[] = 'h.hostid in ('.$accessible_hosts.')';

		update_profile("external_filter",$_REQUEST['external_filter'] = get_request("external_filter" ,get_profile("external_filter", 0)));

		if($_REQUEST['external_filter'])
		{
			insert_item_selection_form();
			echo BR;

			if(ZBX_DISTRIBUTED && isset($_REQUEST['with_node']))
			{
				$from_tables['n'] = 'nodes n';
				$where_case[] = 'n.nodeid='.DBid2nodeid('i.itemid');
				$where_case[] = 'n.name like '.zbx_dbstr('%'.$_REQUEST['with_node'].'%');
			}
			if(isset($_REQUEST['with_group']))
			{
				$from_tables['hg'] = 'hosts_groups hg';
				$from_tables['g'] = 'groups g';
				$where_case[] = 'i.hostid=hg.hostid';
				$where_case[] = 'g.groupid=hg.groupid';
				$where_case[] = 'g.name like '.zbx_dbstr('%'.$_REQUEST['with_group'].'%');
			}
			if(isset($_REQUEST['with_host']))
			{
				$where_case[] = 'h.host like '.zbx_dbstr('%'.$_REQUEST['with_host'].'%');
			}
			if(isset($_REQUEST['with_application']))
			{
				$from_tables['a'] = 'applications a';
				$from_tables['ia'] = 'items_applications ia';
				$where_case[] = 'i.itemid=ia.itemid';
				$where_case[] = 'ia.applicationid=a.applicationid';
				$where_case[] = 'a.name like '.zbx_dbstr('%'.$_REQUEST['with_application'].'%');
			}
			if(isset($_REQUEST['with_type']) && $_REQUEST['with_type'] != -1)
			{
				$where_case[] = 'i.type='.$_REQUEST['with_type'];
			}
			if(isset($_REQUEST['with_key']))
			{
				$where_case[] = 'i.key_ like '.zbx_dbstr('%'.$_REQUEST['with_key'].'%');
			}
			if(isset($_REQUEST['with_snmp_community']))
			{
				$where_case[] = 'i.snmp_community like '.zbx_dbstr('%'.$_REQUEST['with_snmp_community'].'%');
			}
			if(isset($_REQUEST['with_snmp_oid']))
			{
				$where_case[] = 'i.with_snmp_oid like '.zbx_dbstr('%'.$_REQUEST['with_snmp_oid'].'%');
			}
			if(isset($_REQUEST['with_snmp_port']))
			{
				$where_case[] = 'i.snmp_port='.$_REQUEST['with_snmp_port'];
			}
			if(isset($_REQUEST['with_snmpv3_securityname']))
			{
				$where_case[] = 'i.snmpv3_securityname like '.zbx_dbstr('%'.$_REQUEST['with_snmpv3_securityname'].'%');
			}
			if(isset($_REQUEST['with_snmpv3_securitylevel']) && $_REQUEST['with_snmpv3_securitylevel'] != -1)
			{
				$where_case[] = 'i.snmpv3_securitylevel='.$_REQUEST['with_snmpv3_securitylevel'];
			}
			if(isset($_REQUEST['with_snmpv3_authpassphrase']))
			{
				$where_case[] = 'i.snmpv3_authpassphrase like '.zbx_dbstr('%'.$_REQUEST['with_snmpv3_authpassphrase'].'%');
			}
			if(isset($_REQUEST['with_snmpv3_privpassphrase']))
			{
				$where_case[] = 'i.snmpv3_privpassphrase like '.zbx_dbstr('%'.$_REQUEST['with_snmpv3_privpassphrase'].'%');
			}
			if(isset($_REQUEST['with_value_type']) && $_REQUEST['with_value_type'] != -1)
			{
				$where_case[] = 'i.value_type='.$_REQUEST['with_value_type'];
			}
			if(isset($_REQUEST['with_units']))
			{
				$where_case[] = 'i.units='.zbx_dbstr($_REQUEST['with_units']);
			}
			if(isset($_REQUEST['with_formula']))
			{
				$where_case[] = 'i.formula like '.zbx_dbstr('%'.$_REQUEST['with_formula'].'%');
			}
			if(isset($_REQUEST['with_delay']))
			{
				$where_case[] = 'i.delay='.$_REQUEST['with_delay'];
			}
			if(isset($_REQUEST['with_history']))
			{
				$where_case[] = 'i.history='.$_REQUEST['with_history'];
			}
			if(isset($_REQUEST['with_trends']))
			{
				$where_case[] = 'i.trends='.$_REQUEST['with_trends'];
			}
			if(isset($_REQUEST['with_status']) && $_REQUEST['with_status'] != -1)
			{
				$where_case[] = 'i.status='.$_REQUEST['with_status'];
			}
			if(isset($_REQUEST['with_logtimefmt']))
			{
				$where_case[] = 'i.logtimefmt='.zbx_dbstr($_REQUEST['with_logtimefmt']);
			}
			if(isset($_REQUEST['with_delta']) && $_REQUEST['with_delta'] != -1)
			{
				$where_case[] = 'i.delta='.$_REQUEST['with_delta'];
			}
			if(isset($_REQUEST['with_trapper_hosts']))
			{
				$where_case[] = 'i.trapper_hosts like '.zbx_dbstr('%'.$_REQUEST['with_trapper_hosts'].'%');
			}

			$show_applications = 0;
			$show_host = 1;
		}
		else
		{
			
			$form->AddItem(array('[', 
				new CLink($showdisabled ? S_HIDE_DISABLED_ITEMS : S_SHOW_DISABLED_ITEMS,
					'?showdisabled='.($showdisabled ? 0 : 1),'action'),
				']', SPACE));

			$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
			$cmbGroup->AddItem(0,S_ALL_SMALL);

			$result=DBselect("select distinct g.groupid,g.name from groups g,hosts_groups hg".
				" where g.groupid=hg.groupid and hg.hostid in (".$accessible_hosts.") ".
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
					" and h.hostid in (".$accessible_hosts.") ".
					" and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
			}
			else
			{
				$sql="select distinct h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED.
					" and h.hostid in (".$accessible_hosts.") ".
					" group by h.hostid,h.host order by h.host";
			}

			$result=DBselect($sql);

			$_REQUEST["hostid"] = get_request("hostid",0);
			$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

			unset($correct_hostid);
			$first_hostid = -1;
			while($row=DBfetch($result))
			{
				$cmbHosts->AddItem($row["hostid"],$row["host"]);

				if($_REQUEST["hostid"]!=0){
					if($_REQUEST["hostid"]==$row["hostid"])
						$correct_hostid = 'ok';
				}
				if($first_hostid <= 0)
					$first_hostid = $row["hostid"];
			}
			if(!isset($correct_hostid))
				$_REQUEST["hostid"] = $first_hostid;

			$form->AddItem(SPACE.S_HOST.SPACE);
			$form->AddItem($cmbHosts);

			$form->AddItem(SPACE);
			$form->AddItem(new CButton("external_filter",S_EXTERNAL_FILTER));

			if($host_info = DBfetch(DBselect('select host from hosts where hostid='.$_REQUEST["hostid"])))
			{
				$form->AddVar('with_host', $host_info['host']);
			}
			$where_case[] = 'i.hostid='.$_REQUEST['hostid'];
			if($showdisabled == 0) $where_case[] = 'i.status <> 1';

			$show_applications = 1;
			$show_host = 0;
		}
		
		show_table_header(S_ITEMS_BIG, $form);

// TABLE
		$form = new CForm();
		$form->SetName('items');

		$table  = new CTableInfo();
		$table->SetHeader(array(
			$show_host ? S_HOST : null,
			array(	new CCheckBox("all_items",null,
					"CheckAll('".$form->GetName()."','all_items');"),
				S_DESCRIPTION),
			S_MENU,
			S_KEY,nbsp(S_UPDATE_INTERVAL),
			S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,
			$show_applications ? S_APPLICATIONS : null,
			S_ERROR));

		$from_tables['i'] = 'items i'; /* NOTE: must be added as last element to use left join */

		$db_items = DBselect('select distinct th.host as template_host,th.hostid as template_hostid, h.host, i.* '.
			' from '.implode(',', $from_tables).
			' left join items ti on i.templateid=ti.itemid left join hosts th on ti.hostid=th.hostid '.
			' where '.implode(' and ', $where_case).' order by h.host,i.description,i.key_,i.itemid');
		while($db_item = DBfetch($db_items))
		{
			$description = array();

			$item_description = item_description($db_item["description"],$db_item["key_"]);

			if( $_REQUEST['external_filter'] && isset($_REQUEST['with_description']) && !stristr($item_description, $_REQUEST['with_description']) ) continue;

			if($db_item["templateid"])
			{
				$template_host = get_realhost_by_itemid($db_item["templateid"]);
				array_push($description,		
					new CLink($template_host["host"],"?".
						"hostid=".$template_host["hostid"],
						'unknown'),
					":");
			}
			
			array_push($description, new CLink(
				item_description($db_item["description"],$db_item["key_"]),
				"?form=update&itemid=".
				$db_item["itemid"].url_param("hostid").url_param("groupid"),
				'action'));

			$status=new CCol(new CLink(item_status2str($db_item["status"]),
					"?group_itemid%5B%5D=".$db_item["itemid"].
					"&group_task=".($db_item["status"] ? "Activate+selected" : "Disable+selected"),
					item_status2style($db_item["status"])));
	
			if($db_item["error"] == "")
			{
				$error=new CCol(SPACE,"off");
			}
			else
			{
				$error=new CCol($db_item["error"],"on");
			}

			$applications = $show_applications ? implode(', ', get_applications_by_itemid($db_item["itemid"], 'name')) : null;
			
			if(preg_match("/^log\[.*\].*$/",$db_item["key_"])){
				$res = DBselect('SELECT MAX(t.description) as description, t.triggerid'.
					' FROM functions as f, triggers as t, items as i '.
					' WHERE f.itemid='.$db_item["itemid"].
					  ' AND i.itemid=f.itemid AND t.triggerid = f.triggerid '.
					  ' AND i.value_type=2 AND i.key_ LIKE \'log[%\' '.
					' GROUP BY t.triggerid');

				$triggers_flag = false;
				$triggers=",Array('Edit Trigger',null,null,{'outer' : 'pum_o_submenu','inner' : ['pum_i_submenu']}\n";

				while($trigger=DBfetch($res)){
					$item_count = DBfetch(DBselect('SELECT count(DISTINCT f.itemid) as items FROM functions as f WHERE f.triggerid='.$trigger['triggerid']));
					if($item_count['items'] > 1) continue;
					
					$triggers .= ',["'.$trigger['description']."\",\"javascript: openWinCentered('tr_logform.php?sform=1&itemid=".$db_item["itemid"]."&triggerid=".$trigger['triggerid']."','TriggerLog',760,540,'titlebar=no, resizable=yes, scrollbars=yes');\"]";
					$triggers_flag = true;
				}
				if($triggers_flag){
					$triggers=rtrim($triggers,',').')';
				}
				else{
					$triggers='';
				}
				
				$menuicon = new CImg('images/general/menuicon.gif','menu',21,18);
				$menuicon->AddOption('onclick','javascript: call_menu(event, '.zbx_jsvalue($db_item["itemid"]).','.zbx_jsvalue(item_description($db_item["description"],$db_item["key_"])).$triggers.'); return false;');
				$menuicon->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			} 
			else {
				$menuicon = new CImg('images/general/tree/O.gif','zero',21,18);
			}

			$chkBox = new CCheckBox("group_itemid[]",null,null,$db_item["itemid"]);
			//if($db_item["templateid"] > 0) $chkBox->SetEnabled(false);
			$table->AddRow(array(
				$show_host ? $db_item['host'] : null,
				array($chkBox, $description),
				$menuicon,
				$db_item["key_"],
				$db_item["delay"],
				$db_item["history"],
				$db_item["trends"],
				item_type2str($db_item['type']),
				$status,
				$applications,
				$error
				));
		}

		$footerButtons = array();
		array_push($footerButtons, new CButtonQMessage('group_task',S_ACTIVATE_SELECTED,S_ACTIVATE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DISABLE_SELECTED,S_DISABLE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_CLEAN_HISTORY_SELECTED_ITEMS,
			S_HISTORY_CLEANING_CAN_TAKE_A_LONG_TIME_CONTINUE_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButtonQMessage('group_task',S_DELETE_SELECTED,S_DELETE_SELECTED_ITEMS_Q));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_copy_to',S_COPY_SELECTED_TO));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_mass_update',S_MASS_UPDATE));
		$table->SetFooter(new CCol($footerButtons));

		$form->AddItem($table);
		$form->Show();

	}

	if(isset($_REQUEST["form"]) && (in_array($_REQUEST["form"],array(S_CREATE_ITEM,"update","clone")) ||
		($_REQUEST["form"]=="mass_update" && isset($_REQUEST['group_itemid']))))
	{
// FORM
		echo BR;
		insert_item_form();
	}

$jsmenu = new CPUMenu(null,170);
$jsmenu->InsertJavaScript();
?>
<?php

include_once "include/page_footer.php"

?>
