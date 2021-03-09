<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	->cleanItems()
	->setId('valuemap-edit-form')
	->setName('valuemap-edit-form')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addVar('source-name', $data['name'])
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

$form_grid = (new CFormGrid())->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_1_1);

$table = (new CTable())
	->setId('mappings_table')
	->addClass(ZBX_STYLE_TABLE_FORMS)
	->setHeader([_('Value'), '', _('Mapped to'), _('Action')])
	->addStyle('width: 100%;');

$table->addRow([
		(new CCol(
			(new CButton(null, _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		))->setColSpan(4)
	]);

$form_grid
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('name', $data['name'], false, DB::getFieldLength('valuemap', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autofocus', 'autofocus')
				->setAriaRequired()
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	])
	->addItem([
		(new CLabel(_('Mappings'), 'mappings'))->setAsteriskMark(),
		(new CFormField(
			(new CDiv($table))
				->addStyle('width: 100%;')
				->addClass('table-forms-separator')
		))->addClass(CFormField::ZBX_STYLE_FORM_FIELD_FLUID)
	]);

$form->addItem($form_grid);

// Value map mapping template.
$form->addItem((new CScriptTemplate('mapping-row-tmpl'))->addItem(
	(new CRow([
		(new CTextBox('mappings[#{rowNum}][value]', '#{value}', false, DB::getFieldLength('valuemap_mapping', 'value')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		'&rArr;',
		(new CTextBox('mappings[#{rowNum}][newvalue]', '#{newvalue}', false, DB::getFieldLength('valuemap_mapping', 'newvalue')))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired(),
		(new CButton('mappings[#{rowNum}][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove')
	]))->addClass('form_row')
));

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.valuemap.edit.js.php', ['mappings' => $data['mappings']]),
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
