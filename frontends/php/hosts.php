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

	$_GET["config"]=@iif(isset($_GET["config"]),$_GET["config"],get_profile("web.hosts.config",0));
	update_profile("web.hosts.config",$_GET["config"]);
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add items from template")
		{
			if(isset($_GET["host_templateid"])&&($_GET["host_templateid"]!=0))
			{
				$result=sync_items_with_template_host($_GET["hostid"],$_GET["host_templateid"]);
				show_messages(TRUE,S_ITEMS_ADDED,S_CANNOT_ADD_ITEMS);
			}
			else
			{
				show_messages(FALSE,"",S_SELECT_HOST_TEMPLATE_FIRST);
			}
		}	
		if($_GET["register"]=="add linkage")
		{	
			$items=0;
			if(isset($_GET["items_add"]))		$items=$items|1;
			if(isset($_GET["items_update"]))	$items=$items|2;
			if(isset($_GET["items_delete"]))	$items=$items|4;
			$triggers=0;
			if(isset($_GET["triggers_add"]))	$triggers=$triggers|1;
			if(isset($_GET["triggers_update"]))	$triggers=$triggers|2;
			if(isset($_GET["triggers_delete"]))	$triggers=$triggers|4;
			$actions=0;
			if(isset($_GET["actions_add"]))		$actions=$actions|1;
			if(isset($_GET["actions_update"]))	$actions=$actions|2;
			if(isset($_GET["actions_delete"]))	$actions=$actions|4;
			$graphs=0;
			if(isset($_GET["graphs_add"]))		$graphs=$graphs|1;
			if(isset($_GET["graphs_update"]))	$graphs=$graphs|2;
			if(isset($_GET["graphs_delete"]))	$graphs=$graphs|4;
			$screens=0;
			if(isset($_GET["screens_add"]))		$screens=$screens|1;
			if(isset($_GET["screens_update"]))	$screens=$screens|2;
			if(isset($_GET["screens_delete"]))	$screens=$screens|4;
			$result=add_template_linkage($_GET["hostid"],$_GET["templateid"],$items,$triggers,$actions,$graphs,$screens);
			show_messages($result, S_TEMPLATE_LINKAGE_ADDED, S_CANNOT_ADD_TEMPLATE_LINKAGE);
		}
		if($_GET["register"]=="update linkage")
		{	
			$items=0;
			if(isset($_GET["items_add"]))		$items=$items|1;
			if(isset($_GET["items_update"]))	$items=$items|2;
			if(isset($_GET["items_delete"]))	$items=$items|4;
			$triggers=0;
			if(isset($_GET["triggers_add"]))	$triggers=$triggers|1;
			if(isset($_GET["triggers_update"]))	$triggers=$triggers|2;
			if(isset($_GET["triggers_delete"]))	$triggers=$triggers|4;
			$actions=0;
			if(isset($_GET["actions_add"]))	$actions=$actions|1;
			if(isset($_GET["actions_update"]))	$actions=$actions|2;
			if(isset($_GET["actions_delete"]))	$actions=$actions|4;
			$graphs=0;
			if(isset($_GET["graphs_add"]))		$graphs=$graphs|1;
			if(isset($_GET["graphs_update"]))	$graphs=$graphs|2;
			if(isset($_GET["graphs_delete"]))	$graphs=$graphs|4;
			$screens=0;
			if(isset($_GET["screens_add"]))		$screens=$screens|1;
			if(isset($_GET["screens_update"]))	$screens=$screens|2;
			if(isset($_GET["screens_delete"]))	$screens=$screens|4;
			$result=update_template_linkage($_GET["hosttemplateid"],$_GET["hostid"],$_GET["templateid"],$items,$triggers,$actions,$graphs,$screens);
			show_messages($result, S_TEMPLATE_LINKAGE_UPDATED, S_CANNOT_UPDATE_TEMPLATE_LINKAGE);
		}
		if($_GET["register"]=="delete linkage")
		{
			$result=delete_template_linkage($_GET["hosttemplateid"]);
			show_messages($result, S_TEMPLATE_LINKAGE_DELETED, S_CANNOT_DELETE_TEMPLATE_LINKAGE);
			unset($_GET["hosttemplateid"]);
		}	
		if($_GET["register"]=="add profile")
		{
			$result=add_host_profile($_GET["hostid"],$_GET["devicetype"],$_GET["name"],$_GET["os"],$_GET["serialno"],$_GET["tag"],$_GET["macaddress"],$_GET["hardware"],$_GET["software"],$_GET["contact"],$_GET["location"],$_GET["notes"]);
			show_messages($result, S_PROFILE_ADDED, S_CANNOT_ADD_PROFILE);
		}
		if($_GET["register"]=="update profile")
		{
			$result=update_host_profile($_GET["hostid"],$_GET["devicetype"],$_GET["name"],$_GET["os"],$_GET["serialno"],$_GET["tag"],$_GET["macaddress"],$_GET["hardware"],$_GET["software"],$_GET["contact"],$_GET["location"],$_GET["notes"]);
			show_messages($result, S_PROFILE_UPDATED, S_CANNOT_UPDATE_PROFILE);
		}
		if($_GET["register"]=="delete profile")
		{
			$result=delete_host_profile($_GET["hostid"]);
			show_messages($result, S_PROFILE_DELETED, S_CANNOT_DELETE_PROFILE);
		}
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
		if($_GET["register"]=="start monitoring")
		{
			$result=DBselect("select hostid from hosts_groups where groupid=".$_GET["groupid"]);
			while($row=DBfetch($result))
			{
				$res=update_host_status($row["hostid"],HOST_STATUS_MONITORED);
				if($res)
				{
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"New status [".HOST_STATUS_MONITORED."]");
				}
			}
			show_messages(1,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		}
		if($_GET["register"]=="stop monitoring")
		{
			$result=DBselect("select hostid from hosts_groups where groupid=".$_GET["groupid"]);
			while($row=DBfetch($result))
			{
				$res=update_host_status($row["hostid"],HOST_STATUS_NOT_MONITORED);
				if($res)
				{
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"New status [".HOST_STATUS_NOT_MONITORED."]");
				}
			}
			show_messages(1,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		}
		if($_GET["register"]=="Activate selected")
		{
			$result=DBselect("select hostid from hosts");
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["hostid"]]))
				{
					$res=update_host_status($row["hostid"],HOST_STATUS_MONITORED);
					if($res)
					{
						add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"New status [".HOST_STATUS_MONITORED."]");
					}
				}
			}
			show_messages(1,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		}
		if($_GET["register"]=="Disable selected")
		{
			$result=DBselect("select hostid from hosts");
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["hostid"]]))
				{
					$res=update_host_status($row["hostid"],HOST_STATUS_NOT_MONITORED);
					if($res)
					{
						add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_HOST,"New status [".HOST_STATUS_NOT_MONITORED."]");
					}
				}
			}
			show_messages(1,S_HOST_STATUS_UPDATED,S_CANNOT_UPDATE_HOST_STATUS);
		}
		if($_GET["register"]=="Delete selected")
		{
			$result=DBselect("select hostid from hosts");
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["hostid"]]))
				{
					$host=get_host_by_hostid($row["hostid"]);
					$res=delete_host($row["hostid"]);
					if($res)
					{
						add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_HOST,"Host [".addslashes($host["host"])."]");
					}
				}
			}
			show_messages($result, S_HOST_DELETED, S_CANNOT_DELETE_HOST);
		}
	}
