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
	require_once "include/actions.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/events.inc.php";
	require_once "include/forms.inc.php";

	$page["title"]	= "S_CONFIGURATION_OF_ACTIONS";
	$page["file"]	= "actionconf.php";

include_once "include/page_header.php";
	
	$_REQUEST['eventsource'] = get_request('eventsource',get_profile('web.actionconf.eventsource',EVENT_SOURCE_TRIGGERS));
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(

		"actionid"=>	array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,					null),
		"name"=>	array(T_ZBX_STR, O_OPT,	 null,	NOT_EMPTY,				'isset({save})'),
		"eventsource"=>	array(T_ZBX_INT, O_MAND, null,
			IN(array(EVENT_SOURCE_TRIGGERS,EVENT_SOURCE_DISCOVERY)),	null),
		"evaltype"=>	array(T_ZBX_INT, O_OPT,	 null,
			IN(array(ACTION_EVAL_TYPE_AND_OR,ACTION_EVAL_TYPE_AND,ACTION_EVAL_TYPE_OR)), 	'isset({save})'),
		"status"=>	array(T_ZBX_INT, O_OPT,	 null,
			IN(array(ACTION_STATUS_ENABLED,ACTION_STATUS_DISABLED)),			'isset({save})'),

		"g_actionid"=>	array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),

		"conditions"=>	array(null, O_OPT, null, null, null),
		"g_conditionid"=> array(null, O_OPT, null, null, null),

		"new_condition"=>		array(null, 	 O_OPT,  null,	null,	'isset({add_condition})'),

		"operations"=>		array(null, O_OPT, null, null, null),
		"g_operationid"=>	array(null, O_OPT, null, null, null),

		"edit_operationid"=>	array(null, O_OPT, P_ACT,	DB_ID,	null),

		"new_operation"=>	array(null, O_OPT,  null,	null,	'isset({add_operation})'),


/* actions */
		"group_delete"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_enable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"group_disable"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"add_condition"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_condition"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel_new_condition"=>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"add_operation"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"del_operation"=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel_new_operation"=>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"save"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"clone"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"delete"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		"form"=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		"form_refresh"=>	array(T_ZBX_INT, O_OPT,	null,	null,	null)
	);

	check_fields($fields);
	
	if(isset($_REQUEST['actionid']) && !action_accessiable($_REQUEST['actionid'], PERM_READ_WRITE))
	{
		access_deny();
	}
?>
<?php
	update_profile('web.actionconf.eventsource',$_REQUEST['eventsource']);
