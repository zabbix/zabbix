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
	$page["title"] = "Configuration of triggers";
	$page["file"] = "triggers.php";

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
		if($HTTP_GET_VARS["register"]=="add dependency")
		{
			$result=add_trigger_dependency($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["depid"]);
			show_messages($result,"Dependency added","Cannot add dependency");
		}
		if($HTTP_GET_VARS["register"]=="delete dependency")
		{
			$result=delete_trigger_dependency($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["dependency"]);
			show_messages($result,"Dependency deleted","Cannot delete dependency");
		}
		if($HTTP_GET_VARS["register"]=="changestatus")
		{
			$result=update_trigger_status($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["status"]);
			show_messages($result,"Trigger status updated","Cannot update trigger status");
			unset($HTTP_GET_VARS["triggerid"]);
		}
		if($HTTP_GET_VARS["register"]=="enable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($HTTP_GET_VARS[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],0);
				}
			}
			show_messages(TRUE,"Triggers enabled","Cannot enable triggers");
		}
		if($HTTP_GET_VARS["register"]=="disable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($HTTP_GET_VARS[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],1);
				}
			}
			show_messages(TRUE,"Triggers disabled","Cannot disable triggers");
		}
		if($HTTP_GET_VARS["register"]=="delete selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($HTTP_GET_VARS[$row["triggerid"]]))
				{
					$result2=delete_trigger($row["triggerid"]);
				}
			}
			show_messages(TRUE,"Triggers deleted","Cannot delete triggers");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			if(validate_expression($HTTP_GET_VARS["expression"])==0)
			{
				$now=mktime();
				if(isset($HTTP_GET_VARS["disabled"]))	{ $status=1; }
				else			{ $status=0; }
	
				$result=update_trigger($HTTP_GET_VARS["triggerid"],$HTTP_GET_VARS["expression"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["priority"],$status,$HTTP_GET_VARS["comments"],$HTTP_GET_VARS["url"]);
				show_messages($result,"Trigger updated","Cannot update trigger");
			}
			else
			{
				show_error_message("Invalid trigger expression");
			}
			unset($HTTP_GET_VARS["triggerid"]);
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			if(validate_expression($HTTP_GET_VARS["expression"])==0)
			{
				if(isset($HTTP_GET_VARS["disabled"]))	{ $status=1; }
				else			{ $status=0; }
				
				$result=add_trigger($HTTP_GET_VARS["expression"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["priority"],$status,$HTTP_GET_VARS["comments"],$HTTP_GET_VARS["url"]);
				show_messages($result,"Trigger added","Cannot add trigger");
			}
			else
			{
				show_error_message("Invalid trigger expression");
			}
			unset($HTTP_GET_VARS["triggerid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_trigger($HTTP_GET_VARS["triggerid"]);
			show_messages($result,"Trigger deleted","Cannot delete trigger");
			unset($HTTP_GET_VARS["triggerid"]);
		}
	}
?>

<?php
	show_table_header_begin();
	echo "CONFIGURATION OF TRIGGERS";
	show_table_v_delimiter();

//	echo "<font size=2>";

	if(isset($HTTP_GET_VARS["groupid"]))
	{
//		echo "all ";
		echo "<a href='triggers.php'>all</a> ";
	}
	else
	{
		echo "<b>[<a href='triggers.php'>all</a>]</b> ";
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
		echo "<a href='triggers.php?groupid=".$row["groupid"]."'>".$row["name"]."</a>";
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
	while($row=DBfetch($result))
	{
		if(!check_right("Host","U",$row["hostid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($row["hostid"] == $HTTP_GET_VARS["hostid"]))
		{
			echo "<b>[";
		}
		if(isset($HTTP_GET_VARS["groupid"]))
		{
			echo "<A HREF=\"triggers.php?hostid=".$row["hostid"]."&groupid=".$HTTP_GET_VARS["groupid"]."\">".$row["host"]."</A>";
		}
		else
		{
			echo "<A HREF=\"triggers.php?hostid=".$row["hostid"]."\">".$row["host"]."</A>";
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($row["hostid"] == $HTTP_GET_VARS["hostid"]))
		{
			echo "]</b>";
		}
		echo " ";
	}

	show_table_header_end();
?>

<?php

	if(isset($HTTP_GET_VARS["hostid"])&&!isset($HTTP_GET_VARS["triggerid"]))
	{

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.status,t.value,t.priority from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]." order by h.host,t.description");
		$lasthost="";
		$col=0;
		while($row=DBfetch($result))
		{
			if(check_right_on_trigger("R",$row["triggerid"]) == 0)
			{
				continue;
			}
			if($lasthost!=$row["host"])
			{
				if($lasthost!="")
				{
					echo "</TABLE><BR>";
				}
				echo "<br>";
				show_table_header("<A HREF='triggers.php?hostid=".$row["hostid"]."'>".$row["host"]."</A>");
				echo "<form method=\"get\" action=\"triggers.php\">";
				echo "<input class=\"biginput\" name=\"hostid\" type=hidden value=".$HTTP_GET_VARS["hostid"]." size=8>";
				echo "<TABLE BORDER=0 COLS=3 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
				echo "<TR>";
				echo "<TD WIDTH=\"8%\"><B>Id</B></TD>";
				echo "<TD><B>Description</B></TD>";
				echo "<TD><B>Expression</B></TD>";
				echo "<TD WIDTH=5%><B>Severity</B></TD>";
				echo "<TD WIDTH=5%><B>Status</B></TD>";
				echo "<TD WIDTH=15% NOSAVE><B>Actions</B></TD>";
				echo "</TR>\n";
			}
			$lasthost=$row["host"];
	
		        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
			else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

//			$description=stripslashes(htmlspecialchars($row["description"]));

//			if( strstr($description,"%s"))
//			{
				$description=expand_trigger_description($row["triggerid"]);
//			}
			echo "<TD><INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["triggerid"]."\"> ".$row["triggerid"]."</TD>";
			echo "<TD>$description</TD>";
	
			echo "<TD>".explode_exp($row["expression"],1)."</TD>";

			if($row["priority"]==0)		echo "<TD ALIGN=CENTER>Not classified</TD>";
			elseif($row["priority"]==1)	echo "<TD ALIGN=CENTER>Information</TD>";
			elseif($row["priority"]==2)	echo "<TD ALIGN=CENTER>Warning</TD>";
			elseif($row["priority"]==3)	echo "<TD ALIGN=CENTER BGCOLOR=#DDAAAA>Average</TD>";
			elseif($row["priority"]==4)	echo "<TD ALIGN=CENTER BGCOLOR=#FF8888>High</TD>";
			elseif($row["priority"]==5)	echo "<TD ALIGN=CENTER BGCOLOR=RED>Disaster !!!</TD>";
			else				echo "<TD ALIGN=CENTER><B>".$row["priority"]."</B></TD>";

			echo "<TD>";
			if($row["status"] == 1)
			{
				echo "<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=0&hostid=".$row["hostid"]."\"><font color=\"AA0000\">Disabled</font></a>";
			}
			else if($row["status"] == 2)
			{
				echo "<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=1&hostid=".$row["hostid"]."\"><font color=\"AAAAAA\">Unknown</font></a>";
			}
			else
			{
				echo "<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=1&hostid=".$row["hostid"]."\"><font color=\"00AA00\">Enabled</font></a>";
			}
			$expression=rawurlencode($row["expression"]);
			echo "</TD>";

			echo "<TD>";
			if(isset($HTTP_GET_VARS["hostid"]))
			{
				echo "<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."&hostid=".$row["hostid"]."#form\">Change</A> ";
			}
			else
			{
				echo "<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."#form\">Change</A> ";
			}
			echo "-";
			if(get_action_count_by_triggerid($row["triggerid"])>0)
			{
				echo "<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\"><b>A</b>ctions</A>";
			}
			else
			{
				echo "<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">Actions</A>";
			}
			echo "</TD>";
			echo "</TR>";
		}
		echo "</table>";
		show_table2_header_begin();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"enable selected\" onClick=\"return Confirm('Enable selected triggers?');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"disable selected\" onClick=\"return Confirm('Disable selected triggers?');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete selected\" onClick=\"return Confirm('Delete selected triggers?');\">";
		show_table2_header_end();
		echo "</form>";
	}
?>

<?php
	$result=DBselect("select count(*) from hosts");
	if(DBget_field($result,0,0)>0)
	{
		echo "<a name=\"form\"></a>";
		@insert_trigger_form($HTTP_GET_VARS["hostid"],$HTTP_GET_VARS["triggerid"]);
	} 
?>

<?php
	show_footer();
?>
