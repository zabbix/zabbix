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
	->setId('telemetry-aggregated-column-form')
	->setName('telemetry_aggregated_column')
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID)
	->addVar('action', $data['action'])
	->addVar('row_index', $data['row_index'])
	->addVar('signal_type', $data['signal_type'])
	->addVar('metric_point_type', $data['metric_point_type']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = (new CFormGrid())
	->addItem([
		new CLabel(_('Function'), 'label-function'),
		new CFormField(
			(new CSelect('function'))
				->setId('function')
				->setFocusableElementId('label-function')
				->setValue($data['function'])
				->addOptions(CSelect::createOptionsFromArray($data['functions']))
		)
	])
	->addItem([
		(new CLabel(_('Percentage'), 'percentile'))->setAsteriskMark()->setId('js-percentile-label'),
		(new CFormField(
			(new CTextBox('percentile', $data['percentile'], false, 7))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired()
		))->setId('js-percentile-field')
	])
	->addItem([
		(new CLabel(_('Column'), 'label-column'))->setId('js-column-label'),
		(new CFormField(
			(new CSelect('column'))
				->setId('column')
				->setFocusableElementId('label-column')
				->setValue($data['column'])
				->addOptions(CSelect::createOptionsFromArray($data['columns']))
		))->setId('js-column-field')
	])
	->addItem([
		(new CLabel(_('Alias'), 'alias'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('alias', $data['alias']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired()
		)
	]);

$form->addItem($form_grid);

$output = [
	'header' => $data['is_edit'] ? _('Aggregated column') : _('New aggregated column'),
	'script_inline' => getPagePostJs().$this->readJsFile('popup.telemetry.aggregatedcolumn.edit.js.php').
		'telemetry_aggregated_column_popup.init('.json_encode([
			'rules' => $data['js_validation_rules']
		]).');',
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['is_edit'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'telemetry_aggregated_column_popup.submit()'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
