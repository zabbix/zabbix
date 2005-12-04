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

	$page["title"] = "S_CONFIGURATION_OF_ZABBIX";
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
	update_profile("web.menu.config.last",$page["file"]);
?>

<?php
/*	if(isset($_REQUEST["config"]))	$_REQUEST["config"]=$_REQUEST["config"]; */

	$_REQUEST["config"]=@iif(isset($_REQUEST["config"]),$_REQUEST["config"],get_profile("web.config.config",0));
	update_profile("web.config.config",$_REQUEST["config"]);

# For images
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="add image")
		{
			$result=add_image($_REQUEST["name"],$_REQUEST["imagetype"],$_FILES);
			show_messages($result, S_IMAGE_ADDED, S_CANNOT_ADD_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
			}
			unset($_REQUEST["imageid"]);
		}
		if($_REQUEST["register"]=="delete image")
		{
			$result=delete_image($_REQUEST["imageid"]);
			show_messages($result, S_IMAGE_DELETED, S_CANNOT_DELETE_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
			}
			unset($_REQUEST["imageid"]);
		}
		if($_REQUEST["register"]=="update image")
		{
			$result=update_image($_REQUEST["imageid"],$_REQUEST["name"],$_REQUEST["imagetype"],$_FILES);
			show_messages($result, S_IMAGE_UPDATED, S_CANNOT_UPDATE_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image updated");
			}
			unset($_REQUEST["imageid"]);
		}
	}
	if(isset($_REQUEST["register"]))
	{
		if($_REQUEST["register"]=="update")
		{
			$result=update_config($_REQUEST["alarm_history"],$_REQUEST["alert_history"],$_REQUEST["refresh_unsupported"]);
			show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Alarm history [".$_REQUEST["alarm_history"]."] alert history [".$_REQUEST["alert_history"]."]");
			}
		}
		if($_REQUEST["register"]=="add rule")
		{
			$result=add_escalation_rule($_REQUEST["escalationid"],$_REQUEST["level"],$_REQUEST["period"],$_REQUEST["delay"],$_REQUEST["actiontype"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation ID [".addslashes($_REQUEST["escalationid"])."]");
			}
			show_messages($result, S_ESCALATION_RULE_ADDED, S_ESCALATION_RULE_WAS_NOT_ADDED);
		}
		if($_REQUEST["register"]=="update rule")
		{
			$result=update_escalation_rule($_REQUEST["escalationruleid"],$_REQUEST["level"],$_REQUEST["period"],$_REQUEST["delay"],$_REQUEST["actiontype"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation rule ID [".addslashes($_REQUEST["escalationruleid"])."]");
			}
			show_messages($result, S_ESCALATION_RULE_UPDATED, S_ESCALATION_RULE_WAS_NOT_UPDATED);
		}
		if($_REQUEST["register"]=="delete rule")
		{
			$result=delete_escalation_rule($_REQUEST["escalationruleid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation rule ID [".addslashes($_REQUEST["escalationruleid"])."]");
			}
			unset($_REQUEST["escalationruleid"]);
			show_messages($result, S_ESCALATION_RULE_DELETED, S_ESCALATION_RULE_WAS_NOT_DELETED);
		}
		if($_REQUEST["register"]=="add escalation")
		{
			$dflt=iif(isset($_REQUEST["dflt"])&&($_REQUEST["dflt"]=="on"),1,0);
			$result=add_escalation($_REQUEST["name"],$dflt);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ESCALATION,"Escalation [".addslashes($_REQUEST["name"])."]");
			}
			show_messages($result, S_ESCALATION_ADDED, S_ESCALATION_WAS_NOT_ADDED);
		}
		if($_REQUEST["register"]=="update escalation")
		{
			$dflt=iif(isset($_REQUEST["dflt"])&&($_REQUEST["dflt"]=="on"),1,0);
			$result=update_escalation($_REQUEST["escalationid"],$_REQUEST["name"],$dflt);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ESCALATION,"Escalation [".addslashes($_REQUEST["name"])."]");
			}
			show_messages($result, S_ESCALATION_UPDATED, S_ESCALATION_WAS_NOT_UPDATED);
		}
		if($_REQUEST["register"]=="delete escalation")
		{
			$result=delete_escalation($_REQUEST["escalationid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ESCALATION,"Escalation ID [".addslashes($_REQUEST["$escalationid"])."]");
			}
			unset($_REQUEST["escalationid"]);
			show_messages($result, S_ESCALATION_DELETED, S_ESCALATION_WAS_NOT_DELETED);
		}
		if($_REQUEST["register"]=="add autoregistration")
		{
			$result=add_autoregistration($_REQUEST["pattern"],$_REQUEST["priority"],$_REQUEST["hostid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
			}
			show_messages($result, S_AUTOREGISTRATION_ADDED, S_CANNOT_ADD_AUTOREGISTRATION);
		}
		if($_REQUEST["register"]=="update autoregistration")
		{
			$result=update_autoregistration($_REQUEST["id"],$_REQUEST["pattern"],$_REQUEST["priority"],$_REQUEST["hostid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
			}
			show_messages($result, S_AUTOREGISTRATION_UPDATED, S_AUTOREGISTRATION_WAS_NOT_UPDATED);
		}
		if($_REQUEST["register"]=="delete autoregistration")
		{
			$result=delete_autoregistration($_REQUEST["id"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
			}
			show_messages($result, S_AUTOREGISTRATION_DELETED, S_AUTOREGISTRATION_WAS_NOT_DELETED);
		}
		if($_REQUEST["register"]=="add")
		{
			$result=add_mediatype($_REQUEST["type"],$_REQUEST["description"],$_REQUEST["smtp_server"],$_REQUEST["smtp_helo"],$_REQUEST["smtp_email"],$_REQUEST["exec_path"]);
			show_messages($result, S_ADDED_NEW_MEDIA_TYPE, S_NEW_MEDIA_TYPE_WAS_NOT_ADDED);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".addslashes($_REQUEST["description"])."]");
			}
		}
		if($_REQUEST["register"]=="update media")
		{
			$result=update_mediatype($_REQUEST["mediatypeid"],$_REQUEST["type"],$_REQUEST["description"],$_REQUEST["smtp_server"],$_REQUEST["smtp_helo"],$_REQUEST["smtp_email"],$_REQUEST["exec_path"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".addslashes($_REQUEST["description"])."]");
			}
			show_messages($result, S_MEDIA_TYPE_UPDATED, S_MEDIA_TYPE_WAS_NOT_UPDATED);
		}
		if($_REQUEST["register"]=="delete")
		{
			$mediatype=get_mediatype_by_mediatypeid($_REQUEST["mediatypeid"]);
			$result=delete_mediatype($_REQUEST["mediatypeid"]);
			show_messages($result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".$mediatype["description"]."]");
			}
			unset($_REQUEST["mediatypeid"]);
		}
	}
