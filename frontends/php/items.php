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

        $page["title"] = S_CONFIGURATION_OF_ITEMS;
        $page["file"] = "items.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("Host","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font
>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="update")
		{
			$result=update_item($_GET["itemid"],$_GET["description"],$_GET["key"],$_GET["hostid"],$_GET["delay"],$_GET["history"],$_GET["status"],$_GET["type"],$_GET["snmp_community"],$_GET["snmp_oid"],$_GET["value_type"],$_GET["trapper_hosts"],$_GET["snmp_port"],$_GET["units"],$_GET["multiplier"],$_GET["delta"]);
			show_messages($result, S_ITEM_UPDATED, S_CANNOT_UPDATE_ITEM);
//			unset($itemid);
		}
		if($_GET["register"]=="changestatus")
		{
			$result=update_item_status($_GET["itemid"],$_GET["status"]);
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			unset($_GET["itemid"]);
		}
		if($_GET["register"]=="add")
		{
			$result=add_item($_GET["description"],$_GET["key"],$_GET["hostid"],$_GET["delay"],$_GET["history"],$_GET["status"],$_GET["type"],$_GET["snmp_community"],$_GET["snmp_oid"],$_GET["value_type"],$_GET["trapper_hosts"],$_GET["snmp_port"],$_GET["units"],$_GET["multiplier"],$_GET["delta"]);
			show_messages($result, S_ITEM_ADDED, S_CANNOT_ADD_ITEM);
			unset($_GET["itemid"]);
		}
		if($_GET["register"]=="add to all hosts")
		{
			$result=DBselect("select hostid,host from hosts order by host");
			$hosts_ok="";
			$hosts_notok="";
			while($row=DBfetch($result))
			{
				$result2=add_item($_GET["description"],$_GET["key"],$row["hostid"],$_GET["delay"],$_GET["history"],$_GET["status"],$_GET["type"],$_GET["snmp_community"],$_GET["snmp_oid"],$_GET["value_type"],$_GET["trapper_hosts"],$_GET["snmp_port"],$_GET["units"],$_GET["multiplier"],$_GET["delta"]);
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
			unset($_GET["itemid"]);
		}
		if($_GET["register"]=="delete")
		{
			$result=delete_item($_GET["itemid"]);
			show_messages($result, S_ITEM_DELETED, S_CANNOT_DELETE_ITEM);
			unset($itemid);
		}
		if($_GET["register"]=="Delete selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["itemid"]]))
				{
					$result2=delete_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_DELETED, S_CANNOT_DELETE_ITEMS);
		}
		if($_GET["register"]=="Activate selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["itemid"]]))
				{
					$result2=activate_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_ACTIVATED, S_CANNOT_ACTIVATE_ITEMS);
		}
		if($_GET["register"]=="Disable selected")
		{
			$result=DBselect("select itemid from items where hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($_GET[$row["itemid"]]))
				{
					$result2=disable_item($row["itemid"]);
				}
			}
			show_messages(TRUE, S_ITEMS_DISABLED, S_CANNOT_DISABLE_ITEMS);
		}
	}
?>

<?php
	show_table_header_begin();
	echo S_CONFIGURATION_OF_ITEMS_BIG;;
	show_table_v_delimiter();

// Start of new code
	echo "<form name=\"form2\" method=\"get\" action=\"items.php\">";

	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}

	echo S_GROUP."&nbsp;";
	echo "<select class=\"biginput\" name=\"groupid\" onChange=\"submit()\">";
	echo "<option value=\"0\" ".iif(!isset($_GET["groupid"]),"selected","").">".S_ALL_SMALL;

	$result=DBselect("select groupid,name from groups order by name");
	while($row=DBfetch($result))
	{
// Check if at least one host with read permission exists for this group
		$result2=DBselect("select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status in (0,2) and h.hostid=i.hostid and hg.groupid=".$row["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host");
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
			echo "<option value=\"".$row["groupid"]."\" ".iif(isset($_GET["groupid"])&&($_GET["groupid"]==$row["groupid"]),"selected","").">".$row["name"];
		}
	}
	echo "</select>";

	echo "&nbsp;".S_HOST."&nbsp;";
	echo "<select class=\"biginput\" name=\"hostid\" onChange=\"submit()\">";

	$sql=iif(isset($_GET["groupid"]),
		"select h.hostid,h.host from hosts h,items i,hosts_groups hg where h.status in (0,2) and h.hostid=i.hostid and hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid group by h.hostid,h.host order by h.host",
		"select h.hostid,h.host from hosts h,items i where h.status in (0,2) and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(!check_right("Host","U",$row["hostid"]))
		{
			continue;
		}
		echo "<option value=\"".$row["hostid"]."\"".iif(isset($_GET["hostid"])&&($_GET["hostid"]==$row["hostid"]),"selected","").">".$row["host"];
	}
	echo "</select>";

	echo "</form>";
