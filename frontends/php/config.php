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
	require_once "include/regexp.inc.php";
	require_once "include/forms.inc.php";
	

	$page["title"] = "S_CONFIGURATION_OF_ZABBIX";
	$page["file"] = "config.php";

include_once "include/page_header.php";

?>
<?php
	$fields=array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

		"config"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("0,3,5,6,7,8,9"),	NULL),

// other form
		"alert_history"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),		'isset({config})&&({config}==0)&&isset({save})'),
		"event_history"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),		'isset({config})&&({config}==0)&&isset({save})'),
		"work_period"=>		array(T_ZBX_STR, O_NO,	NULL,	NULL,					'isset({config})&&({config}==7)&&isset({save})'),
		"refresh_unsupported"=>	array(T_ZBX_INT, O_NO,	NULL,	BETWEEN(0,65535),	'isset({config})&&({config}==5)&&isset({save})'),
		"alert_usrgrpid"=>	array(T_ZBX_INT, O_NO,	NULL,	DB_ID,					'isset({config})&&({config}==5)&&isset({save})'),

// image form
		"imageid"=>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,						'isset({config})&&({config}==3)&&(isset({form})&&({form}=="update"))'),
		"name"=>		array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,					'isset({config})&&({config}==3)&&isset({save})'),
		"imagetype"=>		array(T_ZBX_INT, O_OPT,	NULL,	IN("1,2"),				'isset({config})&&({config}==3)&&(isset({save}))'),
//value mapping
		"valuemapid"=>		array(T_ZBX_INT, O_NO,	P_SYS,	DB_ID,					'isset({config})&&({config}==6)&&(isset({form})&&({form}=="update"))'),
		"mapname"=>		array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY, 					'isset({config})&&({config}==6)&&isset({save})'),
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
/* acknowledges */
		'event_ack_enable'=>	array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	IN("0,1"),	'isset({config})&&({config}==8)&&isset({save})'),
		'event_expire'=> 		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,65535),	'isset({config})&&({config}==8)&&isset({save})'),
		'event_show_max'=> 		array(T_ZBX_INT, O_OPT, P_SYS|P_ACT,	BETWEEN(1,65535),	'isset({config})&&({config}==8)&&isset({save})'),
		
