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
        if(!check_anyright("Host","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php

//		VAR			TYPE	OPTIONAL TABLE	FIELD	OPTIONAL	VALIDATION	EXCEPTION
	$fields=array(
		"description"=>	array(T_ZBX_STR, O_MAND, "items", NULL,		NOT_EMPTY,		NULL),
		"delay"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,65535*65536),	NULL),
		"key"=>		array(T_ZBX_STR, O_MAND, "items", "key_",	NOT_EMPTY,		NULL),
		"host"=>	array(T_ZBX_STR, O_MAND, "items", NULL,		NOT_EMPTY,		NULL),
		"port"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,65535),	NULL),
		"history"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,10000),	NULL),
		"trends"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,10000),	NULL),
		"trends"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		IN("0,1,2"),		NULL),
		"type"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		IN("0,1,2"),		NULL),
		"snmp_community"=>array(T_ZBX_STR, O_MAND, "items", NULL,	NOT_EMPTY,		NULL),
		"snmp_oid"=>	array(T_ZBX_STR, O_MAND,   "items", NULL,	NOT_EMPTY,		NULL),
		"value_type"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		IN("0,1,2"),		NULL),
		"trapper_hosts"=>array(T_ZBX_STR, O_MAND,  "items", NULL,	NULL,			NULL),
		"snmp_port"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,65535),	NULL),
		"units"=>	array(T_ZBX_STR, O_MAND,  "items", NULL,	NULL,			NULL),
		"multiplier"=>	array(T_ZBX_DBL, O_MAND,  "items", NULL,	GT(0),			NULL),
		"hostid"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,65535*65536),	NULL),
		"snmpv3_securityname"=>array(T_ZBX_STR, O_MAND, "items", NULL,	NULL,			NULL),
		"snmpv3_authpassphrase"=>array(T_ZBX_STR, O_MAND, "items", NULL,NULL,			NULL),
		"snmpv3_privpassphrase"=>array(T_ZBX_STR, O_MAND, "items", NULL,NULL,			NULL),
		"formula"=>	array(T_ZBX_STR, O_MAND, "items", NULL,		NULL,			NULL),
		"logtimefmt"=>	array(T_ZBX_PERIOD, O_MAND, "items", NULL,	NULL,			NULL),
		"groupid"=>	array(T_ZBX_INT, O_MAND, "items", NULL,		BETWEEN(0,65535*65536),	NULL)
	);

//	if(!check_fields($fields))
//	{
//		show_messages();
//	}
?>

<?php
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
//		unset($_REQUEST["groupid"]);
	}
	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]==0))
	{
//		unset($_REQUEST["hostid"]);
	}
?>

<?php
	$_REQUEST["hostid"]=get_request("hostid",get_profile("web.latest.hostid",0));

	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["delete"])&&isset($_REQUEST["itemid"]))
	{
		delete_item_from_templates($_REQUEST["itemid"]);
		$result=delete_item($_REQUEST["itemid"]);
		show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
		unset($_REQUEST["itemid"]);
	}
	elseif(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="do")
		{
			if($_REQUEST["action"]=="add to group")
			{
				$itemid=add_item_to_group(
					$_REQUEST["add_groupid"],$_REQUEST["description"],$_REQUEST["key"],
					$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],
					$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],
					$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],
					$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],
					$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
					$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
					$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],
					$_REQUEST["trends"],$_REQUEST["logtimefmt"]);
				show_messages($itemid, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
				unset($_REQUEST["itemid"]);
				unset($itemid);
			}
			if($_REQUEST["action"]=="update in group")
			{
				$result=update_item_in_group($_REQUEST["add_groupid"],
					$_REQUEST["itemid"],$_REQUEST["description"],$_REQUEST["key"],
					$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],
					$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],
					$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],
					$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],
					$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
					$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
					$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],
					$_REQUEST["trends"],$_REQUEST["logtimefmt"]);
				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
				unset($_REQUEST["itemid"]);
			}
			if($_REQUEST["action"]=="delete from group")
			{
				$result=delete_item_from_group($_REQUEST["add_groupid"],$_REQUEST["itemid"]);
				show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
				unset($_REQUEST["itemid"]);
			}
		}
		else if($_REQUEST["register"]=="update")
		{
			$result=update_item($_REQUEST["itemid"],
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"]);
			update_item_in_templates($_REQUEST["itemid"]);
			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
//			unset($itemid);
			if($result){	
				unset($_REQUEST["itemid"]);
				unset($_REQUEST["form"]);
			}
		}
		else if($_REQUEST["register"]=="changestatus")
		{
			$result=update_item_status($_REQUEST["itemid"],$_REQUEST["status"]);
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			unset($_REQUEST["itemid"]);
		}
		else if($_REQUEST["register"]=="add")
		{
			$itemid=add_item(
				$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],
				$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],
				$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],
				$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],
				$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],
				$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],
				$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],
				$_REQUEST["logtimefmt"]);
			add_item_to_linked_hosts($itemid);
			show_messages($itemid, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			if($itemid){	
				unset($_REQUEST["itemid"]);
				unset($_REQUEST["form"]);
			}
			unset($itemid);
		}
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
		else if($_REQUEST["register"]=="Delete selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(!isset($_REQUEST[$row["itemid"]])) continue;
				delete_item_from_templates($row["itemid"]);
				$result2=delete_item($row["itemid"]);
			}
			show_messages(TRUE, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
		}
		else if($_REQUEST["register"]=="Activate selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(!isset($_REQUEST[$row["itemid"]]))	continue;
				$result2=activate_item($row["itemid"]);
			}
			show_messages(TRUE, S_ITEMS_ACTIVATED, S_CANNOT_ACTIVATE_ITEMS);
		}
		if($_REQUEST["register"]=="Disable selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(!isset($_REQUEST[$row["itemid"]]))	continue;
				$result2=disable_item($row["itemid"]);
			}
			show_messages(TRUE, S_ITEMS_DISABLED, S_CANNOT_DISABLE_ITEMS);
		}
	}
