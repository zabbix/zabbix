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
	->addVar('form', $this->data['form'])
	->addVar('group_rights', $this->data['group_rights']);
if (isset($this->data['usrgrpid'])) {
	$userGroupForm->addVar('usrgrpid', $this->data['usrgrpid']);
}

/*
 * User group tab
*/
$userGroupFormList = (new CFormList())
	->addRow(_('Group name'),
		(new CTextBox('gname', $this->data['name']))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('autofocus', 'autofocus')
	);

// append groups to form list
$groupsComboBox = (new CComboBox('selusrgrp', $this->data['selected_usrgrp'], 'submit()'))
	->addItem(0, _('All'));
foreach ($this->data['usergroups'] as $group) {
	$groupsComboBox->addItem($group['usrgrpid'], $group['name']);
}

// append user tweenbox to form list
$usersTweenBox = new CTweenBox($userGroupForm, 'group_users', $this->data['group_users'], 10);
foreach ($this->data['users'] as $user) {
	$usersTweenBox->addItem($user['userid'], getUserFullname($user));
}
$userGroupFormList->addRow(_('Users'), $usersTweenBox->get(_('In group'), [_('Other groups'), SPACE, $groupsComboBox]));

// append frontend and user status to from list
$isGranted = isset($data['usrgrpid']) ? granted2update_group($data['usrgrpid']) : true;
if ($isGranted) {
	$userGroupFormList->addRow(_('Frontend access'), new CComboBox('gui_access', $this->data['gui_access'], null, [
		GROUP_GUI_ACCESS_SYSTEM => user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM),
		GROUP_GUI_ACCESS_INTERNAL => user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL),
		GROUP_GUI_ACCESS_DISABLED => user_auth_type2str(GROUP_GUI_ACCESS_DISABLED)
	]));
	$userGroupFormList->addRow(_('Enabled'),
		(new CCheckBox('users_status'))->setChecked($this->data['users_status'] ? (isset($data['usrgrpid']) ? false : true) : true)
	);
}
else {
	$userGroupForm
		->addVar('gui_access', $this->data['gui_access'])
		->addVar('users_status', GROUP_STATUS_ENABLED);
	$userGroupFormList
		->addRow(_('Frontend access'),
			(new CSpan(user_auth_type2str($this->data['gui_access'])))
				->addClass('text-field')
				->addClass('green')
		)
		->addRow(_('Enabled'),
			(new CSpan(_('Enabled')))
				->addClass('text-field')
				->addClass('green')
		);
}
$userGroupFormList->addRow(_('Debug mode'), (new CCheckBox('debug_mode'))->setChecked($this->data['debug_mode'] == 1));

/*
 * Permissions tab
 */
$permissionsFormList = new CFormList('permissionsFormList');

// append permissions table to form list
$permissionsTable = (new CTable())->setHeader([_('Read-write'), _('Read only'), _('Deny')]);

$lstWrite = new CListBox('right_to_del[read_write][]', null, 20);
$lstRead = new CListBox('right_to_del[read_only][]', null, 20);
$lstDeny = new CListBox('right_to_del[deny][]', null, 20);

foreach ($this->data['group_rights'] as $id => $rights) {
	if ($rights['permission'] == PERM_DENY) {
		$lstDeny->addItem($id, $rights['name']);
	}
	elseif ($rights['permission'] == PERM_READ) {
		$lstRead->addItem($id, $rights['name']);
	}
	elseif ($rights['permission'] == PERM_READ_WRITE) {
		$lstWrite->addItem($id, $rights['name']);
	}
}

$permissionsTable->addRow([
	(new CCol($lstWrite))->addClass('read_write'),
	(new CCol($lstRead))->addClass('read_only'),
	(new CCol($lstDeny))->addClass('deny')
]);
$permissionsTable->addRow([
	[
		(new CButton('add_read_write', _('Add')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick("return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().
				'&permission='.PERM_READ_WRITE."');"),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSubmit('del_read_write', _('Delete selected')))->addClass(ZBX_STYLE_BTN_GREY)
	],
	[
		(new CButton('add_read_only', _('Add')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick("return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().
				'&permission='.PERM_READ."');"),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSubmit('del_read_only', _('Delete selected')))->addClass(ZBX_STYLE_BTN_GREY)
	],
	[
		(new CButton('add_deny', _('Add')))
			->addClass(ZBX_STYLE_BTN_GREY)
			->onClick("return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().
				'&permission='.PERM_DENY."');"),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		(new CSubmit('del_deny', _('Delete selected')))->addClass(ZBX_STYLE_BTN_GREY)
	]
]);
$permissionsFormList
	->addRow(_('Composing permissions'), $permissionsTable)
	->addRow(_('Calculated permissions'), '');
$permissionsFormList = getPermissionsFormList($this->data['group_rights'], null, $permissionsFormList);

// append form lists to tab
$userGroupTab = (new CTabView())
	->addTab('userGroupTab', _('User group'), $userGroupFormList)
	->addTab('permissionsTab', _('Permissions'), $permissionsFormList);
if (!$this->data['form_refresh']) {
	$userGroupTab->setSelected(0);
}

// append buttons to form
if (isset($this->data['usrgrpid'])) {
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
