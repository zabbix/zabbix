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
	$page["title"]="Actions";
	$page["file"]="actions.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
	if(!check_right_on_trigger("U",$HTTP_GET_VARS["triggerid"]))
	{
		show_table_header("<font color=\"AA0000\">No permissions !</font>");
		show_footer();
		exit;
	}
?>

<?php
	if(isset($HTTP_GET_VARS["register"]))
	{
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_action( $HTTP_GET_VARS["triggerid"], $HTTP_GET_VARS["userid"], $HTTP_GET_VARS["good"], $HTTP_GET_VARS["delay"], $HTTP_GET_VARS["subject"], $HTTP_GET_VARS["message"],$HTTP_GET_VARS["scope"],$HTTP_GET_VARS["severity"],$HTTP_GET_VARS["recipient"],$HTTP_GET_VARS["usrgrpid"]);
			show_messages($result,"Action added","Cannot add action");
		}
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_action( $HTTP_GET_VARS["actionid"], $HTTP_GET_VARS["userid"], $HTTP_GET_VARS["good"], $HTTP_GET_VARS["delay"], $HTTP_GET_VARS["subject"], $HTTP_GET_VARS["message"],$HTTP_GET_VARS["scope"],$HTTP_GET_VARS["severity"],$HTTP_GET_VARS["recipient"],$HTTP_GET_VARS["usrgrpid"]);
			show_messages($result,"Action updated","Cannot update action");
			unset($HTTP_GET_VARS["actionid"]);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_action($HTTP_GET_VARS["actionid"]);
			show_messages($result,"Action deleted","Cannot delete action");
			unset($HTTP_GET_VARS["actionid"]);
		}
	}
?>

<?php
	$trigger=get_trigger_by_triggerid($HTTP_GET_VARS["triggerid"]);
	$expression=explode_exp($trigger["expression"],1);
//	$description=$trigger["description"];
//	if( strstr($description,"%s"))
//	{
		$description=expand_trigger_description($HTTP_GET_VARS["triggerid"]);
//	}
	show_table_header("$description<BR>$expression");
?>

