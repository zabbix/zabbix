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


require_once __DIR__.'/include/config.inc.php';

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');

$request = CSessionHelper::get('request');
CSessionHelper::unset(['request']);

if (hasRequest('request')) {
	$request = getRequest('request');
	preg_match('/^\/?(?<filename>[a-z0-9_.]+\.php)(\?.*)?$/i', $request, $test_request);

	if (!array_key_exists('filename', $test_request) || !file_exists('./'.$test_request['filename'])
			|| $test_request['filename'] === basename(__FILE__)) {
		$request = '';
	}

	if ($request !== '') {
		$redirect_to->setArgument('request', $request);
		CSessionHelper::set('request', $request);
	}
}

if (CAuthenticationHelper::getPublic(CAuthenticationHelper::SAML_AUTH_ENABLED) == ZBX_AUTH_SAML_DISABLED) {
	CSessionHelper::unset(['request']);

	redirect($redirect_to->toString());
}

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Utils;
use SCIM\services\Group as ScimGroup;

global $SSO;

if (!is_array($SSO)) {
	$SSO = [];
}

$SSO += ['SETTINGS' => []];
$certs = [
	'SP_KEY'	=> 'conf/certs/sp.key',
	'SP_CERT'	=> 'conf/certs/sp.crt',
	'IDP_CERT'	=> 'conf/certs/idp.crt'
];
$certs = array_merge($certs, array_intersect_key($SSO, $certs));
$certs = array_filter($certs, 'is_readable');
$certs = array_map('file_get_contents', $certs);
$certs += array_fill_keys(['SP_KEY', 'SP_CERT', 'IDP_CERT'], '');
/** @var CUser $service */
$service = API::getApiService('user');
$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryid();
$provisioning = CProvisioning::forUserDirectoryId($userdirectoryid);
$provisioning_enabled = ($provisioning->isProvisioningEnabled()
	&& CAuthenticationHelper::getPublic(CAuthenticationHelper::SAML_JIT_STATUS) ==  JIT_PROVISIONING_ENABLED
);

if (array_key_exists('baseurl', $SSO['SETTINGS']) && !is_array($SSO['SETTINGS']['baseurl'])
		&& $SSO['SETTINGS']['baseurl'] !== '') {
	Utils::setBaseURL((string) $SSO['SETTINGS']['baseurl']);
}

if (array_key_exists('use_proxy_headers', $SSO['SETTINGS']) && (bool) $SSO['SETTINGS']['use_proxy_headers']) {
	Utils::setProxyVars(true);
}

$baseurl = Utils::getSelfURLNoQuery();
$relay_state = null;
$saml_settings = $provisioning->getIdpConfig();
$settings = [
	'sp' => [
		'entityId' => $saml_settings['sp_entityid'],
		'assertionConsumerService' => [
			'url' => $baseurl.'?acs'
		],
		'singleLogoutService' => [
			'url' => $baseurl.'?sls'
		],
		'NameIDFormat' => $saml_settings['nameid_format'],
		'x509cert' => $certs['SP_CERT'],
		'privateKey' => $certs['SP_KEY']
	],
	'idp' => [
		'entityId' => $saml_settings['idp_entityid'],
		'singleSignOnService' => [
			'url' => $saml_settings['sso_url']
		],
		'singleLogoutService' => [
			'url' => $saml_settings['slo_url']
		],
		'x509cert' => $certs['IDP_CERT']
	],
	'security' => [
		'nameIdEncrypted' => (bool) $saml_settings['encrypt_nameid'],
		'authnRequestsSigned' => (bool) $saml_settings['sign_authn_requests'],
		'logoutRequestSigned' => (bool) $saml_settings['sign_logout_requests'],
		'logoutResponseSigned' => (bool) $saml_settings['sign_logout_responses'],
		'wantMessagesSigned' => (bool) $saml_settings['sign_messages'],
		'wantAssertionsEncrypted' => (bool)$saml_settings['encrypt_assertions'],
		'wantAssertionsSigned' => (bool) $saml_settings['sign_assertions'],
		'wantNameIdEncrypted' => (bool) $saml_settings['encrypt_nameid']
	]
];

foreach (['strict', 'compress', 'contactPerson', 'organization'] as $option) {
	if (array_key_exists($option, $SSO['SETTINGS'])) {
		$settings[$option] = $SSO['SETTINGS'][$option];
	}
}

