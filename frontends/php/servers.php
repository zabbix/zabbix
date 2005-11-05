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
	include "include/config.inc.php";
	include "include/forms.inc.php";
	$page["title"] = "S_MENU_SERVERS";
	$page["file"] = "servers.php";
	show_header($page["title"],0,0);

        if(!check_anyright("Host","R"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }
	
        if(isset($_REQUEST["hostid"])&&!check_right("Host","R",$_REQUEST["hostid"]))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
                show_footer();
                exit;
        }

	$_REQUEST["serverid"]=@iif(isset($_REQUEST["serverid"]),
		$_REQUEST["serverid"],get_profile("web.latest.serverid",0));
	update_profile("web.latest.serverid",$_REQUEST["serverid"]);
	update_profile("web.menu.view.last",$page["file"]);

	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="Add")
		{
			$sql="insert into servers values ('','". $_REQUEST["host"] ."','". $_REQUEST["serverip"] ."',". $_REQUEST["serverport"] .")";
			DBselect($sql);
		}
		elseif($_REQUEST["register"]=="Update")
		{
			$sql="update servers set host='". $_REQUEST["host"] ."',ip='". $_REQUEST["serverip"] ."',port=". $_REQUEST["serverport"] ." where serverid=". $_REQUEST["serverid"];
			DBselect($sql);
		}
		elseif($_REQUEST["register"]=="Delete")
		{
			$sql="delete from servers where serverid=". $_REQUEST["serverid"];
			DBselect($sql);
		}
	}
	$h1="&nbsp;".S_MENU_SERVERS;

	$h2="&nbsp;";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"". $page["file"] ."\">", "</form>");

	table_begin();
	$header=array();
	$header=array_merge($header,array(S_SERVER_SERVERID,S_SERVER_HOST,S_SERVER_IP,S_SERVER_PORT));

	table_header($header);

	$col=0;
	$sql="select s.serverid,s.host,s.ip,s.port from servers s order by s.host";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		$hosturl="<A HREF=\"". $page["file"] ."?register=edit&serverid=". $row["serverid"] ."\">". $row["host"] ."</A>";
		table_row(array(
			$row["serverid"],
			$hosturl,
			$row["ip"],
			$row["port"]
			),
		$col++);
	}

		table_end();
		show_table_header_end();
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="edit")
		{
		insert_zabbix_server_form($page["file"],$_REQUEST["serverid"],0);
		}
		elseif($_REQUEST["register"]=="new")
		{
		insert_zabbix_server_form($page["file"],0,1);
		}
		
	}

	show_footer();
?>
