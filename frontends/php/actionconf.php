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
	require_once 'include/config.inc.php';
	require_once 'include/actions.inc.php';
	require_once 'include/hosts.inc.php';
	include_once 'include/discovery.inc.php';
	require_once 'include/triggers.inc.php';
	require_once 'include/events.inc.php';
	require_once 'include/forms.inc.php';


	$page['title']	= "S_CONFIGURATION_OF_ACTIONS";
	$page['file']	= 'actionconf.php';
	$page['hist_arg'] = array();

include_once 'include/page_header.php';
	
	$_REQUEST['eventsource'] = get_request('eventsource',get_profile('web.actionconf.eventsource',EVENT_SOURCE_TRIGGERS));
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(

		'actionid'=>		array(T_ZBX_INT, O_OPT,  P_SYS,	DB_ID,					null),
		'name'=>			array(T_ZBX_STR, O_OPT,	 null,	NOT_EMPTY,				'isset({save})'),
		'eventsource'=>		array(T_ZBX_INT, O_MAND, null,	IN(array(EVENT_SOURCE_TRIGGERS,EVENT_SOURCE_DISCOVERY)),	null),
		'evaltype'=>		array(T_ZBX_INT, O_OPT,	 null,	IN(array(ACTION_EVAL_TYPE_AND_OR,ACTION_EVAL_TYPE_AND,ACTION_EVAL_TYPE_OR)), 	'isset({save})'),
		'esc_period'=>		array(T_ZBX_INT, O_OPT,  null,	BETWEEN(60,999999),		'isset({save})&&isset({escalation})'),
		'escalation'=>		array(T_ZBX_INT, O_OPT,  null,	IN("0,1"),		null),
		'status'=>			array(T_ZBX_INT, O_OPT,	 null,	IN(array(ACTION_STATUS_ENABLED,ACTION_STATUS_DISABLED)),			'isset({save})'),
		
		'def_shortdata'=>	array(T_ZBX_STR, O_OPT,	 null,	null,				'isset({save})'),
		'def_longdata'=>	array(T_ZBX_STR, O_OPT,	 null,	null,				'isset({save})'),

		'recovery_msg'=>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
		'r_shortdata'=>		array(T_ZBX_STR, O_OPT,	 null,	NOT_EMPTY,				'isset({recovery_msg})&&isset({save})'),
		'r_longdata'=>		array(T_ZBX_STR, O_OPT,	 null,	NOT_EMPTY,				'isset({recovery_msg})&&isset({save})'),

		'g_actionid'=>		array(T_ZBX_INT, O_OPT,  null,	DB_ID,		null),

		'conditions'=>		array(null, O_OPT, null, null, null),
		'g_conditionid'=> 	array(null, O_OPT, null, null, null),

		'new_condition'=>	array(null, 	 O_OPT,  null,	null,	'isset({add_condition})'),
		
		'operations'=>		array(null, O_OPT, null, null, 'isset({save})'),
		'g_operationid'=>	array(null, O_OPT, null, null, null),

		'edit_operationid'=>array(null, O_OPT, P_ACT,	DB_ID,	null),

		'new_operation'=>	array(null, O_OPT,  null,	null,	'isset({add_operation})'),
				
		'opconditions'=>		array(null, O_OPT, null, null, null),
		'g_opconditionid'=> 	array(null, O_OPT, null, null, null),

		'new_opcondition'=>	array(null, 	 O_OPT,  null,	null,	'isset({add_opcondition})'),

/* actions */
		'group_delete'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'group_enable'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'group_disable'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_condition'=>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_operation'=>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_opcondition'=>array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		
		'save'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>				array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>				array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
		
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('a.name',ZBX_SORT_UP);
//SDI($_REQUEST);
/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.audit.filter.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------
	
	if(isset($_REQUEST['actionid']) && !action_accessible($_REQUEST['actionid'], PERM_READ_WRITE)){
		access_deny();
	}
?>
<?php
	update_profile('web.actionconf.eventsource',$_REQUEST['eventsource']);
?>
<?php
	if(inarr_isset(array('clone','actionid'))){
		unset($_REQUEST['actionid']);
		$_REQUEST['form'] = 'clone';
	}
	else if(isset($_REQUEST['cancel_new_condition'])){
		unset($_REQUEST['new_condition']);
	}
	else if(isset($_REQUEST['cancel_new_operation'])){
		unset($_REQUEST['new_operation']);
	}
	else if(isset($_REQUEST['cancel_new_opcondition'])){
		unset($_REQUEST['new_opcondition']);
	}
	else if(isset($_REQUEST['save'])){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$_REQUEST['recovery_msg'] = get_request('recovery_msg',0);
		$_REQUEST['r_shortdata'] = get_request('r_shortdata','');
		$_REQUEST['r_longdata'] = get_request('r_longdata','');
		
		if(!isset($_REQUEST['escalation'])) $_REQUEST['esc_period'] = 0;
		
		$conditions = get_request('conditions', array());
		$operations = get_request('operations', array());

		DBstart();
		if(isset($_REQUEST['actionid'])){

			$actionid=$_REQUEST['actionid'];
			$result = update_action($actionid,
				$_REQUEST['name'],$_REQUEST['eventsource'],$_REQUEST['esc_period'],
				$_REQUEST['def_shortdata'],$_REQUEST['def_longdata'],
				$_REQUEST['recovery_msg'],$_REQUEST['r_shortdata'],$_REQUEST['r_longdata'],
				$_REQUEST['evaltype'],$_REQUEST['status'],
				$conditions, $operations
				);

			$result = DBend();
			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
		} 
		else {
			
			$actionid=add_action(
				$_REQUEST['name'],$_REQUEST['eventsource'],$_REQUEST['esc_period'],
				$_REQUEST['def_shortdata'],$_REQUEST['def_longdata'],
				$_REQUEST['recovery_msg'],$_REQUEST['r_shortdata'],$_REQUEST['r_longdata'],
				$_REQUEST['evaltype'],$_REQUEST['status'],
				$conditions, $operations
				);
			$result=$actionid;

			$result = DBend();
			show_messages($result,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
		}

		if($result){ // result - OK
			add_audit(!isset($_REQUEST['actionid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE, 
				AUDIT_RESOURCE_ACTION, 
				S_NAME.': '.$_REQUEST['name']);

			unset($_REQUEST['form']);
		}
	}
	else if(inarr_isset(array('delete','actionid'))){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();

		$action_data = DBfetch(DBselect('select name from actions where actionid='.$_REQUEST['actionid']));

		DBstart();
		delete_action($_REQUEST['actionid']);
		$result = DBend();
		
		show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
		if($result){
			add_audit(AUDIT_ACTION_DELETE,AUDIT_RESOURCE_ACTION,
				'Id ['.$_REQUEST['actionid'].'] '.S_NAME.' ['.$action_data['name'].']');
			unset($_REQUEST['form']);
			unset($_REQUEST['actionid']);
		}
	}
	else if(inarr_isset(array('add_condition','new_condition'))){
		$new_condition = $_REQUEST['new_condition'];

		if( validate_condition($new_condition['type'],$new_condition['value']) ){
			$_REQUEST['conditions'] = get_request('conditions',array());
			if(!str_in_array($new_condition,$_REQUEST['conditions']))
				array_push($_REQUEST['conditions'],$new_condition);

			unset($_REQUEST['new_condition']);
		}
	}
	else if(inarr_isset(array('del_condition','g_conditionid'))){
		$_REQUEST['conditions'] = get_request('conditions',array());
		foreach($_REQUEST['g_conditionid'] as $val){
			unset($_REQUEST['conditions'][$val]);
		}
	}
	else if(inarr_isset(array('add_opcondition','new_opcondition'))){
		$new_opcondition = $_REQUEST['new_opcondition'];

		if( validate_condition($new_opcondition['conditiontype'],$new_opcondition['value']) ){
			$new_operation = get_request('new_operation',array());
			if(!isset($new_operation['opconditions'])) $new_operation['opconditions'] = array();
			
			if(!str_in_array($new_opcondition,$new_operation['opconditions']))
				array_push($new_operation['opconditions'],$new_opcondition);

			$_REQUEST['new_operation'] = $new_operation;
			
			unset($_REQUEST['new_opcondition']);
		}
	}
	else if(inarr_isset(array('del_opcondition','g_opconditionid'))){
		$new_operation = get_request('new_operation',array());

		foreach($_REQUEST['g_opconditionid'] as $val){
			unset($new_operation['opconditions'][$val]);
		}
		
		$_REQUEST['new_operation'] = $new_operation;
	}
	else if(inarr_isset(array('add_operation','new_operation'))){
		$new_operation = $_REQUEST['new_operation'];
		if(validate_operation($new_operation)){
			zbx_rksort($new_operation);

			$_REQUEST['operations'] = get_request('operations',array());

			if($new_operation['esc_step_from'] > $new_operation['esc_step_to']) {
				$from	= $new_operation['esc_step_to'];
				$new_operation['esc_step_to']	= $new_operation['esc_step_from'];
				$new_operation['esc_step_from']	= $from;
			}

			if(!isset($new_operation['id'])){
				if(!str_in_array($new_operation,$_REQUEST['operations']))
					array_push($_REQUEST['operations'],$new_operation);
			}
			else{
				$id = $new_operation['id'];
				unset($new_operation['id']);
				$_REQUEST['operations'][$id] = $new_operation;
			}

			unset($_REQUEST['new_operation']);
		}
	}
	else if(inarr_isset(array('del_operation','g_operationid'))){
		$_REQUEST['operations'] = get_request('operations',array());
		foreach($_REQUEST['g_operationid'] as $val){
			unset($_REQUEST['operations'][$val]);
		}
	}
	else if(inarr_isset(array('edit_operationid'))){	
		$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
		$edit_operationid = $_REQUEST['edit_operationid'] =array_pop($_REQUEST['edit_operationid']);
		$_REQUEST['operations'] = get_request('operations',array());
		
		if(isset($_REQUEST['operations'][$edit_operationid])){
			$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
			$_REQUEST['new_operation']['id'] = $edit_operationid;
		}
	}
/* GROUP ACTIONS */
	else if(isset($_REQUEST['group_enable'])&&isset($_REQUEST['g_actionid'])){
		if(!count($nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();
		
		$query = 'select distinct actionid from actions'.
				' where '.DBin_node('actionid',$nodes).
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') ';
				
		$result=DBselect($query);
		
		$actionids = array();

		DBstart();
		while($row=DBfetch($result)){
			$res = update_action_status($row['actionid'],0);
			if($res)
				$actionids[] = $row['actionid'];
		}
		$result = DBend();

		if($result && isset($res)){
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] enabled');
		}
	}
	else if(isset($_REQUEST['group_disable'])&&isset($_REQUEST['g_actionid'])){

		if(!count($nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();

		$query = 'select distinct actionid from actions'.
				' where '.DBin_node('actionid',$nodes).
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') ';

		$result=DBselect($query);

		$actionids = array();
		Dbstart();
		while($row=DBfetch($result)){
			$res = update_action_status($row['actionid'],1);
			if($res) 
				$actionids[] = $row['actionid'];
		}
		$result = DBend();
		
		if($result && isset($res)){
			show_messages($result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] disabled');
		}
	}
	else if(isset($_REQUEST['group_delete'])&&isset($_REQUEST['g_actionid'])){
		if(!count($nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY,get_current_nodeid())))
			access_deny();

		$result=DBselect('select distinct actionid from actions'.
				' where '.DBin_node('actionid',$nodes).
				' and actionid in ('.implode(',',$_REQUEST['g_actionid']).') '
				);
		$actionids = array();
		DBstart();
		while($row=DBfetch($result)){
			$del_res = delete_action($row['actionid']);
			if($del_res) 
				$actionids[] = $row['actionid'];
		}
		$result = DBend();
		
		if($result && isset($del_res)){
			show_messages(TRUE, S_ACTIONS_DELETED, S_CANNOT_DELETE_ACTIONS);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] deleted');
		}
	}
?>
<?php
/* header */
	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar('eventsource', $_REQUEST['eventsource']);
	$form->AddItem(new CButton('form',S_CREATE_ACTION));
	show_table_header(S_CONFIGURATION_OF_ACTIONS_BIG, $form);

	if(isset($_REQUEST['form'])){
/* form */
//		insert_action_form();
//* NEW Form 
		$frmAction = new CForm('actionconf.php','post');
		$frmAction->SetName(S_ACTION);
		
		$frmAction->AddVar('form',get_request('form',1));
		$from_rfr = get_request('form_refresh',0);
		$frmAction->AddVar('form_refresh',$from_rfr+1);
		
		$action = null;
		if(isset($_REQUEST['actionid'])){
			$action = get_action_by_actionid($_REQUEST['actionid']);
			$frmAction->AddVar('actionid',$_REQUEST['actionid']);
		}
		
		$left_tab = new CTable();
		$left_tab->SetCellPadding(3);
		$left_tab->SetCellSpacing(3);
		
		$left_tab->AddOption('border',0);
		
		$left_tab->AddRow(create_hat(
				S_ACTION,
				get_act_action_form($action),//null,
				null,
				'hat_action',
				get_profile('web.actionconf.hats.hat_action.state',1)
			));
			
		$left_tab->AddRow(create_hat(
				S_ACTION_CONDITIONS,
				get_act_condition_form($action),//null,
				null,
				'hat_conditions',
				get_profile('web.actionconf.hats.hat_conditions.state',1)
			));
			
		if(isset($_REQUEST['new_condition'])){
			$left_tab->AddRow(create_hat(
					S_NEW_CONDITION,
					get_act_new_cond_form($action),//null,
					null,
					'hat_new_cond',
					get_profile('web.actionconf.hats.hat_new_cond.state',1)
				));			
		}


		$right_tab = new CTable();
		$right_tab->SetCellPadding(3);
		$right_tab->SetCellSpacing(3);
		
		$right_tab->AddOption('border',0);
				
		$right_tab->AddRow(create_hat(
				S_ACTION_OPERATIONS,
				get_act_operations_form($action),//null,
				null,
				'hat_operations',
				get_profile('web.actionconf.hats.hat_operations.state',1)
			));

		if(isset($_REQUEST['new_operation'])){
			$right_tab->AddRow(create_hat(
					S_EDIT_OPERATION,
					get_act_new_oper_form($action),//null,
					null,
					'hat_new_oper',
					get_profile('web.actionconf.hats.hat_new_oper.state',1)
				));
		}
		
		if(isset($_REQUEST['new_opcondition'])){
			$right_tab->AddRow(create_hat(
					S_NEW.SPACE.S_OPERATION_CONDITION,
					get_oper_new_cond_form($action),//null,
					null,
					'hat_new_oper_cond',
					get_profile('web.actionconf.hats.hat_new_oper_cond.state',1)
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
		
		$frmAction->Additem($outer_table);
		
		show_messages();
		$frmAction->Show();
//*/
	}
	else{
		$form = new CForm();
		$form->SetMethod('get');
		
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
				make_sorting_link(S_NAME,'a.name')
			),
			S_CONDITIONS,
			S_OPERATIONS,
			make_sorting_link(S_STATUS,'a.status')
			));

		$db_actions = DBselect('SELECT a.* '.
							' FROM actions a'.
							' WHERE a.eventsource='.$_REQUEST['eventsource'].
								' AND '.DBin_node('actionid').
							order_by('a.name,a.status','a.actionid'));
		while($action_data = DBfetch($db_actions)){
			if(!action_accessible($action_data['actionid'], PERM_READ_WRITE)) continue;

			$conditions=array();
			$db_conditions = DBselect('select * from conditions where actionid='.$action_data['actionid'].
				' order by conditiontype,conditionid');
			while($condition_data = DBfetch($db_conditions)){
				array_push($conditions, array(get_condition_desc(
							$condition_data['conditiontype'],
							$condition_data['operator'],
							$condition_data['value']),BR()));
			}
			unset($db_conditions, $condition_data);

			$operations=array();
			$db_operations = DBselect('select * from operations where actionid='.$action_data['actionid'].
				' order by operationtype,operationid');
			while($operation_data = DBfetch($db_operations))
				array_push($operations,array(get_operation_desc(SHORT_DESCRITION, $operation_data),BR()));
				
			if($action_data['status'] == ACTION_STATUS_DISABLED){
				$status= new CLink(S_DISABLED,
					'actionconf.php?group_enable=1&g_actionid%5B%5D='.$action_data['actionid'].url_param('eventsource'),
					'disabled');
			}
			else{
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
