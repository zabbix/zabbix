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
	if(isset($_POST["config"]))	$_GET["config"]=$_POST["config"];

	$_GET["config"]=@iif(isset($_GET["config"]),$_GET["config"],get_profile("web.config.config",0));
	update_profile("web.config.config",$_GET["config"]);

# For images
	if(isset($_POST["register"]))
	{
		if($_POST["register"]=="add image")
		{
			$result=add_image($_POST["name"],$_POST["imagetype"],$_FILES);
			show_messages($result, S_IMAGE_ADDED, S_CANNOT_ADD_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
			}
			unset($_POST["imageid"]);
		}
		if($_POST["register"]=="delete image")
		{
			$result=delete_image($_POST["imageid"]);
			show_messages($result, S_IMAGE_DELETED, S_CANNOT_DELETE_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
			}
			unset($_POST["imageid"]);
		}
	}
	if(isset($_GET["register"]))
	{
		if($_GET["register"]=="update")
		{
			$result=update_config($_GET["alarm_history"],$_GET["alert_history"]);
			show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Alarm history [".$_GET["alarm_history"]."] alert history [".$_GET["alert_history"]."]");
			}
		}
		if($_GET["register"]=="add rule")
		{
			$result=add_escalation_rule($_GET["escalationid"],$_GET["level"],$_GET["period"],$_GET["delay"],$_GET["actiontype"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation ID [".addslashes($_GET["escalationid"])."]");
			}
			show_messages($result, S_ESCALATION_RULE_ADDED, S_ESCALATION_RULE_WAS_NOT_ADDED);
		}
		if($_GET["register"]=="update rule")
		{
			$result=update_escalation_rule($_GET["escalationruleid"],$_GET["level"],$_GET["period"],$_GET["delay"],$_GET["actiontype"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation rule ID [".addslashes($_GET["escalationruleid"])."]");
			}
			show_messages($result, S_ESCALATION_RULE_UPDATED, S_ESCALATION_RULE_WAS_NOT_UPDATED);
		}
		if($_GET["register"]=="delete rule")
		{
			$result=delete_escalation_rule($_GET["escalationruleid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ESCALATION_RULE,"Escalation rule ID [".addslashes($_GET["escalationruleid"])."]");
			}
			unset($_GET["escalationruleid"]);
			show_messages($result, S_ESCALATION_RULE_DELETED, S_ESCALATION_RULE_WAS_NOT_DELETED);
		}
		if($_GET["register"]=="add escalation")
		{
			$dflt=iif(isset($_GET["dflt"])&&($_GET["dflt"]=="on"),1,0);
			$result=add_escalation($_GET["name"],$dflt);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_ESCALATION,"Escalation [".addslashes($_GET["name"])."]");
			}
			show_messages($result, S_ESCALATION_ADDED, S_ESCALATION_WAS_NOT_ADDED);
		}
		if($_GET["register"]=="update escalation")
		{
			$dflt=iif(isset($_GET["dflt"])&&($_GET["dflt"]=="on"),1,0);
			$result=update_escalation($_GET["escalationid"],$_GET["name"],$dflt);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ESCALATION,"Escalation [".addslashes($_GET["name"])."]");
			}
			show_messages($result, S_ESCALATION_UPDATED, S_ESCALATION_WAS_NOT_UPDATED);
		}
		if($_GET["register"]=="delete escalation")
		{
			$result=delete_escalation($_GET["escalationid"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ESCALATION,"Escalation ID [".addslashes($_GET["$escalationid"])."]");
			}
			unset($_GET["escalationid"]);
			show_messages($result, S_ESCALATION_DELETED, S_ESCALATION_WAS_NOT_DELETED);
		}
		if($_GET["register"]=="add")
		{
			$result=add_mediatype($_GET["type"],$_GET["description"],$_GET["smtp_server"],$_GET["smtp_helo"],$_GET["smtp_email"],$_GET["exec_path"]);
			show_messages($result, S_ADDED_NEW_MEDIA_TYPE, S_NEW_MEDIA_TYPE_WAS_NOT_ADDED);
			if($result)
			{
				add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".addslashes($_GET["description"])."]");
			}
		}
		if($_GET["register"]=="update media")
		{
			$result=update_mediatype($_GET["mediatypeid"],$_GET["type"],$_GET["description"],$_GET["smtp_server"],$_GET["smtp_helo"],$_GET["smtp_email"],$_GET["exec_path"]);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".addslashes($_GET["description"])."]");
			}
			show_messages($result, S_MEDIA_TYPE_UPDATED, S_MEDIA_TYPE_WAS_NOT_UPDATED);
		}
		if($_GET["register"]=="delete")
		{
			$mediatype=get_mediatype_by_mediatypeid($_GET["mediatypeid"]);
			$result=delete_mediatype($_GET["mediatypeid"]);
			show_messages($result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".$mediatype["description"]."]");
			}
			unset($_GET["mediatypeid"]);
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
	$h2=$h2.form_select("config",4,S_AUTODISCOVERY);
	$h2=$h2."</select>";

	show_header2($h1, $h2, "<form name=\"selection\" method=\"get\" action=\"config.php\">", "</form>");


