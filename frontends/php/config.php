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
	require_once "include/config.inc.php";
	require_once "include/images.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_CONFIGURATION_OF_ZABBIX";
	$page["file"] = "config.php";

include_once "include/page_header.php";

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

		"config"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("0,3,5,6,7"),	NULL),

// other form
		"alert_history"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),	'({config}==0)&&isset({save})'),
		"event_history"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),	'({config}==0)&&isset({save})'),
		"work_period"=>		array(T_ZBX_STR, O_NO,	NULL,	NULL,			'({config}==7)&&isset({save})'),
		"refresh_unsupported"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),	'({config}==5)&&isset({save})'),
		"alert_usrgrpid"=>	array(T_ZBX_INT, O_NO,	NULL,	DB_ID,			'({config}==5)&&isset({save})'),

// image form
		"imageid"=>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,
						'{config}==3&&{form}=="update"'),
		"name"=>		array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,
						'{config}==3&&isset({save})'),
		"imagetype"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1,2"),
						'({config}==3)&&(isset({save}))'),
//value mapping
		"valuemapid"=>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,	'{config}==6&&{form}=="update"'),
		"mapname"=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY, '{config}==6&&isset({save})'),
		"valuemap"=>		array(T_ZBX_STR, O_OPT, NULL,	NULL, 	NULL),
		"rem_value"=>		array(T_ZBX_INT, O_OPT, NULL,	BETWEEN(0,65535), NULL),
		"add_value"=>		array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({add_map})'),
		"add_newvalue"=>	array(T_ZBX_STR, O_OPT, NULL,	NOT_EMPTY, 'isset({add_map})'),

/* actions */
		"add_map"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"del_map"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	NULL,	NULL),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	NULL,	NULL,	NULL)
	);
?>

