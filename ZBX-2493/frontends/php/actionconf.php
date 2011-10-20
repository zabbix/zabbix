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
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favobj})'),
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

		if(!isset($_REQUEST['escalation'])) $_REQUEST['esc_period'] = 0;

		$conditions = get_request('conditions', array());
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
			'r_longdata'		=> get_request('r_longdata',''),
			'conditions'		=> $conditions,
			'operations'		=> get_request('operations', array()),
		);

		if(isset($_REQUEST['actionid'])){
			$action['actionid']= $_REQUEST['actionid'];

			$result = CAction::update($action);
			show_messages($result,S_ACTION_UPDATED,S_CANNOT_UPDATE_ACTION);
		}
		else{
			$result = CAction::create($action);
			show_messages($result,S_ACTION_ADDED,S_CANNOT_ADD_ACTION);
		}

		if($result){
			add_audit(!isset($_REQUEST['actionid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_ACTION,
				S_NAME.': '.$_REQUEST['name']);

			unset($_REQUEST['form']);
		}
	}
	else if(inarr_isset(array('delete','actionid'))){
		if(!count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_WRITE,PERM_RES_IDS_ARRAY)))
			access_deny();

		$result = CAction::delete($_REQUEST['actionid']);

		show_messages($result,S_ACTION_DELETED,S_CANNOT_DELETE_ACTION);
		if($result){
			unset($_REQUEST['form']);
			unset($_REQUEST['actionid']);
		}
	}
	else if(inarr_isset(array('add_condition', 'new_condition'))){
		$new_condition = $_REQUEST['new_condition'];

		if(!isset($new_condition['value'])) $new_condition['value'] = '';

		if(validate_condition($new_condition['type'], $new_condition['value'])){
			$_REQUEST['conditions'] = get_request('conditions',array());
			if(!str_in_array($new_condition, $_REQUEST['conditions']))
				array_push($_REQUEST['conditions'],$new_condition);

			unset($_REQUEST['new_condition']);
		}
	}
	else if(inarr_isset(array('del_condition','g_conditionid'))){
		$_REQUEST['conditions'] = get_request('conditions',array());
		foreach($_REQUEST['g_conditionid'] as $condition){
			unset($_REQUEST['conditions'][$condition]);
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

		foreach($_REQUEST['g_opconditionid'] as $condition){
			unset($new_operation['opconditions'][$condition]);
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
		foreach($_REQUEST['g_operationid'] as $condition){
			unset($_REQUEST['operations'][$condition]);
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

		$go_result = CAction::delete($_REQUEST['g_actionid']);
		show_messages($go_result, S_SELECTED_ACTIONS_DELETED, S_CANNOT_DELETE_SELECTED_ACTIONS);
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
	$form->cleanItems();

	$form->addVar('eventsource', $_REQUEST['eventsource']);
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CButton('form', S_CREATE_ACTION));
	}
	$action_wdgt->addPageHeader(S_CONFIGURATION_OF_ACTIONS_BIG, $form);

	if(isset($_REQUEST['form'])){
		$frmAction = new CForm('actionconf.php', 'post');
		$frmAction->setName(S_ACTION);

		$frmAction->addVar('form', get_request('form', 1));

		$action = null;
		if(isset($_REQUEST['actionid'])){
			$options = array(
				'actionids' => $_REQUEST['actionid'],
				'select_operations' => API_OUTPUT_EXTEND,
				'select_conditions' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND
			);
			$actions = CAction::get($options);
			$action = reset($actions);

			$frmAction->addVar('actionid', $_REQUEST['actionid']);
		}

		$left_tab = new CTable();
		$left_tab->setCellPadding(3);
		$left_tab->setCellSpacing(3);

// ACTION FORM {{{
		$tblAct = new CTable(null, 'formElementTable');

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$name = $action['name'];
			$eventsource = $action['eventsource'];
			$esc_period	= $action['esc_period'];
			$status	= $action['status'];
			$def_shortdata = $action['def_shortdata'];
			$def_longdata = $action['def_longdata'];
			$recovery_msg = $action['recovery_msg'];
			$r_shortdata = $action['r_shortdata'];
			$r_longdata	= $action['r_longdata'];

			if($esc_period) $_REQUEST['escalation'] = 1;
		}
		else{
			if(isset($_REQUEST['escalation']) && (0 == $_REQUEST['esc_period']))
				$_REQUEST['esc_period'] = 3600;

			$name		= get_request('name');
			$eventsource	= get_request('eventsource');
			$esc_period	= get_request('esc_period',0);
			$status		= get_request('status');
			$recovery_msg	= get_request('recovery_msg',0);
			$r_shortdata	= get_request('r_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
			$r_longdata	= get_request('r_longdata', ACTION_DEFAULT_MSG_TRIGGER);

			if(!$esc_period) unset($_REQUEST['escalation']);

			if (isset($_REQUEST['actionid']) && isset($_REQUEST['form_refresh'])) {
				$def_shortdata = get_request('def_shortdata');
				$def_longdata = get_request('def_longdata');
			}
			else {
				if ($eventsource == EVENT_SOURCE_TRIGGERS) {
					$def_shortdata = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
					$def_longdata = get_request('def_longdata', ACTION_DEFAULT_MSG_TRIGGER);

					if ((strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_AUTOREG) == 0 && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_AUTOREG) == 0)
							|| (strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_DISCOVERY) == 0 && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_DISCOVERY) == 0))
					{
						$def_shortdata = ACTION_DEFAULT_SUBJ_TRIGGER;
						$def_longdata = ACTION_DEFAULT_MSG_TRIGGER;
					}
				}
				elseif ($eventsource == EVENT_SOURCE_DISCOVERY) {
					$def_shortdata = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_DISCOVERY);
					$def_longdata = get_request('def_longdata', ACTION_DEFAULT_MSG_DISCOVERY);

					if ((strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_AUTOREG) == 0  && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_AUTOREG) == 0)
							|| (strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_TRIGGER) == 0 && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_TRIGGER) == 0))
					{
						$def_shortdata = ACTION_DEFAULT_SUBJ_DISCOVERY;
						$def_longdata = ACTION_DEFAULT_MSG_DISCOVERY;
					}
				}
				elseif ($eventsource == EVENT_SOURCE_AUTO_REGISTRATION) {
					$def_shortdata = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_AUTOREG);
					$def_longdata = get_request('def_longdata', ACTION_DEFAULT_MSG_AUTOREG);

					if ((strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_DISCOVERY) == 0 && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_DISCOVERY) == 0)
							|| (strcmp($def_shortdata, ACTION_DEFAULT_SUBJ_TRIGGER) == 0 && strcmp(str_replace("\r", '', $def_longdata), ACTION_DEFAULT_MSG_TRIGGER) == 0)) {
						$def_shortdata = ACTION_DEFAULT_SUBJ_AUTOREG;
						$def_longdata = ACTION_DEFAULT_MSG_AUTOREG;
					}
				}
			}
		}

		$tblAct->addRow(array(S_NAME, new CTextBox('name', $name, 50)));

		$cmbSource =  new CComboBox('eventsource', $eventsource, 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, S_TRIGGERS);
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$cmbSource->addItem(EVENT_SOURCE_AUTO_REGISTRATION, S_AUTO_REGISTRATION);
		$tblAct->addRow(array(S_EVENT_SOURCE, $cmbSource));


		if(EVENT_SOURCE_TRIGGERS == $eventsource){
			$tblAct->addRow(array(S_ENABLE_ESCALATIONS, new CCheckBox('escalation',isset($_REQUEST['escalation']),'javascript: submit();',1)));

			if(isset($_REQUEST['escalation'])){
				$tblAct->addRow(array(S_PERIOD.' ('.S_SECONDS_SMALL.')', array(new CNumericBox('esc_period', $esc_period, 6, 'no'), '['.S_MIN_SMALL.' 60]')));
			}
			else{
				$tblAct->addItem(new CVar('esc_period',$esc_period));
			}
		}
		else{
			$tblAct->addItem(new CVar('esc_period',$esc_period));
		}

		if(!isset($_REQUEST['escalation'])){
			unset($_REQUEST['new_opcondition']);
		}

		$tblAct->addRow(array(S_DEFAULT_SUBJECT, new CTextBox('def_shortdata', $def_shortdata, 50)));
		$tblAct->addRow(array(S_DEFAULT_MESSAGE, new CTextArea('def_longdata', $def_longdata,50,5)));

		if(EVENT_SOURCE_TRIGGERS == $eventsource){
			$tblAct->addRow(array(S_RECOVERY_MESSAGE, new CCheckBox('recovery_msg',$recovery_msg,'javascript: submit();',1)));
			if($recovery_msg){
				$tblAct->addRow(array(S_RECOVERY_SUBJECT, new CTextBox('r_shortdata', $r_shortdata, 50)));
				$tblAct->addRow(array(S_RECOVERY_MESSAGE, new CTextArea('r_longdata', $r_longdata,50,5)));
			}
			else{
				$tblAct->addItem(new CVar('r_shortdata', $r_shortdata));
				$tblAct->addItem(new CVar('r_longdata', $r_longdata));
			}
		}
		else{
			unset($_REQUEST['recovery_msg']);
		}

		$cmbStatus = new CComboBox('status',$status);
		$cmbStatus->addItem(ACTION_STATUS_ENABLED,S_ENABLED);
		$cmbStatus->addItem(ACTION_STATUS_DISABLED,S_DISABLED);
		$tblAct->addRow(array(S_STATUS, $cmbStatus));

		$footer = array(new CButton('save',S_SAVE));
		if(isset($_REQUEST['actionid'])){
			$footer[] = new CButton('clone',S_CLONE);
			$footer[] = new CButtonDelete(S_DELETE_SELECTED_ACTION_Q,
				url_param('form').url_param('eventsource').
				url_param('actionid')
			);
		}
		$footer[] = new CButtonCancel(url_param('actiontype'));

		$left_tab->addRow(new CFormElement(S_ACTION, $tblAct, $footer));
