<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->setId('popup-operation')
	->setName('popup_operation')
	->addVar('operation[eventsource]', $data['eventsource'])
	->addVar('operation[recovery]', $data['recovery']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid());
$operation = $data['operation'];

$operationtype_value = $operation['opcommand']['scriptid'] != 0
	? 'scriptid['.$operation['opcommand']['scriptid'].']'
	: 'cmd['. $operation['operationtype'].']';

// Operation type row.
if (count($data['operation_types']) > 1) {
	$select_operationtype = (new CFormField(
		(new CSelect('operation[operationtype]'))
			->setFocusableElementId('operationtype')
			->addOptions(CSelect::createOptionsFromArray($data['operation_types']))
			->setValue($operationtype_value ?? 0)
			->setId('operation-type-select')
	))->setId('operation-type');
}
else {
	$select_operationtype = (new CFormField([
		new CLabel($data['operation_types']),
		(new CInput('hidden', 'operation[operationtype]', $operationtype_value))
			->setId('operation-type-select')
	]))->setId('operation-type');
}

if ($data['scripts_with_warning']) {
	$select_operationtype->addItem(
		makeWarningIcon(_('Global script execution on Zabbix server is disabled by server configuration.'))
			->addClass('js-script-warning-icon')
			->addStyle('display: none;')
	);
}

$form_grid->addItem([
	(new CLabel(_('Operation'), 'operationtype'))->setId('operation-type-label'),
	$select_operationtype
]);

// Operation escalation steps row.
if (($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL
		|| $data['eventsource'] == EVENT_SOURCE_SERVICE) && $data['recovery'] == ACTION_OPERATION) {
	$step_from = (new CNumericBox('operation[esc_step_from]', $operation['esc_step_from'] ?? 1, 5))
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		->setId('operation_esc_step_from');
	$step_from->onChange($step_from->getAttribute('onchange').' if (this.value < 1) this.value = 1;');

	$step_to = (new CNumericBox('operation[esc_step_to]', 0, 5, false, false, false))
		->setAttribute('value', $operation['esc_step_to'] ?? 0)
		->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);

	$form_grid->addItem([
		(new CLabel(_('Steps'), 'operation_esc_step_from'))->setId('operation-step-range-label'),
		(new CFormField([
			$step_from,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), '-',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$step_to,
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - infinitely)')
		]))->setId('operation-step-range')
	]);

	// Operation steps duration row.
	$form_grid->addItem([
		(new CLabel(_('Step duration'), 'operation_esc_period'))->setId('operation-step-duration-label'),
		(new CFormField([
			(new CTextBox('operation[esc_period]', 0))
				->setAttribute('value', $operation['esc_period'] ?? 0)
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)->setId('operation_esc_period'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - use action default)')
		]))->setId('operation-step-duration')
	]);
}

// Message recipient is required notice row.
$form_grid->addItem(
	(new CFormField((new CLabel(_('At least one user or user group must be selected.')))
		->setAsteriskMark()
	))->setId('operation-message-notice')
);

$form_grid->addItem([
	(new CLabel(_('Send to user groups'), 'operation_opmessage_grp__usrgrpid_ms'))
		->setId('user-groups-label'),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[opmessage_grp][][usrgrpid]',
			'object_name' => 'usersGroups',
			'data' => $operation['opmessage_grp'],
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'usrgrp',
					'srcfld1' => 'usrgrpid',
					'srcfld2' => 'name',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'operation_opmessage_grp__usrgrpid'
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
	))
		->setId('operation-message-user-groups')
]);

$form_grid->addItem([
	(new CLabel(_('Send to users'),'operation_opmessage_usr__userid_ms'))
		->setId('users-label'),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[opmessage_usr][][userid]',
			'object_name' => 'users',
			'data' => $operation['opmessage_usr'],
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'users',
					'srcfld1' => 'userid',
					'srcfld2' => 'fullname',
					'dstfrm' => $form->getName(),
					'dstfld1'=> 'operation_opmessage_usr__userid'
				]
			]
		]))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
	)->setId('operation-message-users')
]);

