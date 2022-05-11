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

$this->includeJsFile('administration.user.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('user');
}

$widget = (new CWidget())
	->setTitle(_('Users'))
	->setControls((new CList([
		(new CForm('get'))
			->cleanItems()
			->setName('main_filter')
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CVar('action', 'user.list'))->removeId())
			->addItem((new CList())
				->addItem([
					new CLabel(_('User group'), 'label-filter-usrgrpid'),
					(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
					(new CSelect('filter_usrgrpid'))
						->setId('filter-usrgrpid')
						->setValue($data['filter_usrgrpid'])
						->setFocusableElementId('label-filter-usrgrpid')
						->addOptions(CSelect::createOptionsFromArray($data['user_groups']))
				])
			),
			(new CTag('nav', true,
				(new CList())
					->addItem(new CRedirectButton(_('Create user'), 'zabbix.php?action=user.edit'))
				))->setAttribute('aria-label', _('Content controls'))
		]))
	)
	->addItem((new CFilter())
		->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'user.list'))
		->setProfile($data['profileIdx'])
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())->addRow(_('Username'),
				(new CTextBox('filter_username', $data['filter']['username']))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					->setAttribute('autofocus', 'autofocus')
			),
			(new CFormList())->addRow(_('Name'),
				(new CTextBox('filter_name', $data['filter']['name']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			),
			(new CFormList())->addRow(_('Last name'),
				(new CTextBox('filter_surname', $data['filter']['surname']))->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
			),
			(new CFormList())->addRow((new CLabel(_('User roles'), 'filter_roles')),
				(new CMultiSelect([
					'name' => 'filter_roles[]',
					'object_name' => 'roles',
					'data' => $data['filter']['roles'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'roles',
							'srcfld1' => 'roleid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_roles_'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
			)
		])
		->addVar('action', 'user.list')
	);

$form = (new CForm())
	->setName('user_form')
	->setId('users');

// create users table
$url = (new CUrl('zabbix.php'))
	->setArgument('action', 'user.list')
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_users'))->onClick("checkAll('".$form->getName()."', 'all_users', 'userids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Username'), 'username', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_x('Name', 'user first name'), 'name', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Last name'), 'surname', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('User role'), 'role_name', $data['sort'], $data['sortorder'], $url),
		_('Groups'),
		_('Is online?'),
		_('Login'),
		_('Frontend access'),
		_('API access'),
		_('Debug mode'),
		_('Status')
	]);

foreach ($data['users'] as $user) {
	$userid = $user['userid'];
	$session = $data['sessions'][$userid];

	// Online time.
	if ($session['lastaccess']) {
		$autologout = timeUnitToSeconds($user['autologout']);

		$online_time = ($autologout == 0 || ZBX_USER_ONLINE_TIME < $autologout)
			? ZBX_USER_ONLINE_TIME
			: $autologout;

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

	$blocked = ($user['attempt_failed'] >= $data['config']['login_attempts'])
		? (new CLink(_('Blocked'), 'zabbix.php?action=user.unblock&userids[]='.$userid))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass(ZBX_STYLE_RED)
			->addSID()
		: (new CSpan(_('Ok')))->addClass(ZBX_STYLE_GREEN);

	order_result($user['usrgrps'], 'name');

	$users_groups = [];
	$i = 0;

	foreach ($user['usrgrps'] as $user_group) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$users_groups[] = ' &hellip;';

			break;
		}

		if ($users_groups) {
			$users_groups[] = ', ';
		}

		$group = $data['allowed_ui_user_groups']
			? (new CLink($user_group['name'], (new CUrl('zabbix.php'))
				->setArgument('action', 'usergroup.edit')
				->setArgument('usrgrpid', $user_group['usrgrpid'])
				->getUrl()
			))->addClass(ZBX_STYLE_LINK_ALT)
			: new CSpan($user_group['name']);

		$style = ($user_group['gui_access'] == GROUP_GUI_ACCESS_DISABLED
					|| $user_group['users_status'] == GROUP_STATUS_DISABLED)
				? ZBX_STYLE_RED
				: ZBX_STYLE_GREEN;

		$users_groups[] = $group->addClass($style);
	}

	// GUI Access style.
	switch ($user['gui_access']) {
		case GROUP_GUI_ACCESS_INTERNAL:
			$gui_access_style = ZBX_STYLE_ORANGE;
			break;

		case GROUP_GUI_ACCESS_DISABLED:
			$gui_access_style = ZBX_STYLE_GREY;
			break;

		default:
			$gui_access_style = ZBX_STYLE_GREEN;
	}

	$username = new CLink($user['username'], (new CUrl('zabbix.php'))
		->setArgument('action', 'user.edit')
		->setArgument('userid', $userid)
	);

	if (!CRoleHelper::checkAccess('api.access', $user['roleid'])) {
		$api_access = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}
	else {
		$api_access = (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN);
		$api_methods = CRoleHelper::getRoleApiMethods($user['roleid']);

		if ($api_methods) {
			$hint_api_methods = [];
			$status_class = CRoleHelper::checkAccess('api.mode', $user['roleid'])
				? ZBX_STYLE_STATUS_GREEN
				: ZBX_STYLE_STATUS_GREY;

			foreach ($api_methods as $api_method) {
				$hint_api_methods[] = (new CSpan($api_method))->addClass($status_class);
			}

			$api_access->setHint((new CDiv($hint_api_methods))->addClass('rules-status-container'));
		}
	}

	// Append user to table.
	$table->addRow([
		new CCheckBox('userids['.$userid.']', $userid),
		(new CCol($username))->addClass(ZBX_STYLE_NOWRAP),
		$user['name'],
		$user['surname'],
		$user['role']['name'],
		$users_groups,
		$online,
		$blocked,
		(new CSpan(user_auth_type2str($user['gui_access'])))->addClass($gui_access_style),
		$api_access,
		($user['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_ORANGE)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_GREEN),
		($user['users_status'] == GROUP_STATUS_DISABLED)
			? (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED)
			: (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN)
	]);
}

// Append table to form.
$form->addItem([
	$table,
	$data['paging'],
	new CActionButtonList('action', 'userids', [
		'user.unblock' => ['name' => _('Unblock'), 'confirm' => _('Unblock selected users?')],
		'user.delete' => ['name' => _('Delete'), 'confirm' => _('Delete selected users?')]
	], 'user')
]);

// Append form to widget.
$widget
	->addItem($form)
	->show();
