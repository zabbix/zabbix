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
	$page["title"] = "S_AVAILABILITY_REPORT";
	$page["file"] = "report2.php";
	show_header($page["title"],0,0);
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
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL),
		"triggerid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,			NULL)
	);

	check_fields($fields);
?>

<?php
	update_profile("web.menu.reports.last",$page["file"]);
?>

<?php
	$h1="&nbsp;".S_AVAILABILITY_REPORT_BIG;

	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
			{
				continue;
			}
			$cnt=1; break;
		}
		if($cnt!=0)
		{
			$h2=$h2.form_select("groupid",$row["groupid"],$row["name"]);
		}
	}
	$h2=$h2."</select>";

	$h2=$h2."&nbsp;".S_HOST."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid and hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"report2.php\">", "</form>");
?>

<?php
	if(isset($_REQUEST["hostid"])&&!isset($_REQUEST["triggerid"]))
	{
		echo "<br>";
		$result=DBselect("select host from hosts where hostid=".$_REQUEST["hostid"]);
		$row=DBfetch($result);
		show_table_header($row["host"]);

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.value from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.status=0 and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]." and h.status=".HOST_STATUS_MONITORED." and i.status=0 order by h.host, t.description");

		$table = new CTableInfo();
		$table->setHeader(array(S_NAME,S_TRUE,S_FALSE,S_UNKNOWN,S_GRAPH));
		while($row=DBfetch($result))
		{
			if(!check_right_on_trigger("R",$row["triggerid"])) 
			{
				continue;
			}
			$lasthost=$row["host"];

			$description=expand_trigger_description($row["triggerid"]);
			$description=new CLink($description,"alarms.php?triggerid=".$row["triggerid"],"action");
	
			$availability=calculate_availability($row["triggerid"],0,0);

			$true=new CSpan(sprintf("%.4f%%",$availability["true"]), "on");
			$false=new CSpan(sprintf("%.4f%%",$availability["false"]), "off");
			$unknown=new CSpan(sprintf("%.4f%%",$availability["unknown"]), "unknown");
			$actions=new CLink(S_SHOW,"report2.php?hostid=".$_REQUEST["hostid"]."&triggerid=".$row["triggerid"],"action");

			$table->addRow(array(
				$description,
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
	if(isset($_REQUEST["triggerid"]))
	{
		echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#EEEEEE>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "<IMG SRC=\"chart4.php?triggerid=".$_REQUEST["triggerid"]."\" border=0>";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>


<?php
	show_page_footer();
?>
