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
	include "include/forms.inc.php";
	$page["title"] = S_HOSTS;
	$page["file"] = "hosts.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_anyright("Host","U"))
	{
		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add")
		{
			$result=add_host($_GET["host"],$_GET["port"],$_GET["status"],$_GET["useip"],$_GET["ip"],$_GET["host_templateid"],$_GET["newgroup"],$_GET["groups"]);
			show_messages($result, S_HOST_ADDED, S_CANNOT_ADD_HOST);
			if($result)
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_HOST,"Host [".addslashes($_GET["host"])."] IP [".$_GET["ip"]."] Status [".$_GET["status"]."]");
			unset($_GET["hostid"]);
		}
		if($_GET["register"]=="add items from template")
		{
			$result=add_using_host_template($_GET["hostid"],$_GET["host_templateid"]);
			show_messages($result, S_ITEMS_ADDED, S_CANNOT_ADD_ITEMS);
			unset($_GET["hostid"]);
		}
		if($_GET["register"]=="update")
		{
			$result=@update_host($_GET["hostid"],$_GET["host"],$_GET["port"],$_GET["status"],$_GET["useip"],$_GET["ip"],$_GET["newgroup"],$_GET["groups"]);
			show_messages($result, S_HOST_UPDATED, S_CANNOT_UPDATE_HOST);
			if($result)
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"Host [".addslashes($_GET["host"])."] IP [".$_GET["ip"]."] Status [".$_GET["status"]."]");
			unset($_GET["hostid"]);
		}
		if($_GET["register"]=="changestatus")
		{
			$host=get_host_by_hostid($_GET["hostid"]);
			$result=update_host_status($_GET["hostid"],$_GET["status"]);
			show_messages($result,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"Old status [".$host["status"]."] New status [$status]");
			}
			unset($_GET["hostid"]);
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_host($_GET["hostid"]);
			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
			unset($_GET["hostid"]);
		}
		if($_GET["register"]=="add group")
		{
			$result=add_host_group($_GET["name"], $_GET["hosts"]);
			show_messages($result, S_GROUP_ADDED, S_CANNOT_ADD_GROUP);
		}
		if($_GET["register"]=="delete group")
		{
			$result=delete_host_group($_GET["groupid"]);
			show_messages($result, S_GROUP_DELETED, S_CANNOT_DELETE_GROUP);
			unset($_GET["groupid"]);
		}
		if($_GET["register"]=="update group")
		{
			$result=update_host_group($_GET["groupid"], $_GET["name"], $_GET["hosts"]);
			show_messages($result, S_GROUP_UPDATED, _S_CANNOT_UPDATE_GROUP);
		}
	}
?>

<?php
	show_table_header(S_CONFIGURATION_OF_HOST_GROUPS);
?>


<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#AAAAAA\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=\"#CCCCCC\"><TD WIDTH=3%><B>".S_ID."</B></TD>";
	echo "<TD WIDTH=10%><B>".S_NAME."</B></TD>";
	echo "<TD><B>".S_MEMBERS."</B></TD>";
	echo "<TD WIDTH=10%><B>".S_ACTIONS."</B></TD>";
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
		$result1=DBselect("select distinct h.host from hosts h, hosts_groups hg where h.hostid=hg.hostid and hg.groupid=".$row["groupid"]." order by host");
		for($i=0;$i<DBnum_rows($result1);$i++)
		{
			echo DBget_field($result1,$i,0);
			if($i<DBnum_rows($result1)-1)
			{
				echo ",&nbsp;";
			}
		}
		echo "&nbsp;</TD>";
		echo "<TD>";
		echo "<A HREF=\"hosts.php?groupid=".$row["groupid"]."#form\">".S_CHANGE."</A>";
		echo "</TD>";
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_HOST_GROUPS_DEFINED."</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
	echo "<br>";
?>

