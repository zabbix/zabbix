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

	define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";

?>
<?php
	define("SHOW_TRIGGERS",0);
	define("SHOW_DATA",1);

	if(isset($_REQUEST["select"])&&($_REQUEST["select"]!=""))
	{
		unset($_REQUEST["groupid"]);
		unset($_REQUEST["hostid"]);
	}
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"type"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN("0,1"),		NULL)
	);

	check_fields($fields);

	validate_group(PERM_READ_ONLY,array("allow_all_hosts","monitored_hosts","with_monitored_items"));
?>
<?php
	$_REQUEST["type"] = get_request("type",get_profile("web.overview.type",SHOW_TRIGGERS));

	update_profile("web.overview.type",$_REQUEST["type"]);
?>
<?php
	$form = new CForm();
	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	if($_REQUEST["type"] == SHOW_TRIGGERS)
	{
		$from = ", functions f, triggers t";
		$where = " and i.itemid=f.itemid and f.triggerid=t.triggerid and t.status=".TRIGGER_STATUS_ENABLED;
	}
	else
	{
		$where = $from = '';
	}
	
	$result=DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h, items i".$from.
		" where g.groupid in (".
			get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST, null, null, $ZBX_CURNODEID).
		") ".
		" and hg.groupid=g.groupid and h.status=".HOST_STATUS_MONITORED.
		" and h.hostid=i.hostid and hg.hostid=h.hostid and i.status=".ITEM_STATUS_ACTIVE.
		$where.
		" order by g.name");
	while($row=DBfetch($result))
	{
		$cmbGroup->AddItem($row["groupid"],$row["name"]);
	}
	
	$form->AddItem(array(S_GROUP.SPACE,$cmbGroup));

	$cmbType = new CComboBox("type",$_REQUEST["type"],"submit()");
	$cmbType->AddItem(SHOW_TRIGGERS,S_TRIGGERS);
	$cmbType->AddItem(SHOW_DATA,	S_DATA);
	$form->AddItem(array(S_TYPE.SPACE,$cmbType));

	show_table_header(S_OVERVIEW_BIG, $form);
?>

<?php
	if($_REQUEST["type"]==SHOW_DATA)
	{
COpt::profiling_start("get_items_data_overview");
		$table = get_items_data_overview($_REQUEST["groupid"],$ZBX_CURNODEID);
COpt::profiling_stop("get_items_data_overview");
		$table->Show();
		unset($table);
	}
	elseif($_REQUEST["type"]==SHOW_TRIGGERS)
	{
COpt::profiling_start("get_triggers_overview");
		$table = get_triggers_overview($_REQUEST["groupid"], $ZBX_CURNODEID);
COpt::profiling_stop("get_triggers_overview");
		$table->Show();
		unset($table);
	}
?>
<?php

include_once "include/page_footer.php";

?>
