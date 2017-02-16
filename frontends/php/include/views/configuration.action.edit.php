<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

$widget = (new CWidget())->setTitle(_('Actions'));

// create form
$actionForm = (new CForm())
	->setName('action.edit')
	->addVar('form', $data['form'])
	->addVar('eventsource', $data['eventsource']);

if ($data['actionid']) {
	$actionForm->addVar('actionid', $data['actionid']);
}

// Action tab.
$action_tab = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $data['action']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('conditionTable')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$i = 0;

if ($data['action']['filter']['conditions']) {
	$actionConditionStringValues = actionConditionValueToString([$data['action']], $data['config']);

	foreach ($data['action']['filter']['conditions'] as $cIdx => $condition) {
		if (!isset($condition['conditiontype'])) {
			$condition['conditiontype'] = 0;
		}
		if (!isset($condition['operator'])) {
			$condition['operator'] = 0;
		}
		if (!isset($condition['value'])) {
			$condition['value'] = '';
		}
		if (!array_key_exists('value2', $condition)) {
			$condition['value2'] = '';
		}
		if (!str_in_array($condition['conditiontype'], $data['allowedConditions'])) {
			continue;
		}

		$label = isset($condition['formulaid']) ? $condition['formulaid'] : num2letter($i);

		$labelSpan = (new CSpan($label))
			->addClass('label')
			->setAttribute('data-conditiontype', $condition['conditiontype'])
			->setAttribute('data-formulaid', $label);

		$condition_table->addRow(
			[
				$labelSpan,
				getConditionDescription($condition['conditiontype'], $condition['operator'],
					$actionConditionStringValues[0][$cIdx], $condition['value2']
				),
				(new CCol([
					(new CButton('remove', _('Remove')))
						->onClick('javascript: removeCondition('.$i.');')
						->addClass(ZBX_STYLE_BTN_LINK),
					new CVar('conditions['.$i.']', $condition)
				]))->addClass(ZBX_STYLE_NOWRAP)
			],
			null, 'conditions_'.$i
		);

		$i++;
	}
}

