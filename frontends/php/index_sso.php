<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


require_once __DIR__.'/include/config.inc.php';

$config = select_config();
$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

if ($config['saml_auth_enabled'] == ZBX_AUTH_SAML_DISABLED || get_cookie(ZBX_SESSION_NAME) !== null) {
	redirect($redirect_to->toString());
}

require_once __DIR__.'/vendor/php-saml/_toolkit_loader.php';
require_once __DIR__.'/vendor/xmlseclibs/xmlseclibs.php';

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;

$baseurl = Utils::getSelfURLNoQuery();

$sp_key = '';
$sp_cert = '';
$idp_cert = '';

if (isset($SSO)) {
	if (array_key_exists('SP_KEY', $SSO) && file_exists($SSO['SP_KEY'])) {
		$sp_key = file_get_contents($SSO['SP_KEY']);
	}

	if (array_key_exists('SP_CERT', $SSO) && file_exists($SSO['SP_CERT'])) {
		$sp_cert = file_get_contents($SSO['SP_CERT']);
	}

	if (array_key_exists('IDP_CERT', $SSO) && file_exists($SSO['IDP_CERT'])) {
		$idp_cert = file_get_contents($SSO['IDP_CERT']);
	}
}

if (!$sp_key && file_exists('conf/certs/sp.key')) {
	$sp_key = file_get_contents('conf/certs/sp.key');
}

if (!$sp_cert && file_exists('conf/certs/sp.crt')) {
	$sp_cert = file_get_contents('conf/certs/sp.crt');
}

if (!$idp_cert && file_exists('conf/certs/idp.crt')) {
	$idp_cert = file_get_contents('conf/certs/idp.crt');
}

$settings = [
	'sp' => [
		'entityId' => $baseurl.'?metadata',
		'assertionConsumerService' => [
			'url' => $baseurl.'?acs'
		],
		'NameIDFormat' => $config['saml_nameid_format'],
		'x509cert' => $sp_cert,
		'privateKey' => $sp_key
	],
	'idp' => [
		'entityId' => $config['saml_idp_entityid'],
		'singleSignOnService' => [
			'url' => $config['saml_sso_url'],
		],
		'singleLogoutService' => [
			'url' => $config['saml_slo_url'],
		],
		'x509cert' => $idp_cert
	],
	'security' => [
		'nameIdEncrypted' => (bool) $config['saml_encrypt_nameid'],
		'authnRequestsSigned' => (bool) $config['saml_sign_authn_requests'],
		'logoutRequestSigned' => (bool) $config['saml_sign_logout_requests'],
		'logoutResponseSigned' => (bool) $config['saml_sign_logout_responses'],
		'signMetadata' => (bool) $config['saml_sign_metadata']
			? [
				'x509cert' => $sp_cert,
				'privateKey' => $sp_key
			]
			: false,
		'wantMessagesSigned' => (bool) $config['saml_sign_messages'],
		'wantAssertionsEncrypted' => (bool) $config['saml_encrypt_assertions'],
		'wantAssertionsSigned' => (bool) $config['saml_sign_assertions'],
		'wantNameIdEncrypted' => (bool) $config['saml_encrypt_nameid'],
	]
];

try {
	$auth = new Auth($settings);

	if (hasRequest('acs') && !CSession::keyExists('saml_username_attribute')) {
		$auth->processResponse();

		if (!$auth->isAuthenticated()) {
			throw new Exception($auth->getLastErrorReason());
		}

		$user_attributes = $auth->getAttributes();

		if (!array_key_exists($config['saml_username_attribute'], $user_attributes)) {
			throw new Exception(
				_s('The parameter "%1$s" is missing from the user attributes.', $config['saml_username_attribute'])
			);
		}

		CSession::setValue('saml_username_attribute', reset($user_attributes[$config['saml_username_attribute']]));

		if (hasRequest('RelayState') && Utils::getSelfURL() != getRequest('RelayState')) {
			$auth->redirectTo(getRequest('RelayState'));
		}
	}

	if (CSession::keyExists('saml_username_attribute')) {
		$user = API::getApiService('user')->loginSso(CSession::getValue('saml_username_attribute'), false);

		if ($user) {
			CSession::unsetValue(['saml_username_attribute']);

			if ($user['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
				throw new Exception(_('GUI access disabled.'));
			}

			CWebUser::setSessionCookie($user['sessionid']);

			$redirect = array_filter([$request, $user['url'], ZBX_DEFAULT_URL]);
			redirect(reset($redirect));
		}
	}
	else {
		$auth->login();
	}
}
catch (Exception $e) {
	error($e->getMessage());
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => array_column(clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))->onClick('document.location = '.json_encode($redirect_to->getUrl()).';')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();
