<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
define('ZBX_PAGE_NO_AUTHORIZATION', 1);
define('ZBX_NOT_ALLOW_ALL_NODES', 1);
define('ZBX_HIDE_NODE_SELECTION', 1);

require_once('include/config.inc.php');
require_once('include/forms.inc.php');

$page['title']	= 'S_ZABBIX_BIG';
$page['file']	= 'index.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'name'=>			array(T_ZBX_STR, O_NO,	NULL,	NOT_EMPTY,	'isset({enter})', S_LOGIN_NAME),
		'password'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		'isset({enter})'),
		'sessionid'=>		array(T_ZBX_STR, O_OPT,	NULL,	NULL,		NULL),
//		'message'=>			array(T_ZBX_STR, O_OPT,	NULL,	NULL,		NULL),
		'reconnect'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535),NULL),
		'enter'=>			array(T_ZBX_STR, O_OPT, P_SYS,	NULL,		NULL),
		'autologin'=>		array(T_ZBX_INT, O_OPT, NULL,   NULL,   	NULL),
		'request'=>			array(T_ZBX_STR, O_OPT, NULL, 	NULL,   	NULL),
	);
	check_fields($fields);
?>
<?php
	$sessionid = get_cookie('zbx_sessionid');

	if(isset($_REQUEST['reconnect']) && isset($sessionid)){
		add_audit(AUDIT_ACTION_LOGOUT,AUDIT_RESOURCE_USER,'Manual Logout');

		CUser::logout($sessionid);

		require('login.php');
		exit();
	}

	$config = select_config();

	$authentication_type = $config['authentication_type'];

	if($authentication_type == ZBX_AUTH_HTTP){
		if(isset($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_USER'])){
			$_REQUEST['enter'] = _('Sign in');
			$_REQUEST['name'] = $_SERVER['PHP_AUTH_USER'];
			$_REQUEST['password'] = 'zabbix';//$_SERVER['PHP_AUTH_PW'];
		}
		else{
			access_deny();
		}
	}

	$request = get_request('request');

	if(isset($_REQUEST['enter']) && ($_REQUEST['enter'] == _('Sign in'))){
		$name = get_request('name','');
		$passwd = get_request('password','');

		$login = CUser::authenticate(array('user'=>$name, 'password'=>$passwd, 'auth_type'=>$authentication_type));

		if($login){
// save remember login preferance
			$user = array('autologin' => get_request('autologin', 0));
			if($USER_DETAILS['autologin'] != $user['autologin'])
				$result = CUser::updateProfile($user);
// --

			$url = is_null($request)?$USER_DETAILS['url']:$request;

			add_audit_ext(AUDIT_ACTION_LOGIN, AUDIT_RESOURCE_USER, $USER_DETAILS['userid'], '', null,null,null);
			if(zbx_empty($url) || ($url == $page['file'])){
				$url = 'dashboard.php';
			}

			redirect($url);
			exit();
		}
	}

	if($sessionid)
		CUser::checkAuthentication(array('sessionid'=>$sessionid));

	if($USER_DETAILS['alias'] == ZBX_GUEST_USER){
		switch($authentication_type){
			case ZBX_AUTH_HTTP:
				break;
			case ZBX_AUTH_LDAP:
			case ZBX_AUTH_INTERNAL:
			default:
				if(isset($_REQUEST['enter'])) $_REQUEST['autologin'] = get_request('autologin', 0);
				require('login.php');
		}
	}
	else{
		redirect('dashboard.php');
	}
?>