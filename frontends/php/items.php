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

	insert_confirm_javascript();
?>
<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({form})'),

		"add_groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,'{register}=="go"'),
		"action"=>	array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,'{register}=="go"'),

		"copy_type"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0,1"),'isset({copy})'),
		"copy_mode"	=>array(T_ZBX_INT, O_OPT,	 P_SYS,	IN("0"),NULL),

		"itemid"=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,'{form}=="update"'),
		"description"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		"key"=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'isset({save})'),
		"delay"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,86400),'isset({save})&&{type}!=2'),
		"new_delay_flex"=>	array(T_ZBX_STR, O_OPT,  NOT_EMPTY,  "",'isset({add_delay_flex})&&{type}!=2'),
		"rem_delay_flex"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,86400),NULL),
		"delay_flex"=>	array(T_ZBX_STR, O_OPT,  NULL,  "",NULL),
		"history"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"type"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2,3,4,5,6,7,8"),'isset({save})'),
		"trends"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,65535),'isset({save})'),
		"value_type"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2,3,4"),'isset({save})'),
		"valuemapid"=>	array(T_ZBX_INT, O_OPT,	 NULL,	DB_ID,'isset({save})'),

		"snmp_community"=>array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'isset({save})&&'.IN("1,4","type")),
		"snmp_oid"=>	array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'isset({save})&&'.IN("1,4,6","type")),
		"snmp_port"=>	array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'isset({save})&&'.IN("1,4,6","type")),

		"snmpv3_securitylevel"=>array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2"),'isset({save})&&{type}==6'),
		"snmpv3_securityname"=>array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&{type}==6'),
		"snmpv3_authpassphrase"=>array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&{type}==6'),
		"snmpv3_privpassphrase"=>array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&{type}==6'),

		"trapper_hosts"=>array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&{type}==2'),
		"units"=>	array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&'.IN("0,3","type")),
		"multiplier"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1"),'isset({save})&&'.IN("0,3","type")),
		"delta"=>	array(T_ZBX_INT, O_OPT,  NULL,  IN("0,1,2"),'isset({save})&&'.IN("0,3","type")),

		"formula"=>	array(T_ZBX_DBL, O_OPT,  NULL,  NULL,'isset({save})&&{multiplier}==1'),
		"logtimefmt"=>	array(T_ZBX_STR, O_OPT,  NULL,  NULL,'isset({save})&&{value_type}==2'),
                 
		"group_itemid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"copy_targetid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),
		"filter_groupid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, 'isset({copy})&&{copy_type}==0'),
		"applications"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),

		"del_history"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"add_delay_flex"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"del_delay_flex"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		"register"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"group_task"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"copy"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_copy_to"=>	array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	check_fields($fields);

	$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_WRITE,null,null,$ZBX_CURNODEID);

	if(isset($_REQUEST['hostid']) && !in_array($_REQUEST['hostid'], explode(',',$accessible_hosts)))
	{
		unset($_REQUEST['hostid']);
	}
		
	validate_group_with_host(PERM_READ_WRITE,array("always_select_first_host","only_current_node"));