array_unshift($data['mediatype_options'], ['name' => _('All available'), 'mediatypeid' => 0, 'status' => 0]);

$mediatypes = [];
foreach ($data['mediatype_options'] as $mediatype) {
	$mediatypes[] = (new CSelectOption($mediatype['mediatypeid'], $mediatype['name']))
		->addClass($mediatype['status'] == MEDIA_TYPE_STATUS_DISABLED ? ZBX_STYLE_RED : null);
}

// Operation message media type row.
$select_opmessage_mediatype_default = (new CSelect('operation[opmessage][mediatypeid]'))
	->addOptions($mediatypes)
	->setFocusableElementId('operation-opmessage-mediatypeid')
	->setValue($operation['opmessage']['mediatypeid'] ?? 0);

$form_grid->addItem([
	(new CLabel(_('Default media type'), $select_opmessage_mediatype_default->getFocusableElementId()))
		->setId('operation-message-mediatype-default-label'),
	(new CFormField($select_opmessage_mediatype_default))
		->setId('operation-message-mediatype-default')
]);

// Operation message media type row (explicit).
$select_opmessage_mediatype = (new CSelect('operation[opmessage][mediatypeid]'))
	->addOptions($mediatypes)
	->setFocusableElementId('operation-mediatypeid')
	->setName('operation[opmessage][mediatypeid]')
	->setValue($operation['opmessage']['mediatypeid'] ?? 0);

$form_grid->addItem([
	(new CLabel(_('Send to media type'), $select_opmessage_mediatype->getFocusableElementId()))
		->setId('operation-message-mediatype-only-label'),
	(new CFormField($select_opmessage_mediatype))
		->setId('operation-message-mediatype-only')
		->setName('operation[opmessage][default_msg]')
]);

// Operation custom message checkbox row.
$form_grid->addItem([
	(new CLabel(_('Custom message'), 'operation[opmessage][default_msg]'))->setId('operation-message-custom-label'),
	(new CFormField(
		(new CCheckBox('operation[opmessage][default_msg]', $operation['opmessage']['default_msg']))
			->setId('operation_opmessage_default_msg')
			->setChecked($operation['opmessage']['default_msg'] != '1')
	))->setId('operation-message-custom')
]);

// Operation custom message subject row.
$form_grid->addItem([
	(new CLabel(_('Subject'), 'operation-opmessage-subject'))->setId('operation-message-subject-label'),
	(new CFormField(
		(new CTextBox('operation[opmessage][subject]'))
			->setAttribute('value', $operation['opmessage']['default_msg'] == 1
				? ''
				: $operation['opmessage']['subject']
			)
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('operation-opmessage-subject')
	))->setId('operation-message-subject')
]);

