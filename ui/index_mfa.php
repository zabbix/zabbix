<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

CMessageHelper::clear();

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'enter' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'request' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'totp_secret' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'verification_code' =>	[T_ZBX_INT, O_OPT, null,	null,	null],
	'qr_code_url' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'duo_code' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'state' =>				[T_ZBX_STR, O_OPT, null,	null,	null]
];
check_fields($fields);

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');
$request = getRequest('request', '');

if ($request != '' && !CHtmlUrlValidator::validateSameSite($request)) {
	$request = '';
}

if ($request != '') {
	$redirect_to->setArgument('request', $request);
}

$session_data = json_decode(base64_decode(CCookieHelper::get(ZBX_SESSION_NAME)), true);

// If MFA is not required - redirect to the main login page.
if ($session_data['mfaid'] == '') {
	redirect($redirect_to->toString());
}

if ($request != '') {
	CSessionHelper::set('request', $request);
}

$duo_redirect_uri = ((new CUrl($_SERVER['REQUEST_URI']))
	->removeArgument('state')
	->removeArgument('duo_code'))
	->setArgument('request', $request)
	->toString();

$session_data['redirect_uri'] = implode('', [HTTPS ? 'https://' : 'http://', $_SERVER['HTTP_HOST'], $duo_redirect_uri]);
$session_data_required = array_intersect_key($session_data, array_flip(['sessionid', 'mfaid', 'redirect_uri']));
$confirm_data = API::User()->getConfirmData($session_data_required);

$error = array_column(get_and_clear_messages(), 'message');

if ($error) {
	redirectToGeneralWarningPage($error, $redirect_to);
	exit;
}

if ($confirm_data['mfa']['type'] == MFA_TYPE_TOTP) {
	// Check of submitted verification code.
	if (hasRequest('enter')) {
		$data_to_check = array_merge($session_data_required, $confirm_data);
		$data_to_check['verification_code'] = getRequest('verification_code', '');
		unset($data_to_check['mfaid'], $data_to_check['qr_code_url']);

		if (hasRequest('totp_secret')) {
			$data_to_check['totp_secret'] = getRequest('totp_secret');
		}

		$confirm = API::User()->confirm($data_to_check);

		if ($confirm) {
			CWebUser::checkAuthentication($session_data['sessionid']);
			CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
			CSessionHelper::unset(['mfaid']);

			API::getWrapper()->auth = [
				'type' => CJsonRpc::AUTH_TYPE_FRONTEND,
				'auth' => CWebUser::$data['sessionid']
			];

			$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
			redirect(reset($redirect));
		}
		elseif(hasRequest('qr_code_url')) {
			// Show QR code and TOTP secret again, in initial setup verification code was incorrect.
			$confirm_data['qr_code_url'] = getRequest('qr_code_url');
			$confirm_data['totp_secret'] = getRequest('totp_secret');
		}
	}

	$messages = get_and_clear_messages();
	$confirm_data['error'] = $messages ? array_pop($messages) : null;

	echo (new CView('mfa.login', $confirm_data))->getOutput();
	exit;
}


if ($confirm_data['mfa']['type'] == MFA_TYPE_DUO) {
	if (!CSessionHelper::has('state')) {
		CSessionHelper::set('state', $confirm_data['state']);
		CSessionHelper::set('username', $confirm_data['username']);
		CSessionHelper::set('sessionid', $session_data['sessionid']);

		header('Location: '.$confirm_data['prompt_uri']);
	}
	else {
		if (hasRequest('error')) {
			$error_msg = getRequest('error') . ":" . getRequest('error_description');
			CMessageHelper::addError(_($error_msg));
		}
		else {
			$input_data = [
				'duo_code' => getRequest('duo_code'),
				'duo_state' => getRequest('state')
			];
			$session_data_required['state'] = $session_data['state'];
			$session_data_required['username'] = $session_data['username'];

			$data_to_check = array_merge($input_data, $confirm_data, $session_data_required);
			unset($data_to_check['mfaid'], $data_to_check['prompt_uri']);

			$confirm = API::User()->confirm($data_to_check);

			if ($confirm) {
				CWebUser::checkAuthentication($session_data['sessionid']);
				CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
				CSessionHelper::unset(['mfaid', 'state', 'username']);

				API::getWrapper()->auth = [
					'type' => CJsonRpc::AUTH_TYPE_FRONTEND,
					'auth' => CWebUser::$data['sessionid']
				];

				$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
				redirect(reset($redirect));
			}
		}

		CSessionHelper::unset(['state', 'username']);

		$error = array_column(get_and_clear_messages(), 'message');

		redirectToGeneralWarningPage($error, $redirect_to);
	}
}

function redirectToGeneralWarningPage(array $error, CUrl $redirect_to): void {
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
}