?>

<?php

	$db_hosts=DBselect("select hostid from hosts");
	if(isset($_REQUEST["form"])&&isset($_REQUEST["hostid"])&&DBnum_rows($db_hosts)>0)
	{
		insert_item_form();
	} else {
		$form = new CForm();

		$_REQUEST["groupid"] = get_request("groupid",0);
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
		$form->AddItem(SPACE."|".SPACE);
		$form->AddItem(new CButton("form",S_CREATE_ITEM));
		
		show_header2(S_CONFIGURATION_OF_ITEMS_BIG, $form);
	?>

	<?php

		if(isset($_REQUEST["hostid"])) 
		{
			$form = new CForm('items.php');
			$form->SetName('items');
			$form->AddVar('hostid',$_REQUEST["hostid"]);

			$table  = new CTableInfo();
			$table->setHeader(array(
				array(	new CCheckBox("all_items",NULL,NULL,
						"CheckAll('".$form->GetName()."','all_items');"),
					S_ID),
				S_KEY, S_DESCRIPTION,nbsp(S_UPDATE_INTERVAL),
				S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,S_ERROR));

			$result=DBselect("select h.host,i.key_,i.itemid,i.description,h.port,i.delay,".
				"i.history,i.lastvalue,i.lastclock,i.status,i.nextcheck,h.hostid,i.type,".
				"i.trends,i.error from hosts h,items i where h.hostid=i.hostid and".
				" h.hostid=".$_REQUEST["hostid"]." order by h.host,i.key_,i.description");
			while($row=DBfetch($result))
			{
				if(!check_right("Item","R",$row["itemid"]))
				{
					continue;
				}

				$input= array(
					new CCheckBox($row["itemid"]),
					new CLink($row["itemid"],"items.php?form=update&itemid=".
						$row["itemid"].	url_param("hostid").url_param("groupid"))
					);

				$key = new CLink($row["key_"],"items.php?form=update&itemid=".
					$row["itemid"].	url_param("hostid").url_param("groupid"));

				$description = new CLink($row["description"],"items.php?form=update&itemid=".
					$row["itemid"].url_param("hostid").url_param("groupid"));

				switch($row["type"]){
				case 0:	$type = S_ZABBIX_AGENT;			break;
				case 7:	$type = S_ZABBIX_AGENT_ACTIVE;		break;
				case 1:	$type = S_SNMPV1_AGENT;			break;
				case 2:	$type = S_ZABBIX_TRAPPER;		break;
				case 3:	$type = S_SIMPLE_CHECK;			break;
				case 4:	$type = S_SNMPV2_AGENT;			break;
				case 6:	$type = S_SNMPV3_AGENT;			break;
				case 5:	$type = S_ZABBIX_INTERNAL;		break;
				default:$type = S_UNKNOWN;			break;
				}

				switch($row["status"]){
				case 0:	$status=new CCol(new CLink(S_ACTIVE, "items.php?itemid=".$row["itemid"].
						"&hostid=".$_REQUEST["hostid"]."&register=changestatus&status=1",
						"off"),"off");
					break;
				case 1:	$status=new CCol(new CLink(S_ACTIVE, "items.php?itemid=".$row["itemid"].
						"&hostid=".$_REQUEST["hostid"]."&register=changestatus&status=0",
						"on"),"on");
					break;
				case 3:	$status=new CCol(S_NOT_SUPPORTED,"unknown");
					break;
				default:$status=S_UNKNOWN;
				}
		
				if($row["error"] == "")
				{
					$error=new CCol("&nbsp;","off");
				}
				else
				{
					$error=new CCol($row["error"],"on");
				}
				$table->AddRow(array(
					$input,
					$key,
					$description,
					$row["delay"],
					$row["history"],
					$row["trends"],
					$type,
					$status,
					$error
					));
			}

			$footerButtons = array();
			array_push($footerButtons, new CButton('register','Activate selected',
				"return Confirm('".S_ACTIVATE_SELECTED_ITEMS_Q."');"));
			array_push($footerButtons, SPACE);
			array_push($footerButtons, new CButton('register','Disable selected',
				"return Confirm('".S_DISABLE_SELECTED_ITEMS_Q."');"));
			array_push($footerButtons, SPACE);
			array_push($footerButtons, new CButton('register','Delete selected',
				"return Confirm('".S_DELETE_SELECTED_ITEMS_Q."');"));
			$table->SetFooter(new CCol($footerButtons),'table_footer');

			$form->AddItem($table);
			$form->Show();
		}

	}
?>
<?php
	show_page_footer();
?>
