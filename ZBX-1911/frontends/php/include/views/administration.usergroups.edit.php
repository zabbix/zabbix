<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
$userGroupWidget = new CWidget();
$userGroupWidget->addPageHeader(_('CONFIGURATION OF USER GROUP'));

// create form
$userGroupForm = new CForm();
$userGroupForm->setName('userGroupsForm');
$userGroupForm->addVar('form', $this->data['form']);
$userGroupForm->addVar('form_refresh', $this->data['form_refresh'] + 1);
$userGroupForm->addVar('group_rights', $this->data['group_rights']);
if (isset($_REQUEST['usrgrpid'])) {
	$userGroupForm->addVar('usrgrpid', $this->data['usrgrpid']);
}

/*
 * User group tab
*/
$userGroupFormList = new CFormList('userGroupFormList');
$nameTextBox = new CTextBox('gname', $this->data['name'], ZBX_TEXTBOX_STANDARD_SIZE);
$nameTextBox->attr('autofocus', 'autofocus');
$userGroupFormList->addRow(_('Group name'), $nameTextBox);

// append groups to form list
$groupsComboBox = new CComboBox('selusrgrp', $this->data['selected_usrgrp'], 'submit()');
$groupsComboBox->addItem(0, _('All'));
foreach ($this->data['usergroups'] as $group) {
	$groupsComboBox->addItem($group['usrgrpid'], $group['name']);
}

// append user tweenbox to form list
$usersTweenBox = new CTweenBox($userGroupForm, 'group_users', $this->data['group_users'], 10);
foreach ($this->data['users'] as $user) {
	$usersTweenBox->addItem($user['userid'], $user['alias']);
}
$userGroupFormList->addRow(_('Users'), $usersTweenBox->get(_('In group'), array(_('Other groups'), SPACE, $groupsComboBox)));

// append frontend and user status to from list
$isGranted = isset($_REQUEST['usrgrpid']) ? granted2update_group($_REQUEST['usrgrpid']) : true;
if ($isGranted) {
	$frontendComboBox = new CComboBox('gui_access', $this->data['gui_access']);
	$frontendComboBox->addItem(GROUP_GUI_ACCESS_SYSTEM, user_auth_type2str(GROUP_GUI_ACCESS_SYSTEM));
	$frontendComboBox->addItem(GROUP_GUI_ACCESS_INTERNAL, user_auth_type2str(GROUP_GUI_ACCESS_INTERNAL));
	$frontendComboBox->addItem(GROUP_GUI_ACCESS_DISABLED, user_auth_type2str(GROUP_GUI_ACCESS_DISABLED));
	$userGroupFormList->addRow(_('Frontend access'), $frontendComboBox);
	$userGroupFormList->addRow(_('Enabled'), new CCheckBox('users_status', $this->data['users_status'] ? (!isset($_REQUEST['usrgrpid']) ? 1 : 0) : 1, null, 1)); // invert user status 0 - enable, 1 - disable
}
else {
	$userGroupForm->addVar('gui_access', $this->data['gui_access']);
	$userGroupForm->addVar('users_status', GROUP_STATUS_ENABLED);
	$userGroupFormList->addRow(_('Frontend access'), new CSpan(user_auth_type2str($this->data['gui_access']), 'green'));
	$userGroupFormList->addRow(_('Enabled'), new CSpan(_('Enabled'), 'green'));
}
$userGroupFormList->addRow(_('Debug mode'), new CCheckBox('debug_mode', $this->data['debug_mode'], null, 1));

/*
 * Permissions tab
 */
$permissionsFormList = new CFormList('permissionsFormList');

// append permissions table to form list
$permissionsTable = new CTable(_('No permissions defined.'), 'right_table');
$permissionsTable->setHeader(array(_('Read-write'), _('Read only'), _('Deny')), 'header');

$lstWrite = new CListBox('right_to_del[read_write][]', null, 20);
$lstRead = new CListBox('right_to_del[read_only][]', null, 20);
$lstDeny = new CListBox('right_to_del[deny][]', null, 20);

foreach ($this->data['group_rights'] as $id => $rights) {
	if ($rights['permission'] == PERM_DENY) {
		$lstDeny->addItem($id, $rights['name']);
	}
	elseif ($rights['permission'] == PERM_READ_ONLY) {
		$lstRead->addItem($id, $rights['name']);
	}
	elseif ($rights['permission'] == PERM_READ_WRITE) {
		$lstWrite->addItem($id, $rights['name']);
	}
}

$permissionsTable->addRow(array(new CCol($lstWrite, 'read_write'), new CCol($lstRead, 'read_only'), new CCol($lstDeny, 'deny')));
$permissionsTable->addRow(array(
	array(
		new CButton('add_read_write', _('Add'), "return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().'&permission='.PERM_READ_WRITE."', 450, 450);", 'formlist'),
		new CSubmit('del_read_write', _('Delete selected'), null, 'formlist')
	),
	array(
		new CButton('add_read_only', _('Add'), "return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().'&permission='.PERM_READ_ONLY."', 450, 450);", 'formlist'),
		new CSubmit('del_read_only', _('Delete selected'), null, 'formlist')
	),
	array(
		new CButton('add_deny', _('Add'), "return PopUp('popup_right.php?dstfrm=".$userGroupForm->getName().'&permission='.PERM_DENY."', 450, 450);", 'formlist'),
		new CSubmit('del_deny', _('Delete selected'), null, 'formlist')
	)
));
$permissionsFormList->addRow(_('Composing permissions'), $permissionsTable);
$permissionsFormList->addRow(_('Calculated permissions'), '');
$permissionsFormList = getPermissionsFormList($this->data['group_rights'], null, $permissionsFormList);

// append form lists to tab
$userGroupTab = new CTabView(array('remember' => 1));
$userGroupTab->addTab('userGroupTab', _('User group'), $userGroupFormList);
if (!$this->data['form_refresh']) {
	$userGroupTab->setSelected(0);
}
$userGroupTab->addTab('permissionsTab', _('Permissions'), $permissionsFormList);

// append tab to form
$userGroupForm->addItem($userGroupTab);

// append buttons to form
if (empty($this->data['usrgrpid'])) {
	$userGroupForm->addItem(makeFormFooter(array(new CSubmit('save', _('Save'))), array(new CButtonCancel(url_param('config')))));
}
else {
	$userGroupForm->addItem(makeFormFooter(
		array(new CSubmit('save', _('Save'))),
		array(
			new CButtonDelete(_('Delete selected group?'), url_param('form').url_param('usrgrpid').url_param('config')),
			new CButtonCancel(url_param('config'))
		)
	));
}

// append form to widget
$userGroupWidget->addItem($userGroupForm);
return $userGroupWidget;
?>
