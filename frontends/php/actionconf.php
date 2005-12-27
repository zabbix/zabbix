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
//	if(($_REQUEST["triggerid"]!=0)&&!check_right_on_trigger("U",$_REQUEST["triggerid"]))
//	{
//		show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
//		show_footer();
//		exit;
//	}
?>

<?php
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add")
		{
			if($_REQUEST["repeat"]==0)
			{
				$_REQUEST["maxrepeats"]=0;
				$_REQUEST["repeatdelay"]=600;
			}

			$actionid=add_action($_REQUEST["userid"], $_REQUEST["good"], $_REQUEST["delay"], $_REQUEST["subject"], $_REQUEST["message"],$_REQUEST["recipient"],$_REQUEST["usrgrpid"],$_REQUEST["maxrepeats"],$_REQUEST["repeatdelay"]);
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
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] when [".$_REQUEST["good"]."] subject [".$_REQUEST["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_REQUEST["usrgrpid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] when [".$_REQUEST["good"]."] subject [".$_REQUEST["subject"]."]");
				}
			}
		}
		if($_REQUEST["register"]=="update")
		{
			if($_REQUEST["repeat"]==0)
			{
				$_REQUEST["maxrepeats"]=0;
				$_REQUEST["repeatdelay"]=600;
			}
			
			$result=update_action( $_REQUEST["actionid"], $_REQUEST["filter_triggerid"], $_REQUEST["userid"], $_REQUEST["good"], $_REQUEST["delay"], $_REQUEST["subject"], $_REQUEST["message"],$_REQUEST["scope"],$_REQUEST["severity"],$_REQUEST["recipient"],$_REQUEST["usrgrpid"],$_REQUEST["maxrepeats"],$_REQUEST["repeatdelay"]);
			update_action_from_linked_hosts($_REQUEST["actionid"]);
			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
			if($result)
			{
				if(isset($_REQUEST["userid"]))
				{
					$user=get_user_by_userid($_REQUEST["userid"]);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] when [".$_REQUEST["good"]."] subject [".$_REQUEST["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_REQUEST["usrgrpid"]);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] when [".$_REQUEST["good"]."] subject [".$_REQUEST["subject"]."]");
				}
			}
			unset($_REQUEST["actionid"]);
		}
		if($_REQUEST["register"]=="delete")
		{
			delete_action_from_templates($_REQUEST["actionid"]);
			$result=delete_action($_REQUEST["actionid"]);
			show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ACTION,"When [".$_REQUEST["good"]."] subject [".$_REQUEST["subject"]."]");
			}
			unset($_REQUEST["actionid"]);
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
	$h1=S_ACTIONS;

#	$h2=S_GROUP."&nbsp;";
	$h2="";
	$h2=$h2."<select class=\"biginput\" name=\"actiontype\" onChange=\"submit()\">";
	$h2=$h2.form_select("actiontype",0,S_SEND_MESSAGE);
	$h2=$h2.form_select("actiontype",1,S_REMOTE_COMMAND);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"actionconf.php\">", "</form>");

?>

<?php
/*	if(isset($_REQUEST["scope"])&&($_REQUEST["scope"]==2))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a order by a.scope desc";
	}
	elseif(isset($_REQUEST["scope"])&&($_REQUEST["scope"]==1))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a where a.scope=2 or a.scope=1 order by a.recipient desc";
	}
	else
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a where (a.triggerid=".$_REQUEST["triggerid"]." and a.scope=0) or (a.scope=2 or a.scope=1) order by a.recipient desc";
	}*/
//	echo $sql;
	if(isset($_REQUEST["actiontype"])&&($_REQUEST["actiontype"]==1))
	{
		$sql="select * from actions where actiontype=1 order by actiontype, source";
	}
	else
	{
		$sql="select * from actions where actiontype=0 order by actiontype, source";
	}
	$result=DBselect($sql);

	table_begin();
	table_header(array(S_SOURCE,S_SEND_MESSAGE_TO,S_WHEN_TRIGGER,S_DELAY,S_SUBJECT,S_REPEATS,S_ACTIONS));
	$col=0;
	while($row=DBfetch($result))
	{

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
  
		if($row["good"] == 1)
		{
#			echo "<TD><FONT COLOR=\"#AA0000\">".S_ON."</FONT></TD>";
			$good=array("value"=>S_ON,"class"=>"on");
		}
		else if($row["good"] == 0)
		{
#			echo "<TD><FONT COLOR=\"#00AA00\">".S_OFF."</FONT></TD>";
			$good=array("value"=>S_OFF,"class"=>"off");
		}
		else if($row["good"] == 2)
		{
#			echo "<TD><FONT COLOR=\"#AA0000\">".S_ON."</FONT>/<FONT COLOR=\"#00AA00\">OFF</FONT></TD>";
			$good=array("value"=>S_ON."/".S_OFF,"class"=>"on");
		}

		if($row["maxrepeats"] == 0)
		{
			$maxrepeats=S_NO_REPEATS;
		}
		else
		{
			$maxrepeats=$row["maxrepeats"];
		}

		$actions="<A HREF=\"actionconf.php?register=edit&actionid=".$row["actionid"]."#form\">".S_CHANGE."</A>";

		table_row(array(
			get_source_description($row["source"]),
			$recipient,
			$good,
			htmlspecialchars($row["delay"]),
			htmlspecialchars($row["subject"]),
			$maxrepeats,
			$actions
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=7 ALIGN=CENTER>".S_NO_ACTIONS_DEFINED."</TD>";
			echo "<TR>";
	}
	table_end();
?>
</font>
</tr>
</table>

<?php
	echo "<a name=\"form\"></a>";
	insert_action_form();

	show_footer();
?>
