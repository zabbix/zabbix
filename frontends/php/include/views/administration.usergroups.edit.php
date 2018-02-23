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


$widget = (new CWidget())->setTitle(_('User groups'));

// create form
$userGroupForm = (new CForm())
	->setName('userGroupsForm')
	->addVar('form', $data['form']);

if ($data['usrgrpid'] != 0) {
	$userGroupForm->addVar('usrgrpid', $data['usrgrpid']);
}

$userGroupFormList = (new CFormList())
	->addRow(
		(new CLabel(_('Group name'), 'gname'))->setAsteriskMark(),
		(new CTextBox('gname', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
			->setAttribute('maxlength', DB::getFieldLength('usrgrp', 'name'))
	)
	->addRow(
		new CLabel(_('Users'), 'userids[]'),
		(new CMultiSelect([
			'name' => 'userids[]',
			'objectName' => 'users',
			'data' => $data['users_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'users',
					'dstfrm' => $userGroupForm->getName(),
					'dstfld1' => 'userids_',
					'srcfld1' => 'userid',
					'srcfld2' => 'fullname',
					'multiselect' => '1'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);

// append frontend and user status to from list
$isGranted = ($data['usrgrpid'] != 0) ? granted2update_group($data['usrgrpid']) : true;
if ($isGranted) {
	$userGroupFormList->addRow(
		(new CLabel(_('Frontend access'), 'gui_access')),
		(new CComboBox('gui_access', $data['gui_access'], null, [
			GROUP_GUI_ACCESS_SYSTEM => user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM),
			GROUP_GUI_ACCESS_INTERNAL => user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL),
			GROUP_GUI_ACCESS_DISABLED => user_auth_type2str(GROUP_GUI_ACCESS_DISABLED)
		]))
	);
	$userGroupFormList->addRow(_('Enabled'),
		(new CCheckBox('users_status'))->setChecked($data['users_status'] == GROUP_STATUS_ENABLED)
	);
}
else {
	$userGroupForm
		->addVar('gui_access', $data['gui_access'])
		->addVar('users_status', GROUP_STATUS_ENABLED);
	$userGroupFormList
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
$userGroupFormList->addRow(_('Debug mode'), (new CCheckBox('debug_mode'))->setChecked($data['debug_mode'] == 1));

/*
 * Permissions tab
 */
$permissionsFormList = new CFormList('permissionsFormList');

$permissions_table = (new CTable())
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host group'), _('Permissions')]);

foreach ($data['groups_rights'] as $groupid => $group_rights) {
	$userGroupForm->addVar('groups_rights['.$groupid.'][name]', $group_rights['name']);

	if ($groupid == 0) {
		$permissions_table->addRow([italic(_('All groups')), permissionText($group_rights['permission'])]);
		$userGroupForm->addVar('groups_rights['.$groupid.'][grouped]', $group_rights['grouped']);
		$userGroupForm->addVar('groups_rights['.$groupid.'][permission]', $group_rights['permission']);
	}
	else {
		if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
			$userGroupForm->addVar('groups_rights['.$groupid.'][grouped]', $group_rights['grouped']);
			$group_name = [$group_rights['name'], SPACE, italic('('._('including subgroups').')')];
		}
		else {
			$group_name = $group_rights['name'];
		}

		$permissions_table->addRow([$group_name,
			(new CRadioButtonList('groups_rights['.$groupid.'][permission]', (int) $group_rights['permission']))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true)
		]);
	}
}

$permissionsFormList->addRow(_('Permissions'),
	(new CDiv($permissions_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$new_permissions_table = (new CTable())
	->addRow([
		(new CMultiSelect([
			'name' => 'groupids[]',
			'objectName' => 'hostGroup',
			'data' => $data['permission_groups'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'dstfrm' => $userGroupForm->getName(),
					'dstfld1' => 'groupids_',
					'srcfld1' => 'groupid',
					'multiselect' => '1'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('new_permission', (int) $data['new_permission']))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top')
	])
	->addRow([[(new CCheckBox('subgroups'))->setChecked($data['subgroups'] == 1), _('Include subgroups')]])
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$userGroupForm->getName().'", "add_permission", "1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$permissionsFormList->addRow(null,
	(new CDiv($new_permissions_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

/*
 * Tag filter tab
 */
$tag_filter_form_list = new CFormList('tagFilterFormList');

$tag_filter_table = (new CTable())
	->setId('tag_filter_table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host group'), _('Tags'), _('Action')]);

$pre_name = '';

foreach ($data['tag_filters'] as $key => $tag_filter) {
	$action = (new CSimpleButton(_('Remove')))
		->onClick('javascript: submitFormWithParam('.
			'"'.$userGroupForm->getName().'", "remove_tag_filter['.$key.']", "1"'.
		');')
		->addClass(ZBX_STYLE_BTN_LINK);
	if ($pre_name === $tag_filter['name']) {
		$tag_filter['name'] = '';
	}
	else {
		$pre_name = $tag_filter['name'];
	}

	if ($tag_filter['tag'] !== '' && $tag_filter['value'] !== '') {
		$tag_value = $tag_filter['tag'].NAME_DELIMITER.$tag_filter['value'];
	}
	elseif ($tag_filter['tag'] !== '') {
		$tag_value = $tag_filter['tag'];
	}
	else {
		$tag_value = italic(_('All tags'));
	}

	$tag_filter_table->addRow([$tag_filter['name'], $tag_value, $action]);
	$userGroupForm->addVar('tag_filters['.$key.'][groupid]', $tag_filter['groupid']);
	$userGroupForm->addVar('tag_filters['.$key.'][tag]', $tag_filter['tag']);
	$userGroupForm->addVar('tag_filters['.$key.'][value]', $tag_filter['value']);
}

$tag_filter_form_list->addRow(_('Permissions'),
	(new CDiv($tag_filter_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

$new_tag_filter_table = (new CTable())
	->addRow([
		(new CMultiSelect([
			'name' => 'tag_filter_groupids[]',
			'objectName' => 'hostGroup',
			'data' => $data['tag_filter_groups'],
			'styles' => ['margin-top' => '-.3em'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'dstfrm' => $userGroupForm->getName(),
					'dstfld1' => 'tag_filter_groupids_',
					'srcfld1' => 'groupid',
					'multiselect' => '1'
				]
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		new CCol(
			(new CTextBox('tag', $data['tag']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('tag'))
		),
		new CCol(
			(new CTextBox('value', $data['value']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->setAttribute('placeholder', _('value'))
		)
	])
	->addRow([[
		(new CCheckBox('tag_filter_subgroups'))->setChecked($data['tag_filter_subgroups'] == 1),
		_('Include subgroups')
	]])
	->addRow([
		(new CSimpleButton(_('Add')))
			->onClick('javascript: submitFormWithParam("'.$userGroupForm->getName().'", "add_tag_filter", "1");')
			->addClass(ZBX_STYLE_BTN_LINK)
	]);

$tag_filter_form_list->addRow(null,
	(new CDiv($new_tag_filter_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append form lists to tab
$userGroupTab = (new CTabView())
	->addTab('userGroupTab', _('User group'), $userGroupFormList)
	->addTab('permissionsTab', _('Permissions'), $permissionsFormList)
	->addTab('tagFilterTab', _('Tag filter'), $tag_filter_form_list);
if (!$data['form_refresh']) {
	$userGroupTab->setSelected(0);
}

// append buttons to form
if ($data['usrgrpid'] != 0) {
	$userGroupTab->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CButtonDelete(_('Delete selected group?'), url_param('form').url_param('usrgrpid')),
			new CButtonCancel()
		]
	));
}
else {
	$userGroupTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

// append tab to form
$userGroupForm->addItem($userGroupTab);

$widget->addItem($userGroupForm);

return $widget;
