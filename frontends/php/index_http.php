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
$config = select_config();

if ($config['http_auth_enabled'] != ZBX_AUTH_HTTP_ENABLED || CWebUser::isLoggedIn()) {
	redirect((new CUrl('index.php'))->getUrl());

	exit;
}

$http_user = '';

foreach (['PHP_AUTH_USER', 'REMOTE_USER', 'AUTH_USER'] as $key) {
	if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
		$http_user = $_SERVER[$key];
		break;
	}
}

if ($http_user !== '') {
	$parser = new CADNameAttributeParser(['strict' => true]);

	if ($parser->parse($http_user) === CParser::PARSE_SUCCESS) {
		$strip_domain = explode(',', $config['http_strip_domains']);
		$strip_domain = array_map('trim', $strip_domain);

		if ($strip_domain && in_array($parser->getDomainName(), $strip_domain)) {
			$http_user = $parser->getUserName();
		}
	}

	CWebUser::login($http_user, '');

	$request = getRequest('request', '');
	$test_request = [];
	preg_match('/^\/?(?<filename>[a-z0-9\_\.]+\.php)(\?.*)?$/i', $request, $test_request);

	if (!array_key_exists('filename', $test_request) || !file_exists('./'.$test_request['filename'])
			|| $test_request['filename'] == basename(__FILE__)) {
		$request = '';
	}

	if (CWebUser::isGuest()) {
		// Access denied page with custom "Login" URL.
		$url = new CUrl('index.php');

		if ($request !== '') {
			$url->setArgument('request', $request);
		}

		$data = [
			'header' => _('You are not logged in'),
			'messages' => [
				_('You must login to view this page.'),
				_('If you think this message is wrong, please consult your administrators about getting the necessary permissions.')
			],
			'buttons' => [
				(new CButton('login', _('Login')))->onClick('document.location = '.CJs::encodeJson($url->getUrl()).';')
			],
			'theme' => getUserTheme(CWebUser::$data)
		];

		$errors = clear_messages();
		if ($errors) {
			$data['messages'] = array_merge(zbx_objectValues($errors, 'message'), $data['messages']);
		}

		(new CView('general.warning', $data))->render();
	}
	else {
		$url = ($request === '') ? CWebUser::$data['url'] : $request;
		redirect($url === '' ? ZBX_DEFAULT_URL : $url);
	}

	exit;
}

redirect((new CUrl('index.php'))->getUrl());