<?php
	show_table_header_begin();
	echo S_CONFIGURATION_OF_HOSTS_BIG;
	show_table_v_delimiter();

	if(isset($_GET["groupid"]))
	{
//		echo "all ";
		echo "<a href='hosts.php'>".S_ALL_SMALL."</a> ";
	}
	else
	{
		echo "<b>[<a href='hosts.php'>".S_ALL_SMALL."</a>]</b> ";
	}

	$result=DBselect("select groupid,name from groups order by name");

	while($row=DBfetch($result))
	{
//		if(!check_right("Host","R",$row["hostid"]))
//		{
//			continue;
//		}
		if( isset($_GET["groupid"]) && ($_GET["groupid"] == $row["groupid"]) )
		{
			echo "<b>[";
		}
		echo "<a href='hosts.php?groupid=".$row["groupid"]."'>".$row["name"]."</a>";
		if(isset($_GET["groupid"]) && ($_GET["groupid"] == $row["groupid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
	echo "<br>";
?>

<?php
	if(!isset($_GET["hostid"]))
{
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#AAAAAA\" cellspacing=1 cellpadding=3>";
	echo "<TR BGCOLOR=\"#CCCCCC\">";
	echo "<TD WIDTH=3% NOSAVE><B>".S_ID."</B></TD>";
	echo "<TD><B>".S_HOST."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_IP."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_PORT."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_STATUS."</B></TD>";
	echo "<TD WIDTH=10% NOSAVE><B>".S_ACTIONS."</B></TD>";
	echo "</TR>";


	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip from hosts h,hosts_groups hg where hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip from hosts h order by h.host";
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
		if($row["useip"]==1)
		{
			echo "<TD>".$row["ip"]."</TD>";
		}
		else
		{
			echo "<TD>-</TD>";
		}
		echo "<TD>".$row["port"]."</TD>";
		echo "<TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] == 0)	
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=1\"><font color=\"00AA00\">".S_MONITORED."</font></a>";
			else if($row["status"] == 1)
				echo "<a href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=0\"><font color=\"AA0000\">".S_NOT_MONITORED."</font></a>";
			else if($row["status"] == 2)
				echo "<font color=\"AAAAAA\">".S_UNREACHABLE."</font>";
			else if($row["status"] == 3)
				echo "<font color=\"AAAAAA\">".S_TEMPLATE."</font>";
			else if($row["status"] == HOST_STATUS_DELETED)
				echo "<font color=\"AAAAAA\">".S_DELETED."</font>";
			else
				echo S_UNKNOWN;
		}
		else
		{
			if($row["status"] == 0)	
				echo "<font color=\"00AA00\">".S_MONITORED."</font>";
			else if($row["status"] == 1)
				echo "<font color=\"AA0000\">".S_NOT_MONITORED."</font>";
			else if($row["status"] == 2)
				echo "<font color=\"AAAAAA\">".S_UNREACHABLE."</font>";
			else if($row["status"] == 3)
				echo "<font color=\"AAAAAA\">".S_TEMPLATE."</font>";
			else if($row["status"] == HOST_STATUS_DELETED)
				echo "<font color=\"AAAAAA\">".S_DELETED."</font>";
			else
				echo S_UNKNOWN;
		}
		echo "</TD>";
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] != HOST_STATUS_DELETED)
			{
				if(isset($_GET["groupid"]))
				{
					echo "<TD><A HREF=\"hosts.php?register=change&hostid=".$row["hostid"]."&groupid=".$_GET["groupid"]."#form\">".S_CHANGE."</A></TD>";
				}
				else
				{
					echo "<TD><A HREF=\"hosts.php?register=change&hostid=".$row["hostid"]."#form\">".S_CHANGE."</A></TD>";
				}
			}
			else
			{
					echo "<TD>&nbsp;</TD>";
			}
		}
		else
		{
			echo "<TD>".S_CHANGE."</TD>";
		}
		echo "</TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=6 ALIGN=CENTER>".S_NO_HOSTS_DEFINED."</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
}
?>

<?php
	echo "<br>";
	insert_hostgroups_form($_GET["groupid"]);
?>

<?php
	$host=@iif(isset($_GET["host"]),$_GET["host"],"");
	$port=@iif(isset($_GET["port"]),$_GET["port"],get_profile("HOST_PORT",10050));
	$status=@iif(isset($_GET["status"]),$_GET["status"],0);
	$useip=@iif(isset($_GET["useip"]),$_GET["useip"],"off");
	$newgroup=@iif(isset($_GET["newgroup"]),$_GET["newgroup"],"");
	$ip=@iif(isset($_GET["ip"]),$_GET["ip"],"");
	$host_templateid=@iif(isset($_GET["host_templateid"]),$_GET["host_templateid"],"");

	if($useip!="on")
	{
		$useip="";
	}
	else
	{
		$useip="checked";
	}

	if(isset($_GET["register"]) && ($_GET["register"] == "change"))
	{
		$result=DBselect("select host,port,status,useip,ip from hosts where hostid=".$_GET["hostid"]); 
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
	echo S_HOST;

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"hosts.php#form\">";
	if(isset($_GET["hostid"]))
	{
		echo "<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$_GET["hostid"]."\">";
	}
	if(isset($_GET["groupid"]))
	{
		echo "<input class=\"biginput\" name=\"groupid\" type=\"hidden\" value=\"".$_GET["groupid"]."\">";
	}
	echo S_HOST;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"host\" value=\"$host\" size=20>";

	show_table2_v_delimiter();
	echo S_GROUPS;
	show_table2_h_delimiter();
	echo "<select multiple class=\"biginput\" name=\"groups[]\" size=\"5\">";
	$result=DBselect("select distinct groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
		if(isset($_GET["hostid"]))
		{
			$sql="select count(*) as count from hosts_groups where hostid=".$_GET["hostid"]." and groupid=".$row["groupid"];
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
	echo nbsp(S_NEW_GROUP);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"newgroup\" size=20 value=\"$newgroup\">";

	show_table2_v_delimiter();
	echo nbsp(S_USE_IP_ADDRESS);
	show_table2_h_delimiter();
// onChange does not work on some browsers: MacOS, KDE browser
//	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onChange=\"submit()\">";
	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onClick=\"submit()\">";

	if($useip=="checked")
	{
		show_table2_v_delimiter();
		echo S_IP_ADDRESS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"ip\" value=\"$ip\" size=15>";
	}
	else
	{
		echo "<input class=\"biginput\" type=\"hidden\"name=\"ip\" value=\"$ip\" size=15>";
	}

	show_table2_v_delimiter();
	echo S_PORT;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"port\" size=6 value=\"$port\">";

	show_table2_v_delimiter();
	echo S_STATUS;
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"status\" size=\"1\">";
	if($status==0)
	{
		echo "<option value=\"0\" selected>".S_MONITORED;
		echo "<option value=\"1\">".S_NOT_MONITORED;
		echo "<option value=\"3\">".S_TEMPLATE;
	}
	else if($status==3)
	{
		echo "<option value=\"0\">".S_MONITORED;
		echo "<option value=\"1\">".S_NOT_MONITORED;
		echo "<option value=\"3\" selected>".S_TEMPLATE;
	}
	else
	{
		echo "<option value=\"0\">".S_MONITORED;
		echo "<option value=\"1\" selected>".S_NOT_MONITORED;
		echo "<option value=\"3\">".S_TEMPLATE;
	}
	echo "</select>";

	show_table2_v_delimiter();
	echo nbsp(S_USE_THE_HOST_AS_A_TEMPLATE);
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
	if(isset($_GET["hostid"]))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add items from template\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_SELECTED_HOST_Q."');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>
