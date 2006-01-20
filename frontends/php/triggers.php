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
                show_page_footer();
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
	if(isset($_REQUEST["save"])&&!isset($_REQUEST["triggerid"]))
	{
		if(validate_expression($_REQUEST["expression"])==0)
		{
			if(isset($_REQUEST["disabled"]))	{ $status=1; }
			else			{ $status=0; }
			
			$triggerid=add_trigger($_REQUEST["expression"],$_REQUEST["description"],$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"]);
			add_trigger_to_linked_hosts($triggerid);
			show_messages($triggerid, S_TRIGGER_ADDED, S_CANNOT_ADD_TRIGGER);
			if($triggerid){
				unset($_REQUEST["form"]);
			}
		}
		else
		{
			show_error_message(S_INVALID_TRIGGER_EXPRESSION);
		}
		unset($_REQUEST["triggerid"]);
	}

	if(isset($_REQUEST["save"])&&isset($_REQUEST["triggerid"]))
	{
		if(validate_expression($_REQUEST["expression"])==0)
		{
			$now=mktime();
			if(isset($_REQUEST["disabled"]))	{ $status=1; }
			else			{ $status=0; }

			$result=update_trigger($_REQUEST["triggerid"],$_REQUEST["expression"],$_REQUEST["description"],$_REQUEST["priority"],$status,$_REQUEST["comments"],$_REQUEST["url"]);
			update_trigger_from_linked_hosts($_REQUEST["triggerid"]);
			show_messages($result, S_TRIGGER_UPDATED, S_CANNOT_UPDATE_TRIGGER);
			if($result)
			{
				unset($_REQUEST["form"]);
			}
		}
		else
		{
			show_error_message(S_INVALID_TRIGGER_EXPRESSION);
		}
		unset($_REQUEST["triggerid"]);
	}

	if(isset($_REQUEST["delete"]))
	{
		delete_trigger_from_templates($_REQUEST["triggerid"]);
		$result=delete_trigger($_REQUEST["triggerid"]);
		show_messages($result, S_TRIGGER_DELETED, S_CANNOT_DELETE_TRIGGER);
		unset($_REQUEST["triggerid"]);
	}

	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add dependency")
		{
			$result=add_trigger_dependency($_REQUEST["triggerid"],$_REQUEST["depid"]);
			show_messages($result, S_DEPENDENCY_ADDED, S_CANNOT_ADD_DEPENDENCY);
		}
		if($_REQUEST["register"]=="delete dependency")
		{
			$result=delete_trigger_dependency($_REQUEST["triggerid"],$_REQUEST["dependency"]);
			show_messages($result, S_DEPENDENCY_DELETED, S_CANNOT_DELETE_DEPENDENCY);
		}
		if($_REQUEST["register"]=="changestatus")
		{
			$result=update_trigger_status($_REQUEST["triggerid"],$_REQUEST["status"]);
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			unset($_REQUEST["triggerid"]);
		}
		if($_REQUEST["register"]=="enable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_REQUEST[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],0);
				}
			}
			show_messages(TRUE, S_TRIGGERS_ENABLED, S_CANNOT_UPDATE_TRIGGERS);
		}
		if($_REQUEST["register"]=="disable selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_REQUEST[$row["triggerid"]]))
				{
					$result2=update_trigger_status($row["triggerid"],1);
				}
			}
			show_messages(TRUE, S_TRIGGERS_DISABLED, S_CANNOT_DISABLE_TRIGGERS);
		}
		if($_REQUEST["register"]=="delete selected")
		{
			$result=DBselect("select distinct t.triggerid from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]);
			while($row=DBfetch($result))
			{
				if(isset($_REQUEST[$row["triggerid"]]))
				{
					$result2=delete_trigger($row["triggerid"]);
				}
			}
			show_messages(TRUE, S_TRIGGERS_DELETED, S_CANNOT_DELETE_TRIGGERS);
		}
		if($_REQUEST["register"]=="delete")
		{
		}
	}
?>


<?php
	$h1=S_CONFIGURATION_OF_TRIGGERS_BIG;;

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

	$h2=$h2."&nbsp;|&nbsp;";
	$h2=$h2."<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_TRIGGER."\">";

	show_header2($h1, $h2, "<form name=\"form2\" method=\"get\" action=\"triggers.php\">", "</form>");
?>

<?php

	if(!isset($_REQUEST["form"]))
	{
		$table = new CTableInfo();
		$table->SetHeader(array(S_ID,S_NAME,S_EXPRESSION, S_SEVERITY, S_STATUS, S_ERROR));

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.status,t.value,t.priority,t.error from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$_REQUEST["hostid"]." order by h.host,t.description");
		while($row=DBfetch($result))
		{
			if(check_right_on_trigger("R",$row["triggerid"]) == 0)
			{
				continue;
			}
	
			$description=expand_trigger_description($row["triggerid"]);
			if(isset($_REQUEST["hostid"]))
			{
				$description="<A HREF=\"triggers.php?form=0&triggerid=".$row["triggerid"]."&hostid=".$row["hostid"]."\">$description</A>";
			}
			else
			{
				$description="<A HREF=\"triggers.php?form=0&triggerid=".$row["triggerid"]."\">$description</A>";
			}

			$id="<INPUT TYPE=\"CHECKBOX\" class=\"biginput\" NAME=\"".$row["triggerid"]."\"> ".$row["triggerid"];
			$sql="select t.triggerid,t.description from triggers t,trigger_depends d where t.triggerid=d.triggerid_up and d.triggerid_down=".$row["triggerid"];
			$result1=DBselect($sql);
			if(DBnum_rows($result1)>0)
			{
				$description=$description."<p><strong>".S_DEPENDS_ON."</strong>:&nbsp;<br>";
				while($row1=DBfetch($result1))
				{
					$depid=$row1["triggerid"];
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

			if($row["error"]=="")
			{
				$row["error"]="&nbsp;";
			}

//			$actions=$actions." :: ";
//			if(get_action_count_by_triggerid($row["triggerid"])>0)
//			{
//				$actions=$actions."<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\"><b>A</b>ctions</A>";
//			}
//			else
//			{
//				$actions=$actions."<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">".S_ACTIONS."</A>";
//			}
			$table->addRow(array(
				$id,
				$description,
				explode_exp($row["expression"],1),
				$priority,
				$status,
				$row["error"]
			));
		}
		
		$footerButtons = array();
		array_push($footerButtons, new CButton('register','enable selected',
			"return Confirm('".S_ENABLE_SELECTED_TRIGGERS_Q."');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('register','disable selected',
			"return Confirm('Disable selected triggers?');"));
		array_push($footerButtons, SPACE);
		array_push($footerButtons, new CButton('register','delete selected',
			"return Confirm('".S_DISABLE_SELECTED_TRIGGERS_Q."');"));
		$table->SetFooter(new CCol($footerButtons),'table_footer');

		$form = new CForm('triggers.php');
		$form->AddVar('hostid',$_REQUEST["hostid"]);
		$form->AddItem($table);
		$form->Show();
	}
	else
	{
		$result=DBselect("select count(*) as cnt from hosts");
		$row=DBfetch($result);
		if($row["cnt"]>0)
		{
			echo BR;
			@insert_trigger_form($_REQUEST["hostid"],$_REQUEST["triggerid"]);
		} 
	}
?>

<?php
	show_page_footer();
?>
