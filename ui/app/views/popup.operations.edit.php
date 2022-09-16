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

// Operation type row.
$select_operationtype = (new CSelect(''))
	->setFocusableElementId('operationtype')
	->addOptions(CSelect::createOptionsFromArray($data['operation_types']))
	->setId('operation-type-select')
	->setName('operation[operationtype]');

$form_grid->addItem([
	new CLabel(_('Operation'), $select_operationtype->getFocusableElementId()),
	(new CFormField($select_operationtype))
		->setId('operation-type')
	]);

/*
 * Operation escalation steps row.
 */
$step_from = (new CNumericBox('operation[esc_step_from]', 1, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH);
$step_from->onChange($step_from->getAttribute('onchange').' if (this.value < 1) this.value = 1;');

if (($data['eventsource'] == EVENT_SOURCE_TRIGGERS || $data['eventsource'] == EVENT_SOURCE_INTERNAL ||
		$data['eventsource'] == EVENT_SOURCE_SERVICE) && $data['recovery'] == ACTION_OPERATION) {
	$form_grid->addItem([
		new CLabel(_('Steps'), 'step-from'),
		(new CFormField([
			$step_from->setId('step-from'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), '-', (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CNumericBox('operation[esc_step_to]', 0, 5, false, false, false))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN), _('(0 - infinitely)')
		]))->setId('operation-step-range')
	//'operation-step-range'
]);

// Operation steps duration row.
	$form_grid->addItem([
		new CLabel(_('Step duration'), 'step-duration'),
		new CFormField([
			(new CTextBox('operation[esc_period]', 0))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)->setId('step-duration'),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			_('(0 - use action default)')
		]),
	])->setId('operation-step-duration');
}

// Message recipient is required notice row.
$form_grid->addItem(
	new CFormField((new CLabel(_('At least one user or user group must be selected.')))->setAsteriskMark(),
	'operation-message-notice'
));

$form_grid->addItem([
	new CLabel(_('Send to user groups')),
	(new CFormField(
		(new CTable())
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
			)
	))
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	//'operation-message-user-groups'
]);

// Message recipient (users) row.
$form_grid->addItem([
	new CLabel(_('Send to users')),
	(new CFormField(
	(new CTable())
		->addStyle('width: 100%;')
		->setHeader([_('User'), _('Action')])
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('operation-message-users-footer')
				))->setColSpan(2)
			))->setId('operation-message-users-footer')
		)
	))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
//	'operation-message-users'
]);

// Operation message media type row.
// todo: show only when ????
// $select_opmessage_mediatype_default = (new CSelect('operation[opmessage][mediatypeid]'))
//	->setFocusableElementId('operation-opmessage-mediatypeid');

// $form_grid->addItem([
//	new CLabel(_('Default media type'), $select_opmessage_mediatype_default->getFocusableElementId()),
//	(new CFormField($select_opmessage_mediatype_default))->setId('operation-message-mediatype-default')
// ]);

// Operation message media type row (explicit).
$select_opmessage_mediatype = (new CSelect('operation[opmessage][mediatypeid]'))
	->addOptions(CSelect::createOptionsFromArray($data['media_types']))
	->setFocusableElementId('operation-opmessage-mediatypeid')
	->setName('operation[operation-message-mediatype-only]');


$form_grid->addItem([
	new CLabel(_('Send only to'), $select_opmessage_mediatype->getFocusableElementId()),
	(new CFormField($select_opmessage_mediatype))
		->setId('operation-message-mediatype-only')
		->setName('operation[opmessage][default_msg]')
]);

// Operation custom message checkbox row.
$form_grid->addItem([
	new CLabel(_('Custom message'), 'operation_opmessage_default_msg'),
	(new CFormField(new CCheckBox('operation[opmessage][default_msg]', 0)))->setId('operation-message-custom')
	// new CCheckBox('', 0)
]);

// Operation custom message subject row.
$form_grid->addItem([
	(new CLabel(_('Subject')))->setId('operation-message-subject-label'),
	(new CTextBox('operation[opmessage][subject]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('operation-message-subject')
]);

// Operation custom message body row.
$form_grid->addItem([
	(new CLabel(_('Message')))->setId('operation-message-label'),
	(new CTextArea('operation[opmessage][message]', ''))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('operation-message-body')
]);
// todo: til here


// todo show when operation type = ping ?? (and other??)
// todo add ms buttons
// Command execution targets row.
//$form_grid->addItem([
//	(new CLabel(_('Target list')))->setAsteriskMark(),
//	(new CFormField(
//		(new CFormGrid())
//			->cleanItems()
//			->addItem([
//				new CLabel(_('Current host')),
//				new CFormField((new CCheckBox('operation[opcommand_hst][][hostid]', '0'))->setId('operation-command-chst'))
//			])
//			->addItem([
//				new CLabel(_('Host')),
//				(new CMultiSelect([
//					'name' => 'operation[opcommand_hst][][hostid]',
//					'object_name' => 'hosts',
//					'add_post_js' => false
//				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
//			])
//			->addItem([
//				(new CLabel(_('Host group'))),
//				(new CMultiSelect([
//					'name' => 'operation[opcommand_grp][][groupid]',
//					'object_name' => 'hostGroup',
//					'add_post_js' => false
//				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
//			])
//	))
//		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
//		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
//	//'operation-command-targets'
//]);

//// todo: show these when Discovery options and operation type = add/remove from host group or link/unlink from template
//// Add / remove host group attribute row.
// $form_grid->addItem([
//	(new CLabel(_('Host groups')))->setAsteriskMark(),
//	new CFormGrid((new CMultiSelect([
//		'name' => 'operation[opgroup][][groupid]',
//		'object_name' => 'hostGroup',
//		'add_post_js' => false
//	]))
//		->setAriaRequired()
//		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
//	//'operation-attr-hostgroups'
//]);

//// Link / unlink templates attribute row.
// $form_grid->addItem([
//	(new CLabel(_('Templates')))->setAsteriskMark(),
//	new CFormField((new CMultiSelect([
//		'name' => 'operation[optemplate][][templateid]',
//		'object_name' => 'templates',
//		'add_post_js' => false
//	]))
//		->setAriaRequired()
//		->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH))
////	'operation-attr-templates'
//]);

//// todo : show when discovery action and operation type = set host inventory mode (on change)
//// Host inventory mode attribute row.
// $form_grid->addItem([
//	new CLabel(_('Inventory mode'), 'operation_opinventory_inventory_mode'),
//	(new CRadioButtonList('operation[opinventory][inventory_mode]', HOST_INVENTORY_MANUAL))
//		->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
//		->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
//		->setModern(true),
//	// 'operation-attr-inventory'
//]);

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

// Conditions row.
if ($data['eventsource'] == EVENT_SOURCE_TRIGGERS && $data['recovery'] == ACTION_OPERATION) {
	$form_grid->addItem([
		new CLabel(_('Conditions')),
		(new CFormField(
			(new CTable())
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
				)
		))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')

	]);
}

$form->addItem($form_grid);

$buttons = [
	[
		'title' => _('Add'),
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
	'script_inline' => getPagePostJs().$this->readJsFile('popup.operation.common.js.php').
		'operation_popup.init('.json_encode([
			'eventsource' => $data['eventsource'],
			'recovery_phase' => $data['recovery']
		]).');',
	//'script_inline' => getPagePostJs().
	//	$this->readJsFile('popup.operations.js.php')
];

echo json_encode($output);
