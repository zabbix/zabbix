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


require_once dirname(__FILE__).'/js/configuration.correlation.edit.js.php';

$widget = (new CWidget())->setTitle(_('Event correlation rules'));

$form = (new CForm())
	->setName('correlation.edit')
	->addVar('form', $data['form']);

if ($data['correlationid']) {
	$form->addVar('correlationid', $data['correlationid']);
}

$correlation_tab = (new CFormList())
	->addRow(_('Name'),
		(new CTextBox('name', $data['correlation']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// Create condition table.
$condition_table = (new CTable(_('No conditions defined.')))
	->setId('condition_table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Label'), _('Name'), _('Action')]);

$i = 0;

if ($data['correlation']['filter']['conditions']) {
	$correlation_condition_string_values = corrConditionValueToString([$data['correlation']]);

	foreach ($data['correlation']['filter']['conditions'] as $j => $condition) {
		// For some types operators are optional. Set the default "=" if operator is not set.
		if (!array_key_exists('operator', $condition)) {
			$condition['operator'] = CONDITION_OPERATOR_EQUAL;
		}

		if (!array_key_exists($condition['type'], $data['allowedConditions'])) {
			continue;
		}

		$label = isset($condition['formulaid']) ? $condition['formulaid'] : num2letter($i);

		$labelSpan = (new CSpan($label))
			->addClass('label')
			->setAttribute('data-type', $condition['type'])
			->setAttribute('data-formulaid', $label);

		$condition_table->addRow([
				$labelSpan,
				getcorrConditionDescription($condition, $correlation_condition_string_values[0][$j]),
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

$correlation_tab
	->addRow(_('Type of calculation'), [
		new CComboBox('evaltype', $data['correlation']['filter']['evaltype'], 'processTypeOfCalculation()', [
			CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
			CONDITION_EVAL_TYPE_AND => _('And'),
			CONDITION_EVAL_TYPE_OR => _('Or'),
			CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
		]),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('condition_label'),
		(new CTextBox('formula', $data['correlation']['filter']['formula']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('formula')
			->setAttribute('placeholder', 'A or (B and C) &hellip;')
	])
	->addRow(_('Conditions'),
		(new CDiv($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

$condition2 = null;

switch ($data['new_condition']['type']) {
	case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
	case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		$condition = (new CTextBox('new_condition[tag]'))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag'));
		break;

	case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
		$condition = (new CMultiSelect([
			'name' => 'new_condition[groupids][]',
			'objectName' => 'hostGroup',
			'objectOptions' => [
				'editable' => true
			],
			'defaultValue' => 0,
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1=new_condition_groupids_'.
					'&srcfld1=groupid&writeonly=1&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH);
		break;

	case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
		$condition = (new CTextBox('new_condition[newtag]', $data['new_condition']['newtag']))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('new event tag'));
		$condition2 = (new CTextBox('new_condition[oldtag]', $data['new_condition']['oldtag']))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('old event tag'));
		break;

	case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
	case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
		$condition = (new CTextBox('new_condition[value]', $data['new_condition']['value']))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('value'));
		$condition2 = (new CTextBox('new_condition[tag]'))
			->setWidth(ZBX_TEXTAREA_TAG_WIDTH)
			->setAttribute('placeholder', _('tag'));
		break;

	default:
		$condition = null;
}

// Create operator combobox separately, since they depend on condition type.
$condition_operators_combobox = new CComboBox('new_condition[operator]', $data['new_condition']['operator']);
foreach (getOperatorsByCorrConditionType($data['new_condition']['type']) as $operator) {
	$condition_operators_combobox->addItem($operator, corrConditionOperatorToString($operator));
}

$correlation_tab
	->addRow(_('New condition'),
		(new CDiv(
			(new CTable())
				->setAttribute('style', 'width: 100%;')
				->addRow(
					new CCol([
						new CComboBox('new_condition[type]', $data['new_condition']['type'], 'submit()',
							$data['allowedConditions']
						),
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$condition2,
						$condition2 === null ? null : (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$condition_operators_combobox,
						(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
						$condition
					])
				)
				->addRow(
					(new CSimpleButton(_('Add')))
						->onClick('javascript: submitFormWithParam("'.$form->getName().'", "add_condition", "1");')
						->addClass(ZBX_STYLE_BTN_LINK)
				)
		))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
	->addRow(_('Description'),
		(new CTextArea('description', $data['correlation']['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Enabled'),
		(new CCheckBox('status', ZBX_CORRELATION_ENABLED))
			->setChecked($data['correlation']['status'] == ZBX_CORRELATION_ENABLED)
	);

// Operations tab.
$operation_tab = new CFormList('operationlist');

$operations_table = (new CTable())->setAttribute('style', 'width: 100%;')->setHeader([_('Details'), _('Action')]);

if ($data['correlation']['operations']) {
	foreach ($data['correlation']['operations'] as $operationid => $operation) {
		if (!array_key_exists($operation['type'], $data['allowedOperations'])) {
			continue;
		}

		$operations_table->addRow([
			getCorrOperationDescription($operation),
			(new CCol([
				(new CButton('remove', _('Remove')))
					->onClick('javascript: removeOperation('.$operationid.');')
					->addClass(ZBX_STYLE_BTN_LINK),
				new CVar('operations['.$operationid.']', $operation)
			]))->addClass(ZBX_STYLE_NOWRAP)
		], null, 'operations_'.$operationid);
	}
}

$operation_tab
	->addRow(_('Operations'),
		(new CDiv([$operations_table]))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	)
	->addRow(_('New operation'),
		(new CDiv(
			(new CTable())
				->setAttribute('style', 'width: 100%;')
				->addRow(new CComboBox('new_operation[type]', $data['new_operation']['type'], null,
					corrOperationTypes()
				))
				->addRow(
					(new CSimpleButton(_('Add')))
						->onClick('javascript: submitFormWithParam("'.$form->getName().'", "add_operation", "1");')
						->addClass(ZBX_STYLE_BTN_LINK)
				)
		))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);

// Append tabs to form.
$correlation_tabs = (new CTabView())
	->addTab('correlationTab', _('Correlation'), $correlation_tab)
	->addTab('operationTab', _('Operations'), $operation_tab);

if (!hasRequest('form_refresh')) {
	$correlation_tabs->setSelected(0);
}

// Append buttons to form.
if ($data['correlationid']) {
	$correlation_tabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')), [
			new CButton('clone', _('Clone')),
			new CButtonDelete(
				_('Delete current correlation?'),
				url_param('form').url_param('correlationid')
			),
			new CButtonCancel()
		]
	));
}
else {
	$correlation_tabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$form->addItem($correlation_tabs);

$widget->addItem($form);

return $widget;
