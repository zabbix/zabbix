<?php
/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
	$page["title"] = S_LATEST_VALUES;
	$page["file"] = "latest.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
	if(isset($_GET["select"])&&($_GET["select"]!=""))
	{
		unset($_GET["groupid"]);
		unset($_GET["hostid"]);
	}
	
        if(isset($_GET["hostid"])&&!check_right("Host","R",$_GET["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}
?>

<?php
	show_table3_header_begin();
	echo "&nbsp;".S_LATEST_DATA;
	show_table3_h_delimiter(60);
?>
		<form name="form2" method="get" action="latest.php">

<?php
	echo S_GROUP."&nbsp;";
	echo "<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	echo "<option value=\"0\" ".iif(!isset($_GET["groupid"]),"selected","").">".S_ALL_SMALL;

	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status in (0,2) and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
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
			echo "<option value=\"".$row["groupid"]."\" ".iif(isset($_GET["groupid"])&&($_GET["groupid"]==$row["groupid"]),"selected","").">".$row["name"];
		}
	}
	echo "</select>";

	echo "&nbsp;".S_HOST."&nbsp;";
	echo "<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	echo "<option value=\"0\"".iif(!isset($_GET["hostid"])||($_GET["hostid"]==0),"selected","").">Select host...";

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status in (0,2) and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		echo "<option value=\"".$row["hostid"]."\"".iif(isset($_GET["hostid"])&&($_GET["hostid"]==$row["hostid"]),"selected","").">".$row["host"];
	}
	echo "</select>";

	echo nbsp("  ");

	if(isset($_GET["select"])&&($_GET["select"]==""))
	{
		unset($_GET["select"]);
	}
	if(isset($_GET["select"]))
	{
		echo $_GET["select"];
	}
	if(isset($_GET["select"]))
	{
  		echo "<input class=\"biginput\" type=\"text\" name=\"select\" value=\"".$_GET["select"]."\">";
	}
	else
	{
 		echo "<input class=\"biginput\" type=\"text\" name=\"select\" value=\"\">";
	}
	echo nbsp(" ");
  	echo "<input class=\"button\" type=\"submit\" name=\"do\" value=\"select\">";
	echo "</form>";
	show_table_header_end();
?>

