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
	include "include/forms.inc.php";
	$page["title"] = "S_HOSTS";
	$page["file"] = "popup.php";
	show_header($page["title"],0,1);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Host","R"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_page_footer();
		exit;
	}
?>

<?php

	$h1="&nbsp;".S_HOSTS_BIG;

//	$h2_form1="<form name=\"form2\" method=\"get\" action=\"popup.php\">";


	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<input name=\"form\" type=\"hidden\" value=".$_REQUEST["form"].">";
	$h2=$h2."<input name=\"field1\" type=\"hidden\" value=".$_REQUEST["field1"].">";
	$h2=$h2."<input name=\"field2\" type=\"hidden\" value=".$_REQUEST["field2"].">";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and h.status not in (".HOST_STATUS_DELETED.")group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","R",$row2["hostid"]))
			{
				continue;
			}
			$cnt=1; break;
		}
		if($cnt!=0)
		{
			$h2=$h2.form_select("groupid",$row["groupid"],$row["name"]);
		}
	}
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"popup.php\">", "</form>");
?>

<?php
	$table = new CTableInfo(S_NO_HOSTS_DEFINED);
	$table->setHeader(array(S_HOST,S_IP,S_PORT,S_STATUS,S_AVAILABILITY));

	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]!=0))
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error,h.available from hosts h,hosts_groups hg where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error,h.available from hosts h where h.status<>".HOST_STATUS_DELETED." order by h.host";
	}
	$result=DBselect($sql);

	while($row=DBfetch($result))
	{
        	if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
//		$host ="<a href=\"#\" onclick='send_back('Accounts','9ca3e791-4bc9-7808-f70c-4374c24de0df');'>".$row["host"]."</a>";
//		$host ="<a href=\"#\" onclick=\"zzz();\">".$row["host"]."</a>";
		$host ="<a href=\"#\" onclick=\"window.opener.document.".$_REQUEST["form"].".".$_REQUEST["field1"].".value='".$row["hostid"]."'; window.opener.document.".$_REQUEST["form"].".".$_REQUEST["field2"].".value='".$row["host"]."'; window.close();\">".$row["host"]."</a>";
//		$host="<a href=\"popup.php?hostid=".$row["hostid"]."\">".$row["host"]."</a>";

		if($row["useip"]==1)
		{
			$ip=$row["ip"];
		}
		else
		{
			$ip="-";
		}
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] == HOST_STATUS_MONITORED)	
				$status=array("value"=>S_MONITORED,"class"=>"off");
			else if($row["status"] == HOST_STATUS_NOT_MONITORED)
				$status=array("value"=>S_NOT_MONITORED,"class"=>"on");
			else if($row["status"] == HOST_STATUS_TEMPLATE)
				$status=array("value"=>S_TEMPLATE,"class"=>"unknown");
			else if($row["status"] == HOST_STATUS_DELETED)
				$status=array("value"=>S_DELETED,"class"=>"unknown");
			else
				$status=S_UNKNOWN;
		}
		else
		{
			if($row["status"] == HOST_STATUS_MONITORED)	
				$status=array("value"=>S_MONITORED,"class"=>"off");
			else if($row["status"] == HOST_STATUS_NOT_MONITORED)
				$status=array("value"=>S_NOT_MONITORED,"class"=>"on");
//			else if($row["status"] == 2)
//				$status=array("value"=>S_UNREACHABLE,"class"=>"unknown");
			else if($row["status"] == HOST_STATUS_TEMPLATE)
				$status=array("value"=>S_TEMPLATE,"class"=>"unknown");
			else if($row["status"] == HOST_STATUS_DELETED)
				$status=array("value"=>S_DELETED,"class"=>"unknown");
			else
				$status=S_UNKNOWN;
		}

		if($row["available"] == HOST_AVAILABLE_TRUE)	
			$available=array("value"=>S_AVAILABLE,"class"=>"off");
		else if($row["available"] == HOST_AVAILABLE_FALSE)
			$available=array("value"=>S_NOT_AVAILABLE,"class"=>"on");
		else if($row["available"] == HOST_AVAILABLE_UNKNOWN)
			$available=array("value"=>S_UNKNOWN,"class"=>"unknown");

		$table->addRow(array(
			$host,
			$ip,
			$row["port"],
			$status,
			$available
			));
	}
	$table->show();
?>
