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

require_once dirname(__FILE__).'/js/configuration.template.massupdate.js.php';

$widget = (new CWidget())->setTitle(_('Templates'));

// Create form.
$form = (new CForm())
	->setName('templateForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', 'template.massupdate')
	->setId('templateForm')
	->disablePasswordAutofill();

foreach ($data['templates'] as $templateid) {
	$form->addVar('templates['.$templateid.']', $templateid);
}

/*
 * Template tab
 */
$template_form_list = new CFormList('template-form-list');

$template_form_list
	->addRow(
		(new CVisibilityBox('visible[groups]', 'groups-div', _('Original')))
			->setLabel(_('Host groups'))
			->setChecked(array_key_exists('groups', $data['visible']))
			->setAttribute('autofocus', 'autofocus'),
		(new CDiv([
			(new CRadioButtonList('mass_update_groups', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 5px;'),
			(new CMultiSelect([
				'name' => 'groups[]',
				'object_name' => 'hostGroup',
				'add_new' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
				'data' => $data['groups'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'host_groups',
						'srcfld1' => 'groupid',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'groups_',
						'editable' => true
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		]))->setId('groups-div')
	)
	->addRow(
		(new CVisibilityBox('visible[description]', 'description', _('Original')))
			->setLabel(_('Description'))
			->setChecked(array_key_exists('description', $data['visible'])),
		(new CTextArea('description', $data['description']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
	);

/*
 * Linked templates tab
 */
$linked_templates_form_list = new CFormList('linked-templates-form-list');

$new_template_table = (new CTable())
	->addRow(
		(new CRadioButtonList('mass_action_tpls', (int) $data['mass_action_tpls']))
			->addValue(_('Link'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Unlink'), ZBX_ACTION_REMOVE)
			->setModern(true)
	)
	->addRow([
		(new CMultiSelect([
			'name' => 'linked_templates[]',
			'object_name' => 'templates',
			'data' => $data['linked_templates'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'linked_templates_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addRow([
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('mass_clear_tpls'))
				->setLabel(_('Clear when unlinking'))
				->setChecked($data['mass_clear_tpls'] == 1)
			)
	]);

$linked_templates_form_list->addRow(
	(new CVisibilityBox('visible[linked_templates]', 'linked-templates-div', _('Original')))
		->setLabel(_('Link templates'))
		->setChecked(array_key_exists('linked_templates', $data['visible'])),
	(new CDiv($new_template_table))
		->setId('linked-templates-div')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Tags tab
 */
$tags_form_list = (new CFormList('tags-form-list'))
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))
			->setLabel(_('Tags'))
			->setChecked(array_key_exists('tags', $data['visible'])),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable($data['tags'])
				->setHeader([_('Name'), _('Value'), _('Action')])
				->setId('tags-table')
		]))->setId('tags-div')
	);

// Append tabs to the form.
$tabs = (new CTabView())
	->addTab('template_tab', _('Template'), $template_form_list)
	->addTab('linked_templates_tab', _('Linked templates'), $linked_templates_form_list)
	->addTab('tags_tab', _('Tags'), $tags_form_list);

// Macros.
$tabs->addTab('macros_tab', _('Macros'), new CPartial('massupdate.macros.tab', [
	'visible' => $data['visible'],
	'macros' => $data['macros'],
	'macros_checkbox' => $data['macros_checkbox'],
	'macros_visible' => $data['macros_visible']
]));

// Reset tabs when opening the form for the first time.
if (!hasRequest('masssave')) {
	$tabs->setSelected(0);
}

// Append buttons to the form.
$tabs->setFooter(makeFormFooter(
	new CSubmit('masssave', _('Update')),
	[new CButtonCancel()]
));

$form->addItem($tabs);

$widget->addItem($form);

$widget->show();
