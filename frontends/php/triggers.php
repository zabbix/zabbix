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

	$page["title"] = "S_CONFIGURATION_OF_TRIGGERS";
	$page["file"] = "triggers.php";

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
	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
	}
	if(isset($_GET["hostid"])&&($_GET["hostid"]==0))
	{
		unset($_GET["hostid"]);
	}
?>

<?php
	$_GET["hostid"]=@iif(isset($_GET["hostid"]),$_GET["hostid"],get_profile("web.latest.hostid",0));
	update_profile("web.latest.hostid",$_GET["hostid"]);
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="add dependency")
		{
			$result=add_trigger_dependency($_GET["triggerid"],$_GET["depid"]);
			show_messages($result, S_DEPENDENCY_ADDED, S_CANNOT_ADD_DEPENDENCY);
		}
		if($_GET["register"]=="delete dependency")
		{
			$result=delete_trigger_dependency($_GET["triggerid"],$_GET["dependency"]);
			show_messages($result, S_DEPENDENCY_DELETED, S_CANNOT_DELETE_DEPENDENCY);
		}
		if($_GET["register"]=="changestatus")
		{
			$result=update_trigger_status($_GET["triggerid"],$_GET["status"]);
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			unset($_GET["triggerid"]);
		}
		if($_GET["register"]=="enable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_GET[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],0);
				}
			}
			show_messages(TRUE, S_TRIGGERS_ENABLED, S_CANNOT_UPDATE_TRIGGERS);
		}
		if($_GET["register"]=="disable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_GET[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],1);
				}
			}
			show_messages(TRUE, S_TRIGGERS_DISABLED, S_CANNOT_DISABLE_TRIGGERS);
		}
		if($_GET["register"]=="delete selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_GET["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_GET[$row["triggerid"]]))
				{
					$result2=delete_trigger($row["triggerid"]);
				}
			}
			show_messages(TRUE, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
		}
		if($_GET["register"]=="update")
		{
			if(validate_expression($_GET["expression"])==0)
			{
				$now=mktime();
				if(isset($_GET["disabled"]))	{ $status=1; }
				else			{ $status=0; }
	
				$result=update_trigger($_GET["triggerid"],$_GET["expression"],$_GET["description"],$_GET["priority"],$status,$_GET["comments"],$_GET["url"]);
				show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
			}
			else
			{
				show_error_message(S_INVALID_TRIGGER_EXPRESSION);
			}
			unset($_GET["triggerid"]);
		}
		if($_GET["register"]=="add")
		{
			if(validate_expression($_GET["expression"])==0)
			{
				if(isset($_GET["disabled"]))	{ $status=1; }
				else			{ $status=0; }
				
				$triggerid=add_trigger($_GET["expression"],$_GET["description"],$_GET["priority"],$status,$_GET["comments"],$_GET["url"]);
				add_trigger_to_linked_hosts($triggerid);
				show_messages($triggerid, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
			}
			else
			{
				show_error_message(S_INVALID_TRIGGER_EXPRESSION);
			}
			unset($_GET["triggerid"]);
		}
		if($_GET["register"]=="delete")
		{
			delete_trigger_from_templates($_GET["triggerid"]);
			$result=delete_trigger($_GET["triggerid"]);
			show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);
			unset($_GET["triggerid"]);
		}
	}
?>