// Operation custom message body row.
$form_grid->addItem([
	(new CLabel(_('Message'), 'operation_opmessage_message'))->setId('operation-message-label'),
	(new CFormField(
		(new CTextArea('operation[opmessage][message]'))
			->setValue($operation['opmessage']['default_msg'] == 1 ? '' : $operation['opmessage']['message'])
			->setMaxlength(DB::getFieldLength('opmessage', 'message'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('operation_opmessage_message')
	))->setId('operation-message')
]);

$opcommand_hst_value = null;
$hosts_ms = [];

if (array_key_exists('opcommand_hst', $operation)) {
	foreach ($operation['opcommand_hst'] as $host) {
		if ($host !== null) {
			if (array_key_exists('id', $host)) {
				if ($host['id'] == 0) {
					$opcommand_hst_value = 0;
				}
				else {
					$hosts_ms[] = $host;
				}
			}
		}
	}
}

if (array_key_exists('opcommand_hst', $operation) && array_key_exists('opcommand_grp', $operation)) {
	// Command execution targets row.
	$form_grid->addItem([
		(new CLabel(_('Target list')))
			->setId('operation-command-targets-label')
			->setAsteriskMark(),
		(new CFormField(
			(new CFormGrid())
				->cleanItems()
				->addItem([
					new CLabel(_('Current host'), 'operation-command-chst'),
					(new CFormField(
						(new CCheckBox('operation[opcommand_hst][][hostid]', '0'))
							->setChecked($opcommand_hst_value === 0)
							->setId('operation-command-chst')
					))->setId('operation-command-checkbox')
				])
				->addItem([
					(new CLabel(_('Hosts'), 'operation_opcommand_hst__hostid_ms')),
					(new CFormField(
						(new CMultiSelect([
							'name' => 'operation[opcommand_hst][][hostid]',
							'object_name' => 'hosts',
							'data' => $hosts_ms,
							'popup' => [
								'parameters' => [
									'multiselect' => '1',
									'srctbl' => 'hosts',
									'srcfld1' => 'hostid',
									'dstfrm' => 'action.edit',
									'dstfld1' => 'operation_opcommand_hst__hostid'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->setId('operation_opcommand_host_ms')
				])
				->addItem([
					new CLabel(_('Host groups'), 'operation_opcommand_grp__groupid_ms'),
					(new CFormField(
						(new CMultiSelect([
							'name' => 'operation[opcommand_grp][][groupid]',
							'object_name' => 'hostGroup',
							'data' => $operation['opcommand_grp'],
							'popup' => [
								'parameters' => [
									'multiselect' => '1',
									'srctbl' => 'host_groups',
									'srcfld1' => 'groupid',
									'dstfrm' => 'action.edit',
									'dstfld1' => 'operation_opcommand_grp__groupid'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					))->setId('operation_opcommand_hostgroup_ms')
				])
		))
			->setId('operation-command-targets')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	]);
}

// Add / remove host group attribute row.
$form_grid->addItem([
	(new CLabel(_('Host groups'), 'operation_opgroup__groupid_ms'))
		->setId('operation-attr-hostgroups-label')
		->setAsteriskMark(),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[opgroup][][groupid]',
			'object_name' => 'hostGroup',
			'data' => $operation['opgroup'],
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'action.edit',
					'dstfld1' => 'operation_opgroup__groupid'
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
	)->setId('operation-attr-hostgroups')
]);

// Operation Add/Remove host tags.
$form_grid->addItem(
	(new CFormField([
		(new CTable())
			->setId('tags-table')
			->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_CONTAINER)
			->setHeader([_('Name'), _('Value'), ''])
			->setFooter(new CCol(
				(new CButton('tag_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			)),
		(new CTemplateTag('operation-host-tags-row-tmpl'))
			->addItem(
				(new CRow([
					(new CCol(
						(new CTextAreaFlexible('operation[optag][#{row_index}][tag]', '#{tag}',
							['add_post_js' => false]
						))
							->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
							->setAttribute('placeholder', _('tag'))
					))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
					(new CCol(
						(new CTextAreaFlexible('operation[optag][#{row_index}][value]', '#{value}',
							['add_post_js' => false]
						))
							->setWidth(ZBX_TEXTAREA_TAG_VALUE_WIDTH)
							->setAttribute('placeholder', _('value'))
					))->addClass(ZBX_STYLE_TEXTAREA_FLEXIBLE_PARENT),
					(new CCol(
						(new CSimpleButton(_('Remove')))
							->addClass(ZBX_STYLE_BTN_LINK)
							->addClass('element-table-remove')
					))->addClass(ZBX_STYLE_NOWRAP)
				]))
					->addClass('form_row')
					->setAttribute('data-id','#{row_index}')
			)
	]))->setId('operation-host-tags')
);

// Link / unlink templates attribute row.
$form_grid->addItem([
	(new CLabel(_('Templates'), 'operation_optemplate__templateid_ms'))
		->setId('operation-attr-templates-label')
		->setAsteriskMark(),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[optemplate][][templateid]',
			'object_name' => 'templates',
			'data' => $operation['optemplate'],
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'action.edit',
					'dstfld1' => 'operation_optemplate__templateid'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
	)->setId('operation-attr-templates')
]);

// Host inventory mode attribute row.
$form_grid->addItem([
	(new CLabel(_('Inventory mode'), 'operation_opinventory_inventory_mode'))->setId('operation-attr-inventory-label'),
	(new CRadioButtonList('operation[opinventory][inventory_mode]', HOST_INVENTORY_MANUAL))
		->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
		->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
		->setModern(true)
		->addClass('form-field')
		->setId('operation-attr-inventory')
]);

// Conditions type of calculation row.
$select_operation_evaltype = (new CSelect('operation[evaltype]'))
	->setValue($data['operation']['evaltype'])
	->setId('operation-evaltype')
	->setFocusableElementId('operation-evaltype')
	->addOptions(CSelect::createOptionsFromArray([
		CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
		CONDITION_EVAL_TYPE_AND => _('And'),
		CONDITION_EVAL_TYPE_OR => _('Or')
	]));

$form_grid->addItem([
	(new CLabel(_('Type of calculation'), $select_operation_evaltype->getFocusableElementId()))
		->setId('operation-evaltype-label'),
	(new CFormField([
		$select_operation_evaltype,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('operation-condition-evaltype-formula')
	]))->setId('operation-condition-row')
]);

$conditions_table = (new CTable())
	->setId('operation-condition-list')
	->addStyle('width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$conditions_table->addItem(
	(new CTag('tfoot', true))
		->addItem(
			(new CCol(
				(new CButtonLink(_('Add')))
					->addClass('operation-condition-list-footer')
					->setAttribute('data-eventsource', $data['eventsource'])
			))->setColSpan(4)
		)
);

// Conditions row.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS && $data['recovery'] == ACTION_OPERATION) {
	$form_grid->addItem([
		(new CLabel(_('Conditions')))->setId('operation-condition-list-label'),
		(new CFormField([
			$conditions_table,
			(new CTemplateTag('operation-condition-row-tmpl'))->addItem(
				(new CRow([
					(new CCol('#{label}'))
						->setAttribute('data-conditiontype', '#{conditiontype}')
						->setAttribute('data-formulaid', '#{label}')
						->addClass('label'),
					new CCol('#{name}'),
					(new CCol([
						(new CButtonLink(_('Remove')))->addClass('js-remove'),
						(new CInput('hidden'))
							->setAttribute('value', '#{conditiontype}')
							->setName('operation[opconditions][#{row_index}][conditiontype]'),
						(new CInput('hidden'))
							->setAttribute('value', '#{operator}')
							->setName('operation[opconditions][#{row_index}][operator]'),
						(new CInput('hidden'))
							->setAttribute('value', '#{value}')
							->setName('operation[opconditions][#{row_index}][value]')
					])
					)
				]))
					->setAttribute('data-id','#{row_index}')
					->addClass('form_row')
			)
		]))
			->setId('operation-condition-table')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
	]);
}

$form->addItem($form_grid);

$buttons = [
	[
		'title' => $data['operation']['row_index'] === -1 ? _('Add') : _('Update'),
		'class' => 'js-add',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'operation_popup.submit();'
	]
];

$output = [
	'header' => _('Operation details'),
	'body' => $form->toString(),
	'buttons' => $buttons,
	'script_inline' => getPagePostJs().$this->readJsFile('popup.operation.edit.js.php').
		'operation_popup.init('.json_encode([
			'eventsource' => $data['eventsource'],
			'recovery_phase' => $data['recovery'],
			'data' => $operation,
			'scripts_with_warning' => $data['scripts_with_warning'],
			'actionid' => $data['actionid']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
