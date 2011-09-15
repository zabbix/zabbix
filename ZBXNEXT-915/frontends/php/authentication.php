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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/config.inc.php');

$page['title'] = _('Authentication to Zabbix');
$page['file'] = 'authentication.php';
$page['hist_arg'] = array('config');

include_once('include/page_header.php');

?>
<?php
$fields = array(
	//	VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	'config'=>					array(T_ZBX_INT, O_OPT,	NULL,	IN('0,1,2'),	NULL), // 0 - internal, 1- LDAP, 2 - HTTP
	// LDAP form
	'ldap_host'=>				array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_port'=>				array(T_ZBX_INT, O_OPT,	NULL,	BETWEEN(0,65535), 'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_base_dn'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_bind_dn'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_bind_password'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,			'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'ldap_search_attribute'=>	array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&(isset({save})||isset({test}))'),
	'user'=>					array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&(isset({test}))'),
	'user_password'=>			array(T_ZBX_STR, O_OPT,	NULL,	NOT_EMPTY,		'isset({config})&&({config}==1)&&(isset({test}))'),
	// action
	'save'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
	'test'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
);
?>
<?php
check_fields($fields);

if (!isset($_REQUEST['config'])) {
	$_REQUEST['config'] = CProfile::get('web.authentication.config', ZBX_AUTH_INTERNAL);
}

$config = select_config();
$isAuthenticationTypeChanged = ($config['authentication_type'] != $_REQUEST['config']) ? true : false;

if ($_REQUEST['config'] == ZBX_AUTH_INTERNAL) {
	if (isset($_REQUEST['save'])) {
		// remove field from others athentication types
		foreach ($config as $id => $value) {
			if (isset($_REQUEST[$id])) {
				$config[$id] = $_REQUEST[$id];
			}
			else {
				unset($config[$id]);
			}
		}
		$config['authentication_type'] = $_REQUEST['config'];

		// reset all sessions
		if ($isAuthenticationTypeChanged) {
			DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
		}

		// update config
		if (update_config($config)) {
			CProfile::update('web.authentication.config', $_REQUEST['config'], PROFILE_TYPE_INT);
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
	if (isset($_REQUEST['save'])) {
		try {
			// remove field from others athentication types
			foreach ($config as $id => $value) {
				if (isset($_REQUEST[$id])) {
					$config[$id] = $_REQUEST[$id];
					$ldap_cnf[str_replace('ldap_', '', $id)] = $_REQUEST[$id];
				}
				else {
					unset($config[$id]);
				}
			}
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
			CProfile::update('web.authentication.config', $_REQUEST['config'], PROFILE_TYPE_INT);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed to LDAP'));
			show_message(_('Authentication method changed to LDAP'));
			$isAuthenticationTypeChanged = false;
		}
		catch (Exception $e) {
			show_error_message(_('Cannot change authentication method to LDAP'));
		}
	}
	elseif (isset($_REQUEST['test'])) {
		foreach ($config as $id => $value) {
			if (isset($_REQUEST[$id])) {
				$ldap_cnf[str_replace('ldap_', '', $id)] = $_REQUEST[$id];
			}
		}

		// check login/password
		$result = API::User()->ldapLogin(array(
			'user' => get_request('user', $USER_DETAILS['alias']),
			'password' => get_request('user_password', ''),
			'cnf' => $ldap_cnf
		));

		show_messages($result, _('LDAP login successful.'), _('LDAP login was not successful.'));
	}
}
elseif ($_REQUEST['config'] == ZBX_AUTH_HTTP) {
	if (isset($_REQUEST['save'])) {
		$result = DBfetch(DBselect('SELECT COUNT(g.usrgrpid) as cnt_usrgrp FROM usrgrp g WHERE g.gui_access='.GROUP_GUI_ACCESS_INTERNAL));
		if ($result['cnt_usrgrp'] > 0) {
			info(_n('There is %1$d group with Internal GUI access.', 'There are %1$d groups with Internal GUI access.', $result['cnt_usrgrp']));
		}

		// remove field from others athentication types
		foreach ($config as $id => $value) {
			if (isset($_REQUEST[$id])) {
				$config[$id] = $_REQUEST[$id];
			}
			else{
				unset($config[$id]);
			}
		}
		$config['authentication_type'] = $_REQUEST['config'];

		// reset all sessions
		if ($isAuthenticationTypeChanged) {
			DBexecute('UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.' WHERE sessionid<>'.zbx_dbstr($USER_DETAILS['sessionid']));
		}

		// update config
		if (update_config($config)) {
			CProfile::update('web.authentication.config', $_REQUEST['config'], PROFILE_TYPE_INT);
			add_audit(AUDIT_ACTION_UPDATE,AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed to HTTP'));
			show_message(_('Authentication method changed to HTTP'));
			$isAuthenticationTypeChanged = false;
		}
		else {
			show_error_message(_('Cannot changed authentication method to HTTP'));
		}
	}
}
show_messages();

/**
 * Display
 */
$data['config'] = $_REQUEST['config'];
$data['config_data'] = $config;
$data['is_authentication_type_changed'] = $isAuthenticationTypeChanged;

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

if (get_user_auth($USER_DETAILS['userid']) == GROUP_GUI_ACCESS_INTERNAL) {
	$data['usr_test'] = new CComboBox('user', $USER_DETAILS['alias']);
	$sql = 'SELECT u.alias, u.userid '.
				' FROM users u '.
				' WHERE '.DBin_node('u.userid').
				' ORDER BY alias ASC';
	$result = DBselect($sql);
	while ($db_user = Dbfetch($result)) {
		if (check_perm2login($db_user['userid']) && check_perm2system($db_user['userid'])) {
			$data['usr_test']->addItem($db_user['alias'], $db_user['alias']);
		}
	}
}
else {
	$data['usr_test'] = new CTextBox('user', $USER_DETAILS['alias'], null, 'yes');
}

// render view
$authenticationView = new CView('configuration.authentication.edit', $data);
$authenticationView->render();
$authenticationView->show();

include_once 'include/page_footer.php';
?>
