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
	include "include/config.inc.php";

	$page["title"] = S_CONFIGURATION_OF_ZABBIX;
	$page["file"] = "config.php";

	show_header($page["title"],0,0);
	insert_confirm_javascript();
?>

<?php
        if(!check_anyright("Configuration of Zabbix","U"))
        {
                show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font
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
			show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
		}
		if($HTTP_GET_VARS["register"]=="add")
		{
			$result=add_mediatype($HTTP_GET_VARS["type"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["smtp_server"],$HTTP_GET_VARS["smtp_helo"],$HTTP_GET_VARS["smtp_email"],$HTTP_GET_VARS["exec_path"]);
			show_messages($result, S_ADDED_NEW_MEDIA_TYPE, S_NEW_MEDIA_TYPE_WAS_NOT_ADDED);
		}
		if($HTTP_GET_VARS["register"]=="update media")
		{
			$result=update_mediatype($HTTP_GET_VARS["mediatypeid"],$HTTP_GET_VARS["type"],$HTTP_GET_VARS["description"],$HTTP_GET_VARS["smtp_server"],$HTTP_GET_VARS["smtp_helo"],$HTTP_GET_VARS["smtp_email"],$HTTP_GET_VARS["exec_path"]);
			show_messages($result, S_MEDIA_TYPE_UPDATED, S_MEDIA_TYPE_WAS_NOT_UPDATED);
		}
		if($HTTP_GET_VARS["register"]=="delete")
		{
			$result=delete_mediatype($HTTP_GET_VARS["mediatypeid"]);
			show_messages($result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);
			unset($HTTP_GET_VARS["mediatypeid"]);
		}
	}
?>

<?php
	show_table_header(S_CONFIGURATION_OF_ZABBIX_BIG);
	echo "<br>";
?>

<?php
	$config=select_config();
?>


<?php
	show_table2_header_begin();
	echo S_CONFIGURATION;

	show_table2_v_delimiter();
	echo "<form method=\"get\" action=\"config.php\">";
	echo nbsp(S_DO_NOT_KEEP_ALERTS_OLDER_THAN);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alert_history\" value=\"".$config["alert_history"]."\" size=8>";

	show_table2_v_delimiter();
	echo nbsp(S_DO_NOT_KEEP_ALARMS_OLDER_THAN);
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\" size=8>";

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";

	show_table2_header_end();
?>

<?php
	echo "<br>";
	show_table_header(S_AVAILABLE_MEDIA_TYPES);
?>

<?php
	echo "<TABLE BORDER=0 COLS=4 align=center WIDTH=100% BGCOLOR=\"#CCCCCC\" cellspacing=1 cellpadding=3>";
	echo "<TR><TD WIDTH=3%><B>".S_ID."</B></TD>";
	echo "<TD WIDTH=10%><B>".S_TYPE."</B></TD>";
	echo "<TD><B>".S_DESCRIPTION_SMALL."</B></TD>";
	echo "<TD WIDTH=10%><B>".S_ACTIONS."</B></TD>";
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
			echo "<td>".S_EMAIL."</td>";
		}
		else if($row["type"]==1)
		{
			echo "<td>".S_SCRIPT."</td>";
		}
		else
		{
			echo "<td>".S_UNKNOWN."</td>";
		}
		echo "<td>".$row["description"]."</td>";
		echo "<td><a href=\"config.php?register=change&mediatypeid=".$row["mediatypeid"]."\">".S_CHANGE."</a></td>";
		echo "</tr>";
	}
	if(DBnum_rows($result)==0)
	{
			echo "<TR BGCOLOR=#EEEEEE>";
			echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_MEDIA_TYPES_DEFINED."</TD>";
			echo "<TR>";
	}
	echo "</TABLE>";
	echo"<br>";
?>

