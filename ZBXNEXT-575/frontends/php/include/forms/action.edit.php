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
// include JS + templates
require_once('include/templates/action.js.php');
?>
<?php
// TODO
	$data = $data;
//SDII($data);
	$inputLength = 60;

	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh'])) $divTabs->setSelected(0);

	$frmAction = new CForm();
	$frmAction->setName('web.action.edit.php');
	$frmAction->addVar('form', get_request('form', 1));

	$from_rfr = get_request('form_refresh',0);
	$frmAction->addVar('form_refresh', $from_rfr+1);

	if(isset($data['actionid'])) $frmAction->addVar('actionid', $data['actionid']);
	$frmAction->addVar('eventsource', $data['eventsource']);

// ACTION FORM {{{
	$actionList = new CFormList('actionlist');

	$actionList->addRow(S_NAME, new CTextBox('name', $data['name'], $inputLength));

	if(EVENT_SOURCE_TRIGGERS == $data['eventsource']){
		$actionList->addRow(S_PERIOD.' ('.S_MIN_SMALL.' 60 '.S_SECONDS_SMALL.')', array(new CNumericBox('esc_period', $data['esc_period'], 6, 'no'), ' ('.S_SECONDS_SMALL.')'));
	}
	else{
		$frmAction->addVar('esc_period',$data['esc_period']);
	}


	$actionList->addRow(S_DEFAULT_SUBJECT, new CTextBox('def_shortdata', $data['def_shortdata'], $inputLength));
	$actionList->addRow(S_DEFAULT_MESSAGE, new CTextArea('def_longdata', $data['def_longdata'], $inputLength, 5));

	if(EVENT_SOURCE_TRIGGERS == $data['eventsource']){
		$actionList->addRow(S_RECOVERY_MESSAGE, new CCheckBox('recovery_msg',$data['recovery_msg'],'javascript: submit();',1));
		if($data['recovery_msg']){
			$actionList->addRow(S_RECOVERY_SUBJECT, new CTextBox('r_shortdata', $data['r_shortdata'], $inputLength));
			$actionList->addRow(S_RECOVERY_MESSAGE, new CTextArea('r_longdata', $data['r_longdata'], $inputLength, 5));
		}
		else{
			$frmAction->addVar('r_shortdata', $data['r_shortdata']);
			$frmAction->addVar('r_longdata', $data['r_longdata']);
		}
	}

	$actionList->addRow(S_ENABLED, new CCheckBox('status',!$data['status'], null, 0));

	$divTabs->addTab('actionTab', S_ACTION, $actionList);
// }}} ACTION_FORM

// CONDITIONS FORM {{{
	$conditionList = new CFormList('conditionlist');
	$allowedConditions = get_conditions_by_eventsource($data['eventsource']);

	zbx_rksort($data['conditions']);

// group conditions by type
	$condElements = new CTable(S_NO_CONDITIONS_DEFINED);

	$i=0;
	$grouped_conditions = array();
	foreach($data['conditions'] as $id => $condition){
		if(!isset($condition['conditiontype'])) $condition['conditiontype'] = 0;
		if(!isset($condition['operator'])) $condition['operator'] = 0;
		if(!isset($condition['value'])) $condition['value'] = 0;

		if(!str_in_array($condition['conditiontype'], $allowedConditions)) continue;

		$label = chr(ord('A') + $i);
		$condElements->addRow(array('('.$label.')',array(
			new CCheckBox('g_conditionid[]', 'no', null,$i),
			get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']))
		));

		$frmAction->addVar('conditions['.$i.']', $condition);

		$grouped_conditions[$condition['conditiontype']][] = $label;

		$i++;
	}

	if($condElements->itemsCount() > 1){
// prepare condition calcuation type selector
		switch($data['evaltype']){
			case ACTION_EVAL_TYPE_AND:
				$group_op = $glog_op = S_AND;
			break;
			case ACTION_EVAL_TYPE_OR:
				$group_op = $glog_op = S_OR;
			break;
			default:
				$group_op = S_OR;
				$glog_op = S_AND;
		}

		foreach($grouped_conditions as $id => $condition)
			$grouped_conditions[$id] = '('.implode(' '.$group_op.' ', $condition).')';

		$grouped_conditions = implode(' '.$glog_op.' ', $grouped_conditions);

		$cmb_calc_type = new CComboBox('evaltype', $data['evaltype'], 'submit()');
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, S_AND_OR_BIG);
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, S_AND_BIG);
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, S_OR_BIG);
		$conditionList->addRow(S_TYPE_OF_CALCULATION, array($cmb_calc_type, new CSpan($grouped_conditions)));
	}
	else{
		$frmAction->addVar('evaltype', $data['evaltype']);
	}

	$removeCondition = null;
	if($condElements->ItemsCount() > 0){
		$removeCondition = new CSubmit('del_condition', S_DELETE_SELECTED, null, 'link_menu');
	}

	$conditionList->addRow(S_CONDITIONS, new CDiv(array($condElements, $removeCondition), 'objectgroup inlineblock border_dotted ui-corner-all'));

