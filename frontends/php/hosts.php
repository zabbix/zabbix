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
			$groups=array();
			$result=DBselect("select groupid from groups");
			while($row=DBfetch($result))
			{
				if(isset($_GET[$row["groupid"]]))
				{
					$groups=array_merge($groups,array($row["groupid"]));
				}
			}
			$result=add_host($_GET["host"],$_GET["port"],$_GET["status"],$_GET["useip"],$_GET["ip"],$_GET["host_templateid"],$_GET["newgroup"],$groups);
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
			$groups=array();
			$result=DBselect("select groupid from groups");
			while($row=DBfetch($result))
			{
				if(isset($_GET[$row["groupid"]]))
				{
					$groups=array_merge($groups,array($row["groupid"]));
				}
			}
			$result=@update_host($_GET["hostid"],$_GET["host"],$_GET["port"],$_GET["status"],$_GET["useip"],$_GET["ip"],$_GET["newgroup"],$groups);
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
			$host=get_host_by_hostid($_GET["hostid"]);
			$result=delete_host($_GET["hostid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,"Host [".addslashes($host["name"])."]");
			}
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
	if(!isset($_GET["config"]))
	{
		$_GET["config"]=0;
	}

	$h1=S_CONFIGURATION_OF_HOST_GROUPS;

#	$h2=S_GROUP."&nbsp;";
	$h2="";
	$h2=$h2."<select class=\"biginput\" name=\"config\" onChange=\"submit()\">";
	$h2=$h2."<option value=\"0\" ".iif(isset($_GET["config"])&&$_GET["config"]==0,"selected","").">".S_HOSTS;
	$h2=$h2."<option value=\"1\" ".iif(isset($_GET["config"])&&$_GET["config"]==1,"selected","").">".S_HOST_GROUPS;
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"hosts.php\">", "</form>");
?>


<?php
	if($_GET["config"]==1)
	{
		echo "<br>";
		show_table_header(S_CONFIGURATION_OF_HOST_GROUPS);
		table_begin();
		table_header(array(S_ID,S_NAME,S_MEMBERS,S_ACTIONS));

		$result=DBselect("select groupid,name from groups order by name");
		$col=0;
		while($row=DBfetch($result))
		{
//		$members=array("hide"=>1,"value"=>"");
			$members=array("hide"=>0,"value"=>"");
			$result1=DBselect("select distinct h.host from hosts h, hosts_groups hg where h.hostid=hg.hostid and hg.groupid=".$row["groupid"]." order by host");
			for($i=0;$i<DBnum_rows($result1);$i++)
			{
				$members["hide"]=0;
				$members["value"]=$members["value"].DBget_field($result1,$i,0);
				if($i<DBnum_rows($result1)-1)
				{
					$members["value"]=$members["value"].", ";
				}
			}
			$members["value"]=$members["value"]."&nbsp;";
			$actions="<A HREF=\"hosts.php?config=".$_GET["config"]."&groupid=".$row["groupid"]."#form\">".S_CHANGE."</A>";

			table_row(array(
				$row["groupid"],
				$row["name"],
				$members,
				$actions
				),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_HOST_GROUPS_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();
	}
?>

<?php
	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}
?>

<?php
	if(!isset($_GET["hostid"])&&($_GET["config"]==0))
{

	$h1="&nbsp;".S_CONFIGURATION_OF_HOSTS_BIG;

	$h2_form1="<form name=\"form2\" method=\"get\" action=\"latest.php\">";


	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2."<option value=\"0\" ".iif(!isset($_GET["groupid"])," selected","").">".S_ALL_SMALL;
	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
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
			$h2=$h2."<option value=\"".$row["groupid"]."\" ".iif(isset($_GET["groupid"])&&($_GET["groupid"]==$row["groupid"]),"selected","").">".$row["name"];
		}
	}
	$h2=$h2."</select>";

	echo "<br>";
	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"hosts.php\">", "</form>");
?>

