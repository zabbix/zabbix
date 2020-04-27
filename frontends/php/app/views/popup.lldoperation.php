<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 */

$output = [
	'header' => $data['title'],
];

$options = $data['options'];

$operations_popup_form = (new CForm())
	->cleanItems()
	->setId('lldoperation_form') // TODO VM: used?
	->addVar('no', $options['no'])
	->addItem((new CVar('templated', $options['templated']))->removeId())
	->addVar('action', 'popup.lldoperation')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$operations_popup_form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Object'), 'operation_object'))->setAsteriskMark(), // TODO VM: do I need asterix here?
		(new CComboBox('operation_object', $options['operationobject'], null, [
			OPERATION_OBJECT_ITEM_PROTOTYPE => _('Item prototype'),
			OPERATION_OBJECT_TRIGGER_PROTOTYPE => _('Trigger prototype'),
			OPERATION_OBJECT_GRAPH_PROTOTYPE => _('Graph prototype'),
			OPERATION_OBJECT_HOST_PROTOTYPE => _('Host prototype')
		]))
			->setAriaRequired() // TODO VM: do I need asterix here?
			->setId('operation_object')
	)
	->addRow(_('Condition'), [
			(new CComboBox('operator', $options['operator'], null, [
				CONDITION_OPERATOR_EQUAL  => _('equals'),
				CONDITION_OPERATOR_NOT_EQUAL  => _('does not equal'),
				CONDITION_OPERATOR_LIKE  => _('contains'),
				CONDITION_OPERATOR_NOT_LIKE  => _('does not contain'),
				CONDITION_OPERATOR_REGEXP => _('matches'),
				CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
			]))
				->addStyle('margin-right:5px;'), // TODO VM: do ti by CSS
			(new CTextBox('conditions[#{rowNum}][value]', $options['value'], false, DB::getFieldLength('lld_override_operation', 'value')))
				->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH) // TODO VM: is it correct width?
				->setAttribute('placeholder', _('pattern')),
		]
	);

$output['buttons'] = [
	[
		// TODO VM: is this check working?
		'title' => ($options['no'] > 0) ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return lldoverrides.operations.edit_form.validate(overlay);'
	]
];

$operations_popup_form->addItem($operations_popup_form_list);

// HTTP test step editing form.
$output['body'] = (new CDiv($operations_popup_form))->toString();
$output['script_inline'] = 'lldoverrides.operations.onOperationsOverlayReadyCb('.$options['no'].');';

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
