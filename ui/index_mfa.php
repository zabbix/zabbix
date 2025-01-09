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


require_once __DIR__.'/include/classes/user/CWebUser.php';
require_once __DIR__.'/include/config.inc.php';

// Clear 'Session terminated, re-login, please' message.
CMessageHelper::clear();

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'enter' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'request' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'totp_secret' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'hash_function' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'verification_code' =>	[T_ZBX_INT, O_OPT, null,	null,	null],
	'qr_code_url' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'duo_code' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'state' =>				[T_ZBX_STR, O_OPT, null,	null,	null]
];
check_fields($fields);

$page['scripts'] = ['qrcode.js'];

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');
$request = getRequest('request', '');

if ($request != '' && !CHtmlUrlValidator::validateSameSite($request)) {
	$request = '';
}

if ($request != '') {
	$redirect_to->setArgument('request', $request);
}

try {
	$session_data = json_decode(base64_decode(CCookieHelper::get(ZBX_SESSION_NAME)), true);

	// If no session data or MFA is not required - redirect to the main login page.
	if (!$session_data || !array_key_exists('confirmid', $session_data)) {
		redirect($redirect_to->toString());
	}

	$session_data_sign = CSessionHelper::get('sign');
	$session_data_sign_check = CEncryptHelper::sign(json_encode(array_diff_key($session_data, array_flip(['sign']))));

	if (!$session_data_sign || !CEncryptHelper::checkSign($session_data_sign, $session_data_sign_check)) {
		throw new Exception(_('Session initialization error.'));
	}

	if ($request != '') {
		CSessionHelper::set('request', $request);
	}

	$duo_redirect_uri = ((new CUrl($_SERVER['REQUEST_URI']))
		->removeArgument('state')
		->removeArgument('duo_code'))
		->setArgument('request', $request)
		->toString();

	$full_duo_redirect_url = implode('', [HTTPS ? 'https://' : 'http://', $_SERVER['HTTP_HOST'], $duo_redirect_uri]);

	$confirm_data = [
		'sessionid' => CSessionHelper::get('confirmid'),
		'redirect_uri' => implode('', [HTTPS ? 'https://' : 'http://', $_SERVER['HTTP_HOST'], $duo_redirect_uri])
	];

	$error = null;

	if (!CSessionHelper::has('state') && !hasRequest('enter')) {
		$data = CUser::getConfirmData($confirm_data);

		if ($data['mfa']['type'] == MFA_TYPE_TOTP) {
			session_write_close();
			echo (new CView('mfa.login', $data))->getOutput();
			exit;
		}

		if ($data['mfa']['type'] == MFA_TYPE_DUO) {
			CSessionHelper::set('state', $data['state']);
			CSessionHelper::set('username', $data['username']);
			CSessionHelper::set('sessionid', $data['sessionid']);

			redirect($data['prompt_uri']);
		}
	}
	else {
		$data['mfa_response_data'] = [
			'verification_code' => getRequest('verification_code', ''),
			'totp_secret' => getRequest('totp_secret'),
			'duo_code' => getRequest('duo_code'),
			'duo_state' => getRequest('state'),
			'state' => CSessionHelper::get('state'),
			'username' => CSessionHelper::get('username')
		];

		$confirm = CUser::confirm($confirm_data + $data);

		if ($confirm) {
			CWebUser::checkAuthentication($confirm['sessionid']);
			CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
			CSessionHelper::unset(['state', 'username', 'confirmid']);

			API::getWrapper()->auth = [
				'type' => CJsonRpc::AUTH_TYPE_COOKIE,
				'auth' => CWebUser::$data['sessionid']
			];

			$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
			redirect(reset($redirect));
		}
	}
}
catch (Exception $e) {
	$error['error']['message'] = $e->getMessage();

	CMessageHelper::clear();

	if ($e->getCode() == ZBX_API_ERROR_PARAMETERS) {
		$data['qr_code_url'] = getRequest('qr_code_url');
		$data['totp_secret'] = getRequest('totp_secret');
		$data['mfa']['hash_function'] = getRequest('hash_function');

		session_write_close();
		echo (new CView('mfa.login', $data + $error))->getOutput();
		exit;
	}
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => $error,
	'buttons' => [
		(new CButton('login', _('Login')))
			->setAttribute('data-url', $redirect_to->getUrl())
			->onClick('document.location = this.dataset.url;')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();

session_write_close();
