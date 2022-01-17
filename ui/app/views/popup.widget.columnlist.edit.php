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
$form = (new CForm())
	->setName('top_hosts_data_grid')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addItem((new CInput('submit', 'submit'))
		->addStyle('display: none;')
		->removeId()
	);
$form_grid = new CFormGrid();

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

// Item.
$item_select = (new CPatternSelect([
		'name' => 'item',
		'object_name' => 'items',
		'data' => $data['item'] === '' ? '' : [$data['item']],
		'placeholder' => _('item pattern'),
		'multiple' => false,
		'popup' => [
			'parameters' => [
				'srctbl' => 'items',
				'srcfld1' => 'itemid',
				'real_hosts' => 1,
				'numeric' => 1,
				'webitems' => 1,
				'orig_names' => 1,
				'dstfrm' => $form->getName(),
				'dstfld1' => 'item'
			]
		],
		'add_post_js' => false
	]))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
$scripts[] = $item_select->getPostJS();
$form_grid->addItem([
	(new CLabel(_('Item'), 'item'))->setAsteriskMark(),
	new CFormField($item_select)
]);

// Aggregation function.
$form_grid->addItem([
	new CLabel(_('Aggregation function'), 'function'),
	new CFormField(
		(new CSelect('function'))
			->setValue($data['function'])
			->addOptions(CSelect::createOptionsFromArray([
				CWidgetFieldColumnsList::FUNC_NONE => _('none'),
				CWidgetFieldColumnsList::FUNC_MIN => _('min'),
				CWidgetFieldColumnsList::FUNC_MAX => _('max'),
				CWidgetFieldColumnsList::FUNC_AVG => _('avg'),
				CWidgetFieldColumnsList::FUNC_LAST => _('last'),
				CWidgetFieldColumnsList::FUNC_FIRST => _('first'),
				CWidgetFieldColumnsList::FUNC_COUNT => _('count')
			]))
	)
]);

// From.
$form_grid->addItem([
	(new CLabel(_('From'), 'from'))->setAsteriskMark(),
	new CFormField(new CDateSelector('from', $data['from']))
]);

// To.
$form_grid->addItem([
	(new CLabel(_('To'), 'to'))->setAsteriskMark(),
	new CFormField(new CDateSelector('to', $data['to']))
]);

// Display.
$form_grid->addItem([
	new CLabel(_('Display'), 'display'),
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
	new CLabel(_('History data'), 'history'),
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

// Static text.
$form_grid->addItem([
	new CLabel(_('Text'), 'text'),
	new CFormField(
		(new CTextBox('text', $data['text']))
			->setAttribute('placeholder', _('Text, supports {INVENTORY.*}, {HOST.*} macros'))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
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
$header_row = ['', _('Threshold'), _('Action')];
$thresholds = (new CDiv([
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
]))->addClass('table-forms-separator');
$thresholds->addItem(
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->setId('thresholds-row-tmpl')
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
	new CLabel(_('Thresholds'), 'thresholds_table'),
	new CFormField($thresholds)
]);

$form->addItem($form_grid);
// Set thresholds colors.
$scripts[] = 'colorPalette.setThemeColors('.json_encode($data['thresholds_colors']).');';
$scripts[] = $this->readJsFile('popup.widget.columnlist.edit.js.php', [
	'thresholds'	=> $data['thresholds'],
	'form'			=> $form->getName()
]);
$output = [
	'header'		=> array_key_exists('edit', $data) ? _('Update column') : _('New column'),
	'script_inline'	=> implode('', $scripts),
	'body'			=> $form->toString(),
	'buttons'		=> [
		[
			'title'		=> $data['edit'] ? _('Update') : _('Add'),
			'class'		=> '',
			'keepOpen'	=> true,
			'isSubmit'	=> true,
			'action'	=> 'return $(document.forms.top_hosts_data_grid).trigger("submit.form", [overlay])'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
