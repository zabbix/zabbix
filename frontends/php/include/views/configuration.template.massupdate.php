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

// replace host groups
$hostgroups_to_replace = hasRequest('groups')
	? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => $data['groups'],
		'editable' => true
	]), ['groupid' => 'id'])
	: [];

$replace_groups = (new CDiv(
	(new CMultiSelect([
		'name' => 'groups[]',
		'object_name' => 'hostGroup',
		'data' => $hostgroups_to_replace,
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
))->setId('replaceGroups');

$template_form_list->addRow(
	(new CVisibilityBox('visible[groups]', 'replaceGroups', _('Original')))
		->setLabel(_('Replace host groups'))
		->setChecked(array_key_exists('groups', $data['visible']))
		->setAttribute('autofocus', 'autofocus'),
	$replace_groups
);

// add new or existing host groups
$hostgroups_to_add = [];
if (hasRequest('new_groups')) {
	$groupids = [];

	foreach (getRequest('new_groups') as $new_host_group) {
		if (is_array($new_host_group) && array_key_exists('new', $new_host_group)) {
			$hostgroups_to_add[] = [
				'id' => $new_host_group['new'],
				'name' => $new_host_group['new'].' ('._x('new', 'new element in multiselect').')',
				'isNew' => true
			];
		}
		else {
			$groupids[] = $new_host_group;
		}
	}

	$hostgroups_to_add = array_merge($hostgroups_to_add, $groupids
		? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids
		]), ['groupid' => 'id'])
		: []
	);
}

$template_form_list->addRow(
	(new CVisibilityBox('visible[new_groups]', 'newGroups', _('Original')))
		->setLabel((CWebUser::getType() == USER_TYPE_SUPER_ADMIN)
			? _('Add new or existing host groups')
			: _('New host group')
		)
		->setChecked(array_key_exists('new_groups', $data['visible'])),
	(new CDiv(
		(new CMultiSelect([
			'name' => 'new_groups[]',
			'object_name' => 'hostGroup',
			'add_new' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			'data' => $hostgroups_to_add,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $view->getName(),
					'dstfld1' => 'new_groups_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->setId('newGroups')
);

// Get list of host groups to remove if unsuccessful submit.
$host_groups_to_remove = getRequest('remove_groups')
	? CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
		'output' => ['groupid', 'name'],
		'groupids' => getRequest('remove_groups'),
		'editable' => true
	]), ['groupid' => 'id'])
	: [];

// Remove host groups control.
$template_form_list->addRow(
	(new CVisibilityBox('visible[remove_groups]', 'remove_groups', _('Original')))
		->setLabel(_('Remove host groups'))
		->setChecked(array_key_exists('remove_groups', $data['visible'])),
	(new CDiv(
		(new CMultiSelect([
			'name' => 'remove_groups[]',
			'object_name' => 'hostGroup',
			'data' => $host_groups_to_remove,
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $view->getName(),
					'dstfld1' => 'remove_groups_',
					'editable' => true
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->setId('remove_groups')
);

// Replace tags.
$template_form_list->addRow(
	(new CVisibilityBox('visible[tags]', 'tags', _('Original')))
		->setLabel(_('Replace tags'))
		->setChecked(array_key_exists('tags', $data['visible'])),
	(new CDiv(renderTagTable($data['tags'], 'tags')->setId('tbl-tags')))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('tags')
);

// Add tags.
$template_form_list->addRow(
	(new CVisibilityBox('visible[new_tags]', 'new_tags', _('Original')))
		->setLabel(_('Add tags'))
		->setChecked(array_key_exists('new_tags', $data['visible'])),
	(new CDiv(renderTagTable($data['new_tags'], 'new_tags')->setId('tbl-new-tags')))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('new_tags')
);

// Remove tags.
$template_form_list->addRow(
	(new CVisibilityBox('visible[remove_tags]', 'remove_tags', _('Original')))
		->setLabel(_('Remove tags'))
		->setChecked(array_key_exists('remove_tags', $data['visible'])),
	(new CDiv(renderTagTable($data['remove_tags'], 'remove_tags')->setId('tbl-remove-tags')))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
		->setId('remove_tags')
);

// Append description to form list.
$template_form_list->addRow(
	(new CVisibilityBox('visible[description]', 'description', _('Original')))
		->setLabel(_('Description'))
		->setChecked(array_key_exists('description', $data['visible'])),
	(new CTextArea('description', $data['description']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

$linked_templates_form_list = new CFormList('linkedTemplatesFormList');

// Append templates table to form list.
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
