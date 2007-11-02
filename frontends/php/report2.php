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

	$page["title"]	= "S_AVAILABILITY_REPORT";
	$page["file"]	= "report2.php";

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"triggerid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL)
	);

	check_fields($fields);

//	validate_group_with_host(PERM_READ_LIST,array("always_select_first_host","monitored_hosts","with_items"));
	$options = array("allow_all_hosts","always_select_first_host","monitored_hosts","with_items");
	if(!$ZBX_WITH_SUBNODES)	array_push($options,"only_current_node");
	
	validate_group_with_host(PERM_READ_LIST,$options);
?>
<?php
	$r_form = new CForm();
	$r_form->SetMethod('get');

	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, get_current_nodeid());

	$result=DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h, items i ".
		" where h.hostid in (".$availiable_hosts.") ".
		" and hg.groupid=g.groupid and h.status=".HOST_STATUS_MONITORED.
		" and h.hostid=i.hostid and hg.hostid=h.hostid and i.status=".ITEM_STATUS_ACTIVE.
		" order by g.name");
	while($row=DBfetch($result))
	{
		$cmbGroup->AddItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row['name']
				);
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
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid and h.hostid in (".$availiable_hosts.") ".
			" group by h.hostid,h.host order by h.host";
	}
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbHosts->AddItem(
				$row['hostid'],
				get_node_name_by_elid($row['hostid']).$row['host']
				);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	show_table_header(S_AVAILABILITY_REPORT_BIG, $r_form);

?>
<?php
	if( isset($_REQUEST['triggerid']) &&
		!($trigger_data = DBfetch(DBselect('select distinct t.*, h.host from triggers t, functions f, items i, hosts h '.
					' where t.triggerid='.$_REQUEST['triggerid'].
					' and t.triggerid=f.triggerid and f.itemid=i.itemid and i.hostid=h.hostid ' 
					))) )
	{
		unset($_REQUEST['triggerid']);
	}

	if(isset($_REQUEST["triggerid"]))
	{
		if(!check_right_on_trigger_by_triggerid(PERM_READ_ONLY, $_REQUEST['triggerid']))
			access_deny();
		
		show_table_header(array(new CLink($row["host"],"?hostid=".$row["hostid"])," : \"",expand_trigger_description_by_data($trigger_data),"\""));

		$table = new CTableInfo(null,"graph");
		$table->AddRow(new CImg("chart4.php?triggerid=".$_REQUEST["triggerid"]));
		$table->Show();
	}
	else if(isset($_REQUEST["hostid"]))
	{
		$row	= DBfetch(DBselect("select host from hosts where hostid=".$_REQUEST["hostid"]));
		show_table_header($row["host"]);

		$result = DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.value ".
			" from triggers t,hosts h,items i,functions f ".
			" where f.itemid=i.itemid and h.hostid=i.hostid and t.status=".TRIGGER_STATUS_ENABLED.
			" and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]." and h.status=".HOST_STATUS_MONITORED.
			' and '.DBin_node('t.triggerid').
			" and i.status=".ITEM_STATUS_ACTIVE.
			" order by h.host, t.description");

		$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	
		$table = new CTableInfo();
		$table->setHeader(array(is_show_subnodes() ? S_NODE : null, S_NAME,S_TRUE,S_FALSE,S_UNKNOWN,S_GRAPH));
		while($row=DBfetch($result))
		{
			if(!check_right_on_trigger_by_triggerid(null, $row['triggerid'], $accessible_hosts))
				continue;

			$availability = calculate_availability($row["triggerid"],0,0);

			$true	= new CSpan(sprintf("%.4f%%",$availability["true"]), "on");
			$false	= new CSpan(sprintf("%.4f%%",$availability["false"]), "off");
			$unknown= new CSpan(sprintf("%.4f%%",$availability["unknown"]), "unknown");
			$actions= new CLink(S_SHOW,"report2.php?hostid=".$_REQUEST["hostid"]."&triggerid=".$row["triggerid"],"action");

			$table->addRow(array(
				get_node_name_by_elid($row['hostid']),
				new CLink(
					expand_trigger_description_by_data($row),
					"events.php?triggerid=".$row["triggerid"],"action"),
				$true,
				$false,
				$unknown,
				$actions
				));
		}
		$table->show();
	}
?>
<?php
	
	include_once "include/page_footer.php";

?>
