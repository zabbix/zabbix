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
require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of authentication');
$page['file'] = 'authentication.php';
$page['hist_arg'] = array('config');

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE	OPTIONAL FLAGS	VALIDATION						EXCEPTION
$fields = array(
	'config' =>			array(T_ZBX_INT, O_OPT, null, IN(ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP.','.ZBX_AUTH_HTTP), null),
	// LDAP
	'ldap_host' =>			array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))',	_('LDAP host')),
	'ldap_port' =>			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 65535),
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))',	_('Port')),
	'ldap_base_dn' =>		array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))',	_('Base DN')),
	'ldap_bind_dn' =>		array(T_ZBX_STR, O_OPT, null, null,
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_bind_password' =>		array(T_ZBX_STR, O_OPT, null, null,
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_search_attribute' =>	array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))',	_('Search attribute')),
	'user' =>			array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({config})&&({config}==1)&&(isset({test}))'),
	'user_password' =>		array(T_ZBX_STR, O_OPT, null, NOT_EMPTY,
		'isset({config})&&({config}==1)&&(isset({test}))',			_('Bind password')),
	// actions
	'save' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'test' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null)
);
check_fields($fields);

if (!isset($_REQUEST['config'])) {
	$_REQUEST['config'] = $config['authentication_type'];
}

$config = select_config();
$isAuthenticationTypeChanged = ($config['authentication_type'] != $_REQUEST['config']) ? true : false;

foreach ($config as $id => $value) {
	if (isset($_REQUEST[$id])) {
		$config[$id] = $_REQUEST[$id];
	}
}

/*
 * Actions
 */
if ($_REQUEST['config'] == ZBX_AUTH_INTERNAL) {
	if (isset($_REQUEST['save'])) {
		$config['authentication_type'] = $_REQUEST['config'];

		// reset all sessions
		if ($isAuthenticationTypeChanged) {
			DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
		}

		// update config
		if (update_config($config)) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed to Zabbix internal'));
			show_message(_('Authentication method changed to Zabbix internal'));
			$isAuthenticationTypeChanged = false;
		}
		else {
			show_error_message(_('Cannot change authentication method to Zabbix internal'));
		}
	}
}
elseif ($_REQUEST['config'] == ZBX_AUTH_LDAP) {
	foreach ($config as $id => $value) {
		if (isset($_REQUEST[$id])) {
			$ldap_cnf[str_replace('ldap_', '', $id)] = $_REQUEST[$id];
		}
	}

	if (isset($_REQUEST['save'])) {
		try {
			$config['authentication_type'] = $_REQUEST['config'];

			// check login/password
			$login = API::User()->ldapLogin(array(
				'user' => get_request('user', $USER_DETAILS['alias']),
				'password' => get_request('user_password', ''),
				'cnf' => $ldap_cnf
			));
			if (!$login) {
				throw new Exception();
			}

			// reset all sessions
			if ($isAuthenticationTypeChanged) {
				DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
			}

			// update config
			if (!update_config($config)) {
				throw new Exception();
			}
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed to LDAP'));
			show_message(_('Authentication method changed to LDAP'));
			$isAuthenticationTypeChanged = false;
		}
		catch (Exception $e) {
			show_error_message(_('Cannot change authentication method to LDAP'));
		}
	}
	elseif (isset($_REQUEST['test'])) {
		// check login/password
		$result = API::User()->ldapLogin(array(
			'user' => get_request('user', $USER_DETAILS['alias']),
			'password' => get_request('user_password', ''),
			'cnf' => $ldap_cnf
		));

		show_messages($result, _('LDAP login successful'), _('LDAP login was not successful'));
	}
}
elseif ($_REQUEST['config'] == ZBX_AUTH_HTTP) {
	if (isset($_REQUEST['save'])) {
		$config['authentication_type'] = $_REQUEST['config'];

		// get groups wich use this authentication method
		$result = DBfetch(DBselect('SELECT COUNT(g.usrgrpid) AS cnt_usrgrp FROM usrgrp g WHERE g.gui_access='.GROUP_GUI_ACCESS_INTERNAL));
		if ($result['cnt_usrgrp'] > 0) {
			info(_n('There is "%1$d" group with Internal GUI access.', 'There are "%1$d" groups with Internal GUI access.', $result['cnt_usrgrp']));
		}

		// reset all sessions
		if ($isAuthenticationTypeChanged) {
			DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
		}

		// update config
		if (update_config($config)) {
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed to HTTP'));
			show_message(_('Authentication method changed to HTTP'));
			$isAuthenticationTypeChanged = false;
		}
		else {
			show_error_message(_('Cannot change authentication method to HTTP'));
		}
	}
}
show_messages();

/*
 * Display
 */
$data['config'] = $_REQUEST['config'];
$data['config_data'] = $config;
$data['is_authentication_type_changed'] = $isAuthenticationTypeChanged;
$data['user'] = get_request('user', $USER_DETAILS['alias']);
$data['user_password'] = get_request('user_password', '');
$data['user_list'] = null;

// get tab title
switch ($data['config']) {
	case ZBX_AUTH_INTERNAL:
		$data['tab_title'] = _('Zabbix internal authentication');
		break;
	case ZBX_AUTH_LDAP:
		$data['tab_title'] = _('LDAP authentication');
		break;
	case ZBX_AUTH_HTTP:
		$data['tab_title'] = _('HTTP authentication');
		break;
	default:
		$data['tab_title'] = '';
}

// get user list
if (get_user_auth($USER_DETAILS['userid']) == GROUP_GUI_ACCESS_INTERNAL) {
	$data['user_list'] = DBfetchArray(DBselect(
		'SELECT u.alias,u.userid'.
		' FROM users u'.
		' WHERE '.DBin_node('u.userid').
		' ORDER BY alias'
	));
}

// render view
$authenticationView = new CView('administration.authentication.edit', $data);
$authenticationView->render();
$authenticationView->show();

require_once 'include/page_footer.php';
?>
