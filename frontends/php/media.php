<?php
	$page["title"] = "Media";
	$page["file"] = "media.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_right("User","U",$HTTP_GET_VARS["userid"]))
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
		if($HTTP_GET_VARS["register"]=="enable")
		{
			$result=activate_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media activated","Cannot activate media");
		}
		elseif($HTTP_GET_VARS["register"]=="disable")
		{
			$result=disactivate_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media disabled","Cannot disable media");
		}
		elseif($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_media( $HTTP_GET_VARS["userid"], $HTTP_GET_VARS["mediatypeid"], $HTTP_GET_VARS["sendto"],$HTTP_GET_VARS["severity"]);
			show_messages($result,"Media added","Cannot add media");
		}
		elseif($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_media( $HTTP_GET_VARS["mediaid"] );
			show_messages($result,"Media deleted","Cannot delete media");
			unset($HTTP_GET_VARS["mediaid"]);
		}
	}
?>

<?php
	show_table_header("MEDIA");
?>

<FONT COLOR="#000000">
<?php
	$sql="select m.mediaid,mt.description,m.sendto,m.active from media m,media_type mt where m.mediatypeid=mt.mediatypeid and m.userid=".$HTTP_GET_VARS["userid"]." order by mt.type,m.sendto";
	$result=DBselect($sql);

	echo "<TABLE BORDER=0 WIDTH=100% align=center BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR>";
	echo "<TD><B>Type</B></TD>";
	echo "<TD><B>Send to</B></TD>";
	echo "<TD><B>Status</B></TD>";
	echo "<TD><B>Actions</B></TD>";
	echo "</TR>";

	$col=0;
	for($i=0;$i<DBnum_rows($result);$i++)
	{
		if($col==1)
		{
			echo "<TR BGCOLOR=#DDDDDD>";
			$col=0;
		} else
		{
			echo "<TR BGCOLOR=#EEEEEE>";
			$col=1;
		}
		$mediaid=DBget_field($result,$i,0);
		$description=DBget_field($result,$i,1);
		echo "<TD>";
		echo $description;
		echo "</TD>";
		echo "<TD>",DBget_field($result,$i,2),"</TD>";
		echo "<TD>";
		if(DBget_field($result,$i,3)==0) 
		{
			echo "<a href=\"media.php?register=disable&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\"><font color=\"00AA00\">Enabled</font></A>";
		}
		else
		{
			echo "<a href=\"media.php?register=enable&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\"><font color=\"AA0000\">Disabled</font></A>";
		}
		echo "</TD>";
		echo "<TD>";
		echo "<A HREF=\"media.php?register=delete&mediaid=$mediaid&userid=".$HTTP_GET_VARS["userid"]."\">Delete</A>";
		echo "</TD>";
		echo "</TR>";
	}
	echo "</TABLE>";
?>
</FONT>
</TR>
</TABLE>

<?php
	echo "<br>";
	show_table2_header_begin();
	echo "New media";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"media.php\">";
	echo "<input name=\"userid\" type=\"hidden\" value=".$HTTP_GET_VARS["userid"].">";
	echo "Type";
	show_table2_h_delimiter();
	echo "<select class=\"biginput\" name=\"mediatypeid\" size=1>";
	$sql="select mediatypeid,description from media_type order by type";
	$result=DBselect($sql);
	while($row=DBfetch($result))
	{
		echo "<OPTION VALUE=\"".$row["mediatypeid"]."\">".$row["description"];
	}
	echo "</SELECT>";

	show_table2_v_delimiter();
	echo "Send to";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"sendto\" size=20>";

	show_table2_v_delimiter();
	echo "Use if";
	show_table2_h_delimiter();
	echo "<select multiple class=\"biginput\" name=\"severity[]\" size=\"5\">";
	echo "<option value=\"0\" selected>Not classified";
	echo "<option value=\"1\" selected>Information";
	echo "<option value=\"2\" selected>Warning";
	echo "<option value=\"3\" selected>Average";
	echo "<option value=\"4\" selected>High";
	echo "<option value=\"5\" selected>Disaster";
	echo "</select>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";

	show_table2_header_end();

	show_footer();
?>
