<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


$usersWidget = new CWidget();

// append page header to widget
$createForm = new CForm('get');
$createForm->cleanItems();
$configurationComboBox = new CComboBox('config', 'users.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
$configurationComboBox->addItem('usergrps.php', _('User groups'));
$configurationComboBox->addItem('users.php', _('Users'));
$createForm->addItem(array($configurationComboBox, new CSubmit('form', _('Create user'))));
$usersWidget->addPageHeader(_('CONFIGURATION OF USERS'), $createForm);

// append form header to widget
$userGroupListForm = new CForm('get');
$userGroupComboBox = new CComboBox('filter_usrgrpid', $_REQUEST['filter_usrgrpid'], 'submit()');
$userGroupComboBox->addItem(0, _('All'));

foreach ($this->data['userGroups'] as $userGroup) {
	$userGroupComboBox->addItem($userGroup['usrgrpid'], $userGroup['name']);
}
$userGroupListForm->addItem(array(_('User group').SPACE, $userGroupComboBox));

$usersWidget->addHeader(_('Users'), $userGroupListForm);
$usersWidget->addHeaderRowNumber();

// create form
$usersForm = new CForm();
$usersForm->setName('userForm');

// create users table
$usersTable = new CTableInfo(_('No users found.'));
$usersTable->setHeader(array(
	new CCheckBox('all_users', null, "checkAll('".$usersForm->getName()."', 'all_users', 'group_userid');"),
	$this->data['displayNodes'] ? _('Node') : null,
	make_sorting_header(_('Alias'), 'alias'),
	make_sorting_header(_('Name'), 'name'),
	make_sorting_header(_('Surname'), 'surname'),
	make_sorting_header(_('User type'), 'type'),
	_('Groups'),
	_('Is online?'),
	_('Login'),
	_('Frontend access'),
	_('Debug mode'),
	_('Status')
));

foreach ($this->data['users'] as $user) {
	$userId = $user['userid'];
	$session = $this->data['usersSessions'][$userId];

	// online time
	if ($session['lastaccess']) {
		$onlineTime = ($user['autologout'] == 0 || ZBX_USER_ONLINE_TIME < $user['autologout']) ? ZBX_USER_ONLINE_TIME : $user['autologout'];

		$online = (($session['lastaccess'] + $onlineTime) >= time())
			? new CCol(_('Yes').' ('.date('r', $session['lastaccess']).')', 'enabled')
			: new CCol(_('No').' ('.date('r', $session['lastaccess']).')', 'disabled');
	}
	else {
		$online = new CCol(_('No'), 'disabled');
	}

	// blocked
	$blocked = ($user['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS)
		? new CLink(_('Blocked'), 'users.php?go=unblock&group_userid'.SQUAREBRACKETS.'='.$userId, 'on')
		: new CSpan(_('Ok'), 'green');

	// user groups
	order_result($user['usrgrps'], 'name');

	$usersGroups = array();
	$i = 0;

	foreach ($user['usrgrps'] as $userGroup) {
		$i++;

		if ($i > $this->data['config']['max_in_table']) {
			$usersGroups[] = ' &hellip;';

			break;
		}

		if ($usersGroups) {
			$usersGroups[] = ', ';
		}

		$usersGroups[] = new CLink(
			$userGroup['name'],
			'usergrps.php?form=update&usrgrpid='.$userGroup['usrgrpid'],
			($userGroup['gui_access'] == GROUP_GUI_ACCESS_DISABLED || $userGroup['users_status'] == GROUP_STATUS_DISABLED)
				? 'disabled' : 'enabled'
		);
	}

	// user type style
	$userTypeStyle = 'enabled';
	if ($user['type'] == USER_TYPE_ZABBIX_ADMIN) {
		$userTypeStyle = 'orange';
	}
	if ($user['type'] == USER_TYPE_SUPER_ADMIN) {
		$userTypeStyle = 'disabled';
	}

	// gui access style
	$guiAccessStyle = 'green';
	if ($user['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
		$guiAccessStyle = 'orange';
	}
	if ($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
		$guiAccessStyle = 'disabled';
	}

	// append user to table
	$usersTable->addRow(array(
		new CCheckBox('group_userid['.$userId.']', null, null, $userId),
		$this->data['displayNodes'] ? $user['nodename'] : null,
		new CLink($user['alias'], 'users.php?form=update&userid='.$userId),
		$user['name'],
		$user['surname'],
		user_type2str($user['type']),
		$usersGroups,
		$online,
		$blocked,
		new CSpan(user_auth_type2str($user['gui_access']), $guiAccessStyle),
		($user['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) ? new CSpan(_('Enabled'), 'orange') : new CSpan(_('Disabled'), 'green'),
		($user['users_status'] == 1) ? new CSpan(_('Disabled'), 'red') : new CSpan(_('Enabled'), 'green')
	));
}

// append Go buttons
$goComboBox = new CComboBox('go');
$goOption = new CComboItem('unblock', _('Unblock selected'));
$goOption->setAttribute('confirm', _('Unblock selected users?'));
$goComboBox->addItem($goOption);
$goOption = new CComboItem('delete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected users?'));
$goComboBox->addItem($goOption);
$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
zbx_add_post_js('chkbxRange.pageGoName = "group_userid";');

// append table to form
$usersForm->addItem(array($this->data['paging'], $usersTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$usersWidget->addItem($usersForm);

return $usersWidget;