// }}} ACTION_FORM


// CONDITIONS FORM {{{
		$tblCond = new CTable(null, 'formElementTable');


		$conditions	= get_request('conditions', array());

		if (($_REQUEST['eventsource'] == EVENT_SOURCE_TRIGGERS) && !isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])) {
			$conditions = array(
				array(
					'type' => CONDITION_TYPE_TRIGGER_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => TRIGGER_VALUE_TRUE,
				),
				array(
					'type' => CONDITION_TYPE_MAINTENANCE,
					'operator' => CONDITION_OPERATOR_NOT_IN,
					'value' => '',
				),
			);
		}

		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource = $action['eventsource'];
			$evaltype = $action['evaltype'];

			$conditions = $action['conditions'];
			foreach($conditions as $acrow => &$condition_data){
				$condition_data['type'] = $condition_data['conditiontype'];
			}
			unset($condition_data);

		}
		else{
			$evaltype = get_request('evaltype');
			$eventsource = get_request('eventsource');
		}

		$allowed_conditions = get_conditions_by_eventsource($eventsource);

// show CONDITION LIST
		zbx_rksort($conditions);

// group conditions by type
		$grouped_conditions = array();
		$cond_el = new CTable(S_NO_CONDITIONS_DEFINED);
		$i=0;

		foreach($conditions as $id => $condition){
			if(!isset($condition['type'])) $condition['type'] = 0;
			if(!isset($condition['operator'])) $condition['operator'] = 0;
			if(!isset($condition['value'])) $condition['value'] = 0;

			if(!str_in_array($condition['type'], $allowed_conditions)) continue;

			$label = chr(ord('A') + $i);
			$cond_el->addRow(array('('.$label.')',array(
				new CCheckBox('g_conditionid[]', 'no', null,$i),
				get_condition_desc($condition['type'], $condition['operator'], $condition['value']))
			));

			$tblCond->addItem(new CVar("conditions[$i][type]", $condition['type']));
			$tblCond->addItem(new CVar("conditions[$i][operator]", $condition['operator']));
			$tblCond->addItem(new CVar("conditions[$i][value]", $condition['value']));

			$grouped_conditions[$condition['type']][] = $label;

			$i++;
		}
		unset($conditions);

		$footer = array();
		if(!isset($_REQUEST['new_condition'])){
			$footer[] = new CButton('new_condition',S_NEW);
		}

		if($cond_el->ItemsCount() > 0){
			$footer[] = new CButton('del_condition',S_DELETE_SELECTED);
		}
		if($cond_el->ItemsCount() > 1){
			/* prepare condition calcuation type selector */
			switch($evaltype){
				case ACTION_EVAL_TYPE_AND: $group_op = $glog_op = S_AND; break;
				case ACTION_EVAL_TYPE_OR: $group_op = $glog_op = S_OR; break;
				default: $group_op = S_OR; $glog_op = S_AND; break;
			}

			foreach($grouped_conditions as $id => $condition)
				$grouped_conditions[$id] = '('.implode(' '.$group_op.' ', $condition).')';

			$grouped_conditions = implode(' '.$glog_op.' ', $grouped_conditions);

			$cmb_calc_type = new CComboBox('evaltype', $evaltype, 'submit()');
			$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
			$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
			$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);
			$tblCond->addRow(array(S_TYPE_OF_CALCULATION, array($cmb_calc_type, new CTextBox('preview', $grouped_conditions, 60,'yes'))));
			/* end of calculation type selector */
		}
		else{
			$tblCond->addItem(new CVar('evaltype', ACTION_EVAL_TYPE_AND_OR));
		}

		$tblCond->addRow(array(S_CONDITIONS, $cond_el));

		$left_tab->addRow(new CFormElement(S_ACTION_CONDITIONS, $tblCond, $footer));
