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
$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('valuemap')))->removeId())
	->setId('valuemap-edit-form')
	->setName('valuemap-edit-form')
	->addVar('action', $data['action'])
	->addVar('update', 1)
	->addVar('source-name', $data['name']);

// Enable form submitting on Enter.
$form->addItem((new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN));

if ($data['valuemapid']) {
	$form->addVar('valuemapid', $data['valuemapid']);
}

if ($data['edit']) {
	$form->addVar('edit', $data['edit']);
}

foreach (array_values($data['valuemap_names']) as $index => $name) {
	$form->addVar('valuemap_names['.$index.']', $name);
}

$form_grid = (new CFormGrid())
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
		(new CLabel(_('Mappings'), 'mappings-table'))->setAsteriskMark(),
		new CFormField(
			(new CDiv([
				(new CTable())
					->setId('mappings-table')
					->addClass(ZBX_STYLE_TABLE_FORMS)
					->setHeader(['', _('Type'), _('Value'), '', _('Mapped to'), ''])
					->setFooter(
						(new CCol(
							(new CButtonLink(_('Add')))->addClass('element-table-add')
						))->setColSpan(7)
					),
				new CTemplateTag('mapping-row-tmpl',
					(new CRow([
						(new CCol((new CDiv)->addClass(ZBX_STYLE_DRAG_ICON)))->addClass(ZBX_STYLE_TD_DRAG_ICON),
						(new CSelect('mappings[#{rowNum}][type]'))
							->setValue('#{type}')
							->addOptions(CSelect::createOptionsFromArray([
								VALUEMAP_MAPPING_TYPE_EQUAL => _('equals'),
								VALUEMAP_MAPPING_TYPE_GREATER_EQUAL => _('is greater than or equals'),
								VALUEMAP_MAPPING_TYPE_LESS_EQUAL => _('is less than or equals'),
								VALUEMAP_MAPPING_TYPE_IN_RANGE => _('in range'),
								VALUEMAP_MAPPING_TYPE_REGEXP => _('regexp'),
								VALUEMAP_MAPPING_TYPE_DEFAULT => _('default')
							])),
						(new CTextBox('mappings[#{rowNum}][value]', '#{value}', false,
							DB::getFieldLength('valuemap_mapping', 'value')
						))
							->removeId()
							->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
						RARR(),
						(new CTextBox('mappings[#{rowNum}][newvalue]', '#{newvalue}', false,
							DB::getFieldLength('valuemap_mapping', 'newvalue')
						))
							->removeId()
							->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
							->setAriaRequired(),
						(new CButtonLink(_('Remove')))->addClass('element-table-remove')
					]))->addClass('form_row')
				)
			]))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		)
	]);

$form->addItem($form_grid);

$output = [
	'header' => $data['title'],
	'script_inline' => $this->readJsFile('popup.valuemap.edit.js.php', $data),
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