?>
<?php
	if(inarr_isset(array('clone','actionid')))
	{
		unset($_REQUEST['actionid']);
		$_REQUEST['form'] = 'clone';
	}
	elseif(isset($_REQUEST['cancel_new_condition']))
	{
		unset($_REQUEST['new_condition']);
	}
	elseif(isset($_REQUEST['cancel_new_operation']))
	{
		unset($_REQUEST['new_operation']);
	}
	elseif(isset($_REQUEST['save']))
	{
		global $USER_DETAILS, $ZBX_CURNODEID;

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			access_deny();

		$conditions = get_request('conditions', array());
		$operations = get_request('operations', array());

		if(isset($_REQUEST['actionid']))
		{
			$actionid=$_REQUEST['actionid'];
			$result = update_action($actionid,
				$_REQUEST['name'],$_REQUEST['eventsource'],
				$_REQUEST['evaltype'],$_REQUEST['status'],
				$conditions, $operations
				);

			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
		} else {
			$actionid=add_action(
				$_REQUEST['name'],$_REQUEST['eventsource'],
				$_REQUEST['evaltype'],$_REQUEST['status'],
				$conditions, $operations
				);
			$result=$actionid;

			show_messages($result,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
		}

		if($result) // result - OK
		{
			add_audit(!isset($_REQUEST['actionid']) ? AUDIT_ACTION_ADD : AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ACTION, 
				S_NAME.': '.$_REQUEST['name']);

			unset($_REQUEST['form']);
		}
	}
	elseif(inarr_isset(array('delete','actionid')))
	{
		global $USER_DETAILS, $ZBX_CURNODEID;

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			access_deny();

		$action_data = DBfetch(DBselect('select name from actions where actionid='.$_REQUEST['actionid']));

		$result = delete_action($_REQUEST['actionid']);
		show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
		if($result)
		{
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ACTION,
				'Id ['.$_REQUEST['actionid'].'] '.S_NAME.' ['.$action_data['name'].']');
			unset($_REQUEST['form']);
			unset($_REQUEST['actionid']);
		}
	}
	elseif(inarr_isset(array('add_condition','new_condition')))
	{
		$new_condition = $_REQUEST['new_condition'];

		if( validate_condition($new_condition['type'],$new_condition['value']) )
		{
			$_REQUEST['conditions'] = get_request('conditions',array());
			if(!in_array($new_condition,$_REQUEST['conditions']))
				array_push($_REQUEST['conditions'],$new_condition);

			unset($_REQUEST['new_condition']);
		}
	}
	elseif(inarr_isset(array('del_condition','g_conditionid')))
	{
		$_REQUEST['conditions'] = get_request('conditions',array());
		foreach($_REQUEST['g_conditionid'] as $val){
			unset($_REQUEST['conditions'][$val]);
		}
	}
	elseif(inarr_isset(array('add_operation','new_operation')))
	{
		$new_operation = $_REQUEST['new_operation'];

		if( validate_operation($new_operation) )
		{
			zbx_rksort($new_operation);

			$_REQUEST['operations'] = get_request('operations',array());

			if(!isset($new_operation['id']))
			{
				if(!in_array($new_operation,$_REQUEST['operations']))
					array_push($_REQUEST['operations'],$new_operation);
			}
			else
			{
				$id = $new_operation['id'];
				unset($new_operation['id']);
				$_REQUEST['operations'][$id] = $new_operation;
			}

			unset($_REQUEST['new_operation']);
		}
	}
	elseif(inarr_isset(array('del_operation','g_operationid')))
	{
		$_REQUEST['operations'] = get_request('operations',array());
		foreach($_REQUEST['g_operationid'] as $val){
			unset($_REQUEST['operations'][$val]);
		}
	}
	elseif(inarr_isset(array('edit_operationid')))
	{	
		$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
		$edit_operationid = $_REQUEST['edit_operationid'] =array_pop($_REQUEST['edit_operationid']);
		$_REQUEST['operations'] = get_request('operations',array());
		if(isset($_REQUEST['operations'][$edit_operationid]))
		{
			$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
			$_REQUEST['new_operation']['id'] = $edit_operationid;
		}
	}
/* GROUP ACTIONS */
	elseif(isset($_REQUEST['group_enable'])&&isset($_REQUEST['g_actionid']))
	{
		global $USER_DETAILS, $ZBX_CURNODEID;

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			access_deny();

		$result=DBselect('select distinct actionid from actions'.
				' where '.DBid2nodeid('actionid').'='.$ZBX_CURNODEID.
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') '
				);
		
		$actionids = array();
		while($row=DBfetch($result))
		{
			$res = update_action_status($row['actionid'],0);
			if($res)
				$actionids[] = $row['actionid'];
		}
		if(isset($res))
		{
			show_messages(true, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] enabled');
		}
	}
	elseif(isset($_REQUEST['group_disable'])&&isset($_REQUEST['g_actionid']))
	{
		global $USER_DETAILS, $ZBX_CURNODEID;

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			access_deny();

		$result=DBselect('select distinct actionid from actions'.
				' where '.DBid2nodeid('actionid').'='.$ZBX_CURNODEID.
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') '
				);
		$actionids = array();
		while($row=DBfetch($result))
		{
			$res = update_action_status($row['actionid'],1);
			if($res) 
				$actionids[] = $row['actionid'];
		}
		if(isset($res))
		{
			show_messages(true, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] disabled');
		}
	}
	elseif(isset($_REQUEST['group_delete'])&&isset($_REQUEST['g_actionid']))
	{
		global $USER_DETAILS, $ZBX_CURNODEID;

		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_MODE_LT,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			access_deny();

		$result=DBselect('select distinct actionid from actions'.
				' where '.DBid2nodeid('actionid').'='.$ZBX_CURNODEID.
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') '
				);
		$actionids = array();
		while($row=DBfetch($result))
		{
			$del_res = delete_action($row['actionid']);
			if($del_res) 
				$actionids[] = $row['actionid'];
		}
		if(isset($del_res))
		{
			show_messages(TRUE, S_ACTIONS_DELETED, S_CANNOT_DELETE_ACTIONS);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] deleted');
		}
	}
