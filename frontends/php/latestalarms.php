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
	$page["title"] = S_LATEST_EVENTS;
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
	$h1="&nbsp;".S_HISTORY_OF_EVENTS_BIG;

	$h2="";
	if(isset($_GET["start"]))
	{
		$h2=$h2."<input class=\"biginput\" name=\"start\" type=hidden value=".$_GET["start"]." size=8>";
  		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"<< Prev 100\">";
	}
	else
	{
  		$h2=$h2."<input class=\"button\" type=\"submit\" disabled name=\"do\" value=\"<< Prev 100\">";
	}
  	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"do\" value=\"Next 100 >>\">";
	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"latestalarms.php\">","</form>");

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

	table_begin();
	table_header(array(S_TIME, S_DESCRIPTION, S_VALUE, S_SEVERITY));
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
		if($col>100)	break;

		$description=expand_trigger_description($row["triggerid"]);
		$description="<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">$description</a>";

		if($row["value"] == 0)
		{
			$value=array("value"=>S_OFF,"class"=>"off");
		}
		elseif($row["value"] == 1)
		{
			$value=array("value"=>S_ON,"class"=>"on");
		}
		else
		{
			$value=array("value"=>S_UNKNOWN_BIG,"class"=>"unknown");
		}
		if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
		elseif($row["priority"]==1)	$priority=S_INFORMATION;
		elseif($row["priority"]==2)	$priority=S_WARNING;
		elseif($row["priority"]==3)	$priority=array("value"=>S_AVERAGE,"class"=>"average");
		elseif($row["priority"]==4)	$priority=array("value"=>S_HIGH,"class"=>"high");
		elseif($row["priority"]==5)	$priority=array("value"=>S_DISASTER,"class"=>"disaster");
		else				$priority=$row["priority"];

		table_row(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$description,
			$value,
			$priority),
			$col++);
	}
	table_end();
?>
</FONT>
</TR>
</TABLE>

<?php
	show_footer();
?>
