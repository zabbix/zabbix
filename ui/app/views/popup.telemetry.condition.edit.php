<?php declare(strict_types = 0);
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
 * @var array $data
 */

$form = (new CForm())
	->setId('telemetry-condition-form')
	->setName('telemetry_condition')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('row_index', $data['row_index'])
	->addVar('signal_type', $data['signal_type'])
	->addVar('metric_point_type', $data['metric_point_type']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$operator = new CRadioButtonList('operator', (int) $data['operator']);

foreach ($data['operators'] as $value => $label) {
	$operator->addValue($label, $value);
}

$operator->setModern();

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Type'), 'label-column'),
		new CFormField(
			(new CSelect('column'))
				->setId('column')
				->setFocusableElementId('label-column')
				->setValue($data['column'])
				->addOptions(CSelect::createOptionsFromArray($data['columns']))
		)
	])
	->addItem([
		(new CLabel(_('Attribute key'), 'attribute_key'))->setAsteriskMark()->setId('js-key-label'),
		(new CFormField(
			(new CTextBox('attribute_key', $data['attribute_key']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('data-notrim', '')
				->setAriaRequired()
		))->setId('js-key-field')
	])
	->addItem([
		new CLabel(_('Operator'), 'operator'),
		new CFormField($operator)
	])
	->addItem([
		(new CLabel(_('Attribute value'), 'value'))->setAsteriskMark()->setId('js-value-label'),
		(new CFormField(
			(new CTextBox('value', $data['value']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('data-notrim', '')
				->setAriaRequired()
		))->setId('js-value-field')
	]);

$form->addItem($form_grid);

$output = [
	'header' => $data['is_edit'] ? _('Condition') : _('New condition'),
	'script_inline' => getPagePostJs().$this->readJsFile('popup.telemetry.condition.edit.js.php').
		'telemetry_condition_popup.init('.json_encode([
			'rules' => $data['js_validation_rules'],
			'complex_columns' => $data['complex_columns'],
			'condition_operators' => $data['condition_operators']
		]).');',
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'telemetry_condition_popup.submit()'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
