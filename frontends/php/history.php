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

	$result=DBselect("select h.host,i.description,i.nextcheck-$now as nextcheck,h.hostid,i.key_ from items i,hosts h where i.itemid=".$_REQUEST["itemid"]." and h.hostid=i.hostid");
	$row=DBfetch($result);
	$host=$row["host"];
	$description=item_description($row["description"],$row["key_"]);
	$beforenextcheck=$row["nextcheck"]+5;
	if($beforenextcheck<=0)
	{
		$beforenextcheck=5;
	}
	$hostid=$row["hostid"];

	if($_REQUEST["action"]=="showhistory")
	{
		if(isset($_REQUEST["year"]))
		{
			show_header("$host:$description",0,0);
		}
		else
		{
//			show_header("$host:$description",$beforenextcheck,0);
			show_header("$host:$description",1,0);
		}
	}
	if($_REQUEST["action"]=="showvalues")
	{
		show_header("$host:$description",0,0);
	}
	if($_REQUEST["action"]=="showlatest")
	{
		show_header("$host:$description",0,0);
	}
	if($_REQUEST["action"]=="showfreehist")
	{
		show_header("$host:$description",0,0);
	}
	if($_REQUEST["action"]=="showplaintxt")
	{
		show_header("$host:$description",0,0);
	}
	if($_REQUEST["action"]=="plaintext")
	{
		show_header("$host:$description",0,1);
	}

	if(!check_right("Item","R",$_REQUEST["itemid"]))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}

?>

<?php
	$effectiveperiod=navigation_bar_calc();
?>

<?php
	if($_REQUEST["action"]=="plaintext")
	{
		$from=mktime($_REQUEST["fromhour"],$_REQUEST["frommin"],0,$_REQUEST["frommonth"],$_REQUEST["fromday"],$_REQUEST["fromyear"]);
		$till=mktime($_REQUEST["tillhour"],$_REQUEST["tillmin"],0,$_REQUEST["tillmonth"],$_REQUEST["tillday"],$_REQUEST["tillyear"]);
		show_plaintext($_REQUEST["itemid"], $from, $till);
		exit;
	}

?>

<?php
	if(!isset($_REQUEST["config"]))
	{
		$_REQUEST["config"]=0;
	}

#	$h1=S_CONFIGURATION_OF_USERS_AND_USER_GROUPS;
	$item=get_item_by_itemid($_REQUEST["itemid"]);

	$h1="<A HREF='latest.php?hostid=$hostid'>$host</A> : $description";

#	$h2=S_GROUP.SPACE
	$h2="<input class=\"biginput\" name=\"itemid\" type=\"hidden\" value=\"".$_REQUEST["itemid"]."\">";
	$h2=$h2."<select class=\"biginput\" name=\"action\" onChange=\"submit()\">";
	$h2=$h2.form_select("action","showhistory",S_LAST_HOUR_GRAPH);
	$h2=$h2.form_select("action","showvalues",S_VALUES_OF_LAST_HOUR);
	$h2=$h2.form_select("action","showlatest",S_500_LATEST_VALUES);
	$h2=$h2.form_select("action","showfreehist",S_VALUES_OF_SPECIFIED_PERIOD);
	$h2=$h2.form_select("action","showplaintxt",S_VALUES_IN_PLAIN_TEXT_FORMAT);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"history.php\">", "</form>");
?>