?>
<?php
	$result = 0;
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
			$item_data = get_item_by_itemid($_REQUEST["itemid"]);
			if($item_data['templateid'])
			{
				foreach(array(
					"description"		=> null,
					"key"			=> "key_",
					"hostid"		=> null,
					//"delay"		=> null,
					//"history"		=> null,
					//"status"		=> null,
					"type"			=> null,
					"snmp_community"	=> null,
					"snmp_oid"		=> null,
					"value_type"		=> null,
					"trapper_hosts"		=> null,
					"snmp_port"		=> null,
					"units"			=> null,
					"multiplier"		=> null,
					//"delta"		=> null,
					"snmpv3_securityname"	=> null,
					"snmpv3_securitylevel"	=> null,
					"snmpv3_authpassphrase"	=> null,
					"snmpv3_privpassphrase"	=> null,
					"formula"		=> null,
					//"trends"		=> null,
					"logtimefmt"		=> null,
					"valuemapid"		=> null
					) as $req_var_name => $db_varname)
				{
					if(!isset($db_varname)) $db_varname = $req_var_name;
					$_REQUEST[$req_var_name] = $item_data[$db_varname];
				}
			}
			$result = update_item($_REQUEST["itemid"],
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],$db_delay_flex,$applications,
				$item_data['templateid']);

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
		if($_REQUEST["group_task"]=="Delete selected")
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
		else if($_REQUEST["group_task"]=="Activate selected")
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
		elseif($_REQUEST["group_task"]=="Disable selected")
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
		elseif($_REQUEST["group_task"]=='Clean history selected items')
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

	$form->AddVar("hostid",$_REQUEST["hostid"]);

	$form->AddItem(new CButton("form",S_CREATE_ITEM));

	show_table_header(S_CONFIGURATION_OF_ITEMS_BIG, $form);
	echo BR;

	$db_hosts=DBselect("select hostid from hosts where ".DBid2nodeid("hostid")."=".$ZBX_CURNODEID);
	if(isset($_REQUEST["form_copy_to"]) && isset($_REQUEST["group_itemid"]))
	{
		insert_copy_elements_to_forms("group_itemid");
	}
	elseif(isset($_REQUEST["form"])&&isset($_REQUEST["hostid"])&&DBfetch($db_hosts))
	{
// FORM
		insert_item_form();
	} else {

// Table HEADER
		$form = new CForm();

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
		
		show_table_header(S_ITEMS_BIG, $form);

// TABLE
		$form = new CForm();
		$form->SetName('items');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$show_applications = 1;

		$table  = new CTableInfo();
		$table->setHeader(array(
			array(	new CCheckBox("all_items",NULL,
					"CheckAll('".$form->GetName()."','all_items');"),
				S_DESCRIPTION),
			S_KEY,nbsp(S_UPDATE_INTERVAL),
			S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,
			$show_applications == 1 ? S_APPLICATIONS : NULL,
			S_ERROR));

		$db_items = DBselect('select i.*,th.host as template_host,th.hostid as template_hostid from items i '.
			' left join items ti on i.templateid=ti.itemid left join hosts th on ti.hostid=th.hostid '.
			' where i.hostid='.$_REQUEST['hostid'].
			' order by th.host,i.description, i.key_');
		while($db_item = DBfetch($db_items))
		{
			$description = array();

			if($db_item["templateid"])
			{
				$template_host = get_realhost_by_itemid($db_item["templateid"]);
				array_push($description,		
					new CLink($template_host["host"],"items.php?".
						"hostid=".$template_host["hostid"],
						'uncnown'),
					":");
			}
			
			array_push($description, new CLink(
				item_description($db_item["description"],$db_item["key_"]),
				"items.php?form=update&itemid=".
				$db_item["itemid"].url_param("hostid").url_param("groupid"),
				'action'));

			$status=new CCol(new CLink(item_status2str($db_item["status"]),
					"items.php?group_itemid%5B%5D=".$db_item["itemid"].
					"&hostid=".$_REQUEST["hostid"].
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

			$applications = $show_applications == 1 ? implode(', ', get_applications_by_itemid($db_item["itemid"], 'name')) : null;

			$chkBox = new CCheckBox("group_itemid[]",NULL,NULL,$db_item["itemid"]);
			if($db_item["templateid"] > 0) $chkBox->SetEnabled(false);
			$table->AddRow(array(
				array($chkBox, $description),
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
		array_push($footerButtons, new CButton('group_task','Activate selected',
			"return Confirm('".S_ACTIVATE_SELECTED_ITEMS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('group_task','Disable selected',
			"return Confirm('".S_DISABLE_SELECTED_ITEMS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('group_task','Clean history selected items',
			"return Confirm('History cleaning can take a long time. Continue?');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('group_task','Delete selected',
			"return Confirm('".S_DELETE_SELECTED_ITEMS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('form_copy_to','Copy selected to ...'));
		$table->SetFooter(new CCol($footerButtons));

		$form->AddItem($table);
		$form->Show();

	}
?>
<?php

include_once "include/page_footer.php"

?>
