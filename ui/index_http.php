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


require_once dirname(__FILE__).'/include/classes/user/CWebUser.php';
require_once dirname(__FILE__).'/include/config.inc.php';

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');
$request = getRequest('request', '');

if ($request !== '' && !CHtmlUrlValidator::validateSameSite($request)) {
	$request = '';
}

if ($request !== '') {
	$redirect_to->setArgument('request', $request);
}

if (CAuthenticationHelper::get(CAuthenticationHelper::HTTP_AUTH_ENABLED) != ZBX_AUTH_HTTP_ENABLED) {
	redirect($redirect_to->toString());
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
		$strip_domain = explode(',', CAuthenticationHelper::get(CAuthenticationHelper::HTTP_STRIP_DOMAINS));
		$strip_domain = array_map('trim', $strip_domain);

		if ($strip_domain && in_array($parser->getDomainName(), $strip_domain)) {
			$http_user = $parser->getUserName();
		}
	}

	try {
		CWebUser::$data = API::getApiService('user')->loginByUsername($http_user,
			(CAuthenticationHelper::get(CAuthenticationHelper::HTTP_CASE_SENSITIVE) == ZBX_AUTH_CASE_SENSITIVE),
			CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE)
		);

		if (!empty(CWebUser::$data)) {
			CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
			API::getWrapper()->auth = CWebUser::$data['sessionid'];

			$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
			redirect(reset($redirect));
		}
	}
	catch (APIException $e) {
		error($e->getMessage());
	}
}
else {
	error(_('Incorrect user name or password or account is temporarily blocked.'));
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => array_column(get_and_clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))->onClick('document.location = '.
			json_encode($redirect_to->getUrl()).';')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();

session_write_close();