<?php
	if($_REQUEST["action"]=="showfreehist")
	{
		show_freehist($_REQUEST["itemid"],$effectiveperiod);
		exit;
	}

	if($_REQUEST["action"]=="showplaintxt")
	{
//		if(!isset($effectiveperiod))
		{
			show_plaintxt($_REQUEST["itemid"],$effectiveperiod);
		} 
		exit;
   
	}

	if($_REQUEST["action"]=="showvalues")
	{
		if(!isset($_REQUEST["from"]))
		{
			$_REQUEST["from"]=0;
		}
		if(!isset($effectiveperiod))
		{
			$effectiveperiod=3600;
		}
		$time=time(NULL)-$effectiveperiod-$_REQUEST["from"]*3600;
		$till=time(NULL)-$_REQUEST["from"]*3600;
		$hours=$effectiveperiod/3600;

		show_table_header("Showing history of ".$effectiveperiod." seconds($hours h)<BR>[from: ".date("Y.M.d H:i:s",$time)."] [till: ".date("Y.M.d H:i:s",$till)."]");

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

		$item=get_item_by_itemid($_REQUEST["itemid"]);
		if($item["value_type"]==ITEM_VALUE_TYPE_FLOAT)
		{
			$sql="select h.clock,h.value,i.valuemapid from history h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" and h.clock>$time and h.clock<$till order by clock desc";
		}
		else if($item["value_type"]==ITEM_VALUE_TYPE_UINT64)
		{
			$sql="select h.clock,h.value,i.valuemapid from history_uint h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" and h.clock>$time and h.clock<$till order by clock desc";
		}
		else if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
		{
			$sql="select h.clock,h.value,h.timestamp,h.source,h.severity,i.valuemapid".
				" from history_log h, items i".
				" where h.itemid=i.itemid and itemid=".$_REQUEST["itemid"].
				" and h.clock>$time and h.clock<$till order by id desc, clock desc";
		}
		else
		{
			$sql="select h.clock,h.value,i.valuemapid from history_str h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" and h.clock>$time and h.clock<$till order by clock desc";
		}
		$result=DBselect($sql);
		$col=0;
		while($row=DBfetch($result))
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
			$clock=$row["clock"];
			$value=replace_value_by_map($row["value"],$row["valuemapid"]);
			$clock=date("Y.M.d H:i:s",$clock);
			echo "<TD>$clock</TD>";
			if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
			{
				$local=$row["timestamp"];
				$source=$row["source"];
				$severity=$row["severity"];

				if($local==0)
				{
					echo "<TD>".SPACE."</TD>";
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
			else if($item["value_type"]==ITEM_VALUE_TYPE_UINT64)
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
 
		show_page_footer();
		exit;
	}

	if($_REQUEST["action"]=="showhistory")
	{
		if(!isset($effectiveperiod))
		{
			$effectiveperiod=3600;
		}
		if(!isset($_REQUEST["from"]))
		{
			$_REQUEST["from"]=0;
		}
		if(isset($_REQUEST["year"]))
		{
			$_REQUEST["from"]=($now-mktime($_REQUEST["hour"], 0, 0, $_REQUEST["month"], $_REQUEST["day"],$_REQUEST["year"]))/3600;
		}
		@show_history($_REQUEST["itemid"],$_REQUEST["from"],$effectiveperiod);
		show_page_footer();
		exit;
	}

	if($_REQUEST["action"]=="showlatest")
	{

		$table = new CTableInfo();
		$item=get_item_by_itemid($_REQUEST["itemid"]);

		if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
		{
			$table->setHeader(array(S_TIMESTAMP, S_LOCAL, S_SOURCE, S_SEVERITY, S_VALUE));
		}
		else
		{
			$table->setHeader(array(S_TIMESTAMP, S_VALUE));
		}

		if($item["value_type"]==ITEM_VALUE_TYPE_FLOAT)
		{
			$sql="select h.clock, h.value, i.valuemapid from history h, item i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" order by clock desc limit 500";
		}
		else if($item["value_type"]==ITEM_VALUE_TYPE_UINT64)
		{
			$sql="select h.clock, h.value, i.valuemapid from history_uint h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" order by clock desc limit 500";
		}
		else if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
		{
			$sql="select h.clock,h.value,h.timestamp,h.source,h.severity,i.valuemapid".
				" from history_log h, items i".
				" where h.itemid=i.itemid and itemid=".$_REQUEST["itemid"].
				" order by id desc, clock desc limit 500";
		}
		else
		{
			$sql="select h.clock,h.value,i.valuemapid from history_str h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				" order by clock desc limit 500";
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$clock = $row["clock"];
			$clock = date("Y.M.d H:i:s",$row["clock"]);
			$value = replace_value_by_map($row["value"], $row["valuemapid"]);

			if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
			{
				$local=$row["timestamp"];
				$source=$row["source"];
				$severity=$row["severity"];

				if($local==0)
				{
					$local=SPACE;
				}
				{
					$local=date("Y.M.d H:i:s",$local);
				}

		                if($severity==0)         $severity=S_NOT_CLASSIFIED;
		                elseif($severity==1)     $severity=S_INFORMATION;
		                elseif($severity==2)     $severity=S_WARNING;
		                elseif($severity==3)     $severity=array("value"=>S_AVERAGE,"class"=>"average");
		                elseif($severity==4)     $severity=array("value"=>S_HIGH,"class"=>"high");
		                elseif($severity==5)     $severity=array("value"=>S_DISASTER,"class"=>"disaster");
		                elseif($severity==6)     $severity=S_AUDIT_SUCCESS;
		                elseif($severity==7)     $severity=S_AUDIT_FAILURE;

			}
			if(($item["value_type"]!=ITEM_VALUE_TYPE_FLOAT) && ($item["value_type"]!=ITEM_VALUE_TYPE_UINT64))
			{
				$value="<pre>".htmlspecialchars($value)."</pre>";
			}
			if($item["value_type"]==ITEM_VALUE_TYPE_LOG)
			{
				$table->addRow(array(
					$clock,
					$local,
					$source,
					$severity,
					$value
		                        ));
			}
			else
			{
				$table->addRow(array(
					$clock,
					$value
		                        ));
			}
		}
		$table->show();
 
		show_page_footer();
		exit;
	}
?>
