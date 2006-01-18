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
	$page["title"]="S_ACTIONS";
	$page["file"]="actionconf.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	update_profile("web.menu.config.last",$page["file"]);
//	if(($_REQUEST["triggerid"]!=0)&&!check_right_on_trigger("U",$_REQUEST["triggerid"]))
//	{
//		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
//		show_page_footer();
//		exit;
//	}
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="cancel")
		{
			unset($_REQUEST["form"]);
		}
		elseif($_REQUEST["register"]=="add")
		{
			if($_REQUEST["repeat"]==0)
			{
				$_REQUEST["maxrepeats"]=0;
				$_REQUEST["repeatdelay"]=600;
			}

			$actionid=add_action($_REQUEST["userid"], $_REQUEST["delay"], $_REQUEST["subject"], $_REQUEST["message"],$_REQUEST["recipient"],$_REQUEST["usrgrpid"],$_REQUEST["maxrepeats"],$_REQUEST["repeatdelay"]);
//			add_action_to_linked_hosts($actionid);
			for($i=1;$i<=1000;$i++)
			{
				if(isset($_REQUEST["conditiontype$i"]))
				{
					add_action_condition($actionid,$_REQUEST["conditiontype$i"], $_REQUEST["conditionop$i"], $_REQUEST["conditionvalue$i"]);
				}
			}
			show_messages($actionid,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
			if($actionid)
			{
				if(isset($_REQUEST["userid"]))
				{
					$user=get_user_by_userid($_REQUEST["userid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] subject [".$_REQUEST["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_REQUEST["usrgrpid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] subject [".$_REQUEST["subject"]."]");
				}
			}
			unset($_REQUEST["form"]);
		}
		if($_REQUEST["register"]=="update")
		{
			if($_REQUEST["repeat"]==0)
			{
				$_REQUEST["maxrepeats"]=0;
				$_REQUEST["repeatdelay"]=600;
			}

			$actionid=$_REQUEST["actionid"];

			if($_REQUEST['recipient'] == RECIPIENT_TYPE_USER)
			{
				$id = $_REQUEST['userid'];
			}
			else
			{
				$id = $_REQUEST['usrgrpid'];
			}
			

			update_action($actionid, $id, $_REQUEST["delay"], $_REQUEST["subject"], $_REQUEST["message"],$_REQUEST["recipient"],$_REQUEST["maxrepeats"],$_REQUEST["repeatdelay"]);
			$sql="delete from conditions where actionid=$actionid";
			DBexecute($sql);
			for($i=1;$i<=1000;$i++)
			{
				if(isset($_REQUEST["conditiontype$i"]))
				{
					add_action_condition($actionid,$_REQUEST["conditiontype$i"], $_REQUEST["conditionop$i"], $_REQUEST["conditionvalue$i"]);
				}
			}
			show_messages($actionid,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
			if($actionid)
			{
				if(isset($_REQUEST["userid"]))
				{
					$user=get_user_by_userid($_REQUEST["userid"]);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] subject [".$_REQUEST["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_REQUEST["usrgrpid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] subject [".$_REQUEST["subject"]."]");
				}
			}
			unset($_REQUEST["form"]);
		}
		if($_REQUEST["register"]=="delete")
		{
//			delete_action_from_templates($_REQUEST["actionid"]);
			$result=delete_action($_REQUEST["actionid"]);
			show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ACTION,"Subject [".$_REQUEST["subject"]."]");
			}
			unset($_REQUEST["actionid"]);
			unset($_REQUEST["form"]);
		}
		if($_REQUEST["register"]=="add condition")
		{
			for($i=1;$i<=1000;$i++)
			{
				if(!isset($_REQUEST["conditiontype$i"]))
				{
					$num=$i;
					break;
				}
			}
			$_REQUEST["conditionop$num"]=$_REQUEST["operator"];
			$_REQUEST["conditiontype$num"]=$_REQUEST["conditiontype"];
			$_REQUEST["conditionvalue$num"]=$_REQUEST["value"];
		}
		if($_REQUEST["register"]=="delete selected")
		{
			for($i=1;$i<=1000;$i++)
			{
				if(isset($_REQUEST["conditionchecked$i"]))
				{
					unset($_REQUEST["conditionop$i"]);
					unset($_REQUEST["conditiontype$i"]);
					unset($_REQUEST["conditionvalue$i"]);
				}
			}
		}
	}
?>

<?php
	if(isset($_REQUEST["form"]))
	{
		insert_action_form();
	}
	else
	{
		$h1=S_ACTIONS;

#	$h2=S_GROUP."&nbsp;";
		$h2="";
		$h2=$h2."<select class=\"biginput\" name=\"actiontype\" onChange=\"submit()\">";
		$h2=$h2.form_select("actiontype",0,S_SEND_MESSAGE);
//	$h2=$h2.form_select("actiontype",1,S_REMOTE_COMMAND);
		$h2=$h2."</select>";

		$h2=$h2."&nbsp;|&nbsp;";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_ACTION."\">";

		show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"actionconf.php\">", "</form>");
		$tblActions = new CTableInfo(S_NO_ACTIONS_DEFINED);
		$tblActions->SetHeader(array(S_SOURCE,S_CONDITIONS,S_SEND_MESSAGE_TO,S_DELAY,S_SUBJECT,S_REPEATS,S_ACTIONS));

		if(isset($_REQUEST["actiontype"])&&($_REQUEST["actiontype"]==1))
		{
			$sql="select * from actions where actiontype=1 order by actiontype, source";
		}
		else
		{
			$sql="select * from actions where actiontype=0 order by actiontype, source";
		}
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			$sql="select * from conditions where actionid=".$row["actionid"]." order by conditiontype";
			$result2=DBselect($sql);
			$conditions="";
			while($condition=DBfetch($result2))
			{
				$conditions=$conditions.get_condition_desc($condition["conditiontype"],$condition["operator"],$condition["value"])."<br>";
			}
	
			if($row["recipient"] == RECIPIENT_TYPE_USER)
			{
				$user=get_user_by_userid($row["userid"]);
				$recipient=$user["alias"];
			}
			else
			{
				$groupd=get_usergroup_by_usrgrpid($row["userid"]);
				$recipient=$groupd["name"];
			}

			$tblActions->AddRow(array(
				get_source_description($row["source"]),
				$conditions,
				$recipient,
				htmlspecialchars($row["delay"]),
				htmlspecialchars($row["subject"]),
				$row["maxrepeats"] == 0 ? S_NO_REPEATS : $row["maxrepeats"],
				new CLink(S_CHANGE,"actionconf.php?register=edit&amp;form=0&actionid=".$row['actionid']."#form")
				));	
		}
		$tblActions->Show();
	}

	show_messages();

	show_page_footer();
?>
