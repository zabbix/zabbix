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
	include "include/config.inc.php";
	include "include/forms.inc.php";

        $page["title"] = "S_CONFIGURATION_OF_ITEMS";
        $page["file"] = "items.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("Item","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,NULL),
		"hostid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,'isset({form})'),

		"add_groupid"=>	array(T_ZBX_INT, O_OPT,	 P_SYS,	DB_ID,'{register}=="go"'),
		"action"=>	array(T_ZBX_STR, O_OPT,	 P_SYS,	NOT_EMPTY,'{register}=="go"'),

		"itemid"=>	array(T_ZBX_INT, O_NO,	 P_SYS,	DB_ID,'{form}=="update"'),
		"description"=>	array(T_ZBX_STR, O_OPT,  NULL,	NOT_EMPTY,'isset({save})'),
		"key"=>		array(T_ZBX_STR, O_OPT,  NULL,  NOT_EMPTY,'isset({save})'),
		"delay"=>	array(T_ZBX_INT, O_OPT,  NULL,  BETWEEN(0,86400),'isset({save})&&{type}!=2'),
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
		"applications"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID, NULL),

		"showdisabled"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),	NULL),
		
		"del_history"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),

		"register"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"group_task"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);

	$_REQUEST["showdisabled"] = get_request("showdisabled", get_profile("web.latest.showdisabled", 0));
	
	check_fields($fields);

	$showdisabled = get_request("showdisabled", 0);

	validate_group_with_host("U",array("always_select_first_host"));
?>

<?php
	update_profile("web.menu.config.last",$page["file"]);
	update_profile("web.latest.showdisabled",$showdisabled);
?>

<?php
	$result = 0;
	if(isset($_REQUEST["delete"])&&isset($_REQUEST["itemid"]))
	{
		$result = delete_item($_REQUEST["itemid"]);
		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
		if($result){
			unset($_REQUEST["itemid"]);
			unset($_REQUEST["form"]);
		}
	}
	else if(isset($_REQUEST["save"]))
	{
		$applications = get_request("applications",array());
		if(isset($_REQUEST["itemid"]))
		{
			$result=update_item($_REQUEST["itemid"],
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],$applications);

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
				$_REQUEST["logtimefmt"],$_REQUEST["valuemapid"],$applications);

			$result = $itemid;
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
		}
		if($result){	
			unset($_REQUEST["itemid"]);
			unset($_REQUEST["form"]);
		}
	}
	elseif(isset($_REQUEST["del_history"])&&isset($_REQUEST["itemid"]))
	{
		$result = delete_history_by_itemid($_REQUEST["itemid"]);
		if($result)
		{
			DBexecute("update items set nextcheck=0,lastvalue=null,".
				"lastclock=null,prevvalue=null where itemid=".$_REQUEST["itemid"]);
		}
		show_messages($result, S_HISTORY_CLEANED, S_CANNOT_CLEAN_HISTORY);
		
	}
	elseif(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="do")
		{
			if($_REQUEST["action"]=="add to group")
			{
				$applications = get_request("applications",array());
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
					$applications);
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
					$applications);
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
/*
		else if($_REQUEST["register"]=="add to all hosts")
		{
			$result=DBselect("select hostid,host from hosts order by host");
			$hosts_ok="";
			$hosts_notok="";
			while($row=DBfetch($result))
			{
				$result2=add_item(
					$_REQUEST["description"],$_REQUEST["key"],$row["hostid"],
					$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],
					$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],
					$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],
					$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],
					$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],
					$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],
					$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"]);
				if($result2)
				{
					$hosts_ok=$hosts_ok." ".$row["host"];
				}
				else
				{
					$hosts_notok=$hosts_notok." ".$row["host"];
				}
			}
			show_messages(TRUE,"Items added]<br>[Success for '$hosts_ok']<br>".
				"[Failed for '$hosts_notok'","Cannot add item");
			unset($_REQUEST["itemid"]);
		}
*/
	}
	elseif(isset($_REQUEST["group_task"])&&isset($_REQUEST["group_itemid"]))
	{
		if($_REQUEST["group_task"]=="Delete selected")
		{
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				$item = get_item_by_itemid($id);
				if($item["templateid"]<>0)	continue;
				$result2=delete_item($id);
			}
			show_messages(TRUE, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
		}
		else if($_REQUEST["group_task"]=="Activate selected")
		{
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				$result2=activate_item($id);
			}
			show_messages(TRUE, S_ITEMS_ACTIVATED, S_CANNOT_ACTIVATE_ITEMS);
		}
		elseif($_REQUEST["group_task"]=="Disable selected")
		{
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				$result2=disable_item($id);
			}
			show_messages(TRUE, S_ITEMS_DISABLED, S_CANNOT_DISABLE_ITEMS);
		}
		elseif($_REQUEST["group_task"]=='Clean history selected items')
		{
			$group_itemid = $_REQUEST["group_itemid"];
			foreach($group_itemid as $id)
			{
				delete_history_by_itemid($id);
				DBexecute("update items set nextcheck=0,lastvalue=null,".
					"lastclock=null,prevvalue=null where itemid=$id");
			}
			show_messages(TRUE, S_HISTORY_CLEANED, S_CANNOT_CLEAN_HISTORY);
		}
	}
