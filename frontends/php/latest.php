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
	$page["title"] = "S_LATEST_VALUES";
	$page["file"] = "latest.php";
	show_header($page["title"],1,0);
?>
<?php
        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"applications"=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-2,4294967295),	NULL),
		"applicationid"=>	array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(-2,4294967295),	NULL),
		"close"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"open"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),
		"groupbyapp"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1"),	NULL),

		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"select"=>		array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL),

		"show"=>		array(T_ZBX_STR, O_OPT, NULL,   NULL,   NULL)
	);

	check_fields($fields);

	validate_group_with_host("R",array("allow_all_hosts","always_select_first_host","monitored_hosts","with_monitored_items"));
?>
<?php
        if($_REQUEST["hostid"] > 0 && !check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
	update_profile("web.menu.view.last",$page["file"]);
?>
<?php

	$_REQUEST["select"] = get_request("select","");

	$_REQUEST["groupbyapp"] = get_request("groupbyapp",get_profile("web.latest.groupbyapp",1));
	update_profile("web.latest.groupbyapp",$_REQUEST["groupbyapp"]);

	$_REQUEST["applications"] = get_request("applications",get_profile("web.latest.applications",array()),PROFILE_TYPE_ARRAY);

	if(isset($_REQUEST["open"]) && isset($_REQUEST["applicationid"]))
	{
		if($_REQUEST["applicationid"] == -1)
		{
			$_REQUEST["applications"] = array();
			$show_all_apps = 1;
		}
		elseif(!in_array($_REQUEST["applicationid"],$_REQUEST["applications"]))
		{
			array_push($_REQUEST["applications"],$_REQUEST["applicationid"]);
		}
		
	} elseif(isset($_REQUEST["close"]) && isset($_REQUEST["applicationid"]))
	{
		if($_REQUEST["applicationid"] == -1)
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

	$r_form->AddVar("select",$_REQUEST["select"]);

	$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
	$cmbGroup->AddItem(0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select distinct h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid".
			" and i.status=".ITEM_STATUS_ACTIVE." and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid".
			" order by h.host");
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
				continue;
			$cmbGroup->AddItem($row["groupid"],$row["name"]);
			break;
		}
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));

	$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

	if($_REQUEST["groupid"] > 0)
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid".
			" and i.status=".ITEM_STATUS_ACTIVE." group by h.hostid,h.host order by h.host";
	}
	else
	{
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and i.status=".ITEM_STATUS_ACTIVE." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}
	$result=DBselect($sql);
	$first_hostid = -1;
	$correct_hostid = 'no';
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
			continue;
		$cmbHosts->AddItem($row["hostid"],$row["host"]);

		if($first_hostid == -1) $first_hostid = $row["hostid"];

		if($_REQUEST["hostid"] > 0){
			if($_REQUEST["hostid"] == $row["hostid"])
				$correct_hostid = 'ok';
		}
	}
	if($correct_hostid == 'no' && $_REQUEST["groupid"] > 0)
		$_REQUEST["hostid"] = $first_hostid;

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	show_header2(S_LATEST_DATA_BIG,$r_form);

	$r_form = new CForm();

	$r_form->AddVar("hostid",$_REQUEST["hostid"]);
	$r_form->AddVar("groupid",$_REQUEST["groupid"]);

	$r_form->AddItem(array("Show items with description like ", new CTextBox("select",$_REQUEST["select"],20)));
	$r_form->AddItem(array(SPACE, new CButton("show",S_SHOW)));

	show_header2(NULL, $r_form);