<?php
	$type=@iif(isset($HTTP_GET_VARS["type"]),$HTTP_GET_VARS["type"],0);
	$description=@iif(isset($HTTP_GET_VARS["description"]),$HTTP_GET_VARS["description"],"");
	$smtp_server=@iif(isset($HTTP_GET_VARS["smtp_server"]),$HTTP_GET_VARS["smtp_server"],"localhost");
	$smtp_helo=@iif(isset($HTTP_GET_VARS["smtp_helo"]),$HTTP_GET_VARS["smtp_helo"],"localhost");
	$smtp_email=@iif(isset($HTTP_GET_VARS["smtp_email"]),$HTTP_GET_VARS["smtp_email"],"zabbix@localhost");
	$exec_path=@iif(isset($HTTP_GET_VARS["exec_path"]),$HTTP_GET_VARS["exec_path"],"");

	if(isset($HTTP_GET_VARS["register"]) && ($HTTP_GET_VARS["register"] == "change"))
	{
		$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path from media_type where mediatypeid=".$HTTP_GET_VARS["mediatypeid"]);
		$mediatypeid=DBget_field($result,0,0);
		$type=@iif(isset($HTTP_GET_VARS["type"]),$HTTP_GET_VARS["type"],DBget_field($result,0,1));
		$description=DBget_field($result,0,2);
		$smtp_server=DBget_field($result,0,3);
		$smtp_helo=DBget_field($result,0,4);
		$smtp_email=DBget_field($result,0,5);
		$exec_path=DBget_field($result,0,6);
	}

?>

<?php
	show_table2_header_begin();
	echo S_MEDIA;

	show_table2_v_delimiter();
	echo "<form name=\"selForm\" method=\"get\" action=\"config.php\">";
	if(isset($mediatypeid))
	{
		echo "<input class=\"biginput\" name=\"mediatypeid\" type=\"hidden\" value=\"$mediatypeid\" size=8>";
	}

	echo S_DESCRIPTION;
	show_table2_h_delimiter();
	echo "<input class=\"biginput\" name=\"description\" value=\"".$description."\" size=30>";

	show_table2_v_delimiter();
	echo S_TYPE;
	show_table2_h_delimiter();
//	echo "<select class=\"biginput\" name=\"type\" size=\"1\" onChange=\"doSelect(this,'sel_dmk')\">";
	echo "<select class=\"biginput\" name=\"type\" size=\"1\" onChange=\"submit()\">";
	if($type==0)
	{
		echo "<option value=\"0\" selected>".S_EMAIL;
		echo "<option value=\"1\">".S_SCRIPT;
	}
	else
	{
		echo "<option value=\"0\">".S_EMAIL;
		echo "<option value=\"1\" selected>".S_SCRIPT;
	}
	echo "</select>";

	if($type==0)
	{
		echo "<input class=\"biginput\" name=\"exec_path\" type=\"hidden\" value=\"$exec_path\">";

		show_table2_v_delimiter();
		echo nbsp(S_SMTP_SERVER);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$smtp_server."\" size=30>";

		show_table2_v_delimiter();
		echo nbsp(S_SMTP_HELO);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$smtp_helo."\" size=30>";

		show_table2_v_delimiter();
		echo nbsp(S_SMTP_EMAIL);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$smtp_email."\" size=30>";
	}
	if($type==1)
	{
		echo "<input class=\"biginput\" name=\"smtp_server\" type=\"hidden\" value=\"$smtp_server\">";
		echo "<input class=\"biginput\" name=\"smtp_helo\" type=\"hidden\" value=\"$smtp_helo\">";
		echo "<input class=\"biginput\" name=\"smtp_email\" type=\"hidden\" value=\"$smtp_email\">";

		show_table2_v_delimiter();
		echo S_SCRIPT_NAME;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"exec_path\" value=\"".$exec_path."\" size=50>";
	}

	show_table2_v_delimiter2();
	echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";

	if(isset($mediatypeid))
	{
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update media\">";
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA."');\">";
	}

	show_table2_header_end();
?>

<?php
	show_footer();
?>
