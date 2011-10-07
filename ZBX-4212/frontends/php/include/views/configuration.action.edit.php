<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
require_once('include/views/js/configuration.action.edit.js.php');
?>
<?php
// TODO
	$data = $data;
//SDII($data);
	$inputLength = 60;

	$divTabs = new CTabView(array('remember'=>1));
	if(!isset($_REQUEST['form_refresh'])) $divTabs->setSelected(0);

	$frmAction = new CForm();
	$frmAction->setName('action.edit');
	$frmAction->addVar('form', get_request('form', 1));

	$from_rfr = get_request('form_refresh',0);
	$frmAction->addVar('form_refresh', $from_rfr+1);

	if(isset($data['actionid'])) $frmAction->addVar('actionid', $data['actionid']);
	$frmAction->addVar('eventsource', $data['eventsource']);

// ACTION FORM {{{
	$actionList = new CFormList('actionlist');

	$actionList->addRow(_('Name'), new CTextBox('name', $data['name'], $inputLength));

	if(EVENT_SOURCE_TRIGGERS == $data['eventsource']){
		$actionList->addRow(_('Period (minimum 60 seconds)'), array(new CNumericBox('esc_period', $data['esc_period'], 6, 'no'), ' ('._('seconds').')'));
	}
	else{
		$frmAction->addVar('esc_period', 0);
	}


	$actionList->addRow(_('Default subject'), new CTextBox('def_shortdata', $data['def_shortdata'], $inputLength));
	$actionList->addRow(_('Default message'), new CTextArea('def_longdata', $data['def_longdata'], $inputLength, 5));

	if(EVENT_SOURCE_TRIGGERS == $data['eventsource']){
		$actionList->addRow(_('Recovery message'), new CCheckBox('recovery_msg',$data['recovery_msg'],'javascript: submit();',1));
		if($data['recovery_msg']){
			$actionList->addRow(_('Recovery subject'), new CTextBox('r_shortdata', $data['r_shortdata'], $inputLength));
			$actionList->addRow(_('Recovery message'), new CTextArea('r_longdata', $data['r_longdata'], $inputLength, 5));
		}
		else{
			$frmAction->addVar('r_shortdata', $data['r_shortdata']);
			$frmAction->addVar('r_longdata', $data['r_longdata']);
		}
	}

	$actionList->addRow(_('Enabled'), new CCheckBox('status', !$data['status'], null, ACTION_STATUS_ENABLED));

	$divTabs->addTab('actionTab', _('Action'), $actionList);
// }}} ACTION_FORM

// CONDITIONS FORM {{{
	$conditionList = new CFormList('conditionlist');
	$allowedConditions = get_conditions_by_eventsource($data['eventsource']);

	morder_result($data['conditions'], array('conditiontype','operator'), ZBX_SORT_DOWN);

