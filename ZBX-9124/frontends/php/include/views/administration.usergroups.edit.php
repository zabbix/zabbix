<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

/*
 * User group tab
*/
$userGroupFormList = (new CFormList())
	->addRow(_('Group name'),
		(new CTextBox('gname', $data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// append groups to form list
$groupsComboBox = (new CComboBox('selusrgrp', $data['selected_usrgrp'], 'submit()'))
	->addItem(0, _('All'));
foreach ($data['usergroups'] as $group) {
	$groupsComboBox->addItem($group['usrgrpid'], $group['name']);
}

// append user tweenbox to form list
$usersTweenBox = new CTweenBox($userGroupForm, 'group_users', $data['group_users'], 10);
foreach ($data['users'] as $user) {
	$usersTweenBox->addItem($user['userid'], getUserFullname($user));
}
$userGroupFormList->addRow(_('Users'), $usersTweenBox->get(_('In group'), [_('Other groups'), SPACE, $groupsComboBox]));

// append frontend and user status to from list
$isGranted = ($data['usrgrpid'] != 0) ? granted2update_group($data['usrgrpid']) : true;
if ($isGranted) {
	$userGroupFormList->addRow(_('Frontend access'), new CComboBox('gui_access', $data['gui_access'], null, [
		GROUP_GUI_ACCESS_SYSTEM => user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM),
		GROUP_GUI_ACCESS_INTERNAL => user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL),
		GROUP_GUI_ACCESS_DISABLED => user_auth_type2str(GROUP_GUI_ACCESS_DISABLED)
	]));
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
		$permissions_table->addRow(['*', permissionText($group_rights['permission'])]);
		$userGroupForm->addVar('groups_rights['.$groupid.'][grouped]', $group_rights['grouped']);
		$userGroupForm->addVar('groups_rights['.$groupid.'][permission]', $group_rights['permission']);
	}
	else {
		$group_name = $group_rights['name'];
		if (array_key_exists('grouped', $group_rights) && $group_rights['grouped']) {
			$userGroupForm->addVar('groups_rights['.$groupid.'][grouped]', $group_rights['grouped']);

			$group_name .= '/*';
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
			'nested' => true,
			'styles' => ['margin-top' => '-.3em'],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$userGroupForm->getName().
					'&dstfld1=groupids_&srcfld1=groupid&multiselect=1'
			]
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
		(new CCol(
			(new CRadioButtonList('new_permission', PERM_NONE))
				->addValue(_('Read-write'), PERM_READ_WRITE)
				->addValue(_('Read'), PERM_READ)
				->addValue(_('Deny'), PERM_DENY)
				->addValue(_('None'), PERM_NONE)
				->setModern(true)
		))->setAttribute('style', 'vertical-align: top')
	])
	->addRow([(new CSubmit('add_permission', _('Add')))->addClass(ZBX_STYLE_BTN_LINK)]);

$permissionsFormList->addRow(null,
	(new CDiv($new_permissions_table))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
);

// append form lists to tab
$userGroupTab = (new CTabView())
	->addTab('userGroupTab', _('User group'), $userGroupFormList)
	->addTab('permissionsTab', _('Permissions'), $permissionsFormList);
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