<?php
	$h1=S_CONFIGURATION_OF_TRIGGERS_BIG;;

	if(isset($_GET["groupid"])&&($_GET["groupid"]==0))
	{
		unset($_GET["groupid"]);
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

	if(isset($_GET["groupid"]))
	{
		$sql="select h.hostid,h.host from hosts h,hosts_groups hg where hg.groupid=".$_GET["groupid"]." and hg.hostid=h.hostid and h.status<>".HOST_STATUS_DELETED." group by h.hostid,h.host order by h.host";
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

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"triggers.php\">", "</form>");
?>

<?php

	if(isset($_GET["hostid"])&&!isset($_GET["triggerid"]))
	{
		table_begin();
		table_header(array(S_ID,S_DESCRIPTION,S_EXPRESSION, S_SEVERITY, S_STATUS, S_ACTIONS));
		echo "<form method=\"get\" action=\"triggers.php\">";
		echo "<input class=\"biginput\" name=\"hostid\" type=hidden value=".$_GET["hostid"]." size=8>";

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.status,t.value,t.priority from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_GET["hostid"]." order by h.host,t.description");
		$col=0;
		while($row=DBfetch($result))
		{
			if(check_right_on_trigger("R",$row["triggerid"]) == 0)
			{
				continue;
			}
	
			$description=expand_trigger_description($row["triggerid"]);
			$id="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["triggerid"]."\"> ".$row["triggerid"];
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=".$row["triggerid"];
			$result1=DBselect($sql);
			if(DBnum_rows($result1)>0)
			{
				$description=$description."<p><strong>".S_DEPENDS_ON."</strong>:&nbsp;<br>";
				for($i=0;$i<DBnum_rows($result1);$i++)
				{
					$depid=DBget_field($result1,$i,0);
					$depdescr=expand_trigger_description($depid);
					$description=$description."$depdescr<br>";
				}
				$description=$description."</p>";
			}
	
			if($row["priority"]==0)		$priority=S_NOT_CLASSIFIED;
			elseif($row["priority"]==1)	$priority=S_INFORMATION;
			elseif($row["priority"]==2)	$priority=S_WARNING;
			elseif($row["priority"]==3)	$priority=array("value"=>S_AVERAGE,"class"=>"average");
			elseif($row["priority"]==4)	$priority=array("value"=>S_HIGH,"class"=>"high");
			elseif($row["priority"]==5)	$priority=array("value"=>S_DISASTER,"class"=>"disaster");
			else				$priority=$row["priority"];

			if($row["status"] == 1)
			{
				$status="<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=0&hostid=".$row["hostid"]."\"><font color=\"AA0000\">".S_DISABLED."</font></a>";
			}
			else if($row["status"] == 2)
			{
				$status="<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=1&hostid=".$row["hostid"]."\"><font color=\"AAAAAA\">".S_UNKNOWN."</font></a>";
			}
			else
			{
				$status="<a href=\"triggers.php?register=changestatus&triggerid=".$row["triggerid"]."&status=1&hostid=".$row["hostid"]."\"><font color=\"00AA00\">".S_ENABLED."</font></a>";
			}
//			$expression=rawurlencode($row["expression"]);

			if(isset($_GET["hostid"]))
			{
				$actions="<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."&hostid=".$row["hostid"]."#form\">".S_CHANGE."</A>";
			}
			else
			{
				$actions="<A HREF=\"triggers.php?triggerid=".$row["triggerid"]."#form\">".S_CHANGE."</A>";
			}
			$actions=$actions." :: ";
			if(get_action_count_by_triggerid($row["triggerid"])>0)
			{
				$actions=$actions."<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\"><b>A</b>ctions</A>";
			}
			else
			{
				$actions=$actions."<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">".S_ACTIONS."</A>";
			}
			table_row(array(
				$id,
				$description,
				explode_exp($row["expression"],1),
				$priority,
				$status,
				$actions
			),$col++);
		}
		table_end();
		show_form_begin();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"enable selected\" onClick=\"return Confirm('".S_ENABLE_SELECTED_TRIGGERS_Q."');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"disable selected\" onClick=\"return Confirm('Disable selected triggers?');\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete selected\" onClick=\"return Confirm('".S_DISABLE_SELECTED_TRIGGERS_Q."');\">";
		show_table2_header_end();
		echo "</form>";
	}
?>

<?php
	$result=DBselect("select count(*) from hosts");
	if(DBget_field($result,0,0)>0)
	{
		echo "<a name=\"form\"></a>";
		@insert_trigger_form($_GET["hostid"],$_GET["triggerid"]);
	} 
?>

<?php
	show_footer();
?>