?>

<?php

	$form = new CForm();

	$form->AddVar("hostid",$_REQUEST["hostid"]);

	$form->AddItem(new CButton("form",S_CREATE_ITEM));

	show_header2(S_CONFIGURATION_OF_ITEMS_BIG, $form);
	echo BR;

	$db_hosts=DBselect("select hostid from hosts");
	if(isset($_REQUEST["form"])&&isset($_REQUEST["hostid"])&&DBfetch($db_hosts))
	{
// FORM
		insert_item_form();
	} else {

// Table HEADER
		$form = new CForm();
		
		$form->AddItem(array('[', 
			new CLink($showdisabled ? S_HIDE_DISABLED_ITEMS : S_SHOW_DISABLED_ITEMS,
				'items.php?showdisabled='.($showdisabled ? 0 : 1),'action'),
			']', SPACE));
		
		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit();");
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$result=DBselect("select groupid,name from groups order by name");
		while($row=DBfetch($result))
		{
	// Check if at least one host with read permission exists for this group
			$result2=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and".
				" h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host");
			while($row2=DBfetch($result2))
			{
				if(!check_right("Host","U",$row2["hostid"]))	continue;
				$cmbGroup->AddItem($row["groupid"],$row["name"]);
				break;
			}
		}
		$form->AddItem(S_GROUP.SPACE);
		$form->AddItem($cmbGroup);

		if(isset($_REQUEST["groupid"]) && $_REQUEST["groupid"]>0)
		{
			$sql="select h.hostid,h.host from hosts h,hosts_groups hg".
				" where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and".
				" h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
		}
		else
		{
			$sql="select h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED.
				" group by h.hostid,h.host order by h.host";
		}

		$result=DBselect($sql);

		$_REQUEST["hostid"] = get_request("hostid",0);
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit();");

		$correct_hostid='no';
		$first_hostid = -1;
		while($row=DBfetch($result))
		{
			if(!check_right("Host","U",$row["hostid"]))	continue;
			$cmbHosts->AddItem($row["hostid"],$row["host"]);

			if($_REQUEST["hostid"]!=0){
				if($_REQUEST["hostid"]==$row["hostid"])
					$correct_hostid = 'ok';
			}
			if($first_hostid <= 0)
				$first_hostid = $row["hostid"];
		}
		if($correct_hostid!='ok')
			$_REQUEST["hostid"] = $first_hostid;

		$form->AddItem(SPACE.S_HOST.SPACE);
		$form->AddItem($cmbHosts);
		
		show_header2(S_ITEMS_BIG, $form);

