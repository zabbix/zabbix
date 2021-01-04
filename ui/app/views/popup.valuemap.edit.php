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
	->addVar('source-name', $data['name']);

if ($data['valuemapid'] > 0) {
	$form->addVar('valuemapid', $data['valuemapid']);
}

if ($data['edit'] > 0) {
	$form->addVar('edit', $data['edit']);
}

$form_grid = (new CFormGrid())->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_1_1);

$table = (new CTable())
	->setId('mappings_table')
	->setHeader([_('Value'), '', _('Mapped to'), _('Action')])
	->addStyle('width: 100%;');

if (count($data['mappings'])) {
	$i = 0;
	foreach ($data['mappings'] as $mapping) {
		$table->addItem([
			(new CRow([
				(new CTextBox('mappings['.$i.'][value]', $mapping['value'], false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
				'&rArr;',
				(new CTextBox('mappings['.$i.'][newvalue]', $mapping['newvalue'], false, 64))
					->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
					->setAriaRequired(),
				(new CButton('mappings['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			]))->addClass('form_row')
		]);

		$i++;
	}
}
else {
	$table->addItem([
		(new CRow([
			(new CTextBox('mappings[0][value]', '', false, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
			'&rArr;',
			(new CTextBox('mappings[0][newvalue]', '', false, 64))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAriaRequired(),
			(new CButton('mappings[0][remove]', _('Remove')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-remove')
		]))->addClass('form_row')
	]);
}

$table->addRow([
		(new CCol(
			(new CButton('mapping_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		))->setColSpan(4)
	]);

$form_grid
	->addItem([
		(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
		(new CFormField(
			(new CTextBox('name', $data['name']))
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

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.valuemap.edit.js.php'),
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Add'),
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
