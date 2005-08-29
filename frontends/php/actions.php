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
	$page["title"]="S_ACTIONS";
	$page["file"]="actions.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(($_GET["triggerid"]!=0)&&!check_right_on_trigger("U",$_GET["triggerid"]))
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
			$actionid=add_action( $_GET["triggerid"], $_GET["userid"], $_GET["good"], $_GET["delay"], $_GET["subject"], $_GET["message"],$_GET["scope"],$_GET["severity"],$_GET["recipient"],$_GET["usrgrpid"]);
			add_action_to_linked_hosts($actionid);
			show_messages($actionid,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
			if($actionid)
			{
				if(isset($_GET["userid"]))
				{
					$user=get_user_by_userid($_GET["userid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] when [".$_GET["good"]."] subject [".$_GET["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_GET["usrgrpid"]);
					add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] when [".$_GET["good"]."] subject [".$_GET["subject"]."]");
				}
			}
		}
		if($_GET["register"]=="update")
		{
			$result=update_action( $_GET["actionid"], $_GET["triggerid"], $_GET["userid"], $_GET["good"], $_GET["delay"], $_GET["subject"], $_GET["message"],$_GET["scope"],$_GET["severity"],$_GET["recipient"],$_GET["usrgrpid"]);
			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPATE_ACTION);
			if($result)
			{
				if(isset($_GET["userid"]))
				{
					$user=get_user_by_userid($_GET["userid"]);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION,"User [".$user["alias"]."] when [".$_GET["good"]."] subject [".$_GET["subject"]."]");
				}
				else
				{
					$group=get_group_by_groupid($_GET["usrgrpid"]);
					add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION,"User [".$group["name"]."] when [".$_GET["good"]."] subject [".$_GET["subject"]."]");
				}
			}
			unset($_GET["actionid"]);
		}
		if($_GET["register"]=="delete")
		{
			delete_action_from_templates($_GET["actionid"]);
			$result=delete_action($_GET["actionid"]);
			show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ACTION,"When [".$_GET["good"]."] subject [".$_GET["subject"]."]");
			}
			unset($_GET["actionid"]);
		}
	}
?>

<?php
	$trigger=get_trigger_by_triggerid($_GET["triggerid"]);
	$expression=explode_exp($trigger["expression"],1);
//	$description=$trigger["description"];
//	if( strstr($description,"%s"))
//	{
		$description=expand_trigger_description($_GET["triggerid"]);
//	}
	show_table_header("$description<BR>$expression");
?>

<?php
/*	if(isset($_GET["scope"])&&($_GET["scope"]==2))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a order by a.scope desc";
	}
	elseif(isset($_GET["scope"])&&($_GET["scope"]==1))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a where a.scope=2 or a.scope=1 order by a.recipient desc";
	}
	else
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient,a.scope from actions a where (a.triggerid=".$_GET["triggerid"]." and a.scope=0) or (a.scope=2 or a.scope=1) order by a.recipient desc";
	}*/
