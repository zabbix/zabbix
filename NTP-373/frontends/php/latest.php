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

	$page["title"] = "S_LATEST_VALUES";
	$page["file"] = "latest.php";
	define('ZBX_PAGE_DO_REFRESH', 1);
	
include_once "include/page_header.php";
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"applications"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		"applicationid"=>	array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		NULL),
		"close"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"open"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"groupbyapp"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),

		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		"select"=>		array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),

		"show"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,		NULL)
	);

	check_fields($fields);

	validate_group_with_host(PERM_READ_ONLY,array("allow_all_hosts","always_select_first_host","monitored_hosts","with_monitored_items"));
?>
<?php

	$_REQUEST["select"] = get_request("select","");

	$_REQUEST["groupbyapp"] = get_request("groupbyapp",get_profile("web.latest.groupbyapp",1));
	update_profile("web.latest.groupbyapp",$_REQUEST["groupbyapp"]);

	$_REQUEST["applications"] = get_request("applications",get_profile("web.latest.applications",array()),PROFILE_TYPE_ARRAY);

	if(isset($_REQUEST["open"]))
	{
		if(!isset($_REQUEST["applicationid"]))
		{
			$_REQUEST["applications"] = array();
			$show_all_apps = 1;
		}
		elseif(!in_array($_REQUEST["applicationid"],$_REQUEST["applications"]))
		{
			array_push($_REQUEST["applications"],$_REQUEST["applicationid"]);
		}
		
	} elseif(isset($_REQUEST["close"]))
	{
		if(!isset($_REQUEST["applicationid"]))
		{
			$_REQUEST["applications"] = array();
		}
		elseif(($i=array_search($_REQUEST["applicationid"], $_REQUEST["applications"])) !== FALSE)
		{
			unset($_REQUEST["applications"][$i]);
		}
	}

	/* limit opened application count */
	while(count($_REQUEST["applications"]) > 25)
	{
		array_shift($_REQUEST["applications"]);
	}


	update_profile("web.latest.applications",$_REQUEST["applications"],PROFILE_TYPE_ARRAY);
?>
<?php
	$r_form = new CForm();
	$r_form->SetMethod('get');

	$r_form->AddVar("select",$_REQUEST["select"]);

	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	
	$availiable_groups= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
	$availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());

	$result=DBselect("select distinct g.groupid,g.name from groups g, hosts_groups hg, hosts h, items i ".
		" where g.groupid in (".$availiable_groups.") ".
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
		$sql="select distinct h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" and h.hostid in (".$availiable_hosts.") ".
			" group by h.hostid,h.host order by h.host";
	}
	else
	{
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		$sql="select distinct h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and i.status=".ITEM_STATUS_ACTIVE." and h.hostid=i.hostid".
			" and h.hostid in (".$availiable_hosts.") ".
			" group by h.hostid,h.host order by h.host";
	}
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$cmbHosts->AddItem(
				$row['hostid'],
				get_node_name_by_elid($row['hostid']).$row["host"]
				);
	}

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	show_table_header(S_LATEST_DATA_BIG,$r_form);

	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$r_form->AddVar("hostid",$_REQUEST["hostid"]);
	$r_form->AddVar("groupid",$_REQUEST["groupid"]);

	$r_form->AddItem(array(S_SHOW_ITEMS_WITH_DESCRIPTION_LIKE, new CTextBox("select",$_REQUEST["select"],20)));
	$r_form->AddItem(array(SPACE, new CButton("show",S_SHOW)));

	show_table_header(NULL, $r_form);
