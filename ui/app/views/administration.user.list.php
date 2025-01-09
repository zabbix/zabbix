<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

$this->includeJsFile('administration.user.list.js.php');

if ($data['uncheck']) {
	uncheckTableRows('user');
}

$html_page = (new CHtmlPage())
	->setTitle(_('Users'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::USERS_USER_LIST))
	->setControls((new CList([
		(new CForm('get'))
			->setName('main_filter')
			->setAttribute('aria-label', _('Main filter'))
			->addItem((new CVar('action', 'user.list'))->removeId()),
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
			(new CFormGrid())
				->addItem([
					new CLabel(_('Username'), 'filter_username'),
					new CFormField(
						(new CTextBox('filter_username', $data['filter']['username']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
							->setAttribute('autofocus', 'autofocus')
					)
				])
				->addItem([
					new CLabel(_('Name'), 'filter_name'),
					new CFormField(
						(new CTextBox('filter_name', $data['filter']['name']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					)
				])
				->addItem([
					new CLabel(_('Last name'), 'filter_surname'),
					new CFormField(
						(new CTextBox('filter_surname', $data['filter']['surname']))
							->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
					)
				]),
			(new CFormGrid())
				->addItem([(
					new CLabel(_('User roles'), 'filter_roles__ms')),
					new CFormField(
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
				->addItem([
					new CLabel(_('User groups'), 'filter_usrgrpids__ms'),
					new CFormField(
						(new CMultiSelect([
							'name' => 'filter_usrgrpids[]',
							'object_name' => 'usersGroups',
							'data' => $data['filter']['usrgrpids'],
							'popup' => [
								'parameters' => [
									'srctbl' => 'usrgrp',
									'srcfld1' => 'usrgrpid',
									'dstfrm' => 'zbx_filter',
									'dstfld1' => 'filter_usrgrpids_'
								]
							]
						]))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
					)
				])
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
		_('Status'),
		make_sorting_header(_('Provisioned'), 'ts_provisioned', $data['sort'], $data['sortorder'], $url),
		_('Info')
	])
	->setPageNavigation($data['paging']);

$csrf_token = CCsrfTokenHelper::get('user');

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
			->addCsrfToken($csrf_token)
		: (new CSpan(_('Ok')))->addClass(ZBX_STYLE_GREEN);

	order_result($user['usrgrps'], 'name');

	$users_groups = [];
	$i = 0;

	foreach ($user['usrgrps'] as $user_group) {
		$i++;

		if ($i > $data['config']['max_in_table']) {
			$users_groups[] = [' ', HELLIP()];

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

	$provisioned = $user['userdirectoryid'] ? new CDiv(date(ZBX_DATE_TIME, $user['ts_provisioned'])) : '';
	$checkbox = new CCheckBox('userids['.$userid.']', $userid);
	$info = $users_groups ? '' : makeWarningIcon(_('User does not have user groups.'));
	$username = new CLink($user['username'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'user.edit')
			->setArgument('userid', $userid)
	);

	if ($user['userdirectoryid'] && $data['idp_names'][$user['userdirectoryid']]['idp_type'] == IDP_TYPE_LDAP) {
		$checkbox->setAttribute('data-actions', 'ldap');
	}

	if ($user['userdirectoryid']) {
		$idp = $data['idp_names'][$user['userdirectoryid']];
		$provisioned->setHint($idp['idp_type'] == IDP_TYPE_SAML ? _('SAML') : $idp['name']);
		$gui_access = new CSpan($idp['idp_type'] == IDP_TYPE_LDAP ? _('LDAP') : _('SAML'));
	}
	else {
		$gui_access = new CSpan(user_auth_type2str($user['gui_access']));
	}

	if (!$user['roleid']) {
		$info = makeErrorIcon(_('User does not have user role.'));
		$gui_access = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_GREY);
		$api_access = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED);
	}
	else {
		switch ($user['gui_access']) {
			case GROUP_GUI_ACCESS_INTERNAL:
				$gui_access->addClass(ZBX_STYLE_ORANGE);
				break;

			case GROUP_GUI_ACCESS_DISABLED:
				$gui_access->addClass(ZBX_STYLE_GREY);
				break;

			default:
				$gui_access->addClass(ZBX_STYLE_GREEN);
		}

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
	}

	// Append user to table.
	$table->addRow([
		$checkbox,
		(new CCol($username))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($user['name']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($user['surname']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($user['role_name']))->addClass(ZBX_STYLE_WORDBREAK),
		(new CCol($users_groups))->addClass(ZBX_STYLE_WORDBREAK),
		$online,
		$blocked,
		$gui_access,
		$api_access,
		($user['debug_mode'] == GROUP_DEBUG_MODE_ENABLED)
			? (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_ORANGE)
			: (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_GREEN),
		($user['users_status'] == GROUP_STATUS_DISABLED || !$user['roleid'])
			? (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_RED)
			: (new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN),
		$provisioned,
		$info
	]);
}

// Append table to form.
$form->addItem([
	$table,
	new CActionButtonList('action', 'userids', [
		'user.provision' => [
			'name' => _('Provision now'),
			'attributes' => ['data-required' => 'ldap'],
			'confirm_singular' => _('Provision selected LDAP user?'),
			'confirm_plural' => _('Provision selected LDAP users?'),
			'csrf_token' => $csrf_token
		],
		'user.reset.totp' => [
			'name' => _('Reset TOTP secret'),
			'confirm_singular' => _('Multi-factor TOTP secret will be deleted.'),
			'confirm_plural' => _('Multi-factor TOTP secrets will be deleted.'),
			'csrf_token' => $csrf_token,
			'disabled' => CAuthenticationHelper::get(CAuthenticationHelper::MFA_STATUS) == MFA_DISABLED
		],
		'user.unblock' => [
			'name' => _('Unblock'),
			'confirm_singular' => _('Unblock selected user?'),
			'confirm_plural' => _('Unblock selected users?'),
			'csrf_token' => $csrf_token
		],
		'user.delete' => [
			'name' => _('Delete'),
			'confirm_singular' => _('Delete selected user?'),
			'confirm_plural' => _('Delete selected users?'),
			'csrf_token' => $csrf_token
		]
	], 'user')
]);

// Append form to widget.
$html_page
	->addItem($form)
	->show();