// }}} CONDITIONS FORM


// NEW CONDITION FORM {{{
		if(isset($_REQUEST['new_condition'])){
			$tblNewCond = new CTable(null, 'formElementTable');

			if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
				$eventsource = $action['eventsource'];
				$evaltype = $action['evaltype'];
			}
			else{
				$evaltype = get_request('evaltype');
				$eventsource = get_request('eventsource');
			}

			$allowed_conditions = get_conditions_by_eventsource($eventsource);

			$new_condition = get_request('new_condition', array());
			$new_condition = array(
				'type' => isset($new_condition['type']) ? $new_condition['type'] : CONDITION_TYPE_TRIGGER_NAME,
				'operator' => isset($new_condition['operator']) ? $new_condition['operator'] : CONDITION_OPERATOR_LIKE,
				'value' => isset($new_condition['value']) ? $new_condition['value'] : '',
			);

			if(!str_in_array($new_condition['type'], $allowed_conditions))
				$new_condition['type'] = $allowed_conditions[0];

			$rowCondition = array();
			$cmbCondType = new CComboBox('new_condition[type]',$new_condition['type'],'submit()');
			foreach($allowed_conditions as $cond)
				$cmbCondType->addItem($cond, condition_type2str($cond));
			$rowCondition[] = $cmbCondType;


			$cmbCondOp = new CComboBox('new_condition[operator]');
			foreach(get_operators_by_conditiontype($new_condition['type']) as $op)
				$cmbCondOp->addItem($op, condition_operator2str($op));
			$rowCondition[] = $cmbCondOp;

			switch($new_condition['type']){
				case CONDITION_TYPE_HOST_GROUP:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('group','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=group&srctbl=host_group".
							"&srcfld1=groupid&srcfld2=name',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_HOST_TEMPLATE:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('host','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=host_templates".
							"&srcfld1=hostid&srcfld2=host',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_HOST:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('host','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=hosts".
							"&srcfld1=hostid&srcfld2=host',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_TRIGGER:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));

					$rowCondition[] = array(
						new CTextBox('trigger','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=trigger&srctbl=triggers".
							"&srcfld1=triggerid&srcfld2=description');",
							'T'));
					break;
				case CONDITION_TYPE_TRIGGER_NAME:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
				case CONDITION_TYPE_TRIGGER_VALUE:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE) as $tr_val)
						$cmbCondVal->addItem($tr_val, trigger_value2str($tr_val));
					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_TIME_PERIOD:
					$rowCondition[] = new CTextBox('new_condition[value]', "1-7,00:00-23:59", 40);
					break;
				case CONDITION_TYPE_TRIGGER_SEVERITY:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(TRIGGER_SEVERITY_INFORMATION,
						TRIGGER_SEVERITY_WARNING,
						TRIGGER_SEVERITY_AVERAGE,
						TRIGGER_SEVERITY_HIGH,
						TRIGGER_SEVERITY_DISASTER) as $id)
						$cmbCondVal->addItem($id,get_severity_description($id));
					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_MAINTENANCE:
					$rowCondition[] = new CCol(S_MAINTENANCE_SMALL);
					break;
				case CONDITION_TYPE_NODE:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('node','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=node&srctbl=nodes".
							"&srcfld1=nodeid&srcfld2=name',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_DRULE:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('drule','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=drule&srctbl=drules".
							"&srcfld1=druleid&srcfld2=name',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_DCHECK:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('dcheck','',50,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=dcheck&srctbl=dchecks".
							"&srcfld1=dcheckid&srcfld2=name',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_PROXY:
					$tblNewCond->addItem(new CVar('new_condition[value]','0'));
					$rowCondition[] = array(
						new CTextBox('proxy','',20,'yes'),
						new CButton('btn1',S_SELECT,
							"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
							"&dstfld1=new_condition%5Bvalue%5D&dstfld2=proxy&srctbl=proxies".
							"&srcfld1=hostid&srcfld2=host',450,450);",
							'T'));
					break;
				case CONDITION_TYPE_DHOST_IP:
					$rowCondition[] = new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1', 50);
					break;
				case CONDITION_TYPE_DSERVICE_TYPE:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP,
						SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,SVC_AGENT,SVC_SNMPv1,SVC_SNMPv2,SVC_SNMPv3,
						SVC_ICMPPING) as $svc)
						$cmbCondVal->addItem($svc,discovery_check_type2str($svc));
					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DSERVICE_PORT:
					$rowCondition[] = new CTextBox('new_condition[value]', '0-1023,1024-49151', 40);
					break;
				case CONDITION_TYPE_DSTATUS:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER,
							DOBJECT_STATUS_LOST) as $stat)
						$cmbCondVal->addItem($stat,discovery_object_status2str($stat));
					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DOBJECT:
					$cmbCondVal = new CComboBox('new_condition[value]');
					foreach(array(EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE) as $object)
						$cmbCondVal->addItem($object, discovery_object2str($object));
					$rowCondition[] = $cmbCondVal;
					break;
				case CONDITION_TYPE_DUPTIME:
					$rowCondition[] = new CNumericBox('new_condition[value]','600',15);
					break;
				case CONDITION_TYPE_DVALUE:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
				case CONDITION_TYPE_APPLICATION:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
				case CONDITION_TYPE_HOST_NAME:
					$rowCondition[] = new CTextBox('new_condition[value]', "", 40);
					break;
			}

			$tblNewCond->addRow($rowCondition);

			$footer = array(new CButton('add_condition',S_ADD),new CButton('cancel_new_condition',S_CANCEL));
			$left_tab->addRow(new CFormElement(S_NEW_CONDITION, $tblNewCond, $footer));
		}
