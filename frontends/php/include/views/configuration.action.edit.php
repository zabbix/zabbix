<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['action']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
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
				'parameters' => [
					'srctbl' => 'host_groups',
					'dstfrm' => $actionForm->getName(),
					'dstfld1' => 'new_condition_value_',
					'srcfld1' => 'groupid',
					'writeonly' => '1',
					'multiselect' => '1'
				]
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
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $actionForm->getName(),
					'dstfld1' => 'new_condition_value_',
					'templated_hosts' => '1',
					'multiselect' => '1',
					'writeonly' => '1'
				]
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
				'parameters' => [
					'srctbl' => 'hosts',
					'dstfrm' => $actionForm->getName(),
					'dstfld1' => 'new_condition_value_',
					'srcfld1' => 'hostid',
					'writeonly' => '1',
					'multiselect' => '1'
				]
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
				'parameters' => [
					'srctbl' => 'triggers',
					'dstfrm' => $actionForm->getName(),
					'dstfld1' => 'new_condition_value_',
					'srcfld1' => 'triggerid',
					'writeonly' => '1',
					'multiselect' => '1',
					'noempty' => '1'
				]
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
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'drules',
						'srcfld1' => 'druleid',
						'srcfld2' => 'name',
						'dstfrm' => $actionForm->getName(),
						'dstfld1' => 'new_condition_value',
						'dstfld2' => 'drule'
					]).');'
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
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'dchecks',
						'srcfld1' => 'dcheckid',
						'srcfld2' => 'name',
						'dstfrm' => $actionForm->getName(),
						'dstfld1' => 'new_condition_value',
						'dstfld2' => 'dcheck',
						'writeonly' => '1'
					]).');'
				)
		];
		break;

	case CONDITION_TYPE_PROXY:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[value]',
			'objectName' => 'proxies',
			'selectedLimit' => 1,
			'defaultValue' => 0,
			'popup' => [
				'parameters' => [
					'srctbl' => 'proxies',
					'srcfld1' => 'proxyid',
					'srcfld2' => 'host',
					'dstfrm' => $actionForm->getName(),
					'dstfld1' => 'new_condition_value'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
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
	$operation_tab->addRow((new CLabel(_('Default operation step duration'), 'esc_period'))->setAsteriskMark(),
		(new CTextBox('esc_period', $data['action']['esc_period']))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	);
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
	$operationsTable->setHeader([_('Steps'), _('Details'), _('Start in'), _('Duration'), _('Action')]);
	$delays = count_operations_delay($data['action']['operations'], $data['action']['esc_period']);
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

	$simple_interval_parser = new CSimpleIntervalParser();

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

		$details = new CSpan($actionOperationDescriptions[0][$operationid]);

		if (array_key_exists($operationid, $action_operation_hints) && $action_operation_hints[$operationid]) {
			$details->setHint($action_operation_hints[$operationid]);
		}

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

			$esc_period_txt = ($simple_interval_parser->parse($operation['esc_period']) == CParser::PARSE_SUCCESS
					&& timeUnitToSeconds($operation['esc_period']) == 0)
				? _('Default')
				: $operation['esc_period'];

			$esc_delay_txt = ($delays[$operation['esc_step_from']] === null)
				? _('Unknown')
				: ($delays[$operation['esc_step_from']] != 0
					? convert_units(['value' => $delays[$operation['esc_step_from']], 'units' => 'uptime'])
					: _('Immediately')
				);

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
			(new CTextBox('new_operation[esc_period]', $data['new_operation']['esc_period']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('(0 - use action default)')
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
		$new_operation_formlist->addRow((new CLabel(_('Operation type'), 'new_operation[operationtype]')),
			$operationTypeComboBox
		);
	}

	switch ($data['new_operation']['operationtype']) {
		case OPERATION_TYPE_ACK_MESSAGE:
			if (!array_key_exists('opmessage', $data['new_operation'])) {
				$data['new_operation']['opmessage'] = [
					'default_msg'	=> 1,
					'mediatypeid'	=> 0,
					'subject'		=> ACTION_DEFAULT_SUBJ_ACKNOWLEDGE,
					'message'		=> ACTION_DEFAULT_MSG_ACKNOWLEDGE
				];
			}
			elseif (!array_key_exists('default_msg', $data['new_operation']['opmessage'])) {
				$data['new_operation']['opmessage']['default_msg'] = 0;
			}
			break;

		case OPERATION_TYPE_MESSAGE:
			if (!isset($data['new_operation']['opmessage'])) {
				$data['new_operation']['opmessage_usr'] = [];
				$data['new_operation']['opmessage'] = ['default_msg' => 1, 'mediatypeid' => 0];

				if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
					$data['new_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_PROBLEM;
					$data['new_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_PROBLEM;
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
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'usrgrp',
						'srcfld1' => 'usrgrpid',
						'srcfld2' => 'name',
						'dstfrm' => $actionForm->getName(),
						'dstfld1' => 'opmsgUsrgrpListFooter',
						'multiselect' => '1'
					]).');'
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
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'users',
						'srcfld1' => 'userid',
						'srcfld2' => 'fullname',
						'dstfrm' => $actionForm->getName(),
						'dstfld1' => 'opmsgUserListFooter',
						'multiselect' => '1'
					]).');'
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
				->addRow('', (new CLabel(_('At least one user or user group must be selected.')))->setAsteriskMark())
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
			$new_operation_formlist->addRow(
				(new CLabel(_('Target list'), 'opCmdList'))->setAsteriskMark(),
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

			$userScript = [
				new CVar('new_operation[opcommand][scriptid]', $data['new_operation']['opcommand']['scriptid']),
				(new CTextBox('new_operation[opcommand][script]', $data['new_operation']['opcommand']['script'], true))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired(),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				(new CButton('select_operation_opcommand_script', _('Select')))->addClass(ZBX_STYLE_BTN_GREY)
			];

			$new_operation_formlist
				// type
				->addRow(
					(new CLabel(_('Type'), 'new_operation[opcommand][type]')),
					(new CComboBox('new_operation[opcommand][type]',
						$data['new_operation']['opcommand']['type'],
						'showOpTypeForm('.ACTION_OPERATION.')',	[
							ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
							ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
							ZBX_SCRIPT_TYPE_SSH => _('SSH'),
							ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
							ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
						]
					))
				)
				->addRow(
					(new CLabel(_('Script name'), 'new_operation_opcommand_script'))->setAsteriskMark(),
					(new CDiv($userScript))->addClass(ZBX_STYLE_NOWRAP)
				)
				// script
				->addRow(
					(new CLabel(_('Execute on'), 'new_operation[opcommand][execute_on]')),
					(new CRadioButtonList('new_operation[opcommand][execute_on]',
						(int) $data['new_operation']['opcommand']['execute_on']
					))
						->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
						->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
						->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
						->setModern(true)
				)
				// ssh
				->addRow(_('Authentication method'),
					new CComboBox('new_operation[opcommand][authtype]',
						$data['new_operation']['opcommand']['authtype'],
						'showOpTypeAuth('.ACTION_OPERATION.')', [
							ITEM_AUTHTYPE_PASSWORD => _('Password'),
							ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
						]
					)
				)
				->addRow(
					(new CLabel(_('User name'), 'new_operation[opcommand][username]'))->setAsteriskMark(),
					(new CTextBox('new_operation[opcommand][username]',
						$data['new_operation']['opcommand']['username']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Public key file'), 'new_operation[opcommand][publickey]'))->setAsteriskMark(),
					(new CTextBox('new_operation[opcommand][publickey]',
						$data['new_operation']['opcommand']['publickey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Private key file'), 'new_operation[opcommand][privatekey]'))->setAsteriskMark(),
					(new CTextBox('new_operation[opcommand][privatekey]',
						$data['new_operation']['opcommand']['privatekey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(_('Password'),
					(new CTextBox('new_operation[opcommand][password]',
						$data['new_operation']['opcommand']['password']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				// set custom id because otherwise they are set based on name (sick!) and produce duplicate ids
				->addRow(_('Key passphrase'),
					(new CTextBox('new_operation[opcommand][password]',
						$data['new_operation']['opcommand']['password']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setId('new_operation_opcommand_passphrase')
				)
				// ssh && telnet
				->addRow(_('Port'),
					(new CTextBox('new_operation[opcommand][port]', $data['new_operation']['opcommand']['port']))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				// command
				->addRow(
					(new CLabel(_('Commands'), 'new_operation[opcommand][command]'))->setAsteriskMark(),
					(new CTextArea('new_operation[opcommand][command]', $data['new_operation']['opcommand']['command']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Commands'), 'new_operation[opcommand][command]'))->setAsteriskMark(),
					(new CTextBox('new_operation[opcommand][command]', $data['new_operation']['opcommand']['command']))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('new_operation_opcommand_command_ipmi')
						->setAriaRequired()
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
			$new_operation_formlist->addRow(
				(new CLabel(_('Host groups'), 'new_operation[groupids][]'))->setAsteriskMark(),
				(new CMultiSelect([
					'name' => 'new_operation[groupids][]',
					'objectName' => 'hostGroup',
					'objectOptions' => ['editable' => true],
					'data' => $data['new_operation']['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'dstfrm' => $actionForm->getName(),
							'dstfld1' => 'new_operation_groupids_',
							'srcfld1' => 'groupid',
							'writeonly' => '1',
							'multiselect' => '1'
						]
					]
				]))
					->setAriaRequired()
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
			break;

		case OPERATION_TYPE_TEMPLATE_ADD:
		case OPERATION_TYPE_TEMPLATE_REMOVE:
			$new_operation_formlist->addRow(
				(new CLabel(_('Templates'), 'new_operation[templateids][]'))->setAsteriskMark(),
				(new CMultiSelect([
					'name' => 'new_operation[templateids][]',
					'objectName' => 'templates',
					'objectOptions' => ['editable' => true],
					'data' => $data['new_operation']['templates'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'templates',
							'srcfld1' => 'hostid',
							'srcfld2' => 'host',
							'dstfrm' => $actionForm->getName(),
							'dstfld1' => 'new_operation_templateids_',
							'templated_hosts' => '1',
							'multiselect' => '1',
							'writeonly' => '1'
						]
					]
				]))
					->setAriaRequired()
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			);
			break;

		case OPERATION_TYPE_HOST_INVENTORY:
			$new_operation_formlist->addRow(
				(new CLabel(_('Inventory mode'), 'new_operation[opinventory][inventory_mode]')),
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

$bottom_note = _('At least one operation must exist.');

// Recovery operation tab.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL) {
	$bottom_note = _('At least one operation or recovery operation must exist.');
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

			$details = new CSpan($actionOperationDescriptions[0][$operationid]);

			if (array_key_exists($operationid, $action_operation_hints) && $action_operation_hints[$operationid]) {
				$details->setHint($action_operation_hints[$operationid]);
			}

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
			$new_operation_formlist->addRow((new CLabel(_('Operation type'), 'new_recovery_operation[operationtype]')),
				$operationTypeComboBox
			);
		}

		switch ($data['new_recovery_operation']['operationtype']) {
			case OPERATION_TYPE_MESSAGE:
				if (!array_key_exists('opmessage', $data['new_recovery_operation'])) {
					$data['new_recovery_operation']['opmessage_usr'] = [];
					$data['new_recovery_operation']['opmessage'] = ['default_msg' => 1, 'mediatypeid' => 0];

					if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
						$data['new_recovery_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_RECOVERY;
						$data['new_recovery_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_RECOVERY;
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
					->onClick('return PopUp("popup.generic",'.
						CJs::encodeJson([
							'srctbl' => 'usrgrp',
							'srcfld1' => 'usrgrpid',
							'srcfld2' => 'name',
							'dstfrm' => $actionForm->getName(),
							'dstfld1' => 'recOpmsgUsrgrpListFooter',
							'multiselect' => '1'
						]).');'
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
					->onClick('return PopUp("popup.generic",'.
						CJs::encodeJson([
							'srctbl' => 'users',
							'srcfld1' => 'userid',
							'srcfld2' => 'fullname',
							'dstfrm' => $actionForm->getName(),
							'dstfld1' => 'recOpmsgUserListFooter',
							'multiselect' => '1'
						]).');'
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
					->addRow('',
						(new CLabel(_('At least one user or user group must be selected.')))
							->setAsteriskMark()
					)
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
				$new_operation_formlist->addRow(
					(new CLabel(_('Target list'), 'recOpCmdList'))->setAsteriskMark(),
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
				$typeComboBox = (new CComboBox('new_recovery_operation[opcommand][type]',
					$data['new_recovery_operation']['opcommand']['type'],
					'showOpTypeForm('.ACTION_RECOVERY_OPERATION.')', [
						ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
						ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
						ZBX_SCRIPT_TYPE_SSH => _('SSH'),
						ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
						ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
					]
				));

				$userScript = [
					new CVar('new_recovery_operation[opcommand][scriptid]',
						$data['new_recovery_operation']['opcommand']['scriptid']
					),
					(new CTextBox('new_recovery_operation[opcommand][script]',
						$data['new_recovery_operation']['opcommand']['script'], true
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired(),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CButton('select_recovery_operation_opcommand_script', _('Select')))
						->addClass(ZBX_STYLE_BTN_GREY)
				];

				$new_operation_formlist->addRow((new CLabel(_('Type'), 'new_recovery_operation[opcommand][type]')),
					$typeComboBox
				);
				$new_operation_formlist->addRow(
					(new CLabel(_('Script name'), 'new_recovery_operation[opcommand][script]'))->setAsteriskMark(),
					(new CDiv($userScript))->addClass(ZBX_STYLE_NOWRAP)
				);

				// script
				$new_operation_formlist->addRow(
					(new CLabel(_('Execute on'), 'new_recovery_operation[opcommand][execute_on]')),
					(new CRadioButtonList('new_recovery_operation[opcommand][execute_on]',
						(int) $data['new_recovery_operation']['opcommand']['execute_on']
					))
						->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
						->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
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
				$new_operation_formlist->addRow(
					(new CLabel(_('User name'), 'new_recovery_operation[opcommand][username]'))->setAsteriskMark(),
					(new CTextBox('new_recovery_operation[opcommand][username]',
						$data['new_recovery_operation']['opcommand']['username']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				);
				$new_operation_formlist->addRow(
					(new CLabel(_('Public key file'), 'new_recovery_operation[opcommand][publickey]'))
						->setAsteriskMark(),
					(new CTextBox('new_recovery_operation[opcommand][publickey]',
						$data['new_recovery_operation']['opcommand']['publickey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				);
				$new_operation_formlist->addRow(
					(new CLabel(_('Private key file'), 'new_recovery_operation[opcommand][privatekey]'))
						->setAsteriskMark(),
					(new CTextBox('new_recovery_operation[opcommand][privatekey]',
						$data['new_recovery_operation']['opcommand']['privatekey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
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
				$new_operation_formlist->addRow(
					(new CLabel(_('Commands'), 'new_recovery_operation[opcommand][command]'))->setAsteriskMark(),
					(new CTextArea('new_recovery_operation[opcommand][command]',
						$data['new_recovery_operation']['opcommand']['command']
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired()
				);
				$new_operation_formlist->addRow(
					(new CLabel(_('Commands'), 'new_recovery_operation_opcommand_command_ipmi'))->setAsteriskMark(),
					(new CTextBox('new_recovery_operation[opcommand][command]',
						$data['new_recovery_operation']['opcommand']['command']
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('new_recovery_operation_opcommand_command_ipmi')
						->setAriaRequired()
				);
				break;

			case OPERATION_TYPE_RECOVERY_MESSAGE:
				if (!array_key_exists('opmessage', $data['new_recovery_operation'])) {
					$data['new_recovery_operation']['opmessage_usr'] = [];
					$data['new_recovery_operation']['opmessage'] = ['default_msg' => 1];

					if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
						$data['new_recovery_operation']['opmessage']['subject'] = ACTION_DEFAULT_SUBJ_RECOVERY;
						$data['new_recovery_operation']['opmessage']['message'] = ACTION_DEFAULT_MSG_RECOVERY;
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

// Acknowledge operations
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS) {
	$bottom_note = _('At least one operation, recovery operation or acknowledge operation must exist.');
	$action_formname = $actionForm->getName();

	$acknowledge_tab = (new CFormList('operationlist'))
		->addRow(_('Default subject'),
			(new CTextBox('ack_shortdata', $data['action']['ack_shortdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
		->addRow(_('Default message'),
			(new CTextArea('ack_longdata', $data['action']['ack_longdata']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);

	$operations_table = (new CTable())->setAttribute('style', 'width: 100%;');
	$operations_table->setHeader([_('Details'), _('Action')]);

	if ($data['action']['ack_operations']) {
		$operation_descriptions = getActionOperationDescriptions([$data['action']], ACTION_ACKNOWLEDGE_OPERATION);

		$default_message = [
			'subject' => $data['action']['ack_shortdata'],
			'message' => $data['action']['ack_longdata']
		];

		$operation_hints = getActionOperationHints($data['action']['ack_operations'], $default_message);

		foreach ($data['action']['ack_operations'] as $operationid => $operation) {
			if (!str_in_array($operation['operationtype'], $data['allowedOperations'][ACTION_ACKNOWLEDGE_OPERATION])) {
				continue;
			}
			$operation += [
				'opconditions'	=> [],
				'mediatypeid'	=> 0
			];

			$details = new CSpan($operation_descriptions[0][$operationid]);

			if (array_key_exists($operationid, $operation_hints) && $operation_hints[$operationid]) {
				$details->setHint($operation_hints[$operationid]);
			}

			$operations_table->addRow([
				$details,
				(new CCol(
					new CHorList([
						(new CSimpleButton(_('Edit')))
							->onClick('javascript: submitFormWithParam('.
								'"'.$action_formname.'", "edit_ack_operationid['.$operationid.']", "1");'
							)
							->addClass(ZBX_STYLE_BTN_LINK),
						[
							(new CButton('remove', _('Remove')))
								->onClick('javascript: removeOperation('.$operationid.', '.ACTION_ACKNOWLEDGE_OPERATION.
									');'
								)
								->addClass(ZBX_STYLE_BTN_LINK),
							new CVar('ack_operations['.$operationid.']', $operation)
						]
					])
				))->addClass(ZBX_STYLE_NOWRAP)
			], null, 'ack_operations_'.$operationid);

			$convert_to_hash = [
				'opmessage_grp' => 'usrgrpid',
				'opmessage_usr' => 'userid',
				'opcommand_grp' => 'groupid',
				'opcommand_hst' => 'hostid'
			];

			foreach ($convert_to_hash as $operation_key => $hash_key) {
				$operation[$operation_key] = array_key_exists($operation_key, $operation)
					? zbx_toHash($operation[$operation_key], $hash_key)
					: null;
			}
		}
	}

	if ($data['new_ack_operation']) {
		$new_operation_formlist = (new CFormList())->addStyle('width: 100%;');
		$new_ack_operation_vars = [];

		foreach (['id', 'operationid', 'actionid'] as $field) {
			if (array_key_exists($field, $data['new_ack_operation'])) {
				$new_ack_operation_vars[] = new CVar('new_ack_operation['.$field.']',
					$data['new_ack_operation'][$field]
				);
			}
		}

		$operationtype = new CComboBox('new_ack_operation[operationtype]', $data['new_ack_operation']['operationtype'],
			'submit()'
		);

		foreach ($data['allowedOperations'][ACTION_ACKNOWLEDGE_OPERATION] as $operation) {
			$operationtype->addItem($operation, operation_type2str($operation));
		}

		$new_operation_formlist->addRow((new CLabel(_('Operation type'), 'new_ack_operation[operationtype]')),
			$operationtype
		);

		$usrgrp_list = null;
		$user_list = null;

		if ($data['new_ack_operation']['operationtype'] == OPERATION_TYPE_MESSAGE) {
			$usrgrp_list = (new CTable())
				->addStyle('width: 100%;')
				->setHeader([_('User group'), _('Action')])
				->addRow(
					(new CRow(
						(new CCol(
							(new CButton(null, _('Add')))
								->onClick('return PopUp("popup.generic",'.
									CJs::encodeJson([
										'srctbl' => 'usrgrp',
										'srcfld1' => 'usrgrpid',
										'srcfld2' => 'name',
										'dstfrm' => $actionForm->getName(),
										'dstfld1' => 'ackOpmsgUsrgrpListFooter',
										'multiselect' => '1'
									]).');'
								)
								->addClass(ZBX_STYLE_BTN_LINK)
						))->setColSpan(2)
					))->setId('ackOpmsgUsrgrpListFooter')
				);

			$user_list = (new CTable())
				->addStyle('width: 100%;')
				->setHeader([_('User'), _('Action')])
				->addRow(
					(new CRow(
						(new CCol(
							(new CButton(null, _('Add')))
								->onClick('return PopUp("popup.generic",'.
									CJs::encodeJson([
										'srctbl' => 'users',
										'srcfld1' => 'userid',
										'srcfld2' => 'fullname',
										'dstfrm' => $actionForm->getName(),
										'dstfld1' => 'ackOpmsgUserListFooter',
										'multiselect' => '1'
									]).');'
								)
								->addClass(ZBX_STYLE_BTN_LINK)
						))->setColSpan(2)
					))->setId('ackOpmsgUserListFooter')
				);

			$usrgrpids = array_key_exists('opmessage_grp', $data['new_ack_operation'])
				? zbx_objectValues($data['new_ack_operation']['opmessage_grp'], 'usrgrpid')
				: [];

			$userids = array_key_exists('opmessage_usr', $data['new_ack_operation'])
				? zbx_objectValues($data['new_ack_operation']['opmessage_usr'], 'userid')
				: [];

			$usrgrps = API::UserGroup()->get([
				'output' => ['name'],
				'usrgrpids' => $usrgrpids
			]);
			order_result($usrgrps, 'name');

			$users = API::User()->get([
				'output' => ['userid', 'alias', 'name', 'surname'],
				'userids' => $userids
			]);
			order_result($users, 'alias');

			foreach ($users as &$user) {
				$user['id'] = $user['userid'];
				$user['name'] = getUserFullname($user);
			}
			unset($user);

			$js_insert = 'addPopupValues('.zbx_jsvalue(['object' => 'usrgrpid', 'values' => $usrgrps,
				'parentId' => 'ackOpmsgUsrgrpListFooter']).
			');';
			$js_insert .= 'addPopupValues('.zbx_jsvalue(['object' => 'userid', 'values' => $users,
				'parentId' => 'ackOpmsgUserListFooter']).
			');';
			zbx_add_post_js($js_insert);
		}
		elseif ($data['new_ack_operation']['operationtype'] == OPERATION_TYPE_COMMAND) {
			if (!array_key_exists('opcommand', $data['new_ack_operation'])) {
				$data['new_ack_operation']['opcommand'] = [];
			}

			$data['new_ack_operation'] += [
				'opcommand_grp'	=> [],
				'opcommand_hst' => []
			];
			$data['new_ack_operation']['opcommand'] += [
				'type'			=> ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scriptid'		=> '',
				'execute_on'	=> ZBX_SCRIPT_EXECUTE_ON_AGENT,
				'publickey'		=> '',
				'privatekey'	=> '',
				'authtype'		=> ITEM_AUTHTYPE_PASSWORD,
				'username'		=> '',
				'password'		=> '',
				'port'			=> '',
				'command'		=> ''
			];
			$script_name = '';

			if ($data['new_ack_operation']['opcommand']['scriptid']) {
				$user_scripts = API::Script()->get([
					'output' => ['name'],
					'scriptids' => $data['new_ack_operation']['opcommand']['scriptid']
				]);

				if ($user_scripts) {
					$user_script = reset($user_scripts);
					$script_name = $user_script['name'];
				}
			}
			$data['new_ack_operation']['opcommand']['script'] = $script_name;

			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => zbx_objectValues($data['new_ack_operation']['opcommand_hst'], 'hostid'),
				'preservekeys' => true,
				'editable' => true
			]);

			$data['new_ack_operation']['opcommand_hst'] = array_values($data['new_ack_operation']['opcommand_hst']);

			foreach ($data['new_ack_operation']['opcommand_hst'] as $ohnum => $cmd) {
				$data['new_ack_operation']['opcommand_hst'][$ohnum]['name'] = ($cmd['hostid'] > 0)
					? $hosts[$cmd['hostid']]['name']
					: '';
			}
			order_result($data['new_ack_operation']['opcommand_hst'], 'name');

			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => zbx_objectValues($data['new_ack_operation']['opcommand_grp'], 'groupid'),
				'preservekeys' => true,
				'editable' => true
			]);

			$data['new_ack_operation']['opcommand_grp'] = array_values(
				$data['new_ack_operation']['opcommand_grp']
			);

			foreach ($data['new_ack_operation']['opcommand_grp'] as $ognum => $cmd) {
				$data['new_ack_operation']['opcommand_grp'][$ognum]['name'] = $groups[$cmd['groupid']]['name'];
			}
			order_result($data['new_ack_operation']['opcommand_grp'], 'name');

			// js add commands
			$host_values = zbx_jsvalue([
				'object' => 'hostid',
				'values' => $data['new_ack_operation']['opcommand_hst'],
				'parentId' => 'ackOpCmdListFooter'
			]);

			$js_insert = 'addPopupValues('.$host_values.');';

			$group_values = zbx_jsvalue([
				'object' => 'groupid',
				'values' => $data['new_ack_operation']['opcommand_grp'],
				'parentId' => 'ackOpCmdListFooter'
			]);

			$js_insert .= 'addPopupValues('.$group_values.');';
			zbx_add_post_js($js_insert);

			$new_operation_formlist->addRow(
					(new CLabel(_('Target list'), 'ackOpCmdList'))->setAsteriskMark(),
					(new CDiv(
						(new CTable())
							->addStyle('width: 100%;')
							->setHeader([_('Target'), _('Action')])
							->addRow(
								(new CRow(
									(new CCol(
										(new CButton('add', _('New')))
											->onClick('javascript: showOpCmdForm(0, '.ACTION_ACKNOWLEDGE_OPERATION.');')
											->addClass(ZBX_STYLE_BTN_LINK)
									))->setColSpan(3)
								))->setId('ackOpCmdListFooter')
							)
					))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setId('ackOpCmdList')
				)
				->addRow(
					(new CLabel(_('Type'), 'new_ack_operation[opcommand][type]')),
					(new CComboBox('new_ack_operation[opcommand][type]',
						$data['new_ack_operation']['opcommand']['type'],
						'showOpTypeForm('.ACTION_ACKNOWLEDGE_OPERATION.')', [
							ZBX_SCRIPT_TYPE_IPMI => _('IPMI'),
							ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT => _('Custom script'),
							ZBX_SCRIPT_TYPE_SSH => _('SSH'),
							ZBX_SCRIPT_TYPE_TELNET => _('Telnet'),
							ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT => _('Global script')
					]))
				)
				->addRow(
					(new CLabel(_('Script name'), 'new_ack_operation[opcommand][script]'))->setAsteriskMark(),
					(new CDiv([
						new CVar('new_ack_operation[opcommand][scriptid]',
							$data['new_ack_operation']['opcommand']['scriptid']
						),
						(new CTextBox('new_ack_operation[opcommand][script]',
							$data['new_ack_operation']['opcommand']['script'], true
						))
							->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
							->setAriaRequired(),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						(new CButton('select_ack_operation_opcommand_script', _('Select')))
							->addClass(ZBX_STYLE_BTN_GREY)
					]))->addClass(ZBX_STYLE_NOWRAP)
				)
				->addRow(
					(new CLabel(_('Execute on'), 'new_ack_operation[opcommand][execute_on]')),
					(new CRadioButtonList('new_ack_operation[opcommand][execute_on]',
						(int) $data['new_ack_operation']['opcommand']['execute_on']
					))
						->addValue(_('Zabbix agent'), ZBX_SCRIPT_EXECUTE_ON_AGENT)
						->addValue(_('Zabbix server (proxy)'), ZBX_SCRIPT_EXECUTE_ON_PROXY)
						->addValue(_('Zabbix server'), ZBX_SCRIPT_EXECUTE_ON_SERVER)
						->setModern(true)
				)
				->addRow(_('Authentication method'),
					new CComboBox('new_ack_operation[opcommand][authtype]',
						$data['new_ack_operation']['opcommand']['authtype'],
						'showOpTypeAuth('.ACTION_ACKNOWLEDGE_OPERATION.')', [
							ITEM_AUTHTYPE_PASSWORD => _('Password'),
							ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
					])
				)
				->addRow((new CLabel(_('User name'), 'new_ack_operation[opcommand][username]'))->setAsteriskMark(),
					(new CTextBox('new_ack_operation[opcommand][username]',
						$data['new_ack_operation']['opcommand']['username']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Public key file'), 'new_ack_operation[opcommand][publickey]'))->setAsteriskMark(),
					(new CTextBox('new_ack_operation[opcommand][publickey]',
						$data['new_ack_operation']['opcommand']['publickey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Private key file'), 'new_ack_operation[opcommand][privatekey]'))->setAsteriskMark(),
					(new CTextBox('new_ack_operation[opcommand][privatekey]',
						$data['new_ack_operation']['opcommand']['privatekey']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setAriaRequired()
				)
				->addRow(_('Password'),
					(new CTextBox('new_ack_operation[opcommand][password]',
						$data['new_ack_operation']['opcommand']['password']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addRow(_('Key passphrase'),
					(new CTextBox('new_ack_operation[opcommand][password]',
						$data['new_ack_operation']['opcommand']['password']
					))
						->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
						->setId('new_ack_operation_opcommand_passphrase')
				)
				->addRow(_('Port'),
					(new CTextBox('new_ack_operation[opcommand][port]',
						$data['new_ack_operation']['opcommand']['port']
					))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				)
				->addRow(
					(new CLabel(_('Commands'), 'new_ack_operation[opcommand][command]'))->setAsteriskMark(),
					(new CTextArea('new_ack_operation[opcommand][command]',
						$data['new_ack_operation']['opcommand']['command']
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setAriaRequired()
				)
				->addRow(
					(new CLabel(_('Commands'), 'new_ack_operation[opcommand][command]'))->setAsteriskMark(),
					(new CTextBox('new_ack_operation[opcommand][command]',
						$data['new_ack_operation']['opcommand']['command']
					))
						->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
						->setId('new_ack_operation_opcommand_command_ipmi')
						->setAriaRequired()
				);
		}

		if ($usrgrp_list || $user_list) {
			$new_operation_formlist->addRow('',
				(new CLabel(_('At least one user or user group must be selected.')))
					->setAsteriskMark()
			);
		}

		if ($usrgrp_list) {
			$new_operation_formlist->addRow(_('Send to User groups'),
				(new CDiv($usrgrp_list))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			);
		}

		if ($user_list) {
			$new_operation_formlist->addRow(_('Send to Users'),
				(new CDiv($user_list))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			);
		}

		if (array_key_exists('opmessage', $data['new_ack_operation'])
				&& $data['new_ack_operation']['operationtype'] != OPERATION_TYPE_COMMAND) {
			$mediatype_cbox = (new CComboBox('new_ack_operation[opmessage][mediatypeid]',
				$data['new_ack_operation']['opmessage']['mediatypeid'])
			)->addItem(0, '- '._('All').' -');

			foreach ($data['available_mediatypes'] as $mediatype) {
				$mediatype_cbox->addItem($mediatype['mediatypeid'], $mediatype['description']);
			}
			$is_default_msg = (array_key_exists('default_msg', $data['new_ack_operation']['opmessage'])
				&& $data['new_ack_operation']['opmessage']['default_msg'] == 1);

			if ($data['new_ack_operation']['operationtype'] == OPERATION_TYPE_ACK_MESSAGE) {
				$new_operation_formlist->addRow(_('Default media type'), $mediatype_cbox);
			}
			else {
				$new_operation_formlist->addRow(_('Send only to'), $mediatype_cbox);
			}

			$new_operation_formlist
				->addRow(_('Default message'),
					(new CCheckBox('new_ack_operation[opmessage][default_msg]'))
						->setChecked($is_default_msg)
				)
				->addRow(_('Subject'),
					(new CTextBox('new_ack_operation[opmessage][subject]',
						$data['new_ack_operation']['opmessage']['subject']
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				)
				->addRow(_('Message'),
					(new CTextArea('new_ack_operation[opmessage][message]',
						$data['new_ack_operation']['opmessage']['message']
					))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				);
		}

		$acknowledge_tab->addRow(_('Operations'),
			(new CDiv([$new_ack_operation_vars, $operations_table]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		);

		$acknowledge_tab->addRow(_('Operation details'),
			(new CDiv([$new_operation_formlist,
				new CHorList([
					(new CSimpleButton(array_key_exists('id', $data['new_ack_operation']) ? _('Update') : _('Add')))
						->onClick('javascript: submitFormWithParam("'.$action_formname.'", "add_ack_operation", "1");')
						->addClass(ZBX_STYLE_BTN_LINK),
					(new CSimpleButton(_('Cancel')))
						->onClick('javascript: submitFormWithParam("'.$action_formname.'", "cancel_new_ack_operation", "1");')
						->addClass(ZBX_STYLE_BTN_LINK)
				])
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		);
	}
	else {
		$acknowledge_tab->addRow(_('Operations'),
			(new CDiv([$operations_table,
				(new CSimpleButton(_('New')))
					->onClick('javascript: submitFormWithParam("'.$action_formname.'", "new_ack_operation", "1");')
					->addClass(ZBX_STYLE_BTN_LINK)
			]))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		);
	}

	$action_tabs->addTab('acknowledgeTab', _('Acknowledgement operations'), $acknowledge_tab);
}

if (!hasRequest('form_refresh')) {
	$action_tabs->setSelected(0);
}

// Append buttons to form.
$others = [];
if ($data['actionid']) {
	$form_buttons = [
		new CSubmit('update', _('Update')), [
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete current action?'),
				url_param('form').url_param('eventsource').url_param('actionid')
			),
			new CButtonCancel(url_param('actiontype'))
		]
	];
}
else {
	$form_buttons = [
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('actiontype'))]
	];
}

$action_tabs->setFooter([
	(new CList())
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->addItem([
			new CDiv(''),
			(new CDiv((new CLabel($bottom_note))->setAsteriskMark()))
				->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT)
		]),
	makeFormFooter($form_buttons[0], $form_buttons[1])
]);
$actionForm->addItem($action_tabs);

// Append form to widget.
$widget->addItem($actionForm);

return $widget;
