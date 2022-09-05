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

$this->includeJsFile('administration.usergroup.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('User groups'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::ADMINISTRATION_USERGROUP_EDIT));

$form = (new CForm())
	->setId('user-group-form')
	->setName('user_group_form')
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

if ($data['usrgrpid'] != 0) {
	$form->addVar('usrgrpid', $data['usrgrpid']);
}

$form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Group name'), 'name'))->setAsteriskMark(),
		(new CTextBox('name', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('usrgrp', 'name'))
	)
	->addRow(
		new CLabel(_('Users'), 'userids__ms'),
		(new CMultiSelect([
			'name' => 'userids[]',
			'object_name' => 'users',
			'data' => $data['users_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'users',
					'srcfld1' => 'userid',
					'srcfld2' => 'fullname',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'userids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

if ($data['can_update_group']) {
	$select_gui_access = (new CSelect('gui_access'))
		->setValue($data['gui_access'])
		->setFocusableElementId('gui-access')
		->addOptions(CSelect::createOptionsFromArray([
			GROUP_GUI_ACCESS_SYSTEM => user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM),
			GROUP_GUI_ACCESS_INTERNAL => user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL),
			GROUP_GUI_ACCESS_LDAP => user_auth_type2str(GROUP_GUI_ACCESS_LDAP),
			GROUP_GUI_ACCESS_DISABLED => user_auth_type2str(GROUP_GUI_ACCESS_DISABLED)
		]));

	$userdirectory = (new CSelect('userdirectoryid'))
		->setValue($data['userdirectoryid'])
		->setFocusableElementId('userdirectoryid')
		->addOption((new CSelectOption(0, _('Default')))->addClass(ZBX_STYLE_DEFAULT_OPTION))
		->addOptions(CSelect::createOptionsFromArray($data['userdirectories']))
		->setAdaptiveWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	$form_list
		->addRow((new CLabel(_('Frontend access'), $select_gui_access->getFocusableElementId())), $select_gui_access)
		->addRow((new CLabel(_('LDAP Server'), $userdirectory->getFocusableElementId())), $userdirectory)
		->addRow(_('Enabled'), (new CCheckBox('users_status', GROUP_STATUS_ENABLED))
			->setUncheckedValue(GROUP_STATUS_DISABLED)
			->setChecked($data['users_status'] == GROUP_STATUS_ENABLED)
		);
}
else {
	$form_list
		->addRow(_('Frontend access'),
			(new CSpan(user_auth_type2str($data['gui_access'])))
				->addClass('text-field')
				->addClass('green')
		)
		->addRow(_('Enabled'),
			(new CSpan(_('Enabled')))
				->addClass('text-field')
				->addClass('green')
		);
}

$form_list->addRow(_('Debug mode'),
	(new CCheckBox('debug_mode', GROUP_DEBUG_MODE_ENABLED))
		->setUncheckedValue(GROUP_DEBUG_MODE_DISABLED)
		->setChecked($data['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
);

$template_permissions_form_grid = (new CFormGrid())->addItem([
	new CLabel(_('Permissions')),
	new CFormField((new CDiv(new CPartial('administration.usergroup.templategrouprights.html', [
		'templategroup_rights' => $data['templategroup_rights']
	])))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'))
]);

$new_templategroup_right_table = (new CTable())
	->setId('new-templategroup-right-table')
	->addRow([
		(new CMultiSelect([
			'name' => 'new_templategroup_right[groupids][]',
			'object_name' => 'templateGroup',
			'data' => array_intersect_key($data['template_groups_ms'],
				array_flip($data['new_templategroup_right']['groupids'])
			),
			'popup' => [
				'parameters' => [
					'srctbl' => 'template_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'new_templategroup_right_groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('new_templategroup_right[permission]',
				(int) $data['new_templategroup_right']['permission']
			))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top')
	])
	->addRow((new CCheckBox('new_templategroup_right[include_subgroups]'))
		->setChecked((bool) $data['new_templategroup_right']['include_subgroups'])
		->setLabel(_('Include subgroups'))
	)
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: usergroups.submitNewTemplateGroupRight("usergroup.templategroupright.add");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$template_permissions_form_grid
	->addItem([
		new CFormField((new CDiv($new_templategroup_right_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'))
	]);

$host_permissions_form_grid = (new CFormGrid())->addItem([
	new CLabel(_('Permissions')),
	new CFormField((new CDiv(new CPartial('administration.usergroup.grouprights.html', [
		'group_rights' => $data['group_rights']
	])))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'))
]);

$new_group_right_table = (new CTable())
	->setId('new-group-right-table')
	->addRow([
		(new CMultiSelect([
			'name' => 'new_group_right[groupids][]',
			'object_name' => 'hostGroup',
			'data' => array_intersect_key($data['host_groups_ms'], array_flip($data['new_group_right']['groupids'])),
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'new_group_right_groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('new_group_right[permission]', (int) $data['new_group_right']['permission']))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top')
	])
	->addRow((new CCheckBox('new_group_right[include_subgroups]'))
		->setChecked((bool) $data['new_group_right']['include_subgroups'])
		->setLabel(_('Include subgroups'))
	)
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: usergroups.submitNewGroupRight("usergroup.groupright.add");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$host_permissions_form_grid
	->addItem([
		new CFormField((new CDiv($new_group_right_table))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'))
	]);

$tag_filter_form_list = new CFormList('tagFilterFormList');

$tag_filter_form_list->addRow(_('Permissions'),
	(new CDiv(new CPartial('administration.usergroup.tagfilters.html', ['tag_filters' => $data['tag_filters']])))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$new_tag_filter_table = (new CTable())
	->setId('new-tag-filter-table')
	->addRow([
		(new CMultiSelect([
			'name' => 'new_tag_filter[groupids][]',
			'object_name' => 'hostGroup',
			'data' => array_intersect_key($data['host_groups_ms'], array_flip($data['new_tag_filter']['groupids'])),
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $form->getName(),
					'dstfld1' => 'new_tag_filter_groupids_'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CTextBox('new_tag_filter[tag]', $data['new_tag_filter']['tag']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('tag'))
		))->addStyle('vertical-align: top;'),
		(new CCol(
			(new CTextBox('new_tag_filter[value]', $data['new_tag_filter']['value']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('value'))
		))->addStyle('vertical-align: top;')
	])
	->addRow((new CCheckBox('new_tag_filter[include_subgroups]'))
		->setChecked((bool) $data['new_tag_filter']['include_subgroups'])
		->setLabel(_('Include subgroups'))
	)
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: usergroups.submitNewTagFilter("usergroup.tagfilter.add");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$tag_filter_form_list->addRow(null,
	(new CDiv($new_tag_filter_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$tabs = (new CTabView())
	->addTab('user_group_tab', _('User group'), $form_list)
	->addTab('template_permissions_tab', _('Template permissions'), $template_permissions_form_grid, TAB_INDICATOR_TEMPLATE_PERMISSIONS)
	->addTab('permissions_tab', _('Host permissions'), $host_permissions_form_grid, TAB_INDICATOR_HOST_PERMISSIONS)
	->addTab('tag_filter_tab', _('Problem tag filter'), $tag_filter_form_list, TAB_INDICATOR_TAG_FILTER);
if (!$data['form_refresh']) {
	$tabs->setSelected(0);
}

$cancel_button = (new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))
	->setArgument('action', 'usergroup.list')
	->setArgument('page', CPagerHelper::loadPage('usergroup.list', null))
))->setId('cancel');

if ($data['usrgrpid'] != 0) {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Update'), 'action', 'usergroup.update'))->setId('update'),
		[
			(new CRedirectButton(_('Delete'),
				(new CUrl('zabbix.php'))->setArgument('action', 'usergroup.delete')
					->setArgument('usrgrpids', [$data['usrgrpid']])->setArgumentSID(),
				_('Delete selected group?')
			))->setId('delete'),
			$cancel_button
		]
	));
}
else {
	$tabs->setFooter(makeFormFooter(
		(new CSubmitButton(_('Add'), 'action', 'usergroup.create'))->setId('add'),
		[
			$cancel_button
		]
	));
}

// append tab to form
$form->addItem($tabs);
$widget->addItem($form);
$widget->show();
