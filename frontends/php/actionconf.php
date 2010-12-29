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
require_once('include/forms.inc.php');

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
	$form = new CForm('get');
	$form->cleanItems();
	$form->addVar('eventsource', $_REQUEST['eventsource']);
	if(!isset($_REQUEST['form'])){
		$form->addItem(new CSubmit('form', S_CREATE_ACTION));
	}
	$action_wdgt->addPageHeader(S_CONFIGURATION_OF_ACTIONS_BIG, $form);

	if(isset($_REQUEST['form'])){
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

			foreach($action['operations'] as $aorow => &$operation_data){
				if($db_opmtype = reset($operation_data['opmediatypes']))
					$operation_data['mediatypeid'] = $db_opmtype['mediatypeid'];
			}
			unset($operation_data);
		}

		if(isset($action['actionid']) && !isset($_REQUEST['form_refresh'])){
		}
		else{
			if(isset($_REQUEST['escalation']) && (0 == $_REQUEST['esc_period']))
				$_REQUEST['esc_period'] = 3600;

			$action['name']			= get_request('name');
			$action['eventsource']	= get_request('eventsource');
			$action['evaltype']		= get_request('evaltype');
			$action['esc_period']	= get_request('esc_period',0);
			$action['status']		= get_request('status', 1);
			$action['def_shortdata']= get_request('def_shortdata', ACTION_DEFAULT_SUBJ);
			$action['def_longdata']	= get_request('def_longdata', ACTION_DEFAULT_MSG);
			$action['recovery_msg']	= get_request('recovery_msg',0);
			$action['r_shortdata']	= get_request('r_shortdata', ACTION_DEFAULT_SUBJ);
			$action['r_longdata']	= get_request('r_longdata', ACTION_DEFAULT_MSG);

			$action['conditions']	= get_request('conditions',array());
			$action['operations']	= get_request('operations',array());
		}

		$actionForm = new CGetForm();
		$action_wdgt->addItem($actionForm->render('action.edit', $action));

/*
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

// init new_operation variable
			$new_operation = get_request('new_operation', array());

			if(!is_array($new_operation)){
				$new_operation = array();
				$new_operation['default_msg'] = 1;
			}
			if(!isset($new_operation['operationtype']))	$new_operation['operationtype']	= OPERATION_TYPE_MESSAGE;
			if(!isset($new_operation['object']))		$new_operation['object']	= OPERATION_OBJECT_GROUP;
			if(!isset($new_operation['objectid']))		$new_operation['objectid']	= 0;
			if(!isset($new_operation['mediatypeid']))	$new_operation['mediatypeid']	= 0;
			if(!isset($new_operation['shortdata']))		$new_operation['shortdata']	= ACTION_DEFAULT_SUBJ;
			if(!isset($new_operation['longdata']))		$new_operation['longdata']	= ACTION_DEFAULT_MSG;
			if(!isset($new_operation['esc_step_from']))	$new_operation['esc_step_from'] = 1;
			if(!isset($new_operation['esc_step_to']))	$new_operation['esc_step_to'] = 1;
			if(!isset($new_operation['esc_period']))	$new_operation['esc_period'] = 0;
			if(!isset($new_operation['evaltype']))		$new_operation['evaltype']	= 0;
			if(!isset($new_operation['opconditions']))	$new_operation['opconditions'] = array();
			if(!isset($new_operation['default_msg']))	$new_operation['default_msg'] = 0;


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
					break;
				case OPERATION_TYPE_HOST_ADD:
				case OPERATION_TYPE_HOST_REMOVE:
				case OPERATION_TYPE_HOST_ENABLE:
				case OPERATION_TYPE_HOST_DISABLE:
					$tblOper->addItem(new CVar('new_operation[object]', 0));
					$tblOper->addItem(new CVar('new_operation[objectid]', 0));
					$tblOper->addItem(new CVar('new_operation[shortdata]', ''));
					$tblOper->addItem(new CVar('new_operation[longdata]', ''));
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
					$cond_buttons[] = new CSubmit('new_opcondition', S_NEW);
				}

				if($cond_el->ItemsCount() > 0){
					$cond_buttons[] = new CSubmit('del_opcondition', S_DELETE_SELECTED);
				}

				if($cond_el->ItemsCount() > 1){
// prepare opcondition calcuation type selector
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
				new CSubmit('add_operation', $update_mode ? S_SAVE : S_ADD),
				new CSubmit('cancel_new_operation', S_CANCEL)
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
				new CSubmit('add_opcondition', S_ADD),
				new CSubmit('cancel_new_opcondition', S_CANCEL)
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
//*/
		show_messages();

//		$action_wdgt->addItem($frmAction);
	}
	else{
		$form = new CForm('get');

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

		$goButton = new CSubmit('goButton',S_GO);
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
