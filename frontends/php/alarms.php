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
	$page["title"] = "S_ALARMS";
	$page["file"] = "alarms.php";
	show_header($page["title"],0,0);
?>

<?php
	if(!check_right_on_trigger("R",$_REQUEST["triggerid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_page_footer();
                exit;
        }
?>

<?php
	if(isset($_REQUEST["limit"]) && ($_REQUEST["limit"]=="NO"))
	{
		$limit="";
	}
	else
	{
		$limit="limit 100";
		$_REQUEST["limit"] = 100;
	}

	$trigger=get_trigger_by_triggerid($_REQUEST["triggerid"]);

	$expression=$trigger["expression"];

	$expression=explode_exp($expression,1);
	$description=expand_trigger_description($_REQUEST["triggerid"]);

	$h1=S_ALARMS_BIG.":$description<br>$expression";

	$h2="";
	$h2=$h2."<input name=\"triggerid\" type=\"hidden\" value=".$_REQUEST["triggerid"].">";
	$h2=$h2."<select class=\"biginput\" name=\"limit\" onChange=\"submit()\">";
	$h2=$h2.form_select("limit","NO",S_SHOW_ALL);
	$h2=$h2.form_select("limit","100",S_SHOW_ONLY_LAST_100);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"alarms.php\">", "</form>");
?>

<FONT COLOR="#000000">
<?php
	$sql="select clock,value,triggerid from alarms where triggerid=".$_REQUEST["triggerid"]." order by clock desc $limit";
	$result=DBselect($sql);

	$table = new CTableInfo();
	$table->setHeader(array(S_TIME,S_STATUS,S_DURATION,S_SUM,"%"));
	$truesum=0;
	$falsesum=0;
	$dissum=0;
	$clock=mktime();
	while($row=DBfetch($result))
	{
		$lclock=$clock;
		$clock=$row["clock"];
		$leng=$lclock-$row["clock"];

//		if($row["value"]==0)		{ echo "<TR BGCOLOR=#EEFFEE>"; }
//		elseif($row["value"]==2)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
//		else				{ echo "<TR BGCOLOR=#FFDDDD>"; }

//		table_td(date("Y.M.d H:i:s",$row["clock"]),"");
		if($row["value"]==1)
		{
			$istrue=new CCol(S_TRUE_BIG,"on");
			$truesum=$truesum+$leng;
			$sum=$truesum;
		}
		elseif($row["value"]==0)
		{
			$istrue=new CCol(S_FALSE_BIG,"off");
			$falsesum=$falsesum+$leng;
			$sum=$falsesum;
		}
		elseif($row["value"]==3)
		{
			$istrue=new CCol(S_DISABLED_BIG,"unknown");
			$dissum=$dissum+$leng;
			$sum=$dissum;
		}
		elseif($row["value"]==2)
		{
			$istrue=new CCol(S_UNKNOWN_BIG,"unknown");
			$dissum=$dissum+$leng;
			$sum=$dissum;
		}
	
		$proc=(100*$sum)/($falsesum+$truesum+$dissum);
		$proc=round($proc*100)/100;
		$proc="$proc%";
 
//		table_td("<B>$istrue</B>","");
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
  
//		table_td($leng,"");
//		table_td($sum,"");
//		table_td($proc,"");
//		echo "</TR>";
		$table->addRow(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$istrue,
			$leng,
			$sum,
			$proc
			));
	}
	$table->show();
?>

<?php
	show_page_footer();
?>