?>
<?php
	if(isset($show_all_apps))
		$link = new CLink(new CImg("images/general/opened.gif"),
			"?close=1".
			url_param("groupid").url_param("hostid").url_param("applications").
			url_param("select"));
	else
		$link = new CLink(new CImg("images/general/closed.gif"),
			"?open=1".
			url_param("groupid").url_param("hostid").url_param("applications").
			url_param("select"));

	$table=new CTableInfo();
	$table->SetHeader(array(
		is_show_subnodes() ? S_NODE : null,
		$_REQUEST["hostid"] ==0 ? S_HOST : NULL,
		($link->ToString()).SPACE.S_DESCRIPTION,
		S_LAST_CHECK,S_LAST_VALUE,S_CHANGE,S_HISTORY));
	$table->ShowStart();

	$compare_host = ($_REQUEST["hostid"] > 0)?(' and h.hostid='.$_REQUEST['hostid']):'';

	$any_app_exist = false;
		
	$db_applications = DBselect("select distinct h.host,h.hostid,a.* from applications a,hosts h ".
		" where a.hostid=h.hostid".$compare_host.' and h.hostid in ('.$availiable_hosts.')'.
		" and h.status=".HOST_STATUS_MONITORED." order by a.name,a.applicationid,h.host");
	while($db_app = DBfetch($db_applications))
	{		
		$db_items = DBselect("select distinct i.* from items i,items_applications ia".
			" where ia.applicationid=".$db_app["applicationid"]." and i.itemid=ia.itemid".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" order by i.description, i.itemid");

		$app_rows = array();
		$item_cnt = 0;
		while($db_item = DBfetch($db_items))
		{
			$description = item_description($db_item["description"],$db_item["key_"]);

			if( '' != $_REQUEST["select"] && !stristr($description, $_REQUEST["select"]) ) continue;

			++$item_cnt;
			if(!in_array($db_app["applicationid"],$_REQUEST["applications"]) && !isset($show_all_apps)) continue;

			if(isset($db_item["lastclock"]))
				$lastclock=date(S_DATE_FORMAT_YMDHMS,$db_item["lastclock"]);
			else
				$lastclock = new CCol('-', 'center');

			$lastvalue=format_lastvalue($db_item);

			if( isset($db_item["lastvalue"]) && isset($db_item["prevvalue"]) &&
				($db_item["value_type"] == 0) && ($db_item["lastvalue"]-$db_item["prevvalue"] != 0) )
			{
				if($db_item["lastvalue"]-$db_item["prevvalue"]<0)
				{
					$change=convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
				}
				else
				{
					$change="+".convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
				}
				$change=nbsp($change);
			}
			else
			{
				$change=new CCol("-","center");
			}
			if(($db_item["value_type"]==ITEM_VALUE_TYPE_FLOAT) ||($db_item["value_type"]==ITEM_VALUE_TYPE_UINT64))
			{
				$actions=new CLink(S_GRAPH,"history.php?action=showgraph&itemid=".$db_item["itemid"],"action");
			}
			else
			{
				$actions=new CLink(S_HISTORY,"history.php?action=showvalues&period=3600&itemid=".$db_item["itemid"],"action");
			}
			array_push($app_rows, new CRow(array(
				is_show_subnodes() ? SPACE : null,
				$_REQUEST["hostid"] > 0 ? NULL : SPACE,
				str_repeat(SPACE,6).$description,
				$lastclock,
				new CCol($lastvalue, $lastvalue=='-' ? 'center' : null),
				$change,
				$actions
				)));
		}
		if($item_cnt > 0)
		{
			if(in_array($db_app["applicationid"],$_REQUEST["applications"]) || isset($show_all_apps))
				$link = new CLink(new CImg("images/general/opened.gif"),
					"?close=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			else
				$link = new CLink(new CImg("images/general/closed.gif"),
					"?open=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));

			$col = new CCol(array($link,SPACE,bold($db_app["name"]),
				SPACE."(".$item_cnt.SPACE.S_ITEMS.")"));
			$col->SetColSpan(5);

			$table->ShowRow(array(
					get_node_name_by_elid($db_app['hostid']),
					$_REQUEST["hostid"] > 0 ? NULL : $db_app["host"],
					$col
					));

			$any_app_exist = true;
		
			foreach($app_rows as $row)
				$table->ShowRow($row);
		}
	}
	

	$sql = 'SELECT DISTINCT h.host,h.hostid '.
			' FROM hosts h, items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
			' WHERE ia.itemid is NULL '.
				' AND h.hostid=i.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND i.status='.ITEM_STATUS_ACTIVE.
				$compare_host.
				' AND h.hostid in ('.$availiable_hosts.') '.
			' ORDER BY h.host';
		
	$db_appitems = DBselect($sql);
	
	while($db_appitem = DBfetch($db_appitems)){

		$sql = 'SELECT h.host,h.hostid,i.* '.
				' FROM hosts h, items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid'.
				' WHERE ia.itemid is NULL '.
					' AND h.hostid=i.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					$compare_host.
					' AND h.hostid='.$db_appitem['hostid'].
				' ORDER BY i.description,i.itemid';
				
		$db_items = DBselect($sql);
	
		$app_rows = array();
		$item_cnt = 0;
		
		while($db_item = DBfetch($db_items))
		{
			$description = item_description($db_item["description"],$db_item["key_"]);
	
			if( '' != $_REQUEST["select"] && !stristr($description, $_REQUEST["select"]) ) continue;
	
			++$item_cnt;
			if(!in_array(0,$_REQUEST["applications"]) && $any_app_exist && !isset($show_all_apps)) continue;
	
			if(isset($db_item["lastclock"]))
				$lastclock=zbx_date2str(S_DATE_FORMAT_YMDHMS,$db_item["lastclock"]);
			else
				$lastclock = new CCol('-', 'center');
	
			$lastvalue=format_lastvalue($db_item);
	
			if( isset($db_item["lastvalue"]) && isset($db_item["prevvalue"]) &&
				($db_item["value_type"] == ITEM_VALUE_TYPE_FLOAT || $db_item["value_type"] == ITEM_VALUE_TYPE_UINT64) &&
				($db_item["lastvalue"]-$db_item["prevvalue"] != 0) )
			{
				if($db_item["lastvalue"]-$db_item["prevvalue"]<0)
				{
					$change=convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
					$change=nbsp($change);
				}
				else
				{
					$change="+".convert_units($db_item["lastvalue"]-$db_item["prevvalue"],$db_item["units"]);
					$change=nbsp($change);
				}
			}
			else
			{
				$change=new CCol("-","center");
			}
			if(($db_item["value_type"]==ITEM_VALUE_TYPE_FLOAT) ||($db_item["value_type"]==ITEM_VALUE_TYPE_UINT64))
			{
				$actions=new CLink(S_GRAPH,"history.php?action=showgraph&itemid=".$db_item["itemid"],"action");
			}
			else
			{
				$actions=new CLink(S_HISTORY,"history.php?action=showvalues&period=3600&itemid=".$db_item["itemid"],"action");
			}
			array_push($app_rows, new CRow(array(
				is_show_subnodes() ? SPACE : null,//get_node_name_by_elid($db_item['itemid']) : null,
				$_REQUEST["hostid"] > 0 ? NULL : SPACE,//$db_item["host"],
				str_repeat(SPACE, ($any_app_exist ? 6 : 0)).$description,
				$lastclock,
				new CCol($lastvalue, $lastvalue == '-' ? 'center' : null),
				$change,
				$actions
				)));
		}
	
		if($item_cnt > 0)
		{
			if($any_app_exist)
			{
				if(in_array(0,$_REQUEST["applications"]) || isset($show_all_apps))
					$link = new CLink(new CImg("images/general/opened.gif"),
						"?close=1&applicationid=0".
						url_param("groupid").url_param("hostid").url_param("applications").
						url_param("select"));
				else
					$link = new CLink(new CImg("images/general/closed.gif"),
						"?open=1&applicationid=0".
						url_param("groupid").url_param("hostid").url_param("applications").
						url_param("select"));
	
				$col = new CCol(array($link,SPACE,bold(S_MINUS_OTHER_MINUS),
					SPACE."(".$item_cnt.SPACE.S_ITEMS.")"));
				$col->SetColSpan(5);
				
				$table->ShowRow(array(
						get_node_name_by_elid($db_appitem['hostid']),
						$_REQUEST["hostid"] > 0 ? NULL : $db_appitem["host"],
						$col
						));	
				}
			foreach($app_rows as $row)
				$table->ShowRow($row);
		}
	}
	
	$table->ShowEnd();
?>
<?php

include_once "include/page_footer.php";

?>
