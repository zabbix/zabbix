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
	$page["title"] = S_ALARMS;
	$page["file"] = "alarms.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right_on_trigger("R",$_GET["triggerid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
?>

<?php
	show_table_header_begin();
	echo S_ALARMS_BIG;
 
	show_table_v_delimiter(); 

	if(!isset($_GET["triggerid"]))
	{
		echo "<div align=center><B>No triggerID!!!!</B><BR>Please Contact Server Adminstrator</div>";
		show_footer();
		exit;
	}
	else
	{
		$trigger=get_trigger_by_triggerid($_GET["triggerid"]);

		$Expression=$trigger["expression"];
		$Description=$trigger["description"];
//		$Priority=$trigger["priority"];
	}
?>

<?php
	if(isset($_GET["limit"]) && ($_GET["limit"]=="NO"))
	{
		echo "[<A HREF=\"alarms.php?triggerid=".$_GET["triggerid"]."&limit=30\">";
		echo S_SHOW_ONLY_LAST_100;
		echo "</A>]";
		$limit=" ";
	}
	else 
	{
		echo "[<A HREF=\"alarms.php?triggerid=".$_GET["triggerid"]."&limit=NO\">";
		echo S_SHOW_ALL;
		echo "</A>]";
		$limit=" limit 100";
	}

	show_table_header_end();
	echo "<br>";
?>


<?php
	$Expression=explode_exp($Expression,1);
//	if( strstr($Description,"%s"))
//	{
		$Description=expand_trigger_description($trigger["triggerid"]);
//	}
	show_table_header("$Description<BR><font size=-1>$Expression</font>");
?>

<FONT COLOR="#000000">
<?php
	$sql="select clock,value,triggerid from alarms where triggerid=".$_GET["triggerid"]." order by clock desc $limit";
	$result=DBselect($sql);

	echo "<TABLE WIDTH=100% align=center BORDER=0 BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	table_td("<B>".S_TIME."</B>","");
	table_td("<B>".S_STATUS."</B>","");
	table_td("<B>".S_DURATION."</B>","");
	table_td("<B>".S_SUM."</B>","");
	table_td("<B>%</B>","");
	echo "</TR>";
	$truesum=0;
	$falsesum=0;
	$dissum=0;
	$clock=mktime();
	while($row=DBfetch($result))
	{
		$lclock=$clock;
		$clock=$row["clock"];
		$leng=$lclock-$row["clock"];

		if($row["value"]==0)		{ echo "<TR BGCOLOR=#EEFFEE>"; }
		elseif($row["value"]==2)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else				{ echo "<TR BGCOLOR=#FFDDDD>"; }

		table_td(date("Y.M.d H:i:s",$row["clock"]),"");
		if($row["value"]==1)
		{
			$istrue=S_TRUE_BIG;
			$truesum=$truesum+$leng;
			$sum=$truesum;
		}
		elseif($row["value"]==0)
		{
			$istrue=S_FALSE_BIG;
			$falsesum=$falsesum+$leng;
			$sum=$falsesum;
		}
		elseif($row["value"]==3)
		{
			$istrue=S_DISABLED_BIG;
			$dissum=$dissum+$leng;
			$sum=$dissum;
		}
		elseif($row["value"]==2)
		{
			$istrue=S_UNKNOWN_BIG;
			$dissum=$dissum+$leng;
			$sum=$dissum;
		}
	
		$proc=(100*$sum)/($falsesum+$truesum+$dissum);
		$proc=round($proc*100)/100;
		$proc="$proc%";
 
		table_td("<B>$istrue</B>","");
		if($leng>60*60*24)
		{
			$leng= round(($leng/(60*60*24))*10)/10;
			$leng="$leng days";
		}
		elseif ($leng>60*60)
		{
			$leng= round(($leng/(60*60))*10)/10;
			$leng="$leng hours"; 
		}
		elseif ($leng>60)
		{
			$leng= round(($leng/(60))*10)/10;
			$leng="$leng mins";
		}
		else
		{
			$leng="$leng secs";
		}

		if($sum>60*60*24)
		{
			$sum= round(($sum/(60*60*24))*10)/10;
			$sum="$sum days";
		}
		elseif ($sum>60*60)
		{
			$sum= round(($sum/(60*60))*10)/10;
			$sum="$sum hours"; 
		}
		elseif ($sum>60)
		{
			$sum= round(($sum/(60))*10)/10;
			$sum="$sum mins";
		}
		else
		{
			$sum="$sum secs";
		}
  
		table_td($leng,"");
		table_td($sum,"");
		table_td($proc,"");
		echo "</TR>";
	}
	echo "</TABLE>";
?>

<?php
	show_footer();
?>
