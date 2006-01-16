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

	if(isset($_REQUEST["save"])&&isset($_REQUEST["config"])&&(in_array($_REQUEST["config"],array(0,5))))
	{
		$result=update_config($_REQUEST["alarm_history"],$_REQUEST["alert_history"],$_REQUEST["refresh_unsupported"]);
		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Alarm history [".$_REQUEST["alarm_history"]."] alert history [".$_REQUEST["alert_history"]."]");
		}
	}

	if(isset($_REQUEST["delete"])&&($_REQUEST["config"]==1))
	{
		$mediatype=get_mediatype_by_mediatypeid($_REQUEST["mediatypeid"]);
		$result=delete_mediatype($_REQUEST["mediatypeid"]);
		show_messages($result, S_MEDIA_TYPE_DELETED, S_MEDIA_TYPE_WAS_NOT_DELETED);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".$mediatype["description"]."]");
		}
		if($result)
		{
			unset($_REQUEST["form"]);
		}
	}

	if(isset($_REQUEST["save"])&&!isset($_REQUEST["mediatypeid"])&&($_REQUEST["config"]==1))
	{
		if(isset($_REQUEST["mediatypeid"]))
		{
			$action = AUDIT_ACTION_UPDATE;
			$result=update_mediatype($_REQUEST["mediatypeid"],$_REQUEST["type"],$_REQUEST["description"],$_REQUEST["smtp_server"],$_REQUEST["smtp_helo"],$_REQUEST["smtp_email"],$_REQUEST["exec_path"]);
			show_messages($result, S_MEDIA_TYPE_UPDATED, S_MEDIA_TYPE_WAS_NOT_UPDATED);
		}
		else
		{
			$action = AUDIT_ACTION_ADD;
			$result=add_mediatype($_REQUEST["type"],$_REQUEST["description"],$_REQUEST["smtp_server"],$_REQUEST["smtp_helo"],$_REQUEST["smtp_email"],$_REQUEST["exec_path"]);
			show_messages($result, S_ADDED_NEW_MEDIA_TYPE, S_NEW_MEDIA_TYPE_WAS_NOT_ADDED);
		}
		if($result)
		{
			add_audit($action,AUDIT_RESOURCE_MEDIA_TYPE,"Media type [".addslashes($_REQUEST["description"])."]");
			unset($_REQUEST["form"]);
		}
	}

	if(isset($_REQUEST["save"])&&!isset($_REQUEST["imageid"])&&($_REQUEST["config"]==3))
	{
		$result=add_image($_REQUEST["name"],$_REQUEST["imagetype"],$_FILES);
		show_messages($result, S_IMAGE_ADDED, S_CANNOT_ADD_IMAGE);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
		}
		unset($_REQUEST["imageid"]);
	}

	if(isset($_REQUEST["delete"])&&($_REQUEST["config"]==3))
	{
		$result=delete_image($_REQUEST["imageid"]);
		show_messages($result, S_IMAGE_DELETED, S_CANNOT_DELETE_IMAGE);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image deleted");
		}
		unset($_REQUEST["imageid"]);
	}

	if(isset($_REQUEST["save"])&&isset($_REQUEST["imageid"])&&($_REQUEST["config"]==3))
	{
		$result=update_image($_REQUEST["imageid"],$_REQUEST["name"],$_REQUEST["imagetype"],$_FILES);
		show_messages($result, S_IMAGE_UPDATED, S_CANNOT_UPDATE_IMAGE);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,"Image updated");
		}
		unset($_REQUEST["imageid"]);
	}

	if(isset($_REQUEST["delete"])&&($_REQUEST["config"]==4))
	{
		$result=delete_autoregistration($_REQUEST["id"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
		}
		show_messages($result, S_AUTOREGISTRATION_DELETED, S_AUTOREGISTRATION_WAS_NOT_DELETED);
	}

	if(isset($_REQUEST["save"])&&!isset($_REQUEST["id"])&&($_REQUEST["config"]==4))
	{
		$result=add_autoregistration($_REQUEST["pattern"],$_REQUEST["priority"],$_REQUEST["hostid"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_ADD,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
		}
		show_messages($result, S_AUTOREGISTRATION_ADDED, S_CANNOT_ADD_AUTOREGISTRATION);
	}

	if(isset($_REQUEST["save"])&&isset($_REQUEST["id"])&&($_REQUEST["config"]==4))
	{
		$result=update_autoregistration($_REQUEST["id"],$_REQUEST["pattern"],$_REQUEST["priority"],$_REQUEST["hostid"]);
		if($result)
		{
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_AUTOREGISTRATION,"Autoregistration [".addslashes($_REQUEST["pattern"])."]");
		}
		show_messages($result, S_AUTOREGISTRATION_UPDATED, S_AUTOREGISTRATION_WAS_NOT_UPDATED);
	}

	if(isset($_REQUEST["cancel"])){
		unset($_REQUEST["form"]);
	}
?>

<?php

	$h1=S_CONFIGURATION_OF_ZABBIX_BIG;

#	$h2=S_GROUP."&nbsp;";
	$h2="";
	$h2=$h2."<select class=\"biginput\" name=\"config\" onChange=\"submit()\">";
	$h2=$h2.form_select("config",0,S_HOUSEKEEPER);
	$h2=$h2.form_select("config",1,S_MEDIA_TYPES);
//	$h2=$h2.form_select("config",2,S_ESCALATION_RULES);
	$h2=$h2.form_select("config",3,S_IMAGES);
	$h2=$h2.form_select("config",4,S_AUTOREGISTRATION);
	$h2=$h2.form_select("config",5,S_OTHER);
	$h2=$h2."</select>";
	if($_REQUEST["config"] == 1)
	{
		$h2=$h2."&nbsp;|&nbsp;";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_MEDIA_TYPE."\">";
	}
	else if($_REQUEST["config"] == 3)
	{
		$h2=$h2."&nbsp;|&nbsp;";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_IMAGE."\">";
	}
	else if($_REQUEST["config"] == 4)
	{
		$h2=$h2."&nbsp;|&nbsp;";
		$h2=$h2."<input class=\"button\" type=\"submit\" name=\"form\" value=\"".S_CREATE_RULE."\">";
	}

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
		echo "<input class=\"biginput\" name=\"config\" type=\"hidden\" value=\"0\" size=8>";
		echo "<input type=\"hidden\" name=\"refresh_unsupported\" value=\"".$config["refresh_unsupported"]."\">";
		echo nbsp(S_DO_NOT_KEEP_ACTIONS_OLDER_THAN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alert_history\" value=\"".$config["alert_history"]."\" size=8>";

		show_table2_v_delimiter($col++);
		echo nbsp(S_DO_NOT_KEEP_EVENTS_OLDER_THAN);
		show_table2_h_delimiter();
		echo "<input class=\"biginput\" name=\"alarm_history\" value=\"".$config["alarm_history"]."\" size=8>";

		show_table2_v_delimiter2();
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";

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
		echo "<input class=\"button\" type=\"submit\" name=\"save\" value=\"".S_SAVE."\">";

		show_table2_header_end();
	}
?>

<?php
	if($_REQUEST["config"]==3)
	{
		if(!isset($_REQUEST["form"]))
		{
			echo "<br>";
			show_table_header(S_IMAGES_BIG);

			$table=new CTableInfo(S_NO_IMAGES_DEFINED);
			$table->setHeader(array(S_ID,S_TYPE,S_NAME,S_ACTIONS));
	
			$result=DBselect("select imageid,imagetype,name,image from images order by name");
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
				$name="<a href=\"config.php?config=3&form=0&register=change&imageid=".$row["imageid"]."\">".$row["name"]."</a>";
				$actions="<a href=\"image.php?imageid=".$row["imageid"]."\">".S_SHOW."</a>";
				$table->addRow(array(
					$row["imageid"],
					$imagetype,
					$name,
	//				"<img src=\"image.php?imageid=".$row["imageid"]."\">",
					$actions));
			}
			$table->show();
		}
		else
		{
			insert_image_form();
		}
	}
