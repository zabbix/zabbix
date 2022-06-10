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
 * @var array $data
 */

$form = (new CForm())
	->setName('tophosts_column')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addItem(
		(new CInput('submit', 'submit'))
			->addStyle('display: none;')
			->removeId()
	);

$form_grid = new CFormGrid();

$scripts = [];

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Name.
$form_grid->addItem([
	new CLabel(_('Name'), 'name'),
	new CFormField(
		(new CTextBox('name', $data['name'], false))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
]);

// Data.
$form_grid->addItem([
	new CLabel(_('Data'), 'data'),
	new CFormField(
		(new CSelect('data'))
			->setValue($data['data'])
			->addOptions(CSelect::createOptionsFromArray([
				CWidgetFieldColumnsList::DATA_ITEM_VALUE => _('Item value'),
				CWidgetFieldColumnsList::DATA_HOST_NAME => _('Host name'),
				CWidgetFieldColumnsList::DATA_TEXT => _('Text')
			]))
	)
]);

// Static text.
$form_grid->addItem([
	(new CLabel(_('Text'), 'text'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('text', $data['text']))
			->setAttribute('placeholder', _('Text, supports {INVENTORY.*}, {HOST.*} macros'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
]);

// Item.
$item_select = (new CPatternSelect([
	'name' => 'item',
	'object_name' => 'items',
	'data' => $data['item'] === '' ? '' : [$data['item']],
	'multiple' => false,
	'popup' => [
		'parameters' => [
			'srctbl' => 'items',
			'srcfld1' => 'itemid',
			'real_hosts' => 1,
			'dstfrm' => $form->getName(),
			'dstfld1' => 'item'
		]
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$scripts[] = $item_select->getPostJS();

$form_grid->addItem([
	(new CLabel(_('Item'), 'item'))->setAsteriskMark(),
	new CFormField($item_select)
]);

// Time shift.
$form_grid->addItem([
	new CLabel(_('Time shift'), 'timeshift'),
	new CFormField(
		(new CTextBox('timeshift', $data['timeshift']))
			->setAttribute('placeholder', _('none'))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
]);

$numeric_only_warning = new CSpan([
	'&nbsp;',
	makeWarningIcon(_('With this setting only numeric items will be displayed in this column.'))
]);

// Aggregation function.
$form_grid->addItem([
	new CLabel([
		_('Aggregation function'),
		$numeric_only_warning->setId('tophosts-column-aggregate-function-warning')
	], 'aggregate_function'),
	new CFormField(
		(new CSelect('aggregate_function'))
			->setValue($data['aggregate_function'])
			->addOptions(CSelect::createOptionsFromArray([
				AGGREGATE_NONE => _('none'),
				AGGREGATE_MIN => _('min'),
				AGGREGATE_MAX => _('max'),
				AGGREGATE_AVG => _('avg'),
				AGGREGATE_COUNT => _('count'),
				AGGREGATE_SUM => _('sum'),
				AGGREGATE_FIRST => _('first'),
				AGGREGATE_LAST => _('last')
			]))
	)
]);

// Aggregation interval.
$form_grid->addItem([
	(new CLabel(_('Aggregation interval'), 'aggregate_interval'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('aggregate_interval', $data['aggregate_interval']))->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
	)
]);

// Display.
$form_grid->addItem([
	new CLabel([
		_('Display'),
		$numeric_only_warning->setId('tophosts-column-display-warning')
	], 'display'),
	new CFormField(
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), CWidgetFieldColumnsList::DISPLAY_AS_IS)
			->addValue(_('Bar'), CWidgetFieldColumnsList::DISPLAY_BAR)
			->addValue(_('Indicators'), CWidgetFieldColumnsList::DISPLAY_INDICATORS)
			->setModern(true)
	)
]);

// History data.
$form_grid->addItem([
	new CLabel([
		_('History data'),
		makeHelpIcon(
			_('This setting applies only to numeric data. Non-numeric data will always be taken from history.')
		)
	], 'history'),
	new CFormField(
		(new CRadioButtonList('history', (int) $data['history']))
			->addValue(_('Auto'), CWidgetFieldColumnsList::HISTORY_DATA_AUTO)
			->addValue(_('History'), CWidgetFieldColumnsList::HISTORY_DATA_HISTORY)
			->addValue(_('Trends'), CWidgetFieldColumnsList::HISTORY_DATA_TRENDS)
			->setModern(true)
	)
]);

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'base_color'),
	new CFormField(new CColor('base_color', $data['base_color']))
]);

// Min value.
$form_grid->addItem([
	new CLabel(_('Min'), 'min'),
	new CFormField(
		(new CTextBox('min', $data['min']))
			->setAttribute('placeholder', _('calculated'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
]);

// Max value.
$form_grid->addItem([
	new CLabel(_('Max'), 'max'),
	new CFormField(
		(new CTextBox('max', $data['max']))
			->setAttribute('placeholder', _('calculated'))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
	)
]);

// Thresholds table.
$header_row = [
	'',
	(new CColHeader(_('Threshold')))->setWidth('100%'),
	_('Action')
];

$thresholds = (new CDiv(
	(new CTable())
		->setId('thresholds_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			))->setColSpan(count($header_row))
		))
))
	->addClass('table-forms-separator')
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$thresholds->addItem(
	(new CScriptTemplate('thresholds-row-tmpl'))
		->addItem((new CRow([
			(new CColor('thresholds[#{rowNum}][color]', '#{color}'))->appendColorPickerJs(false),
			(new CTextBox('thresholds[#{rowNum}][threshold]', '#{threshold}', false))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired(),
			(new CButton('thresholds[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
);

$form_grid->addItem([
	new CLabel([
		_('Thresholds'),
		$numeric_only_warning->setId('tophosts-column-thresholds-warning')
	], 'thresholds_table'),
	new CFormField($thresholds)
]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			tophosts_column_edit_form.init('.json_encode([
				'form_name'			=> $form->getName(),
				'thresholds'		=> $data['thresholds'],
				'thresholds_colors'	=> $data['thresholds_colors']
			]).');
		'))->setOnDocumentReady()
	);

$output = [
	'header'		=> array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'script_inline'	=> implode('', $scripts).$this->readJsFile('popup.tophosts.column.edit.js.php'),
	'body'			=> $form->toString(),
	'buttons'		=> [
		[
			'title'		=> array_key_exists('edit', $data) ? _('Update') : _('Add'),
			'keepOpen'	=> true,
			'isSubmit'	=> true,
			'action'	=> '$(document.forms.tophosts_column).trigger("process.form", [overlay])'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