// NEW CONDITION

	$new_condition = get_request('new_condition', array());
	$new_condition = array(
		'conditiontype' => isset($new_condition['conditiontype']) ? $new_condition['conditiontype'] : CONDITION_TYPE_TRIGGER_NAME,
		'operator' => isset($new_condition['operator']) ? $new_condition['operator'] : CONDITION_OPERATOR_LIKE,
		'value' => isset($new_condition['value']) ? $new_condition['value'] : '',
	);

	if(!str_in_array($new_condition['conditiontype'], $allowedConditions))
		$new_condition['conditiontype'] = $allowedConditions[0];

	$rowCondition = array();
	$cmbCondType = new CComboBox('new_condition[conditiontype]',$new_condition['conditiontype'],'submit()');
	foreach($allowedConditions as $cond)
		$cmbCondType->addItem($cond, condition_type2str($cond));
	$rowCondition[] = $cmbCondType;


	$cmbCondOp = new CComboBox('new_condition[operator]');
	foreach(get_operators_by_conditiontype($new_condition['conditiontype']) as $op)
		$cmbCondOp->addItem($op, condition_operator2str($op));
	$rowCondition[] = $cmbCondOp;

	switch($new_condition['conditiontype']){
		case CONDITION_TYPE_HOST_GROUP:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('group','',40,'yes'),
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=group&srctbl=host_group".
					"&srcfld1=groupid&srcfld2=name',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('host','',40,'yes'),
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=host_templates".
					"&srcfld1=hostid&srcfld2=host',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_HOST:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('host','',40,'yes'),
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=hosts".
					"&srcfld1=hostid&srcfld2=host',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_TRIGGER:
			$conditionList->addItem(new CVar('new_condition[value]','0'));

			$rowCondition[] = array(
				new CTextBox('trigger','',40,'yes'),
				new CSubmit('btn1',S_SELECT,
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
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('node','',40,'yes'),
				new CButton('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=node&srctbl=nodes".
					"&srcfld1=nodeid&srcfld2=name',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_DRULE:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('drule','',40,'yes'),
				new CButton('btn1',S_SELECT,
					"return PopUp('popup.php?dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=drule&srctbl=drules".
					"&srcfld1=druleid&srcfld2=name',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_DCHECK:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('dcheck','',40,'yes'),
				new CButton('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=dcheck&srctbl=dchecks".
					"&srcfld1=dcheckid&srcfld2=name',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_PROXY:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('proxy','',40,'yes'),
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

	$newCond = new CDiv(
		array(new CDiv($rowCondition), new CSubmit('add_condition',S_ADD, null, 'link_menu')),
		'objectgroup inlineblock border_dotted ui-corner-all'
	);

	$conditionList->addRow(S_NEW_CONDITION, $newCond);

	$divTabs->addTab('conditionTab', S_CONDITIONS, $conditionList);
// }}} CONDITION FORM

// ACTION OPERATIONS FORM {{{
	$operationList = new CFormList('operationlist');

	$allowedOperations = get_operations_by_eventsource($data['eventsource']);

// sorting
	$esc_step_from = array();
	$esc_step_to = array();
	$esc_period = array();
	foreach($data['operations'] as $key => $operation) {
		$esc_step_from[$key] = $operation['esc_step_from'];
		$esc_step_to[$key] = $operation['esc_step_to'];
		$esc_period[$key] = $operation['esc_period'];
	}
	array_multisort($esc_step_from, SORT_ASC, SORT_NUMERIC, $esc_step_to, SORT_ASC, $esc_period, SORT_ASC, $data['operations']);
// --

	$tblOper = new CTable(S_NO_OPERATIONS_DEFINED, 'formElementTable');
	$tblOper->setHeader(array(
		new CCheckBox('all_operations',null,'checkAll("'.S_ACTION.'","all_operations","g_operationid");'),
		S_STEPS, S_DETAILS, S_PERIOD.' ('.S_SEC_SMALL.')', S_DELAY, S_ACTION
	));
//SDII($data['operations']);
	$delay = count_operations_delay($data['operations'],$data['esc_period']);
	foreach($data['operations'] as $id => $operation){
		if(!str_in_array($operation['operationtype'], $allowedOperations)) continue;
		if(!isset($operation['opconditions'])) $operation['opconditions'] = array();
		if(!isset($operation['mediatypeid'])) $operation['mediatypeid'] = 0;

		$opid = isset($operation['operationid']) ? $operation['operationid'] : $id;
		$operation['id'] = $opid;

		$oper_details = new CSpan(get_operation_desc(SHORT_DESCRITION, $operation));
		$oper_details->setHint(get_operation_desc(LONG_DESCRITION, $operation));

		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if($operation['esc_step_from'] < 1) $operation['esc_step_from'] = 1;

		$esc_steps_txt = $operation['esc_step_from'].' - '.$operation['esc_step_to'];
// Display N-N as N
		$esc_steps_txt = ($operation['esc_step_from']==$operation['esc_step_to'])?
			$operation['esc_step_from']:$operation['esc_step_from'].' - '.$operation['esc_step_to'];

		$esc_period_txt = $operation['esc_period']?$operation['esc_period']:S_DEFAULT;
		$esc_delay_txt = $delay[$operation['esc_step_from']]?convert_units($delay[$operation['esc_step_from']],'uptime'):S_IMMEDIATELY;

		$tblOper->addRow(array(
			new CCheckBox("g_operationid[]", 'no', null,$id),
			$esc_steps_txt,
			$oper_details,
			$esc_period_txt,
			$esc_delay_txt,
			new CSubmit('edit_operationid['.$opid.']',S_EDIT, null, 'link_menu')
		));

		$operation['opmessage_grp'] = zbx_toHash($operation['opmessage_grp'], 'usrgrpid');
		$operation['opmessage_usr'] = zbx_toHash($operation['opmessage_usr'], 'userid');
		$operation['opcommand_grp'] = zbx_toHash($operation['opcommand_grp'], 'opcommand_grpid');
		$operation['opcommand_hst'] = zbx_toHash($operation['opcommand_hst'], 'opcommand_hstid');

		$tblOper->addItem(new CVar('operations['.$opid.']', $operation));
	}

	$footer = array();
	if(!isset($_REQUEST['new_operation']))
		$footer[] = new CSubmit('new_operation',S_NEW,null,'link_menu');

	if($tblOper->ItemsCount() > 0 ){
		$footer[] = SPACE;
		$footer[] = new CSubmit('del_operation',S_DELETE_SELECTED, null, 'link_menu');
	}

	$operationList->addRow(S_ACTION_OPERATIONS, new CDiv( array($tblOper, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));

// NEW OPERATION FORM {{{
	if(isset($_REQUEST['new_operation'])){
		$tblOper = new CTable(null, 'formElementTable');

// init new_operation variable
		$new_operation = get_request('new_operation', null);

		if(!is_array($new_operation)){
			$new_operation = array(
				'action' => 'create',
				'operationtype' => 0,
				'esc_period' => 0,
				'esc_step_from' => 1,
				'esc_step_to' => 0,
				'evaltype' => 0
			);
		}
//SDII($_REQUEST);
		$update_mode = ($new_operation['action'] == 'update');

		$tblOper->addItem(new CVar('new_operation[action]', $new_operation['action']));

		if(isset($new_operation['id']))
			$tblOper->addItem(new CVar('new_operation[id]', $new_operation['id']));
		if(isset($new_operation['operationid']))
			$tblOper->addItem(new CVar('new_operation[operationid]', $new_operation['operationid']));

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

		$tblOper->addRow(array(S_STEP, $tblStep));

		$cmbOpType = new CComboBox('new_operation[operationtype]', $new_operation['operationtype'], 'submit()');
		foreach($allowedOperations as $oper)
			$cmbOpType->addItem($oper, operation_type2str($oper));

		$tblOper->addRow(array(S_OPERATION_TYPE, $cmbOpType));
//SDII($data['operations']);
		switch($new_operation['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if(!isset($new_operation['opmessage'])){
					$new_operation['opmessage_usr'] = array();
					$new_operation['opmessage'] = array(
						'default_msg' => 1,
						'subject' => '{TRIGGER.NAME}: {STATUS}',
						'message' => '{TRIGGER.NAME}: {STATUS}',
						'mediatypeid' => 0,
					);
				}

				if(!isset($new_operation['opmessage']['default_msg']))
					$new_operation['opmessage']['default_msg'] = 0;

				$usrgrpList = new CTable();
				$usrgrpList->setAttribute('id', 'opmsgUsrgrpList');

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm='.S_ACTION.'&srctbl=usrgrp'.'&srcfld1=usrgrpid'.'&srcfld2=name'.'&multiselect=1'.'",450,450)','link_menu');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opmsgUsrgrpListFooter');

				$usrgrpList->addRow($buttonRow);


				$userList = new CTable();
				$userList->setAttribute('id', 'opmsgUserList');

				$addUserBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm='.S_ACTION.'&srctbl=users'.'&srcfld1=userid'.'&srcfld2=alias'.'&multiselect=1'.'",450,450)','link_menu');

				$col = new CCol($addUserBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opmsgUserListFooter');

				$userList->addRow($buttonRow);

// Add Participations
				$usrgrpids = isset($new_operation['opmessage_grp']) ?
					zbx_objectValues($new_operation['opmessage_grp'], 'usrgrpid') :
					array();

				$userids = isset($new_operation['opmessage_usr']) ?
					zbx_objectValues($new_operation['opmessage_usr'], 'userid') :
					array();

				$usrgrps = CUserGroup::get(array('usrgrpids' => $usrgrpids, 'output' => array('name')));
				order_result($usrgrps, 'name');

				$users = CUser::get(array('userids' => $userids, 'output' => array('alias')));
				order_result($users, 'alias');

				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'usrgrpid', 'values'=>$usrgrps)).');';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'userid', 'values'=>$users)).');';

				zbx_add_post_js($jsInsert);

				$tblOper->addRow(array(_('Send to User groups'), new CDiv($usrgrpList, 'objectgroup inlineblock border_dotted ui-corner-all')));
				$tblOper->addRow(array(_('Send to Users'), new CDiv($userList, 'objectgroup inlineblock border_dotted ui-corner-all')));

				$cmbMediaType = new CComboBox('new_operation[opmessage][mediatypeid]', $new_operation['opmessage']['mediatypeid'], 'submit()');
				$cmbMediaType->addItem(0, S_MINUS_ALL_MINUS);

				$sql = 'SELECT mt.mediatypeid, mt.description' .
						' FROM media_type mt ' .
						' WHERE ' . DBin_node('mt.mediatypeid') .
						' ORDER BY mt.description';
				$db_mediatypes = DBselect($sql);
				while($db_mediatype = DBfetch($db_mediatypes)){
					$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
				}

				$tblOper->addRow(array(S_SEND_ONLY_TO, $cmbMediaType));

				$tblOper->addRow(array(S_DEFAULT_MESSAGE, new CCheckBox('new_operation[opmessage][default_msg]', $new_operation['opmessage']['default_msg'], 'javascript: submit();', 1)));

				if(!$new_operation['opmessage']['default_msg']){
					$tblOper->addRow(array(S_SUBJECT, new CTextBox('new_operation[opmessage][subject]', $new_operation['opmessage']['subject'], 77)));
					$tblOper->addRow(array(S_MESSAGE, new CTextArea('new_operation[opmessage][message]', $new_operation['opmessage']['message'], 77, 7)));
				}
				else{
					$tblOper->addItem(new CVar('new_operation[opmessage][subject]', $new_operation['opmessage']['subject']));
					$tblOper->addItem(new CVar('new_operation[opmessage][message]', $new_operation['opmessage']['message']));
				}
				break;
			case OPERATION_TYPE_COMMAND:
				$cmdList = new CTable();
				$cmdList->addRow(array(_('Target'), _('Command'), SPACE));

				$addCmdBtn = new CButton('add', _('Add'), "javascript: showOpCmdForm(0,'new');",'link_menu');

				$col = new CCol($addCmdBtn);
				$col->setAttribute('colspan', 3);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opCmdListFooter');

				$cmdList->addRow($buttonRow);

// Add Participations
				if(!isset($new_operation['opcommand_grp'])) $new_operation['opcommand_grp'] = array();
				if(!isset($new_operation['opcommand_hst'])) $new_operation['opcommand_hst'] = array();

				$hosts = CHost::get(array(
					'hostids' => zbx_objectValues($new_operation['opcommand_hst'], 'hostid'),
					'output' => array('hostid','host'),
					'preservekeys' => true
				));
				foreach($new_operation['opcommand_hst'] as $ohnum => $cmd)
					$new_operation['opcommand_hst'][$ohnum]['host'] = ($cmd['hostid'] > 0) ? $hosts[$cmd['hostid']]['host'] : '';
				morder_result($new_operation['opcommand_hst'], array('host', 'opcommand_hstid'));

				$groups = CHostGroup::get(array(
					'groupids' => zbx_objectValues($new_operation['opcommand_grp'], 'groupid'),
					'output' => array('groupid','name'),
					'preservekeys' => true
				));

				foreach($new_operation['opcommand_grp'] as $ognum => $cmd)
					$new_operation['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
				morder_result($new_operation['opcommand_grp'], array('name', 'opcommand_grpid'));
// JS Add commands
				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'hostid', 'values'=>$new_operation['opcommand_hst'])).');';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'groupid', 'values'=>$new_operation['opcommand_grp'])).');';

				zbx_add_post_js($jsInsert);

				$cmdList = new CDiv($cmdList, 'objectgroup border_dotted ui-corner-all');
				$cmdList->setAttribute('id', 'opCmdList');

				$tblOper->addRow(array(_('Remote commands'), $cmdList));
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
				if(!isset($new_operation['opgroup'])){
					$new_operation['opgroup'] = array();
				}

				$groupList = new CTable();
				$groupList->setAttribute('id', 'opGroupList');

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm='.S_ACTION.'&srctbl=host_group&srcfld1=groupid&srcfld2=name&multiselect=1&reference=dsc_groupid'.'",450,450)','link_menu');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opGroupListFooter');

				$groupList->addRow($buttonRow);

// Add Participations
				$groupids = isset($new_operation['opgroup']) ?
					zbx_objectValues($new_operation['opgroup'], 'groupid') :
					array();

				$groups = CHostGroup::get(array('groupids' => $groupids, 'output' => array('name')));
				order_result($groups, 'name');

				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'dsc_groupid', 'values'=>$groups)).');';

				zbx_add_post_js($jsInsert);

				$caption = (OPERATION_TYPE_GROUP_ADD == $new_operation['operationtype']) ? _('Add to host groups') : _('Remove from host groups');
				$tblOper->addRow(array($caption, new CDiv($groupList, 'objectgroup inlineblock border_dotted ui-corner-all')));
				break;
			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if(!isset($new_operation['optemplate'])){
					$new_operation['optemplate'] = array();
				}

				$templateList = new CTable();
				$templateList->setAttribute('id', 'opTemplateList');

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm='.S_ACTION.'&srctbl=host_templates&srcfld1=templateid&srcfld2=host&multiselect=1&reference=dsc_templateid'.'",450,450)','link_menu');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opTemplateListFooter');

				$templateList->addRow($buttonRow);

// Add Participations
				$templateids = isset($new_operation['optemplate']) ?
					zbx_objectValues($new_operation['optemplate'], 'templateid') :
					array();

				$templates = CTemplate::get(array('templateids' => $templateids, 'output' => array('templateid','host')));
				order_result($templates, 'host');

				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'dsc_templateid', 'values'=>$templates)).');';

				zbx_add_post_js($jsInsert);

				$caption = (OPERATION_TYPE_TEMPLATE_ADD == $new_operation['operationtype']) ? _('Link with templates') : _('Unlink from templates');
				$tblOper->addRow(array($caption, new CDiv($templateList, 'objectgroup inlineblock border_dotted ui-corner-all')));
				break;
		}

