<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$usersWidget = (new CWidget())->setTitle(_('Users'));

// append page header to widget
$createForm = new CForm('get');
$createForm->cleanItems();
$controls = new CList();
$userGroupComboBox = new CComboBox('filter_usrgrpid', $_REQUEST['filter_usrgrpid'], 'submit()');
$userGroupComboBox->addItem(0, _('All'));

foreach ($this->data['userGroups'] as $userGroup) {
	$userGroupComboBox->addItem($userGroup['usrgrpid'], $userGroup['name']);
}
$controls->addItem([_('User group').SPACE, $userGroupComboBox]);
$controls->addItem(new CSubmit('form', _('Create user')));

$createForm->addItem($controls);
$usersWidget->setControls($createForm);

// create form
$usersForm = new CForm();
$usersForm->setName('userForm');

// create users table
$usersTable = new CTableInfo();
$usersTable->setHeader([
	(new CColHeader(
		new CCheckBox('all_users', null, "checkAll('".$usersForm->getName()."', 'all_users', 'group_userid');")))->
			addClass('cell-width'),
	make_sorting_header(_('Alias'), 'alias', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_x('Name', 'user first name'), 'name', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Surname'), 'surname', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('User type'), 'type', $this->data['sort'], $this->data['sortorder']),
	_('Groups'),
	_('Is online?'),
	_('Login'),
	_('Frontend access'),
	_('Debug mode'),
	_('Status')
]);

foreach ($this->data['users'] as $user) {
	$userId = $user['userid'];
	$session = $this->data['usersSessions'][$userId];

	// online time
	if ($session['lastaccess']) {
		$onlineTime = ($user['autologout'] == 0 || ZBX_USER_ONLINE_TIME < $user['autologout']) ? ZBX_USER_ONLINE_TIME : $user['autologout'];

		$online = (($session['lastaccess'] + $onlineTime) >= time())
			? (new CCol(_('Yes').' ('.zbx_date2str(DATE_TIME_FORMAT_SECONDS, $session['lastaccess']).')'))->addClass(ZBX_STYLE_GREEN)
			: (new CCol(_('No').' ('.zbx_date2str(DATE_TIME_FORMAT_SECONDS, $session['lastaccess']).')'))->addClass(ZBX_STYLE_RED);
	}
	else {
		$online = (new CCol(_('No')))->addClass(ZBX_STYLE_RED);
	}

	// blocked
	$blocked = ($user['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS)
		? new CLink(_('Blocked'), 'users.php?action=user.massunblock&group_userid[]='.$userId, ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_RED)
		: new CSpan(_('Ok'), ZBX_STYLE_GREEN);

	// user groups
	order_result($user['usrgrps'], 'name');

	$usersGroups = [];
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
				? ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_RED : ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_GREEN
		);
	}

	// user type style
	$userTypeStyle = ZBX_STYLE_GREEN;
	if ($user['type'] == USER_TYPE_ZABBIX_ADMIN) {
		$userTypeStyle = ZBX_STYLE_ORANGE;
	}
	if ($user['type'] == USER_TYPE_SUPER_ADMIN) {
		$userTypeStyle = ZBX_STYLE_RED;
	}

	// gui access style
	$guiAccessStyle = ZBX_STYLE_GREEN;
	if ($user['gui_access'] == GROUP_GUI_ACCESS_INTERNAL) {
		$guiAccessStyle = ZBX_STYLE_ORANGE;
	}
	if ($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
		$guiAccessStyle = ZBX_STYLE_GREY;
	}

	$alias = new CLink($user['alias'], 'users.php?form=update&userid='.$userId);

	// append user to table
	$usersTable->addRow([
		new CCheckBox('group_userid['.$userId.']', null, null, $userId),
		(new CCol($alias))->addClass(ZBX_STYLE_NOWRAP),
		$user['name'],
		$user['surname'],
		user_type2str($user['type']),
		$usersGroups,
		$online,
		$blocked,
		new CSpan(user_auth_type2str($user['gui_access']), $guiAccessStyle),
		($user['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) ? new CSpan(_('Enabled'), ZBX_STYLE_ORANGE) : new CSpan(_('Disabled'), ZBX_STYLE_GREEN),
		($user['users_status'] == 1) ? new CSpan(_('Disabled'), ZBX_STYLE_RED) : new CSpan(_('Enabled'), ZBX_STYLE_GREEN)
	]);
}

// append table to form
$usersForm->addItem([
	$usersTable,
	$this->data['paging'],
	new CActionButtonList('action', 'group_userid', [
		'user.massunblock' => ['name' => _('Unblock'), 'confirm' => _('Unblock selected users?')],
		'user.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected users?')]
	])
]);

// append form to widget
$usersWidget->addItem($usersForm);

return $usersWidget;
