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
	$page["file"]="history.php";

	include "include/config.inc.php";


	$now=time();

	$result=DBselect("select h.host,i.description,i.nextcheck-$now,h.hostid from items i,hosts h where i.itemid=".$_GET["itemid"]." and h.hostid=i.hostid");
	$host=DBget_field($result,0,0);
	$description=DBget_field($result,0,1);
	$beforenextcheck=DBget_field($result,0,2)+5;
	if($beforenextcheck<=0)
	{
		$beforenextcheck=5;
	}
	$hostid=DBget_field($result,0,3);

	if($_GET["action"]=="showhistory")
	{
		if(isset($_GET["year"]))
		{
			show_header("$host:$description",0,0);
		}
		else
		{
			show_header("$host:$description",$beforenextcheck,0);
		}
	}
	if($_GET["action"]=="showhistory2")
	{
		show_header("$host:$description",0,0);
	}
	if($_GET["action"]=="showvalues")
	{
		show_header("$host:$description",0,0);
	}
	if($_GET["action"]=="showfreehist")
	{
		show_header("$host:$description",0,0);
	}
	if($_GET["action"]=="showplaintxt")
	{
		show_header("$host:$description",0,0);
	}
	if($_GET["action"]=="plaintext")
	{
		show_header("$host:$description",0,1);
	}

	if(!check_right("Item","R",$_GET["itemid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}

?>

<?php
	if($_GET["action"]=="plaintext")
	{
		$from=mktime($_GET["fromhour"],$_GET["frommin"],0,$_GET["frommonth"],$_GET["fromday"],$_GET["fromyear"]);
		$till=mktime($_GET["tillhour"],$_GET["tillmin"],0,$_GET["tillmonth"],$_GET["tillday"],$_GET["tillyear"]);
		show_plaintext($_GET["itemid"], $from, $till);
		exit;
	}

?>

<?php
	$item=get_item_by_itemid($_GET["itemid"]);

	if($item["value_type"] == 0)
	{
		$h1="<A HREF='latest.php?hostid=$hostid'>$host</A> : <a href='trends.php?itemid=".$_GET["itemid"]."'>$description</a>";
	}
	else
	{
		$h1="<A HREF='latest.php?hostid=$hostid'>$host</A> : $description";
	}

	show_table_header($h1);

	$h1="";
	if($_GET["action"] =="showhistory")
	{
		$h1=$h1."<b>[<A HREF=\"history.php?action=showhistory&itemid=".$_GET["itemid"]."\">".S_LAST_HOUR_GRAPH."</A>]</b> ";
	}
	else
	{
		$item=get_item_by_itemid($_GET["itemid"]);
		if($item["value_type"] == 0)
		{
			$h1=$h1."<A HREF=\"history.php?action=showhistory&itemid=".$_GET["itemid"]."\">".S_LAST_HOUR_GRAPH."</A> ";
		}
	}
	if($_GET["action"] =="showhistory2")
	{
		$h1=$h1."<b>[<A HREF=\"history.php?action=showhistory2&itemid=".$_GET["itemid"]."\">".S_LAST_HOUR_GRAPH_DIFF."</A>]</b> ";
	}
	else
	{
		$item=get_item_by_itemid($_GET["itemid"]);
		if($item["value_type"] == 0)
		{
			$h1=$h1."<A HREF=\"history.php?action=showhistory2&itemid=".$_GET["itemid"]."\">".S_LAST_HOUR_GRAPH_DIFF."</A> ";
		}
	}
	if($_GET["action"] =="showvalues")
	{
		$h1=$h1."<b>[<A HREF=\"history.php?action=showvalues&itemid=".$_GET["itemid"]."&period=3600\">".S_VALUES_OF_LAST_HOUR."</A>]</b> ";
	}
	else
	{
		$h1=$h1."<A HREF=\"history.php?action=showvalues&itemid=".$_GET["itemid"]."&period=3600\">".S_VALUES_OF_LAST_HOUR."</A> ";
	}
	if($_GET["action"] =="showfreehist")
	{
		$h1=$h1."<b>[<A HREF=\"history.php?action=showfreehist&itemid=".$_GET["itemid"]."\">".S_VALUES_OF_SPECIFIED_PERIOD."</A>]</b> ";
	}
	else
	{
		$h1=$h1."<A HREF=\"history.php?action=showfreehist&itemid=".$_GET["itemid"]."\">".S_VALUES_OF_SPECIFIED_PERIOD."</A> ";
	}
	if($_GET["action"] =="showplaintxt")
	{
		$h1=$h1."<b>[<A HREF=\"history.php?action=showplaintxt&itemid=".$_GET["itemid"]."\">".S_VALUES_IN_PLAIN_TEXT_FORMAT."</A>]</b> ";
	}
	else
	{
		$h1=$h1."<A HREF=\"history.php?action=showplaintxt&itemid=".$_GET["itemid"]."\">".S_VALUES_IN_PLAIN_TEXT_FORMAT."</A> ";
	}
	show_table_header($h1);
	echo	"<br>";

	if($_GET["action"]=="showfreehist")
	{
		if(!isset($_GET["period"]))
		{
			show_freehist($_GET["itemid"],$_GET["period"]);
		} 
		exit;
 
	}

	if($_GET["action"]=="showplaintxt")
	{
		if(!isset($_GET["period"]))
		{
			show_plaintxt($_GET["itemid"],$_GET["period"]);
		} 
		exit;
   
	}

	if($_GET["action"]=="showvalues")
	{
		if(!isset($_GET["from"]))
		{
			$_GET["from"]=0;
		}
		if(!isset($_GET["period"]))
		{
			$_GET["period"]=3600;
		}
		$time=time(NULL)-$_GET["period"]-$_GET["from"]*3600;
		$till=time(NULL)-$_GET["from"]*3600;
		$hours=$_GET["period"]/3600;

		show_table_header("Showing history of ".$_GET["period"]." seconds($hours h)<BR>[from: ".date("Y.M.d H:i:s",$time)."] [till: ".date("Y.M.d H:i:s",$till)."]");

		echo "<TABLE BORDER=0 COLS=2 ALIGN=CENTER WIDTH=100% BGCOLOR=\"#AAAAAA\" cellspacing=1 cellpadding=3>";
		echo "<TR BGCOLOR=\"#CCCCCC\">";
		echo "<TD><B>".S_CLOCK."</B></TD>";
		echo "<TD><B>".S_VALUE."</B></TD>";
		echo "</TR>";

		$item=get_item_by_itemid($_GET["itemid"]);
		if($item["value_type"]==0)
		{
			$sql="select clock,value from history where itemid=".$_GET["itemid"]." and clock>$time and clock<$till order by clock desc";
		}
		else
		{
			$sql="select clock,value from history_str where itemid=".$_GET["itemid"]." and clock>$time and clock<$till order by clock desc";
		}
		$result=DBselect($sql);
		$col=0;
		for($i=0;$i<DBnum_rows($result);$i++)
		{
			if($col==1)
			{
				echo "<TR BGCOLOR=#DDDDDD>";
				$col=0;
			} else
			{
				echo "<TR BGCOLOR=#EEEEEE>";
				$col=1;
			}
			$clock=DBget_field($result,$i,0);
			$value=DBget_field($result,$i,1);
			$clock=date("Y.M.d H:i:s",$clock);
			echo "<TD>$clock</TD>";
			if($item["value_type"]==ITEM_VALUE_TYPE_FLOAT)
			{
				echo "<TD>$value</TD>";
			}
			else
			{
				echo "<TD>".htmlspecialchars($value)."</TD>";
			}
			echo "</TR>";
		}
		echo "</TABLE>";        
 
		show_footer();
		exit;
	}

	if($_GET["action"]=="showhistory")
	{
		if(!isset($_GET["period"]))
		{
			$_GET["period"]=3600;
		}
		if(!isset($_GET["from"]))
		{
			$_GET["from"]=0;
		}
		if(isset($_GET["year"]))
		{
			$_GET["from"]=($now-mktime($_GET["hour"], 0, 0, $_GET["month"], $_GET["day"],$_GET["year"]))/3600;
		}
		@show_history($_GET["itemid"],$_GET["from"],$_GET["period"],0);
		show_footer();
		exit;
	}

	if($_GET["action"]=="showhistory2")
	{
		@show_history($_GET["itemid"],$_GET["from"],$_GET["period"],1);
		show_footer();
		exit;
	}

?>
