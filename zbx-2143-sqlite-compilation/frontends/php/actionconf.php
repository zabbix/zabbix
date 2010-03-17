<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
require_once('include/config.inc.php');
require_once('include/actions.inc.php');
require_once('include/hosts.inc.php');
include_once('include/discovery.inc.php');
require_once('include/triggers.inc.php');
require_once('include/events.inc.php');
require_once('include/forms.inc.php');
require_once('include/media.inc.php');
require_once('include/nodes.inc.php');

$page['title']		= 'S_CONFIGURATION_OF_ACTIONS';
$page['file']		= 'actionconf.php';
$page['hist_arg']	= array();

include_once('include/page_header.php');

$_REQUEST['eventsource'] = get_request('eventsource',CProfile::get('web.actionconf.eventsource',EVENT_SOURCE_TRIGGERS));
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(

		'actionid'=>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, null),
		'name'=>			array(T_ZBX_STR, O_OPT,	 null, NOT_EMPTY, 'isset({save})'),
		'eventsource'=>		array(T_ZBX_INT, O_MAND, null, IN(array(EVENT_SOURCE_TRIGGERS,EVENT_SOURCE_DISCOVERY,EVENT_SOURCE_AUTO_REGISTRATION)),	null),
		'evaltype'=>		array(T_ZBX_INT, O_OPT, null, IN(array(ACTION_EVAL_TYPE_AND_OR,ACTION_EVAL_TYPE_AND,ACTION_EVAL_TYPE_OR)),	'isset({save})'),
		'esc_period'=>		array(T_ZBX_INT, O_OPT, null, BETWEEN(60,999999), 'isset({save})&&isset({escalation})'),
		'escalation'=>		array(T_ZBX_INT, O_OPT, null, IN("0,1"), null),
		'status'=>			array(T_ZBX_INT, O_OPT, null, IN(array(ACTION_STATUS_ENABLED,ACTION_STATUS_DISABLED)), 'isset({save})'),
		'def_shortdata'=>	array(T_ZBX_STR, O_OPT,	null, null, 'isset({save})'),
		'def_longdata'=>	array(T_ZBX_STR, O_OPT,	null, null, 'isset({save})'),
		'recovery_msg'=>	array(T_ZBX_INT, O_OPT,	null, null, null),
		'r_shortdata'=>		array(T_ZBX_STR, O_OPT,	null, NOT_EMPTY, 'isset({recovery_msg})&&isset({save})'),
		'r_longdata'=>		array(T_ZBX_STR, O_OPT,	null, NOT_EMPTY, 'isset({recovery_msg})&&isset({save})'),
		'g_actionid'=>		array(T_ZBX_INT, O_OPT,	null, DB_ID, null),
		'conditions'=>		array(null, O_OPT, null, null, null),
		'g_conditionid'=>	array(null, O_OPT, null, null, null),
		'new_condition'=>	array(null, O_OPT, null, null, 'isset({add_condition})'),
		'operations'=>		array(null, O_OPT, null, null, 'isset({save})'),
		'g_operationid'=>	array(null, O_OPT, null, null, null),
		'edit_operationid'=>	array(null, O_OPT, P_ACT, DB_ID, null),
		'new_operation'=>		array(null, O_OPT, null, null, 'isset({add_operation})'),
		'opconditions'=>		array(null, O_OPT, null, null, null),
		'g_opconditionid'=>		array(null, O_OPT, null, null, null),
		'new_opcondition'=>		array(null,	O_OPT,  null,	null,	'isset({add_opcondition})'),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'add_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_condition'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_operation'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_opcondition'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL, NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('name',ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go','none');
?>
<?php
/* AJAX */
// for future use
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.audit.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once('include/page_footer.php');
		exit();
	}
