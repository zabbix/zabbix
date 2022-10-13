<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CView $this
 * @var array $data
 */

$form = (new CForm())
	->cleanItems()
	->setId('popup.operation')
	->setName('popup.operation')
	->addVar('operation[eventsource]', $data['eventsource'])
	->addVar('operation[recovery]', $data['recovery'])
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$form_grid = (new CFormGrid());
$operation = $data['operation'];

$operationtype = array_key_exists('operationtype', $operation)
	? $operation['operationtype']
	: '0';

$operationtype_value = $operation['opcommand']['scriptid'] !== '0'
	? 'scriptid['.$operation['opcommand']['scriptid'].']'
	: 'cmd['.$operationtype.']';

// Operation type row.
$select_operationtype = (new CSelect(''))
	->setFocusableElementId('operationtype')
	->addOptions(CSelect::createOptionsFromArray($data['operation_types']))
	->setAttribute('value', $operationtype_value ?? 0)
	->setId('operation-type-select')
	->setName('operation[operationtype]');

$form_grid->addItem([
	(new CLabel(_('Operation'), $select_operationtype->getFocusableElementId()))->setId('operation-type-label'),
	(new CFormField($select_operationtype))
		//->setAttribute('value', $operationtype_value ?? 0)
		->setId('operation-type')
	]);

// Operation escalation steps row.
$step_from = (new CNumericBox('operation[esc_step_from]', 1, 5))
	->setAttribute('value', $operation['esc_step_from'] ?? 1)
	->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
$step_from->onChange($step_from->getAttribute('onchange').' if (this.value < 1) this.value = 1;');

if (($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL ||
		$data['eventsource'] == EVENT_SOURCE_SERVICE) && $data['recovery'] == ACTION_OPERATION) {
	$form_grid->addItem([
		(new CLabel(_('Steps'), 'step-from'))->setId('operation-step-range-label'),
		(new CFormField([
			$step_from->setId('step-from'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), '-',
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('operation[esc_step_to]', 0, 5, false, false, false))
				->setAttribute('value', $operation['esc_step_to'] ?? 0)
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - infinitely)')
		]))->setId('operation-step-range')
]);