// TABLE
		$form = new CForm();
		$form->SetName('items');
		$form->AddVar('hostid',$_REQUEST["hostid"]);

		$show_applications = 1;

		$table  = new CTableInfo();
		$table->setHeader(array(
			array(	new CCheckBox("all_items",NULL,
					"CheckAll('".$form->GetName()."','all_items');"),
				S_ID),
			S_DESCRIPTION,S_KEY,nbsp(S_UPDATE_INTERVAL),
			S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,
			$show_applications == 1 ? S_APPLICATIONS : NULL,
			S_ERROR));

		$sql = "select i.* from hosts h,items i where h.hostid=i.hostid and".
			" h.hostid = ".$_REQUEST["hostid"];
			
		if($showdisabled == 0)
		    $sql .= " and i.status <> 1";
		    
		$sql .= " order by i.description, i.key_";
		
		$db_items = DBselect($sql);
		while($db_item = DBfetch($db_items))
		{
			if(!check_right("Item","U",$db_item["itemid"]))
			{
				continue;
			}

			if($db_item["templateid"]==0)
			{
				$description = new CLink(
					item_description($db_item["description"],$db_item["key_"]),
					"items.php?form=update&itemid=".
					$db_item["itemid"].url_param("hostid").url_param("groupid"),
					'action');
			} else {
				$template_host = get_realhost_by_itemid($db_item["templateid"]);
				$description = array(		
					new CLink($template_host["host"],"items.php?groupid=0".
						"&hostid=".$template_host["hostid"],
						'action'),
					":",
					item_description($db_item["description"],$db_item["key_"]),
					);
			}

			switch($db_item["type"]){
			case 0:	$type = S_ZABBIX_AGENT;			break;
			case 7:	$type = S_ZABBIX_AGENT_ACTIVE;		break;
			case 1:	$type = S_SNMPV1_AGENT;			break;
			case 2:	$type = S_ZABBIX_TRAPPER;		break;
			case 3:	$type = S_SIMPLE_CHECK;			break;
			case 4:	$type = S_SNMPV2_AGENT;			break;
			case 6:	$type = S_SNMPV3_AGENT;			break;
			case 5:	$type = S_ZABBIX_INTERNAL;		break;
			case 8:	$type = S_ZABBIX_AGGREGATE;		break;
			default:$type = S_UNKNOWN;			break;
			}

			switch($db_item["status"]){
			case 0:	$status=new CCol(new CLink(S_ACTIVE, 
					"items.php?group_itemid%5B%5D=".$db_item["itemid"].
					"&hostid=".$_REQUEST["hostid"].
					"&group_task=Disable+selected",
					"off"),"off");
				break;
			case 1:	$status=new CCol(new CLink(S_DISABLED,
					"items.php?group_itemid%5B%5D=".$db_item["itemid"].
					"&hostid=".$_REQUEST["hostid"].
					"&group_task=Activate+selected",
					"on"),"on");
				break;
			case 3:	$status=new CCol(new CLink(S_NOT_SUPPORTED,
					"items.php?group_itemid%5B%5D=".$db_item["itemid"].
					"&hostid=".$_REQUEST["hostid"].
					"&group_task=Activate+selected",
					"action")
					,"unknown");
				break;
			default:$status=S_UNKNOWN;
			}
	
			if($db_item["error"] == "")
			{
				$error=new CCol(SPACE,"off");
			}
			else
			{
				$error=new CCol($db_item["error"],"on");
			}

			$applications = "";
			$db_applications = get_applications_by_itemid($db_item["itemid"]);
			while($db_app = DBfetch($db_applications))
			{
				$applications .= $db_app["name"].", ";
			}

			$table->AddRow(array(
				array(
					new CCheckBox("group_itemid[]",NULL,NULL,$db_item["itemid"]),
					$db_item["itemid"]
				),
				$description,
				$db_item["key_"],
				$db_item["delay"],
				$db_item["history"],
				$db_item["trends"],
				$type,
				$status,
				$show_applications == 1 ? trim($applications,", ") : NULL,
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
		$table->SetFooter(new CCol($footerButtons));

		$form->AddItem($table);
		$form->Show();

	}
?>
<?php
	show_page_footer();
?>
