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

$userGroupComboBox = (new CComboBox('filter_usrgrpid', $_REQUEST['filter_usrgrpid'], 'submit()'))
	->addItem(0, _('All'));

foreach ($this->data['userGroups'] as $userGroup) {
	$userGroupComboBox->addItem($userGroup['usrgrpid'], $userGroup['name']);
}

$widget = (new CWidget())
	->setTitle(_('Users'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem([_('User group'), SPACE, $userGroupComboBox])
			->addItem(new CSubmit('form', _('Create user')))
		)
	);

// create form
$usersForm = (new CForm())->setName('userForm');

// create users table
$usersTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_users'))->onClick("checkAll('".$usersForm->getName()."', 'all_users', 'group_userid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
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
		$online_time = ($user['autologout'] == 0 || ZBX_USER_ONLINE_TIME < $user['autologout'])
			? ZBX_USER_ONLINE_TIME
			: $user['autologout'];

		$online = ($session['status'] == ZBX_SESSION_ACTIVE && $user['users_status'] == GROUP_STATUS_ENABLED
				&& ($session['lastaccess'] + $online_time) >= time())
			? (new CCol(_('Yes').' ('.zbx_date2str(DATE_TIME_FORMAT_SECONDS, $session['lastaccess']).')'))
				->addClass(ZBX_STYLE_GREEN)
			: (new CCol(_('No').' ('.zbx_date2str(DATE_TIME_FORMAT_SECONDS, $session['lastaccess']).')'))
				->addClass(ZBX_STYLE_RED);
	}
	else {
		$online = (new CCol(_('No')))->addClass(ZBX_STYLE_RED);
	}

	// blocked
	$blocked = ($user['attempt_failed'] >= ZBX_LOGIN_ATTEMPTS)
		? (new CLink(_('Blocked'), 'users.php?action=user.massunblock&group_userid[]='.$userId))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addSID()
		: (new CSpan(_('Ok')))->addClass(ZBX_STYLE_GREEN);

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

		$usersGroups[] = (new CLink(
			$userGroup['name'],
			'usergrps.php?form=update&usrgrpid='.$userGroup['usrgrpid']))
			->addClass($userGroup['gui_access'] == GROUP_GUI_ACCESS_DISABLED
					|| $userGroup['users_status'] == GROUP_STATUS_DISABLED
				? ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_RED
				: ZBX_STYLE_LINK_ALT . ' ' . ZBX_STYLE_GREEN);
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
		new CCheckBox('group_userid['.$userId.']', $userId),
		(new CCol($alias))->addClass(ZBX_STYLE_NOWRAP),
		$user['name'],
		$user['surname'],
		user_type2str($user['type']),
		$usersGroups,
		$online,
		$blocked,
		(new CSpan(user_auth_type2str($user['gui_access'])))->addClass($guiAccessStyle),
		($user['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_ORANGE)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_GREEN),
		($user['users_status'] == GROUP_STATUS_DISABLED)
			? (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED)
			: (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
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
$widget->addItem($usersForm);

return $widget;