// end of new code

	show_table_header_end();

	$lasthost="";
	if(isset($_GET["hostid"])&&!isset($_GET["type"])) 
	{
		$result=DBselect("select h.host,i.key_,i.itemid,i.description,h.port,i.delay,i.history,i.lastvalue,i.lastclock,i.status,i.lastdelete,i.nextcheck,h.hostid,i.type from hosts h,items i where h.hostid=i.hostid and h.hostid=".$_GET["hostid"]." order by h.host,i.key_,i.description");
		$col=0;
		while($row=DBfetch($result))
		{
        		if(!check_right("Item","R",$row["itemid"]))
			{
				continue;
			}
			if($lasthost != $row["host"])
			{
				if($lasthost != "")
				{
					echo "</TABLE><BR>";
				}
				echo "<br>";
				show_table_header("<A HREF='items.php?hostid=".$row["hostid"]."'>".$row["host"]."</A>");
				echo "<form method=\"get\" action=\"items.php\">";
				echo "<input class=\"biginput\" name=\"hostid\" type=hidden value=".$_GET["hostid"]." size=8>";
				echo "<TABLE BORDER=0 COLS=13  align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
				echo "<TR>";
//				echo "<TD WIDTH=3% NOSAVE><B>Sel</B></TD>";
				echo "<TD WIDTH=8% NOSAVE><B>".S_ID."</B></TD>";
//				echo "<TD WIDTH=10% NOSAVE><B>".S_HOST."</B></TD>";
				echo "<TD WIDTH=10% NOSAVE><B>".S_KEY."</B></TD>";
				echo "<TD WIDTH=10% NOSAVE><B>".S_DESCRIPTION."</B></TD>";
				echo "<TD WIDTH=5%  NOSAVE><B>".S_UPDATE_INTERVAL."</B></TD>";
				echo "<TD WIDTH=5%  NOSAVE><B>".S_HISTORY."</B></TD>";
				echo "<TD><B>".S_SHORT_NAME."</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>".S_TYPE."</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>".S_STATUS."</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>".S_ACTIONS."</B></TD>";
				echo "</TR>";
			}
			$lasthost=$row["host"];
		        iif_echo($col++%2 == 1,
				"<TR BGCOLOR=#DDDDDD>",
				"<TR BGCOLOR=#EEEEEE>");

			echo "<TD><INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["itemid"]."\"> ".$row["itemid"]."</TD>";
//			echo "<TD>".$row["itemid"]."</TD>";
//			echo "<TD>".$row["host"]."</TD>";
			echo "<TD>".$row["key_"]."</TD>";
			echo "<TD>".$row["description"]."</TD>";
			echo "<TD>".$row["delay"]."</TD>";
			echo "<TD>".$row["history"]."</TD>";
			echo "<TD>".$row["host"].":".$row["key_"]."</TD>";
	
			echo "<td align=center>";
			switch($row["type"])
			{
				case 0:
					echo S_ZABBIX_AGENT;
					break;
				case 1:
					echo S_SNMPV1_AGENT;
					break;
				case 2:
					echo S_ZABBIX_TRAPPER;
					break;
				case 3:
					echo S_SIMPLE_CHECK;
					break;
				case 4:
					echo S_SNMPV2_AGENT;
					break;
				case 5:
					echo S_ZABBIX_INTERNAL;
					break;
				default:
					echo S_UNKNOWN;
					break;
			}
			echo "</td>";

			
			echo "<td align=center>";
			switch($row["status"])
			{
				case 0:
					echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=".$_GET["hostid"]."&register=changestatus&status=1\"><font color=\"00AA00\">".S_ACTIVE."</font></a>";
					break;
				case 1:
					echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=".$_GET["hostid"]."&register=changestatus&status=0\"><font color=\"AA0000\">".S_NOT_ACTIVE."</font></a>";
					break;
#				case 2:
#					echo "Trapper";
#					break;
				case 3:
					echo "<font color=\"AAAAAA\">".S_NOT_SUPPORTED."</font>";
					break;
				default:
					echo S_UNKNOWN;
			}
			echo "</td>";
	
        		iif_echo(check_right("Item","U",$row["itemid"]),
				"<TD><A HREF=\"items.php?register=change&itemid=".$row["itemid"]."#form\">".S_CHANGE."</A></TD>",
				"<TD>".S_CHANGE."</TD>");
			echo "</TR>";
		}
		echo "</TABLE>";
		show_table2_header_begin();
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