// }}} NEW CONDITION FORM


		$right_tab = new CTable();
		$right_tab->setCellPadding(3);
		$right_tab->setCellSpacing(3);

// ACTION OPERATIONS FORM {{{
		$tblOper = new CTableInfo(S_NO_OPERATIONS_DEFINED);

		$operations	= get_request('operations',array());
		if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
			$eventsource = $action['eventsource'];
			$evaltype	= $action['evaltype'];
			$esc_period	= $action['esc_period'];

			$operations	= $action['operations'];
			foreach($operations as $aorow => &$operation_data){
				if($db_opmtype = reset($operation_data['opmediatypes']))
					$operation_data['mediatypeid'] = $db_opmtype['mediatypeid'];
			}
			unset($operation_data);
		}
		else{
			$eventsource = get_request('eventsource');
			$evaltype = get_request('evaltype');
			$esc_period	= get_request('esc_period');
		}

		$esc_step_from = array();
		$objects_tmp = array();
		$objectids_tmp = array();
		foreach($operations as $key => $operation) {
			$esc_step_from[$key] = $operation['esc_step_from'];
			$objects_tmp[$key] = $operation['object'];
			$objectids_tmp[$key] = $operation['objectid'];
		}

		array_multisort($esc_step_from, SORT_ASC, SORT_NUMERIC, $objects_tmp, SORT_DESC, $objectids_tmp, SORT_ASC, $operations);

		$tblOper->setHeader(array(
			new CCheckBox('all_operations',null,'checkAll("'.S_ACTION.'","all_operations","g_operationid");'),
			isset($_REQUEST['escalation'])?S_STEPS:null,
			S_DETAILS,
			isset($_REQUEST['escalation'])?S_PERIOD.' ('.S_SEC_SMALL.')':null,
			isset($_REQUEST['escalation'])?S_DELAY:null,
			S_ACTION
			));

		$allowed_operations = get_operations_by_eventsource($eventsource);

		$delay = count_operations_delay($operations,$esc_period);
		foreach($operations as $id => $condition){
			if(!str_in_array($condition['operationtype'], $allowed_operations)) continue;

			if(!isset($condition['default_msg'])) $condition['default_msg'] = 0;
			if(!isset($condition['opconditions'])) $condition['opconditions'] = array();
			if(!isset($condition['mediatypeid'])) $condition['mediatypeid'] = 0;

			$oper_details = new CSpan(get_operation_desc(SHORT_DESCRITION, $condition));
			$oper_details->setHint(nl2br(get_operation_desc(LONG_DESCRITION, $condition)));

			$esc_steps_txt = null;
			$esc_period_txt = null;
			$esc_delay_txt = null;

			if($condition['esc_step_from'] < 1) $condition['esc_step_from'] = 1;

			if(isset($_REQUEST['escalation'])){
				$esc_steps_txt = $condition['esc_step_from'].' - '.$condition['esc_step_to'];
				/* Display N-N as N */
				$esc_steps_txt = ($condition['esc_step_from']==$condition['esc_step_to'])?
					$condition['esc_step_from']:$condition['esc_step_from'].' - '.$condition['esc_step_to'];

				$esc_period_txt = $condition['esc_period']?$condition['esc_period']:S_DEFAULT;
				$esc_delay_txt = $delay[$condition['esc_step_from']]?convert_units($delay[$condition['esc_step_from']],'uptime'):S_IMMEDIATELY;
			}

			$tblOper->addRow(array(
				new CCheckBox("g_operationid[]", 'no', null,$id),
				$esc_steps_txt,
				$oper_details,
				$esc_period_txt,
				$esc_delay_txt,
				new CButton('edit_operationid['.$id.']',S_EDIT)
			));

			$tblOper->addItem(new CVar('operations['.$id.'][operationtype]'	,$condition['operationtype']));
			$tblOper->addItem(new CVar('operations['.$id.'][object]'	,$condition['object']	));
			$tblOper->addItem(new CVar('operations['.$id.'][objectid]'	,$condition['objectid']));
			$tblOper->addItem(new CVar('operations['.$id.'][mediatypeid]'	,$condition['mediatypeid']));
			$tblOper->addItem(new CVar('operations['.$id.'][shortdata]'	,$condition['shortdata']));
			$tblOper->addItem(new CVar('operations['.$id.'][longdata]'	,$condition['longdata']));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_period]'	,$condition['esc_period']	));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_step_from]'	,$condition['esc_step_from']));
			$tblOper->addItem(new CVar('operations['.$id.'][esc_step_to]'	,$condition['esc_step_to']));
			$tblOper->addItem(new CVar('operations['.$id.'][default_msg]'	,$condition['default_msg']));
			$tblOper->addItem(new CVar('operations['.$id.'][evaltype]'	,$condition['evaltype']));

			foreach($condition['opconditions'] as $opcondid => $opcond){
				foreach($opcond as $field => $value)
					$tblOper->addItem(new CVar('operations['.$id.'][opconditions]['.$opcondid.']['.$field.']',$value));
			}
		}

		$footer = array();
		if(!isset($_REQUEST['new_operation'])){
			$footer[] = new CButton('new_operation',S_NEW);
		}
		if($tblOper->ItemsCount() > 0 ){
			$footer[] = new CButton('del_operation',S_DELETE_SELECTED);
		}

		$right_tab->addRow(new CFormElement(S_ACTION_OPERATIONS, $tblOper, $footer));
