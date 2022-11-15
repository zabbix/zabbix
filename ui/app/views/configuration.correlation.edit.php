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

$this->addJsFile('popup.condition.common.js');
$this->includeJsFile('configuration.correlation.edit.js.php');

$widget = (new CWidget())->setTitle(_('Event correlation rules'));

$form = (new CForm())
	->setId('correlation.edit')
	->setName('correlation.edit')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'correlation.condition.add')
		->getUrl()
	)
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

if ($data['correlationid'] != 0) {
	$form->addVar('correlationid', $data['correlationid']);
}

$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name']))
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

if ($data['conditions']) {
	foreach ($data['conditions'] as $condition) {
		// For some types operators are optional. Set the default "=" if operator is not set.
		if (!array_key_exists('operator', $condition)) {
			$condition['operator'] = CONDITION_OPERATOR_EQUAL;
		}

		if (!array_key_exists($condition['type'], $data['allowedConditions'])) {
			continue;
		}

		$label = ($data['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) ? $condition['formulaid'] : num2letter($i);

		$labelSpan = (new CSpan($label))
			->addClass('label')
			->setAttribute('data-type', $condition['type'])
			->setAttribute('data-formulaid', $label);

		$condition_table->addRow([
				$labelSpan,
				(new CCol(CCorrelationHelper::getConditionDescription($condition, $data['group_names'])))
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
		->onClick(
			'return PopUp("popup.condition.event.corr", '.
				json_encode(['type' => ZBX_POPUP_CONDITION_TYPE_EVENT_CORR]).',
				{dialogue_class: "modal-popup-medium"}
			);'
		)
		->addClass(ZBX_STYLE_BTN_LINK)
]);

$form_list
	->addRow(new CLabel(_('Type of calculation'), 'label-evaltype'), [
		(new CSelect('evaltype'))
			->setId('evaltype')
			->setValue($data['evaltype'])
			->setFocusableElementId('label-evaltype')
			->addOptions(CSelect::createOptionsFromArray([
				CONDITION_EVAL_TYPE_AND_OR => _('And/Or'),
				CONDITION_EVAL_TYPE_AND => _('And'),
				CONDITION_EVAL_TYPE_OR => _('Or'),
				CONDITION_EVAL_TYPE_EXPRESSION => _('Custom expression')
			])),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSpan())->setId('condition_label'),
		(new CTextBox('formula', $data['formula']))
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

$form_list
	->addRow(_('Description'),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
	)
	->addRow(_('Operations'),
		(new CCheckBoxList())
			->setVertical(true)
			->setOptions([
				[
					'label' => _('Close old events'),
					'checked' => $data['op_close_old'],
					'name' => 'op_close_old',
					'id' => 'operation_0_type',
					'value' => '1'
				],
				[
					'label' => _('Close new events'),
					'checked' => $data['op_close_new'],
					'name' => 'op_close_new',
					'id' => 'operation_1_type',
					'value' => '1'
				]
			])
	)
	->addRow('', (new CDiv((new CLabel(_('At least one operation must be selected.')))->setAsteriskMark())))
	->addRow(_('Enabled'),
		(new CCheckBox('status', ZBX_CORRELATION_ENABLED))
			->setChecked($data['status'] == ZBX_CORRELATION_ENABLED)
			->setUncheckedValue(ZBX_CORRELATION_DISABLED)
	);

// Append tabs to form.
$correlation_tabs = (new CTabView())->addTab('correlationTab', _('Correlation'), $form_list);

// Append buttons to form.
$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'correlation.list')
	->setArgument('page', CPagerHelper::loadPage('correlation.list', null))
))->setId('cancel');

if ($data['correlationid'] == 0) {
	$add_button = (new CSubmitButton(_('Add'), 'action', 'correlation.create'))->setId('add');
	$correlation_tabs->setFooter(makeFormFooter($add_button, [$cancel_button]));
}
else {
	$update_button = (new CSubmitButton(_('Update'), 'action', 'correlation.update'))->setId('update');
	$clone_button = (new CSimpleButton(_('Clone')))->setId('clone');
	$delete_button = (new CRedirectButton(_('Delete'), (new CUrl('zabbix.php'))
			->setArgument('action', 'correlation.delete')
			->setArgument('correlationids', (array) $data['correlationid'])
			->setArgumentSID(),
		_('Delete current correlation?')
	))->setId('delete');

	$correlation_tabs->setFooter(makeFormFooter($update_button, [$clone_button, $delete_button, $cancel_button]));
}

$form->addItem($correlation_tabs);

$widget->addItem($form);

$widget->show();