// group conditions by type
	$condElements = new CTable(_('No conditions defined.'), 'formElementTable');

	$i = 0;
	$grouped_conditions = array();
	foreach($data['conditions'] as $id => $condition){
		if(!isset($condition['conditiontype'])) $condition['conditiontype'] = 0;
		if(!isset($condition['operator'])) $condition['operator'] = 0;
		if(!isset($condition['value'])) $condition['value'] = 0;

		if(!str_in_array($condition['conditiontype'], $allowedConditions)) continue;

		$label = chr(ord('A') + $i);
		$condElements->addRow(array('('.$label.')',array(
			new CCheckBox('g_conditionid[]', 'no', null,$i), SPACE,
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
				$group_op = $glog_op = _('and');
			break;
			case ACTION_EVAL_TYPE_OR:
				$group_op = $glog_op = _('or');
			break;
			default:
				$group_op = _('or');
				$glog_op = _('and');
		}

		foreach($grouped_conditions as $id => $condition)
			$grouped_conditions[$id] = '('.implode(' '.$group_op.' ', $condition).')';

		$grouped_conditions = implode(' '.$glog_op.' ', $grouped_conditions);

		$cmb_calc_type = new CComboBox('evaltype', $data['evaltype'], 'submit()');
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, _('AND / OR'));
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, _('AND'));
		$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, _('OR'));
		$conditionList->addRow(_('Type of calculation'), array($cmb_calc_type, new CSpan($grouped_conditions)));
	}
	else{
		$frmAction->addVar('evaltype', $data['evaltype']);
	}

	$removeCondition = null;
	if($condElements->ItemsCount() > 0){
		$removeCondition = new CSubmit('del_condition', _('Delete selected'), null, 'link_menu');
	}

	$conditionList->addRow(_('Conditions'), new CDiv(array($condElements, $removeCondition), 'objectgroup inlineblock border_dotted ui-corner-all'));

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


	$cmbCondOp = new CComboBox('new_condition[operator]', $new_condition['operator']);
	foreach(get_operators_by_conditiontype($new_condition['conditiontype']) as $op)
		$cmbCondOp->addItem($op, condition_operator2str($op));
	$rowCondition[] = $cmbCondOp;

	switch($new_condition['conditiontype']){
		case CONDITION_TYPE_HOST_GROUP:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('group','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=group&srctbl=host_group".
					"&srcfld1=groupid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('hostname','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=hostname&srctbl=host_templates".
					"&srcfld1=templateid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_HOST:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('hostname','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=hostname&srctbl=hosts".
					"&srcfld1=hostid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_TRIGGER:
			$conditionList->addItem(new CVar('new_condition[value]','0'));

			$rowCondition[] = array(
				new CTextBox('trigger','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=trigger&srctbl=triggers".
					"&srcfld1=triggerid&srcfld2=description');",
					'link_menu'));
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
			$rowCondition[] = new CTextBox('new_condition[value]', ZBX_DEFAULT_INTERVAL, 40);
			break;
		case CONDITION_TYPE_TRIGGER_SEVERITY:
			$cmbCondVal = new CComboBox('new_condition[value]');
			$cmbCondVal->addItems(getSeverityCaption());

			$rowCondition[] = $cmbCondVal;
			break;
		case CONDITION_TYPE_MAINTENANCE:
			$rowCondition[] = new CCol(_('maintenance'));
			break;
		case CONDITION_TYPE_NODE:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('node','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=node&srctbl=nodes".
					"&srcfld1=nodeid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_DRULE:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('drule','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=drule&srctbl=drules".
					"&srcfld1=druleid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_DCHECK:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('dcheck','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=dcheck&srctbl=dchecks".
					"&srcfld1=dcheckid&srcfld2=name',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_PROXY:
			$conditionList->addItem(new CVar('new_condition[value]','0'));
			$rowCondition[] = array(
				new CTextBox('proxy','',40,'yes'),
				new CButton('btn1',_('Select'),
					"return PopUp('popup.php?writeonly=1&dstfrm=".$frmAction->getName().
					"&dstfld1=new_condition_value&dstfld2=proxy&srctbl=proxies".
					"&srcfld1=hostid&srcfld2=host',450,450);",
					'link_menu'));
			break;
		case CONDITION_TYPE_DHOST_IP:
			$rowCondition[] = new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1', 50);
			break;
		case CONDITION_TYPE_DSERVICE_TYPE:
			$cmbCondVal = new CComboBox('new_condition[value]');
			foreach(array(SVC_SSH, SVC_LDAP, SVC_SMTP, SVC_FTP, SVC_HTTP,
				SVC_POP, SVC_NNTP, SVC_IMAP, SVC_TCP,SVC_AGENT,SVC_SNMPv1,SVC_SNMPv2c,SVC_SNMPv3,
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
		array(new CDiv($rowCondition), new CSubmit('add_condition',_('Add'), null, 'link_menu')),
		'objectgroup inlineblock border_dotted ui-corner-all'
	);

	$conditionList->addRow(_('New condition'), $newCond);

	$divTabs->addTab('conditionTab', _('Conditions'), $conditionList);
// }}} CONDITION FORM

// ACTION OPERATIONS FORM {{{
	$operationList = new CFormList('operationlist');

	$allowedOperations = get_operations_by_eventsource($data['eventsource']);

	if(EVENT_SOURCE_TRIGGERS == $data['eventsource'])
		$operationsHeader = array(new CCheckBox('all_operations',null,'checkAll("'.$frmAction->getName().'","all_operations","g_operationid");'),
			_('Steps'), _('Details'), _('Period (sec)'), _('Delay'), _('Action'));
	else
		$operationsHeader = array(new CCheckBox('all_operations',null,'checkAll("'.$frmAction->getName().'","all_operations","g_operationid");'),
			_('Details'), _('Action'));

	$tblOper = new CTable(_('No operations defined.'), 'formElementTable');
	$tblOper->setHeader($operationsHeader);

	$delay = count_operations_delay($data['operations'],$data['esc_period']);
	foreach($data['operations'] as $opid => $operation){
		if(!str_in_array($operation['operationtype'], $allowedOperations)) continue;
		if(!isset($operation['opconditions'])) $operation['opconditions'] = array();
		if(!isset($operation['mediatypeid'])) $operation['mediatypeid'] = 0;

		$oper_details = new CSpan(get_operation_desc(SHORT_DESCRIPTION, $operation));
		$oper_details->setHint(get_operation_desc(LONG_DESCRIPTION, $operation));

		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if($operation['esc_step_from'] < 1) $operation['esc_step_from'] = 1;

		$esc_steps_txt = $operation['esc_step_from'].' - '.$operation['esc_step_to'];
// Display N-N as N
		$esc_steps_txt = ($operation['esc_step_from']==$operation['esc_step_to'])?
			$operation['esc_step_from']:$operation['esc_step_from'].' - '.$operation['esc_step_to'];

		$esc_period_txt = $operation['esc_period']?$operation['esc_period']:_('Default');
		$esc_delay_txt = $delay[$operation['esc_step_from']]?convert_units($delay[$operation['esc_step_from']],'uptime'):_('Immediately');

		if(EVENT_SOURCE_TRIGGERS == $data['eventsource'])
			$operationRow = array(
				new CCheckBox('g_operationid['.$opid.']', 'no', null, $opid),
				$esc_steps_txt,
				$oper_details,
				$esc_period_txt,
				$esc_delay_txt,
				new CSubmit('edit_operationid['.$opid.']',_('Edit'), null, 'link_menu')
			);
		else
			$operationRow = array(
				new CCheckBox('g_operationid['.$opid.']', 'no', null, $opid),
				$oper_details,
				new CSubmit('edit_operationid['.$opid.']',_('Edit'), null, 'link_menu')
			);
		$tblOper->addRow($operationRow);

		$operation['opmessage_grp'] = zbx_toHash($operation['opmessage_grp'], 'usrgrpid');
		$operation['opmessage_usr'] = zbx_toHash($operation['opmessage_usr'], 'userid');
		$operation['opcommand_grp'] = zbx_toHash($operation['opcommand_grp'], 'groupid');
		$operation['opcommand_hst'] = zbx_toHash($operation['opcommand_hst'], 'hostid');

		$tblOper->addItem(new CVar('operations['.$opid.']', $operation));
	}

	$footer = array();
	if(!isset($_REQUEST['new_operation']))
		$footer[] = new CSubmit('new_operation',_('New'),null,'link_menu');

	if($tblOper->ItemsCount() > 0 ){
		$footer[] = SPACE.SPACE;
		$footer[] = new CSubmit('del_operation',_('Remove selected'), null, 'link_menu');
	}

	$operationList->addRow(_('Action operations'), new CDiv( array($tblOper, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));

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
				'esc_step_to' => 1,
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

		if(EVENT_SOURCE_TRIGGERS == $data['eventsource']){
			$tblStep = new CTable();

			$step_from = new CNumericBox('new_operation[esc_step_from]', $new_operation['esc_step_from'],4);
			$step_from->addAction('onchange','javascript:'.$step_from->getAttribute('onchange').' if(this.value == 0) this.value=1;');

			$tblStep->addRow(array(_('From'), $step_from));
			$tblStep->addRow(array(
				_('To'),
				new CCol(array(
					new CNumericBox('new_operation[esc_step_to]', $new_operation['esc_step_to'], 4),
					_('(0 - infinitely)')))
			));

			$tblStep->addRow(array(
				_('Period'),
				new CCol(array(
					new CNumericBox('new_operation[esc_period]', $new_operation['esc_period'], 5),
					_('(minimum 60 seconds, 0 - use action default)')))
			));

			$tblOper->addRow(array(_('Step'), $tblStep));
		}
		else{
			$tblOper->addItem(new CVar('new_operation[esc_step_from]', 1));
			$tblOper->addItem(new CVar('new_operation[esc_step_to]', 1));
			$tblOper->addItem(new CVar('new_operation[esc_period]', 0));
		}

		$cmbOpType = new CComboBox('new_operation[operationtype]', $new_operation['operationtype'], 'submit()');
		foreach($allowedOperations as $oper)
			$cmbOpType->addItem($oper, operation_type2str($oper));

		$tblOper->addRow(array(_('Operation type'), $cmbOpType));
		switch($new_operation['operationtype']){
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

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name&multiselect=1",450,450)', 'link_menu');
				$addUsrgrpBtn->attr('id', 'addusrgrpbtn');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opmsgUsrgrpListFooter');

				$usrgrpList->addRow($buttonRow);


				$userList = new CTable();
				$userList->setAttribute('id', 'opmsgUserList');

				$addUserBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=users&srcfld1=userid&srcfld2=alias&multiselect=1",450,450)', 'link_menu');
				$addUserBtn->attr('id', 'adduserbtn');

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

				$usrgrps = API::UserGroup()->get(array('usrgrpids' => $usrgrpids, 'output' => array('name')));
				order_result($usrgrps, 'name');

				$users = API::User()->get(array('userids' => $userids, 'output' => array('alias')));
				order_result($users, 'alias');

				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'usrgrpid', 'values'=>$usrgrps)).');';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'userid', 'values'=>$users)).');';

				zbx_add_post_js($jsInsert);

				$tblOper->addRow(array(_('Send to User groups'), new CDiv($usrgrpList, 'objectgroup inlineblock border_dotted ui-corner-all')));
				$tblOper->addRow(array(_('Send to Users'), new CDiv($userList, 'objectgroup inlineblock border_dotted ui-corner-all')));

				$cmbMediaType = new CComboBox('new_operation[opmessage][mediatypeid]', $new_operation['opmessage']['mediatypeid']);
				$cmbMediaType->addItem(0, '- '._('All').' -');

				$sql = 'SELECT mt.mediatypeid, mt.description' .
						' FROM media_type mt ' .
						' WHERE ' . DBin_node('mt.mediatypeid') .
						' ORDER BY mt.description';
				$db_mediatypes = DBselect($sql);
				while($db_mediatype = DBfetch($db_mediatypes)){
					$cmbMediaType->addItem($db_mediatype['mediatypeid'], $db_mediatype['description']);
				}

				$tblOper->addRow(array(_('Send only to'), $cmbMediaType));

				$tblOper->addRow(array(_('Default message'), new CCheckBox('new_operation[opmessage][default_msg]', $new_operation['opmessage']['default_msg'], 'javascript: submit();', 1)));

				if(!$new_operation['opmessage']['default_msg']){
					$tblOper->addRow(array(_('Subject'), new CTextBox('new_operation[opmessage][subject]', $new_operation['opmessage']['subject'], 77)));
					$tblOper->addRow(array(_('Message'), new CTextArea('new_operation[opmessage][message]', $new_operation['opmessage']['message'], 77, 7)));
				}
				else{
					$tblOper->addItem(new CVar('new_operation[opmessage][subject]', $new_operation['opmessage']['subject']));
					$tblOper->addItem(new CVar('new_operation[opmessage][message]', $new_operation['opmessage']['message']));
				}
				break;
			case OPERATION_TYPE_COMMAND:
				if(!isset($new_operation['opcommand'])){
					$new_operation['opcommand'] = array();
				}
				$new_operation['opcommand']['type'] = isset($new_operation['opcommand']['type'])
						? $new_operation['opcommand']['type'] : ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT;
				$new_operation['opcommand']['scriptid'] = isset($new_operation['opcommand']['scriptid'])
						? $new_operation['opcommand']['scriptid'] : '';
				$new_operation['opcommand']['execute_on'] = isset($new_operation['opcommand']['execute_on'])
						? $new_operation['opcommand']['execute_on'] : ZBX_SCRIPT_EXECUTE_ON_AGENT;
				$new_operation['opcommand']['publickey'] = isset($new_operation['opcommand']['publickey'])
						? $new_operation['opcommand']['publickey'] : '';
				$new_operation['opcommand']['privatekey'] = isset($new_operation['opcommand']['privatekey'])
						? $new_operation['opcommand']['privatekey'] : '';
				$new_operation['opcommand']['authtype'] = isset($new_operation['opcommand']['authtype'])
						? $new_operation['opcommand']['authtype'] : ITEM_AUTHTYPE_PASSWORD;
				$new_operation['opcommand']['username'] = isset($new_operation['opcommand']['username'])
						? $new_operation['opcommand']['username'] : '';
				$new_operation['opcommand']['password'] = isset($new_operation['opcommand']['password'])
						? $new_operation['opcommand']['password'] : '';
				$new_operation['opcommand']['port'] = isset($new_operation['opcommand']['port'])
						? $new_operation['opcommand']['port'] : '';
				$new_operation['opcommand']['command'] = isset($new_operation['opcommand']['command'])
						? $new_operation['opcommand']['command'] : '';

				$new_operation['opcommand']['script'] = '';
				if(!zbx_empty($new_operation['opcommand']['scriptid'])){
					$userScripts = API::Script()->get(array(
						'scriptids' => $new_operation['opcommand']['scriptid'],
						'output' => API_OUTPUT_EXTEND
					));
					if($userScript = reset($userScripts))
						$new_operation['opcommand']['script'] = $userScript['name'];
				}

				$cmdList = new CTable(null, 'formElementTable');
				$cmdList->addRow(array(_('Target'), _('Action')));

				$addCmdBtn = new CButton('add', _('New'), "javascript: showOpCmdForm(0,'new');",'link_menu');

				$col = new CCol($addCmdBtn);
				$col->setAttribute('colspan', 3);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opCmdListFooter');

				$cmdList->addRow($buttonRow);

// Add Participations
				if(!isset($new_operation['opcommand_grp'])) $new_operation['opcommand_grp'] = array();
				if(!isset($new_operation['opcommand_hst'])) $new_operation['opcommand_hst'] = array();

				$hosts = API::Host()->get(array(
					'hostids' => zbx_objectValues($new_operation['opcommand_hst'], 'hostid'),
					'output' => array('hostid','name'),
					'preservekeys' => true,
					'editable' => true,
				));

				$new_operation['opcommand_hst'] = array_values($new_operation['opcommand_hst']);
				foreach($new_operation['opcommand_hst'] as $ohnum => $cmd)
					$new_operation['opcommand_hst'][$ohnum]['name'] = ($cmd['hostid'] > 0) ? $hosts[$cmd['hostid']]['name'] : '';
				order_result($new_operation['opcommand_hst'], 'name');

				$groups = API::HostGroup()->get(array(
					'groupids' => zbx_objectValues($new_operation['opcommand_grp'], 'groupid'),
					'output' => array('groupid','name'),
					'preservekeys' => true,
					'editable' => true,
				));

				$new_operation['opcommand_grp'] = array_values($new_operation['opcommand_grp']);
				foreach($new_operation['opcommand_grp'] as $ognum => $cmd)
					$new_operation['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
				order_result($new_operation['opcommand_grp'], 'name');

// JS Add commands
				$jsInsert = '';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'hostid', 'values'=>$new_operation['opcommand_hst'])).');';
				$jsInsert.= 'addPopupValues('.zbx_jsvalue(array('object'=>'groupid', 'values'=>$new_operation['opcommand_grp'])).');';

				zbx_add_post_js($jsInsert);

// Target list
				$cmdList = new CDiv($cmdList, 'objectgroup border_dotted ui-corner-all inlineblock');
				$cmdList->setAttribute('id', 'opCmdList');

				$tblOper->addRow(array(_('Target list'), $cmdList));

// TYPE
				$typeCB = new CComboBox('new_operation[opcommand][type]', $new_operation['opcommand']['type'], 'javascript: showOpTypeForm();');
				$typeCB->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
				$typeCB->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Custom script'));
				$typeCB->addItem(ZBX_SCRIPT_TYPE_SSH, _('SSH'));
				$typeCB->addItem(ZBX_SCRIPT_TYPE_TELNET, _('Telnet'));
				$typeCB->addItem(ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT, _('Global script'));

				$userScriptId = new CVar('new_operation[opcommand][scriptid]', $new_operation['opcommand']['scriptid']);
				$userScriptName = new CTextBox('new_operation[opcommand][script]', $new_operation['opcommand']['script'], 30, true);
				$userScriptSelect = new CButton('select_opcommand_script',_('Select'), null, 'link_menu');

				$userScript = new CDiv(array($userScriptId,$userScriptName, SPACE, $userScriptSelect), 'class_opcommand_userscript inlineblock hidden');

				$tblOper->addRow(array(_('Type'), array($typeCB,SPACE,$userScript)));

// Script
				$executeOnRb = new CRadioButtonList('new_operation[opcommand][execute_on]', $new_operation['opcommand']['execute_on']);
				$executeOnRb->makeVertical();

				$executeOnRb->addValue(_('Zabbix agent'),ZBX_SCRIPT_EXECUTE_ON_AGENT);
				$executeOnRb->addValue(_('Zabbix server'),ZBX_SCRIPT_EXECUTE_ON_SERVER);

				$tblOper->addRow(array(_('Execute on'), new CDiv($executeOnRb, 'objectgroup border_dotted ui-corner-all inlineblock')), 'class_opcommand_execute_on hidden');

// SSH
				$cmbAuthType = new CComboBox('new_operation[opcommand][authtype]', $new_operation['opcommand']['authtype'], 'javascript: showOpTypeAuth();');
				$cmbAuthType->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
				$cmbAuthType->addItem(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'));

				$tblOper->addRow(array(_('Authentication method'), $cmbAuthType), 'class_authentication_method hidden');

				$tblOper->addRow(array(_('User name'), new CTextBox('new_operation[opcommand][username]',$new_operation['opcommand']['username'])), 'class_authentication_username hidden');
				$tblOper->addRow(array(_('Public key file'),new CTextBox('new_operation[opcommand][publickey]',$new_operation['opcommand']['publickey'])), 'class_authentication_publickey hidden');
				$tblOper->addRow(array(_('Private key file'),new CTextBox('new_operation[opcommand][privatekey]',$new_operation['opcommand']['privatekey'])), 'class_authentication_privatekey hidden');
				$tblOper->addRow(array(_('Password'),new CTextBox('new_operation[opcommand][password]',$new_operation['opcommand']['password'])), 'class_authentication_password hidden');


// SSH && Telnet
				$tblOper->addRow(array(_('Port'), new CTextBox('new_operation[opcommand][port]', $new_operation['opcommand']['port'])), 'class_opcommand_port hidden');

// Command
				$commandTextArea = new CTextArea('new_operation[opcommand][command]', $new_operation['opcommand']['command'], 77, 7);
				$commandTextArea->addStyle('width: 40em;');
				$tblOper->addRow(array(_('Commands'), $commandTextArea), 'class_opcommand_command hidden');

				$commandIpmiTextBox = new CTextBox('new_operation[opcommand][command]', $new_operation['opcommand']['command']);
				$commandIpmiTextBox->addStyle('width: 40em;');
				$commandIpmiTextBox->attr('id', 'opcommand_command_ipmi');
				$tblOper->addRow(array(_('Commands'), $commandIpmiTextBox), 'class_opcommand_command_ipmi hidden');

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

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=host_group&srcfld1=groupid&srcfld2=name&multiselect=1&reference=dsc_groupid",450,450)', 'link_menu');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opGroupListFooter');

				$groupList->addRow($buttonRow);

// Add Participations
				$groupids = isset($new_operation['opgroup']) ?
					zbx_objectValues($new_operation['opgroup'], 'groupid') :
					array();

				$groups = API::HostGroup()->get(array('groupids' => $groupids, 'output' => array('name')));
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

				$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=host_templates&srcfld1=templateid&srcfld2=name&multiselect=1&reference=dsc_templateid",450,450)', 'link_menu');

				$col = new CCol($addUsrgrpBtn);
				$col->setAttribute('colspan', 2);

				$buttonRow = new CRow($col);
				$buttonRow->setAttribute('id', 'opTemplateListFooter');

				$templateList->addRow($buttonRow);

// Add Participations
				$templateids = isset($new_operation['optemplate']) ?
					zbx_objectValues($new_operation['optemplate'], 'templateid') :
					array();

				$templates = API::Template()->get(array('templateids' => $templateids, 'output' => array('templateid', 'name')));
				order_result($templates, 'name');

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
			$cond_el = new CTable(_('No conditions defined.'));
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
				$cond_buttons[] = new CSubmit('new_opcondition', _('New'), null, 'link_menu');
			}
			if($cond_el->ItemsCount() > 0){
				$cond_buttons[] = SPACE.SPACE;
				$cond_buttons[] = new CSubmit('del_opcondition', _('Remove selected'), null, 'link_menu');
			}

			if($cond_el->ItemsCount() > 1){
// prepare opcondition calcuation type selector
				switch($evaltype) {
					case ACTION_EVAL_TYPE_AND:
						$group_op = $glog_op = _('and');
						break;
					case ACTION_EVAL_TYPE_OR:
						$group_op = $glog_op = _('or');
						break;
					default:
						$group_op = _('or');
						$glog_op = _('and');
						break;
				}

				foreach($grouped_opconditions as $id => $condition)
					$grouped_opconditions[$id] = '(' . implode(' ' . $group_op . ' ', $condition) . ')';

				$grouped_opconditions = implode(' ' . $glog_op . ' ', $grouped_opconditions);

				$cmb_calc_type = new CComboBox('new_operation[evaltype]', $evaltype, 'submit()');
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND_OR, _('AND / OR'));
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_AND, _('AND'));
				$cmb_calc_type->addItem(ACTION_EVAL_TYPE_OR, _('OR'));

				$tblOper->addRow(array(
					_('Type of calculation'),
					array($cmb_calc_type, new CTextBox('preview', $grouped_opconditions, 60, 'yes'))
				));
			}
			else{
				$tblCond->addItem(new CVar('new_operation[evaltype]', ACTION_EVAL_TYPE_AND_OR));
			}

			$tblCond->addRow($cond_el);
			$tblCond->addRow(new CCol($cond_buttons));

			$tblOper->addRow(array(_('Conditions'), $tblCond));
			unset($grouped_opconditions, $cond_el, $cond_buttons, $tblCond);
		}

		$footer = array(
			new CSubmit('add_operation', $update_mode ? _('Update') : _('Add'), null, 'link_menu'),
			SPACE.SPACE,
			new CSubmit('cancel_new_operation', _('Cancel'), null, 'link_menu')
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
					$cmbCondVal->addItem(0, _('Not Ack'));
					$cmbCondVal->addItem(1, _('Ack'));
					$rowCondition[] = $cmbCondVal;
					break;
			}
			$tblCond->addRow($rowCondition);

			$footer = array(
				new CSubmit('add_opcondition', _('Add'), null, 'link_menu'),
				SPACE,
				new CSubmit('cancel_new_opcondition', _('Cancel'), null, 'link_menu')
			);

			$operationList->addRow(_('Operation condition'), new CDiv( array($tblCond, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));
		}
	}

	$divTabs->addTab('operationTab', _('Operations'), $operationList);
// }}} ACTION OPERATIONS FORM

	$frmAction->addItem($divTabs);

// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(isset($data['actionid'])){
		$others[] = new CButton('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete current action?'),url_param('form').url_param('eventsource').url_param('actionid'));
	}
	$others[] = new CButtonCancel(url_param('actiontype'));

	$frmAction->addItem(makeFormFooter($main, $others));

return $frmAction;
?>
