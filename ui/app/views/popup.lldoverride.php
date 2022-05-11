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
 */

$output = [
	'header' => $data['title']
];

$options = $data['options'];

$overrides_popup_form = (new CForm())
	->cleanItems()
	->setId('lldoverride_form')
	->addItem((new CVar('no', $options['no']))->removeId())
	->addItem((new CVar('templated', $options['templated']))->removeId())
	->addVar('old_name', $options['old_name'])
	->addVar('overrides_names', $options['overrides_names'])
	->addItem((new CVar('action', 'popup.lldoverride'))->removeId())
	->addItem((new CInput('submit', 'submit'))
		->addStyle('display: none;')
		->removeId()
	);

$overrides_popup_form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'override_name'))->setAsteriskMark(),
		(new CTextBox('name', $options['old_name'], $options['templated'], DB::getFieldLength('lld_override', 'name')))
			->setAriaRequired()
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('override_name')
	)
	->addRow(
		_('If filter matches'),
		(new CRadioButtonList('stop', (int) $options['stop']))
			->addValue(_('Continue overrides'), ZBX_LLD_OVERRIDE_STOP_NO)
			->addValue(_('Stop processing'), ZBX_LLD_OVERRIDE_STOP_YES)
			->setModern(true)
			->setReadonly($options['templated'])
	);

// filters
$override_evaltype_select = (new CSelect('overrides_evaltype'))
	->setId('overrides-evaltype')
	->setValue($options['overrides_evaltype'])
	->addOptions(CSelect::createOptionsFromArray([
		CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
		CONDITION_EVAL_TYPE_AND => _('And'),
		CONDITION_EVAL_TYPE_OR => _('Or'),
		CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
	]));

if ($options['templated']) {
	$override_evaltype_select->setReadonly();
}

$override_evaltype = (new CDiv([
	(new CDiv([
		_('Type of calculation'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$override_evaltype_select,
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN)
	]))->addClass(ZBX_STYLE_CELL),
	(new CDiv([
		(new CSpan(''))
			->addStyle('white-space: normal;')
			->setId('overrides_expression'),
		(new CTextBox('overrides_formula', $options['overrides_formula'], $options['templated'],
				DB::getFieldLength('lld_override', 'formula')))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('overrides_formula')
			->setAttribute('placeholder', 'A or (B and C) &hellip;')
	]))->addClass(ZBX_STYLE_CELL)
]))
	->addClass(ZBX_STYLE_ROW)
	->setId('overrideRow');

$filter_table = (new CTable())
	->setId('overrides_filters')
	->addStyle('width: 100%;')
	->setHeader([_('Label'), _('Macro'), '', _('Regular expression'), (new CColHeader(_('Action')))->setWidth('100%')]);

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

$operators = CSelect::createOptionsFromArray([
	CONDITION_OPERATOR_REGEXP => _('matches'),
	CONDITION_OPERATOR_NOT_REGEXP => _('does not match'),
	CONDITION_OPERATOR_EXISTS => _('exists'),
	CONDITION_OPERATOR_NOT_EXISTS => _('does not exist')
]);

foreach ($overrides_filters as $i => $overrides_filter) {
	$formulaid = [
		new CSpan($overrides_filter['formulaid']),
		new CVar('overrides_filters['.$i.'][formulaid]', $overrides_filter['formulaid'])
	];

	$macro = (new CTextBox('overrides_filters['.$i.'][macro]', $overrides_filter['macro'], $options['templated'],
			DB::getFieldLength('lld_override_condition', 'macro')))
		->setWidth(ZBX_TEXTAREA_MACRO_WIDTH)
		->addClass(ZBX_STYLE_UPPERCASE)
		->addClass('macro')
		->setAttribute('placeholder', '{#MACRO}')
		->setAttribute('data-formulaid', $overrides_filter['formulaid']);

	$operator_select = (new CSelect('overrides_filters['.$i.'][operator]'))
		->setValue($overrides_filter['operator'])
		->addClass('js-operator')
		->addOptions($operators);

	if ($options['templated']) {
		$operator_select->setReadonly();
	}

	$value = (new CTextBox('overrides_filters['.$i.'][value]', $overrides_filter['value'],$options['templated'],
			DB::getFieldLength('lld_override_condition', 'value')))
		->addClass('js-value')
		->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH)
		->setAttribute('placeholder', _('regular expression'));

	if ($overrides_filter['operator'] == CONDITION_OPERATOR_EXISTS
			|| $overrides_filter['operator'] == CONDITION_OPERATOR_NOT_EXISTS) {
		$value->addClass(ZBX_STYLE_DISPLAY_NONE);
	}

	$delete_button_cell = [
		(new CButton('overrides_filters_'.$i.'_remove', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
			->setEnabled(!$options['templated'])
	];

	$row = [
		$formulaid,
		$macro,
		$operator_select,
		(new CDiv($value))->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH),
		(new CCol($delete_button_cell))->addClass(ZBX_STYLE_NOWRAP)
	];

	$filter_table->addRow($row, 'form_row');
}

$filter_table->setFooter(new CCol(
	(new CButton('macro_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')
		->setEnabled(!$options['templated'])
		->removeId()
));

$overrides_popup_form_list->addRow(_('Filters'),
	(new CDiv([$override_evaltype, $filter_table]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addStyle('width: 100%;')
);

// operations
$operations_list = (new CTable())
	->addClass('lld-overrides-operations-table')
	->addStyle('width: 100%;')
	->setHeader([
		_('Condition'),
		(new CColHeader(''))->setWidth('50')
	])
	->addRow(
		(new CCol(
			(new CDiv(
				(new CButton('param_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
					->setEnabled(!$options['templated'])
					->removeId()
			))->addClass('step-action')
		))
	);

$overrides_popup_form_list->addRow(_('Operations'),
	(new CDiv($operations_list))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->addStyle('width: 100%;')
		->addStyle('max-width: 788px;')
);

$output['buttons'] = [
	[
		'title' => $options['old_name'] ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'enabled' => !$options['templated'],
		'action' => 'return lldoverrides.overrides.edit_form.validate(overlay);'
	]
];

$overrides_popup_form->addItem($overrides_popup_form_list);

$output['body'] = (new CDiv($overrides_popup_form))->toString();
$output['script_inline'] = 'lldoverrides.overrides.onStepOverlayReadyCb('.$options['no'].');';

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
