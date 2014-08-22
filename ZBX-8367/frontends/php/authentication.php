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


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of authentication');
$page['file'] = 'authentication.php';
$page['hist_arg'] = array('config');

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR						TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'config' =>			array(T_ZBX_INT, O_OPT, null, IN(ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP.','.ZBX_AUTH_HTTP), null),
	'form_refresh' =>	array(T_ZBX_INT, O_OPT, null,			null, null),
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null, null),
	'test' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null, null),
	// LDAP
	'ldap_host' =>		array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))',	_('LDAP host')),
	'ldap_port' =>		array(T_ZBX_INT, O_OPT, null,			BETWEEN(0, 65535),
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))',	_('Port')),
	'ldap_base_dn' =>	array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))',	_('Base DN')),
	'ldap_bind_dn' =>	array(T_ZBX_STR, O_OPT, null,			null,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))'),
	'ldap_bind_password' => array(T_ZBX_STR, O_OPT, null,		null, null,				_('Bind password')),
	'ldap_search_attribute' => array(T_ZBX_STR, O_OPT, null,	NOT_EMPTY,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))',	_('Search attribute')),
	'user' =>			array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))'),
	'user_password' =>	array(T_ZBX_STR, O_OPT, null,			NOT_EMPTY,
		'isset({config})&&{config}=='.ZBX_AUTH_LDAP.'&&(isset({save})||isset({test}))',	_('User password')),
	'change_bind_password' => array(T_ZBX_STR, O_OPT, null, null,	null)
);
check_fields($fields);

$config = select_config();

if (isset($_REQUEST['config'])) {
	$isAuthenticationTypeChanged = ($config['authentication_type'] != $_REQUEST['config']);
	$config['authentication_type'] = $_REQUEST['config'];
}
else {
	$isAuthenticationTypeChanged = false;
}

foreach ($config as $name => $value) {
	if (array_key_exists($name, $_REQUEST)) {
		$config[$name] = $_REQUEST[$name];
	}
}

/*
 * Actions
 */
if ($config['authentication_type'] == ZBX_AUTH_INTERNAL) {
	if (isset($_REQUEST['save'])) {
		$messageSuccess = _('Authentication method changed to Zabbix internal');
		$messageFailed = _('Cannot change authentication method to Zabbix internal');

		DBstart();

		$result = update_config($config);

		if ($result) {
			// reset all sessions
			if ($isAuthenticationTypeChanged) {
				$result &= DBexecute(
					'UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.
					' WHERE sessionid<>'.zbx_dbstr(CWebUser::$data['sessionid'])
				);
			}

			$isAuthenticationTypeChanged = false;

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, $messageSuccess);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
elseif ($config['authentication_type'] == ZBX_AUTH_LDAP) {
	if (isset($_REQUEST['save']) || isset($_REQUEST['test'])) {
		// check LDAP login/password
		$ldapValidator = new CLdapAuthValidator(array(
			'conf' => array(
				'host' => $config['ldap_host'],
				'port' => $config['ldap_port'],
				'base_dn' => $config['ldap_base_dn'],
				'bind_dn' => $config['ldap_bind_dn'],
				'bind_password' => $config['ldap_bind_password'],
				'search_attribute' => $config['ldap_search_attribute']
			)
		));

		$login = $ldapValidator->validate(array(
			'user' => getRequest('user', CWebUser::$data['alias']),
			'password' => getRequest('user_password', '')
		));

		if (!$login) {
			error(_('Login name or password is incorrect!'));
		}

		if (isset($_REQUEST['save'])) {
			if (!$login) {
				show_error_message(_('Cannot change authentication method to LDAP'));
			}
			else {
				$messageSuccess = $isAuthenticationTypeChanged
					? _('Authentication method changed to LDAP')
					: _('LDAP authentication changed');
				$messageFailed = $isAuthenticationTypeChanged
						? _('Cannot change authentication method to LDAP')
						: _('Cannot change authentication');

				DBstart();

				$result = update_config($config);

				if ($result) {
					unset($_REQUEST['change_bind_password']);

					// reset all sessions
					if ($isAuthenticationTypeChanged) {
						$result &= DBexecute(
							'UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.
							' WHERE sessionid<>'.zbx_dbstr(CWebUser::$data['sessionid'])
						);
					}

					$isAuthenticationTypeChanged = false;

					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, $messageSuccess);
				}

				$result = DBend($result);
				show_messages($result, $messageSuccess, $messageFailed);
			}
		}
	}
	elseif (isset($_REQUEST['test'])) {
		show_messages($login, _('LDAP login successful'), _('LDAP login was not successful'));
	}
}
elseif ($config['authentication_type'] == ZBX_AUTH_HTTP) {
	if (isset($_REQUEST['save'])) {
		// get groups that use this authentication method
		$result = DBfetch(DBselect(
			'SELECT COUNT(g.usrgrpid) AS cnt_usrgrp FROM usrgrp g WHERE g.gui_access='.GROUP_GUI_ACCESS_INTERNAL
		));

		if ($result['cnt_usrgrp'] > 0) {
			info(_n(
				'There is "%1$d" group with Internal GUI access.',
				'There are "%1$d" groups with Internal GUI access.',
				$result['cnt_usrgrp']
			));
		}

		$messageSuccess = _('Authentication method changed to HTTP');
		$messageFailed = _('Cannot change authentication method to HTTP');

		DBstart();

		$result = update_config($config);

		if ($result) {
			// reset all sessions
			if ($isAuthenticationTypeChanged) {
				$result &= DBexecute(
					'UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.
					' WHERE sessionid<>'.zbx_dbstr(CWebUser::$data['sessionid'])
				);
			}

			$isAuthenticationTypeChanged = false;

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, $messageSuccess);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
}

show_messages();

/*
 * Display
 */
$data = array(
	'form_refresh' => getRequest('form_refresh'),
	'config' => $config,
	'is_authentication_type_changed' => $isAuthenticationTypeChanged,
	'user' => getRequest('user', CWebUser::$data['alias']),
	'user_password' => getRequest('user_password', ''),
	'user_list' => null,
	'change_bind_password' => getRequest('change_bind_password')
);

// get tab title
$data['title'] = authentication2str($config['authentication_type']);

// get user list
if (getUserGuiAccess(CWebUser::$data['userid']) == GROUP_GUI_ACCESS_INTERNAL) {
	$data['user_list'] = DBfetchArray(DBselect(
		'SELECT u.alias,u.userid FROM users u ORDER BY u.alias'
	));
}

// render view
$authenticationView = new CView('administration.authentication.edit', $data);
$authenticationView->render();
$authenticationView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
