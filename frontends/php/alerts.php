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
	$page["title"] = "S_ALERT_HISTORY_SMALL";
	$page["file"] = "alerts.php";
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
	$h1="&nbsp;".S_ALERT_HISTORY_BIG;

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

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"alerts.php\">","</form>");
?>

<FONT COLOR="#000000">
<?php
	$sql="select max(alertid) as max from alerts";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalertid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($_GET["start"]))
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries,ac.scope,a.error from alerts a,actions ac,media_type mt where a.actionid=ac.actionid and mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200 order by a.clock desc limit 200";
	}
	else
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,ac.triggerid,a.status,a.retries,ac.scope,a.error from alerts a,actions ac,media_type mt where a.actionid=ac.actionid and mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200-".$_GET["start"]." order by a.clock desc limit ".($_GET["start"]+500);
	}
	$result=DBselect($sql);

	table_begin();
	table_header(array(S_TIME, S_TYPE, S_STATUS, S_RECIPIENTS, S_SUBJECT, S_MESSAGE, S_ERROR));
	$col=0;
	$zzz=0;
	while($row=DBfetch($result))
	{
		$zzz++;	
		if(isset($_GET["start"])&&($zzz<$_GET["start"]))
		{
			continue;
		}
		if(($row["scope"]==0)&&!check_right_on_trigger("R",$row["triggerid"]))
                {
			continue;
		}
		if(($row["scope"]==1)&&!check_right("Host","R",$row["triggerid"]))
                {
			continue;
		}
		if(($row["scope"]==2)&&!check_anyright("Default permission","R"))
                {
			continue;
		}

		if($col>100)	break;

		if($row["scope"]==0)
		{
			$time="<a href=\"alarms.php?triggerid=".$row["triggerid"]."\">".date("Y.M.d H:i:s",$row["clock"])."</a>";
		}
		else
		{
			$time=date("Y.M.d H:i:s",$row["clock"]);
		}
		if($row["status"] == 1)
		{
			$status=array("value"=>S_SENT,"class"=>"off");
		}
		else
		{
			$status=array("value"=>S_NOT_SENT,"class"=>"on");
		}
		$sendto=htmlspecialchars($row["sendto"]);
		$subject="<pre>".htmlspecialchars($row["subject"])."</pre>";
		$message="<pre>".htmlspecialchars($row["message"])."</pre>";
		if($row["error"] == "")
		{
			$error=array("value"=>"&nbsp;","class"=>"off");
		}
		else
		{
			$error=array("value"=>$row["error"],"class"=>"on");
		}
		table_row(array(
			$time,
			$row["description"],
			$status,
			$sendto,
			$subject,
			$message,
			$error),
			$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=7 ALIGN=CENTER>".S_NO_ALERTS."</TD>";
			echo "<TR>";
	}
	table_end();
?>

<?php
	show_footer();
?>
