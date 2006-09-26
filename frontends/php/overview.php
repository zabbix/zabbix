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
	require_once "include/triggers.inc.php";
	require_once "include/items.inc.php";

	$page["title"] = "S_OVERVIEW";
	$page["file"] = "overview.php";
	show_header($page["title"],1,0);
?>

<?php
	define("SHOW_TRIGGERS",0);
	define("SHOW_DATA",1);
?>


<?php
        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
	if(isset($_REQUEST["select"])&&($_REQUEST["select"]!=""))
	{
		unset($_REQUEST["groupid"]);
		unset($_REQUEST["hostid"]);
	}
	
        if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"type"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),		NULL)
	);

	check_fields($fields);

	validate_group("R",array("allow_all_hosts","monitored_hosts","with_monitored_items"));
?>

<?php
	$_REQUEST["type"] = get_request("type",get_profile("web.overview.type",0));

	update_profile("web.overview.type",$_REQUEST["type"]);
?>

<?php

	$form = new CForm();
	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups where mod(groupid,100)=$ZBX_CURNODEID order by name");
	while($row=DBfetch($result))
	{
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where".
			" h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"].
			" and i.status=".ITEM_STATUS_ACTIVE." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))	continue;
			$cmbGroup->AddItem($row["groupid"],$row["name"]);
			break;
		}
	}
	$form->AddItem(array(S_GROUP.SPACE,$cmbGroup));

	$cmbType = new CComboBox("type",$_REQUEST["type"],"submit()");
	$cmbType->AddItem(0,S_TRIGGERS);
	$cmbType->AddItem(1,S_DATA);
	$form->AddItem(array(S_TYPE.SPACE,$cmbType));

	show_header2(S_OVERVIEW_BIG, $form);
?>

<?php
	if($_REQUEST["type"]==SHOW_DATA)
	{
COpt::profiling_start("get_items_data_overview");
		$table = get_items_data_overview($_REQUEST["groupid"]);
COpt::profiling_stop("get_items_data_overview");
		$table->Show();
		unset($table);
	}
	elseif($_REQUEST["type"]==SHOW_TRIGGERS)
	{
COpt::profiling_start("get_triggers_overview");
		$table = get_triggers_overview($_REQUEST["groupid"]);
COpt::profiling_stop("get_triggers_overview");
		$table->Show();
		unset($table);
	}
?>

<?php
	show_page_footer();
?>
