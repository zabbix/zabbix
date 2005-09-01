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
	navigation_bar_calc();
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
	if(!isset($_GET["config"]))
	{
		$_GET["config"]=0;
	}

#	$h1=S_CONFIGURATION_OF_USERS_AND_USER_GROUPS;
	$item=get_item_by_itemid($_GET["itemid"]);

	$h1="<A HREF='latest.php?hostid=$hostid'>$host</A> : $description";

#	$h2=S_GROUP."&nbsp;";
	$h2="<input class=\"biginput\" name=\"itemid\" type=\"hidden\" value=\"".$_GET["itemid"]."\">";
	$h2=$h2."<select class=\"biginput\" name=\"action\" onChange=\"submit()\">";
	$h2=$h2.form_select("action","showhistory",S_LAST_HOUR_GRAPH);
	$h2=$h2.form_select("action","showhistory2",S_LAST_HOUR_GRAPH_DIFF);
	$h2=$h2.form_select("action","showvalues",S_VALUES_OF_LAST_HOUR);
	$h2=$h2.form_select("action","showfreehist",S_VALUES_OF_SPECIFIED_PERIOD);
	$h2=$h2.form_select("action","showplaintxt",S_VALUES_IN_PLAIN_TEXT_FORMAT);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"history.php\">", "</form>");
?>

<?php
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
		echo "<TD><B>".S_TIMESTAMP."</B></TD>";
		if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
		{
			echo "<TD><B>".S_LOCAL."</B></TD>";
			echo "<TD><B>".S_SOURCE."</B></TD>";
			echo "<TD><B>".S_SEVERITY."</B></TD>";
		}
		echo "<TD><B>".S_VALUE."</B></TD>";
		echo "</TR>";

		$item=get_item_by_itemid($_GET["itemid"]);
		if($item["value_type"]==ITEM_VALUE_TYPE_FLOAT)
		{
			$sql="select clock,value from history where itemid=".$_GET["itemid"]." and clock>$time and clock<$till order by clock desc";
		}
		else if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
		{
			$sql="select clock,value,timestamp,source,severity from history_log where itemid=".$_GET["itemid"]." and clock>$time and clock<$till order by id desc, clock desc";
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
			if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
			{
				$local=DBget_field($result,$i,2);
				$source=DBget_field($result,$i,3);
				$severity=DBget_field($result,$i,4);

				if($local==0)
				{
					echo "<TD>&nbsp;</TD>";
				}
				else
				{
					echo "<TD>".date("Y.M.d H:i:s",$local)."</TD>";
				}
				echo "<TD>$source</TD>";

		                if($severity==0)         $severity=S_NOT_CLASSIFIED;
		                elseif($severity==1)     $severity=S_INFORMATION;
		                elseif($severity==2)     $severity=S_WARNING;
		                elseif($severity==3)     $severity=array("value"=>S_AVERAGE,"class"=>"average");
		                elseif($severity==4)     $severity=array("value"=>S_HIGH,"class"=>"high");
		                elseif($severity==5)     $severity=array("value"=>S_DISASTER,"class"=>"disaster");
		                elseif($severity==6)     $severity=S_AUDIT_SUCCESS;
		                elseif($severity==7)     $severity=S_AUDIT_FAILURE;
		                else                            $severity=$row["priority"];

				echo "<TD>$severity</TD>";
			}
			if($item["value_type"]==ITEM_VALUE_TYPE_FLOAT)
			{
				echo "<TD>$value</TD>";
			}
			else
			{
				echo "<TD><pre>".htmlspecialchars($value)."</pre></TD>";
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