?>

<?php
	if($_REQUEST["config"]==1)
	{
		if(!isset($_REQUEST["form"]))
		{
			echo "<br>";
			show_table_header(S_MEDIA_TYPES_BIG);

			$table=new CTableInfo(S_NO_MEDIA_TYPES_DEFINED);
			$table->setHeader(array(S_ID,S_DESCRIPTION,S_TYPE));

			$result=DBselect("select mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path from media_type mt order by mt.type");
			while($row=DBfetch($result))
			{
				$description="<a href=\"config.php?config=1&form=0&register=change&mediatypeid=".$row["mediatypeid"]."\">".$row["description"]."</a>";
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
				$table->addRow(array(
					$row["mediatypeid"],
					$description,
					$type));
			}
			$table->show();
		}
?>

<?php
		if(isset($_REQUEST["form"]))
		{
			echo "<br>";
			insert_media_type_form();
		}
	}
?>

<?php
// Disabled
	if($_REQUEST["config"]==20)
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
		if(!isset($_REQUEST["form"]))
		{
			echo "<br>";
			show_table_header(S_AUTOREGISTRATION_RULES_BIG);

			$table=new CTableInfo(S_NO_AUTOREGISTRATION_RULES_DEFINED);
			$table->setHeader(array(S_ID,S_PRIORITY,S_PATTERN,S_HOST));

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
				$pattern="<a href=\"config.php?config=4&form=0&register=change&id=".$row["id"]."\">".$row["pattern"]."</a>";
				$table->addRow(array(
					$row["id"],
					$row["priority"],
					$pattern,
					$name));
			}
			$table->show();
		}
		else
		{
			@insert_autoregistration_form($_REQUEST["id"]);
		}
	}
?>

<?php
	show_footer();
?>