<?php
	$_REQUEST["config"] = get_request("config",get_profile("web.config.config",0));

	check_fields($fields);

	update_profile("web.config.config",$_REQUEST["config"]);

	$result = 0;
	if($_REQUEST["config"]==3)
	{
/* IMAGES ACTIONS */
		if(isset($_REQUEST["save"]))
		{
			$file = isset($_FILES["image"]) && $_FILES["image"]["name"] != "" ? $_FILES["image"] : NULL;
			if(isset($_REQUEST["imageid"]))
			{
	/* UPDATE */
				$result=update_image($_REQUEST["imageid"],$_REQUEST["name"],
					$_REQUEST["imagetype"],$file);

				$msg_ok = S_IMAGE_UPDATED;
				$msg_fail = S_CANNOT_UPDATE_IMAGE;
				$audit_action = "Image [".$_REQUEST["name"]."] updated";
			} else {
	/* ADD */
				if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,
						PERM_RES_IDS_ARRAY,get_current_nodeid())))
				{
					access_deny();
				}
				$result=add_image($_REQUEST["name"],$_REQUEST["imagetype"],$file);

				$msg_ok = S_IMAGE_ADDED;
				$msg_fail = S_CANNOT_ADD_IMAGE;
				$audit_action = "Image [".$_REQUEST["name"]."] added";
			}
			show_messages($result, $msg_ok, $msg_fail);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_IMAGE,$audit_action);
				unset($_REQUEST["form"]);
			}
		} elseif(isset($_REQUEST["delete"])&&isset($_REQUEST["imageid"])) {
	/* DELETE */
			$image = get_image_by_imageid($_REQUEST["imageid"]);
			
			$result=delete_image($_REQUEST["imageid"]);
			show_messages($result, S_IMAGE_DELETED, S_CANNOT_DELETE_IMAGE);
			if($result)
			{
				add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_IMAGE,"Image [".$image['name']."] deleted");
				unset($_REQUEST["form"]);
			}
			unset($image, $_REQUEST["imageid"]);
		}
	}
	elseif(isset($_REQUEST["save"])&&in_array($_REQUEST["config"],array(0,5,7)))
	{

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();

/* OTHER ACTIONS */
		$result=update_config(
			get_request('event_history'),
			get_request('alert_history'),
			get_request('refresh_unsupported'),
			get_request('work_period'),
			get_request('alert_usrgrpid'));

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);
		if($result)
		{
			$msg = array();
			if(!is_null($val = get_request('event_history')))
				$msg[] = S_DO_NOT_KEEP_EVENTS_OLDER_THAN.' ['.$val.']';
			if(!is_null($val = get_request('alert_history')))
				$msg[] = S_DO_NOT_KEEP_ACTIONS_OLDER_THAN.' ['.$val.']';
			if(!is_null($val = get_request('refresh_unsupported')))
				$msg[] = S_REFRESH_UNSUPPORTED_ITEMS.' ['.$val.']';
			if(!is_null($val = get_request('work_period')))
				$msg[] = S_WORKING_TIME.' ['.$val.']';
			if(!is_null($val = get_request('alert_usrgrpid')))
			{
				if(0 == $val) 
				{
					$val = S_NONE;
				}
				else
				{
					$val = DBfetch(DBselect('select name from usrgrp where usrgrpid='.$val));
					$val = $val['name'];
				}

				$msg[] = S_USER_GROUP_FOR_DATABASE_DOWN_MESSAGE.' ['.$val.']';
			}

			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,implode('; ',$msg));
		}
	}
	elseif($_REQUEST["config"]==6)
	{
		$_REQUEST["valuemap"] = get_request("valuemap",array());
		if(isset($_REQUEST["add_map"]))
		{
			$added = 0;
			$cnt = count($_REQUEST["valuemap"]);
			for($i=0; $i < $cnt; $i++)
			{
				if($_REQUEST["valuemap"][$i]["value"] != $_REQUEST["add_value"])	continue;
				$_REQUEST["valuemap"][$i]["newvalue"] = $_REQUEST["add_newvalue"];
				$added = 1;
				break;
			}
			if($added == 0)
			{
				array_push($_REQUEST["valuemap"],array(
					"value"		=> $_REQUEST["add_value"],
					"newvalue"	=> $_REQUEST["add_newvalue"]));
			}
		}
		elseif(isset($_REQUEST["del_map"])&&isset($_REQUEST["rem_value"]))
		{
			$_REQUEST["valuemap"] = get_request("valuemap",array());
			foreach($_REQUEST["rem_value"] as $val)
				unset($_REQUEST["valuemap"][$val]);
		}
		elseif(isset($_REQUEST["save"]))
		{
			$mapping = get_request("valuemap",array());
			if(isset($_REQUEST["valuemapid"]))
			{
				$result = update_valuemap($_REQUEST["valuemapid"],$_REQUEST["mapname"], $mapping);
				$audit_action	= AUDIT_ACTION_UPDATE;
				$msg_ok		= S_VALUE_MAP_UPDATED;
				$msg_fail	= S_CANNNOT_UPDATE_VALUE_MAP;
				$valuemapid	= $_REQUEST["valuemapid"];
			}
			else
			{
				if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,
					PERM_RES_IDS_ARRAY,get_current_nodeid())))
				{
					access_deny();
				}
				$result = add_valuemap($_REQUEST["mapname"], $mapping);
				$audit_action	= AUDIT_ACTION_ADD;
				$msg_ok		= S_VALUE_MAP_ADDED;
				$msg_fail	= S_CANNNOT_ADD_VALUE_MAP;
				$valuemapid	= $result;
			}
			if($result)
			{
				add_audit($audit_action, AUDIT_RESOURCE_VALUE_MAP,
					S_VALUE_MAP." [".$_REQUEST["mapname"]."] [".$valuemapid."]");
				unset($_REQUEST["form"]);
			}
			show_messages($result,$msg_ok, $msg_fail);
		}
		elseif(isset($_REQUEST["delete"]) && isset($_REQUEST["valuemapid"]))
		{
			$result = false;

			if(($map_data = DBfetch(DBselect('select * from valuemaps where '.DBin_node('valuemapid').
				" and valuemapid=".$_REQUEST["valuemapid"]))))
			{
				$result = delete_valuemap($_REQUEST["valuemapid"]);
			}
			if($result)
			{
				add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_VALUE_MAP,
					S_VALUE_MAP." [".$map_data["name"]."] [".$map_data['valuemapid']."]");
				unset($_REQUEST["form"]);
			}
			show_messages($result, S_VALUE_MAP_DELETED, S_CANNNOT_DELETE_VALUE_MAP);
		}
	}

