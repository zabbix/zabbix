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
	$page["title"] = S_LATEST_ALARMS;
	$page["file"] = "latestalarms.php";
	show_header($page["title"],30,0);
?>

<?php
	if(isset($_GET["start"])&&isset($_GET["do"])&&($_GET["do"]=="<< Prev 100"))
	{
		$_GET["start"]-=100;
	}
	if(isset($_GET["do"])&&($_GET["do"]=="Next 100 >>"))
	{
		if(isset($_GET["start"]))
		{
			$_GET["start"]+=100;
		}
		else
		{
			$_GET["start"]=100;
		}
	}
	if(isset($_GET["start"])&&($_GET["start"]<=0))
	{
		unset($_GET["start"]);
	}
?>

<?php
	show_table3_header_begin();
	echo "&nbsp;".S_HISTORY_OF_ALARMS_BIG;
	show_table3_h_delimiter(20);
	echo "<form name=\"form2\" method=\"get\" action=\"latestalarms.php\">";
	if(isset($_GET["start"]))
	{
		echo "<input class=\"biginput\" name=\"start\" type=hidden value=".$_GET["start"]." size=8>";
  		echo "<input class=\"button\" type=\"submit\" name=\"do\" value=\"<< Prev 100\">";
	}
	else
	{
  		echo "<input class=\"button\" type=\"submit\" disabled name=\"do\" value=\"<< Prev 100\">";
	}
  	echo "<input class=\"button\" type=\"submit\" name=\"do\" value=\"Next 100 >>\">";
	echo "</form>";
	show_table_header_end();
?>

<FONT COLOR="#000000">
<?php
	$sql="select max(alarmid) as max from alarms";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalarmid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($_GET["start"]))
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t where t.triggerid=a.triggerid and a.alarmid>$maxalarmid-200 order by clock desc limit 200";
	}
	else
	{
		$sql="select t.description,a.clock,a.value,t.triggerid,t.priority from alarms a,triggers t where t.triggerid=a.triggerid and a.alarmid>$maxalarmid-".($_GET["start"]+200)." order by clock desc limit ".($_GET["start"]+200);
	}
	$result=DBselect($sql);

//	echo "<TABLE WIDTH=100% align=center BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TABLE WIDTH=100% align=center BGCOLOR=\"#AAAAAA\" BORDER=0 cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=\"#CCCCCC\">";
	echo "<TD width=20%><b>".S_TIME."</b></TD>";
	echo "<TD><b>".S_DESCRIPTION."</b></TD>";
	echo "<TD width=10%><b>".S_VALUE."</b></TD>";
	echo "<TD width=10%><b>".S_SEVERITY."</b></TD>";
	echo "</TR>";
	$col=0;
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($_GET["start"])&&($i<$_GET["start"]))
		{
			continue;
		}
		if(!check_right_on_trigger("R",$row["triggerid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<tr bgcolor=#DDDDDD>"; }
		else		{ echo "<tr bgcolor=#EEEEEE>"; }

		if($col>100)	break;

		echo "<TD>",date("Y.M.d H:i:s",$row["clock"]),"</TD>";
//		$description=$row["description"];
//		if( strstr($description,"%s"))
//		{
			$description=expand_trigger_description($row["triggerid"]);
//		}
		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a></TD>";
//		echo "<TD><a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".htmlspecialchars($description)."</a></TD>";
		if($row["value"] == 0)
		{
			echo "<TD><font color=\"00AA00\">".S_OFF."</font></TD>";
		}
		elseif($row["value"] == 1)
		{
			echo "<TD><font color=\"AA0000\">".S_ON."</font></TD>";
		}
		else
		{
			echo "<TD><font color=\"AAAAAA\">".S_UNKNOWN_BIG."</font></TD>";
		}
		if($row["priority"]==0)         echo "<TD ALIGN=CENTER>".S_NOT_CLASSIFIED."</TD>";
		elseif($row["priority"]==1)     echo "<TD ALIGN=CENTER>".S_INFORMATION."</TD>";
		elseif($row["priority"]==2)     echo "<TD ALIGN=CENTER>".S_WARNING."</TD>";
		elseif($row["priority"]==3)     echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA>".S_AVERAGE."</TD>";
		elseif($row["priority"]==4)     echo "<TD ALIGN=CENTER BGCOLOR=#FF8888>".S_HIGH."</TD>";
		elseif($row["priority"]==5)     echo "<TD ALIGN=CENTER BGCOLOR=RED>".S_DISASTER."</TD>";
		else                            echo "<TD ALIGN=CENTER><B>".$row["priority"]."</B></TD>";
		echo "</TR>";
		cr();
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	show_footer();
?>