?>

<?php

	$h1=S_CONFIGURATION_OF_ZABBIX_BIG;

#	$h2=S_GROUP."&nbsp;";
	$h2="";
	$h2=$h2."<select class=\"biginput\" name=\"config\" onChange=\"submit()\">";
	$h2=$h2.form_select("config",0,S_HOUSEKEEPER);
	$h2=$h2.form_select("config",1,S_MEDIA_TYPES);
	$h2=$h2.form_select("config",2,S_ESCALATION_RULES);
	$h2=$h2.form_select("config",3,S_IMAGES);
	$h2=$h2.form_select("config",4,S_AUTOREGISTRATION);
	$h2=$h2.form_select("config",5,S_OTHER);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"config.php\">", "</form>");

#	show_table_header(S_CONFIGURATION_OF_ZABBIX_BIG);
?>

<?php
	if($_REQUEST["config"]==0)
	{
		$config=select_config();

		$col=0;
		show_form_begin("config.housekeeper");
		echo S_HOUSEKEEPER;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
		echo "<input type=\"hidden\" name=\"refresh_unsupported\" value=\"".$config["refresh_unsupported"]."\">";
		echo nbsp(S_DO_NOT_KEEP_ACTIONS_OLDER_THAN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alert_history\" value=\"".$config["alert_history"]."\" size=8>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_DO_NOT_KEEP_EVENTS_OLDER_THAN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\" size=8>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";

		show_table2_header_end();
	}
?>

<?php
	if($_REQUEST["config"]==5)
	{
		$config=select_config();

		$col=0;
		show_form_begin("config.other");
		echo S_OTHER_PARAMETERS;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"5\" size=8>";
		echo "<input type=\"hidden\" name=\"alert_history\" value=\"".$config["alert_history"]."\">";
		echo "<input type=\"hidden\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\">";
		echo nbsp(S_REFRESH_UNSUPPORTED_ITEMS);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"refresh_unsupported\" value=\"".$config["refresh_unsupported"]."\" size=8>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update\">";

		show_table2_header_end();
	}
?>

<?php
	if($_REQUEST["config"]==3)
	{
		echo "<br>";
		show_table_header(S_IMAGES_BIG);

		table_begin();
		table_header(array(S_ID,S_TYPE,S_NAME,S_ACTIONS));

		$result=DBselect("select imageid,imagetype,name,image from images order by name");
		$col=0;
		while($row=DBfetch($result))
		{
			if($row["imagetype"]==1)
			{
				$imagetype=S_ICON;
			}
			else if($row["imagetype"]==2)
			{
				$imagetype=S_BACKGROUND;
			}
			else
			{
				$imagetype=S_UNKNOWN;
			}
			$actions="<a href=\"image.php?imageid=".$row["imageid"]."\">".S_SHOW."</a>";
			$actions=$actions." :: <a href=\"config.php?config=3&register=change&imageid=".$row["imageid"]."\">".S_CHANGE."</a>";
			table_row(array(
				$row["imageid"],
				$imagetype,
				$row["name"],
//				"<img src=\"image.php?imageid=".$row["imageid"]."\">",
				$actions),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_IMAGES_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();

		if(!isset($_REQUEST["imageid"]))
		{
			$name="";
			$imagetype=1;
		}
		else
		{
			$result=DBselect("select imageid,imagetype,name,image from images where imageid=".$_REQUEST["imageid"]);
			$row=DBfetch($result);
			$name=$row["name"];
			$imagetype=$row["imagetype"];
			$imageid=$row["imageid"];
		}

		$col=0;
		show_form_begin("config.images");
		echo S_IMAGE;

		show_table2_v_delimiter($col++);
#		echo "<form method=\"get\" action=\"config.php\">";
		echo "<form enctype=\"multipart/form-data\" method=\"post\" action=\"config.php\">";
		echo "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"".(1024*1024)."\">";
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"3\" size=8>";
		if(isset($imageid))
		{
			echo "<input class=\"biginput\" name=\"imageid\" type=\"hidden\" value=\"$imageid\" size=8>";
		}
		echo nbsp(S_NAME);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"name\" value=\"".$name."\" size=64>";

		show_table2_v_delimiter($col++);
		echo S_TYPE;
		show_table2_h_delimiter();
		echo "<select class=\"biginput\" name=\"imagetype\" size=\"1\">";
		if($imagetype==1)
		{
			echo "<option value=\"1\" selected>".S_ICON;
			echo "<option value=\"2\">".S_BACKGROUND;
		}
		else
		{
			echo "<option value=\"1\">".S_ICON;
			echo "<option value=\"2\" selected>".S_BACKGROUND;
		}
		echo "</select>";

		show_table2_v_delimiter($col++);
		echo S_UPLOAD;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"image\" type=\"file\">";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add image\">";
		if(isset($_REQUEST["imageid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update image\" onClick=\"return Confirm('".S_UPDATE_SELECTED_IMAGE."');\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete image\" onClick=\"return Confirm('".S_DELETE_SELECTED_IMAGE."');\">";
		}

		show_table2_header_end();
	}
?>

<?php
	if($_REQUEST["config"]==1)
	{
		echo "<br>";
		show_table_header(S_MEDIA_TYPES_BIG);

		table_begin();
		table_header(array(S_ID,S_TYPE,S_DESCRIPTION_SMALL,S_ACTIONS));

		$result=DBselect("select mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path from media_type mt order by mt.type");
		$col=0;
		while($row=DBfetch($result))
		{
			if($row["type"]==0)
			{
				$type=S_EMAIL;
			}
			else if($row["type"]==1)
			{
				$type=S_SCRIPT;
			}
			else
			{
				$type=S_UNKNOWN;
			}
			$actions="<a href=\"config.php?config=1&register=change&mediatypeid=".$row["mediatypeid"]."\">".S_CHANGE."</a>";
			table_row(array(
				$row["mediatypeid"],
				$type,
				$row["description"],
				$actions),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_MEDIA_TYPES_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();
?>

<?php
		$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],0);
		$description=@iif(isset($_REQUEST["description"]),$_REQUEST["description"],"");
		$smtp_server=@iif(isset($_REQUEST["smtp_server"]),$_REQUEST["smtp_server"],"localhost");
		$smtp_helo=@iif(isset($_REQUEST["smtp_helo"]),$_REQUEST["smtp_helo"],"localhost");
		$smtp_email=@iif(isset($_REQUEST["smtp_email"]),$_REQUEST["smtp_email"],"zabbix@localhost");
		$exec_path=@iif(isset($_REQUEST["exec_path"]),$_REQUEST["exec_path"],"");

		if(isset($_REQUEST["register"]) && ($_REQUEST["register"] == "change"))
		{
			$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path from media_type where mediatypeid=".$_REQUEST["mediatypeid"]);
			$row=DBfetch($result);
			$mediatypeid=$row["mediatypeid"];
			$type=@iif(isset($_REQUEST["type"]),$_REQUEST["type"],$row["type"]);
			$description=$row["description"];
			$smtp_server=$row["smtp_server"];
			$smtp_helo=$row["smtp_helo"];
			$smtp_email=$row["smtp_email"];
			$exec_path=$row["exec_path"];
		}

?>

<?php
		show_form_begin("config.medias");
		echo S_MEDIA;

		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form name=\"selForm\" method=\"get\" action=\"config.php\">";
		if(isset($_REQUEST["mediatypeid"]))
		{
			echo "<input class=\"biginput\" name=\"mediatypeid\" type=\"hidden\" value=\"".$_REQUEST["mediatypeid"]."\" size=8>";
		}
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"1\" size=8>";

		echo S_DESCRIPTION;
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"description\" value=\"".$description."\" size=30>";

		show_table2_v_delimiter($col++);
		echo S_TYPE;
		show_table2_h_delimiter();
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

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_SERVER);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_server\" value=\"".$smtp_server."\" size=30>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_HELO);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_helo\" value=\"".$smtp_helo."\" size=30>";

			show_table2_v_delimiter($col++);
			echo nbsp(S_SMTP_EMAIL);
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"smtp_email\" value=\"".$smtp_email."\" size=30>";
		}
		if($type==1)
		{
			echo "<input class=\"biginput\" name=\"smtp_server\" type=\"hidden\" value=\"$smtp_server\">";
			echo "<input class=\"biginput\" name=\"smtp_helo\" type=\"hidden\" value=\"$smtp_helo\">";
			echo "<input class=\"biginput\" name=\"smtp_email\" type=\"hidden\" value=\"$smtp_email\">";

			show_table2_v_delimiter($col++);
			echo S_SCRIPT_NAME;
			show_table2_h_delimiter();
			echo "<input class=\"biginput\" name=\"exec_path\" value=\"".$exec_path."\" size=50>";
		}

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"add\">";

		if(isset($_REQUEST["mediatypeid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update media\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA."');\">";
		}

		show_table2_header_end();
	}
?>

<?php
	if($_REQUEST["config"]==2)
	{
		echo "<br>";
		show_table_header(S_ESCALATION_RULES_BIG);

		table_begin();
		table_header(array(S_ID,S_DESCRIPTION_SMALL,S_DEFAULT,S_ACTIONS));

		$result=DBselect("select * from escalations order by name");
		$col=0;
		while($row=DBfetch($result))
		{
			$yes=iif($row["dflt"]==1,array("value"=>S_YES,"class"=>"on"),array("value"=>S_NO,"class"=>"off"));

			$actions="<a href=\"config.php?config=2&register=change&escalationid=".$row["escalationid"]."\">".S_CHANGE."</a>";
			table_row(array(
				$row["escalationid"],
				$row["name"],
				$yes,
				$actions),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=4 ALIGN=CENTER>".S_NO_ESCALATION_RULES_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();

		insert_escalation_form($_REQUEST["escalationid"]);

		if(isset($_REQUEST["escalationid"]))
		{
			echo "<br>";
			show_table_header(S_ESCALATION_RULES);

			table_begin();
			table_header(array(S_LEVEL,S_PERIOD,S_DELAY_BEFORE_ACTION,S_DO,S_ACTIONS));

			$result=DBselect("select * from escalation_rules order by level");
			$col=0;
			while($row=DBfetch($result))
			{
				$actions="<a href=\"config.php?config=2&register=change&escalationid=".$_REQUEST["escalationid"]."&escalationruleid=".$row["escalationruleid"]."\">".S_CHANGE."</a>";
				$actiontypes=array("Do nothing","Execute action","Increase severity","Increase administrative hierarcy");

				table_row(array(
					$row["level"],
					$row["period"],
					$row["delay"],
					$actiontypes[$row["actiontype"]],
					$actions),$col++);
			}
			if(DBnum_rows($result)==0)
			{
					echo "<TR BGCOLOR=#EEEEEE>";
					echo "<TD COLSPAN=5 ALIGN=CENTER>".S_NO_ESCALATION_DETAILS."</TD>";
					echo "<TR>";
			}
			table_end();

			insert_escalation_rule_form($_REQUEST["escalationid"],$_REQUEST["escalationruleid"]);
		}
	}
?>

<?php
	if($_REQUEST["config"]==4)
	{
		echo "<br>";
		show_table_header(S_AUTOREGISTRATION_RULES_BIG);

		table_begin();
		table_header(array(S_ID,S_PRIORITY,S_PATTERN,S_HOST,S_ACTIONS));

		$result=DBselect("select * from autoreg order by priority");
		$col=0;
		while($row=DBfetch($result))
		{
			if($row["hostid"]==0)
			{
				$name="&nbsp;";
			}
			else
			{
				$host=get_host_by_hostid($row["hostid"]);
				$name=$host["host"];
			}
			$actions="<a href=\"config.php?config=4&register=change&id=".$row["id"]."\">".S_CHANGE."</a>";
			table_row(array(
				$row["id"],
				$row["priority"],
				$row["pattern"],
				$name,
				$actions),$col++);
		}
		if(DBnum_rows($result)==0)
		{
				echo "<TR BGCOLOR=#EEEEEE>";
				echo "<TD COLSPAN=5 ALIGN=CENTER>".S_NO_AUTOREGISTRATION_RULES_DEFINED."</TD>";
				echo "<TR>";
		}
		table_end();

		@insert_autoregistration_form($_REQUEST["id"]);
	}
?>

<?php
	show_footer();
?>
