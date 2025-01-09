<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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

use Widgets\ItemHistory\Includes\CWidgetFieldColumnsList;

$form = (new CForm())
	->setId('item_history_column_edit_form')
	->setName('item_history_column_edit_form')
	->addStyle('display: none;')
	->addVar('action', $data['action'])
	->addVar('update', 1);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

$form_grid = new CFormGrid();

$scripts = [];

if (array_key_exists('edit', $data)) {
	$form->addVar('edit', 1);
}

// Name.
$form_grid->addItem([
	(new CLabel(_('Name'), 'column_name'))->setAsteriskMark(),
	new CFormField(
		(new CTextBox('name', $data['name'], false))
			->setId('column_name')
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
			->setAriaRequired()
	)
]);

// Itemid.
$parameters = [
	'srctbl' => 'items',
	'srcfld1' => 'itemid',
	'dstfrm' => $form->getName(),
	'dstfld1' => 'itemid'
];

$parameters += $data['templateid'] === ''
	? [
		'real_hosts' => true,
		'resolve_macros' => true
	]
	: [
		'hostid' => $data['templateid'],
		'hide_host_filter' => true
	];

$item_select = (new CMultiSelect([
	'name' => 'itemid',
	'object_name' => 'items',
	'data' => $data['ms_item'] ? [$data['ms_item']] : '',
	'multiple' => false,
	'popup' => [
		'parameters' => $parameters
	],
	'add_post_js' => false
]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$scripts[] = $item_select->getPostJS();

$form_grid->addItem([
	(new CLabel(_('Item'), 'itemid_ms'))->setAsteriskMark(),
	new CFormField($item_select)
]);

// Base color.
$form_grid->addItem([
	new CLabel(_('Base color'), 'lbl_base_color'),
	new CFormField(new CColor('base_color', $data['base_color']))
]);

// Highlights table
$highlight_header_row = [
	'',
	_('Regular expression'),
	(new CColHeader(''))->setWidth('100%')
];

$highlights = (new CDiv(
	(new CTable())
		->setId('highlights_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($highlight_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($highlight_header_row))
		))
))
	->addClass('table-forms-separator')
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$highlights->addItem(
	(new CTemplateTag('highlights-row-tmpl'))
		->addItem((new CRow([
			(new CColor('highlights[#{rowNum}][color]', '#{color}'))->appendColorPickerJs(false),
			(new CTextBox('highlights[#{rowNum}][pattern]', '#{pattern}', false))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAriaRequired(),
			(new CButton('highlights[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
);

$form_grid->addItem([
	(new CLabel(_('Highlights'), 'highlights_table'))->addClass('js-highlights-row'),
	(new CFormField($highlights))->addClass('js-highlights-row')
]);

// Display.
$form_grid->addItem([
	(new CLabel([
		_('Display'),
		makeHelpIcon(_('Single line - result will be displayed in a single line and truncated to specified length.'))
			->addStyle('display: none;')
			->addClass('js-display-help-icon')
	], 'display'))->addClass('js-display-row'),
	(new CFormField([
		(new CRadioButtonList('display', (int) $data['display']))
			->addValue(_('As is'), CWidgetFieldColumnsList::DISPLAY_AS_IS)
			->addValue(_('Bar'), CWidgetFieldColumnsList::DISPLAY_BAR)
			->addValue(_('Indicators'), CWidgetFieldColumnsList::DISPLAY_INDICATORS)
			->addValue(_('HTML'), CWidgetFieldColumnsList::DISPLAY_HTML)
			->addValue(_('Single line'), CWidgetFieldColumnsList::DISPLAY_SINGLE_LINE)
			->setModern()
			->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CNumericBox('max_length', $data['max_length'], 3, false, false, false))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->addClass('js-single-line-input')
	]))->addClass('js-display-row')
]);

// Min value.
$form_grid->addItem([
	(new CLabel(_('Min'), 'min'))->addClass('js-min-row'),
	(new CFormField(
		(new CTextBox('min', $data['min']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-min-row')
]);

// Max value.
$form_grid->addItem([
	(new CLabel(_('Max'), 'max'))->addClass('js-max-row'),
	(new CFormField(
		(new CTextBox('max', $data['max']))
			->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			->setAttribute('placeholder', _('calculated'))
	))->addClass('js-max-row')
]);

// Thresholds table.
$threshold_header_row = [
	'',
	_('Threshold'),
	(new CColHeader(''))->setWidth('100%')
];

$thresholds = (new CDiv(
	(new CTable())
		->setId('thresholds_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($threshold_header_row)
		->setFooter(new CRow(
			(new CCol(
				(new CButtonLink(_('Add')))->addClass('element-table-add')
			))->setColSpan(count($threshold_header_row))
		))
))
	->addClass('table-forms-separator')
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

$thresholds->addItem(
	(new CTemplateTag('thresholds-row-tmpl'))
		->addItem((new CRow([
			(new CColor('thresholds[#{rowNum}][color]', '#{color}'))->appendColorPickerJs(false),
			(new CTextBox('thresholds[#{rowNum}][threshold]', '#{threshold}', false))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('thresholds[#{rowNum}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row'))
);

$form_grid->addItem([
	(new CLabel(_('Thresholds'), 'thresholds_table'))->addClass('js-thresholds-row'),
	(new CFormField($thresholds))->addClass('js-thresholds-row')
]);

// History data.
$form_grid->addItem([
	(new CLabel(_('History data'), 'history'))->addClass('js-history-row'),
	(new CFormField(
		(new CRadioButtonList('history', (int) $data['history']))
			->addValue(_('Auto'), CWidgetFieldColumnsList::HISTORY_DATA_AUTO)
			->addValue(_('History'), CWidgetFieldColumnsList::HISTORY_DATA_HISTORY)
			->addValue(_('Trends'), CWidgetFieldColumnsList::HISTORY_DATA_TRENDS)
			->setModern()
	))->addClass('js-history-row')
]);

// Monospace font.
$form_grid->addItem([
	(new CLabel(_('Use monospace font'), 'monospace_font'))->addClass('js-monospace-row'),
	(new CFormField(
		(new CCheckBox('monospace_font'))->setChecked($data['monospace_font'])
	))->addClass('js-monospace-row')
]);

// Local time.
$form_grid->addItem([
	(new CLabel([
		_('Display local time'),
		makeHelpIcon(_('This setting will display local time instead of the timestamp. "Show timestamp" must also be checked in the advanced configuration.'))
	], 'local_time'))->addClass('js-local-time-row'),
	(new CFormField(
		(new CCheckBox('local_time'))->setChecked($data['local_time'])
	))->addClass('js-local-time-row')
]);

// Display as image.
$form_grid->addItem([
	(new CLabel(_('Show thumbnail'), 'show_thumbnail'))->addClass('js-display-as-image-row'),
	(new CFormField(
		(new CCheckBox('show_thumbnail'))->setChecked($data['show_thumbnail'])
	))->addClass('js-display-as-image-row')
]);

$form
	->addItem($form_grid)
	->addItem(
		(new CScriptTag('
			item_history_column_edit.init('.json_encode([
				'form_id' => $form->getId(),
				'templateid' => $data['templateid'],
				'thresholds' => $data['thresholds'],
				'highlights' => $data['highlights'],
				'colors' => $data['colors'],
				'item_value_type' => $data['item_value_type'],
				'multiselect_item_name' => $data['ms_item'] ? $data['ms_item']['prefix'].$data['ms_item']['name'] : ''
			], JSON_THROW_ON_ERROR).');
		'))->setOnDocumentReady()
	);

$output = [
	'header'		=> array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'body'			=> $form->toString(),
	'buttons'		=> [
		[
			'title'		=> array_key_exists('edit', $data) ? _('Update') : _('Add'),
			'keepOpen'	=> true,
			'isSubmit'	=> true,
			'action'	=> 'item_history_column_edit.submit();'
		]
	],
	'script_inline'	=> implode('', $scripts).$this->readJsFile('column.edit.js.php', null, '')
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output, JSON_THROW_ON_ERROR);