?>

<?php
	if(!isset($_GET["config"]))
	{
		$_GET["config"]=0;
	}

	$h1=S_CONFIGURATION_OF_HOSTS_AND_HOST_GROUPS;

#	$h2=S_GROUP."&nbsp;";
	$h2="";
	$h2=$h2."<select class=\"biginput\" name=\"config\" onChange=\"submit()\">";
	$h2=$h2.form_select("config",0,S_HOSTS);
	$h2=$h2.form_select("config",1,S_HOST_GROUPS);
	$h2=$h2.form_select("config",2,S_HOSTS_TEMPLATES_LINKAGE);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"hosts.php\">", "</form>");
?>


<?php
	if($_GET["config"]==2)
	{
	$h1=S_CONFIGURATION_OF_TEMPLATES_LINKAGE;

	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}
	if(isset($_GET["hostid"])&&($_GET["hostid"]==0))
	{
		unset($_GET["hostid"]);
	}

	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"".$_GET["config"]."\">";
	if(isset($_GET["hostid"]))
	{
		$h2=$h2."<input class=\"biginput\" name=\"hostid\" type=\"hidden\" value=\"".$_GET["hostid"]."\">";
	}
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);

	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host order by h.host");
		$cnt=0;
		while($row2=DBfetch($result2))
		{
			if(!check_right("Host","U",$row2["hostid"]))
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

	$h2=$h2."&nbsp;".S_HOST."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";
	$h2=$h2.form_select("hostid",0,S_SELECT_HOST_DOT_DOT_DOT);

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid and h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h where h.status not in (".HOST_STATUS_DELETED.") group by h.hostid,h.host order by h.host";
	}

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","U",$row["hostid"]))
		{
			continue;
		}
		$h2=$h2.form_select("hostid",$row["hostid"],$row["host"]);
	}
	$h2=$h2."</select>";

	echo "<br>";
	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"hosts.php\">", "</form>");
	}