if (array_key_exists('sp', $SSO['SETTINGS'])) {
	foreach (['attributeConsumingService', 'x509certNew'] as $option) {
		if (array_key_exists($option, $SSO['SETTINGS']['sp'])) {
			$settings['sp'][$option] = $SSO['SETTINGS']['sp'][$option];
		}
	}
}

if (array_key_exists('idp', $SSO['SETTINGS'])) {
	if (array_key_exists('singleLogoutService', $SSO['SETTINGS']['idp'])
			&& array_key_exists('responseUrl', $SSO['SETTINGS']['idp']['singleLogoutService'])) {
		$settings['idp']['singleLogoutService']['responseUrl'] =
			$SSO['SETTINGS']['idp']['singleLogoutService']['responseUrl'];
	}

	foreach (['certFingerprint', 'certFingerprintAlgorithm', 'x509certMulti'] as $option) {
		if (array_key_exists($option, $SSO['SETTINGS']['idp'])) {
			$settings['idp'][$option] = $SSO['SETTINGS']['idp'][$option];
		}
	}
}

if (array_key_exists('security', $SSO['SETTINGS'])) {
	foreach (['signMetadata', 'wantNameId', 'requestedAuthnContext', 'requestedAuthnContextComparison',
			'wantXMLValidation', 'relaxDestinationValidation', 'destinationStrictlyMatches', 'lowercaseUrlencoding',
			'rejectUnsolicitedResponsesWithInResponseTo', 'signatureAlgorithm', 'digestAlgorithm'] as $option) {
		if (array_key_exists($option, $SSO['SETTINGS']['security'])) {
			$settings['security'][$option] = $SSO['SETTINGS']['security'][$option];
		}
	}
}

