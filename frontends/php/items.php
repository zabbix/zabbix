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

        $page["title"] = "S_CONFIGURATION_OF_ITEMS";
        $page["file"] = "items.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	$serverlist=array();
	$result=DBselect("select distinct serverid,host from servers");
	$servers=DBnum_rows($result);
	while($row=DBfetch($result))
	{
		$serverlist=$serverlist + array($row["serverid"]=>$row["host"]);
	}

        if(!check_anyright("Host","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font
>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}
	if(isset($_REQUEST["hostid"])&&($_REQUEST["hostid"]==0))
	{
		unset($_REQUEST["hostid"]);
	}
?>

<?php
	$_REQUEST["hostid"]=@iif(isset($_REQUEST["hostid"]),$_REQUEST["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_REQUEST["hostid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="do")
		{
			if($_REQUEST["action"]=="add to group")
			{
				$itemid=add_item_to_group($_REQUEST["groupid"],$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["serverid"]);
				show_messages($itemid, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
				unset($_REQUEST["itemid"]);
				unset($itemid);
			}
			if($_REQUEST["action"]=="update in group")
			{
				$result=update_item_in_group($_REQUEST["groupid"],$_REQUEST["itemid"],$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["serverid"]);
				show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
				unset($_REQUEST["itemid"]);
			}
			if($_REQUEST["action"]=="delete from group")
			{
				$result=delete_item_from_group($_REQUEST["groupid"],$_REQUEST["itemid"]);
				show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
				unset($_REQUEST["itemid"]);
			}
		}
		if($_REQUEST["register"]=="update")
		{
			$result=update_item($_REQUEST["itemid"],$_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["serverid"]);
			update_item_in_templates($_REQUEST["itemid"]);
			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
//			unset($itemid);
		}
		if($_REQUEST["register"]=="changestatus")
		{
			$result=update_item_status($_REQUEST["itemid"],$_REQUEST["status"]);
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			unset($_REQUEST["itemid"]);
		}
		if($_REQUEST["register"]=="add")
		{
			$itemid=add_item($_REQUEST["description"],$_REQUEST["key"],$_REQUEST["hostid"],$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["serverid"]);
			add_item_to_linked_hosts($itemid);
			show_messages($itemid, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			unset($_REQUEST["itemid"]);
			unset($itemid);
		}
		if($_REQUEST["register"]=="add to all hosts")
		{
			$result=DBselect("select hostid,host from hosts order by host");
			$hosts_ok="";
			$hosts_notok="";
			while($row=DBfetch($result))
			{
				$result2=add_item($_REQUEST["description"],$_REQUEST["key"],$row["hostid"],$_REQUEST["delay"],$_REQUEST["history"],$_REQUEST["status"],$_REQUEST["type"],$_REQUEST["snmp_community"],$_REQUEST["snmp_oid"],$_REQUEST["value_type"],$_REQUEST["trapper_hosts"],$_REQUEST["snmp_port"],$_REQUEST["units"],$_REQUEST["multiplier"],$_REQUEST["delta"],$_REQUEST["snmpv3_securityname"],$_REQUEST["snmpv3_securitylevel"],$_REQUEST["snmpv3_authpassphrase"],$_REQUEST["snmpv3_privpassphrase"],$_REQUEST["formula"],$_REQUEST["trends"],$_REQUEST["logtimefmt"],$_REQUEST["serverid"]);
				if($result2)
				{
					$hosts_ok=$hosts_ok." ".$row["host"];
				}
				else
				{
					$hosts_notok=$hosts_notok." ".$row["host"];
				}
			}
			show_messages(TRUE,"Items added]<br>[Success for '$hosts_ok']<br>[Failed for '$hosts_notok'","Cannot add item");
			unset($_REQUEST["itemid"]);
		}
		if($_REQUEST["register"]=="delete")
		{
			delete_item_from_templates($_REQUEST["itemid"]);
			$result=delete_item($_REQUEST["itemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($_REQUEST["itemid"]);
		}
		if($_REQUEST["register"]=="Delete selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_REQUEST[$row["itemid"]]))
				{
					delete_item_from_templates($row["itemid"]);
					$result2=delete_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
		}
		if($_REQUEST["register"]=="Activate selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_REQUEST[$row["itemid"]]))
				{
					$result2=activate_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_ACTIVATED, S_CANNOT_ACTIVATE_ITEMS);
		}
		if($_REQUEST["register"]=="Disable selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_REQUEST[$row["itemid"]]))
				{
					$result2=disable_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_DISABLED, S_CANNOT_DISABLE_ITEMS);
		}
	}
?>

<?php
	$h1=S_CONFIGURATION_OF_ITEMS_BIG;

	if(isset($_REQUEST["groupid"])&&($_REQUEST["groupid"]==0))
	{
		unset($_REQUEST["groupid"]);
	}

	$h2=S_GROUP."&nbsp;";
	$h2=$h2."<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	$h2=$h2.form_select("groupid",0,S_ALL_SMALL);

	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host");
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

	if(isset($_REQUEST["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$_REQUEST["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
	}
	else
	{
		$sql="select h.hostid,h.host from hosts h where h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
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

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"items.php\">", "</form>");
?>

<?php

	if(isset($_REQUEST["hostid"])) 
//	if(isset($_REQUEST["hostid"])&&!isset($_REQUEST["type"])) 
	{
		table_begin();
		if($servers==1||$servers==0)
		{
		table_header(array(S_ID,S_KEY,S_DESCRIPTION,nbsp(S_UPDATE_INTERVAL),S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,S_ERROR,S_ACTIONS));
		}
		else
		{
			table_header(array(S_ID,S_SERVERNAME,S_KEY,S_DESCRIPTION,nbsp(S_UPDATE_INTERVAL),S_HISTORY,S_TRENDS,S_TYPE,S_STATUS,S_ERROR,S_ACTIONS));
		}
		echo "<form method=\"get\" action=\"items.php\">";
		echo "<input class=\"biginput\" name=\"hostid\" type=hidden value=".$_REQUEST["hostid"]." size=8>";
		$result=DBselect("select h.host,i.key_,i.itemid,i.description,h.port,i.delay,i.history,i.lastvalue,i.lastclock,i.status,i.nextcheck,h.hostid,i.type,i.trends,i.serverid,i.error from hosts h,items i where h.hostid=i.hostid and h.hostid=".$_REQUEST["hostid"]." order by h.host,i.key_,i.description");
		$col=0;
		while($row=DBfetch($result))
		{
        		if(!check_right("Item","R",$row["itemid"]))
			{
				continue;
			}

			$input="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["itemid"]."\"> ".$row["itemid"];

			switch($row["type"])
			{
				case 0:
					$type=S_ZABBIX_AGENT;
					break;
				case 7:
					$type=S_ZABBIX_AGENT_ACTIVE;
					break;
				case 1:
					$type=S_SNMPV1_AGENT;
					break;
				case 2:
					$type=S_ZABBIX_TRAPPER;
					break;
				case 3:
					$type=S_SIMPLE_CHECK;
					break;
				case 4:
					$type=S_SNMPV2_AGENT;
					break;
				case 6:
					$type=S_SNMPV3_AGENT;
					break;
				case 5:
					$type=S_ZABBIX_INTERNAL;
					break;
				default:
					$type=S_UNKNOWN;
					break;
			}

			
			switch($row["status"])
			{
				case 0:
					$status=array("value"=>"<a class=\"off\" href=\"items.php?itemid=".$row["itemid"]."&hostid=".$_REQUEST["hostid"]."&register=changestatus&status=1\">".S_ACTIVE."</a>","class"=>"off");
					break;
				case 1:
					$status=array("value"=>"<a class=\"on\" href=\"items.php?itemid=".$row["itemid"]."&hostid=".$_REQUEST["hostid"]."&register=changestatus&status=0\">".S_NOT_ACTIVE."</a>","class"=>"on");
					break;
				case 3:
					$status=array("value"=>S_NOT_SUPPORTED,"class"=>"unknown");
					break;
				default:
					$status=S_UNKNOWN;
			}
	
        		$actions=iif(check_right("Item","U",$row["itemid"]),
				"<A HREF=\"items.php?register=change&itemid=".$row["itemid"].url_param("hostid").url_param("groupid")."#form\">".S_CHANGE."</A>",
				S_CHANGE);

			if($row["error"] == "")
			{
				$error=array("value"=>"&nbsp;","class"=>"off");
			}
			else
			{
				$error=array("value"=>$row["error"],"class"=>"on");
			}
			if($servers==1||$servers==0)
			{
				table_row(array(
					$input,
					$row["key_"],
					$row["description"],
					$row["delay"],
					$row["history"],
					$row["trends"],
					$type,
					$status,
					$error,
					$actions
					),$col++);
			}
			else
			{
				$serverid=$row["serverid"];
			table_row(array(
				$input,
					$serverlist[$serverid],
				$row["key_"],
				$row["description"],
				$row["delay"],
				$row["history"],
				$row["trends"],
				$type,
				$status,
				$error,
				$actions
				),$col++);
		}
		}
		table_end();

		show_form_begin();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Activate selected\" onClick=\"return Confirm('".S_ACTIVATE_SELECTED_ITEMS_Q."');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Disable selected\" onClick=\"return Confirm('".S_DISABLE_SELECTED_ITEMS_Q."');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Delete selected\" onClick=\"return Confirm('".S_DELETE_SELECTED_ITEMS_Q."');\">";
		show_table2_header_end();
		echo "</form>";
	}
	else
	{
//		echo "<center>Select Host</center>";
	}
?>

<?php
	$result=DBselect("select count(*) from hosts");
	if(DBget_field($result,0,0)>0)
	{
		echo "<a name=\"form\"></a>";
		insert_item_form();
	}
?>

<?php
	show_footer();
?>
