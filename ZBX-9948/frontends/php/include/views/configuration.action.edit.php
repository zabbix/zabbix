<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/js/configuration.action.edit.js.php';

$actionWidget = new CWidget(null, 'action-edit');
$actionWidget->addPageHeader(_('CONFIGURATION OF ACTIONS'));

// create form
$actionForm = new CForm();
$actionForm->setName('action.edit');
$actionForm->addVar('form', $this->data['form']);

if ($this->data['actionid']) {
	$actionForm->addVar('actionid', $this->data['actionid']);
}
else {
	$actionForm->addVar('eventsource', $this->data['eventsource']);
}

/*
 * Action tab
 */
$actionFormList = new CFormList('actionlist');
$nameTextBox = new CTextBox('name', $this->data['action']['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$actionFormList->addRow(_('Name'), $nameTextBox);

$actionFormList->addRow(_('Default subject'), new CTextBox('def_shortdata', $this->data['action']['def_shortdata'], ZBX_TEXTBOX_STANDARD_SIZE));
$actionFormList->addRow(_('Default message'), new CTextArea('def_longdata', $this->data['action']['def_longdata']));

if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS || $this->data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$actionFormList->addRow(_('Recovery message'), new CCheckBox('recovery_msg', $this->data['action']['recovery_msg'], 'javascript: submit();', 1));
	if ($this->data['action']['recovery_msg']) {
		$actionFormList->addRow(_('Recovery subject'), new CTextBox('r_shortdata', $this->data['action']['r_shortdata'], ZBX_TEXTBOX_STANDARD_SIZE));
		$actionFormList->addRow(_('Recovery message'), new CTextArea('r_longdata', $this->data['action']['r_longdata']));
	}
	else {
		$actionForm->addVar('r_shortdata', $this->data['action']['r_shortdata']);
		$actionForm->addVar('r_longdata', $this->data['action']['r_longdata']);
	}
}
$actionFormList->addRow(_('Enabled'), new CCheckBox('status', !$this->data['action']['status'], null, ACTION_STATUS_ENABLED));

/*
 * Condition tab
 */
$conditionFormList = new CFormList('conditionlist');

// create condition table
$conditionTable = new CTable(_('No conditions defined.'), 'formElementTable');
$conditionTable->attr('id', 'conditionTable');
$conditionTable->attr('style', 'min-width: 350px;');
$conditionTable->setHeader(array(_('Label'), _('Name'), _('Action')));

$i = 0;
foreach ($this->data['action']['filter']['conditions'] as $condition) {
	if (!isset($condition['conditiontype'])) {
		$condition['conditiontype'] = 0;
	}
	if (!isset($condition['operator'])) {
		$condition['operator'] = 0;
	}
	if (!isset($condition['value'])) {
		$condition['value'] = '';
	}
	if (!str_in_array($condition['conditiontype'], $this->data['allowedConditions'])) {
		continue;
	}

	$label = isset($condition['formulaid']) ? $condition['formulaid'] : num2letter($i);

	$labelSpan = new CSpan($label, 'label');
	$labelSpan->setAttribute('data-conditiontype', $condition['conditiontype']);
	$labelSpan->setAttribute('data-formulaid', $label);

	$conditionTable->addRow(
		array(
			$labelSpan,
			get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']),
			array(
				new CButton('remove', _('Remove'), 'javascript: removeCondition('.$i.');', 'link_menu'),
				new CVar('conditions['.$i.']', $condition)
			),
			new CVar('conditions[' . $i . '][formulaid]', $label)
		),
		null, 'conditions_'.$i
	);

	$i++;
}

$formula = new CTextBox('formula', $this->data['action']['filter']['formula'], ZBX_TEXTBOX_STANDARD_SIZE);
$formula->attr('id', 'formula');
$formula->attr('placeholder', 'A or (B and C) &hellip;');
if ($this->data['action']['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION)  {
	$formula->addClass('hidden');
}

$calculationTypeComboBox = new CComboBox('evaltype', $this->data['action']['filter']['evaltype'], 'processTypeOfCalculation()');
$calculationTypeComboBox->addItem(CONDITION_EVAL_TYPE_AND_OR, _('And/Or'));
$calculationTypeComboBox->addItem(CONDITION_EVAL_TYPE_AND, _('And'));
$calculationTypeComboBox->addItem(CONDITION_EVAL_TYPE_OR, _('Or'));
$calculationTypeComboBox->addItem(CONDITION_EVAL_TYPE_EXPRESSION, _('Custom expression'));
$calculationTypeComboBox->setAttribute('id', 'evaltype');

$conditionFormList->addRow(
	_('Type of calculation'),
	array(
		$calculationTypeComboBox,
		new CSpan('', ($this->data['action']['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) ? 'hidden' : '', 'conditionLabel'),
		$formula
	),
	false,
	'conditionRow'
);
$conditionFormList->addRow(_('Conditions'), new CDiv($conditionTable, 'objectgroup inlineblock border_dotted ui-corner-all'));

// append new condition to form list
$conditionTypeComboBox = new CComboBox('new_condition[conditiontype]', $this->data['new_condition']['conditiontype'], 'submit()');
foreach ($this->data['allowedConditions'] as $key => $condition) {
	$this->data['allowedConditions'][$key] = array(
		'name' => condition_type2str($condition),
		'type' => $condition
	);
}
order_result($this->data['allowedConditions'], 'name');
foreach ($this->data['allowedConditions'] as $condition) {
	$conditionTypeComboBox->addItem($condition['type'], $condition['name']);
}

$conditionOperatorsComboBox = new CComboBox('new_condition[operator]', $this->data['new_condition']['operator']);
foreach (get_operators_by_conditiontype($this->data['new_condition']['conditiontype']) as $operator) {
	$conditionOperatorsComboBox->addItem($operator, condition_operator2str($operator));
}

switch ($this->data['new_condition']['conditiontype']) {
	case CONDITION_TYPE_HOST_GROUP:
		$condition = new CMultiSelect(array(
			'name' => 'new_condition[value][]',
			'objectName' => 'hostGroup',
			'objectOptions' => array(
				'editable' => true
			),
			'defaultValue' => 0,
			'popup' => array(
				'parameters' => 'srctbl=host_groups&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=groupid&writeonly=1&multiselect=1',
				'width' => 450,
				'height' => 450
			)
		));
		break;

	case CONDITION_TYPE_TEMPLATE:
		$condition = new CMultiSelect(array(
			'name' => 'new_condition[value][]',
			'objectName' => 'templates',
			'objectOptions' => array(
				'editable' => true
			),
			'defaultValue' => 0,
			'popup' => array(
				'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$actionForm->getName().
					'&dstfld1=new_condition_value_&templated_hosts=1&multiselect=1&writeonly=1',
				'width' => 450,
				'height' => 450
			)
		));
		break;

	case CONDITION_TYPE_HOST:
		$condition = new CMultiSelect(array(
			'name' => 'new_condition[value][]',
			'objectName' => 'hosts',
			'objectOptions' => array(
				'editable' => true
			),
			'defaultValue' => 0,
			'popup' => array(
				'parameters' => 'srctbl=hosts&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=hostid&writeonly=1&multiselect=1',
				'width' => 450,
				'height' => 450
			)
		));
		break;

	case CONDITION_TYPE_TRIGGER:
		$condition = new CMultiSelect(array(
			'name' => 'new_condition[value][]',
			'objectName' => 'triggers',
			'objectOptions' => array(
				'editable' => true
			),
			'defaultValue' => 0,
			'popup' => array(
				'parameters' => 'srctbl=triggers&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=triggerid&writeonly=1&multiselect=1&noempty=1',
				'width' => 600,
				'height' => 450
			)
		));
		break;

	case CONDITION_TYPE_TRIGGER_NAME:
		$condition = new CTextBox('new_condition[value]', '', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_TRIGGER_VALUE:
		$condition = new CComboBox('new_condition[value]');
		foreach (array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE) as $trigerValue) {
			$condition->addItem($trigerValue, trigger_value2str($trigerValue));
		}
		break;

	case CONDITION_TYPE_TIME_PERIOD:
		$condition = new CTextBox('new_condition[value]', ZBX_DEFAULT_INTERVAL, ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_TRIGGER_SEVERITY:
		$condition = new CComboBox('new_condition[value]');
		$condition->addItems(getSeverityCaption());
		break;

	case CONDITION_TYPE_MAINTENANCE:
		$condition = new CCol(_('maintenance'));
		break;

	case CONDITION_TYPE_DRULE:
		$conditionFormList->addItem(new CVar('new_condition[value]', '0'));
		$condition = array(
			new CTextBox('drule', '', ZBX_TEXTBOX_STANDARD_SIZE, true),
			SPACE,
			new CButton('btn1', _('Select'),
				'return PopUp("popup.php?srctbl=drules&srcfld1=druleid&srcfld2=name'.
					'&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value&dstfld2=drule", 450, 450);',
				'link_menu'
			)
		);
		break;

	case CONDITION_TYPE_DCHECK:
		$conditionFormList->addItem(new CVar('new_condition[value]', '0'));
		$condition = array(
			new CTextBox('dcheck', '', ZBX_TEXTBOX_STANDARD_SIZE, true),
			SPACE,
			new CButton('btn1', _('Select'),
				'return PopUp("popup.php?srctbl=dchecks&srcfld1=dcheckid&srcfld2=name'.
					'&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value&dstfld2=dcheck&writeonly=1", 450, 450);',
				'link_menu'
			)
		);
		break;

	case CONDITION_TYPE_PROXY:
		$conditionFormList->addItem(new CVar('new_condition[value]', '0'));
		$condition = array(
			new CTextBox('proxy', '', ZBX_TEXTBOX_STANDARD_SIZE, true),
			SPACE,
			new CButton('btn1', _('Select'),
				'return PopUp("popup.php?srctbl=proxies&srcfld1=hostid&srcfld2=host'.
					'&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value&dstfld2=proxy'.
					'", 450, 450);',
				'link_menu'
			)
		);
		break;

	case CONDITION_TYPE_DHOST_IP:
		$condition = new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_DSERVICE_TYPE:
		$discoveryCheckTypes = discovery_check_type2str();
		order_result($discoveryCheckTypes);

		$condition = new CComboBox('new_condition[value]');
		foreach ($discoveryCheckTypes as $key => $discoveryCheckType) {
			$condition->addItem($key, $discoveryCheckType);
		}
		break;

	case CONDITION_TYPE_DSERVICE_PORT:
		$condition = new CTextBox('new_condition[value]', '0-1023,1024-49151', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_DSTATUS:
		$condition = new CComboBox('new_condition[value]');
		foreach (array(DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER, DOBJECT_STATUS_LOST) as $stat) {
			$condition->addItem($stat, discovery_object_status2str($stat));
		}
		break;

	case CONDITION_TYPE_DOBJECT:
		$condition = new CComboBox('new_condition[value]');
		foreach (array(EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE) as $object) {
			$condition->addItem($object, discovery_object2str($object));
		}
		break;

	case CONDITION_TYPE_DUPTIME:
		$condition = new CNumericBox('new_condition[value]', 600, 15);
		break;

	case CONDITION_TYPE_DVALUE:
		$condition = new CTextBox('new_condition[value]', '', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_APPLICATION:
		$condition = new CTextBox('new_condition[value]', '', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_HOST_NAME:
		$condition = new CTextBox('new_condition[value]', '', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	case CONDITION_TYPE_EVENT_TYPE:
		$condition = new CComboBox('new_condition[value]', null, null, eventType());
		break;

	case CONDITION_TYPE_HOST_METADATA:
		$condition = new CTextBox('new_condition[value]', '', ZBX_TEXTBOX_STANDARD_SIZE);
		break;

	default:
		$condition = null;
}

$conditionTable = new CTable(null, 'newActionConditionTable');
$conditionTable->addRow(array($conditionTypeComboBox, $conditionOperatorsComboBox, $condition));
$conditionTable->addRow(array(new CSubmit('add_condition', _('Add'), null, 'link_menu'), SPACE, SPACE));

$conditionFormList->addRow(_('New condition'), new CDiv($conditionTable, 'objectgroup floatleft border_dotted ui-corner-all'));

/*
 * Operation tab
 */
$operationFormList = new CFormList('operationlist');

if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS || $this->data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operationFormList->addRow(_('Default operation step duration'), array(
		new CNumericBox('esc_period', $this->data['action']['esc_period'], 6),
		' ('._('minimum 60 seconds').')')
	);
}

// create operation table
$operationsTable = new CTable(_('No operations defined.'), 'formElementTable');
$operationsTable->attr('style', 'min-width: 600px;');
if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS || $this->data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operationsTable->setHeader(array(_('Steps'), _('Details'), _('Start in'), _('Duration (sec)'), _('Action')));
	$delay = count_operations_delay($this->data['action']['operations'], $this->data['action']['esc_period']);
}
else {
	$operationsTable->setHeader(array(_('Details'), _('Action')));
}

foreach ($this->data['action']['operations'] as $operationid => $operation) {
	if (!str_in_array($operation['operationtype'], $this->data['allowedOperations'])) {
		continue;
	}
	if (!isset($operation['opconditions'])) {
		$operation['opconditions'] = array();
	}
	if (!isset($operation['mediatypeid'])) {
		$operation['mediatypeid'] = 0;
	}

	$details = new CSpan(get_operation_descr(SHORT_DESCRIPTION, $operation));
	$details->setHint(get_operation_descr(LONG_DESCRIPTION, $operation));

	if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS || $this->data['eventsource'] == EVENT_SOURCE_INTERNAL) {
		$esc_steps_txt = null;
		$esc_period_txt = null;
		$esc_delay_txt = null;

		if ($operation['esc_step_from'] < 1) {
			$operation['esc_step_from'] = 1;
		}

		$esc_steps_txt = $operation['esc_step_from'].' - '.$operation['esc_step_to'];

		// display N-N as N
		$esc_steps_txt = ($operation['esc_step_from'] == $operation['esc_step_to'])
			? $operation['esc_step_from']
			: $operation['esc_step_from'].' - '.$operation['esc_step_to'];

		$esc_period_txt = $operation['esc_period'] ? $operation['esc_period'] : _('Default');
		$esc_delay_txt = $delay[$operation['esc_step_from']]
			? convert_units(array('value' => $delay[$operation['esc_step_from']], 'units' => 'uptime'))
			: _('Immediately');

		$operationRow = array(
			$esc_steps_txt,
			$details,
			$esc_delay_txt,
			$esc_period_txt,
			array(
				new CSubmit('edit_operationid['.$operationid.']', _('Edit'), null, 'link_menu'),
				SPACE, SPACE, SPACE,
				array(
					new CButton('remove', _('Remove'), 'javascript: removeOperation('.$operationid.');', 'link_menu'),
					new CVar('operations['.$operationid.']', $operation)
				)
			)
		);
	}
	else {
		$operationRow = array(
			$details,
			array(
				new CSubmit('edit_operationid['.$operationid.']', _('Edit'), null, 'link_menu'),
				SPACE, SPACE, SPACE,
				array(
					new CButton('remove', _('Remove'), 'javascript: removeOperation('.$operationid.');', 'link_menu'),
					new CVar('operations['.$operationid.']', $operation)
				)
			)
		);
	}
	$operationsTable->addRow($operationRow, null, 'operations_'.$operationid);

	$operation['opmessage_grp'] = isset($operation['opmessage_grp']) ? zbx_toHash($operation['opmessage_grp'], 'usrgrpid') : null;
	$operation['opmessage_usr'] = isset($operation['opmessage_usr']) ? zbx_toHash($operation['opmessage_usr'], 'userid') : null;
	$operation['opcommand_grp'] = isset($operation['opcommand_grp']) ? zbx_toHash($operation['opcommand_grp'], 'groupid') : null;
	$operation['opcommand_hst'] = isset($operation['opcommand_hst']) ? zbx_toHash($operation['opcommand_hst'], 'hostid') : null;
}

$footer = array();
if (empty($this->data['new_operation'])) {
	$footer[] = new CSubmit('new_operation', _('New'), null, 'link_menu');
}

$operationFormList->addRow(_('Action operations'), new CDiv(array($operationsTable, $footer), 'objectgroup inlineblock border_dotted ui-corner-all'));

// create new operation table
if (!empty($this->data['new_operation'])) {
	$newOperationsTable = new CTable(null, 'formElementTable');
	$newOperationsTable->addItem(new CVar('new_operation[actionid]', $this->data['actionid']));

	if (isset($this->data['new_operation']['id'])) {
		$newOperationsTable->addItem(new CVar('new_operation[id]', $this->data['new_operation']['id']));
	}
	if (isset($this->data['new_operation']['operationid'])) {
		$newOperationsTable->addItem(new CVar('new_operation[operationid]', $this->data['new_operation']['operationid']));
	}

	if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS || $this->data['eventsource'] == EVENT_SOURCE_INTERNAL) {
		$stepFrom = new CNumericBox('new_operation[esc_step_from]', $this->data['new_operation']['esc_step_from'], 5);
		$stepFrom->attr('size', 6);
		$stepFrom->addAction(
			'onchange',
			'javascript:'.$stepFrom->getAttribute('onchange').' if (this.value == 0) this.value = 1;'
		);

		$stepTo = new CNumericBox('new_operation[esc_step_to]', $this->data['new_operation']['esc_step_to'], 5);
		$stepTo->attr('size', 6);

		$stepTable = new CTable();
		$stepTable->addRow(array(_('From'), $stepFrom), 'indent_both');
		$stepTable->addRow(
			array(
				_('To'),
				new CCol(array($stepTo, SPACE, _('(0 - infinitely)')))
			),
			'indent_both'
		);

		$stepTable->addRow(
			array(
				_('Step duration'),
				new CCol(array(
					new CNumericBox('new_operation[esc_period]', $this->data['new_operation']['esc_period'], 6),
					SPACE,
					_('(minimum 60 seconds, 0 - use action default)')
				))
			),
			'indent_both'
		);

		$newOperationsTable->addRow(array(_('Step'), $stepTable));
	}

	// if multiple operation types are available, display a select
	if (count($this->data['allowedOperations']) > 1) {
		$operationTypeComboBox = new CComboBox(
			'new_operation[operationtype]',
			$this->data['new_operation']['operationtype'], 'submit()'
		);
		foreach ($this->data['allowedOperations'] as $operation) {
			$operationTypeComboBox->addItem($operation, operation_type2str($operation));
		}
		$newOperationsTable->addRow(array(_('Operation type'), $operationTypeComboBox), 'indent_both');
	}
	// if only one operation is available - show only the label
	else {
		$operation = $this->data['allowedOperations'][0];
		$newOperationsTable->addRow(array(
			_('Operation type'),
			array(operation_type2str($operation), new CVar('new_operation[operationtype]', $operation)),
		), 'indent_both');
	}

	switch ($this->data['new_operation']['operationtype']) {
		case OPERATION_TYPE_MESSAGE:
			if (!isset($this->data['new_operation']['opmessage'])) {
				$this->data['new_operation']['opmessage_usr'] = array();
				$this->data['new_operation']['opmessage'] = array('default_msg' => 1, 'mediatypeid' => 0);

				if ($this->data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
					$this->data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_TRIGGER;
					$this->data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_TRIGGER;
				}
				elseif ($this->data['eventsource'] == EVENT_SOURCE_DISCOVERY) {
					$this->data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_DISCOVERY;
					$this->data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_DISCOVERY;
				}
				elseif ($this->data['eventsource'] == EVENT_SOURCE_AUTO_REGISTRATION) {
					$this->data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_AUTOREG;
					$this->data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_AUTOREG;
				}
				else {
					$this->data['new_operation']['opmessage']['subject'] = '';
					$this->data['new_operation']['opmessage']['message'] = '';
				}
			}

			if (!isset($this->data['new_operation']['opmessage']['default_msg'])) {
				$this->data['new_operation']['opmessage']['default_msg'] = 0;
			}

			$usrgrpList = new CTable(null, 'formElementTable');
			$usrgrpList->setHeader(array(_('User group'), _('Action')));
			$usrgrpList->attr('style', 'min-width: 310px;');
			$usrgrpList->setAttribute('id', 'opmsgUsrgrpList');

			$addUsrgrpBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name&multiselect=1", 450, 450)', 'link_menu');
			$addUsrgrpBtn->attr('id', 'addusrgrpbtn');
			$usrgrpList->addRow(new CRow(new CCol($addUsrgrpBtn, null, 2), null, 'opmsgUsrgrpListFooter'));

			$userList = new CTable(null, 'formElementTable');
			$userList->setHeader(array(_('User'), _('Action')));
			$userList->attr('style', 'min-width: 310px;');
			$userList->setAttribute('id', 'opmsgUserList');

			$addUserBtn = new CButton('add', _('Add'), 'return PopUp("popup.php?dstfrm=action.edit&srctbl=users&srcfld1=userid&srcfld2=fullname&multiselect=1", 450, 450)', 'link_menu');
			$addUserBtn->attr('id', 'adduserbtn');
			$userList->addRow(new CRow(new CCol($addUserBtn, null, 2), null, 'opmsgUserListFooter'));

			// add participations
			$usrgrpids = isset($this->data['new_operation']['opmessage_grp'])
				? zbx_objectValues($this->data['new_operation']['opmessage_grp'], 'usrgrpid')
				: array();

			$userids = isset($this->data['new_operation']['opmessage_usr'])
				? zbx_objectValues($this->data['new_operation']['opmessage_usr'], 'userid')
				: array();

			$usrgrps = API::UserGroup()->get(array(
				'usrgrpids' => $usrgrpids,
				'output' => array('name')
			));
			order_result($usrgrps, 'name');

			$users = API::User()->get(array(
				'userids' => $userids,
				'output' => array('alias', 'name', 'surname')
			));
			order_result($users, 'alias');

			foreach ($users as &$user) {
				$user['fullname'] = getUserFullname($user);
			}
			unset($user);

			$jsInsert = 'addPopupValues('.zbx_jsvalue(array('object' => 'usrgrpid', 'values' => $usrgrps)).');';
			$jsInsert .= 'addPopupValues('.zbx_jsvalue(array('object' => 'userid', 'values' => $users)).');';
			zbx_add_post_js($jsInsert);

			$newOperationsTable->addRow(array(_('Send to User groups'), new CDiv($usrgrpList, 'objectgroup inlineblock border_dotted ui-corner-all')));
			$newOperationsTable->addRow(array(_('Send to Users'), new CDiv($userList, 'objectgroup inlineblock border_dotted ui-corner-all')));

			$mediaTypeComboBox = new CComboBox('new_operation[opmessage][mediatypeid]', $this->data['new_operation']['opmessage']['mediatypeid']);
			$mediaTypeComboBox->addItem(0, '- '._('All').' -');

			$dbMediaTypes = DBfetchArray(DBselect('SELECT mt.mediatypeid,mt.description FROM media_type mt'));

			order_result($dbMediaTypes, 'description');

			foreach ($dbMediaTypes as $dbMediaType) {
				$mediaTypeComboBox->addItem($dbMediaType['mediatypeid'], $dbMediaType['description']);
			}

			$newOperationsTable->addRow(array(_('Send only to'), $mediaTypeComboBox));
			$newOperationsTable->addRow(
				array(
					_('Default message'),
					new CCheckBox('new_operation[opmessage][default_msg]', $this->data['new_operation']['opmessage']['default_msg'], 'javascript: submit();', 1)
				),
				'indent_top'
			);

			if (!$this->data['new_operation']['opmessage']['default_msg']) {
				$newOperationsTable->addRow(array(
					_('Subject'),
					new CTextBox('new_operation[opmessage][subject]', $this->data['new_operation']['opmessage']['subject'], ZBX_TEXTBOX_STANDARD_SIZE)
				));
				$newOperationsTable->addRow(array(
					_('Message'),
					new CTextArea('new_operation[opmessage][message]', $this->data['new_operation']['opmessage']['message'])
				));
			}
			else {
				$newOperationsTable->addItem(new CVar('new_operation[opmessage][subject]', $this->data['new_operation']['opmessage']['subject']));
				$newOperationsTable->addItem(new CVar('new_operation[opmessage][message]', $this->data['new_operation']['opmessage']['message']));
			}
			break;

		case OPERATION_TYPE_COMMAND:
			if (!isset($this->data['new_operation']['opcommand'])) {
				$this->data['new_operation']['opcommand'] = array();
			}

			$this->data['new_operation']['opcommand']['type'] = isset($this->data['new_operation']['opcommand']['type'])
				? $this->data['new_operation']['opcommand']['type'] : ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT;
			$this->data['new_operation']['opcommand']['scriptid'] = isset($this->data['new_operation']['opcommand']['scriptid'])
				? $this->data['new_operation']['opcommand']['scriptid'] : '';
			$this->data['new_operation']['opcommand']['execute_on'] = isset($this->data['new_operation']['opcommand']['execute_on'])
				? $this->data['new_operation']['opcommand']['execute_on'] : ZBX_SCRIPT_EXECUTE_ON_AGENT;
			$this->data['new_operation']['opcommand']['publickey'] = isset($this->data['new_operation']['opcommand']['publickey'])
				? $this->data['new_operation']['opcommand']['publickey'] : '';
			$this->data['new_operation']['opcommand']['privatekey'] = isset($this->data['new_operation']['opcommand']['privatekey'])
				? $this->data['new_operation']['opcommand']['privatekey'] : '';
			$this->data['new_operation']['opcommand']['authtype'] = isset($this->data['new_operation']['opcommand']['authtype'])
				? $this->data['new_operation']['opcommand']['authtype'] : ITEM_AUTHTYPE_PASSWORD;
			$this->data['new_operation']['opcommand']['username'] = isset($this->data['new_operation']['opcommand']['username'])
				? $this->data['new_operation']['opcommand']['username'] : '';
			$this->data['new_operation']['opcommand']['password'] = isset($this->data['new_operation']['opcommand']['password'])
				? $this->data['new_operation']['opcommand']['password'] : '';
			$this->data['new_operation']['opcommand']['port'] = isset($this->data['new_operation']['opcommand']['port'])
				? $this->data['new_operation']['opcommand']['port'] : '';
			$this->data['new_operation']['opcommand']['command'] = isset($this->data['new_operation']['opcommand']['command'])
				? $this->data['new_operation']['opcommand']['command'] : '';

			$this->data['new_operation']['opcommand']['script'] = '';
			if (!zbx_empty($this->data['new_operation']['opcommand']['scriptid'])) {
				$userScripts = API::Script()->get(array(
					'scriptids' => $this->data['new_operation']['opcommand']['scriptid'],
					'output' => API_OUTPUT_EXTEND
				));
				if ($userScript = reset($userScripts)) {
					$this->data['new_operation']['opcommand']['script'] = $userScript['name'];
				}
			}

			$cmdList = new CTable(null, 'formElementTable');
			$cmdList->attr('style', 'min-width: 310px;');
			$cmdList->setHeader(array(_('Target'), _('Action')));

			$addCmdBtn = new CButton('add', _('New'), 'javascript: showOpCmdForm(0, "new");', 'link_menu');
			$cmdList->addRow(new CRow(new CCol($addCmdBtn, null, 3), null, 'opCmdListFooter'));

			// add participations
			if (!isset($this->data['new_operation']['opcommand_grp'])) {
				$this->data['new_operation']['opcommand_grp'] = array();
			}
			if (!isset($this->data['new_operation']['opcommand_hst'])) {
				$this->data['new_operation']['opcommand_hst'] = array();
			}

			$hosts = API::Host()->get(array(
				'hostids' => zbx_objectValues($this->data['new_operation']['opcommand_hst'], 'hostid'),
				'output' => array('hostid', 'name'),
				'preservekeys' => true,
				'editable' => true
			));

			$this->data['new_operation']['opcommand_hst'] = array_values($this->data['new_operation']['opcommand_hst']);
			foreach ($this->data['new_operation']['opcommand_hst'] as $ohnum => $cmd) {
				$this->data['new_operation']['opcommand_hst'][$ohnum]['name'] = ($cmd['hostid'] > 0) ? $hosts[$cmd['hostid']]['name'] : '';
			}
			order_result($this->data['new_operation']['opcommand_hst'], 'name');

			$groups = API::HostGroup()->get(array(
				'groupids' => zbx_objectValues($this->data['new_operation']['opcommand_grp'], 'groupid'),
				'output' => array('groupid', 'name'),
				'preservekeys' => true,
				'editable' => true
			));

			$this->data['new_operation']['opcommand_grp'] = array_values($this->data['new_operation']['opcommand_grp']);
			foreach ($this->data['new_operation']['opcommand_grp'] as $ognum => $cmd) {
				$this->data['new_operation']['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
			}
			order_result($this->data['new_operation']['opcommand_grp'], 'name');

			// js add commands
			$jsInsert = 'addPopupValues('.zbx_jsvalue(array('object' => 'hostid', 'values' => $this->data['new_operation']['opcommand_hst'])).');';
			$jsInsert .= 'addPopupValues('.zbx_jsvalue(array('object' => 'groupid', 'values' => $this->data['new_operation']['opcommand_grp'])).');';
			zbx_add_post_js($jsInsert);

			// target list
			$cmdList = new CDiv($cmdList, 'objectgroup border_dotted ui-corner-all inlineblock');
			$cmdList->setAttribute('id', 'opCmdList');
			$newOperationsTable->addRow(array(_('Target list'), $cmdList), 'indent_top');

			// type
			$typeComboBox = new CComboBox('new_operation[opcommand][type]', $this->data['new_operation']['opcommand']['type'], 'javascript: showOpTypeForm();');
			$typeComboBox->addItem(ZBX_SCRIPT_TYPE_IPMI, _('IPMI'));
			$typeComboBox->addItem(ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, _('Custom script'));
			$typeComboBox->addItem(ZBX_SCRIPT_TYPE_SSH, _('SSH'));
			$typeComboBox->addItem(ZBX_SCRIPT_TYPE_TELNET, _('Telnet'));
			$typeComboBox->addItem(ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT, _('Global script'));

			$userScriptId = new CVar('new_operation[opcommand][scriptid]', $this->data['new_operation']['opcommand']['scriptid']);
			$userScriptName = new CTextBox('new_operation[opcommand][script]', $this->data['new_operation']['opcommand']['script'], 32, true);
			$userScriptSelect = new CButton('select_opcommand_script', _('Select'), null, 'link_menu');

			$userScript = new CDiv(array($userScriptId, $userScriptName, SPACE, $userScriptSelect), 'class_opcommand_userscript inlineblock hidden');

			$newOperationsTable->addRow(array(_('Type'), array($typeComboBox, SPACE, $userScript)), 'indent_bottom');

			// script
			$executeOnRadioButton = new CRadioButtonList('new_operation[opcommand][execute_on]', $this->data['new_operation']['opcommand']['execute_on']);
			$executeOnRadioButton->makeVertical();
			$executeOnRadioButton->addValue(SPACE._('Zabbix agent').SPACE, ZBX_SCRIPT_EXECUTE_ON_AGENT);
			$executeOnRadioButton->addValue(SPACE._('Zabbix server').SPACE, ZBX_SCRIPT_EXECUTE_ON_SERVER);
			$newOperationsTable->addRow(array(_('Execute on'), new CDiv($executeOnRadioButton, 'objectgroup border_dotted ui-corner-all inlineblock')), 'class_opcommand_execute_on hidden indent_both');

			// ssh
			$authTypeComboBox = new CComboBox('new_operation[opcommand][authtype]', $this->data['new_operation']['opcommand']['authtype'], 'javascript: showOpTypeAuth();');
			$authTypeComboBox->addItem(ITEM_AUTHTYPE_PASSWORD, _('Password'));
			$authTypeComboBox->addItem(ITEM_AUTHTYPE_PUBLICKEY, _('Public key'));

			$newOperationsTable->addRow(
				array(
					_('Authentication method'),
					$authTypeComboBox
				),
				'class_authentication_method hidden'
			);
			$newOperationsTable->addRow(
				array(
					_('User name'),
					new CTextBox('new_operation[opcommand][username]', $this->data['new_operation']['opcommand']['username'], ZBX_TEXTBOX_SMALL_SIZE)
				),
				'class_authentication_username hidden indent_both'
			);
			$newOperationsTable->addRow(
				array(
					_('Public key file'),
					new CTextBox('new_operation[opcommand][publickey]', $this->data['new_operation']['opcommand']['publickey'], ZBX_TEXTBOX_SMALL_SIZE)
				),
				'class_authentication_publickey hidden indent_both'
			);
			$newOperationsTable->addRow(
				array(
					_('Private key file'),
					new CTextBox('new_operation[opcommand][privatekey]', $this->data['new_operation']['opcommand']['privatekey'], ZBX_TEXTBOX_SMALL_SIZE)
				),
				'class_authentication_privatekey hidden indent_both'
			);
			$newOperationsTable->addRow(
				array(
					_('Password'),
					new CTextBox('new_operation[opcommand][password]', $this->data['new_operation']['opcommand']['password'], ZBX_TEXTBOX_SMALL_SIZE)
				),
				'class_authentication_password hidden indent_both'
			);

			// set custom id because otherwise they are set based on name (sick!) and produce duplicate ids
			$passphraseCB = new CTextBox('new_operation[opcommand][password]', $this->data['new_operation']['opcommand']['password'], ZBX_TEXTBOX_SMALL_SIZE);
			$passphraseCB->attr('id', 'new_operation_opcommand_passphrase');
			$newOperationsTable->addRow(array(_('Key passphrase'), $passphraseCB), 'class_authentication_passphrase hidden');

			// ssh && telnet
			$newOperationsTable->addRow(
				array(
					_('Port'),
					new CTextBox('new_operation[opcommand][port]', $this->data['new_operation']['opcommand']['port'], ZBX_TEXTBOX_SMALL_SIZE)
				),
				'class_opcommand_port hidden indent_both'
			);

			// command
			$commandTextArea = new CTextArea('new_operation[opcommand][command]', $this->data['new_operation']['opcommand']['command']);
			$newOperationsTable->addRow(array(_('Commands'), $commandTextArea), 'class_opcommand_command hidden indent_both');

			$commandIpmiTextBox = new CTextBox('new_operation[opcommand][command]', $this->data['new_operation']['opcommand']['command'], ZBX_TEXTBOX_STANDARD_SIZE);
			$commandIpmiTextBox->attr('id', 'opcommand_command_ipmi');
			$newOperationsTable->addRow(array(_('Commands'), $commandIpmiTextBox), 'class_opcommand_command_ipmi hidden indent_both');
			break;

		case OPERATION_TYPE_HOST_ADD:
		case OPERATION_TYPE_HOST_REMOVE:
		case OPERATION_TYPE_HOST_ENABLE:
		case OPERATION_TYPE_HOST_DISABLE:
			$newOperationsTable->addItem(new CVar('new_operation[object]', 0));
			$newOperationsTable->addItem(new CVar('new_operation[objectid]', 0));
			$newOperationsTable->addItem(new CVar('new_operation[shortdata]', ''));
			$newOperationsTable->addItem(new CVar('new_operation[longdata]', ''));
			break;

		case OPERATION_TYPE_GROUP_ADD:
		case OPERATION_TYPE_GROUP_REMOVE:
			if (!isset($this->data['new_operation']['opgroup'])) {
				$this->data['new_operation']['opgroup'] = array();
			}

			$groupList = new CTable();
			$groupList->setAttribute('id', 'opGroupList');
			$groupList->addRow(new CRow(
				new CCol(
					new CMultiSelect(array(
						'name' => 'discoveryHostGroup',
						'objectName' => 'hostGroup',
						'objectOptions' => array('editable' => true),
						'popup' => array(
							'parameters' => 'srctbl=host_groups&dstfrm='.$actionForm->getName().
								'&dstfld1=discoveryHostGroup&srcfld1=groupid&writeonly=1&multiselect=1',
							'width' => 450,
							'height' => 450
						)
					)),
					null, 2
				),
				null,
				'opGroupListFooter'
			));
			$groupList->addRow(new CCol(
				new CButton('add', _('Add'), 'return addDiscoveryHostGroup();', 'link_menu'), null, 2
			));

			// load host groups
			$groupIds = isset($this->data['new_operation']['opgroup'])
				? zbx_objectValues($this->data['new_operation']['opgroup'], 'groupid')
				: array();

			if ($groupIds) {
				$hostGroups = API::HostGroup()->get(array(
					'groupids' => $groupIds,
					'output' => array('groupid', 'name')
				));
				order_result($hostGroups, 'name');

				$jsInsert = '';
				$jsInsert .= 'addPopupValues('.zbx_jsvalue(array('object' => 'dsc_groupid', 'values' => $hostGroups)).');';
				zbx_add_post_js($jsInsert);
			}

			$caption = (OPERATION_TYPE_GROUP_ADD == $this->data['new_operation']['operationtype'])
				? _('Add to host groups')
				: _('Remove from host groups');

			$newOperationsTable->addRow(array($caption, new CDiv($groupList, 'objectgroup inlineblock border_dotted ui-corner-all')));
			break;

		case OPERATION_TYPE_TEMPLATE_ADD:
		case OPERATION_TYPE_TEMPLATE_REMOVE:
			if (!isset($this->data['new_operation']['optemplate'])) {
				$this->data['new_operation']['optemplate'] = array();
			}

			$templateList = new CTable();
			$templateList->setAttribute('id', 'opTemplateList');
			$templateList->addRow(new CRow(
				new CCol(
					new CMultiSelect(array(
						'name' => 'discoveryTemplates',
						'objectName' => 'templates',
						'objectOptions' => array('editable' => true),
						'popup' => array(
							'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$actionForm->getName().
								'&dstfld1=discoveryTemplates&templated_hosts=1&multiselect=1&writeonly=1',
							'width' => 450,
							'height' => 450
						)
					)),
					null, 2
				),
				null,
				'opTemplateListFooter'
			));
			$templateList->addRow(new CCol(
				new CButton('add', _('Add'), 'return addDiscoveryTemplates();', 'link_menu'), null, 2
			));

			// load templates
			$templateIds = isset($this->data['new_operation']['optemplate'])
				? zbx_objectValues($this->data['new_operation']['optemplate'], 'templateid')
				: array();

			if ($templateIds) {
				$templates = API::Template()->get(array(
					'templateids' => $templateIds,
					'output' => array('templateid', 'name')
				));
				order_result($templates, 'name');

				$jsInsert = '';
				$jsInsert .= 'addPopupValues('.zbx_jsvalue(array('object' => 'dsc_templateid', 'values' => $templates)).');';
				zbx_add_post_js($jsInsert);
			}

			$caption = (OPERATION_TYPE_TEMPLATE_ADD == $this->data['new_operation']['operationtype'])
				? _('Link with templates')
				: _('Unlink from templates');

			$newOperationsTable->addRow(array($caption, new CDiv($templateList, 'objectgroup border_dotted ui-corner-all')));
			break;
	}

	// append operation conditions to form list
	if ($this->data['eventsource'] == 0) {
		if (!isset($this->data['new_operation']['opconditions'])) {
			$this->data['new_operation']['opconditions'] = array();
		}
		else {
			zbx_rksort($this->data['new_operation']['opconditions']);
		}

		$allowed_opconditions = get_opconditions_by_eventsource($this->data['eventsource']);
		$grouped_opconditions = array();

		$operationConditionsTable = new CTable(_('No conditions defined.'), 'formElementTable');
		$operationConditionsTable->attr('id', 'operationConditionTable');
		$operationConditionsTable->attr('style', 'min-width: 310px;');
		$operationConditionsTable->setHeader(array(_('Label'), _('Name'), _('Action')));

		$i = 0;
		foreach ($this->data['new_operation']['opconditions'] as $opcondition) {
			if (!isset($opcondition['conditiontype'])) {
				$opcondition['conditiontype'] = 0;
			}
			if (!isset($opcondition['operator'])) {
				$opcondition['operator'] = 0;
			}
			if (!isset($opcondition['value'])) {
				$opcondition['value'] = 0;
			}
			if (!str_in_array($opcondition['conditiontype'], $allowed_opconditions)) {
				continue;
			}

			$label = num2letter($i);
			$labelCol = new CCol($label, 'label');
			$labelCol->setAttribute('data-conditiontype', $opcondition['conditiontype']);
			$labelCol->setAttribute('data-formulaid', $label);
			$operationConditionsTable->addRow(
				array(
					$labelCol,
					get_condition_desc($opcondition['conditiontype'], $opcondition['operator'], $opcondition['value']),
					array(
						new CButton('remove', _('Remove'), 'javascript: removeOperationCondition('.$i.');', 'link_menu'),
						new CVar('new_operation[opconditions]['.$i.'][conditiontype]', $opcondition['conditiontype']),
						new CVar('new_operation[opconditions]['.$i.'][operator]', $opcondition['operator']),
						new CVar('new_operation[opconditions]['.$i.'][value]', $opcondition['value'])
					)
				),
				null, 'opconditions_'.$i
			);

			$i++;
		}

		$calcTypeComboBox = new CComboBox('new_operation[evaltype]', $this->data['new_operation']['evaltype'], 'submit()');
		$calcTypeComboBox->attr('id', 'operationEvaltype');
		$calcTypeComboBox->addItem(CONDITION_EVAL_TYPE_AND_OR, _('And/Or'));
		$calcTypeComboBox->addItem(CONDITION_EVAL_TYPE_AND, _('And'));
		$calcTypeComboBox->addItem(CONDITION_EVAL_TYPE_OR, _('Or'));

		$newOperationsTable->addRow(array(
				_('Type of calculation'),
				array($calcTypeComboBox, new CSpan('', null, 'operationConditionLabel'))
			),
			null, 'operationConditionRow'
		);

		if (!isset($_REQUEST['new_opcondition'])) {
			$operationConditionsTable->addRow(new CCol(new CSubmit('new_opcondition', _('New'), null, 'link_menu')));
		}
		$newOperationsTable->addRow(array(_('Conditions'), new CDiv($operationConditionsTable, 'objectgroup inlineblock border_dotted ui-corner-all')), 'indent_top');
	}

	// append new operation condition to form list
	if (isset($_REQUEST['new_opcondition'])) {
		$newOperationConditionTable = new CTable(null, 'formElementTable');

		$allowedOpConditions = get_opconditions_by_eventsource($this->data['eventsource']);

		$new_opcondition = getRequest('new_opcondition', array());
		if (!is_array($new_opcondition)) {
			$new_opcondition = array();
		}

		if (empty($new_opcondition)) {
			$new_opcondition['conditiontype'] = CONDITION_TYPE_EVENT_ACKNOWLEDGED;
			$new_opcondition['operator'] = CONDITION_OPERATOR_LIKE;
			$new_opcondition['value'] = 0;
		}

		if (!str_in_array($new_opcondition['conditiontype'], $allowedOpConditions)) {
			$new_opcondition['conditiontype'] = $allowedOpConditions[0];
		}

		$rowCondition = array();
		$conditionTypeComboBox = new CComboBox('new_opcondition[conditiontype]', $new_opcondition['conditiontype'], 'submit()');
		foreach ($allowedOpConditions as $opcondition) {
			$conditionTypeComboBox->addItem($opcondition, condition_type2str($opcondition));
		}
		array_push($rowCondition, $conditionTypeComboBox);

		$operationConditionComboBox = new CComboBox('new_opcondition[operator]');
		foreach (get_operators_by_conditiontype($new_opcondition['conditiontype']) as $operationCondition) {
			$operationConditionComboBox->addItem($operationCondition, condition_operator2str($operationCondition));
		}
		array_push($rowCondition, $operationConditionComboBox);

		if ($new_opcondition['conditiontype'] == CONDITION_TYPE_EVENT_ACKNOWLEDGED) {
			$operationConditionValueComboBox = new CComboBox('new_opcondition[value]', $new_opcondition['value']);
			$operationConditionValueComboBox->addItem(0, _('Not Ack'));
			$operationConditionValueComboBox->addItem(1, _('Ack'));
			$rowCondition[] = $operationConditionValueComboBox;
		}
		$newOperationConditionTable->addRow($rowCondition);

		$newOperationConditionFooter = array(
			new CSubmit('add_opcondition', _('Add'), null, 'link_menu'),
			SPACE.SPACE,
			new CSubmit('cancel_new_opcondition', _('Cancel'), null, 'link_menu')
		);

		$newOperationsTable->addRow(array(_('Operation condition'), new CDiv(array($newOperationConditionTable, $newOperationConditionFooter), 'objectgroup inlineblock border_dotted ui-corner-all')));
	}

	$footer = array(
		new CSubmit('add_operation', (isset($this->data['new_operation']['id'])) ? _('Update') : _('Add'), null, 'link_menu'),
		SPACE.SPACE,
		new CSubmit('cancel_new_operation', _('Cancel'), null, 'link_menu')
	);
	$operationFormList->addRow(_('Operation details'), new CDiv(array($newOperationsTable, $footer), 'objectgroup floatleft border_dotted ui-corner-all'));
}

// append tabs to form
$actionTabs = new CTabView();
if (!hasRequest('form_refresh')) {
	$actionTabs->setSelected(0);
}
$actionTabs->addTab('actionTab', _('Action'), $actionFormList);
$actionTabs->addTab('conditionTab', _('Conditions'), $conditionFormList);
$actionTabs->addTab('operationTab', _('Operations'), $operationFormList);
$actionForm->addItem($actionTabs);

// append buttons to form
$others = array();
if (!empty($this->data['actionid'])) {
	$actionForm->addItem(makeFormFooter(
		new CSubmit('update', _('Update')),
		array(
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete current action?'),
				url_param('form').url_param('eventsource').url_param('actionid')
			),
			new CButtonCancel(url_param('actiontype'))
		)
	));
}
else {
	$actionForm->addItem(makeFormFooter(
		new CSubmit('add', _('Add')),
		new CButtonCancel(url_param('actiontype'))
	));
}


// append form to widget
$actionWidget->addItem($actionForm);

return $actionWidget;