try {
	CMessageHelper::clear();
	$auth = new Auth($settings);

	if (hasRequest('metadata')) {
		$metadata = $auth->getSettings()->getSPMetadata();

		header('Content-Type: text/xml');
		echo $metadata;

		session_write_close();
		exit;
	}

	if (hasRequest('acs') && !CSessionHelper::has('saml_data')) {
		$auth->processResponse();

		if (!$auth->isAuthenticated()) {
			throw new Exception($auth->getLastErrorReason());
		}

		$groups_key = $saml_settings['group_name'];

		foreach ($auth->getAttributes() as $attribute => $value) {
			if ($groups_key !== $attribute) {
				$value = reset($value);
			}

			$user_attributes[$attribute] = $value;
		}

		if (!array_key_exists($saml_settings['username_attribute'], $user_attributes)) {
			throw new Exception(
				_s('The parameter "%1$s" is missing from the user attributes.', $saml_settings['username_attribute'])
			);
		}

		$saml_data = [
			'username_attribute'		=> $user_attributes[$saml_settings['username_attribute']],
			'nameid'					=> $auth->getNameId(),
			'nameid_format'				=> $auth->getNameIdFormat(),
			'nameid_name_qualifier'		=> $auth->getNameIdNameQualifier(),
			'nameid_sp_name_qualifier'	=> $auth->getNameIdSPNameQualifier(),
			'session_index'				=> $auth->getSessionIndex(),
			'provisioned_user'			=> []
		];

		if ($provisioning_enabled) {
			$user = $provisioning->getUserAttributes($user_attributes);
			$user['medias'] = $provisioning->getUserMedias($user_attributes);
			$idp_groups = [];

			if (array_key_exists($groups_key, $user_attributes) && is_array($user_attributes[$groups_key])) {
				$idp_groups = (count($user_attributes[$groups_key]) > 1)
					? $user_attributes[$groups_key]
					: explode(';', $user_attributes[$groups_key][0]);
			}

			$user += $provisioning->getUserGroupsAndRole($idp_groups);
			$saml_data['idp_groups'] = $idp_groups;
			$saml_data['provisioned_user'] = $user;
		}

		$saml_data['sign'] = CEncryptHelper::sign(json_encode($saml_data));

		CSessionHelper::set('saml_data', $saml_data);

		if (hasRequest('RelayState') && strpos(getRequest('RelayState'), $baseurl) === false) {
			$relay_state = getRequest('RelayState');
		}
	}

	if ($saml_settings['slo_url'] !== '') {
		if (hasRequest('slo') && CSessionHelper::has('saml_data')) {
			$saml_data = CSessionHelper::get('saml_data');

			CWebUser::logout();

			$auth->logout(null, [], $saml_data['nameid'], $saml_data['session_index'], false,
				$saml_data['nameid_format'], $saml_data['nameid_name_qualifier'], $saml_data['nameid_sp_name_qualifier']
			);
		}

		if (hasRequest('sls')) {
			CSessionHelper::unset(['saml_data']);
			$auth->processSLO();

			redirect('index.php');
		}
	}

	if (CWebUser::isLoggedIn() && !CWebUser::isGuest()) {
		redirect($redirect_to->toString());
	}

	if (CSessionHelper::has('saml_data')) {
		$saml_data = CSessionHelper::get('saml_data');

		if (!array_key_exists('sign', $saml_data)) {
			throw new Exception(_('Session initialization error.'));
		}

		$saml_data_sign = $saml_data['sign'];
		$saml_data_sign_check = CEncryptHelper::sign(json_encode(array_diff_key($saml_data, array_flip(['sign']))));

		if (!CEncryptHelper::checkSign($saml_data_sign, $saml_data_sign_check)) {
			throw new Exception(_('Session initialization error.'));
		}

		// Temporary disabling wrapper for API requests.
		$wrapper = API::getWrapper();
		API::setWrapper();

		if ($saml_data['provisioned_user'] && $provisioning_enabled) {
			$userdirectoryid = CAuthenticationHelper::getSamlUserdirectoryid();

			$db_users = CUser::findUsersByUsername($saml_data['username_attribute'],
				CAuthenticationHelper::getPublic(CAuthenticationHelper::SAML_CASE_SENSITIVE) == ZBX_AUTH_CASE_SENSITIVE
			);

			if (!$db_users && $saml_data['provisioned_user']['roleid']) {
				$saml_data['provisioned_user'] += [
					'userdirectoryid' => $userdirectoryid,
					'username' => $saml_data['username_attribute']
				];
				$user = API::User()->createProvisionedUser($saml_data['provisioned_user']);
				ScimGroup::createScimGroupsFromSamlAttributes($saml_data['idp_groups'], $user['userid']);
			}

			if (count($db_users) > 1) {
				throw new Exception(_s('Authentication failed: %1$s.', _('supplied credentials are not unique')));
			}

			if ($db_users) {
				$db_user = $db_users[0];

				CUser::addUserGroupFields($db_user, $group_status);

				if (($group_status == GROUP_STATUS_ENABLED || $db_user['deprovisioned'])
						&& bccomp($db_user['userdirectoryid'], $userdirectoryid) == 0) {
					$saml_data['provisioned_user']['userid'] = $db_user['userid'];
					API::User()->updateProvisionedUser($saml_data['provisioned_user']);
					ScimGroup::createScimGroupsFromSamlAttributes($saml_data['idp_groups'], $db_user['userid']);
				}
			}

			unset($saml_data['provisioned_user'], $saml_data['idp_groups']);
			CSessionHelper::set('saml_data', $saml_data);
		}

		CWebUser::$data = CUser::loginByUsername($saml_data['username_attribute'],
			CAuthenticationHelper::getPublic(CAuthenticationHelper::SAML_CASE_SENSITIVE) == ZBX_AUTH_CASE_SENSITIVE
		);
		API::setWrapper($wrapper);

		if (CWebUser::$data['gui_access'] == GROUP_GUI_ACCESS_DISABLED) {
			throw new Exception(_('GUI access disabled.'));
		}

		CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
		API::getWrapper()->auth = [
			'type' => CJsonRpc::AUTH_TYPE_COOKIE,
			'auth' => CWebUser::$data['sessionid']
		];

		$redirect = array_filter([$request, CWebUser::$data['url'], $relay_state, CMenuHelper::getFirstUrl()]);
		redirect(reset($redirect));
	}

	$auth->login();
}
catch (Exception $e) {
	CSessionHelper::unset(['saml_data']);

	error($e->getMessage());
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => array_column(get_and_clear_messages(), 'message'),
	'buttons' => [
		(new CButton('login', _('Login')))
			->setAttribute('data-url',
				$redirect_to
					->setArgument('request', $request)
					->getUrl()
			)
			->onClick('document.location = this.dataset.url;')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();

session_write_close();
