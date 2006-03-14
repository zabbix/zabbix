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
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}
?>

<?php
	$_REQUEST["hostid"]=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	$h1=SPACE.S_LATEST_DATA_BIG;

	$h2=S_GROUP.SPACE;
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

	$h2=$h2.SPACE.S_HOST.SPACE;
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

	$h2=$h2.nbsp("  ");

	if(isset($_REQUEST["select"])&&($_REQUEST["select"]==""))
	{
		unset($_REQUEST["select"]);
	}
//	$h2=$h2.S_SELECT;
//	$h2=$h2.nbsp("  ");
	if(isset($_REQUEST["select"]))
	{
  		$h2=$h2."<input class=\"biginput\" type=\"text\" name=\"select\" value=\"".$_REQUEST["select"]."\">";
	}
	else
	{
 		$h2=$h2."<input class=\"biginput\" type=\"text\" name=\"select\" value=\"\">";
	}
	$h2=$h2.nbsp(" ");
  	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"select\">";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"latest.php\">", "</form>");
?>

<?php
	if(isset($_REQUEST["hostid"]))
	{
		$result=DBselect("select host from hosts where hostid=".$_REQUEST["hostid"]);
		if(DBnum_rows($result)==0)
		{
			unset($_REQUEST["hostid"]);
		}
	}

	if(isset($_REQUEST["hostid"])||isset($_REQUEST["select"]))
	{

//		echo "<br>";
		if(!isset($_REQUEST["select"])||($_REQUEST["select"] == ""))
		{
			$result=get_host_by_hostid($_REQUEST["hostid"]);
			$host=$result["host"];
//			show_table_header("<a href=\"latest.php?hostid=".$_REQUEST["hostid"]."\">$host</a>");
		}
		else
		{
//			show_table_header("Description is like *".$_REQUEST["select"]."*");
		}
#		show_table_header_begin();
#		echo "<a href=\"latest.php?hostid=".$_REQUEST["hostid"]."\">$host</a>";
#		show_table3_v_delimiter();

//		table_begin();
		$header=array();
		if(isset($_REQUEST["select"]))
		{
			$header=array_merge($header,array(S_HOST));
		}
		$header=array_merge($header,array(S_DESCRIPTION, S_LAST_CHECK, S_LAST_VALUE,S_CHANGE,S_HISTORY));

		$table=new CTableInfo();
		$table->setHeader($header);

//		table_header($header);

		$col=0;
		if(isset($_REQUEST["select"]))
			$sql="select h.host,i.*,h.hostid from items i,hosts h".
				" where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED.
				" and i.status=0 and i.description like ".zbx_dbstr("%",$_REQUEST["select"]."%").
				" order by i.description";
		else
			$sql="select h.host,i.*,h.hostid from items i,hosts h".
				" where h.hostid=i.hostid and h.status=".HOST_STATUS_MONITORED.
				" and i.status=0 and h.hostid=".$_REQUEST["hostid"]." order by i.description";

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

//			iif_echo($col++%2 == 1,
//				"<tr bgcolor=#DDDDDD>",
//				"<tr bgcolor=#EEEEEE>");

			$host=NULL;
			if(isset($_REQUEST["select"]))
			{
				$host=$row["host"];
			}
			$description = item_description($row["description"],$row["key_"]);

			if(isset($row["lastclock"]))
			{
				$lastclock=date(S_DATE_FORMAT_YMDHMS,$row["lastclock"]);
			}
			else
			{
				$lastclock="-";
			}

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
				$actions=new CLink(S_GRAPH,"history.php?action=showhistory&itemid=".$row["itemid"],"action");
			}
			else
			{
				$actions=new CLink(S_HISTORY,"history.php?action=showvalues&period=3600&itemid=".$row["itemid"],"action");
			}

			$table->addRow(array(
				$host,
				$description,
				$lastclock,
				$lastvalue,
				$change,
				$actions
			));
		}
		$table->show();
	}
?>

<?php
	show_page_footer();
?>