//--------

	if(isset($_REQUEST['actionid'])){
		$aa = CAction::get(array('actionids' => $_REQUEST['actionid'], 'editable' => 1));
		if(empty($aa)){
			access_deny();
		}
	}

	CProfile::update('web.actionconf.eventsource',$_REQUEST['eventsource'], PROFILE_TYPE_INT);
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

			$actionid = $_REQUEST['actionid'];
			$result = update_action($actionid,
				$_REQUEST['name'],$_REQUEST['eventsource'],$_REQUEST['esc_period'],
				$_REQUEST['def_shortdata'],$_REQUEST['def_longdata'],
				$_REQUEST['recovery_msg'],$_REQUEST['r_shortdata'],$_REQUEST['r_longdata'],
				$_REQUEST['evaltype'],$_REQUEST['status'],
				$conditions, $operations
				);

			$result = DBend($result);
			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
		}
		else {
			foreach($conditions as $cnum => &$condition){
				$condition['conditiontype'] = $condition['type'];
			}
			unset($condition);

			$action = array(
					'name'				=> get_request('name'),
					'eventsource'		=> get_request('eventsource',0),
					'evaltype'			=> get_request('evaltype',0),
					'status'			=> get_request('status',0),
					'esc_period'		=> get_request('esc_period',0),
					'def_shortdata'		=> get_request('def_shortdata',''),
					'def_longdata'		=> get_request('def_longdata',''),
					'recovery_msg'		=> get_request('recovery_msg',0),
					'r_shortdata'		=> get_request('r_shortdata',''),
					'r_longdata'			=> get_request('r_longdata',''),
					'conditions'		=> $conditions,
					'operations'		=> $operations
				);

			$result = CAction::create($action);
			$result = DBend($result);
			show_messages($result,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
		}

		if($result){
// result - OK
			add_audit(!isset($_REQUEST['actionid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_ACTION,
				S_NAME.': '.$_REQUEST['name']);

			unset($_REQUEST['form']);
		}
	}
	else if(inarr_isset(array('delete','actionid'))){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
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

		if(!isset($new_condition['value'])) $new_condition['value'] = '';

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


			if(($new_operation['esc_step_from'] <= $new_operation['esc_step_to']) || ($new_operation['esc_step_to']==0)) {

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
			else{
				info(S_INCORRECT_STEPS);
			}
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
// ------ GO ------
	else if(str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['g_actionid'])){
		if(!count($nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$status = ($_REQUEST['go'] == 'activate')?0:1;
		$status_name = $status?'disabled':'enabled';

		DBstart();
		$actionids = array();
		$sql = 'SELECT DISTINCT a.actionid '.
					' FROM actions a '.
					' WHERE '.DBin_node('a.actionid',$nodes).
						' AND '.DBcondition('a.actionid', $_REQUEST['g_actionid']);

		$go_result=DBselect($sql);
		while($row=DBfetch($go_result)){
			$res = update_action_status($row['actionid'],$status);
			if($res)
				$actionids[] = $row['actionid'];
		}
		$go_result = DBend($res);

		if($go_result && isset($res)){
			show_messages($go_result, S_STATUS_UPDATED, S_CANNOT_UPDATE_STATUS);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] '.$status_name);
		}
	}
	else if(($_REQUEST['go'] == 'delete') && isset($_REQUEST['g_actionid'])){
		if(!count($nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		DBstart();
		$actionids = array();
		$sql = 'SELECT DISTINCT a.actionid '.
					' FROM actions a '.
					' WHERE '.DBin_node('a.actionid',$nodes).
						' AND '.DBcondition('a.actionid', $_REQUEST['g_actionid']);

		$go_result=DBselect($sql);
		while($row=DBfetch($go_result)){
			$del_res = delete_action($row['actionid']);
			if($del_res)
				$actionids[] = $row['actionid'];
		}
		$go_result = DBend();

		if($go_result && isset($del_res)){
			show_messages(TRUE, S_ACTIONS_DELETED, S_CANNOT_DELETE_ACTIONS);
			add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',',$actionids).'] deleted');
		}
	}

	if(($_REQUEST['go'] != 'none') && isset($go_result) && $go_result){
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	$action_wdgt = new CWidget();

/* header */
	$form = new CForm(null, 'get');

	$form->addVar('eventsource', $_REQUEST['eventsource']);
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CButton('form', S_CREATE_ACTION));
	}

	$action_wdgt->addPageHeader(S_CONFIGURATION_OF_ACTIONS_BIG, $form);

	if(isset($_REQUEST['form'])){
		$frmAction = new CForm('actionconf.php', 'post');
		$frmAction->setName(S_ACTION);

		$frmAction->addVar('form', get_request('form', 1));
		$from_rfr = get_request('form_refresh', 0);
		$frmAction->addVar('form_refresh', $from_rfr+1);

		$action = null;
		if(isset($_REQUEST['actionid'])){
			$action = get_action_by_actionid($_REQUEST['actionid']);
			$frmAction->addVar('actionid',$_REQUEST['actionid']);
		}

		$left_tab = new CTable();
		$left_tab->setCellPadding(3);
		$left_tab->setCellSpacing(3);

		$left_tab->setAttribute('border',0);

		$left_tab->addRow(create_hat(
				S_ACTION,
				get_act_action_form($action),//null,
				null,
				'hat_action'
			));

		$left_tab->addRow(create_hat(
				S_ACTION_CONDITIONS,
				get_act_condition_form($action),//null,
				null,
				'hat_conditions'
			));

		if(isset($_REQUEST['new_condition'])){
			$left_tab->addRow(create_hat(
					S_NEW_CONDITION,
					get_act_new_cond_form($action),//null,
					null,
					'hat_new_cond'
				));
		}


		$right_tab = new CTable();
		$right_tab->setCellPadding(3);
		$right_tab->setCellSpacing(3);

		$right_tab->setAttribute('border',0);

		$right_tab->addRow(create_hat(
				S_ACTION_OPERATIONS,
				get_act_operations_form($action),//null,
				null,
				'hat_operations'
			));

		if(isset($_REQUEST['new_operation'])){
			$right_tab->addRow(create_hat(
					S_EDIT_OPERATION,
					get_act_new_oper_form($action),//null,
					null,
					'hat_new_oper'
				));
		}

		if(isset($_REQUEST['new_opcondition'])){
			$right_tab->addRow(create_hat(
					S_NEW.SPACE.S_OPERATION_CONDITION,
					get_oper_new_cond_form($action),//null,
					null,
					'hat_new_oper_cond'
				));
		}

		$td_l = new CCol($left_tab);
		$td_l->setAttribute('valign','top');

		$td_r = new CCol($right_tab);
		$td_r->setAttribute('valign','top');

		$outer_table = new CTable();
		$outer_table->setAttribute('border',0);
		$outer_table->setCellPadding(1);
		$outer_table->setCellSpacing(1);
		$outer_table->addRow(array($td_l,$td_r));

		$frmAction->additem($outer_table);

		show_messages();

		$action_wdgt->addItem($frmAction);
//*/
	}
	else{
		$form = new CForm(null, 'get');

		$cmbSource = new CComboBox('eventsource',$_REQUEST['eventsource'],'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS,S_TRIGGERS);
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY,S_DISCOVERY);
		$cmbSource->addItem(EVENT_SOURCE_AUTO_REGISTRATION,S_AUTO_REGISTRATION);
		$form->addItem(array(S_EVENT_SOURCE, SPACE, $cmbSource));

		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$action_wdgt->addHeader(S_ACTIONS_BIG, $form);
		$action_wdgt->addHeader($numrows);

		unset($form, $cmbSource);

// table
		$form = new CForm();
		$form->setName('actions');

		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();
		$options = array(
			'extendoutput' => 1,
			'eventsource' => $_REQUEST['eventsource'],
			'select_conditions' => 1,
			'select_operations' => 1,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$actions = CAction::get($options);

		$tblActions = new CTableInfo(S_NO_ACTIONS_DEFINED);
		$tblActions->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->getName()."','all_items','g_actionid');"),
			make_sorting_header(S_NAME, 'name'),
			S_CONDITIONS,
			S_OPERATIONS,
			make_sorting_header(S_STATUS, 'status')
		));

// sorting && paging
		order_result($actions, $sortfield, $sortorder);
		$paging = getPagingLine($actions);
//-------

		foreach($actions as $anum => $action){
			$actionid = $action['actionid'];

			$conditions = array();

// sorting
			order_result($action['conditions'], 'conditiontype', null, true);
			foreach($action['conditions'] as $cnum => $condition){
				array_push($conditions, array(get_condition_desc(
							$condition['conditiontype'],
							$condition['operator'],
							$condition['value']),BR()));
			}


			$operations=array();

// sorting
			order_result($action['operations'], 'operationtype', null, true);
			foreach($action['operations'] as $onum => $operation){
				array_push($operations,array(get_operation_desc(SHORT_DESCRITION, $operation),BR()));
			}

			if($action['status'] == ACTION_STATUS_DISABLED){
				$status= new CLink(S_DISABLED,
					'actionconf.php?go=activate&g_actionid%5B%5D='.$action['actionid'].url_param('eventsource'),
					'disabled');
			}
			else{
				$status= new CLink(S_ENABLED,
					'actionconf.php?go=disable&g_actionid%5B%5D='.$action['actionid'].url_param('eventsource'),
					'enabled');
			}

			$tblActions->addRow(array(
				new CCheckBox('g_actionid['.$action['actionid'].']',null,null,$action['actionid']),
				new CLink($action['name'],'actionconf.php?form=update&actionid='.$action['actionid']),
				$conditions,
				$operations,
				$status
				));
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate',S_ENABLE_SELECTED);
		$goOption->setAttribute('confirm',S_ENABLE.' '.S_SELECTED_ACTIONS);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable',S_DISABLE_SELECTED);
		$goOption->setAttribute('confirm',S_DISABLE.' '.S_SELECTED_ACTIONS);
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete',S_DELETE_SELECTED);
		$goOption->setAttribute('confirm',S_DELETE.' '.S_SELECTED_ACTIONS);
		$goBox->addItem($goOption);

// goButton name is necessary!!!
		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');

		$jsLocale = array(
			'S_CLOSE',
			'S_NO_ELEMENTS_SELECTED'
		);

		zbx_addJSLocale($jsLocale);

		zbx_add_post_js('chkbxRange.pageGoName = "g_actionid";');

		$footer = get_table_header(array($goBox, $goButton));
//----

// PAGING FOOTER
		$tblActions = array($paging, $tblActions, $paging, $footer);
//---------

		$form->addItem($tblActions);
		$action_wdgt->addItem($form);
	}

	$action_wdgt->show();

	
include_once('include/page_footer.php');
?>