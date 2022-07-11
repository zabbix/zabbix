<?php declare(strict_types = 0);
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

// Create form.
$form = (new CForm())
	->setId('massupdate-form')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', 'popup.massupdate.template')
	->addVar('update', '1')
	->addVar('ids', $data['ids'])
	->addVar('location_url', $data['location_url'])
	->disablePasswordAutofill();

/*
 * Template tab
 */
$template_tab = new CFormList('template-form-list');

$link_templates = (new CTable())
	->addRow(
		(new CRadioButtonList('mass_action_tpls', ZBX_ACTION_ADD))
			->addValue(_('Link'), ZBX_ACTION_ADD)
			->addValue(_('Replace'), ZBX_ACTION_REPLACE)
			->addValue(_('Unlink'), ZBX_ACTION_REMOVE)
			->setModern(true)
	)
	->addRow([
		(new CMultiSelect([
			'name' => 'linked_templates[]',
			'object_name' => 'templates',
			'data' => [],
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
			)
	]);

$template_tab->addRow(
	(new CVisibilityBox('visible[linked_templates]', 'linked-templates-div', _('Original')))
		->setLabel(_('Link templates')),
	(new CDiv($link_templates))
		->setId('linked-templates-div')
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$template_tab
	->addRow(
		(new CVisibilityBox('visible[groups]', 'groups-div', _('Original')))
			->setLabel(_('Host groups'))
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
				'data' => [],
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
		(new CVisibilityBox('visible[description]', 'description', _('Original')))->setLabel(_('Description')),
		(new CTextArea('description', ''))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setMaxlength(DB::getFieldLength('hosts', 'description'))
	);

$tags_tab = (new CFormList('tags-form-list'))
	->addRow(
		(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))->setLabel(_('Tags')),
		(new CDiv([
			(new CRadioButtonList('mass_update_tags', ZBX_ACTION_ADD))
				->addValue(_('Add'), ZBX_ACTION_ADD)
				->addValue(_('Replace'), ZBX_ACTION_REPLACE)
				->addValue(_('Remove'), ZBX_ACTION_REMOVE)
				->setModern(true)
				->addStyle('margin-bottom: 10px;'),
			renderTagTable([['tag' => '', 'value' => '']])
				->setHeader([_('Name'), _('Value'), _('Action')])
				->setId('tags-table')
		]))->setId('tags-div')
	);

// Append tabs to the form.
$tabs = (new CTabView())
	->addTab('template_tab', _('Template'), $template_tab)
	->addTab('tags_tab', _('Tags'), $tags_tab)
	->setSelected(0);

// Macros.
$tabs->addTab('macros_tab', _('Macros'), new CPartial('massupdate.macros.tab', [
	'visible' => [],
	'macros' => [['macro' => '', 'type' => ZBX_MACRO_TYPE_TEXT, 'value' => '', 'description' => '']],
	'macros_checkbox' => [ZBX_ACTION_ADD => 0, ZBX_ACTION_REPLACE => 0, ZBX_ACTION_REMOVE => 0,
		ZBX_ACTION_REMOVE_ALL => 0
	]
]));

// Value mapping.
$tabs->addTab('valuemaps_tab', _('Value mapping'), new CPartial('massupdate.valuemaps.tab', [
	'visible' => [],
	'hostids' => $data['ids'],
	'context' => 'template'
]));

$form->addItem($tabs);

$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.tmpl.js.php')));
$form->addItem(new CJsScript($this->readJsFile('popup.massupdate.macros.js.php')));

$output = [
	'header' => $data['title'],
	'body' => $form->toString(),
	'buttons' => [
		[
			'title' => _('Update'),
			'class' => '',
			'keepOpen' => true,
			'isSubmit' => true,
			'action' => 'return submitPopup(overlay);'
		]
	]
];

$output['script_inline'] = $this->readJsFile('popup.massupdate.js.php');
$output['script_inline'] .= getPagePostJs();

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