// Operation steps duration row.
	$form_grid->addItem([
		(new CLabel(_('Step duration'), 'step-duration'))->setId('operation-step-duration-label'),
		(new CFormField([
			(new CTextBox('operation[esc_period]', 0))
				->setAttribute('value', $operation['esc_period'] ?? 0)
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)->setId('step-duration'),
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

$usergroup_table = (new CTable())
	->setId('operation-message-user-groups-table')
	->addStyle('width: 100%;')
	->setHeader([_('User group'), _('Action')])
	->addRow(
		(new CRow(
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('operation-message-user-groups-footer')
			))->setColSpan(2)
		))->setId('operation-message-user-groups-footer')
	);

if (array_key_exists('opmessage_grp', $operation)) {
	$i = 0;

	foreach ($operation['opmessage_grp'] as $opmessage_grp) {
		$usr_grpids = $opmessage_grp['usrgrpid'];

		$user_groups = API::UserGroup()->get([
			'output' => ['name'],
			'usrgrpids' => $usr_grpids,
			'preservekeys' => true
		]);

		foreach ($user_groups as $user_group) {
			foreach ($operation['opmessage_grp'] as $group)

			$operation['opmessage_grp'][$i]['name'] = $user_group['name'];
			$i++;
		}
	}
}

$form_grid->addItem([
	(new CLabel(_('Send to user groups')))->setId('operation-message-user-groups-label'),
	(new CFormField(
		$usergroup_table
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('operation-message-user-groups')
]);

$user_table = (new CTable())
	->addStyle('width: 100%;')
	->setHeader([_('User'), _('Action')]);

if (array_key_exists('opmessage_usr', $operation)) {
	$i = 0;
	foreach ($operation['opmessage_usr'] as $opmessage_usr) {
		$userids = $opmessage_usr['userid'];

		$fullnames = [];

		$users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname'],
			'userids' => $userids
		]);

		foreach ($users as $user) {
			$fullnames[$user['userid']] = getUserFullname($user);

			$operation['opmessage_usr'][$i]['name'] = $fullnames[$opmessage_usr['userid']];
			$i++;
		}
	}
}

$user_table->addRow(
	(new CRow(
		(new CCol(
			(new CButton(null, _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('operation-message-users-footer')
		))->setColSpan(2)
	))->setId('operation-message-users-footer')
);

// Message recipient (users) row.
$form_grid->addItem([
	(new CLabel(_('Send to users')))->setId('operation-message-users-label'),
	(new CFormField($user_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('operation-message-users')
]);

// Make CSelectOption label red, if media type is disabled.
/** @var CSelectOption $option */
foreach ($data['mediatype_options'] as $option) {
	$mediatype = $option->toArray();
	if(in_array($mediatype['value'], array_values($data['disabled_media']))) {
		$option->addClass(ZBX_STYLE_RED);
	}
}

// Operation message media type row.
$select_opmessage_mediatype_default = (new CSelect('operation[opmessage][mediatypeid]'))
	->addOptions($data['mediatype_options'])
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
	->addOptions($data['mediatype_options'])
	->setFocusableElementId('operation-opmessage-mediatypeid')
	->setName('operation[opmessage][mediatypeid]')
	->setValue($operation['opmessage']['mediatypeid'] ?? 0);

$form_grid->addItem([
	(new CLabel(_('Send only to'), $select_opmessage_mediatype->getFocusableElementId()))->setId('operation-message-mediatype-only-label'),
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
			->setChecked((bool) $operation['opmessage']['default_msg'] != '1')
	))->setId('operation-message-custom')
]);

// Operation custom message subject row.
$form_grid->addItem([
	(new CLabel(_('Subject')))->setId('operation-message-subject-label'),
	(new CTextBox('operation[opmessage][subject]'))
		->setAttribute('value',  $operation['opmessage']['subject'] ?? '')
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('operation-message-subject')
]);

// Operation custom message body row.
$form_grid->addItem([
	(new CLabel(_('Message')))->setId('operation-message-label'),
	(new CTextArea('operation[opmessage][message]'))
		->setValue( $operation['opmessage']['message'] ?? '')
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('operation-message-body')
]);

$opcommand_hst_value = array_key_exists('0', $operation['opcommand_hst'])
	? $operation['opcommand_hst']['0']['hostid']
	: null;

foreach($operation['opcommand_hst'] as $host) {
	if ($host[0]['hostid'] == 0) {
		$multiselect_values_host = null;
	}
	else {
		$hosts['id'] = $host[0]['hostid'];
		$hosts['name'] = $host[0]['name'];
		$multiselect_values_host[] = $hosts;
	}
}

if($operation['opcommand_grp']) {
	foreach ($operation['opcommand_grp'] as $group) {
		$host_group['id'] = $group[0]['hostid'];
		$host_group['name'] = $group[0]['name'];
		$multiselect_values_host_grp[] = $host_group;
	}
}

// Command execution targets row.
$form_grid->addItem([
	(new CLabel(_('Target list')))
		->setId('operation-command-targets-label')
		->setAsteriskMark(),
	(new CFormField(
		(new CFormGrid())
			->cleanItems()
			->addItem([
				new CLabel(_('Current host')),
				(new CFormField((new CCheckBox('operation[opcommand_hst][][hostid][current_host]', '0'))
				->setChecked($opcommand_hst_value == 0 && $opcommand_hst_value !== null)
				))->setId('operation-command-checkbox')
			])
			->addItem([
				(new CLabel(_('Host'))),
				(new CMultiSelect([
					'name' => 'operation[opcommand_hst][][hostid]',
					'object_name' => 'hosts',
					'data' => $multiselect_values_host  == null ? [] : $multiselect_values_host ,
					'popup' => [
						'parameters' => [
							'multiselect' => '1',
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => 'action.edit',
							'dstfld1' => 'operation_opcommand_hst__hostid',
							'editable' => '1'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			])
			->addItem([
				new CLabel(_('Host group')),
				(new CMultiSelect([
					'name' => 'operation[opcommand_grp][][groupid]',
					'object_name' => 'hostGroup',
					'data' => $multiselect_values_host_grp,
					'popup' => [
						'parameters' => [
							'multiselect' => '1',
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'action.edit',
							'dstfld1' => 'operation_opcommand_grp__groupid',
							'editable' => '1'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			])
	))
		->setId('operation-command-targets')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
]);

// Add / remove host group attribute row.
$form_grid->addItem([
	(new CLabel(_('Host groups'),'operation-attr-hostgroups'))
		->setId('operation-attr-hostgroups-label')
		->setAsteriskMark(),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[opgroup][][groupid]',
			'object_name' => 'hostGroup',
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => 'action.edit',
					'dstfld1' => 'operation_opgroup__groupid',
					'editable' => '1'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
	)->setId('operation-attr-hostgroups')
]);

// Link / unlink templates attribute row.
$form_grid->addItem([
	(new CLabel(_('Templates')))
		->setId('operation-attr-templates-label')
		->setAsteriskMark(),
	(new CFormField(
		(new CMultiSelect([
			'name' => 'operation[optemplate][][templateid]',
			'object_name' => 'templates',
			'popup' => [
				'parameters' => [
					'multiselect' => '1',
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'dstfrm' => 'action.edit',
					'dstfld1' => 'operation_optemplate__templateid',
					'editable' => '1'
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
	->setValue((string) CONDITION_EVAL_TYPE_AND_OR)
	->setId('operation-evaltype')
	->setFocusableElementId('operation-evaltype')
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND_OR, _('And/Or')))
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_AND, _('And')))
	->addOption(new CSelectOption(CONDITION_EVAL_TYPE_OR, _('Or')));

$form_grid->addItem([
	(new CLabel(_('Type of calculation'), $select_operation_evaltype->getFocusableElementId()))
		->setId('operation-evaltype-label'),
	(new CFormField([
		$select_operation_evaltype,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())
			->setId('operation-condition-evaltype-formula'),
	]))->setId('operation-condition-row')
]);

$conditions_table = (new CTable())
	->setId('operation-condition-list')
	->addStyle('width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')])
	->addRow(
		(new CRow(
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('operation-condition-list-footer')
			))->setColSpan(3)
		))->setId('operation-condition-list-footer')
	);

// Conditions row.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS && $data['recovery'] == ACTION_OPERATION) {
	$form_grid->addItem([
		(new CLabel(_('Conditions')))->setId('operation-condition-list-label'),
		(new CFormField(
			$conditions_table
		))
			->setId('operation-condition-table')
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')

	]);
}

$form->addItem($form_grid);

$buttons = [
	[
		'title' => array_key_exists('operationid',$data['operation']) ? _('Update') : _('Add'),
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
			'actionid' => $data['actionid']
		]).');',
];

echo json_encode($output);