// }}} ACTION OPERATIONS FORM


// NEW OPERATION FORM {{{
		if(isset($_REQUEST['new_operation'])){
			$tblOper = new CTable(null, 'formElementTable');

			$operations	= get_request('operations', array());

			if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
				$eventsource = $action['eventsource'];
			}
			else{
				$eventsource = get_request('eventsource');
			}

			$allowed_operations = get_operations_by_eventsource($eventsource);

			/* init new_operation variable */
			$new_operation = get_request('new_operation', array());

			if(!is_array($new_operation)){
				$new_operation = array();
				$new_operation['default_msg'] = 1;
			}

			if(!isset($new_operation['operationtype']))	$new_operation['operationtype']	= OPERATION_TYPE_MESSAGE;
			if(!isset($new_operation['object']))		$new_operation['object']	= OPERATION_OBJECT_GROUP;
			if(!isset($new_operation['objectid']))		$new_operation['objectid']	= 0;
			if(!isset($new_operation['mediatypeid']))	$new_operation['mediatypeid']	= 0;
			if(!isset($new_operation['esc_step_from']))	$new_operation['esc_step_from'] = 1;
			if(!isset($new_operation['esc_step_to']))	$new_operation['esc_step_to'] = 1;
			if(!isset($new_operation['esc_period']))	$new_operation['esc_period'] = 0;
			if(!isset($new_operation['evaltype']))		$new_operation['evaltype']	= 0;
			if(!isset($new_operation['opconditions']))	$new_operation['opconditions'] = array();
			if(!isset($new_operation['default_msg']))	$new_operation['default_msg'] = 0;

			if ($new_operation['operationtype'] == OPERATION_TYPE_MESSAGE) {
				if ($eventsource == EVENT_SOURCE_TRIGGERS) {
					if(!isset($new_operation['shortdata'])) {
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_TRIGGER;
					}
					if(!isset($new_operation['longdata'])) {
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_TRIGGER;
					}

					if ($new_operation['longdata'] == '' || (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_AUTOREG) == 0 && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_AUTOREG) == 0)
							|| (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_DISCOVERY) == 0 && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_DISCOVERY) == 0))
					{
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_TRIGGER;
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_TRIGGER;
					}
				}
				elseif ($eventsource == EVENT_SOURCE_DISCOVERY) {
					if(!isset($new_operation['shortdata'])) {
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_DISCOVERY;
					}
					if(!isset($new_operation['longdata'])) {
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_DISCOVERY;
					}

					if ($new_operation['longdata'] == '' || (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_AUTOREG) == 0  && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_AUTOREG) == 0)
							|| (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_TRIGGER) == 0 && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_TRIGGER) == 0))
					{
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_DISCOVERY;
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_DISCOVERY;
					}
				}
				elseif ($eventsource == EVENT_SOURCE_AUTO_REGISTRATION) {
					if(!isset($new_operation['shortdata'])) {
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_AUTOREG;
					}
					if(!isset($new_operation['longdata'])) {
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_AUTOREG;
					}

					if ($new_operation['longdata'] == '' || (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_DISCOVERY) == 0 && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_DISCOVERY) == 0)
							|| (strcmp($new_operation['shortdata'], ACTION_DEFAULT_SUBJ_TRIGGER) == 0 && strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_TRIGGER) == 0))
					{
						$new_operation['shortdata'] = ACTION_DEFAULT_SUBJ_AUTOREG;
						$new_operation['longdata'] = ACTION_DEFAULT_MSG_AUTOREG;
					}
				}
			}
			elseif ($new_operation['operationtype'] == OPERATION_TYPE_COMMAND) {
				if (strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_TRIGGER) == 0
					|| strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_AUTOREG) == 0
					|| strcmp(str_replace("\r", '', $new_operation['longdata']), ACTION_DEFAULT_MSG_DISCOVERY) == 0
				) {
					$new_operation['longdata'] = '';
				}
			}

			$evaltype = $new_operation['evaltype'];

			$update_mode = false;
			if(isset($new_operation['id'])){
				$tblOper->addItem(new CVar('new_operation[id]', $new_operation['id']));
				$update_mode = true;
			}

			$tblNewOperation = new CTable();

			if(isset($_REQUEST['escalation'])){
				$tblStep = new CTable();

				$step_from = new CNumericBox('new_operation[esc_step_from]', $new_operation['esc_step_from'],4);
				$step_from->addAction('onchange','javascript:'.$step_from->getAttribute('onchange').' if(this.value == 0) this.value=1;');

				$tblStep->addRow(array(S_FROM, $step_from));
				$tblStep->addRow(array(
					S_TO,
					new CCol(array(
						new CNumericBox('new_operation[esc_step_to]', $new_operation['esc_step_to'], 4),
						' [0-' . S_INFINITY.']'))
				));

				$tblStep->addRow(array(
					S_PERIOD,
					new CCol(array(
						new CNumericBox('new_operation[esc_period]', $new_operation['esc_period'], 5),
						' ['.S_MIN_SMALL.' 60, 0-' . S_DEFAULT . ']'))
				));

				$tblNewOperation->addRow(array(S_STEP, $tblStep));
			}
			else{
				$tblOper->addItem(new CVar('new_operation[esc_period]', $new_operation['esc_period']));
				$tblOper->addItem(new CVar('new_operation[esc_step_from]', $new_operation['esc_step_from']));
				$tblOper->addItem(new CVar('new_operation[esc_step_to]', $new_operation['esc_step_to']));
				$tblOper->addItem(new CVar('new_operation[evaltype]', $new_operation['evaltype']));
			}

			$cmbOpType = new CComboBox('new_operation[operationtype]', $new_operation['operationtype'], 'submit()');
			foreach($allowed_operations as $oper)
				$cmbOpType->addItem($oper, operation_type2str($oper));

			$tblNewOperation->addRow(array(S_OPERATION_TYPE, $cmbOpType));

			switch($new_operation['operationtype']) {
				case OPERATION_TYPE_MESSAGE:
					if($new_operation['object'] == OPERATION_OBJECT_GROUP) {
						$object_srctbl = 'usrgrp';
						$object_srcfld1 = 'usrgrpid';
						$object_name = CUserGroup::get(array('usrgrpids' => $new_operation['objectid'], 'output' => API_OUTPUT_EXTEND));
						$object_name = reset($object_name);
						$display_name = 'name';
					}
					else {
						$object_srctbl = 'users';
						$object_srcfld1 = 'userid';
						$object_name = CUser::get(array('userids' => $new_operation['objectid'], 'output' => API_OUTPUT_EXTEND));
						$object_name = reset($object_name);
						$display_name = 'alias';
					}

					$tblOper->addItem(new CVar('new_operation[objectid]', $new_operation['objectid']));

					if($object_name) $object_name = $object_name[$display_name];

					$cmbObject = new CComboBox('new_operation[object]', $new_operation['object'], 'submit()');
					$cmbObject->addItem(OPERATION_OBJECT_USER, S_SINGLE_USER);
					$cmbObject->addItem(OPERATION_OBJECT_GROUP, S_USER_GROUP);

					$tblNewOperation->addRow(array(S_SEND_MESSAGE_TO, array(
						$cmbObject,
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object', S_SELECT,
							'return PopUp("popup.php?dstfrm=' . S_ACTION .
								'&dstfld1=new_operation%5Bobjectid%5D' .
								'&dstfld2=object_name' .
								'&srctbl=' . $object_srctbl .
								'&srcfld1=' . $object_srcfld1 .
								'&srcfld2=' . $display_name .
								'&submit=1' .
								'",450,450)', 'T')
					)));

					$cmbMediaType = new CComboBox('new_operation[mediatypeid]', $new_operation['mediatypeid'], 'submit()');
					$cmbMediaType->addItem(0, S_MINUS_ALL_MINUS);

					if(OPERATION_OBJECT_USER == $new_operation['object']){
						$sql = 'SELECT DISTINCT mt.mediatypeid,mt.description,m.userid ' .
								' FROM media_type mt, media m ' .
								' WHERE ' . DBin_node('mt.mediatypeid') .
									' AND m.mediatypeid=mt.mediatypeid ' .
									' AND m.userid=' . $new_operation['objectid'] .
									' AND m.active=' . ACTION_STATUS_ENABLED .
								' ORDER BY mt.description';
						$db_mediatypes = DBselect($sql);
						while($db_mediatype = DBfetch($db_mediatypes)){
							$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
						}
					}
					else{
						$sql = 'SELECT mt.mediatypeid, mt.description' .
								' FROM media_type mt ' .
								' WHERE ' . DBin_node('mt.mediatypeid') .
								' ORDER BY mt.description';
						$db_mediatypes = DBselect($sql);
						while($db_mediatype = DBfetch($db_mediatypes)){
							$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
						}
					}
					$tblNewOperation->addRow(array(S_SEND_ONLY_TO, $cmbMediaType));

					if(OPERATION_OBJECT_USER == $new_operation['object']){
						$media_table = new CTable(S_NO_MEDIA_DEFINED,'tablestripped');

						$sql = 'SELECT mt.description,m.sendto,m.period,m.severity ' .
								' FROM media_type mt,media m ' .
								' WHERE ' . DBin_node('mt.mediatypeid') .
									' AND mt.mediatypeid=m.mediatypeid ' .
									' AND m.userid=' . $new_operation['objectid'] .
									($new_operation['mediatypeid'] ? ' AND m.mediatypeid=' . $new_operation['mediatypeid'] : '') .
									' AND m.active=' . ACTION_STATUS_ENABLED .
								' ORDER BY mt.description,m.sendto';
						$db_medias = DBselect($sql);
						while($db_media = DBfetch($db_medias)) {
							$media_table->addRow(array(
								new CSpan($db_media['description'], 'nowrap'),
								new CSpan($db_media['sendto'], 'nowrap'),
								new CSpan($db_media['period'], 'nowrap'),
								media_severity2str($db_media['severity'])
							));
						}

						$tblNewOperation->addRow(array(S_USER_MEDIAS, $media_table));
					}
					$tblNewOperation->addRow(array(S_DEFAULT_MESSAGE, new CCheckBox('new_operation[default_msg]', $new_operation['default_msg'], 'javascript: submit();', 1)));

					if(!$new_operation['default_msg']){
						$tblNewOperation->addRow(array(S_SUBJECT, new CTextBox('new_operation[shortdata]', $new_operation['shortdata'], 77)));
						$tblNewOperation->addRow(array(S_MESSAGE, new CTextArea('new_operation[longdata]', $new_operation['longdata'], 77, 7)));
					}
					else{
						$tblOper->addItem(new CVar('new_operation[shortdata]', $new_operation['shortdata']));
						$tblOper->addItem(new CVar('new_operation[longdata]', $new_operation['longdata']));
					}
					break;
				case OPERATION_TYPE_COMMAND:
					$tblOper->addItem(new CVar('new_operation[object]', 0));
					$tblOper->addItem(new CVar('new_operation[objectid]', 0));
					$tblOper->addItem(new CVar('new_operation[shortdata]', ''));

					$tblNewOperation->addRow(array(S_REMOTE_COMMAND,
						new CTextArea('new_operation[longdata]', $new_operation['longdata'], 77, 7)));

					$frmAction->addVar('new_operation[default_msg]', $new_operation['default_msg']);
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					$tblOper->addItem(new CVar('new_operation[object]', 0));
					$tblOper->addItem(new CVar('new_operation[objectid]', 0));
					$tblOper->addItem(new CVar('new_operation[shortdata]', ''));
					$tblOper->addItem(new CVar('new_operation[longdata]', ''));
					$frmAction->addVar('new_operation[default_msg]', $new_operation['default_msg']);
					break;
				case OPERATION_TYPE_GROUP_ADD:
				case OPERATION_TYPE_GROUP_REMOVE:
					$tblOper->addItem(new CVar('new_operation[object]', 0));
					$tblOper->addItem(new CVar('new_operation[objectid]', $new_operation['objectid']));
					$tblOper->addItem(new CVar('new_operation[shortdata]', ''));
					$tblOper->addItem(new CVar('new_operation[longdata]', ''));

					if($object_name = DBfetch(DBselect('select name FROM groups WHERE groupid=' . $new_operation['objectid']))) {
						$object_name = $object_name['name'];
					}
					$tblNewOperation->addRow(array(S_GROUP, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object', S_SELECT,
							'return PopUp("popup.php?dstfrm=' . S_ACTION .
								'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name' .
								'&srctbl=host_group&srcfld1=groupid&srcfld2=name' .
								'",450,450)','T')
					)));
					$frmAction->addVar('new_operation[default_msg]', $new_operation['default_msg']);
					break;
				case OPERATION_TYPE_TEMPLATE_ADD:
				case OPERATION_TYPE_TEMPLATE_REMOVE:
					$tblOper->addItem(new CVar('new_operation[object]', 0));
					$tblOper->addItem(new CVar('new_operation[objectid]', $new_operation['objectid']));
					$tblOper->addItem(new CVar('new_operation[shortdata]', ''));
					$tblOper->addItem(new CVar('new_operation[longdata]', ''));

					if($object_name = DBfetch(DBselect('SELECT host FROM hosts ' .
							' WHERE status=' . HOST_STATUS_TEMPLATE . ' AND hostid=' . $new_operation['objectid']))){
						$object_name = $object_name['host'];
					}
					$tblNewOperation->addRow(array(S_TEMPLATE, array(
						new CTextBox('object_name', $object_name, 40, 'yes'),
						new CButton('select_object', S_SELECT,
								'return PopUp("popup.php?dstfrm=' . S_ACTION .
										'&dstfld1=new_operation%5Bobjectid%5D&dstfld2=object_name' .
										'&srctbl=host_templates&srcfld1=hostid&srcfld2=host' .
										'",450,450)','T')
					)));
					$frmAction->addVar('new_operation[default_msg]', $new_operation['default_msg']);
					break;
			}

			// new Operation conditions
			if(isset($_REQUEST['escalation'])){
				$tblCond = new CTable();

				$opconditions = $new_operation['opconditions'];
				$allowed_opconditions = get_opconditions_by_eventsource($eventsource);

				zbx_rksort($opconditions);

				$grouped_opconditions = array();
				$cond_el = new CTable(S_NO_CONDITIONS_DEFINED);
				$i = 0;

				foreach($opconditions as $condition){
					if(!isset($condition['conditiontype'])) $condition['conditiontype'] = 0;
					if(!isset($condition['operator'])) $condition['operator'] = 0;
					if(!isset($condition['value'])) $condition['value'] = 0;

					if(!str_in_array($condition['conditiontype'], $allowed_opconditions)) continue;

					$label = chr(ord('A') + $i);
					$cond_el->addRow(array('(' . $label . ')', array(
						new CCheckBox('g_opconditionid[]', 'no', null, $i),
						get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']))
					));

					$tblCond->addItem(new CVar("new_operation[opconditions][$i][conditiontype]", $condition["conditiontype"]));
					$tblCond->addItem(new CVar("new_operation[opconditions][$i][operator]", $condition["operator"]));
					$tblCond->addItem(new CVar("new_operation[opconditions][$i][value]", $condition["value"]));

					$grouped_opconditions[$condition["conditiontype"]][] = $label;

					$i++;
				}

				$cond_buttons = array();

				if(!isset($_REQUEST['new_opcondition'])) {
					$cond_buttons[] = new CButton('new_opcondition', S_NEW);
				}

				if($cond_el->ItemsCount() > 0){
					$cond_buttons[] = new CButton('del_opcondition', S_DELETE_SELECTED);
				}

				if($cond_el->ItemsCount() > 1){
					/* prepare opcondition calcuation type selector */
					switch($evaltype) {
						case ACTION_EVAL_TYPE_AND:
							$group_op = $glog_op = S_AND;
							break;
						case ACTION_EVAL_TYPE_OR:
							$group_op = $glog_op = S_OR;
							break;
						default:
							$group_op = S_OR;
							$glog_op = S_AND;
							break;
					}

					foreach($grouped_opconditions as $id => $condition)
						$grouped_opconditions[$id] = '(' . implode(' ' . $group_op . ' ', $condition) . ')';

					$grouped_opconditions = implode(' ' . $glog_op . ' ', $grouped_opconditions);

					$cmb_calc_type = new CComboBox('new_operation[evaltype]', $evaltype, 'submit()');
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
					$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);

					$tblNewOperation->addRow(array(
						S_TYPE_OF_CALCULATION,
						array($cmb_calc_type, new CTextBox('preview', $grouped_opconditions, 60, 'yes'))
					));
				}
				else{
					$tblCond->addItem(new CVar('new_operation[evaltype]', ACTION_EVAL_TYPE_AND_OR));
				}

				$tblCond->addRow($cond_el);
				$tblCond->addRow(new CCol($cond_buttons));

				$tblNewOperation->addRow(array(S_CONDITIONS, $tblCond));
				unset($grouped_opconditions, $cond_el, $cond_buttons, $tblCond);
			}
			$tblOper->addRow($tblNewOperation);


			$footer = array(
				new CButton('add_operation', $update_mode ? S_SAVE : S_ADD),
				new CButton('cancel_new_operation', S_CANCEL)
			);
			$right_tab->addRow(new CFormElement(S_EDIT_OPERATION, $tblOper, $footer));
		}
