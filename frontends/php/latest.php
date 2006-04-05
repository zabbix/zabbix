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
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"select"=>		array(T_ZBX_STR, O_OPT, NULL,	NULL,	NULL)
	);

//	check_fields($fields);
?>

<?php
        if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>
<?php
	$_REQUEST["groupid"] = get_request("groupid",0);
	$_REQUEST["select"] = get_request("select","");
	$_REQUEST["hostid"]=get_request("hostid",get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.view.last",$page["file"]);

	if($_REQUEST["hostid"] > 0)
	{
		$result=DBselect("select host from hosts where hostid=".$_REQUEST["hostid"]);
		if(DBnum_rows($result)==0)
		{
			$_REQUEST["hostid"] = 0;
		}
	}

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
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg".
			" where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid".
			" and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host".
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
			" group by h.hostid,h.host order by h.host";
	}
	else
	{
		$cmbHosts->AddItem(0,S_ALL_SMALL);
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED.
			" and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
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

		if($_REQUEST["hostid"]!=0){
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
	$table=new CTableInfo();
	$header=array(
		$_REQUEST["hostid"] ==0 ? S_HOST : NULL,
		S_DESCRIPTION,S_LAST_CHECK,S_LAST_VALUE,S_CHANGE,S_HISTORY);
	$table->SetHeader($header);

	if($_REQUEST["select"] != "")
		$compare_description = " and i.description like ".zbx_dbstr("%".$_REQUEST["select"]."%");
	else
		$compare_description = "";

	if($_REQUEST["hostid"] != 0)
		$compare_host = " and h.hostid=".$_REQUEST["hostid"];
	else
		$compare_host = "";

	$sql="select h.host,i.*,h.hostid from items i,hosts h".
		" where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED.
		" and i.status=0".$compare_description.$compare_host.
		" order by i.description";

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Item","R",$row["itemid"]))
		{
			continue;
		}
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}

		if(isset($row["lastclock"]))
			$lastclock=date(S_DATE_FORMAT_YMDHMS,$row["lastclock"]);
		else
			$lastclock="-";

		if(isset($row["lastvalue"]))
		{
			if(($row["value_type"] == ITEM_VALUE_TYPE_FLOAT) ||
				($row["value_type"] == ITEM_VALUE_TYPE_UINT64))
			{
				$lastvalue=convert_units($row["lastvalue"],$row["units"]);
			}
			else
			{
				$lastvalue=nbsp(htmlspecialchars(substr($row["lastvalue"],0,20)." ..."));
			}
			$lastvalue = replace_value_by_map($lastvalue, $row["valuemapid"]);
		}
		else
		{
			$lastvalue=new CCol("-","center");
		}
		if( isset($row["lastvalue"]) && isset($row["prevvalue"]) &&
			($row["value_type"] == 0) && ($row["lastvalue"]-$row["prevvalue"] != 0) )
		{
			if($row["lastvalue"]-$row["prevvalue"]<0)
			{
				$change=convert_units($row["lastvalue"]-$row["prevvalue"],$row["units"]);
				$change=nbsp($change);
			}
			else
			{
				$change="+".convert_units($row["lastvalue"]-$row["prevvalue"],$row["units"]);
				$change=nbsp($change);
			}
		}
		else
		{
			$change=new CCol("-","center");
		}
		if(($row["value_type"]==ITEM_VALUE_TYPE_FLOAT) ||($row["value_type"]==ITEM_VALUE_TYPE_UINT64))
		{
			$actions=new CLink(S_GRAPH,"history.php?action=showgraph&itemid=".$row["itemid"],"action");
		}
		else
		{
			$actions=new CLink(S_HISTORY,"history.php?action=showvalues&period=3600&itemid=".$row["itemid"],"action");
		}

		$table->addRow(array(
			$_REQUEST["hostid"] ==0 ? $row["host"] : NULL,
			item_description($row["description"],$row["key_"]),
			$lastclock,
			$lastvalue,
			$change,
			$actions
		));
	}
	$table->show();
?>

<?php
	show_page_footer();
?>
