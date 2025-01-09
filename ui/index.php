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


require_once dirname(__FILE__).'/include/classes/user/CWebUser.php';

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['file'] = 'index.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'name' =>		[T_ZBX_STR, O_NO,	null,	null,	'isset({enter}) && {enter} != "'.ZBX_GUEST_USER.'"', _('Username')],
	'password' =>	[T_ZBX_STR, O_OPT, P_NO_TRIM,	null,	'isset({enter}) && {enter} != "'.ZBX_GUEST_USER.'"'],
	'sessionid' =>	[T_ZBX_STR, O_OPT, null,	null,	null],
	'reconnect' =>	[T_ZBX_INT, O_OPT, P_SYS|P_ACT,	null,	null],
	'enter' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'autologin' =>	[T_ZBX_INT, O_OPT, null,	null,	null],
	'request' =>	[T_ZBX_STR, O_OPT, null,	null,	null],
	'form' =>		[T_ZBX_STR, O_OPT, null,	null,	null]
];
check_fields($fields);

if (hasRequest('reconnect') && CWebUser::isLoggedIn()) {
	if (CAuthenticationHelper::get(CAuthenticationHelper::SAML_AUTH_ENABLED) == ZBX_AUTH_SAML_ENABLED) {
		$provisioning = CProvisioning::forUserDirectoryId(CAuthenticationHelper::getSamlUserdirectoryid());
		$saml_config = $provisioning->getIdpConfig();

		if ($saml_config['slo_url'] !== '' && CSessionHelper::has('saml_data')) {
			redirect('index_sso.php?slo');
		}
	}

	CWebUser::logout();
	redirect('index.php');
}

$autologin = hasRequest('enter') ? getRequest('autologin', 0) : getRequest('autologin', 1);
$request = getRequest('request', '');

if ($request !== '' && !CHtmlUrlValidator::validateSameSite($request)) {
	$request = '';
}

if (!hasRequest('form')
		&& CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_AUTH_ENABLED) == ZBX_AUTH_HTTP_ENABLED
		&& CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_LOGIN_FORM) == ZBX_AUTH_FORM_HTTP
		&& !hasRequest('enter')) {
	redirect('index_http.php');
}

// login via form
if (hasRequest('enter') && CWebUser::login(getRequest('name', ZBX_GUEST_USER), getRequest('password', ''))) {
	CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);

	if (CWebUser::$data['autologin'] != $autologin) {
		API::User()->update([
			'userid' => CWebUser::$data['userid'],
			'autologin' => $autologin
		]);
	}

	if (CWebUser::$data['mfaid']) {
		CSessionHelper::set('confirmid', CWebUser::$data['sessionid']);

		// In case user has not finished their Duo authentication, need to unset data saved into session by Duo.
		if (CSessionHelper::has('state')) {
			CSessionHelper::unset(['state', 'username']);
		}

		$mfa_url = (new CUrl('index_mfa.php'));

		if ($request !== '') {
			$mfa_url->setArgument('request', $request);
		}

		redirect($mfa_url->toString());
	}

	$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
	redirect(reset($redirect));
}

if (CWebUser::isLoggedIn() && !CWebUser::isGuest()) {
	redirect(CWebUser::$data['url'] ? : CMenuHelper::getFirstUrl());
}

$messages = get_and_clear_messages();

echo (new CView('general.login', [
	'http_login_url' => (CAuthenticationHelper::getPublic(CAuthenticationHelper::HTTP_AUTH_ENABLED) == ZBX_AUTH_HTTP_ENABLED)
		? (new CUrl('index_http.php'))->setArgument('request', getRequest('request'))
		: '',
	'saml_login_url' => (CAuthenticationHelper::getPublic(CAuthenticationHelper::SAML_AUTH_ENABLED) == ZBX_AUTH_SAML_ENABLED)
		? (new CUrl('index_sso.php'))->setArgument('request', getRequest('request'))
		: '',
	'guest_login_url' => CWebUser::isGuestAllowed() ? (new CUrl())->setArgument('enter', ZBX_GUEST_USER) : '',
	'autologin' => $autologin == 1,
	'error' => (hasRequest('enter') && $messages) ? array_pop($messages) : null
]))->getOutput();

session_write_close();