<?php
	table_begin();
	table_header(array(S_ID,S_HOST,S_IP,S_PORT,S_STATUS,S_ERROR,S_ACTIONS));

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error from hosts h,hosts_groups hg where hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error from hosts h order by h.host";
	}
	$result=DBselect($sql);

	$col=0;
	while($row=DBfetch($result))
	{
        	if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$host="<a href=\"items.php?hostid=".$row["hostid"]."\">".$row["host"]."</a>";

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
			if($row["status"] == 0)	
				$status=array("value"=>"<a class=\"off\" href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=1\">".S_MONITORED."</a>","class"=>"off");
			else if($row["status"] == 1)
				$status=array("value"=>"<a class=\"on\" href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=0\">".S_NOT_MONITORED."</a>","class"=>"on");
			else if($row["status"] == 2)
				$status=array("value"=>S_UNREACHABLE,"class"=>"unknown");
			else if($row["status"] == 3)
				$status=array("value"=>S_TEMPLATE,"class"=>"unknown");
			else if($row["status"] == HOST_STATUS_DELETED)
				$status=array("value"=>S_DELETED,"class"=>"unknown");
			else
				$status=S_UNKNOWN;
		}
		else
		{
			if($row["status"] == 0)	
				$status=array("value"=>S_MONITORED,"class"=>"off");
			else if($row["status"] == 1)
				$status=array("value"=>S_NOT_MONITORED,"class"=>"on");
			else if($row["status"] == 2)
				$status=array("value"=>S_UNREACHABLE,"class"=>"unknown");
			else if($row["status"] == 3)
				$status=array("value"=>S_TEMPLATE,"class"=>"unknown");
			else if($row["status"] == HOST_STATUS_DELETED)
				$status=array("value"=>S_DELETED,"class"=>"unknown");
			else
				$status=S_UNKNOWN;
		}
		if($row["error"] == "")
		{
			$error=array("value"=>"&nbsp;","class"=>"off");
		}
		else
		{
			$error=array("value"=>$row["error"],"class"=>"on");
		}
        	if(check_right("Host","U",$row["hostid"]))
		{
			if($row["status"] != HOST_STATUS_DELETED)
			{
				if(isset($_GET["groupid"]))
				{
					$actions="<A HREF=\"hosts.php?register=change&config=".$_GET["config"]."&hostid=".$row["hostid"]."&groupid=".$_GET["groupid"]."#form\">".S_CHANGE."</A>";
				}
				else
				{
					$actions="<A HREF=\"hosts.php?register=change&config=".$_GET["config"]."&hostid=".$row["hostid"]."#form\">".S_CHANGE."</A>";
				}
			}
			else
			{
					$actions="&nbsp;";
			}
		}
		else
		{
			$actions=S_CHANGE;
		}
		table_row(array(
			$row["hostid"],
			$host,
			$ip,
			$row["port"],
			$status,
			$error,
			$actions
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=7 ALIGN=CENTER>".S_NO_HOSTS_DEFINED."</TD>";
			echo "<TR>";
	}
	table_end();
}
?>

<?php
	if($_GET["config"]==1)
	{
		insert_hostgroups_form($_GET["groupid"]);
	}
?>

<?php
	if($_GET["config"]==0)
	{
	
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


	echo "<a name=\"form\"></a>";
	show_form_begin("hosts.host");
	echo S_HOST;
	$col=0;

	show_table2_v_delimiter($col++);
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

/*	show_table2_v_delimiter($col++);
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
	echo "</select>";*/

	show_table2_v_delimiter($col++);
	echo S_GROUPS;
	show_table2_h_delimiter();
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
				echo "<input type=checkbox name=\"".$row["groupid"]."\">".$row["name"];
			}
			else
			{
				echo "<input type=checkbox name=\"".$row["groupid"]."\" checked>".$row["name"];
			}
		}
		else
		{
			echo "<input type=checkbox name=\"".$row["groupid"]."\">".$row["name"];
		}
		echo "<br>";
	}
	echo "</select>";

	show_table2_v_delimiter($col++);
	echo nbsp(S_NEW_GROUP);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"newgroup\" size=20 value=\"$newgroup\">";

	show_table2_v_delimiter($col++);
	echo nbsp(S_USE_IP_ADDRESS);
	show_table2_h_delimiter();
// onChange does not work on some browsers: MacOS, KDE browser
//	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onChange=\"submit()\">";
	echo "<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"useip\" $useip onClick=\"submit()\">";

	if($useip=="checked")
	{
		show_table2_v_delimiter($col++);
		echo S_IP_ADDRESS;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"ip\" value=\"$ip\" size=15>";
	}
	else
	{
		echo "<input class=\"biginput\" type=\"hidden\"name=\"ip\" value=\"$ip\" size=15>";
	}

	show_table2_v_delimiter($col++);
	echo S_PORT;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"port\" size=6 value=\"$port\">";

	show_table2_v_delimiter($col++);
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

	show_table2_v_delimiter($col++);
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
//	end of if($_GET["config"]==1)
	}
?>

<?php
	show_footer();
?>
