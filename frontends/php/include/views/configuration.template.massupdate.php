<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.template.massupdate.js.php';

$widget = (new CWidget())->setTitle(_('Templates'));

// create form
$view = (new CForm())
	->setName('templateForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->addVar('action', 'template.massupdate')
	->setId('templateForm');
foreach ($data['templates'] as $templateid) {
	$view->addVar('templates['.$templateid.']', $templateid);
}

// create form list
$template_form_list = new CFormList('templateFormList');

// update host groups
$groups_to_update = $data['groups']
	? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $data['groups'],
		'editable' => true
	]), ['groupid' => 'id'])
	: [];

$template_form_list->addRow(
	(new CVisibilityBox('visible[groups]', 'groups-div', _('Original')))
		->setLabel(_('Host groups'))
		->setChecked(array_key_exists('groups', $data['visible']))
		->setAttribute('autofocus', 'autofocus'),
	(new CDiv([
		(new CRadioButtonList('mass_update_groups', ZBX_MASSUPDATE_ACTION_ADD))
			->addValue(_('Add'), ZBX_MASSUPDATE_ACTION_ADD)
			->addValue(_('Replace'), ZBX_MASSUPDATE_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_MASSUPDATE_ACTION_REMOVE)
			->setModern(true)
			->addStyle('margin-bottom: 5px;'),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'hostGroup',
			'add_new' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			'data' => $groups_to_update,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $view->getName(),
					'dstfld1' => 'groups_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->setId('groups-div')
);

// update tags
$template_form_list->addRow(
	(new CVisibilityBox('visible[tags]', 'tags-div', _('Original')))
		->setLabel(_('Tags'))
		->setChecked(array_key_exists('tags', $data['visible'])),
	(new CDiv([
		(new CRadioButtonList('mass_update_tags', ZBX_MASSUPDATE_ACTION_ADD))
			->addValue(_('Add'), ZBX_MASSUPDATE_ACTION_ADD)
			->addValue(_('Replace'), ZBX_MASSUPDATE_ACTION_REPLACE)
			->addValue(_('Remove'), ZBX_MASSUPDATE_ACTION_REMOVE)
			->setModern(true),
		renderTagTable($data['tags'])->setId('tbl-tags')
	]))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		->setId('tags-div')
);

// append description to form list
$template_form_list->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))
		->setLabel(_('Description'))
		->setChecked(array_key_exists('description', $data['visible'])),
	(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

$linked_templates_form_list = new CFormList('linkedTemplatesFormList');

// append templates table to form list
$new_template_table = (new CTable())
	->addRow([
		(new CMultiSelect([
			'name' => 'linked_templates[]',
			'object_name' => 'linked_templates',
			'data' => $data['linked_templates'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'templates',
					'srcfld1' => 'hostid',
					'srcfld2' => 'host',
					'dstfrm' => $view->getName(),
					'dstfld1' => 'linked_templates_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	])
	->addRow([
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('mass_replace_tpls'))
				->setLabel(_('Replace'))
				->setChecked($data['mass_replace_tpls'] == 1)
			)
			->addItem((new CCheckBox('mass_clear_tpls'))
				->setLabel(_('Clear when unlinking'))
				->setChecked($data['mass_clear_tpls'] == 1)
			)
	]);

$linked_templates_form_list->addRow(
	(new CVisibilityBox('visible[linked_templates]', 'templateDiv', _('Original')))
		->setLabel(_('Link templates'))
		->setChecked(array_key_exists('linked_templates', $data['visible'])),
	(new CDiv($new_template_table))
		->setId('templateDiv')
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append tabs to form
$tab = (new CTabView())
	->addTab('templateTab', _('Template'), $template_form_list)
	->addTab('linkedTemplatesTab', _('Linked templates'), $linked_templates_form_list);

// reset the tab when opening the form for the first time
if (!hasRequest('masssave')) {
	$tab->setSelected(0);
}

// append buttons to form
$tab->setFooter(makeFormFooter(
	new CSubmit('masssave', _('Update')),
	[new CButtonCancel(url_param('groupid'))]
));

$view->addItem($tab);

$widget->addItem($view);

return $widget;
