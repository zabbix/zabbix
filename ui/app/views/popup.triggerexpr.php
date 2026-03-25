<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 */

// Create form.
$expression_form = (new CForm())
	->setName('expression')
	->addVar('action', 'popup.triggerexpr.edit')
	->addVar('dstfrm', $data['dstfrm'])
	->addVar('dstfld1', $data['dstfld1'])
	->addVar('context', $data['context'])
	->addItem((new CVar('hostid', $data['hostid']))->removeId())
	->addVar('function', $data['values']['function'])
	->addVar('function_type', $data['values']['function_type'])
	->setId('expression-form')
	->setName('expression-form')
	->addStyle('display: none;')
	->addItem(
		(new CInput('hidden', 'item_value_type', $data['values']['item_value_type']))
			->setAttribute('data-field-type', 'hidden')
	);

// Enable form submitting on Enter.
$expression_form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['parent_discoveryid'] !== '') {
	$expression_form->addVar('parent_discoveryid', $data['parent_discoveryid']);
}

// Create form list.
$expression_form_list = new CFormList();

// Append item to form list.
$item = [
	(new CTextBox('itemid', $data['values']['itemid'], true))
		->setAttribute('hidden', true)
		->setAttribute('data-error-container', 'itemid-error-container'),
	(new CTextBox('item_description', $data['values']['item_description'], true))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CButton('select', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
];

if ($data['parent_discoveryid'] !== '') {
	$item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$item[] = (new CButton('select', _('Select prototype')))
		->setId('select-item-prototype')
		->addClass(ZBX_STYLE_BTN_GREY);
}

$item[] = (new CDiv())->setId('itemid-error-container')->addClass(ZBX_STYLE_ERROR_CONTAINER);

$expression_form_list->addRow((new CLabel(_('Item'), 'item_description'))->setAsteriskMark(), $item,
	null, 'hidden'
);

$function_select = (new CSelect('function_select'))
	->setFocusableElementId('label-function')
	->setId('function-select')
	->setAttribute('autofocus', 'autofocus')
	->setValue($data['values']['function_type'].'_'.$data['values']['function'])
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setErrorContainer('function_error_container');

$function_types = [
	ZBX_FUNCTION_TYPE_AGGREGATE => _('Aggregate functions'),
	ZBX_FUNCTION_TYPE_BITWISE => _('Bitwise functions'),
	ZBX_FUNCTION_TYPE_DATE_TIME => _('Date and time functions'),
	ZBX_FUNCTION_TYPE_HISTORY => _('History functions'),
	ZBX_FUNCTION_TYPE_MATH => _('Mathematical functions'),
	ZBX_FUNCTION_TYPE_OPERATOR => _('Operator functions'),
	ZBX_FUNCTION_TYPE_PREDICTION => _('Prediction functions'),
	ZBX_FUNCTION_TYPE_STRING => _('String functions')
];

$expression_form_list->addRow(new CLabel(_('Function'), $function_select->getFocusableElementId()),
	[$function_select, (new CDiv())->setId('function-error-container')->addClass(ZBX_STYLE_ERROR_CONTAINER)]
);

$expression_form_list->addRow(new CLabel(_('Last of').' (T)', 'params_last'),
	[
		(new CTextBox('params[last]',
			array_key_exists('last', $data['values']['params']) ? $data['values']['params']['last'] : ''
		))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSelect('paramtype'))
			->setValue($data['values']['paramtype'])
			->addOptions(CSelect::createOptionsFromArray([PARAM_TYPE_TIME => _('Time'), PARAM_TYPE_COUNTS => _('Count')])),
		(new CDiv())->addClass('paramtype')->addStyle('display: none;')
	], null, 'hidden'
);

foreach ($data['params_fields'] as $name => $field_config) {
	$input_field = (new $field_config['type']('params['.$name.']'))
		->setAriaRequired()
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH);

	if (array_key_exists('attributes', $field_config)) {
		foreach ($field_config['attributes'] as $attribute => $value) {
			$input_field->setAttribute($attribute, $value);
		}
	}

	if (array_key_exists('options', $field_config)) {
		$input_field->addOptions(CSelect::createOptionsFromArray($field_config['options']));

		if (array_key_exists($name, $data['values']['params'])) {
			$input_field->setValue($data['values']['params'][$name]);
		}
	}
	elseif (array_key_exists($name, $data['values']['params'])) {
		$input_field->setAttribute('value', $data['values']['params'][$name]);
	}

	$row = [$input_field, (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)];

	if (array_key_exists('paramtype', $field_config)) {
		$row[] = $field_config['paramtype'];
	}

	$expression_form_list->addRow((new CLabel('', 'params['.$name.']'))->setAsteriskMark(), $row,
		null, 'hidden'
	);
}

$expression_form_list->addRow(
	(new CLabel(_('Result'), 'value'))->setAsteriskMark(), [
		(new CSelect('operator'))
			->setValue($data['values']['operator'])
			->addOptions(CSelect::createOptionsFromArray($data['operators']))
			->setFocusableElementId('value'),
		' ',
		(new CTextBox('value', $data['values']['value']))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	],
	null, 'hidden'
);

$expression_form->addItem($expression_form_list);

$output = [
	'header' => $data['title'],
	'body' => (new CDiv([$data['messages'], $expression_form]))->toString(),
	'buttons' => [
		[
			'title' => _('Insert'),
			'class' => 'js-submit',
			'keepOpen' => true,
			'isSubmit' => true
		]
	],
	'script_inline' => $this->readJsFile('popup.triggerexpr.js.php').
		'trigger_edit_expression_popup.init('.json_encode([
			'functions' => $data['functions'],
			'function_types' => $function_types,
			'is_new' => $data['is_new']
		]).');'
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