// new Operation conditions
		if($data['eventsource'] == 0){
			$evaltype = $new_operation['evaltype'];

			$tblCond = new CTable();

			if(!isset($new_operation['opconditions']))
				$new_operation['opconditions'] = array();

			$opconditions = $new_operation['opconditions'];
			$allowed_opconditions = get_opconditions_by_eventsource($data['eventsource']);

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
				$cond_buttons[] = new CSubmit('new_opcondition', S_NEW, null, 'link_menu');
			}
			if($cond_el->ItemsCount() > 0){
				$cond_buttons[] = SPACE;
				$cond_buttons[] = new CSubmit('del_opcondition', S_DELETE_SELECTED, null, 'link_menu');
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

				$tblOper->addRow(array(
					S_TYPE_OF_CALCULATION,
					array($cmb_calc_type, new CTextBox('preview', $grouped_opconditions, 60, 'yes'))
				));
			}
			else{
				$tblCond->addItem(new CVar('new_operation[evaltype]', ACTION_EVAL_TYPE_AND_OR));
			}

			$tblCond->addRow($cond_el);
			$tblCond->addRow(new CCol($cond_buttons));

			$tblOper->addRow(array(S_CONDITIONS, $tblCond));
			unset($grouped_opconditions, $cond_el, $cond_buttons, $tblCond);
		}

		$footer = array(
			new CSubmit('add_operation', $update_mode ? _s('Save') : _s('Add'), null, 'link_menu'),
			SPACE,
			new CSubmit('cancel_new_operation', _s('Cancel'), null, 'link_menu')
		);

		$operationList->addRow(_s('Operation details'), new CDiv( array($tblOper, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));

// NEW OPERATION CONDITION
		if(isset($_REQUEST['new_opcondition'])){
			$tblCond = new CTable(null, 'formElementTable');

			$allowedOpConditions = get_opconditions_by_eventsource($data['eventsource']);

			$new_opcondition = get_request('new_opcondition', array());
			if(!is_array($new_opcondition))	$new_opcondition = array();

			if(empty($new_opcondition)){
				$new_opcondition['conditiontype'] = CONDITION_TYPE_EVENT_ACKNOWLEDGED;
				$new_opcondition['operator'] = CONDITION_OPERATOR_LIKE;
				$new_opcondition['value'] = 0;
			}

			if(!str_in_array($new_opcondition['conditiontype'], $allowedOpConditions))
				$new_opcondition['conditiontype'] = $allowedOpConditions[0];

			$rowCondition = array();

			$cmbCondType = new CComboBox('new_opcondition[conditiontype]',$new_opcondition['conditiontype'],'submit()');
			foreach($allowedOpConditions as $cond)
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
				new CSubmit('add_opcondition', S_ADD, null, 'link_menu'),
				SPACE,
				new CSubmit('cancel_new_opcondition', S_CANCEL, null, 'link_menu')
			);

			$operationList->addRow(_s('Operation condition'), new CDiv( array($tblCond, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));
		}
	}

	$divTabs->addTab('operationTab', S_OPERATIONS, $operationList);
// }}} ACTION OPERATIONS FORM

	$frmAction->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', S_SAVE));
	$others = array();
	if(isset($data['actionid'])){
		$others[] = new CButton('clone', S_CLONE);
		$others[] = new CButtonDelete(S_DELETE_SELECTED_ACTION_Q,url_param('form').url_param('eventsource').url_param('actionid'));
	}
	$others[] = new CButtonCancel(url_param('actiontype'));

	$frmAction->addItem(makeFormFooter($main, $others));

return $frmAction;
?>