?>
<?php
	if(isset($show_all_apps))
		$link = new CLink(new CImg("images/general/opened.gif"),
			"latest.php?close=1&applicationid=-1".
			url_param("groupid").url_param("hostid").url_param("applications").
			url_param("select"));
	else
		$link = new CLink(new CImg("images/general/closed.gif"),
			"latest.php?open=1&applicationid=-1".
			url_param("groupid").url_param("hostid").url_param("applications").
			url_param("select"));

	$table=new CTableInfo();
	$table->SetHeader(array(
		$_REQUEST["hostid"] ==0 ? S_HOST : NULL,
		($link->ToString()).SPACE.S_DESCRIPTION,
		S_LAST_CHECK,S_LAST_VALUE,S_CHANGE,S_HISTORY));
	$table->ShowStart();

	if($_REQUEST["select"] != "")
		$compare_description = " and i.description like ".zbx_dbstr("%".$_REQUEST["select"]."%");
	else
		$compare_description = "";

	if($_REQUEST["hostid"] > 0)
		$compare_host = " and h.hostid=".$_REQUEST["hostid"];
	else
		$compare_host = "";

	$any_app_exist = false;

	$db_applications = DBselect("select h.host,h.hostid,a.* from applications a,hosts h where a.hostid=h.hostid".$compare_host.
		" order by a.name,a.applicationid,h.host");
	while($db_app = DBfetch($db_applications))
	{
		if(!check_right("Application","R",$db_app["applicationid"]))	continue;

		$sql = "select i.* from items i,hosts h,items_applications ia".
			" where h.hostid=i.hostid and ia.applicationid=".$db_app["applicationid"]." and i.itemid=ia.itemid".
			" and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
			$compare_description.$compare_host.
			" order by i.description";

		$db_items = DBselect($sql);
		$app_rows = array();
		$item_cnt = 0;
		while($db_item = DBfetch($db_items))
		{
			if(!check_right("Item","R",$db_item["itemid"]))
			{
				continue;
			}
			if(!check_right("Host","R",$db_item["hostid"]))
			{
				continue;
			}

			++$item_cnt;
			if(!in_array($db_app["applicationid"],$_REQUEST["applications"]) && !isset($show_all_apps)) continue;

			if(isset($db_item["lastclock"]))
				$lastclock=date(S_DATE_FORMAT_YMDHMS,$db_item["lastclock"]);
			else
				$lastclock=new CCol("-","center");

			$lastvalue=format_lastvalue($db_item);

			if( isset($db_item["lastvalue"]) && isset($db_item["prevvalue"]) &&
				($db_item["value_type"] == 0) && ($db_item["lastvalue"]-$db_item["prevvalue"] != 0) )
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
				$_REQUEST["hostid"] > 0 ? NULL : SPACE,
				str_repeat(SPACE,6).item_description($db_item["description"],$db_item["key_"]),
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
					"latest.php?close=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			else
				$link = new CLink(new CImg("images/general/closed.gif"),
					"latest.php?open=1&applicationid=".$db_app["applicationid"].
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));

			$col = new CCol(array($link,SPACE,bold($db_app["name"]),
				SPACE."(".$item_cnt.SPACE.S_ITEMS.")"));
			$col->SetColSpan(5);

			$table->ShowRow(array($_REQUEST["hostid"] > 0 ? NULL : $db_app["host"], $col));

			$any_app_exist = true;
		
			foreach($app_rows as $row)
				$table->ShowRow($row);
		}
	}
	$sql="select h.host,h.hostid,i.* from hosts h, items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid".
		" where ia.itemid is NULL and h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED." and i.status=".ITEM_STATUS_ACTIVE.
		$compare_description.$compare_host.
		" order by i.description,h.host";
	$db_items = DBselect($sql);

	$app_rows = array();
	$item_cnt = 0;
	while($db_item = DBfetch($db_items))
	{
		if(!check_right("Host","R",$db_item["hostid"]))
		{
			continue;
		}
		if(!check_right("Item","R",$db_item["itemid"]))
		{
			continue;
		}

		++$item_cnt;
		if(!in_array(0,$_REQUEST["applications"]) && $any_app_exist && !isset($show_all_apps)) continue;


		if(isset($db_item["lastclock"]))
			$lastclock=date(S_DATE_FORMAT_YMDHMS,$db_item["lastclock"]);
		else
			$lastclock=new CCol("-","center");

		$lastvalue=format_lastvalue($db_item);

		if( isset($db_item["lastvalue"]) && isset($db_item["prevvalue"]) &&
			($db_item["value_type"] == 0) && ($db_item["lastvalue"]-$db_item["prevvalue"] != 0) )
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
			$_REQUEST["hostid"] > 0 ? NULL : $db_item["host"],
			str_repeat(SPACE, ($any_app_exist ? 6 : 0)).item_description($db_item["description"],$db_item["key_"]),
			$lastclock,
			new CCol($lastvalue, $lastvalue=='-' ? 'center' : null),
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
					"latest.php?close=1&applicationid=0".
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));
			else
				$link = new CLink(new CImg("images/general/closed.gif"),
					"latest.php?open=1&applicationid=0".
					url_param("groupid").url_param("hostid").url_param("applications").
					url_param("select"));

			$col = new CCol(array($link,SPACE,bold(S_MINUS_OTHER_MINUS),
				SPACE."(".$item_cnt.SPACE.S_ITEMS.")"));
			$col->SetColSpan(5);

			$table->ShowRow(array($_REQUEST["hostid"] > 0 ? NULL : SPACE, $col));
		}	
		foreach($app_rows as $row)
			$table->ShowRow($row);
	}

	$table->ShowEnd();
?>

<?php
	show_page_footer();
?>
