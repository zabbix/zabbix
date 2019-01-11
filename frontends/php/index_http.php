<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

$config = select_config();
$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

$request = getRequest('request', '');
$test_request = [];
preg_match('/^\/?(?<filename>[a-z0-9\_\.]+\.php)(\?.*)?$/i', $request, $test_request);

if (!array_key_exists('filename', $test_request) || !file_exists('./'.$test_request['filename'])
		|| $test_request['filename'] == basename(__FILE__)) {
	$request = '';
}

if ($request !== '') {
	$redirect_to->setArgument('request', $request);
}

if ($config['http_auth_enabled'] != ZBX_AUTH_HTTP_ENABLED) {
	redirect($redirect_to->toString());

	exit;
}

$http_user = '';
foreach (['PHP_AUTH_USER', 'REMOTE_USER', 'AUTH_USER'] as $key) {
	if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
		$http_user = $_SERVER[$key];
		break;
	}
}

if ($http_user) {
	$parser = new CADNameAttributeParser(['strict' => true]);

	if ($parser->parse($http_user) === CParser::PARSE_SUCCESS) {
		$strip_domain = explode(',', $config['http_strip_domains']);
		$strip_domain = array_map('trim', $strip_domain);

		if ($strip_domain && in_array($parser->getDomainName(), $strip_domain)) {
			$http_user = $parser->getUserName();
		}
	}

	try {
		$user = API::getApiService('user')->loginHttp($http_user, false);

		if ($user) {
			CWebUser::setSessionCookie($user['sessionid']);
			$redirect = array_filter([$request, $user['url'], ZBX_DEFAULT_URL]);
			redirect(reset($redirect));

			exit;
		}
	}
	catch (APIException $e) {
		error($e->getMessage());
	}
}
else {
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
