<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/js/configuration.correlation.edit.js.php';

$widget = (new CWidget())->setTitle(_('Event correlation rules'));

$form = (new CForm())
	->setId('correlation.edit')
	->setName('correlation.edit')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('form', $data['form']);

if ($data['correlationid']) {
	$form->addVar('correlationid', $data['correlationid']);
}

$correlation_tab = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['correlation']['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
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
				(new CCol(getcorrConditionDescription($condition, $correlation_condition_string_values[0][$j])))
					->addClass(ZBX_STYLE_TABLE_FORMS_OVERFLOW_BREAK),
				(new CCol([
					(new CButton('remove', _('Remove')))
						->onClick('javascript: removeCondition('.$i.');')
						->addClass(ZBX_STYLE_BTN_LINK)
						->removeId(),
					new CVar('conditions['.$i.']', $condition)
				]))->addClass(ZBX_STYLE_NOWRAP)
			],
			null, 'conditions_'.$i
		);

		$i++;
	}
}

$condition_table->addRow([
	(new CSimpleButton(_('Add')))
		->onClick('return PopUp("popup.condition.event.corr",'.json_encode([
			'type' => ZBX_POPUP_CONDITION_TYPE_EVENT_CORR
		]).', null, this);')
		->addClass(ZBX_STYLE_BTN_LINK)
]);

$correlation_tab
	->addRow(new CLabel(_('Type of calculation'), 'label-evaltype'), [
		(new CSelect('evaltype'))
			->setId('evaltype')
			->setValue($data['correlation']['filter']['evaltype'])
			->setFocusableElementId('label-evaltype')
			->addOptions(CSelect::createOptionsFromArray([
				CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
				CONDITION_EVAL_TYPE_AND => _('And'),
				CONDITION_EVAL_TYPE_OR => _('Or'),
				CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
			])),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('condition_label'),
		(new CTextBox('formula', $data['correlation']['filter']['formula']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setId('formula')
			->setAttribute('placeholder', 'A or (B and C) &hellip;')
	])
	->addRow(
		(new CLabel(_('Conditions'), $condition_table->getId()))->setAsteriskMark(),
		(new CDiv($condition_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
			->setAriaRequired()
	);

$correlation_tab
	->addRow(_('Description'),
		(new CTextArea('description', $data['correlation']['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow(_('Enabled'),
		(new CCheckBox('status', ZBX_CORRELATION_ENABLED))
			->setChecked($data['correlation']['status'] == ZBX_CORRELATION_ENABLED)
	);

// Operations tab.
$operation_tab = (new CFormList())
	->addRow(
		_('Close old events'),
		(new CCheckBox('operations[][type]', ZBX_CORR_OPERATION_CLOSE_OLD))
			->setChecked($data['correlation']['operations'][ZBX_CORR_OPERATION_CLOSE_OLD])
			->setId('operation_0_type')
	)
	->addRow(
		_('Close new event'),
		(new CCheckBox('operations[][type]', ZBX_CORR_OPERATION_CLOSE_NEW))
			->setChecked($data['correlation']['operations'][ZBX_CORR_OPERATION_CLOSE_NEW])
			->setId('operation_1_type')
	)
	->addRow('', (new CDiv((new CLabel(_('At least one operation must be selected.')))->setAsteriskMark())));

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

$widget->show();
