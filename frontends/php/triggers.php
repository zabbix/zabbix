<?php
	$page["title"] = "Configuration of triggers";
	$page["file"] = "triggers.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_right("Host","U",0))
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
?>

<?php
	$result=DBselect("select hostid,host from hosts order by host");
	while($row=DBfetch($result))
	{
		if(!check_right("Host","R",$row["hostid"]))
		{
			continue;
		}
		if(isset($HTTP_GET_VARS["hostid"]) && ($row["hostid"] == $HTTP_GET_VARS["hostid"]))
		{
			echo "<b>[<A HREF=\"triggers.php?hostid=".$row["hostid"]."\">".$row["host"]."</A>]</b>  ";
		}
		else
		{
			echo "<A HREF=\"triggers.php?hostid=".$row["hostid"]."\">".$row["host"]."</A>  ";
		}
	}

	show_table_header_end();
?>

<?php

	if(isset($HTTP_GET_VARS["hostid"])&&!isset($HTTP_GET_VARS["triggerid"]))
	{

		$result=DBselect("select distinct h.hostid,h.host,t.triggerid,t.expression,t.description,t.status,t.value from triggers t,hosts h,items i,functions f where f.itemid=i.itemid and h.hostid=i.hostid and t.triggerid=f.triggerid and h.hostid=".$HTTP_GET_VARS["hostid"]." order by h.host,t.description");
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
				echo "<TABLE BORDER=0 COLS=3 WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
				echo "<TR>";
				echo "<TD><B>Description</B></TD>";
				echo "<TD><B>Expression</B></TD>";
				echo "<TD WIDTH=5%><B>Status</B></TD>";
				echo "<TD WIDTH=15% NOSAVE><B>Actions</B></TD>";
				echo "</TR>\n";
			}
			$lasthost=$row["host"];
	
		        if($col++%2 == 1)	{ echo "<TR BGCOLOR=#DDDDDD>"; }
			else			{ echo "<TR BGCOLOR=#EEEEEE>"; }

			$description=$row["description"];

			if( strstr($description,"%s"))
			{
				$description=expand_trigger_description($row["triggerid"]);
			}
			echo "<TD>$description</TD>";
	
			echo "<TD>".explode_exp($row["expression"],1)."</TD>";
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
//			echo "-<A HREF=\"actions.php?triggerid=".$row["triggerid"]."&description=".$row["description"]."\">ShowActions</A>";
			echo "-<A HREF=\"actions.php?triggerid=".$row["triggerid"]."\">ShowActions</A>";
			echo "</TD>";
			echo "</TR>\n";
		}
		echo "</table>\n";
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