<?php
	if(!isset($_GET["sort"]))
	{
		$_GET["sort"]="description";
	}

	if(isset($_GET["hostid"]))
	{
		$result=DBselect("select host from hosts where hostid=".$_GET["hostid"]);
		if(DBnum_rows($result)==0)
		{
			unset($_GET["hostid"]);
		}
	}

	if(isset($_GET["hostid"])||isset($_GET["select"]))
	{

//		echo "<br>";
		if(!isset($_GET["select"])||($_GET["select"] == ""))
		{
			$result=DBselect("select host from hosts where hostid=".$_GET["hostid"]);
			$host=DBget_field($result,0,0);
//			show_table_header("<a href=\"latest.php?hostid=".$_GET["hostid"]."\">$host</a>");
		}
		else
		{
//			show_table_header("Description is like *".$_GET["select"]."*");
		}
#		show_table_header_begin();
#		echo "<a href=\"latest.php?hostid=".$_GET["hostid"]."\">$host</a>";
#		show_table3_v_delimiter();

		echo "<TABLE BORDER=0 COLS=4 WIDTH=100% BGCOLOR=\"AAAAAA\" cellspacing=1 cellpadding=3>";
//		echo "<TABLE BORDER=0 COLS=4 WIDTH=100% cellspacing=1 cellpadding=3>";
		cr();
		echo "<TR BGCOLOR=\"CCCCCC\">";
		cr();
		if(isset($_GET["select"]))
		{
			echo "<TD><B>Host</B></TD>";
		}
		if(!isset($_GET["sort"])||(isset($_GET["sort"])&&($_GET["sort"]=="description")))
		{
			echo "<TD><B>".S_DESCRIPTION_LARGE."</B></TD>";
		}
		else
		{
			iif_echo(isset($_GET["select"]),
				"<TD><B><a href=\"latest.php?select=".$_GET["select"]."&sort=description\">".S_DESCRIPTION_SMALL."</B></TD>",
				"<TD><B><a href=\"latest.php?hostid=".$_GET["hostid"]."&sort=description\">".S_DESCRIPTION_SMALL."</B></TD>");
		}
		if(isset($_GET["sort"])&&($_GET["sort"]=="lastcheck"))
		{
			echo "<TD WIDTH=12%><B>LAST CHECK</B></TD>";
		}
		else
		{
			if(isset($_GET["select"]))
				echo "<TD WIDTH=12%><B><a href=\"latest.php?select=".$_GET["select"]."&sort=lastcheck\">Last check</B></TD>";
			else
				echo "<TD WIDTH=12%><B><a href=\"latest.php?hostid=".$_GET["hostid"]."&sort=lastcheck\">Last check</B></TD>";
		}
		cr();
		echo "<TD WIDTH=10%><B>Last value</B></TD>"; 
		cr();
		echo "<TD WIDTH=10%><B>Change</B></TD>"; 
		cr();
		echo "<TD WIDTH=5% align=center><B>History</B></TD>";
		cr();
		echo "<TD WIDTH=5% align=center><B>Trends</B></TD>";
		cr();
		echo "<TD WIDTH=5% align=center><B>Compare</B></TD>";
		cr();
		echo "</TR>";
		cr();

		$col=0;
		if(isset($_GET["sort"]))
		{
			switch ($_GET["sort"])
			{
				case "description":
					$_GET["sort"]="order by i.description";
					break;
				case "lastcheck":
					$_GET["sort"]="order by i.lastclock";
					break;
				default:
					$_GET["sort"]="order by i.description";
					break;
			}
		}
		else
		{
			$_GET["sort"]="order by i.description";
		}
		if(isset($_GET["select"]))
			$sql="select h.host,i.itemid,i.description,i.lastvalue,i.prevvalue,i.lastclock,i.status,h.hostid,i.value_type,i.units,i.multiplier from items i,hosts h where h.hostid=i.hostid and h.status in (0,2) and i.status=0 and i.description like '%".$_GET["select"]."%' ".$_GET["sort"];
		else
			$sql="select h.host,i.itemid,i.description,i.lastvalue,i.prevvalue,i.lastclock,i.status,h.hostid,i.value_type,i.units,i.multiplier from items i,hosts h where h.hostid=i.hostid and h.status in (0,2) and i.status=0 and h.hostid=".$_GET["hostid"]." ".$_GET["sort"];
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
			iif_echo($col++%2 == 1,
				"<tr bgcolor=#DDDDDD>",
				"<tr bgcolor=#EEEEEE>");

			if(isset($_GET["select"]))
			{
				table_td($row["host"],"");
			}
			table_td($row["description"],"");

			echo "<td>";
			if($row["status"] == 2)
			{
				echo "<font color=\"#FF6666\">";
			}

			iif_echo(!isset($row["lastclock"]),
				"<div align=center>-</div>",
				date("d M H:i:s",$row["lastclock"]));
			echo "</font></td>";

			if(isset($row["lastvalue"]))
			{
				iif_echo($row["value_type"] == 0,
					"<td>".convert_units($row["lastvalue"],$row["units"])."</td>",
					"<td>".nbsp(htmlspecialchars(substr($row["lastvalue"],0,20)." ..."))."</td>");
			}
			else
			{
				table_td("-","align=center");
			}
			if( isset($row["lastvalue"]) && isset($row["prevvalue"]) &&
				($row["value_type"] == 0) && ($row["lastvalue"]-$row["prevvalue"] != 0) )
			{
//				echo "<td>"; echo $row["lastvalue"]-$row["prevvalue"]; echo "</td>";
//	sprintf("%+0.2f"); does not work
				if($row["lastvalue"]-$row["prevvalue"]<0)
				{
					$str=convert_units($row["lastvalue"]-$row["prevvalue"],$row["units"]);
					$str=nbsp($str);
					table_td($str,"");
				}
				else
				{
					$str="+".convert_units($row["lastvalue"]-$row["prevvalue"],$row["units"]);
					$str=nbsp($str);
					table_td($str,"");
//					printf("<td>+%0.2f</td>",$row["lastvalue"]-$row["prevvalue"]);
				}
			}
			else
			{
				echo "<td align=center>-</td>";
			}
			iif_echo($row["value_type"]==0,
				"<td align=center><a href=\"history.php?action=showhistory&itemid=".$row["itemid"]."\">".S_GRAPH."</a></td>",
				"<td align=center><a href=\"history.php?action=showvalues&period=3600&itemid=".$row["itemid"]."\">".S_HISTORY."</a></td>");

			iif_echo($row["value_type"]==0,
				"<td align=center><a href=\"trends.php?itemid=".$row["itemid"]."\">".S_TREND."</a></td>",
				"<td align=center>-</td>");

			iif_echo($row["value_type"]==0,
				"<td align=center><a href=\"compare.php?itemid=".$row["itemid"]."\">".S_COMPARE."</a></td>",
				"<td align=center>-</td>");

			echo "</tr>";
			cr();
		}
		echo "</table>";
		show_table_header_end();
	}
	else
	{
		echo "<TABLE BORDER=0 align=center COLS=4 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=#DDDDDD>";
		echo "<TD ALIGN=CENTER>";
		echo "...";
		echo "</TD>";
		echo "</TR>";
		echo "</TABLE>";
	}
?>

<?php
	show_footer();
?>
