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

$overrides_popup_form = (new CForm())
	->cleanItems()
	->setId('lldoverride_form')
	->addVar('no', $options['no'])
//	->addVar('httpstepid', $options['httpstepid'])
	->addItem((new CVar('templated', $options['templated']))->removeId())
	->addVar('old_name', $options['old_name'])
//	->addVar('steps_names', $options['steps_names'])
	->addVar('action', 'popup.lldoverride')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$overrides_popup_form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'override_name'))->setAsteriskMark(),
		(new CTextBox('name', $options['old_name'], (bool) $options['templated'], DB::getFieldLength('lld_override', 'name')))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('override_name')
	);

$overrides_popup_form_list
	->addRow(_('Stop processing next overrides if matches'),
		(new CCheckBox('stop'))
			->setChecked($options['stop'] == HTTPTEST_STEP_FOLLOW_REDIRECTS_ON) // TODO VM: change to propper define.
	);

/*FILTERS*/ // TODO VM: remove comment
// TODO VM: improve styles
$override_evaltype = (new CDiv([
	_('Type of calculation'),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	new CComboBox('overrides_evaltype', $options['overrides_evaltype'], null, [
		CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
		CONDITION_EVAL_TYPE_AND => _('And'),
		CONDITION_EVAL_TYPE_OR => _('Or'),
		CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
	]),
	(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
	(new CSpan(''))
		->setId('overrides_expression'),
	(new CTextBox('overrides_formula', $options['overrides_formula']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setId('overrides_formula')
		->setAttribute('placeholder', 'A or (B and C) &hellip;')
]))
	->addClass('overrideRow');

// TODO VM: rename macros to filters, where necessary
// macros
$filterTable = (new CTable())
	->setId('overrides_filters')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), _('Action')]);

$overrides_filters = $options['overrides_filters'];
if (!$overrides_filters) {
	$overrides_filters = [[
		'macro' => '',
		'operator' => CONDITION_OPERATOR_REGEXP,
		'value' => '',
		'formulaid' => num2letter(0)
	]];
}
else {
	$overrides_filters = CConditionHelper::sortConditionsByFormulaId($overrides_filters);
}

$operators = [
	CONDITION_OPERATOR_REGEXP => _('matches'),
	CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
];

// fields
foreach ($overrides_filters as $i => $overrides_filter) {
	// formula id
	$formulaId = [
		new CSpan($overrides_filter['formulaid']),
		new CVar('overrides_filters['.$i.'][formulaid]', $overrides_filter['formulaid'])
	];

	// macro
	$macro = (new CTextBox('overrides_filters['.$i.'][macro]', $overrides_filter['macro'], false, DB::getFieldLength('lld_override_condition', 'macro')))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->addClass('macro')
		->setAttribute('placeholder', '{#MACRO}')
		->setAttribute('data-formulaid', $overrides_filter['formulaid']);

	// value
	$value = (new CTextBox('overrides_filters['.$i.'][value]', $overrides_filter['value'], false, DB::getFieldLength('lld_override_condition', 'value')))
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('regular expression'));

	// delete button
	$deleteButtonCell = [
		(new CButton('overrides_filters_'.$i.'_remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	];

	$row = [$formulaId, $macro,
		(new CComboBox('overrides_filters['.$i.'][operator]', $overrides_filter['operator'], null, $operators))->addClass('operator'),
		$value,
		(new CCol($deleteButtonCell))->addClass(ZBX_STYLE_NOWRAP)
	];
	$filterTable->addRow($row, 'form_row');
}

$filterTable->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
));

$overrides_popup_form_list->addRow(_('Filters'),
	(new CDiv([$override_evaltype, $filterTable]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);
/*EOF FILTERS*/ // TODO VM: remove comment

/*OPERATIONS*/ // TODO VM: remove comment
$operations_list = (new CTable())
	->addClass('lld-overrides-operations-table')
	// TODO VM: new text variables?
	->setHeader([
		(new CColHeader(_('Condition')))->setWidth('150'), // TODO VM: maybe this can be made more dynamic
		(new CColHeader(_('Actions')))->setWidth('150'),
		(new CColHeader(''))->setWidth('50')
	])
	->addRow(
		(new CCol(
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
//					->setEnabled(!$templated) // TODO VM: should I be able to see overrides, for templated discovery rule
					// TODO VM: check this class, most likely it should be changed.
			))->addClass('step-action')
		// TODO VM: check, how this class is used.
		))
			->addClass('lld-overrides-operations-table-foot')
	);

$overrides_popup_form_list->addRow(_('Operations'),
	(new CDiv($operations_list))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
);
/*EOF OPERATIONS*/ // TODO VM: remove comment

$output['buttons'] = [
	[
		'title' => $options['old_name'] ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return lldoverrides.overrides.edit_form.validate(overlay);'
	]
];

$overrides_popup_form->addItem($overrides_popup_form_list);

// HTTP test step editing form.
$output['body'] = (new CDiv($overrides_popup_form))->toString();
$output['script_inline'] = 'lldoverrides.overrides.onStepOverlayReadyCb('.$options['no'].');';

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