//	echo $sql;
	$sql="select actionid,userid,delay,subject,message,scope,severity,recipient,good,triggerid from actions where (scope=0 and triggerid=".$_GET["triggerid"].") or scope=1 or scope=2";
	$result=DBselect($sql);

	table_begin();
	table_header(array(S_SCOPE,S_SEND_MESSAGE_TO,S_WHEN_TRIGGER,S_DELAY,S_SUBJECT,S_ACTIONS));
	$col=0;
	while($row=DBfetch($result))
	{

		if($row["scope"] == 1)
		{
			$sql="select h.hostid from triggers t,hosts h,functions f,items i where f.triggerid=t.triggerid and h.hostid=i.hostid and i.itemid=f.itemid and t.triggerid=".$_GET["triggerid"];
//			echo "$sql<br>";
			$result2=DBselect($sql);
			$found=0;
			while($row2=DBfetch($result2))
			{
//				$sql="select * from actions a,triggers t,hosts h,functions f,items i where a.triggerid=t.triggerid and f.triggerid=t.triggerid and h.hostid=a.triggerid and i.hostid=h.hostid and a.actionid=".$row["actionid"]." and a.scope=1 and h.hostid=".$row2["hostid"];
				$sql="select * from actions a where a.actionid=".$row["actionid"]." and a.scope=1 and a.triggerid=".$row2["hostid"];
//				echo "$sql<br>";
				$result3=DBselect($sql);
				if(DBnum_rows($result3)>0)	$found=1;
			}
			if($found==0)	continue;
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
		$actions="<A HREF=\"actions.php?register=edit&actionid=".$row["actionid"]."&triggerid=".$_GET["triggerid"]."#form\">Change</A>";

		table_row(array(
			get_scope_description($row["scope"]),
			$recipient,
			$good,
			htmlspecialchars($row["delay"]),
			htmlspecialchars($row["subject"]),
			$actions
			),$col++);
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=6 ALIGN=CENTER>".S_NO_ACTIONS_DEFINED."</TD>";
			echo "<TR>";
	}
	table_end();
?>
</font>
</tr>
</table>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($_GET["actionid"]))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.scope,a.severity,a.recipient from actions a where a.actionid=".$_GET["actionid"];
		$result=DBselect($sql);

		$actionid=DBget_field($result,0,0);
		$triggerid=DBget_field($result,0,1);
		$good=DBget_field($result,0,2);
		$delay=DBget_field($result,0,3);
		// Otherwise symbols like ",' will not be shown
		$subject=htmlspecialchars(DBget_field($result,0,4));
		$message=DBget_field($result,0,5);
		$uid=DBget_field($result,0,6);
		$scope=@iif(isset($_GET["scope"]),$_GET["scope"],DBget_field($result,0,7));
		$severity=DBget_field($result,0,8);
		$recipient=@iif(isset($_GET["recipient"]),$_GET["recipient"],DBget_field($result,0,9));
	}
	else
	{
		$trigger=get_trigger_by_triggerid($_GET["triggerid"]);
		$description=htmlspecialchars(stripslashes($trigger["description"]));

//		$delay=30;
		$delay=@iif(isset($_GET["delay"]),$_GET["delay"],30);
//		$subject=$description;
		$subject=@iif(isset($_GET["subject"]),$_GET["subject"],$description);
		$scope=@iif(isset($_GET["scope"]),$_GET["scope"],0);
		$good=@iif(isset($_GET["good"]),$_GET["good"],1);
		$recipient=@iif(isset($_GET["recipient"]),$_GET["recipient"],RECIPIENT_TYPE_GROUP);
//		$severity=0;
		$severity=@iif(isset($_GET["severity"]),$_GET["severity"],0);

		$sql="select i.description, h.host, i.key_ from hosts h, items i,functions f where f.triggerid=".$_GET["triggerid"]." and h.hostid=i.hostid and f.itemid=i.itemid order by i.description";
		$result=DBselect($sql);
		if(isset($_GET["message"]))
		{
			$message=$_GET["message"];
		}
		else
		{
			$message="INSERT YOUR MESSAGE HERE\n\n------Latest data------\n\n";
			while($row=DBfetch($result))
			{
				$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".last(0)}  (latest value)\n";
				$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".max(300)} (maximum value for last 5 min)\n";
				$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".min(300)} (minimum value for last 5 min)\n\n";
			}
			$message=$message."---------End--------\n";
		}
	}
	show_form_begin("actions.action");
	echo nbsp(S_NEW_ACTION);
	$col=0;

	show_table2_v_delimiter($col++);
	echo "<form method=\"get\" action=\"actions.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=".$_GET["triggerid"].">";
	if(isset($_GET["actionid"]))
	{
		echo "<input name=\"actionid\" type=\"hidden\" value=".$_GET["actionid"].">";
	}
	echo nbsp(S_SEND_MESSAGE_TO);
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"recipient\" size=\"1\" onChange=\"submit()\">";

	echo "<option value=\"0\""; if($recipient==RECIPIENT_TYPE_USER) echo " selected"; echo ">".S_SINGLE_USER;
	echo "<option value=\"1\""; if($recipient==RECIPIENT_TYPE_GROUP) echo " selected"; echo ">".S_USER_GROUP;
	echo "</select>";

	if($recipient==RECIPIENT_TYPE_GROUP)
	{
		show_table2_v_delimiter($col++);
		echo nbsp(S_GROUP);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"usrgrpid\" size=\"1\">";
	
		$sql="select usrgrpid,name from usrgrp order by name";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
//			if(isset($usrgrpid) && ($row["usrgrpid"] == $usrgrpid))
			if(isset($uid) && ($row["usrgrpid"] == $uid))
			{
				echo "<option value=\"".$row["usrgrpid"]."\" selected>".$row["name"];
			}
			else
			{
				echo "<option value=\"".$row["usrgrpid"]."\">".$row["name"];
			}
		}
		echo "</select>";
	}
	else
	{
		show_table2_v_delimiter($col++);
		echo nbsp(S_USER);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"userid\" size=\"1\">";
	
		$sql="select userid,alias from users order by alias";
		$result=DBselect($sql);
		while($row=DBfetch($result))
		{
			if(isset($uid) && ($row["userid"] == $uid))
			{
				echo "<option value=\"".$row["userid"]."\" selected>".$row["alias"];
			}
			else
			{
				echo "<option value=\"".$row["userid"]."\">".$row["alias"];
			}
		}
		echo "</select>";
	}
	
	show_table2_v_delimiter($col++);
	echo nbsp(S_WHEN_TRIGGER_BECOMES);
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"good\" size=1>";
	echo "<OPTION VALUE=\"1\""; if($good==1) echo "SELECTED"; echo ">".S_ON;
	echo "<OPTION VALUE=\"0\""; if($good==0) echo "SELECTED"; echo ">".S_OFF;
	echo "<OPTION VALUE=\"2\""; if($good==2) echo "SELECTED"; echo ">".S_ON_OR_OFF;
	echo "</SELECT>";

	show_table2_v_delimiter($col++);
	echo nbsp(S_DELAY_BETWEEN_MESSAGES_IN_SEC);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";

	show_table2_v_delimiter($col++);
	echo S_SUBJECT;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"subject\" value=\"$subject\" size=70>";

	show_table2_v_delimiter($col++);
	echo S_MESSAGE;
	show_table2_h_delimiter();
 	echo "<textarea class=\"biginput\" name=\"message\" cols=70 ROWS=\"7\" wrap=\"soft\">$message</TEXTAREA>";

	show_table2_v_delimiter($col++);
	echo S_SCOPE;
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"scope\" size=1 onChange=\"submit()\">";
	echo "<OPTION VALUE=\"0\""; if($scope==0) echo "SELECTED"; echo ">".S_THIS_TRIGGER_ONLY;
	echo "<OPTION VALUE=\"1\""; if($scope==1) echo "SELECTED"; echo ">".S_ALL_TRIGGERS_OF_THIS_HOST;
	echo "<OPTION VALUE=\"2\""; if($scope==2) echo "SELECTED"; echo ">".S_ALL_TRIGGERS;
	echo "</SELECT>";

	if($scope>0)
	{
		show_table2_v_delimiter($col++);
		echo nbsp(S_USE_IF_TRIGGER_SEVERITY);
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"severity\" size=1>";
		echo "<OPTION VALUE=\"0\" "; if($severity==0) echo "SELECTED"; echo ">".S_NOT_CLASSIFIED;
		echo "<OPTION VALUE=\"1\" "; if($severity==1) echo "SELECTED"; echo ">".S_INFORMATION;
		echo "<OPTION VALUE=\"2\" "; if($severity==2) echo "SELECTED"; echo ">".S_WARNING;
		echo "<OPTION VALUE=\"3\" "; if($severity==3) echo "SELECTED"; echo ">".S_AVERAGE;
		echo "<OPTION VALUE=\"4\" "; if($severity==4) echo "SELECTED"; echo ">".S_HIGH;
		echo "<OPTION VALUE=\"5\" "; if($severity==5) echo "SELECTED"; echo ">".S_DISASTER;
		echo "</SELECT>";
	}
	else
	{
		echo "<input name=\"severity\" type=\"hidden\" value=$severity>";
	}

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($actionid))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('Delete selected action?');\">";
	}

	show_table2_header_end();

	show_footer();
?>
