<?
	$page["title"]="Actions";
	$page["file"]="actions.php";

	include "include/config.inc";
	show_header($page["title"],0,0);
?>

<?
	if(isset($register))
	{
		if($register=="add")
		{
			$result=add_action( $triggerid, $userid, $good, $delay, $subject, $message );
			show_messages($result,"Action added","Cannot add action");
		}
		if($register=="update")
		{
			$result=update_action( $actionid, $userid, $good, $delay, $subject, $message );
			show_messages($result,"Action updated","Cannot update action");
			unset($actionid);
		}
		if($register=="delete")
		{
			$result=delete_action($actionid);
			show_messages($result,"Action deleted","Cannot delete action");
			unset($actionid);
		}
	}
?>

<?
	$trigger=get_trigger_by_triggerid($triggerid);
	$expression=explode_exp($trigger["expression"],1);
	$description=$trigger["description"];
	show_table_header("$description<BR>$expression");
?>

<hr>

<?
	$sql="select a.actionid,a.triggerid,u.alias,a.good,a.delay,a.subject,a.message from actions a,users u where a.userid=u.userid and a.triggerid=$triggerid order by u.alias, a.good desc";
	$result=DBselect($sql);

	echo "<CENTER>";
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
  
		echo "<TD>".$row["alias"]."</TD>";
		if($row["good"])
		{
			echo "<TD>ON</TD>";
		}
		else
		{
			echo "<TD>OFF</TD>";
		}
		echo "<TD>".$row["delay"]."</TD>";
		echo "<TD>".$row["subject"]."</TD>";
		echo "<TD>".$row["message"]."</TD>";
		echo "<TD>";
		echo " <A HREF=\"actions.php?register=edit&actionid=".$row["actionid"]."&triggerid=".$row["triggerid"]."\">Edit</A>";
		echo ", <A HREF=\"actions.php?register=delete&actionid=".$row["actionid"]."&triggerid=".$row["triggerid"]."\">Delete</A>";
		echo "</TD></TR>";
	}
	echo "</TABLE>";
?>
</font>
</tr>
</table></center>

<?

	if(isset($actionid))
	{
		$sql="select a.actionid,a.triggerid,a.good,a.delay,a.subject,a.message,a.userid from actions a where a.actionid=$actionid";
		$result=DBselect($sql);

		$actionid=DBget_field($result,0,0);
		$triggerid=DBget_field($result,0,1);
		$good=DBget_field($result,0,2);
		$delay=DBget_field($result,0,3);
		$subject=DBget_field($result,0,4);
		$message=DBget_field($result,0,5);
		$uid=DBget_field($result,0,6);
	}
	else
	{
		$trigger=get_trigger_by_triggerid($triggerid);
		$description=$trigger["description"];

		$good=1;
		$delay=30;
		$subject=$description;

		$sql="select i.description, h.host, i.key_ from hosts h, items i,functions f where f.triggerid=$triggerid and h.hostid=i.hostid and f.itemid=i.itemid order by i.description";
		$result=DBselect($sql);
		$message="<INSERT YOUR MESSAGE HERE>\n\n------Latest data------\n\n";
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
	echo "New action";

	show_table2_v_delimiter();
	echo "<form method=\"post\" action=\"actions.php\">";
	echo "<input name=\"triggerid\" type=\"hidden\" value=$triggerid>";
	if(isset($actionid))
	{
		echo "<input name=\"actionid\" type=\"hidden\" value=$actionid>";
	}
	echo "Send message to";
	show_table2_h_delimiter();
	echo "<SELECT NAME=\"userid\" SIZE=\"1\">";

	$sql="select userid,alias from users order by alias";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		if(isset($uid) && ($row["userid"] == $uid))
		{
			echo "<option value=\"".$row["userid"]."\" SELECTED>".$row["alias"];
		}
		else
		{
			echo "<option value=\"".$row["userid"]."\">".$row["alias"];
		}
	}
	echo "</select>";

	show_table2_v_delimiter();
	echo "When trigger becomes";
	show_table2_h_delimiter();
	echo "<select name=\"good\" size=1>";
	echo "<OPTION VALUE=\"1\""; if($good==1) echo "SELECTED"; echo ">ON";
	echo "<OPTION VALUE=\"0\""; if($good==0) echo "SELECTED"; echo ">OFF";
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Delay";
	show_table2_h_delimiter();
	echo "<input name=\"delay\" value=\"$delay\" size=5>";

	show_table2_v_delimiter();
	echo "Subject";
	show_table2_h_delimiter();
	echo "<input name=\"subject\" value=\"$subject\" size=70>";

	show_table2_v_delimiter();
	echo "Message";
	show_table2_h_delimiter();
 	echo "<textarea name=\"message\" cols=70 ROWS=\"7\" wrap=\"soft\">$message</TEXTAREA>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";
	if(isset($actionid))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update\">";
	}

	show_table2_header_end();

	show_footer();
?>
