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
$form = (new CForm())
	->setId('valuemap-edit-form')
	->setName('valuemap-edit-form')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addVar('source-name', $data['name'])
	->addItem(new CJsScript($this->readJsFile('../../../include/views/js/editabletable.js.php')))
	->addItem((new CInput('submit', 'submit'))
		->addStyle('display: none;')
		->removeId()
	);

if ($data['valuemapid']) {
	$form->addVar('valuemapid', $data['valuemapid']);
}

if ($data['edit']) {
	$form->addVar('edit', $data['edit']);
}

foreach (array_values($data['valuemap_names']) as $index => $name) {
	$form->addVar('valuemap_names['.$index.']', $name);
}

$form_grid = new CFormGrid();

$header_row = ['', _('Type'), _('Value'), '', _('Mapped to'), _('Action'), ''];
$mappings = (new CDiv([
	(new CTable())
		->setId('mappings_table')
		->addClass(ZBX_STYLE_TABLE_FORMS)
		->setHeader($header_row)
		->addRow((new CRow)->setAttribute('data-insert-point', 'append'))
		->setFooter(new CRow(
			(new CCol(
				(new CButton(null, _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setAttribute('data-row-action', 'add_row')
			))->setColSpan(count($header_row))
		))
]))->setAttribute('data-sortable-pairs-table', '1');

// Value map mapping template.
$mappings->addItem(
	(new CTag('script', true))
		->setAttribute('type', 'text/x-jquery-tmpl')
		->addItem((new CRow([
			(new CCol((new CDiv)
				->addClass(ZBX_STYLE_DRAG_ICON)))
				->addClass(ZBX_STYLE_TD_DRAG_ICON),
			(new CSelect('mappings[#{index}][type]'))
				->setValue('#{type}')
				->addOptions(CSelect::createOptionsFromArray([
					VALUEMAP_MAPPING_TYPE_EQUAL => _('equals'),
					VALUEMAP_MAPPING_TYPE_GREATER_EQUAL => _('is greater than or equals'),
					VALUEMAP_MAPPING_TYPE_LESS_EQUAL => _('is less than or equals'),
					VALUEMAP_MAPPING_TYPE_IN_RANGE => _('in range'),
					VALUEMAP_MAPPING_TYPE_REGEXP => _('regexp'),
					VALUEMAP_MAPPING_TYPE_DEFAULT => _('default')
				])),
			(new CTextBox('mappings[#{index}][value]', '#{value}', false, DB::getFieldLength('valuemap_mapping', 'value')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'&rArr;',
			(new CTextBox('mappings[#{index}][newvalue]', '#{newvalue}', false, DB::getFieldLength('valuemap_mapping', 'newvalue')))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('mappings[#{index}][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->setAttribute('data-row-action', 'remove_row')
		])))
);

$mappings_data = (new CTag('script', true))->setAttribute('type', 'text/json');
$mappings_data->items = [json_encode($data['mappings'])];
$mappings->addItem($mappings_data);

$form_grid
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		new CFormField(
			(new CTextBox('name', $data['name'], false, DB::getFieldLength('valuemap', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autofocus', 'autofocus')
				->setAriaRequired()
		)
	])
	->addItem([
		(new CLabel(_('Mappings'), 'mappings'))->setAsteriskMark(),
		new CFormField(
			(new CDiv($mappings))->addClass('table-forms-separator')
		)
	]);

$form->addItem($form_grid);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.valuemap.edit.js.php', []),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => $data['edit'] ? _('Update') : _('Add'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitValueMap(overlay);'
		]
	]
];

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
