<?php
	$page["title"] = "Configuration of Zabbix";
	$page["file"] = "config.php";

	include "include/config.inc.php";
	show_header($page["title"],0,0);
?>

<?php
        if(!check_right("Configuration of Zabbix","U",0))
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
		if($HTTP_GET_VARS["register"]=="update")
		{
			$result=update_config($HTTP_GET_VARS["alarm_history"],$HTTP_GET_VARS["alert_history"]);
			show_messages($result, "Configuration updated", "Configuration was NOT updated");
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_mediatype($HTTP_GET_VARS["type"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["smtp_server"],$HTTP_GET_VARS["smtp_helo"],$HTTP_GET_VARS["smtp_email"],$HTTP_GET_VARS["exec_path"]);
			show_messages($result, "Added new media type", "New media type was NOT added");
		}
		if($HTTP_GET_VARS["register"]=="update media")
		{
			$result=update_mediatype($HTTP_GET_VARS["mediatypeid"],$HTTP_GET_VARS["type"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["smtp_server"],$HTTP_GET_VARS["smtp_helo"],$HTTP_GET_VARS["smtp_email"],$HTTP_GET_VARS["exec_path"]);
			show_messages($result, "Media type was updated", "Media type was NOT updated");
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_mediatype($HTTP_GET_VARS["mediatypeid"]);
			show_messages($result, "Deleted media type", "Media typewas NOT deleted");
			unset($HTTP_GET_VARS["mediatypeid"]);
		}
	}
?>

<?php
	show_table_header("CONFIGURATION OF ZABBIX");
	echo "<br>";
?>

<?php
	$config=select_config();
?>

<?php
	show_table2_header_begin();
	echo "Configuration";

//	show_table2_v_delimiter();
//	echo "<form method=\"get\" action=\"config.php\">";
//	echo "SMTP server";
//	show_table2_h_delimiter();
//	echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$config["smtp_server"]."\"size=40>";

//	show_table2_v_delimiter();
//	echo "Value for SMTP HELO authentification (can be empty)";
//	show_table2_h_delimiter();
//	echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$config["smtp_helo"]."\"size=40>";

//	show_table2_v_delimiter();
//	echo "ZABBIX email address to send alarms from";
//	show_table2_h_delimiter();
//	echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$config["smtp_email"]."\"size=40>";

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"config.php\">";
	echo "Do not keep alerts older than (in days)";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alert_history\" value=\"".$config["alert_history"]."\" size=8>";

	show_table2_v_delimiter();
	echo "Do not keep alarms older than (in days)";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\" size=8>";

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?php
	echo "<br>";
	show_table_header("AVAILABLE MEDIA TYPES");
?>

<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=3%><B>Id</B></TD>";
	echo "<TD WIDTH=10%><B>Type</B></TD>";
	echo "<TD><B>Description</B></TD>";
	echo "<TD WIDTH=10%><B>Actions</B></TD>";
	echo "</TR>";

	$result=DBselect("select mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path from media_type mt order by mt.type");
	$col=0;
	while($row=DBfetch($result))
	{
		if($col++%2==0)	{ echo "<TR BGCOLOR=#EEEEEE>"; }
		else		{ echo "<TR BGCOLOR=#DDDDDD>"; }
		echo "<td>".$row["mediatypeid"]."</td>";
		if($row["type"]==0)
		{
			echo "<td>Email</td>";
		}
		else if($row["type"]==1)
		{
			echo "<td>Script</td>";
		}
		else
		{
			echo "<td>Unknown</td>";
		}
		echo "<td>".$row["description"]."</td>";
		echo "<td><a href=\"config.php?mediatypeid=".$row["mediatypeid"]."\">Change</a></td>";
		echo "</tr>";
	}
	echo "</TABLE>";
	echo"<br>";
?>

<?php
	if(isset($HTTP_GET_VARS["mediatypeid"]))
	{
		$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path from media_type where mediatypeid=".$HTTP_GET_VARS["mediatypeid"]);
		$mediatypeid=DBget_field($result,0,0);
		if(isset($HTTP_GET_VARS["type"]))
		{
			$type=$HTTP_GET_VARS["type"];
		}
		else
		{
			$type=DBget_field($result,0,1);
		}
		$description=DBget_field($result,0,2);
		$smtp_server=DBget_field($result,0,3);
		$smtp_helo=DBget_field($result,0,4);
		$smtp_email=DBget_field($result,0,5);
		$exec_path=DBget_field($result,0,6);
	}
	else
	{
		if(isset($HTTP_GET_VARS["type"]))
		{
			$type=$HTTP_GET_VARS["type"];
		}
		else
		{
			$type=0;
		}
		if(isset($HTTP_GET_VARS["description"]))
		{
			$description=$HTTP_GET_VARS["description"];
		}
		else
		{
			$description=0;
		}
		if(isset($HTTP_GET_VARS["smtp_server"]))
		{
			$smtp_server=$HTTP_GET_VARS["smtp_server"];
		}
		else
		{
			$smtp_server=0;
		}
		if(isset($HTTP_GET_VARS["smtp_helo"]))
		{
			$smtp_helo=$HTTP_GET_VARS["smtp_helo"];
		}
		else
		{
			$smtp_helo=0;
		}
		if(isset($HTTP_GET_VARS["smtp_email"]))
		{
			$smtp_email=$HTTP_GET_VARS["smtp_email"];
		}
		else
		{
			$smtp_email=0;
		}
		if(isset($HTTP_GET_VARS["exec_path"]))
		{
			$exec_path=$HTTP_GET_VARS["exec_path"];
		}
		else
		{
			$exec_path=0;
		}
	}
?>

<?php
	show_table2_header_begin();
	echo "Media";

	show_table2_v_delimiter();
	echo "<form name=\"selForm\" method=\"get\" action=\"config.php\">";
	if(isset($mediatypeid))
	{
		echo "<input class=\"biginput\" name=\"mediatypeid\" type=\"hidden\" value=\"$mediatypeid\" size=8>";
	}

	echo "Description";
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"description\" value=\"".$description."\" size=30>";

	show_table2_v_delimiter();
	echo "Type";
	show_table2_h_delimiter();
//	echo "<select class=\"biginput\" name=\"type\" size=\"1\" onChange=\"doSelect(this,'sel_dmk')\">";
	echo "<select name=\"type\" size=\"1\" onChange=\"submit()\">";
	if($type==0)
	{
		echo "<option value=\"0\" selected>Email";
		echo "<option value=\"1\">Script";
	}
	else
	{
		echo "<option value=\"0\">Email";
		echo "<option value=\"1\" selected>Script";
	}

	if($type==0)
	{
		show_table2_v_delimiter();
		echo "SMTP server";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$smtp_server."\" size=30>";

		show_table2_v_delimiter();
		echo "SMTP helo";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$smtp_helo."\" size=30>";

		show_table2_v_delimiter();
		echo "SMTP email";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$smtp_email."\" size=30>";
	}
	if($type==1)
	{
		show_table2_v_delimiter();
		echo "Script path";
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"exec_path\" value=\"".$exec_path."\" size=50>";
	}

	show_table2_v_delimiter2();
	echo "<input type=\"submit\" name=\"register\" value=\"add\">";

	if(isset($mediatypeid))
	{
		echo "<input type=\"submit\" name=\"register\" value=\"update media\">";
		echo "<input type=\"submit\" name=\"register\" value=\"delete\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>
