<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/include/classes/user/CWebUser.php';
CWebUser::disableSessionCookie();
require_once dirname(__FILE__).'/include/config.inc.php';

clear_messages();
$http_user = CWebUser::getHttpRemoteUser();
$config = $http_user ? select_config() : [];

$request = getRequest('request', '');
$test_request = [];
preg_match('/^\/?(?<filename>[a-z0-9\_\.]+\.php)(\?.*)?$/i', $request, $test_request);

if (!array_key_exists('filename', $test_request) || !file_exists('./'.$test_request['filename'])
		|| $test_request['filename'] == basename(__FILE__)) {
	$request = '';
}

if ($http_user && $config['http_auth_enabled'] == ZBX_AUTH_HTTP_ENABLED && CWebUser::isLoggedIn()) {
	if (!CWebUser::isGuest() || CWebUser::authenticateHttpUser()){
		CWebUser::setSessionCookie(CWebUser::$data['sessionid']);
		$redirect = array_filter([$request, CWebUser::$data['url'], ZBX_DEFAULT_URL]);
		redirect(reset($redirect));

		exit;
	}
}

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

if ($request !== '') {
	$redirect_to->setArgument('request', $request);
}

if ($config && $config['http_auth_enabled'] == ZBX_AUTH_HTTP_DISABLED) {
	redirect($redirect_to->toString());

	exit;
}
elseif (!$http_user) {
	error(_('Login name or password is incorrect.'));
}

(new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => zbx_objectValues(clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))->onClick('document.location = '.
			CJs::encodeJson($redirect_to->getUrl()).';')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->render();