?>

<?php
/* header */
	$form = new CForm();
	$form->AddVar('eventsource', $_REQUEST['eventsource']);
	$form->AddItem(new CButton('form',S_CREATE_ACTION));
	show_table_header(S_CONFIGURATION_OF_ACTIONS_BIG, $form);
	echo BR;

	if(isset($_REQUEST['form']))
	{
/* form */
		insert_action_form();
	}
	else
	{
		$form = new CForm();

		$cmbSource = new CComboBox('eventsource',$_REQUEST['eventsource'],'submit()');
		$cmbSource->AddItem(EVENT_SOURCE_TRIGGERS,S_TRIGGERS);
		$cmbSource->AddItem(EVENT_SOURCE_DISCOVERY,S_DISCOVERY);
		$form->AddItem(array(S_EVENT_SOURCE, SPACE, $cmbSource));

		show_table_header(S_ACTIONS_BIG, $form);
		unset($form, $cmbSource);
/* table */
		$form = new CForm();
		$form->SetName('actions');

		$tblActions = new CTableInfo(S_NO_ACTIONS_DEFINED);
		$tblActions->SetHeader(array(
			array(	new CCheckBox('all_items',null,'CheckAll("'.$form->GetName().'","all_items");'),
				S_NAME
			),
			S_CONDITIONS,
			S_OPERATIONS,
			S_STATUS));

		$db_actions = DBselect('select * from actions where eventsource='.$_REQUEST['eventsource'].
			' and '.DBid2nodeid('actionid').'='.$ZBX_CURNODEID.' order by name,actionid');
		while($action_data = DBfetch($db_actions))
		{
			if(!action_accessiable($action_data['actionid'], PERM_READ_WRITE)) continue;

			$conditions='';
			$db_conditions = DBselect('select * from conditions where actionid='.$action_data['actionid'].
				' order by conditiontype,conditionid');
			while($condition_data = DBfetch($db_conditions))
			{
				$conditions .= get_condition_desc(
							$condition_data['conditiontype'],
							$condition_data['operator'],
							$condition_data['value']).BR;
			}
			unset($db_conditions, $condition_data);

			$operations='';
			$db_operations = DBselect('select * from operations where actionid='.$action_data['actionid'].
				' order by operationtype,operationid');
			while($operation_data = DBfetch($db_operations))
				$operations .= get_operation_desc(SHORT_DESCRITION, $operation_data).BR;
				
			if($action_data['status'] == ACTION_STATUS_DISABLED)
			{
				$status= new CLink(S_DISABLED,
					'actionconf.php?group_enable=1&g_actionid%5B%5D='.$action_data['actionid'].url_param('eventsource'),
					'disabled');
			}
			else
			{
				$status= new CLink(S_ENABLED,
					'actionconf.php?group_disable=1&g_actionid%5B%5D='.$action_data['actionid'].url_param('eventsource'),
					'enabled');
			}

			$tblActions->AddRow(array(
				array(
					new CCheckBox(
						'g_actionid[]',			/* name */
						null,				/* checked */
						null,				/* action */
						$action_data['actionid']),	/* value */
					SPACE,
					new CLink(
						$action_data['name'],
						'actionconf.php?form=update&actionid='.$action_data['actionid'],'action'),
					),
				$conditions,
				$operations,
				$status
				));	
		}

		$tblActions->SetFooter(new CCol(array(
			new CButtonQMessage('group_enable',S_ENABLE_SELECTED,S_ENABLE_SELECTED_ACTIONS_Q),
			SPACE,
			new CButtonQMessage('group_disable',S_DISABLE_SELECTED,S_DISABLE_SELECTED_ACTIONS_Q),
			SPACE,
			new CButtonQMessage('group_delete',S_DELETE_SELECTED,S_DELETE_SELECTED_ACTIONS_Q)
		)));

		$form->AddItem($tblActions);
		$form->Show();
	}
?>
<?php

	include_once "include/page_footer.php";

?>