#	show_table_header(S_CONFIGURATION_OF_ZABBIX_BIG);
?>

<?php
	if($_GET["config"]==0)
	{
		$config=select_config();

		$col=0;
		show_form_begin("config.housekeeper");
		echo S_HOUSEKEEPER;

		show_table2_v_delimiter($col++);
		echo "<form method=\"get\" action=\"config.php\">";
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
	if($_GET["config"]==3)
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

		if(!isset($_GET["imageid"]))
		{
			$name="";
			$imagetype=1;
		}
		else
		{
			$result=DBselect("select imageid,imagetype,name,image from images where imageid=".$_GET["imageid"]);
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
		if(isset($_GET["imageid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete image\" onClick=\"return Confirm('".S_DELETE_SELECTED_IMAGE."');\">";
		}

		show_table2_header_end();
	}
?>

<?php
	if($_GET["config"]==1)
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
		$type=@iif(isset($_GET["type"]),$_GET["type"],0);
		$description=@iif(isset($_GET["description"]),$_GET["description"],"");
		$smtp_server=@iif(isset($_GET["smtp_server"]),$_GET["smtp_server"],"localhost");
		$smtp_helo=@iif(isset($_GET["smtp_helo"]),$_GET["smtp_helo"],"localhost");
		$smtp_email=@iif(isset($_GET["smtp_email"]),$_GET["smtp_email"],"zabbix@localhost");
		$exec_path=@iif(isset($_GET["exec_path"]),$_GET["exec_path"],"");

		if(isset($_GET["register"]) && ($_GET["register"] == "change"))
		{
			$result=DBselect("select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path from media_type where mediatypeid=".$_GET["mediatypeid"]);
			$mediatypeid=DBget_field($result,0,0);
			$type=@iif(isset($_GET["type"]),$_GET["type"],DBget_field($result,0,1));
			$description=DBget_field($result,0,2);
			$smtp_server=DBget_field($result,0,3);
			$smtp_helo=DBget_field($result,0,4);
			$smtp_email=DBget_field($result,0,5);
			$exec_path=DBget_field($result,0,6);
		}

?>

<?php
		show_form_begin("config.medias");
		echo S_MEDIA;

		$col=0;

		show_table2_v_delimiter($col++);
		echo "<form name=\"selForm\" method=\"get\" action=\"config.php\">";
		if(isset($_GET["mediatypeid"]))
		{
			echo "<input class=\"biginput\" name=\"mediatypeid\" type=\"hidden\" value=\"".$_GET["mediatypeid"]."\" size=8>";
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

		if(isset($_GET["mediatypeid"]))
		{
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"update media\">";
			echo "<input class=\"button\" type=\"submit\" name=\"register\" value=\"delete\" onClick=\"return Confirm('".S_DELETE_SELECTED_MEDIA."');\">";
		}

		show_table2_header_end();
	}
?>

<?php
	if($_GET["config"]==2)
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

		insert_escalation_form($_GET["escalationid"]);

		if(isset($_GET["escalationid"]))
		{
			echo "<br>";
			show_table_header(S_ESCALATION_RULES);

			table_begin();
			table_header(array(S_LEVEL,S_PERIOD,S_DELAY_BEFORE_ACTION,S_DO,S_ACTIONS));

			$result=DBselect("select * from escalation_rules order by level");
			$col=0;
			while($row=DBfetch($result))
			{
				$actions="<a href=\"config.php?config=2&register=change&escalationid=".$_GET["escalationid"]."&escalationruleid=".$row["escalationruleid"]."\">".S_CHANGE."</a>";
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

			insert_escalation_rule_form($_GET["escalationid"],$_GET["escalationruleid"]);
		}
	}
?>

<?php
	show_footer();
?>