// regexp
		'regexpids'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID, 		NULL),
		'regexpid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		'isset({config})&&({config}==9)&&(isset({form})&&({form}=="update"))'),
		'rename'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'isset({config})&&({config}==9)&&isset({save})'),
		'test_string'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,	'isset({config})&&({config}==9)&&isset({save})'),
		'delete_regexp'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),
		
		'g_expressionid'=>			array(T_ZBX_INT, O_OPT,	NULL,	DB_ID,		null),
		'expressions'=>				array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({config})&&({config}==9)&&isset({save})'),
		'new_expression'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),
		'cancel_new_expression'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),

		'add_expression'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),
		'edit_expressionid'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),
		'delete_expression'=>	array(T_ZBX_STR, O_OPT,	NULL,	NULL,		null),
		
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
	elseif(isset($_REQUEST["save"]) && ($_REQUEST["config"]==8)){
		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();

/* OTHER ACTIONS */
		$result=update_config(
			get_request('event_history'),
			get_request('alert_history'),
			get_request('refresh_unsupported'),
			get_request('work_period'),
			get_request('alert_usrgrpid'),
			get_request('event_ack_enable'),
			get_request('event_expire'),
			get_request('event_show_max')
			);

		show_messages($result, S_CONFIGURATION_UPDATED, S_CONFIGURATION_WAS_NOT_UPDATED);

		if($result)
		{
			$msg = array();
			if(!is_null($val = get_request('event_ack_enable')))
				$msg[] = S_EVENT_ACKNOWLEDGES.' ['.($val?(S_DISABLED):(S_ENABLED)).']';
			if(!is_null($val = get_request('event_expire')))
				$msg[] = S_SHOW_EVENTS_NOT_OLDER.SPACE.'('.S_DAYS.')'.' ['.$val.']';
			if(!is_null($val = get_request('event_show_max')))
				$msg[] = S_SHOW_EVENTS_MAX.' ['.$val.']';

			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG,implode('; ',$msg));
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
			get_request('alert_usrgrpid'),
			get_request('event_ack_enable'),
			get_request('event_expire'),
			get_request('event_show_max')
			);

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
	else if($_REQUEST['config'] == 9){
		if(inarr_isset(array('clone','regexpid'))){
			unset($_REQUEST['regexpid']);
			$_REQUEST['form'] = 'clone';
		}
		else if(isset($_REQUEST['cancel_new_expression'])){
			unset($_REQUEST['new_expression']);
		}
		else if(isset($_REQUEST['save'])){
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
				access_deny();	

			$regexp = array('name' => $_REQUEST['rename'],
						'test_string' => $_REQUEST['test_string']
					);			
			
			if(isset($_REQUEST['regexpid'])){
				$regexpid=$_REQUEST['regexpid'];
				
				delete_expressions_by_regexpid($_REQUEST['regexpid']);					
				$result = update_regexp($regexpid, $regexp);

				$msg1 = S_REGULAR_EXPRESSION_UPDATED;
				$msg2 = S_CANNOT_UPDATE_REGULAR_EXPRESSION;
			} 
			else {
				$result = $regexpid = add_regexp($regexp);

				$msg1 = S_REGULAR_EXPRESSION_ADDED;
				$msg2 = S_CANNOT_ADD_REGULAR_EXPRESSION;
			}
			
			$expressions = get_request('expressions', array());
			foreach($expressions as $id => $expression){
				$expressionid = add_expression($regexpid,$expression);
			}
			
			show_messages($result,$msg1,$msg2);
				
			if($result){ // result - OK
				add_audit(!isset($_REQUEST['regexpid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE, 
					AUDIT_RESOURCE_REGEXP, 
					S_NAME.': '.$_REQUEST['rename']);
	
				unset($_REQUEST['form']);
			}
		}
		else if(isset($_REQUEST['delete'])){
			if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY))) access_deny();

			$regexpids = get_request('regexpid', array());
			if(isset($_REQUEST['regexpids']))
				$regexpids = $_REQUEST['regexpids'];
			
			zbx_value2array($regexpids);

			$regexps = array();
			foreach($regexpids as $id => $regexpid){
				$regexps[$regexpid] = get_regexp_by_regexpid($regexpid);
			}
			
			$result = delete_regexp($regexpids);
			
			show_messages($result,S_REGULAR_EXPRESSION_DELETED,S_CANNOT_DELETE_REGULAR_EXPRESSION);
			if($result){
				foreach($regexps as $regexpid => $regexp){
					add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_REGEXP,'Id ['.$regexpid.'] '.S_NAME.' ['.$regexp['name'].']');
				}
				
				unset($_REQUEST['form']);
				unset($_REQUEST['regexpid']);
			}
		}
		else if(inarr_isset(array('add_expression','new_expression'))){
			$new_expression = $_REQUEST['new_expression'];
			
			if(!isset($new_expression['case_sensitive']))		$new_expression['case_sensitive'] = 0;
	
			$result = false;
			if(zbx_empty($new_expression['expression'])) {
				info(S_INCORRECT_EXPRESSION);
			}
			else{
				$result = true;
			}

			if($result){
				if(!isset($new_expression['id'])){
					if(!isset($_REQUEST['expressions'])) $_REQUEST['expressions'] = array();
					
					if(!str_in_array($new_expression,$_REQUEST['expressions']))
						array_push($_REQUEST['expressions'],$new_expression);
				}
				else{
					$id = $new_expression['id'];
					unset($new_expression['id']);
					$_REQUEST['expressions'][$id] = $new_expression;
				}
	
				unset($_REQUEST['new_expression']);
			}
		}
		else if(inarr_isset(array('del_expression','g_expressionid'))){
			$_REQUEST['expressions'] = get_request('expressions',array());
			foreach($_REQUEST['g_expressionid'] as $val){
				unset($_REQUEST['expressions'][$val]);
			}
		}
		else if(inarr_isset(array('edit_expressionid'))){	
			$_REQUEST['edit_expressionid'] = array_keys($_REQUEST['edit_expressionid']);
			$edit_expressionid = $_REQUEST['edit_expressionid'] = array_pop($_REQUEST['edit_expressionid']);
			$_REQUEST['expressions'] = get_request('expressions',array());

			if(isset($_REQUEST['expressions'][$edit_expressionid])){
				$_REQUEST['new_expression'] = $_REQUEST['expressions'][$edit_expressionid];
				$_REQUEST['new_expression']['id'] = $edit_expressionid;
			}
		}
	}

?>

<?php

	$form = new CForm("config.php");
	$form->SetMethod('get');
	$cmbConfig = new CCombobox("config",$_REQUEST["config"],"submit()");
	$cmbConfig->AddItem(8,S_EVENTS);
	$cmbConfig->AddItem(0,S_HOUSEKEEPER);
//	$cmbConfig->AddItem(2,S_ESCALATION_RULES);
	$cmbConfig->AddItem(3,S_IMAGES);
	$cmbConfig->AddItem(9,S_REGULAR_EXPRESSIONS);
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
?>

