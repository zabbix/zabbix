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
	$page["file"] = "actions.php";
	show_header($page["title"],1,0);
?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	BETWEEN(0,65535),	NULL),
		"hostid"=>		array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	BETWEEN(0,65535),	NULL),
		"start"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),	NULL),
		"next"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL)
	);

	check_fields($fields);
?>

<?php
	if(isset($_REQUEST["start"])&&isset($_REQUEST["prev"]))
	{
		$_REQUEST["start"]-=100;
		if($_REQUEST["start"]<=0)	unset($_REQUEST["start"]);
	}
	if(isset($_REQUEST["next"]))
	{
		if(isset($_REQUEST["start"]))
		{
			$_REQUEST["start"]+=100;
		}
		else
		{
			$_REQUEST["start"]=100;
		}
	}
?>

<?php
	update_profile("web.menu.view.last",$page["file"]);
?>

<?php
	$h1="&nbsp;".S_ALERT_HISTORY_BIG;

	$h2="";

	if(isset($_REQUEST["start"]))
	{
		$h2=$h2."<input class=\"biginput\" name=\"start\" type=hidden value=".$_REQUEST["start"]." size=8>";
  		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"prev\" value=\"<< Prev 100\">";
	}
	else
	{
  		$h2=$h2."<input class=\"button\" type=\"submit\" disabled name=\"do\" value=\"<< Prev 100\">";
	}
  	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"next\" value=\"Next 100 >>\">";

	show_header2($h1,$h2,"<form name=\"form2\" method=\"get\" action=\"actions.php\">","</form>");
?>


<FONT COLOR="#000000">
<?php
	$sql="select max(alertid) as max from alerts";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxalertid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($_REQUEST["start"]))
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,a.status,a.retries,a.error from alerts a,media_type mt where mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200 order by a.clock desc limit 200";
	}
	else
	{
		$sql="select a.alertid,a.clock,mt.description,a.sendto,a.subject,a.message,a.status,a.retries,a.error from alerts a,media_type mt where mt.mediatypeid=a.mediatypeid and a.alertid>$maxalertid-200-".$_REQUEST["start"]." order by a.clock desc limit ".($_REQUEST["start"]+500);
	}
	echo $sql,"<br>";
	$result=DBselect($sql);

	$table = new CTableInfo(S_NO_ALERTS);
	$table->setHeader(array(S_TIME, S_TYPE, S_STATUS, S_RECIPIENTS, S_SUBJECT, S_MESSAGE, S_ERROR));
	$col=0;
	$zzz=0;
	while($row=DBfetch($result))
	{
		$zzz++;	
		if(!check_anyright("Default permission","R"))
                {
			continue;
		}

		if($col>100)	break;

		$time=date("Y.M.d H:i:s",$row["clock"]);

		if($row["status"] == 1)
		{
			$status=new CCol(S_SENT,"off");
		}
		else
		{
			$status=new CCol(S_NOT_SENT,"on");
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
		$table->addRow(array(
			$time,
			$row["description"],
			$status,
			$sendto,
			$subject,
			$message,
			$error));
	}
	$table->show();
?>

<?php
	show_page_footer();
?>
