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
	$page["title"] = "S_AUDIT_LOG";
	$page["file"] = "audit.php";
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
	echo "&nbsp;".S_AUDIT_LOG_BIG;
	show_table3_h_delimiter(20);
	echo "<form name=\"form2\" method=\"get\" action=\"audit.php\">";
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

<?php
	$sql="select max(auditid) as max from audit";
	$result=DBselect($sql);
	$row=DBfetch($result);
	$maxauditid=@iif(DBnum_rows($result)>0,$row["max"],0);

	if(!isset($_GET["start"]))
	{
		$sql="select u.alias,a.clock,a.action,a.resource,a.details from audit a, users u where u.userid=a.userid and a.auditid>$maxauditid-200 order by clock desc limit 200";
	}
	else
	{
		$sql="select u.alias,a.clock,a.action,a.resource,a.details from audit a, users u where u.userid=a.userid and a.auditid>$maxauditid-".($_GET["start"]+200)." order by clock desc limit ".($_GET["start"]+200);

	}
	$result=DBselect($sql);

	table_begin();
	table_header(array(S_TIME,S_USER,S_RESOURCE,S_ACTION,S_DETAILS));
	$col=0;
	$i=0;
	while($row=DBfetch($result))
	{
		$i++;
		if(isset($_GET["start"])&&($i<$_GET["start"]))
		{
			continue;
		}
		if($i>100)	break;

		if($row["resource"]==AUDIT_RESOURCE_USER)
		{
			$resource=S_USER;
		}
		else if($row["resource"]==AUDIT_RESOURCE_ZABBIX_CONFIG)
		{
			$resource=S_CONFIGURATION_OF_ZABBIX;
		}
		else if($row["resource"]==AUDIT_RESOURCE_MEDIA_TYPE)
		{
			$resource=S_MEDIA_TYPE;
		}
		else if($row["resource"]==AUDIT_RESOURCE_HOST)
		{
			$resource=S_HOST;
		}
		else if($row["resource"]==AUDIT_RESOURCE_ACTION)
		{
			$resource=S_ACTION;
		}
		else if($row["resource"]==AUDIT_RESOURCE_GRAPH)
		{
			$resource=S_GRAPH;
		}
		else if($row["resource"]==AUDIT_RESOURCE_GRAPH_ELEMENT)
		{
			$resource=S_GRAPH_ELEMENT;
		}
		else
		{
			$resource=S_UNKNOWN_RESOURCE;
		}
		if($row["action"]==AUDIT_ACTION_ADD)
		{
			$action=S_ADDED;
		}
		else if($row["action"]==AUDIT_ACTION_UPDATE)
		{
			$action=S_UPDATED;
		}
		else if($row["action"]==AUDIT_ACTION_DELETE)
		{
			$action=S_DELETED;
		}
		else
		{
			$action=S_UNKNOWN_ACTION;
		}
		table_row(array(
			date("Y.M.d H:i:s",$row["clock"]),
			$row["alias"],
			$resource,
			$action,
			$row["details"]
		),$col++);
	}
	table_end();
?>

<?php
	show_footer();
?>