?>

<?php
	if($_GET["config"]==1)
	{
		echo "<br>";
		show_table_header(S_HOST_GROUPS_BIG);
		table_begin();
		table_header(array(S_ID,S_NAME,S_MEMBERS,S_ACTIONS));

		$result=DBselect("select groupid,name from groups order by name");
		$col=0;
		while($row=DBfetch($result))
		{
//		$members=array("hide"=>1,"value"=>"");
			$members=array("hide"=>0,"value"=>"");
			$result1=DBselect("select distinct h.host from hosts h, hosts_groups hg where h.hostid=hg.hostid and hg.groupid=".$row["groupid"]." and h.status not in (".HOST_STATUS_DELETED.") order by host");
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
	if(isset($_GET["hostid"])&&($_GET["config"]==2))
	{
		table_begin();
		table_header(array(S_HOST,S_TEMPLATE,S_ITEMS,S_TRIGGERS,S_ACTIONS,S_GRAPHS,S_SCREENS,S_ACTIONS));

		$result=DBselect("select hosttemplateid,hostid,templateid,items,triggers,actions,graphs,screens from hosts_templates where hostid=".$_GET["hostid"]);
		$col=0;
		while($row=DBfetch($result))
		{
			$host=get_host_by_hostid($row["hostid"]);
			$template=get_host_by_hostid($row["templateid"]);
//		$members=array("hide"=>1,"value"=>"");
#			$actions="<A HREF=\"hosts.php?config=".$_GET["config"]."&groupid=".$row["groupid"]."#form\">".S_CHANGE."</A>";
			$actions="<a href=\"hosts.php?config=2&hostid=".$row["hostid"]."&hosttemplateid=".$row["hosttemplateid"]."\">".S_CHANGE."</a>";

			table_row(array(
				$host["host"],
				$template["host"],
				get_template_permission_str($row["items"]),
				get_template_permission_str($row["triggers"]),
				get_template_permission_str($row["actions"]),
				get_template_permission_str($row["graphs"]),
				get_template_permission_str($row["screens"]),
				$actions
				),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=8 ALIGN=CENTER>".S_NO_LINKAGES_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();
	}
	if(isset($_GET["hostid"])&&$_GET["config"]==2)
	{
		@insert_template_form($_GET["hostid"], $_GET["hosttemplateid"]);
	}
?>

<?php
	if(!isset($_GET["hostid"])&&($_GET["config"]==0))
{

	$h1="&nbsp;".S_HOSTS_BIG;

	$h2_form1="<form name=\"form2\" method=\"get\" action=\"latest.php\">";


	$h2=S_GROUP."&nbsp;";
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

	echo "<br>";
	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"hosts.php\">", "</form>");
?>

<?php
	table_begin();
	table_header(array(S_ID,S_HOST,S_IP,S_PORT,S_STATUS,S_AVAILABILITY,S_ERROR,S_ACTIONS));
	echo "<form method=\"get\" action=\"hosts.php\">";

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error,h.available from hosts h,hosts_groups hg where hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host,h.port,h.status,h.useip,h.ip,h.error,h.available from hosts h where h.status<>".HOST_STATUS_DELETED." order by h.host";
	}
	$result=DBselect($sql);

	$col=0;
	while($row=DBfetch($result))
	{
        	if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		$id="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["hostid"]."\"> ".$row["hostid"];
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
			if($row["status"] == HOST_STATUS_MONITORED)	
				$status=array("value"=>"<a class=\"off\" href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=1\">".S_MONITORED."</a>","class"=>"off");
			else if($row["status"] == HOST_STATUS_NOT_MONITORED)
				$status=array("value"=>"<a class=\"on\" href=\"hosts.php?hostid=".$row["hostid"]."&register=changestatus&status=0\">".S_NOT_MONITORED."</a>","class"=>"on");
//			else if($row["status"] == 2)
//				$status=array("value"=>S_UNREACHABLE,"class"=>"unknown");
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
					$actions="<A HREF=\"hosts.php?register=change&hostid=".$row["hostid"].url_param("groupid").url_param("config")."#form\">".S_CHANGE."</A>";
/*				if(isset($_GET["groupid"]))
				{
					$actions="<A HREF=\"hosts.php?register=change&config=".$_GET["config"]."&hostid=".$row["hostid"]."&groupid=".$_GET["groupid"]."#form\">".S_CHANGE."</A>";
				}
				else
				{
					$actions="<A HREF=\"hosts.php?register=change&config=".$_GET["config"]."&hostid=".$row["hostid"]."#form\">".S_CHANGE."</A>";
				}*/
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
			$id,
			$host,
			$ip,
			$row["port"],
			$status,
			$available,
			$error,
			$actions
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=8 ALIGN=CENTER>".S_NO_HOSTS_DEFINED."</TD>";
			echo "<TR>";
	}
	table_end();
	show_form_begin();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Activate selected\" onClick=\"return Confirm('".S_ACTIVATE_SELECTED_HOSTS_Q."');\">";
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Disable selected\" onClick=\"return Confirm('".S_DISABLE_SELECTED_HOSTS_Q."');\">";
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Delete selected\" onClick=\"return Confirm('".S_DELETE_SELECTED_HOSTS_Q."');\">";
	show_table2_header_end();
	echo "</form>";
}
?>

<?php
	if($_GET["config"]==1)
	{
		@insert_hostgroups_form($_GET["groupid"]);
	}
?>

<?php
	if($_GET["config"]==0)
	{
	
	$host=@iif(isset($_GET["host"]),$_GET["host"],"");
	$port=@iif(isset($_GET["port"]),$_GET["port"],get_profile("HOST_PORT",10050));
	$status=@iif(isset($_GET["status"]),$_GET["status"],HOST_STATUS_MONITORED);
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
	if($status==HOST_STATUS_MONITORED)
	{
		echo "<option value=\"0\" selected>".S_MONITORED;
		echo "<option value=\"1\">".S_NOT_MONITORED;
		echo "<option value=\"3\">".S_TEMPLATE;
	}
	else if($status==HOST_STATUS_TEMPLATE)
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
//	echo nbsp(S_USE_THE_HOST_AS_A_TEMPLATE);
	echo nbsp(S_USE_TEMPLATES_OF_THIS_HOST);
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"host_templateid\" size=\"1\">";
	echo "<option value=\"0\" selected>...";
//	$result=DBselect("select host,hostid from hosts where status=3 order by host");
	$result=DBselect("select host,hostid from hosts where status not in (".HOST_STATUS_DELETED.") order by host");
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

	if(isset($_GET["hostid"]))
	{
		insert_host_profile_form($_GET["hostid"]);
	}

	}
?>

<?php
	show_footer();
?>