<?php
	$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.recipient from actions a where a.triggerid=".$HTTP_GET_VARS["triggerid"]." order by a.recipient desc";
	$result=DBselect($sql);

	echo "<div align=center>";
	echo "<TABLE BORDER=0 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD><b>Send message to</b></TD>";
	echo "<TD><b>When trigger</b></TD>";
	echo "<TD><b>Delay</b></TD>";                            
	echo "<TD><b>Subject</b></TD>";
	echo "<TD><b>Message</b></TD>";
	echo "<TD><b>Actions</b></TD>";                               
	echo "</TR>";
	$col=0;
	while($row=DBfetch($result))
	{
		if(isset($actionid) && ($actionid==$row["actionid"]))
		{
			echo "<TR BGCOLOR=#FFDDDD>";
			$col++;
		} 
		else
		{
			if($col++%2 == 1)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
			else			{ echo "<TR BGCOLOR=#DDDDDD>"; }
		}
		if($row["recipient"] == RECIPIENT_TYPE_USER)
		{
			$user=get_user_by_userid($row["userid"]);
			echo "<TD>".$user["alias"]."</TD>";
		}
		else
		{
			$groupd=get_usergroup_by_usrgrpid($row["userid"]);
			echo "<TD>".$groupd["name"]."</TD>";
		}
  
		if($row["good"] == 1)
		{
			echo "<TD><FONT COLOR=\"#AA0000\">ON</FONT></TD>";
		}
		else if($row["good"] == 0)
		{
			echo "<TD><FONT COLOR=\"#00AA00\">OFF</FONT></TD>";
		}
		else if($row["good"] == 2)
		{
			echo "<TD><FONT COLOR=\"#AA0000\">ON</FONT>/<FONT COLOR=\"#00AA00\">OFF</FONT></TD>";
		}
		echo "<TD>".htmlspecialchars($row["delay"])."</TD>";
		echo "<TD>".htmlspecialchars($row["subject"])."</TD>";
		echo "<TD>";
		echo "<pre>".htmlspecialchars($row["message"])."</pre>";
		echo "</TD>";
		echo "<TD>";
		echo " <A HREF=\"actions.php?register=edit&actionid=".$row["actionid"]."&triggerid=".$row["triggerid"]."#form\">Change</A>";
		echo "</TD></TR>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=6 ALIGN=CENTER>-No actions defined-</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
?>
</font>
</tr>
</table>
</div>

<?php
	echo "<a name=\"form\"></a>";

	if(isset($HTTP_GET_VARS["actionid"]))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid,a.scope,a.severity,a.recipient from actions a where a.actionid=".$HTTP_GET_VARS["actionid"];
		$result=DBselect($sql);

		$actionid=DBget_field($result,0,0);
		$triggerid=DBget_field($result,0,1);
		$good=DBget_field($result,0,2);
		$delay=DBget_field($result,0,3);
		// Otherwise symbols like ",' will not be shown
		$subject=htmlspecialchars(DBget_field($result,0,4));
		$message=DBget_field($result,0,5);
		$uid=DBget_field($result,0,6);
		$scope=@iif(isset($HTTP_GET_VARS["scope"]),$HTTP_GET_VARS["scope"],DBget_field($result,0,7));
		$severity=DBget_field($result,0,8);
		$recipient=@iif(isset($HTTP_GET_VARS["recipient"]),$HTTP_GET_VARS["recipient"],DBget_field($result,0,9));
	}
	else
	{
		$trigger=get_trigger_by_triggerid($HTTP_GET_VARS["triggerid"]);
		$description=$trigger["description"];

		$delay=30;
		$subject=$description;
		$scope=@iif(isset($HTTP_GET_VARS["scope"]),$HTTP_GET_VARS["scope"],0);
		$good=@iif(isset($HTTP_GET_VARS["good"]),$HTTP_GET_VARS["good"],1);
		$recipient=@iif(isset($HTTP_GET_VARS["recipient"]),$HTTP_GET_VARS["recipient"],RECIPIENT_TYPE_GROUP);
		$severity=0;

		$sql="select i.description, h.host, i.key_ from hosts h, items i,functions f where f.triggerid=".$HTTP_GET_VARS["triggerid"]." and h.hostid=i.hostid and f.itemid=i.itemid order by i.description";
		$result=DBselect($sql);
		$message="INSERT YOUR MESSAGE HERE\n\n------Latest data------\n\n";
		while($row=DBfetch($result))
		{
			$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".last(0)}  (latest value)\n";
			$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".max(300)} (maximum value for last 5 min)\n";
			$message=$message.$row["description"].": {".$row["host"].":".$row["key_"].".min(300)} (minimum value for last 5 min)\n\n";
		}
		$message=$message."---------End--------\n";
	}
	echo "<br>";
	show_table2_header_begin();
	echo nbsp("New action");

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"actions.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=".$HTTP_GET_VARS["triggerid"].">";
	if(isset($HTTP_GET_VARS["actionid"]))
	{
		echo "<input name=\"actionid\" type=\"hidden\" value=".$HTTP_GET_VARS["actionid"].">";
	}
	echo nbsp("Send message to");
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"recipient\" size=\"1\" onChange=\"submit()\">";

	echo "<option value=\"0\""; if($recipient==RECIPIENT_TYPE_USER) echo " selected"; echo ">Single user";
	echo "<option value=\"1\""; if($recipient==RECIPIENT_TYPE_GROUP) echo " selected"; echo ">User group";
	echo "</select>";

	if($recipient==RECIPIENT_TYPE_GROUP)
	{
		show_table2_v_delimiter();
		echo nbsp("Group");
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
		show_table2_v_delimiter();
		echo nbsp("User");
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
	
	show_table2_v_delimiter();
	echo nbsp("When trigger becomes");
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"good\" size=1>";
	echo "<OPTION VALUE=\"1\""; if($good==1) echo "SELECTED"; echo ">ON";
	echo "<OPTION VALUE=\"0\""; if($good==0) echo "SELECTED"; echo ">OFF";
	echo "<OPTION VALUE=\"2\""; if($good==2) echo "SELECTED"; echo ">ON or OFF";
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo nbsp("Delay between messages (in sec)");
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"delay\" value=\"$delay\" size=5>";

	show_table2_v_delimiter();
	echo "Subject";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"subject\" value=\"$subject\" size=70>";

	show_table2_v_delimiter();
	echo "Message";
	show_table2_h_delimiter();
 	echo "<textarea class=\"biginput\" name=\"message\" cols=70 ROWS=\"7\" wrap=\"soft\">$message</TEXTAREA>";

	show_table2_v_delimiter();
	echo "Scope";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"scope\" size=1 onChange=\"submit()\">";
	echo "<OPTION VALUE=\"0\""; if($scope==0) echo "SELECTED"; echo ">This trigger only";
	echo "<OPTION VALUE=\"1\""; if($scope==1) echo "SELECTED"; echo ">All triggers of this host";
	echo "<OPTION VALUE=\"2\""; if($scope==2) echo "SELECTED"; echo ">All triggers";
	echo "</SELECT>";

	if($scope>0)
	{
		show_table2_v_delimiter();
		echo nbsp("Use if trigger's severity equal or more than");
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"severity\" size=1>";
		echo "<OPTION VALUE=\"0\" "; if($severity==0) echo "SELECTED"; echo ">Not classified";
		echo "<OPTION VALUE=\"1\" "; if($severity==1) echo "SELECTED"; echo ">Information";
		echo "<OPTION VALUE=\"2\" "; if($severity==2) echo "SELECTED"; echo ">Warning";
		echo "<OPTION VALUE=\"3\" "; if($severity==3) echo "SELECTED"; echo ">Average";
		echo "<OPTION VALUE=\"4\" "; if($severity==4) echo "SELECTED"; echo ">High";
		echo "<OPTION VALUE=\"5\" "; if($severity==5) echo "SELECTED"; echo ">Disaster";
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
