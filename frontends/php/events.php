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
	require_once "include/events.inc.php";

	$page["title"] = "S_LATEST_EVENTS";
	$page["file"] = "events.php";
	
	define('ZBX_PAGE_DO_REFRESH', 1);

include_once "include/page_header.php";

?>
<?php
	define('PAGE_SIZE',	100);
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"start"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".PAGE_SIZE."==0)",	NULL),
		"next"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL)
	);

	check_fields($fields);

	validate_group_with_host(PERM_READ_ONLY, array("allow_all_hosts","always_select_first_host","monitored_hosts","with_items"));
?>
<?php
	$_REQUEST["start"] = get_request("start", 0);
	if(isset($_REQUEST["prev"]))
	{
		$_REQUEST["start"] -= PAGE_SIZE;
	}
	if(isset($_REQUEST["next"]))
	{
		$_REQUEST["start"]	+= PAGE_SIZE;
	}
	if($_REQUEST["start"] < 0) $_REQUEST["start"] = 0;
?>
<?php
        $table = get_history_of_events($_REQUEST["start"],100,$_REQUEST["groupid"],$_REQUEST["hostid"]);
	
	$r_form = new CForm();

	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST, null, null, $ZBX_CURNODEID);

	$result=DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h, items i ".
		" where h.hostid in (".$availiable_hosts.") ".
		" and hg.groupid=g.groupid and h.status=".HOST_STATUS_MONITORED.
		" and h.hostid=i.hostid and hg.hostid=h.hostid ".
		" order by g.name");
	while($row=DBfetch($result))
	{
		$cmbGroup->AddItem($row["groupid"],$row["name"]);
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
	
	if($_REQUEST["groupid"] > 0)
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
			" and h.hostid in (".$availiable_hosts.") ".
			" group by h.hostid,h.host order by h.host";
	}
	else
	{
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid".
			" and h.hostid in (".$availiable_hosts.") ".
			" group by h.hostid,h.host order by h.host";
	}
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbHosts->AddItem($row["hostid"],$row["host"]);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	
	$r_form->AddVar("start",$_REQUEST["start"]);

	$btnPrev = new CButton("prev","<< Prev ".PAGE_SIZE);
	if($_REQUEST["start"] <= 0)
		$btnPrev->SetEnabled('no');
	$r_form->AddItem($btnPrev);

	$btnNext = new CButton("next","Next ".PAGE_SIZE." >>");
	if($table->GetNumRows() < PAGE_SIZE)
		$btnNext->SetEnabled('no');
	$r_form->AddItem($btnNext);
	
	show_table_header(S_HISTORY_OF_EVENTS_BIG,$r_form);

        $table->Show();
?>
<?php

include_once "include/page_footer.php";

?>