// }}} NEW OPERATION FORM


// NEW OPERATION CONDITION {{{
		if(isset($_REQUEST['new_opcondition'])){
			$tblCond = new CTable(null, 'formElementTable');

			if(isset($_REQUEST['actionid']) && !isset($_REQUEST['form_refresh'])){
				$eventsource = $action['eventsource'];
				$evaltype = $action['evaltype'];
			}
			else{
				$evaltype = get_request('evaltype');
				$eventsource = get_request('eventsource');
			}

			$allowed_conditions = get_opconditions_by_eventsource($eventsource);
			$new_opcondition = get_request('new_opcondition', array());
			if(!is_array($new_opcondition))	$new_opcondition = array();

			if(!isset($new_opcondition['conditiontype'])) $new_opcondition['conditiontype']	= CONDITION_TYPE_EVENT_ACKNOWLEDGED;
			if(!isset($new_opcondition['operator'])) $new_opcondition['operator'] = CONDITION_OPERATOR_LIKE;
			if(!isset($new_opcondition['value'])) $new_opcondition['value'] = 0;

			if(!str_in_array($new_opcondition['conditiontype'], $allowed_conditions))
				$new_opcondition['conditiontype'] = $allowed_conditions[0];

			$rowCondition = array();

			$cmbCondType = new CComboBox('new_opcondition[conditiontype]',$new_opcondition['conditiontype'],'submit()');
			foreach($allowed_conditions as $cond)
				$cmbCondType->addItem($cond, condition_type2str($cond));
			array_push($rowCondition,$cmbCondType);

			$cmbCondOp = new CComboBox('new_opcondition[operator]');
			foreach(get_operators_by_conditiontype($new_opcondition['conditiontype']) as $op)
				$cmbCondOp->addItem($op, condition_operator2str($op));
			array_push($rowCondition,$cmbCondOp);

			switch($new_opcondition['conditiontype']){
				case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
					$cmbCondVal = new CComboBox('new_opcondition[value]',$new_opcondition['value']);
					$cmbCondVal->addItem(0, S_NOT_ACK);
					$cmbCondVal->addItem(1, S_ACK);
					$rowCondition[] = $cmbCondVal;
					break;
			}
			$tblCond->addRow($rowCondition);

			$footer = array(
				new CButton('add_opcondition', S_ADD),
				new CButton('cancel_new_opcondition', S_CANCEL)
			);
			$right_tab->addRow(new CFormElement(S_NEW.SPACE.S_OPERATION_CONDITION, $tblCond, $footer));
		}
