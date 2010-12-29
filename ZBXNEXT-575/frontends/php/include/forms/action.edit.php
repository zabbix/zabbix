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
?>
<?php
// TODO
	$data = $data;
SDII($data);
	$inputLength = 60;

	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh'])) $divTabs->setSelected(0);

	$frmAction = new CForm();
	$frmAction->setName('web.action.edit.php.');
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

		$frmAction->addVar('conditions['.$i.'][conditiontype]', $condition['conditiontype']);
		$frmAction->addVar('conditions['.$i.'][operator]', $condition['operator']);
		$frmAction->addVar('conditions['.$i.'][value]', $condition['value']);

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
		'type' => isset($new_condition['type']) ? $new_condition['type'] : CONDITION_TYPE_TRIGGER_NAME,
		'operator' => isset($new_condition['operator']) ? $new_condition['operator'] : CONDITION_OPERATOR_LIKE,
		'value' => isset($new_condition['value']) ? $new_condition['value'] : '',
	);

	if(!str_in_array($new_condition['type'], $allowedConditions))
		$new_condition['type'] = $allowedConditions[0];

	$rowCondition = array();
	$cmbCondType = new CComboBox('new_condition[type]',$new_condition['type'],'submit()');
	foreach($allowedConditions as $cond)
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
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=group&srctbl=host_group".
					"&srcfld1=groupid&srcfld2=name',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			$tblNewCond->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('host','',20,'yes'),
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=host_templates".
					"&srcfld1=hostid&srcfld2=host',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_HOST:
			$tblNewCond->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('host','',20,'yes'),
				new CSubmit('btn1',S_SELECT,
					"return PopUp('popup.php?writeonly=1&dstfrm=".S_ACTION.
					"&dstfld1=new_condition%5Bvalue%5D&dstfld2=host&srctbl=hosts".
					"&srcfld1=hostid&srcfld2=host',450,450);",
					'T'));
			break;
		case CONDITION_TYPE_TRIGGER:
			$tblNewCond->addItem(new CVar('new_condition[value]','0'));

			$rowCondition[] = array(
				new CTextBox('trigger','',20,'yes'),
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
	$objects_tmp = array();
	$objectids_tmp = array();
	foreach($data['operations'] as $key => $operation) {
		$esc_step_from[$key] = $operation['esc_step_from'];
		$objects_tmp[$key] = $operation['object'];
		$objectids_tmp[$key] = $operation['objectid'];
	}
	array_multisort($esc_step_from, SORT_ASC, SORT_NUMERIC, $objects_tmp, SORT_DESC, $objectids_tmp, SORT_ASC, $data['operations']);
// --

	$tblOper = new CTable(S_NO_OPERATIONS_DEFINED);
	$tblOper->addStyle('width: 100%;');
	$tblOper->setHeader(array(
		new CCheckBox('all_operations',null,'checkAll("'.S_ACTION.'","all_operations","g_operationid");'),
		S_STEPS, S_DETAILS, S_PERIOD.' ('.S_SEC_SMALL.')', S_DELAY, S_ACTION
	));

	$delay = count_operations_delay($data['operations'],$data['esc_period']);
	foreach($data['operations'] as $id => $condition){
		if(!str_in_array($condition['operationtype'], $allowedOperations)) continue;

		if(!isset($condition['default_msg'])) $condition['default_msg'] = 0;
		if(!isset($condition['opconditions'])) $condition['opconditions'] = array();
		if(!isset($condition['mediatypeid'])) $condition['mediatypeid'] = 0;

		$oper_details = new CSpan(get_operation_desc(SHORT_DESCRITION, $condition));
		$oper_details->setHint(nl2br(get_operation_desc(LONG_DESCRITION, $condition)));

		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if($condition['esc_step_from'] < 1) $condition['esc_step_from'] = 1;

		$esc_steps_txt = $condition['esc_step_from'].' - '.$condition['esc_step_to'];
// Display N-N as N
		$esc_steps_txt = ($condition['esc_step_from']==$condition['esc_step_to'])?
			$condition['esc_step_from']:$condition['esc_step_from'].' - '.$condition['esc_step_to'];

		$esc_period_txt = $condition['esc_period']?$condition['esc_period']:S_DEFAULT;
		$esc_delay_txt = $delay[$condition['esc_step_from']]?convert_units($delay[$condition['esc_step_from']],'uptime'):S_IMMEDIATELY;

		$tblOper->addRow(array(
			new CCheckBox("g_operationid[]", 'no', null,$id),
			$esc_steps_txt,
			$oper_details,
			$esc_period_txt,
			$esc_delay_txt,
			new CSubmit('edit_operationid['.$id.']',S_EDIT, null, 'link_menu')
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
	if(!isset($_REQUEST['new_operation']))
		$footer[] = new CSubmit('new_operation',S_NEW,null,'link_menu');

	if($tblOper->ItemsCount() > 0 ){
		$footer[] = SPACE;
		$footer[] = new CSubmit('del_operation',S_DELETE_SELECTED, null, 'link_menu');
	}

	$operationList->addRow(S_ACTION_OPERATIONS, new CDiv( array($tblOper, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));
	$divTabs->addTab('operationTab', S_OPERATIONS, $operationList);
// }}} ACTION OPERATIONS FORM




	$frmAction->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', S_SAVE));
	$others = array();
	if(isset($data['actionid'])){
		$others[] = new CSubmit('clone', S_CLONE);
		$others[] = new CButtonDelete(S_DELETE_SELECTED_ACTION_Q,url_param('form').url_param('eventsource').url_param('actionid'));
	}
	$others[] = new CButtonCancel(url_param('actiontype'));

	$frmAction->addItem(makeFormFooter($main, $others));

return $frmAction;
?>