$formula = (new CTextBox('formula', $data['action']['filter']['formula']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setId('formula')
	->setAttribute('placeholder', 'A or (B and C) &hellip;');

$calculationTypeComboBox = new CComboBox('evaltype', $data['action']['filter']['evaltype'],
	'processTypeOfCalculation()',
	[
		CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
		CONDITION_EVAL_TYPE_AND => _('And'),
		CONDITION_EVAL_TYPE_OR => _('Or'),
		CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
	]
);

$action_tab->addRow(_('Type of calculation'), [
	$calculationTypeComboBox,
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CSpan())->setId('conditionLabel'),
	$formula
]);
$action_tab->addRow(_('Conditions'),
	(new CDiv($condition_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append new condition to form list
$conditionTypeComboBox = new CComboBox('new_condition[conditiontype]', $data['new_condition']['conditiontype'], 'submit()');
foreach ($data['allowedConditions'] as $key => $condition) {
	$data['allowedConditions'][$key] = [
		'name' => condition_type2str($condition),
		'type' => $condition
	];
}
order_result($data['allowedConditions'], 'name');
foreach ($data['allowedConditions'] as $condition) {
	$conditionTypeComboBox->addItem($condition['type'], $condition['name']);
}

$conditionOperatorsComboBox = new CComboBox('new_condition[operator]', $data['new_condition']['operator']);
foreach (get_operators_by_conditiontype($data['new_condition']['conditiontype']) as $operator) {
	$conditionOperatorsComboBox->addItem($operator, condition_operator2str($operator));
}

$condition2 = null;

switch ($data['new_condition']['conditiontype']) {
	case CONDITION_TYPE_HOST_GROUP:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[value][]',
			'objectName' => 'hostGroup',
			'objectOptions' => [
				'editable' => true
			],
			'defaultValue' => 0,
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=groupid&writeonly=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_TEMPLATE:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[value][]',
			'objectName' => 'templates',
			'objectOptions' => [
				'editable' => true
			],
			'defaultValue' => 0,
			'popup' => [
				'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$actionForm->getName().
					'&dstfld1=new_condition_value_&templated_hosts=1&multiselect=1&writeonly=1'
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_HOST:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[value][]',
			'objectName' => 'hosts',
			'objectOptions' => [
				'editable' => true
			],
			'defaultValue' => 0,
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=hostid&writeonly=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_TRIGGER:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[value][]',
			'objectName' => 'triggers',
			'objectOptions' => [
				'editable' => true
			],
			'defaultValue' => 0,
			'popup' => [
				'parameters' => 'srctbl=triggers&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value_'.
					'&srcfld1=triggerid&writeonly=1&multiselect=1&noempty=1'
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_TRIGGER_NAME:
		$condition = (new CTextBox('new_condition[value]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_TIME_PERIOD:
		$condition = (new CTextBox('new_condition[value]', ZBX_DEFAULT_INTERVAL))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_TRIGGER_SEVERITY:
		$severityNames = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severityNames[] = getSeverityName($severity, $data['config']);
		}
		$condition = new CComboBox('new_condition[value]', null, null, $severityNames);
		break;

	case CONDITION_TYPE_MAINTENANCE:
		$condition = _('maintenance');
		break;

	case CONDITION_TYPE_DRULE:
		$action_tab->addItem(new CVar('new_condition[value]', '0'));
		$condition = [
			(new CTextBox('drule', '', true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('btn1', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.php?srctbl=drules&srcfld1=druleid&srcfld2=name'.
					'&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value&dstfld2=drule");'
				)
		];
		break;

	case CONDITION_TYPE_DCHECK:
		$action_tab->addItem(new CVar('new_condition[value]', '0'));
		$condition = [
			(new CTextBox('dcheck', '', true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('btn1', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.php?srctbl=dchecks&srcfld1=dcheckid&srcfld2=name'.
					'&dstfrm='.$actionForm->getName().'&dstfld1=new_condition_value&dstfld2=dcheck&writeonly=1");'
				)
		];
		break;

	case CONDITION_TYPE_PROXY:
		$action_tab->addItem(new CVar('new_condition[value]', '0'));
		$condition = [
			(new CTextBox('proxy', '', true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('btn1', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp('.
						'"popup.php?srctbl=proxies&srcfld1=hostid&srcfld2=host&dstfrm='.$actionForm->getName().
						'&dstfld1=new_condition_value&dstfld2=proxy"'.
					')'
				)
		];
		break;

	case CONDITION_TYPE_DHOST_IP:
		$condition = (new CTextBox('new_condition[value]', '192.168.0.1-127,192.168.2.1'))
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_DSERVICE_TYPE:
		$discoveryCheckTypes = discovery_check_type2str();
		order_result($discoveryCheckTypes);

		$condition = new CComboBox('new_condition[value]', null, null, $discoveryCheckTypes);
		break;

	case CONDITION_TYPE_DSERVICE_PORT:
		$condition = (new CTextBox('new_condition[value]', '0-1023,1024-49151'))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_DSTATUS:
		$condition = new CComboBox('new_condition[value]');
		foreach ([DOBJECT_STATUS_UP, DOBJECT_STATUS_DOWN, DOBJECT_STATUS_DISCOVER, DOBJECT_STATUS_LOST] as $stat) {
			$condition->addItem($stat, discovery_object_status2str($stat));
		}
		break;

	case CONDITION_TYPE_DOBJECT:
		$condition = new CComboBox('new_condition[value]');
		foreach ([EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE] as $object) {
			$condition->addItem($object, discovery_object2str($object));
		}
		break;

	case CONDITION_TYPE_DUPTIME:
		$condition = (new CNumericBox('new_condition[value]', 600, 15))->setWidth(ZBX_TEXTAREA_NUMERIC_BIG_WIDTH);
		break;

	case CONDITION_TYPE_DVALUE:
		$condition = (new CTextBox('new_condition[value]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_APPLICATION:
		$condition = (new CTextBox('new_condition[value]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_HOST_NAME:
		$condition = (new CTextBox('new_condition[value]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_EVENT_TYPE:
		$condition = new CComboBox('new_condition[value]', null, null, eventType());
		break;

	case CONDITION_TYPE_HOST_METADATA:
		$condition = (new CTextBox('new_condition[value]', ''))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case CONDITION_TYPE_EVENT_TAG:
		$condition = (new CTextBox('new_condition[value]', ''))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag'));
		break;

	case CONDITION_TYPE_EVENT_TAG_VALUE:
		$condition = (new CTextBox('new_condition[value]', ''))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value'));

		$condition2 = (new CTextBox('new_condition[value2]', ''))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag'));
		break;

	default:
		$condition = null;
}

$action_tab->addRow(_('New condition'),
	(new CDiv(
		(new CTable())
			->setAttribute('style', 'width: 100%;')
			->addRow(
				new CCol([
					$conditionTypeComboBox,
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$condition2,
					$condition2 === null ? null : (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$conditionOperatorsComboBox,
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					$condition
				])
			)
			->addRow(
				(new CSimpleButton(_('Add')))
					->onClick('javascript: submitFormWithParam("'.$actionForm->getName().'", "add_condition", "1");')
					->addClass(ZBX_STYLE_BTN_LINK)
			)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$action_tab->addRow(_('Enabled'),
	(new CCheckBox('status', ACTION_STATUS_ENABLED))->setChecked($data['action']['status'] == ACTION_STATUS_ENABLED)
);

// Operations tab.
$operation_tab = new CFormList('operationlist');

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operation_tab->addRow(_('Default operation step duration'), [
		(new CNumericBox('esc_period', $data['action']['esc_period'], 6))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
		' ('._('minimum 60 seconds').')'
	]);
}

$operation_tab
	->addRow(_('Default subject'),
		(new CTextBox('def_shortdata', $data['action']['def_shortdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Default message'),
		(new CTextArea('def_longdata', $data['action']['def_longdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$operation_tab->addRow(_('Pause operations while in maintenance'),
		(new CCheckBox('maintenance_mode', ACTION_MAINTENANCE_MODE_PAUSE))
			->setChecked($data['action']['maintenance_mode'] == ACTION_MAINTENANCE_MODE_PAUSE)
	);
}

// create operation table
$operationsTable = (new CTable())->setAttribute('style', 'width: 100%;');
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$operationsTable->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration (sec)'), _('Action')]);
	$delay = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);
}
else {
	$operationsTable->setHeader([_('Details'), _('Action')]);
}

if ($data['action']['operations']) {
	$actionOperationDescriptions = getActionOperationDescriptions([$data['action']], ACTION_OPERATION);

	$default_message = [
		'subject' => $data['action']['def_shortdata'],
		'message' => $data['action']['def_longdata']
	];

	$action_operation_hints = getActionOperationHints($data['action']['operations'], $default_message);

	foreach ($data['action']['operations'] as $operationid => $operation) {
		if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_OPERATION])) {
			continue;
		}
		if (!isset($operation['opconditions'])) {
			$operation['opconditions'] = [];
		}
		if (!isset($operation['mediatypeid'])) {
			$operation['mediatypeid'] = 0;
		}

		$details = (new CSpan($actionOperationDescriptions[0][$operationid]))
			->setHint($action_operation_hints[$operationid]);

		if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
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
				? convert_units(['value' => $delay[$operation['esc_step_from']], 'units' => 'uptime'])
				: _('Immediately');

			$operationRow = [
				$esc_steps_txt,
				$details,
				$esc_delay_txt,
				$esc_period_txt,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('javascript: submitFormWithParam('.
								'"'.$actionForm->getName().'", "edit_operationid['.$operationid.']", "1"'.
							');')
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('javascript: removeOperation('.$operationid.', '.ACTION_OPERATION.');')
								->addClass(ZBX_STYLE_BTN_LINK),
							new CVar('operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			];
		}
		else {
			$operationRow = [
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('javascript: submitFormWithParam('.
								'"'.$actionForm->getName().'", "edit_operationid['.$operationid.']", "1"'.
							');')
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('javascript: removeOperation('.$operationid.', '.ACTION_OPERATION.');')
								->addClass(ZBX_STYLE_BTN_LINK),
							new CVar('operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			];
		}
		$operationsTable->addRow($operationRow, null, 'operations_'.$operationid);

		$operation['opmessage_grp'] = isset($operation['opmessage_grp'])
			? zbx_toHash($operation['opmessage_grp'], 'usrgrpid')
			: null;
		$operation['opmessage_usr'] = isset($operation['opmessage_usr'])
			? zbx_toHash($operation['opmessage_usr'], 'userid')
			: null;
		$operation['opcommand_grp'] = isset($operation['opcommand_grp'])
			? zbx_toHash($operation['opcommand_grp'], 'groupid')
			: null;
		$operation['opcommand_hst'] = isset($operation['opcommand_hst'])
			? zbx_toHash($operation['opcommand_hst'], 'hostid')
			: null;
	}
}

$footer = null;
if (empty($data['new_operation'])) {
	$footer = (new CSimpleButton(_('New')))
		->onClick('javascript: submitFormWithParam("'.$actionForm->getName().'", "new_operation", "1");')
		->addClass(ZBX_STYLE_BTN_LINK);
}

$operation_tab->addRow(_('Operations'),
	(new CDiv([$operationsTable, $footer]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// create new operation table
if (!empty($data['new_operation'])) {
	$new_operation_vars = [
		new CVar('new_operation[actionid]', $data['actionid'])
	];

	$new_operation_formlist = (new CFormList())->setAttribute('style', 'width: 100%;');

	if (isset($data['new_operation']['id'])) {
		$new_operation_vars[] = new CVar('new_operation[id]', $data['new_operation']['id']);
	}
	if (isset($data['new_operation']['operationid'])) {
		$new_operation_vars[] = new CVar('new_operation[operationid]', $data['new_operation']['operationid']);
	}

	if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
		$stepFrom = (new CNumericBox('new_operation[esc_step_from]', $data['new_operation']['esc_step_from'], 5))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
		$stepFrom->onChange('javascript:'.$stepFrom->getAttribute('onchange').' if (this.value == 0) this.value = 1;');

		$new_operation_formlist->addRow(_('Steps'), [
			$stepFrom,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			'-',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('new_operation[esc_step_to]', $data['new_operation']['esc_step_to'], 5))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('(0 - infinitely)')
		]);

		$new_operation_formlist->addRow(_('Step duration'), [
			(new CNumericBox('new_operation[esc_period]', $data['new_operation']['esc_period'], 6))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('(minimum 60 seconds, 0 - use action default)')
		]);
	}

	// if only one operation is available - show only the label
	if (count($data['allowedOperations'][ACTION_OPERATION]) == 1) {
		$operation = $data['allowedOperations'][ACTION_OPERATION][0];
		$new_operation_formlist->addRow(_('Operation type'),
			[operation_type2str($operation), new CVar('new_operation[operationtype]', $operation)]
		);
	}
	// if multiple operation types are available, display a select
	else {
		$operationTypeComboBox = new CComboBox('new_operation[operationtype]',
			$data['new_operation']['operationtype'], 'submit()'
		);
		foreach ($data['allowedOperations'][ACTION_OPERATION] as $operation) {
			$operationTypeComboBox->addItem($operation, operation_type2str($operation));
		}
		$new_operation_formlist->addRow(_('Operation type'), $operationTypeComboBox);
	}

	switch ($data['new_operation']['operationtype']) {
		case OPERATION_TYPE_MESSAGE:
			if (!isset($data['new_operation']['opmessage'])) {
				$data['new_operation']['opmessage_usr'] = [];
				$data['new_operation']['opmessage'] = ['default_msg' => 1, 'mediatypeid' => 0];

				if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
					$data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_TRIGGER;
					$data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_TRIGGER;
				}
				elseif ($data['eventsource'] == EVENT_SOURCE_DISCOVERY) {
					$data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_DISCOVERY;
					$data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_DISCOVERY;
				}
				elseif ($data['eventsource'] == EVENT_SOURCE_AUTO_REGISTRATION) {
					$data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_AUTOREG;
					$data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_AUTOREG;
				}
				else {
					$data['new_operation']['opmessage']['subject'] = '';
					$data['new_operation']['opmessage']['message'] = '';
				}
			}

			if (!isset($data['new_operation']['opmessage']['default_msg'])) {
				$data['new_operation']['opmessage']['default_msg'] = 0;
			}

			$usrgrpList = (new CTable())
				->setAttribute('style', 'width: 100%;')
				->setHeader([_('User group'), _('Action')]);

			$addUsrgrpBtn = (new CButton(null, _('Add')))
				->onClick('return PopUp("'.
						'popup.php?dstfrm=action.edit&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name&multiselect=1'.
						'&dstfld1=opmsgUsrgrpListFooter"'.
					')'
				)
				->addClass(ZBX_STYLE_BTN_LINK);
			$usrgrpList->addRow(
				(new CRow(
					(new CCol($addUsrgrpBtn))->setColSpan(2)
				))->setId('opmsgUsrgrpListFooter')
			);

			$userList = (new CTable())
				->setAttribute('style', 'width: 100%;')
				->setHeader([_('User'), _('Action')]);

			$addUserBtn = (new CButton(null, _('Add')))
				->onClick('return PopUp('.
						'"popup.php?dstfrm=action.edit&srctbl=users&srcfld1=userid&srcfld2=fullname&multiselect=1'.
						'&dstfld1=opmsgUserListFooter"'.
					')'
				)
				->addClass(ZBX_STYLE_BTN_LINK);
			$userList->addRow(
				(new CRow(
					(new CCol($addUserBtn))->setColSpan(2)
				))->setId('opmsgUserListFooter')
			);

			// add participations
			$usrgrpids = isset($data['new_operation']['opmessage_grp'])
				? zbx_objectValues($data['new_operation']['opmessage_grp'], 'usrgrpid')
				: [];

			$userids = isset($data['new_operation']['opmessage_usr'])
				? zbx_objectValues($data['new_operation']['opmessage_usr'], 'userid')
				: [];

			$usrgrps = API::UserGroup()->get([
				'usrgrpids' => $usrgrpids,
				'output' => ['name']
			]);
			order_result($usrgrps, 'name');

			$users = API::User()->get([
				'userids' => $userids,
				'output' => ['userid', 'alias', 'name', 'surname']
			]);
			order_result($users, 'alias');

			foreach ($users as &$user) {
				$user['id'] = $user['userid'];
				$user['name'] = getUserFullname($user);
			}
			unset($user);

			$js_insert = 'addPopupValues('.
				zbx_jsvalue(['object' => 'usrgrpid', 'values' => $usrgrps, 'parentId' => 'opmsgUsrgrpListFooter']).
			');';
			$js_insert .= 'addPopupValues('.
				zbx_jsvalue(['object' => 'userid', 'values' => $users, 'parentId' => 'opmsgUserListFooter']).
			');';
			zbx_add_post_js($js_insert);

			$new_operation_formlist
				->addRow(_('Send to User groups'),
					(new CDiv($usrgrpList))
						->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
						->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				)
				->addRow(_('Send to Users'),
					(new CDiv($userList))
						->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
						->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
				);

			$mediaTypeComboBox = (new CComboBox('new_operation[opmessage][mediatypeid]', $data['new_operation']['opmessage']['mediatypeid']))
				->addItem(0, '- '._('All').' -');

			$dbMediaTypes = DBfetchArray(DBselect('SELECT mt.mediatypeid,mt.description FROM media_type mt'));

			order_result($dbMediaTypes, 'description');

			foreach ($dbMediaTypes as $dbMediaType) {
				$mediaTypeComboBox->addItem($dbMediaType['mediatypeid'], $dbMediaType['description']);
			}

			$new_operation_formlist
				->addRow(_('Send only to'), $mediaTypeComboBox)
				->addRow(_('Default message'),
					(new CCheckBox('new_operation[opmessage][default_msg]'))
						->setChecked($data['new_operation']['opmessage']['default_msg'] == 1)
				)
				->addRow(_('Subject'),
					(new CTextBox('new_operation[opmessage][subject]', $data['new_operation']['opmessage']['subject']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
				->addRow(_('Message'),
					(new CTextArea('new_operation[opmessage][message]', $data['new_operation']['opmessage']['message']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				);
			break;

		case OPERATION_TYPE_COMMAND:
			if (!isset($data['new_operation']['opcommand'])) {
				$data['new_operation']['opcommand'] = [];
			}

			$data['new_operation']['opcommand']['type'] = isset($data['new_operation']['opcommand']['type'])
				? $data['new_operation']['opcommand']['type'] : ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT;
			$data['new_operation']['opcommand']['scriptid'] = isset($data['new_operation']['opcommand']['scriptid'])
				? $data['new_operation']['opcommand']['scriptid'] : '';
			$data['new_operation']['opcommand']['execute_on'] = isset($data['new_operation']['opcommand']['execute_on'])
				? $data['new_operation']['opcommand']['execute_on'] : ZBX_SCRIPT_EXECUTE_ON_AGENT;
			$data['new_operation']['opcommand']['publickey'] = isset($data['new_operation']['opcommand']['publickey'])
				? $data['new_operation']['opcommand']['publickey'] : '';
			$data['new_operation']['opcommand']['privatekey'] = isset($data['new_operation']['opcommand']['privatekey'])
				? $data['new_operation']['opcommand']['privatekey'] : '';
			$data['new_operation']['opcommand']['authtype'] = isset($data['new_operation']['opcommand']['authtype'])
				? $data['new_operation']['opcommand']['authtype'] : ITEM_AUTHTYPE_PASSWORD;
			$data['new_operation']['opcommand']['username'] = isset($data['new_operation']['opcommand']['username'])
				? $data['new_operation']['opcommand']['username'] : '';
			$data['new_operation']['opcommand']['password'] = isset($data['new_operation']['opcommand']['password'])
				? $data['new_operation']['opcommand']['password'] : '';
			$data['new_operation']['opcommand']['port'] = isset($data['new_operation']['opcommand']['port'])
				? $data['new_operation']['opcommand']['port'] : '';
			$data['new_operation']['opcommand']['command'] = isset($data['new_operation']['opcommand']['command'])
				? $data['new_operation']['opcommand']['command'] : '';

			$data['new_operation']['opcommand']['script'] = '';
			if (!zbx_empty($data['new_operation']['opcommand']['scriptid'])) {
				$userScripts = API::Script()->get([
					'scriptids' => $data['new_operation']['opcommand']['scriptid'],
					'output' => API_OUTPUT_EXTEND
				]);
				if ($userScript = reset($userScripts)) {
					$data['new_operation']['opcommand']['script'] = $userScript['name'];
				}
			}

			// add participations
			if (!isset($data['new_operation']['opcommand_grp'])) {
				$data['new_operation']['opcommand_grp'] = [];
			}
			if (!isset($data['new_operation']['opcommand_hst'])) {
				$data['new_operation']['opcommand_hst'] = [];
			}

			$hosts = API::Host()->get([
				'hostids' => zbx_objectValues($data['new_operation']['opcommand_hst'], 'hostid'),
				'output' => ['hostid', 'name'],
				'preservekeys' => true,
				'editable' => true
			]);

			$data['new_operation']['opcommand_hst'] = array_values($data['new_operation']['opcommand_hst']);
			foreach ($data['new_operation']['opcommand_hst'] as $ohnum => $cmd) {
				$data['new_operation']['opcommand_hst'][$ohnum]['name'] = ($cmd['hostid'] > 0) ? $hosts[$cmd['hostid']]['name'] : '';
			}
			order_result($data['new_operation']['opcommand_hst'], 'name');

			$groups = API::HostGroup()->get([
				'groupids' => zbx_objectValues($data['new_operation']['opcommand_grp'], 'groupid'),
				'output' => ['groupid', 'name'],
				'preservekeys' => true,
				'editable' => true
			]);

			$data['new_operation']['opcommand_grp'] = array_values($data['new_operation']['opcommand_grp']);
			foreach ($data['new_operation']['opcommand_grp'] as $ognum => $cmd) {
				$data['new_operation']['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
			}
			order_result($data['new_operation']['opcommand_grp'], 'name');

			// js add commands
			$host_values = zbx_jsvalue([
				'object' => 'hostid',
				'values' => $data['new_operation']['opcommand_hst'],
				'parentId' => 'opCmdListFooter'
			]);

			$js_insert = 'addPopupValues('.$host_values.');';

			$group_values = zbx_jsvalue([
				'object' => 'groupid',
				'values' => $data['new_operation']['opcommand_grp'],
				'parentId' => 'opCmdListFooter'
			]);

			$js_insert .= 'addPopupValues('.$group_values.');';
			zbx_add_post_js($js_insert);

			// target list
			$new_operation_formlist->addRow(_('Target list'),
				(new CDiv(
					(new CTable())
						->setAttribute('style', 'width: 100%;')
						->setHeader([_('Target'), _('Action')])
						->addRow(
							(new CRow(
								(new CCol(
									(new CButton('add', _('New')))
										->onClick('javascript: showOpCmdForm(0, '.ACTION_OPERATION.');')
										->addClass(ZBX_STYLE_BTN_LINK)
								))->setColSpan(3)
							))->setId('opCmdListFooter')
						)
				))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setId('opCmdList')
			);

			// type
			$typeComboBox = new CComboBox('new_operation[opcommand][type]',
				$data['new_operation']['opcommand']['type'],
				'showOpTypeForm('.ACTION_OPERATION.')',	[
					ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
					ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
					ZBX_SCRIPT_TYPE_SSH => _('SSH'),
					ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
					ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
				]
			);

			$userScript = [
				new CVar('new_operation[opcommand][scriptid]', $data['new_operation']['opcommand']['scriptid']),
				(new CTextBox(
					'new_operation[opcommand][script]', $data['new_operation']['opcommand']['script'], true
				))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('select_operation_opcommand_script', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
			];

			$new_operation_formlist->addRow(_('Type'), $typeComboBox);
			$new_operation_formlist->addRow(_('Script name'), (new CDiv($userScript))->addClass(ZBX_STYLE_NOWRAP));

			// script
			$new_operation_formlist->addRow(_('Execute on'),
				(new CRadioButtonList('new_operation[opcommand][execute_on]',
					(int) $data['new_operation']['opcommand']['execute_on']
				))
					->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
					->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
					->setModern(true)
			);

			// ssh
			$authTypeComboBox = new CComboBox('new_operation[opcommand][authtype]',
				$data['new_operation']['opcommand']['authtype'],
				'showOpTypeAuth('.ACTION_OPERATION.')', [
					ITEM_AUTHTYPE_PASSWORD => _('Password'),
					ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
				]
			);

			$new_operation_formlist->addRow(_('Authentication method'), $authTypeComboBox);
			$new_operation_formlist->addRow(_('User name'),
				(new CTextBox('new_operation[opcommand][username]', $data['new_operation']['opcommand']['username']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
			$new_operation_formlist->addRow(_('Public key file'),
				(new CTextBox('new_operation[opcommand][publickey]', $data['new_operation']['opcommand']['publickey']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
			$new_operation_formlist->addRow(_('Private key file'),
				(new CTextBox('new_operation[opcommand][privatekey]', $data['new_operation']['opcommand']['privatekey']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);
			$new_operation_formlist->addRow(_('Password'),
				(new CTextBox('new_operation[opcommand][password]', $data['new_operation']['opcommand']['password']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);

			// set custom id because otherwise they are set based on name (sick!) and produce duplicate ids
			$passphraseCB = (new CTextBox('new_operation[opcommand][password]', $data['new_operation']['opcommand']['password']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setId('new_operation_opcommand_passphrase');
			$new_operation_formlist->addRow(_('Key passphrase'), $passphraseCB);

			// ssh && telnet
			$new_operation_formlist->addRow(_('Port'),
				(new CTextBox('new_operation[opcommand][port]', $data['new_operation']['opcommand']['port']))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			);

			// command
			$new_operation_formlist->addRow(_('Commands'),
				(new CTextArea('new_operation[opcommand][command]', $data['new_operation']['opcommand']['command']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
			$new_operation_formlist->addRow(_('Commands'),
				(new CTextBox('new_operation[opcommand][command]', $data['new_operation']['opcommand']['command']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setId('new_operation_opcommand_command_ipmi')
			);
			break;

		case OPERATION_TYPE_HOST_ADD:
		case OPERATION_TYPE_HOST_REMOVE:
		case OPERATION_TYPE_HOST_ENABLE:
		case OPERATION_TYPE_HOST_DISABLE:
			$new_operation_vars[] = new CVar('new_operation[object]', 0);
			$new_operation_vars[] = new CVar('new_operation[objectid]', 0);
			$new_operation_vars[] = new CVar('new_operation[shortdata]', '');
			$new_operation_vars[] = new CVar('new_operation[longdata]', '');
			break;

		case OPERATION_TYPE_GROUP_ADD:
		case OPERATION_TYPE_GROUP_REMOVE:
			$new_operation_formlist->addRow(_('Host groups'),
				(new CMultiSelect([
					'name' => 'new_operation[groupids][]',
					'objectName' => 'hostGroup',
					'objectOptions' => ['editable' => true],
					'data' => $data['new_operation']['groups'],
					'popup' => [
						'parameters' => 'srctbl=host_groups&dstfrm='.$actionForm->getName().
							'&dstfld1=new_operation_groupids_&srcfld1=groupid&writeonly=1&multiselect=1'
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
			break;

		case OPERATION_TYPE_TEMPLATE_ADD:
		case OPERATION_TYPE_TEMPLATE_REMOVE:
			$new_operation_formlist->addRow(_('Templates'),
				(new CMultiSelect([
					'name' => 'new_operation[templateids][]',
					'objectName' => 'templates',
					'objectOptions' => ['editable' => true],
					'data' => $data['new_operation']['templates'],
					'popup' => [
						'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm='.$actionForm->getName().
							'&dstfld1=new_operation_templateids_&templated_hosts=1&multiselect=1&writeonly=1'
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
			break;

		case OPERATION_TYPE_HOST_INVENTORY:
			$new_operation_formlist->addRow(_('Inventory mode'),
				(new CRadioButtonList('new_operation[opinventory][inventory_mode]',
					(int) $data['new_operation']['opinventory']['inventory_mode']
				))
					->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
					->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
					->setModern(true)
			);
			break;
	}

	// append operation conditions to form list
	if ($data['eventsource'] == 0) {
		if (!isset($data['new_operation']['opconditions'])) {
			$data['new_operation']['opconditions'] = [];
		}
		else {
			zbx_rksort($data['new_operation']['opconditions']);
		}

		$allowed_opconditions = get_opconditions_by_eventsource($data['eventsource']);
		$grouped_opconditions = [];

		$operationConditionsTable = (new CTable())
			->setAttribute('style', 'width: 100%;')
			->setId('operationConditionTable')
			->setHeader([_('Label'), _('Name'), _('Action')]);

		$i = 0;

		$operationConditionStringValues = actionOperationConditionValueToString(
			$data['new_operation']['opconditions']
		);

		foreach ($data['new_operation']['opconditions'] as $cIdx => $opcondition) {
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
			$labelCol = (new CCol($label))
				->addClass('label')
				->setAttribute('data-conditiontype', $opcondition['conditiontype'])
				->setAttribute('data-formulaid', $label);
			$operationConditionsTable->addRow([
					$labelCol,
					getConditionDescription($opcondition['conditiontype'], $opcondition['operator'],
						$operationConditionStringValues[$cIdx], ''
					),
					(new CCol([
						(new CButton('remove', _('Remove')))
							->onClick('javascript: removeOperationCondition('.$i.');')
							->addClass(ZBX_STYLE_BTN_LINK),
						new CVar('new_operation[opconditions]['.$i.'][conditiontype]', $opcondition['conditiontype']),
						new CVar('new_operation[opconditions]['.$i.'][operator]', $opcondition['operator']),
						new CVar('new_operation[opconditions]['.$i.'][value]', $opcondition['value'])
					]))->addClass(ZBX_STYLE_NOWRAP)
				],
				null, 'opconditions_'.$i
			);

			$i++;
		}

		$calcTypeComboBox = new CComboBox('new_operation[evaltype]', $data['new_operation']['evaltype'], 'submit()', [
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or')
		]);
		$calcTypeComboBox->setId('operationEvaltype');

		$new_operation_formlist->addRow(_('Type of calculation'), [
			$calcTypeComboBox,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CSpan())->setId('operationConditionLabel')
		]);

		if (!hasRequest('new_opcondition')) {
			$operationConditionsTable->addRow((new CCol(
				(new CSimpleButton(_('New')))
					->onClick('javascript: submitFormWithParam("'.$actionForm->getName().'", "new_opcondition", "1");')
					->addClass(ZBX_STYLE_BTN_LINK)
			))->setColspan(3));
		}
		$new_operation_formlist->addRow(_('Conditions'),
			(new CDiv($operationConditionsTable))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		);
	}

	// Append new operation condition to form list.
	if (hasRequest('new_opcondition')) {
		$newOperationConditionTable = (new CTable())->setAttribute('style', 'width: 100%;');

		$allowedOpConditions = get_opconditions_by_eventsource($data['eventsource']);

		$new_opcondition = getRequest('new_opcondition', []);
		if (!is_array($new_opcondition)) {
			$new_opcondition = [];
		}

		if (empty($new_opcondition)) {
			$new_opcondition['conditiontype'] = CONDITION_TYPE_EVENT_ACKNOWLEDGED;
			$new_opcondition['operator'] = CONDITION_OPERATOR_LIKE;
			$new_opcondition['value'] = 0;
		}

		if (!str_in_array($new_opcondition['conditiontype'], $allowedOpConditions)) {
			$new_opcondition['conditiontype'] = $allowedOpConditions[0];
		}

		$condition_types = [];
		foreach ($allowedOpConditions as $opcondition) {
			$condition_types[$opcondition] = condition_type2str($opcondition);
		}

		$operators = [];
		foreach (get_operators_by_conditiontype($new_opcondition['conditiontype']) as $operation_condition) {
			$operators[$operation_condition] = condition_operator2str($operation_condition);
		}

		$rowCondition = [
			new CComboBox('new_opcondition[conditiontype]', $new_opcondition['conditiontype'], 'submit()',
				$condition_types
			),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			new CComboBox('new_opcondition[operator]', null, null, $operators)
		];
		if ($new_opcondition['conditiontype'] == CONDITION_TYPE_EVENT_ACKNOWLEDGED) {
			$rowCondition[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
			$rowCondition[] = new CComboBox('new_opcondition[value]', $new_opcondition['value'], null, [
				0 => _('Not Ack'),
				1 => _('Ack')
			]);
		}
		$newOperationConditionTable->addRow(new CCol($rowCondition));

		$new_operation_formlist->addRow(_('Operation condition'),
			(new CDiv([
				$newOperationConditionTable,
				new CHorList([
					(new CSimpleButton(_('Add')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$actionForm->getName().'", "add_opcondition", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK),
					(new CSimpleButton(_('Cancel')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$actionForm->getName().'", "cancel_new_opcondition", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK)
				])
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		);
	}

	$operation_tab->addRow(_('Operation details'),
		(new CDiv([
			$new_operation_vars,
			$new_operation_formlist,
			new CHorList([
				(new CSimpleButton((isset($data['new_operation']['id'])) ? _('Update') : _('Add')))
					->onClick('javascript: submitFormWithParam("'.$actionForm->getName().'", "add_operation", "1");')
					->addClass(ZBX_STYLE_BTN_LINK),
				(new CSimpleButton(_('Cancel')))
					->onClick('javascript: submitFormWithParam('.
						'"'.$actionForm->getName().'", "cancel_new_operation", "1"'.
					');')
					->addClass(ZBX_STYLE_BTN_LINK)
			])
		]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}

// Append tabs to form.
$action_tabs = (new CTabView())
	->addTab('actionTab', _('Action'), $action_tab)
	->addTab('operationTab', _('Operations'), $operation_tab);

// Recovery operation tab.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$recovery_tab = (new CFormList('operationlist'))
		->addRow(_('Default subject'),
			(new CTextBox('r_shortdata', $data['action']['r_shortdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Default message'),
			(new CTextArea('r_longdata', $data['action']['r_longdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);

	// Create operation table.
	$operationsTable = (new CTable())->setAttribute('style', 'width: 100%;');
	$operationsTable->setHeader([_('Details'), _('Action')]);

	if ($data['action']['recovery_operations']) {
		$actionOperationDescriptions = getActionOperationDescriptions([$data['action']], ACTION_RECOVERY_OPERATION);

		$default_message = [
			'subject' => $data['action']['r_shortdata'],
			'message' => $data['action']['r_longdata']
		];

		$action_operation_hints = getActionOperationHints($data['action']['recovery_operations'], $default_message);

		foreach ($data['action']['recovery_operations'] as $operationid => $operation) {
			if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_RECOVERY_OPERATION])) {
				continue;
			}
			if (!isset($operation['opconditions'])) {
				$operation['opconditions'] = [];
			}
			if (!isset($operation['mediatypeid'])) {
				$operation['mediatypeid'] = 0;
			}

			$details = (new CSpan($actionOperationDescriptions[0][$operationid]))
				->setHint($action_operation_hints[$operationid]);

			$operationRow = [
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('javascript: submitFormWithParam('.
								'"'.$actionForm->getName().'", "edit_recovery_operationid['.$operationid.']", "1"'.
							');')
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick(
									'javascript: removeOperation('.$operationid.', '.ACTION_RECOVERY_OPERATION.');'
								)
								->addClass(ZBX_STYLE_BTN_LINK),
							new CVar('recovery_operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			];
			$operationsTable->addRow($operationRow, null, 'recovery_operations_'.$operationid);

			$operation['opmessage_grp'] = isset($operation['opmessage_grp'])
				? zbx_toHash($operation['opmessage_grp'], 'usrgrpid')
				: null;
			$operation['opmessage_usr'] = isset($operation['opmessage_usr'])
				? zbx_toHash($operation['opmessage_usr'], 'userid')
				: null;
			$operation['opcommand_grp'] = isset($operation['opcommand_grp'])
				? zbx_toHash($operation['opcommand_grp'], 'groupid')
				: null;
			$operation['opcommand_hst'] = isset($operation['opcommand_hst'])
				? zbx_toHash($operation['opcommand_hst'], 'hostid')
				: null;
		}
	}

	$footer = null;
	if (empty($data['new_recovery_operation'])) {
		$footer = (new CSimpleButton(_('New')))
			->onClick('javascript: submitFormWithParam("'.$actionForm->getName().'", "new_recovery_operation", "1");')
			->addClass(ZBX_STYLE_BTN_LINK);
	}

	$recovery_tab->addRow(_('Operations'),
		(new CDiv([$operationsTable, $footer]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

	// create new operation table
	if (!empty($data['new_recovery_operation'])) {
		$new_recovery_operation_vars = [
			new CVar('new_recovery_operation[actionid]', $data['actionid'])
		];

		$new_operation_formlist = (new CFormList())->setAttribute('style', 'width: 100%;');

		if (isset($data['new_recovery_operation']['id'])) {
			$new_recovery_operation_vars[] = new CVar('new_recovery_operation[id]',
				$data['new_recovery_operation']['id']
			);
		}
		if (isset($data['new_recovery_operation']['operationid'])) {
			$new_recovery_operation_vars[] = new CVar('new_recovery_operation[operationid]',
				$data['new_recovery_operation']['operationid']
			);
		}

		// if only one operation is available - show only the label
		if (count($data['allowedOperations'][ACTION_RECOVERY_OPERATION]) == 1) {
			$operation = $data['allowedOperations'][ACTION_RECOVERY_OPERATION][0];
			$new_operation_formlist->addRow(_('Operation type'),
				[operation_type2str($operation), new CVar('new_recovery_operation[operationtype]', $operation)]
			);
		}
		// if multiple operation types are available, display a select
		else {
			$operationTypeComboBox = new CComboBox('new_recovery_operation[operationtype]',
				$data['new_recovery_operation']['operationtype'], 'submit()'
			);
			foreach ($data['allowedOperations'][ACTION_RECOVERY_OPERATION] as $operation) {
				$operationTypeComboBox->addItem($operation, operation_type2str($operation));
			}
			$new_operation_formlist->addRow(_('Operation type'), $operationTypeComboBox);
		}

		switch ($data['new_recovery_operation']['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if (!array_key_exists('opmessage', $data['new_recovery_operation'])) {
					$data['new_recovery_operation']['opmessage_usr'] = [];
					$data['new_recovery_operation']['opmessage'] = ['default_msg' => 1, 'mediatypeid' => 0];

					if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
						$data['new_recovery_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_TRIGGER;
						$data['new_recovery_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_TRIGGER;
					}
					else {
						$data['new_recovery_operation']['opmessage']['subject'] = '';
						$data['new_recovery_operation']['opmessage']['message'] = '';
					}
				}

				if (!array_key_exists('default_msg', $data['new_recovery_operation']['opmessage'])) {
					$data['new_recovery_operation']['opmessage']['default_msg'] = 0;
				}

				if (!array_key_exists('mediatypeid', $data['new_recovery_operation']['opmessage'])) {
					$data['new_recovery_operation']['opmessage']['mediatypeid'] = 0;
				}

				$usrgrpList = (new CTable())
					->setAttribute('style', 'width: 100%;')
					->setHeader([_('User group'), _('Action')]);

				$addUsrgrpBtn = (new CButton(null, _('Add')))
					->onClick('return PopUp('.
							'"popup.php?dstfrm=action.edit&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name'.
							'&multiselect=1&dstfld1=recOpmsgUsrgrpListFooter"'.
						')'
					)
					->addClass(ZBX_STYLE_BTN_LINK);
				$usrgrpList->addRow(
					(new CRow(
						(new CCol($addUsrgrpBtn))->setColSpan(2)
					))->setId('recOpmsgUsrgrpListFooter')
				);

				$userList = (new CTable())
					->setAttribute('style', 'width: 100%;')
					->setHeader([_('User'), _('Action')]);

				$addUserBtn = (new CButton(null, _('Add')))
					->onClick('return PopUp('.
							'"popup.php?dstfrm=action.edit&srctbl=users&srcfld1=userid&srcfld2=fullname'.
							'&multiselect=1&dstfld1=recOpmsgUserListFooter"'.
						')'
					)
					->addClass(ZBX_STYLE_BTN_LINK);
				$userList->addRow(
					(new CRow(
						(new CCol($addUserBtn))->setColSpan(2)
					))->setId('recOpmsgUserListFooter')
				);

				// add participations
				$usrgrpids = isset($data['new_recovery_operation']['opmessage_grp'])
					? zbx_objectValues($data['new_recovery_operation']['opmessage_grp'], 'usrgrpid')
					: [];

				$userids = isset($data['new_recovery_operation']['opmessage_usr'])
					? zbx_objectValues($data['new_recovery_operation']['opmessage_usr'], 'userid')
					: [];

				$usrgrps = API::UserGroup()->get([
					'usrgrpids' => $usrgrpids,
					'output' => ['name']
				]);
				order_result($usrgrps, 'name');

				$users = API::User()->get([
					'userids' => $userids,
					'output' => ['userid', 'alias', 'name', 'surname']
				]);
				order_result($users, 'alias');

				foreach ($users as &$user) {
					$user['id'] = $user['userid'];
					$user['name'] = getUserFullname($user);
				}
				unset($user);

				$js_insert = 'addPopupValues('.zbx_jsvalue(['object' => 'usrgrpid', 'values' => $usrgrps,
					'parentId' => 'recOpmsgUsrgrpListFooter']).
				');';
				$js_insert .= 'addPopupValues('.zbx_jsvalue(['object' => 'userid', 'values' => $users,
					'parentId' => 'recOpmsgUserListFooter']).
				');';
				zbx_add_post_js($js_insert);

				$new_operation_formlist
					->addRow(_('Send to User groups'),
						(new CDiv($usrgrpList))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					)
					->addRow(_('Send to Users'),
						(new CDiv($userList))
							->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
							->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					);

				$mediaTypeComboBox = (new CComboBox('new_recovery_operation[opmessage][mediatypeid]',
					$data['new_recovery_operation']['opmessage']['mediatypeid'])
				)->addItem(0, '- '._('All').' -');

				$dbMediaTypes = DBfetchArray(DBselect('SELECT mt.mediatypeid,mt.description FROM media_type mt'));

				order_result($dbMediaTypes, 'description');

				foreach ($dbMediaTypes as $dbMediaType) {
					$mediaTypeComboBox->addItem($dbMediaType['mediatypeid'], $dbMediaType['description']);
				}

				$new_operation_formlist
					->addRow(_('Send only to'), $mediaTypeComboBox)
					->addRow(_('Default message'),
						(new CCheckBox('new_recovery_operation[opmessage][default_msg]'))
							->setChecked($data['new_recovery_operation']['opmessage']['default_msg'] == 1)
					)
					->addRow(_('Subject'),
						(new CTextBox('new_recovery_operation[opmessage][subject]',
							$data['new_recovery_operation']['opmessage']['subject']
						))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					)
					->addRow(_('Message'),
						(new CTextArea('new_recovery_operation[opmessage][message]',
							$data['new_recovery_operation']['opmessage']['message']
						))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					);
				break;

			case OPERATION_TYPE_COMMAND:
				if (!isset($data['new_recovery_operation']['opcommand'])) {
					$data['new_recovery_operation']['opcommand'] = [];
				}

				$data['new_recovery_operation']['opcommand']['type'] = isset($data['new_recovery_operation']['opcommand']['type'])
					? $data['new_recovery_operation']['opcommand']['type']
					: ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT;
				$data['new_recovery_operation']['opcommand']['scriptid'] = isset($data['new_recovery_operation']['opcommand']['scriptid'])
					? $data['new_recovery_operation']['opcommand']['scriptid']
					: '';
				$data['new_recovery_operation']['opcommand']['execute_on'] = isset($data['new_recovery_operation']['opcommand']['execute_on'])
					? $data['new_recovery_operation']['opcommand']['execute_on']
					: ZBX_SCRIPT_EXECUTE_ON_AGENT;
				$data['new_recovery_operation']['opcommand']['publickey'] = isset($data['new_recovery_operation']['opcommand']['publickey'])
					? $data['new_recovery_operation']['opcommand']['publickey']
					: '';
				$data['new_recovery_operation']['opcommand']['privatekey'] = isset($data['new_recovery_operation']['opcommand']['privatekey'])
					? $data['new_recovery_operation']['opcommand']['privatekey']
					: '';
				$data['new_recovery_operation']['opcommand']['authtype'] = isset($data['new_recovery_operation']['opcommand']['authtype'])
					? $data['new_recovery_operation']['opcommand']['authtype']
					: ITEM_AUTHTYPE_PASSWORD;
				$data['new_recovery_operation']['opcommand']['username'] = isset($data['new_recovery_operation']['opcommand']['username'])
					? $data['new_recovery_operation']['opcommand']['username']
					: '';
				$data['new_recovery_operation']['opcommand']['password'] = isset($data['new_recovery_operation']['opcommand']['password'])
					? $data['new_recovery_operation']['opcommand']['password']
					: '';
				$data['new_recovery_operation']['opcommand']['port'] = isset($data['new_recovery_operation']['opcommand']['port'])
					? $data['new_recovery_operation']['opcommand']['port']
					: '';
				$data['new_recovery_operation']['opcommand']['command'] = isset($data['new_recovery_operation']['opcommand']['command'])
					? $data['new_recovery_operation']['opcommand']['command']
					: '';

				$data['new_recovery_operation']['opcommand']['script'] = '';
				if (!zbx_empty($data['new_recovery_operation']['opcommand']['scriptid'])) {
					$userScripts = API::Script()->get([
						'scriptids' => $data['new_recovery_operation']['opcommand']['scriptid'],
						'output' => API_OUTPUT_EXTEND
					]);
					if ($userScript = reset($userScripts)) {
						$data['new_recovery_operation']['opcommand']['script'] = $userScript['name'];
					}
				}

				// add participations
				if (!isset($data['new_recovery_operation']['opcommand_grp'])) {
					$data['new_recovery_operation']['opcommand_grp'] = [];
				}
				if (!isset($data['new_recovery_operation']['opcommand_hst'])) {
					$data['new_recovery_operation']['opcommand_hst'] = [];
				}

				$hosts = API::Host()->get([
					'hostids' => zbx_objectValues($data['new_recovery_operation']['opcommand_hst'], 'hostid'),
					'output' => ['hostid', 'name'],
					'preservekeys' => true,
					'editable' => true
				]);

				$data['new_recovery_operation']['opcommand_hst'] = array_values(
					$data['new_recovery_operation']['opcommand_hst']
				);

				foreach ($data['new_recovery_operation']['opcommand_hst'] as $ohnum => $cmd) {
					$data['new_recovery_operation']['opcommand_hst'][$ohnum]['name'] = ($cmd['hostid'] > 0)
						? $hosts[$cmd['hostid']]['name']
						: '';
				}
				order_result($data['new_recovery_operation']['opcommand_hst'], 'name');

				$groups = API::HostGroup()->get([
					'groupids' => zbx_objectValues($data['new_recovery_operation']['opcommand_grp'], 'groupid'),
					'output' => ['groupid', 'name'],
					'preservekeys' => true,
					'editable' => true
				]);

				$data['new_recovery_operation']['opcommand_grp'] = array_values(
					$data['new_recovery_operation']['opcommand_grp']
				);

				foreach ($data['new_recovery_operation']['opcommand_grp'] as $ognum => $cmd) {
					$data['new_recovery_operation']['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
				}
				order_result($data['new_recovery_operation']['opcommand_grp'], 'name');

				// js add commands
				$host_values = zbx_jsvalue([
					'object' => 'hostid',
					'values' => $data['new_recovery_operation']['opcommand_hst'],
					'parentId' => 'recOpCmdListFooter'
				]);

				$js_insert = 'addPopupValues('.$host_values.');';

				$group_values = zbx_jsvalue([
					'object' => 'groupid',
					'values' => $data['new_recovery_operation']['opcommand_grp'],
					'parentId' => 'recOpCmdListFooter'
				]);

				$js_insert .= 'addPopupValues('.$group_values.');';
				zbx_add_post_js($js_insert);

				// target list
				$new_operation_formlist->addRow(_('Target list'),
					(new CDiv(
						(new CTable())
							->setAttribute('style', 'width: 100%;')
							->setHeader([_('Target'), _('Action')])
							->addRow(
								(new CRow(
									(new CCol(
										(new CButton('add', _('New')))
											->onClick('javascript: showOpCmdForm(0, '.ACTION_RECOVERY_OPERATION.');')
											->addClass(ZBX_STYLE_BTN_LINK)
									))->setColSpan(3)
								))->setId('recOpCmdListFooter')
							)
					))
						->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
						->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
						->setId('recOpCmdList')
				);

				// type
				$typeComboBox = new CComboBox('new_recovery_operation[opcommand][type]',
					$data['new_recovery_operation']['opcommand']['type'],
					'showOpTypeForm('.ACTION_RECOVERY_OPERATION.')', [
						ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
						ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
						ZBX_SCRIPT_TYPE_SSH => _('SSH'),
						ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
						ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
					]
				);

				$userScript = [
					new CVar('new_recovery_operation[opcommand][scriptid]',
						$data['new_recovery_operation']['opcommand']['scriptid']
					),
					(new CTextBox('new_recovery_operation[opcommand][script]',
						$data['new_recovery_operation']['opcommand']['script'], true
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('select_recovery_operation_opcommand_script', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
				];

				$new_operation_formlist->addRow(_('Type'), $typeComboBox);
				$new_operation_formlist->addRow(_('Script name'), $userScript);

				// script
				$new_operation_formlist->addRow(_('Execute on'),
					(new CRadioButtonList('new_recovery_operation[opcommand][execute_on]',
						(int) $data['new_recovery_operation']['opcommand']['execute_on']
					))
						->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
						->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
						->setModern(true)
				);

				// ssh
				$authTypeComboBox = new CComboBox('new_recovery_operation[opcommand][authtype]',
					$data['new_recovery_operation']['opcommand']['authtype'],
					'showOpTypeAuth('.ACTION_RECOVERY_OPERATION.')', [
						ITEM_AUTHTYPE_PASSWORD => _('Password'),
						ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
					]
				);

				$new_operation_formlist->addRow(_('Authentication method'), $authTypeComboBox);
				$new_operation_formlist->addRow(_('User name'),
					(new CTextBox('new_recovery_operation[opcommand][username]',
						$data['new_recovery_operation']['opcommand']['username']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				);
				$new_operation_formlist->addRow(_('Public key file'),
					(new CTextBox('new_recovery_operation[opcommand][publickey]',
						$data['new_recovery_operation']['opcommand']['publickey']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				);
				$new_operation_formlist->addRow(_('Private key file'),
					(new CTextBox('new_recovery_operation[opcommand][privatekey]',
						$data['new_recovery_operation']['opcommand']['privatekey']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				);
				$new_operation_formlist->addRow(_('Password'),
					(new CTextBox('new_recovery_operation[opcommand][password]',
						$data['new_recovery_operation']['opcommand']['password']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				);

				// set custom id because otherwise they are set based on name (sick!) and produce duplicate ids
				$passphraseCB = (new CTextBox('new_recovery_operation[opcommand][password]',
					$data['new_recovery_operation']['opcommand']['password']
				))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setId('new_recovery_operation_opcommand_passphrase');
				$new_operation_formlist->addRow(_('Key passphrase'), $passphraseCB);

				// ssh && telnet
				$new_operation_formlist->addRow(_('Port'),
					(new CTextBox('new_recovery_operation[opcommand][port]',
						$data['new_recovery_operation']['opcommand']['port']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				);

				// command
				$new_operation_formlist->addRow(_('Commands'),
					(new CTextArea('new_recovery_operation[opcommand][command]',
						$data['new_recovery_operation']['opcommand']['command']
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				);
				$new_operation_formlist->addRow(_('Commands'),
					(new CTextBox('new_recovery_operation[opcommand][command]',
						$data['new_recovery_operation']['opcommand']['command']
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('new_recovery_operation_opcommand_command_ipmi')
				);
				break;

			case OPERATION_TYPE_RECOVERY_MESSAGE:
				if (!array_key_exists('opmessage', $data['new_recovery_operation'])) {
					$data['new_recovery_operation']['opmessage_usr'] = [];
					$data['new_recovery_operation']['opmessage'] = ['default_msg' => 1];

					if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
						$data['new_recovery_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_TRIGGER;
						$data['new_recovery_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_TRIGGER;
					}
					else {
						$data['new_recovery_operation']['opmessage']['subject'] = '';
						$data['new_recovery_operation']['opmessage']['message'] = '';
					}
				}

				if (!array_key_exists('default_msg', $data['new_recovery_operation']['opmessage'])) {
					$data['new_recovery_operation']['opmessage']['default_msg'] = 0;
				}

				$new_operation_formlist
					->addRow(_('Default message'),
						(new CCheckBox('new_recovery_operation[opmessage][default_msg]'))
							->setChecked($data['new_recovery_operation']['opmessage']['default_msg'] == 1)
					)
					->addRow(_('Subject'),
						(new CTextBox('new_recovery_operation[opmessage][subject]',
								$data['new_recovery_operation']['opmessage']['subject']
						))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					)
					->addRow(_('Message'),
						(new CTextArea('new_recovery_operation[opmessage][message]',
							$data['new_recovery_operation']['opmessage']['message']
						))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					);
				break;
		}

		$recovery_tab->addRow(_('Operation details'),
			(new CDiv([
				$new_recovery_operation_vars,
				$new_operation_formlist,
				new CHorList([
					(new CSimpleButton((isset($data['new_recovery_operation']['id'])) ? _('Update') : _('Add')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$actionForm->getName().'", "add_recovery_operation", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK),
					(new CSimpleButton(_('Cancel')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$actionForm->getName().'", "cancel_new_recovery_operation", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK)
				])
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		);
	}

	$action_tabs->addTab('recoveryOperationTab', _('Recovery operations'), $recovery_tab);
}

if (!hasRequest('form_refresh')) {
	$action_tabs->setSelected(0);
}

// Append buttons to form.
$others = [];
if ($data['actionid']) {
	$action_tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete current action?'),
				url_param('form').url_param('eventsource').url_param('actionid')
			),
			new CButtonCancel(url_param('actiontype'))
		]
	));
}
else {
	$action_tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('actiontype'))]
	));
}

$actionForm->addItem($action_tabs);

// Append form to widget.
$widget->addItem($actionForm);

return $widget;