// }}} NEW OPERATION CONDITION

		$td_l = new CCol($left_tab);
		$td_l->setAttribute('valign','top');

		$td_r = new CCol($right_tab);
		$td_r->setAttribute('valign','top');

		$outer_table = new CTable();
		$outer_table->addRow(array($td_l, $td_r));
		$frmAction->additem($outer_table);

		show_messages();

		$action_wdgt->addItem($frmAction);
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

// table
		$form = new CForm();
		$form->setName('actions');

		$tblActions = new CTableInfo(S_NO_ACTIONS_DEFINED);
		$tblActions->setHeader(array(
			new CCheckBox('all_items',null,"checkAll('".$form->getName()."','all_items','g_actionid');"),
			make_sorting_header(S_NAME, 'name'),
			S_CONDITIONS,
			S_OPERATIONS,
			make_sorting_header(S_STATUS, 'status')
		));


		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => array(
				'eventsource' => array($_REQUEST['eventsource'])
			),
			'select_conditions' => API_OUTPUT_EXTEND,
			'select_operations' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$actions = CAction::get($options);

// sorting && paging
		order_result($actions, $sortfield, $sortorder);
		$paging = getPagingLine($actions);
//-------

		foreach($actions as $anum => $action){

			$conditions = array();
			order_result($action['conditions'], 'conditiontype', ZBX_SORT_DOWN);
			foreach($action['conditions'] as $cnum => $condition){
				$conditions[] = array(
					get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']),
					BR()
				);
			}

			$operations=array();
			order_result($action['operations'], 'operationtype', ZBX_SORT_DOWN);
			foreach($action['operations'] as $onum => $operation){
				$operations[] = array(
					get_operation_desc(SHORT_DESCRITION, $operation),
					BR()
				);
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

		$goButton = new CButton('goButton',S_GO);
		$goButton->setAttribute('id','goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "g_actionid";');

		$footer = get_table_header(array($goBox, $goButton));


		$form->addItem(array($paging, $tblActions, $paging, $footer));
		$action_wdgt->addItem($form);
	}

	$action_wdgt->show();

?>
<?php

include_once('include/page_footer.php');

?>
