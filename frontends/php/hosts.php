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
	include "include/config.inc.php";
	$page["title"] = "Hosts";
	$page["file"] = "hosts.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Host","U"))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_host($HTTP_GET_VARS["host"],$HTTP_GET_VARS["port"],$HTTP_GET_VARS["status"],$HTTP_GET_VARS["useip"],$HTTP_GET_VARS["ip"],$HTTP_GET_VARS["host_templateid"],$HTTP_GET_VARS["newgroup"],$HTTP_GET_VARS["groups"]);
			show_messages($result,"Host added","Cannot add host");
			unset($HTTP_GET_VARS["hostid"]);
		}
		if($HTTP_GET_VARS["register"]=="add items from template")
		{
			$result=add_using_host_template($HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["host_templateid"]);
			show_messages($result,"Items added","Cannot add items");
			unset($HTTP_GET_VARS["hostid"]);
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=@update_host($HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["host"],$HTTP_GET_VARS["port"],$HTTP_GET_VARS["status"],$HTTP_GET_VARS["useip"],$HTTP_GET_VARS["ip"],$HTTP_GET_VARS["newgroup"],$HTTP_GET_VARS["groups"]);
			show_messages($result,"Host details updated","Cannot update host details");
			unset($HTTP_GET_VARS["hostid"]);
		}
		if($HTTP_GET_VARS["register"]=="changestatus")
		{
			$result=update_host_status($HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["status"]);
			show_messages($result,"Host status updated","Cannot update host status");
			unset($HTTP_GET_VARS["hostid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_host($HTTP_GET_VARS["hostid"]);
			show_messages($result,"Host deleted","Cannot delete host");
			unset($HTTP_GET_VARS["hostid"]);
		}
		if($HTTP_GET_VARS["register"]=="add group")
		{
			$result=add_host_group($HTTP_GET_VARS["name"], $HTTP_GET_VARS["hosts"]);
			show_messages($result, "Group added", "Cannot add group");
		}
		if($HTTP_GET_VARS["register"]=="delete group")
		{
			$result=delete_host_group($HTTP_GET_VARS["groupid"]);
			show_messages($result, "Group deleted", "Cannot delete group");
			unset($HTTP_GET_VARS["groupid"]);
		}
		if($HTTP_GET_VARS["register"]=="update group")
		{
			$result=update_host_group($HTTP_GET_VARS["groupid"], $HTTP_GET_VARS["name"], $HTTP_GET_VARS["hosts"]);
			show_messages($result, "Group updated", "Cannot update group");
		}
	}
?>

<?php
	show_table_header("CONFIGURATION OF HOST GROUPS");
?>


<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=3%><B>Id</B></TD>";
	echo "<TD><B>Name</B></TD>";
	echo "<TD WIDTH=10%><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select groupid,name from groups order by name");
	$col=0;
	while($row=DBfetch($result))
	{
//		if(!check_right("User group","R",$row["usrgrpid"]))
//		{
//			continue;
//		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else 		{ echo "<TR BGCOLOR=#DDDDDD>"; }
		echo "<TD>".$row["groupid"]."</TD>";
		echo "<TD>".$row["name"]."</TD>";
		echo "<TD>";
		echo "<A HREF=\"hosts.php?groupid=".$row["groupid"]."#form\">Change</A>";
		echo "</TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=3 ALIGN=CENTER>-No host groups defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
	echo "<br>";
?>

<?php
	show_table_header_begin();
	echo "CONFIGURATION OF HOSTS";
	show_table_v_delimiter();

	if(isset($HTTP_GET_VARS["groupid"]))
	{
//		echo "all ";
		echo "<a href='hosts.php'>all</a> ";
	}
	else
	{
		echo "<b>[<a href='hosts.php'>all</a>]</b> ";
	}

	$result=DBselect("select groupid,name from groups order by name");

	while($row=DBfetch($result))
	{
//		if(!check_right("Host","R",$row["hostid"]))
//		{
//			continue;
//		}
		if( isset($HTTP_GET_VARS["groupid"]) && ($HTTP_GET_VARS["groupid"] == $row["groupid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='hosts.php?groupid=".$row["groupid"]."'>".$row["name"]."</a>";
		if(isset($HTTP_GET_VARS["groupid"]) && ($HTTP_GET_VARS["groupid"] == $row["groupid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
	echo "<br>";
?>

<?php
	if(!isset($HTTP_GET_VARS["hostid"]))
{
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD WIDTH=3% NOSAVE><B>Id</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Host</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Port</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Status</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>Actions</B></TD>";
	echo "</TR>";


	if(isset($HTTP_GET_VARS["groupid"]))
	{
		$sql="select h.hostid,h.host,h.port,h.status from hosts h,hosts_groups hg where hg.groupid=".$HTTP_GET_VARS["groupid"]." and hg.hostid=h.hostid order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host,h.port,h.status from hosts h order by h.host";
	}
	$result=DBselect($sql);

	$col=0;
	while($row=DBfetch($result))
	{
        	if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else 		{ echo "<TR BGCOLOR=#DDDDDD>"; }
	
		echo "<TD>".$row["hostid"]."</TD>";
		echo "<TD><a href=\"items.php?hostid=".$row["hostid"]."\">".$row["host"]."</a></TD>";
		echo "<TD>".$row["port"]."</TD>";
		echo "<TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] == 0)	
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=1\"><font color=\"00AA00\">Monitored</font></a>";
			else if($row["status"] == 1)
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=0\"><font color=\"AA0000\">Not monitored</font></a>";
			else if($row["status"] == 2)
				echo "<font color=\"AAAAAA\">Unreachable</font>";
			else if($row["status"] == 3)
				echo "<font color=\"AAAAAA\">Template</font>";
			else if($row["status"] == HOST_STATUS_DELETED)
				echo "<font color=\"AAAAAA\">Deleted</font>";
			else
				echo "Unknown";
		}
		else
		{
			if($row["status"] == 0)	
				echo "<font color=\"00AA00\">Monitored</font>";
			else if($row["status"] == 1)
				echo "<font color=\"AA0000\">Not monitored</font>";
			else if($row["status"] == 2)
				echo "<font color=\"AAAAAA\">Unreachable</font>";
			else if($row["status"] == 3)
				echo "<font color=\"AAAAAA\">Template</font>";
			else if($row["status"] == HOST_STATUS_DELETED)
				echo "<font color=\"AAAAAA\">Deleted</font>";
			else
				echo "Unknown";
		}
		echo "</TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] != HOST_STATUS_DELETED)
			{
				if(isset($HTTP_GET_VARS["groupid"]))
				{
					echo "<TD><A HREF=\"hosts.php?register=change&hostid=".$row["hostid"]."&groupid=".$HTTP_GET_VARS["groupid"]."#form\">Change</A></TD>";
				}
				else
				{
					echo "<TD><A HREF=\"hosts.php?register=change&hostid=".$row["hostid"]."#form\">Change</A></TD>";
				}
			}
			else
			{
					echo "<TD>&nbsp;</TD>";
			}
		}
		else
		{
			echo "<TD>Change</TD>";
		}
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=5 ALIGN=CENTER>-No hosts defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
}
?>

<?
	echo "<br>";
	insert_hostgroups_form($HTTP_GET_VARS["groupid"]);
?>

<?php
	$host=@iif(isset($HTTP_GET_VARS["host"]),$HTTP_GET_VARS["host"],"");
	$port=@iif(isset($HTTP_GET_VARS["port"]),$HTTP_GET_VARS["port"],get_profile("HOST_PORT",10000));
	$status=@iif(isset($HTTP_GET_VARS["status"]),$HTTP_GET_VARS["status"],0);
	$useip=@iif(isset($HTTP_GET_VARS["useip"]),$HTTP_GET_VARS["useip"],"off");
	$newgroup=@iif(isset($HTTP_GET_VARS["newgroup"]),$HTTP_GET_VARS["newgroup"],"");
	$ip=@iif(isset($HTTP_GET_VARS["ip"]),$HTTP_GET_VARS["ip"],"");
	$host_templateid=@iif(isset($HTTP_GET_VARS["host_templateid"]),$HTTP_GET_VARS["host_templateid"],"");

	if($useip!="on")
	{
		$useip="";
	}
	else
	{
		$useip="checked";
	}

	if(isset($HTTP_GET_VARS["register"]) && ($HTTP_GET_VARS["register"] == "change"))
	{
		$result=DBselect("select host,port,status,useip,ip from hosts where hostid=".$HTTP_GET_VARS["hostid"]); 
		$host=DBget_field($result,0,0);
		$port=DBget_field($result,0,1);
		$status=DBget_field($result,0,2);
		$useip=DBget_field($result,0,3);
		$ip=DBget_field($result,0,4);

		if($useip==0)
		{
			$useip="";
		}
		else
		{
			$useip="checked";
		}
	}
	else
	{
	}


	echo "<br>";
	echo "<a name=\"form\"></a>";
	show_table2_header_begin();
	echo "Host";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"hosts.php#form\">";
	if(isset($HTTP_GET_VARS["hostid"]))
	{
		echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$HTTP_GET_VARS["hostid"]."\">";
	}
	if(isset($HTTP_GET_VARS["groupid"]))
	{
		echo "<input class=\"biginput\" name=\"groupid\" type=\"hidden\" value=\"".$HTTP_GET_VARS["groupid"]."\">";
	}
	echo "Host";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"host\" value=\"$host\" size=20>";

	show_table2_v_delimiter();
	echo "Groups";
	show_table2_h_delimiter();
	echo "<select multiple class=\"biginput\" name=\"groups[]\" size=\"5\">";
	$result=DBselect("select distinct groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
		if(isset($HTTP_GET_VARS["hostid"]))
		{
			$sql="select count(*) as count from hosts_groups where hostid=".$HTTP_GET_VARS["hostid"]." and groupid=".$row["groupid"];
			$result2=DBselect($sql);
			$row2=DBfetch($result2);
			if($row2["count"]==0)
			{
				echo "<option value=\"".$row["groupid"]."\">".$row["name"];
			}
			else
			{
				echo "<option value=\"".$row["groupid"]."\" selected>".$row["name"];
			}
		}
		else
		{
			echo "<option value=\"".$row["groupid"]."\">".$row["name"];
		}
	}
	echo "</select>";

	show_table2_v_delimiter();
	echo nbsp("New group");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"newgroup\" size=20 value=\"$newgroup\">";

	show_table2_v_delimiter();
	echo nbsp("Use IP address");
	show_table2_h_delimiter();
// onChange does not work on some browsers: MacOS, KDE browser
//	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onChange=\"submit()\">";
	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onClick=\"submit()\">";

	if($useip=="checked")
	{
		show_table2_v_delimiter();
		echo "IP address";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"ip\" value=\"$ip\" size=15>";
	}
	else
	{
		echo "<input class=\"biginput\" type=\"hidden\"name=\"ip\" value=\"$ip\" size=15>";
	}

	show_table2_v_delimiter();
	echo "Port";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"port\" size=6 value=\"$port\">";

	show_table2_v_delimiter();
	echo "Status";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"status\" size=\"1\">";
	if($status==0)
	{
		echo "<option value=\"0\" selected>Monitored";
		echo "<option value=\"1\">Not monitored";
		echo "<option value=\"3\">Template";
	}
	else if($status==3)
	{
		echo "<option value=\"0\">Monitored";
		echo "<option value=\"1\">Not monitored";
		echo "<option value=\"3\" selected>Template";
	}
	else
	{
		echo "<option value=\"0\">Monitored";
		echo "<option value=\"1\" selected>Not monitored";
		echo "<option value=\"3\">Template";
	}
	echo "</select>";

	show_table2_v_delimiter();
	echo nbsp("Use the host as a template");
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"host_templateid\" size=\"1\">";
	echo "<option value=\"0\" selected>...";
//	$result=DBselect("select host,hostid from hosts where status=3 order by host");
	$result=DBselect("select host,hostid from hosts order by host");
	while($row=DBfetch($result))
	{
		if($host_templateid == $row["hostid"])
		{
			echo "<option value=\"".$row["hostid"]."\" selected>".$row["host"];
		}
		else
		{
			echo "<option value=\"".$row["hostid"]."\">".$row["host"];
		}
		
	}
	echo "</select>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($HTTP_GET_VARS["hostid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add items from template\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected host?');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>