<?php
	if($_REQUEST["config"]==0)
	{
		echo BR;
		insert_housekeeper_form();
	}
	elseif($_REQUEST["config"]==5)
	{
		echo BR;
		insert_other_parameters_form();
	}
	elseif($_REQUEST["config"]==7)
	{
		echo BR;
		insert_work_period_form();
	}
	elseif($_REQUEST["config"]==8)
	{
		echo BR;
		insert_event_ack_form();
	}
	elseif($_REQUEST["config"]==3)
	{
		echo BR;
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
		echo BR;
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
	else if($_REQUEST['config'] == 9){
		if(isset($_REQUEST["form"])){

			$frmRegExp = new CForm('config.php','post');
			$frmRegExp->setName(S_REGULAR_EXPRESSION);
			
			$frmRegExp->addVar('form',get_request('form',1));
			
			$from_rfr = get_request('form_refresh',0);
			$frmRegExp->addVar('form_refresh',$from_rfr+1);
			
			$frmRegExp->addVar('config',get_request('config',9));
			
			if(isset($_REQUEST['regexpid']))
				$frmRegExp->addVar('regexpid',$_REQUEST['regexpid']);
						
			$left_tab = new CTable();
			$left_tab->setCellPadding(3);
			$left_tab->setCellSpacing(3);
			
			$left_tab->addOption('border',0);
			
			$left_tab->addRow(create_hat(
					S_REGULAR_EXPRESSION,
					get_regexp_form(),//null,
					null,
					'hat_regexp',
					get_profile('web.config.hats.hat_regexp.state',1)
				));
			
			$right_tab = new CTable();
			$right_tab->setCellPadding(3);
			$right_tab->setCellSpacing(3);
			
			$right_tab->addOption('border',0);
					
			$right_tab->addRow(create_hat(
					S_EXPRESSIONS,
					get_expressions_tab(),//null,
					null,
					'hat_expressions',
					get_profile('web.config.hats.hat_expressions.state',1)
				));

			if(isset($_REQUEST['new_expression'])){		
				$right_tab->addRow(create_hat(
						S_NEW_EXPRESSION,
						get_expression_form(),//null
						null,
						'hat_new_expression',
						get_profile('web.config.hats.hat_new_expression.state',1)
					));
			}
			
			
			$td_l = new CCol($left_tab);
			$td_l->AddOption('valign','top');
			
			$td_r = new CCol($right_tab);
			$td_r->AddOption('valign','top');
			
			$outer_table = new CTable();
			$outer_table->AddOption('border',0);
			$outer_table->SetCellPadding(1);
			$outer_table->SetCellSpacing(1);
			$outer_table->AddRow(array($td_l,$td_r));
			
			$frmRegExp->Additem($outer_table);
			
			show_messages();
			$frmRegExp->Show();
		}
		else{
			echo BR;
			$form = new CForm();
			$form->addVar('config', $_REQUEST['config']);
			$form->addItem(new CButton('form',S_NEW_REGULAR_EXPRESSION));
			
			show_table_header(S_REGULAR_EXPRESSIONS,$form);
// ----
			$regexps = array();
			$regexpids = array();
			
			$sql = 'SELECT re.* '.
					' FROM regexps re '.
					' WHERE '.DBin_node('re.regexpid').
					' ORDER BY re.name';

			$db_regexps = DBselect($sql);
			while($regexp = DBfetch($db_regexps)){
				$regexp['expressions'] = array();
				
				$regexps[$regexp['regexpid']] = $regexp;
				$regexpids[$regexp['regexpid']] = $regexp['regexpid'];
			}
			
			$count = array();
			$expressions = array();
			$sql = 'SELECT e.* '.
					' FROM expressions e '.
					' WHERE '.DBin_node('e.expressionid').
						' AND '.DBcondition('e.regexpid',$regexpids).
					' ORDER BY e.expression_type';

			$db_exps = DBselect($sql);
			while($exp = DBfetch($db_exps)){
				if(!isset($expressions[$exp['regexpid']])) $count[$exp['regexpid']] = 1;
				else $count[$exp['regexpid']]++;
								
				if(!isset($expressions[$exp['regexpid']])) $expressions[$exp['regexpid']] = new CTable();

				$expressions[$exp['regexpid']]->addRow(array($count[$exp['regexpid']], ' &raquo; ', $exp['expression'],' ['.expression_type2str($exp['expression_type']).']'));
				
				$regexp[$exp['regexpid']]['expressions'][$exp['expressionid']] = $exp;
			}
		
			$form = new CForm(null,'post');
			$form->setName('regexp');
			
			$table = new CTableInfo();
			$table->setHeader(array(
				array(
					new CCheckBox('all_regexps',NULL,"CheckAll('".$form->GetName()."','all_regexps','group_regexpid');"),
					S_NAME
				),
				S_EXPRESSIONS
				));
				
			foreach($regexps as $regexpid => $regexp){
				
				$table->addRow(array(
					array(
						new CCheckBox('regexpids['.$regexp['regexpid'].']',NULL,NULL,$regexp['regexpid']),
						new CLink($regexp['name'],
							'config.php?form=update'.url_param('config').
							'&regexpid='.$regexp['regexpid'].'#form', 'action')
					),
					isset($expressions[$regexpid])?$expressions[$regexpid]:'-'
					));
			}
//			$table->SetFooter(new CCol(new CButtonQMessage('delete_selected',S_DELETE_SELECTED,S_DELETE_SELECTED_USERS_Q)));
			
			$table->SetFooter(new CCol(array(
				new CButtonQMessage('delete',S_DELETE_SELECTED,S_DELETE_SELECTED_REGULAR_EXPRESSIONS_Q)
			)));

			$form->AddItem($table);

			$form->show();
		}
	}
?>
<?php

include_once "include/page_footer.php";

?>