?>

<?php

	$form = new CForm("config.php");
	$cmbConfig = new CCombobox("config",$_REQUEST["config"],"submit()");
	$cmbConfig->AddItem(0,S_HOUSEKEEPER);
//	$cmbConfig->AddItem(2,S_ESCALATION_RULES);
	$cmbConfig->AddItem(3,S_IMAGES);
//	$cmbConfig->AddItem(4,S_AUTOREGISTRATION);
	$cmbConfig->AddItem(6,S_VALUE_MAPPING);
	$cmbConfig->AddItem(7,S_WORKING_TIME);
	$cmbConfig->AddItem(5,S_OTHER);
	$form->AddItem($cmbConfig);
	switch($_REQUEST["config"])
	{
	case 3:
		$form->AddItem(SPACE."|".SPACE);
		$form->AddItem(new CButton("form",S_CREATE_IMAGE));
		break;
	case 6:
		$form->AddItem(SPACE."|".SPACE);
		$form->AddItem(new CButton("form",S_CREATE_VALUE_MAP));
		break;
	}
	show_table_header(S_CONFIGURATION_OF_ZABBIX_BIG, $form);
	echo BR;
?>

<?php
	if($_REQUEST["config"]==0)
	{
		insert_housekeeper_form();
	}
	elseif($_REQUEST["config"]==5)
	{
		insert_other_parameters_form();
	}
	elseif($_REQUEST["config"]==7)
	{
		insert_work_period_form();
	}
	elseif($_REQUEST["config"]==3)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_image_form();
		}
		else
		{
			show_table_header(S_IMAGES_BIG);

			$table=new CTableInfo(S_NO_IMAGES_DEFINED);
			$table->setHeader(array(S_NAME,S_TYPE,S_IMAGE));
	
			$result=DBselect('select imageid,imagetype,name from images'.
					' where '.DBin_node('imageid').
					' order by name');
			while($row=DBfetch($result))
			{
				if($row["imagetype"]==1)	$imagetype=S_ICON;
				else if($row["imagetype"]==2)	$imagetype=S_BACKGROUND;
				else				$imagetype=S_UNKNOWN;

				$name=new CLink($row["name"],"config.php?form=update".url_param("config").
					"&imageid=".$row["imageid"],'action');

				$table->addRow(array(
					$name,
					$imagetype,
					$actions=new CLink(
						new CImg("image.php?height=24&imageid=".$row["imageid"],"no image",NULL),
						"image.php?imageid=".$row["imageid"])
					));
			}
			$table->show();
		}
	}
	elseif($_REQUEST["config"]==6)
	{
		if(isset($_REQUEST["form"]))
		{
			insert_value_mapping_form();
		}
		else
		{
			show_table_header(S_VALUE_MAPPING_BIG);
			$table = new CTableInfo();
			$table->SetHeader(array(S_NAME, S_VALUE_MAP));

			$db_valuemaps = DBselect('select * from valuemaps where '.DBin_node('valuemapid'));
			while($db_valuemap = DBfetch($db_valuemaps))
			{
				$mappings_row = array();
				$db_maps = DBselect("select * from mappings".
					" where valuemapid=".$db_valuemap["valuemapid"]);
				while($db_map = DBfetch($db_maps))
				{
					array_push($mappings_row, 
						$db_map["value"],
						SPACE.RARR.SPACE,
						$db_map["newvalue"],
						BR);
				}
				$table->AddRow(array(
					new CLink($db_valuemap["name"],"config.php?form=update&".
						"valuemapid=".$db_valuemap["valuemapid"].url_param("config"),
						"action"),
					$mappings_row));
			}
			
			$table->Show();
		}
	}
?>
<?php

include_once "include/page_footer.php";

?>
