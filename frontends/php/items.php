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
        $page["title"] = "Configuration of items";
        $page["file"] = "items.php";

        include "include/config.inc.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("Host","U"))
        {
                show_table_header("<font color=\"AA0000\">No permissions !</font
>");
                show_footer();
                exit;
        }
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_item($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["key"],$HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["delay"],$HTTP_GET_VARS["history"],$HTTP_GET_VARS["status"],$HTTP_GET_VARS["type"],$HTTP_GET_VARS["snmp_community"],$HTTP_GET_VARS["snmp_oid"],$HTTP_GET_VARS["value_type"],$HTTP_GET_VARS["trapper_hosts"],$HTTP_GET_VARS["snmp_port"],$HTTP_GET_VARS["units"],$HTTP_GET_VARS["multiplier"],$HTTP_GET_VARS["delta"]);
			show_messages($result,"Item updated","Cannot update item");
//			unset($itemid);
		}
		if($HTTP_GET_VARS["register"]=="changestatus")
		{
			$result=update_item_status($HTTP_GET_VARS["itemid"],$HTTP_GET_VARS["status"]);
			show_messages($result,"Status of item changed","Cannot change item status");
			unset($HTTP_GET_VARS["itemid"]);
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_item($HTTP_GET_VARS["description"],$HTTP_GET_VARS["key"],$HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["delay"],$HTTP_GET_VARS["history"],$HTTP_GET_VARS["status"],$HTTP_GET_VARS["type"],$HTTP_GET_VARS["snmp_community"],$HTTP_GET_VARS["snmp_oid"],$HTTP_GET_VARS["value_type"],$HTTP_GET_VARS["trapper_hosts"],$HTTP_GET_VARS["snmp_port"],$HTTP_GET_VARS["units"],$HTTP_GET_VARS["multiplier"],$HTTP_GET_VARS["delta"]);
			show_messages($result,"Item added","Cannot add item");
			unset($HTTP_GET_VARS["itemid"]);
		}
		if($HTTP_GET_VARS["register"]=="add to all hosts")
		{
			$result=DBselect("select hostid,host from hosts order by host");
			$hosts_ok="";
			$hosts_notok="";
			while($row=DBfetch($result))
			{
				$result2=add_item($HTTP_GET_VARS["description"],$HTTP_GET_VARS["key"],$row["hostid"],$HTTP_GET_VARS["delay"],$HTTP_GET_VARS["history"],$HTTP_GET_VARS["status"],$HTTP_GET_VARS["type"],$HTTP_GET_VARS["snmp_community"],$HTTP_GET_VARS["snmp_oid"],$HTTP_GET_VARS["value_type"],$HTTP_GET_VARS["trapper_hosts"],$HTTP_GET_VARS["snmp_port"],$HTTP_GET_VARS["units"],$HTTP_GET_VARS["multiplier"],$HTTP_GET_VARS["delta"]);
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
			unset($HTTP_GET_VARS["itemid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_item($HTTP_GET_VARS["itemid"]);
			show_messages($result,"Item deleted","Cannot delete item");
			unset($itemid);
		}
		if($HTTP_GET_VARS["register"]=="Delete selected")
		{
			$result=DBselect("select itemid from items where hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($HTTP_GET_VARS[$row["itemid"]]))
				{
					$result2=delete_item($row["itemid"]);
				}
			}
			show_messages(TRUE,"Items deleted","Cannot delete items");
		}
		if($HTTP_GET_VARS["register"]=="Activate selected")
		{
			$result=DBselect("select itemid from items where hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($HTTP_GET_VARS[$row["itemid"]]))
				{
					$result2=activate_item($row["itemid"]);
				}
			}
			show_messages(TRUE,"Items activated","Cannot activate items");
		}
		if($HTTP_GET_VARS["register"]=="Disable selected")
		{
			$result=DBselect("select itemid from items where hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
// $$ is correct here
				if(isset($HTTP_GET_VARS[$row["itemid"]]))
				{
					$result2=disable_item($row["itemid"]);
				}
			}
			show_messages(TRUE,"Items disabled","Cannot disable items");
		}
	}
?>

<?php
	show_table_header_begin();
	echo "CONFIGURATION OF ITEMS";
	show_table_v_delimiter();

//	echo "<font size=2>";

	if(isset($HTTP_GET_VARS["groupid"]))
	{
//		echo "all ";
		echo "<a href='items.php'>all</a> ";
	}
	else
	{
		echo "<b>[<a href='items.php'>all</a>]</b> ";
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
		echo "<a href='items.php?groupid=".$row["groupid"]."'>".$row["name"]."</a>";
		if(isset($HTTP_GET_VARS["groupid"]) && ($HTTP_GET_VARS["groupid"] == $row["groupid"]) )
		{
			echo "]</b>";
		}
		echo " ";
	}
?>

<?php
	show_table_v_delimiter();
	if(isset($HTTP_GET_VARS["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$HTTP_GET_VARS["groupid"]." and hg.hostid=h.hostid order by h.host";
	}
	else
	{
		$sql="select hostid,host from hosts order by host";
	}
	$result=DBselect($sql);
//	$result=DBselect("select hostid,host from hosts order by host");
	while($row=DBfetch($result))
	{
        	if(!check_right("Host","U",$row["hostid"]))
        	{
			continue;
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]))
		{
			echo "<b>[";
		}
		if(isset($HTTP_GET_VARS["groupid"]))
		{
			echo "<A HREF=\"items.php?hostid=".$row["hostid"]."&groupid=".$HTTP_GET_VARS["groupid"]."\">".$row["host"]."</A>";
		}
		else
		{
			echo "<A HREF=\"items.php?hostid=".$row["hostid"]."\">".$row["host"]."</A>";
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($HTTP_GET_VARS["hostid"] == $row["hostid"]))
		{
			echo "]</b>";
		}
		echo " ";
	}
	show_table_header_end();

	$lasthost="";
	if(isset($HTTP_GET_VARS["hostid"])&&!isset($HTTP_GET_VARS["type"])) 
	{
		$result=DBselect("select h.host,i.key_,i.itemid,i.description,h.port,i.delay,i.history,i.lastvalue,i.lastclock,i.status,i.lastdelete,i.nextcheck,h.hostid,i.type from hosts h,items i where h.hostid=i.hostid and h.hostid=".$HTTP_GET_VARS["hostid"]." order by h.host,i.key_,i.description");
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
				echo "<input class=\"biginput\" name=\"hostid\" type=hidden value=".$HTTP_GET_VARS["hostid"]." size=8>";
				echo "<TABLE BORDER=0 COLS=13  align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
				echo "<TR>";
//				echo "<TD WIDTH=3% NOSAVE><B>Sel</B></TD>";
				echo "<TD WIDTH=8% NOSAVE><B>Id</B></TD>";
//				echo "<TD WIDTH=10% NOSAVE><B>Host</B></TD>";
				echo "<TD WIDTH=10% NOSAVE><B>Key</B></TD>";
				echo "<TD WIDTH=10% NOSAVE><B>Description</B></TD>";
				echo "<TD WIDTH=5%  NOSAVE><B>Update interval</B></TD>";
				echo "<TD WIDTH=5%  NOSAVE><B>History</B></TD>";
				echo "<TD><B>Shortname</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>Type</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>Status</B></TD>";
				echo "<TD WIDTH=5% NOSAVE><B>Actions</B></TD>";
				echo "</TR>";
			}
			$lasthost=$row["host"];
		        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
			else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

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
					echo "Zabbix agent";
					break;
				case 1:
					echo "SNMPv1 agent";
					break;
				case 2:
					echo "Zabbix trapper";
					break;
				case 3:
					echo "Simple check";
					break;
				case 4:
					echo "SNMPv2 agent";
					break;
				case 5:
					echo "Zabbix internal";
					break;
				default:
					echo "Unknown";
					break;
			}
			echo "</td>";

			
			echo "<td align=center>";
#			if(isset($HTTP_GET_VARS["hostid"]))
#			{
				switch($row["status"])
				{
					case 0:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=".$HTTP_GET_VARS["hostid"]."&register=changestatus&status=1\"><font color=\"00AA00\">Active</font></a>";
						break;
					case 1:
						echo "<a href=\"items.php?itemid=".$row["itemid"]."&hostid=".$HTTP_GET_VARS["hostid"]."&register=changestatus&status=0\"><font color=\"AA0000\">Not active</font></a>";
						break;
#					case 2:
#						echo "Trapper";
#						break;
					case 3:
						echo "<font color=\"AAAAAA\">Not supported</font>";
						break;
					default:
						echo "<B>$status</B> Unknown";
				}
#			}
#			else
#			{
#				switch($row["status"])
#				{
#					case 0:
#						echo "<a href=\"items.php?itemid=".$row["itemid"]."&register=changestatus&status=1\">Active</a>";
#						break;
#					case 1:
#						echo "<a href=\"items.php?itemid=".$row["itemid"]."&register=changestatus&status=0\">Not active</a>";
#						break;
#					case 2:
#						echo "Trapper";
#						break;
#					case 3:
#						echo "Not supported";
#						break;
#					default:
#						echo "<B>$status</B> Unknown";
#				}
#			}
			echo "</td>";
	
        		if(check_right("Item","U",$row["itemid"]))
			{
				echo "<TD><A HREF=\"items.php?register=change&itemid=".$row["itemid"]."#form\">Change</A></TD>";
			}
			else
			{
				echo "<TD>Change</TD>";
			}
			echo "</TR>";
		}
		echo "</TABLE>";
		show_table2_header_begin();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Activate selected\" onClick=\"return Confirm('Activate selected items?');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Disable selected\" onClick=\"return Confirm('Disable selected items?');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"Delete selected\" onClick=\"return Confirm('Delete selected items?');\">";
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
