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


class CControllerAuthenticationEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	/**
	 * Validate user input.
	 *
	 * @return bool
	 */
	protected function checkInput() {
		$fields = [
			'form_refresh' =>				'string',
			'ldap_test_user' =>				'string',
			'ldap_test_password' =>			'string',
			'change_bind_password' =>		'in 0,1',
			'db_authentication_type' =>		'string',
			'authentication_type' =>		'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'http_auth_enabled' =>			'in '.ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED,
			'http_login_form' =>			'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
			'http_strip_domains' =>			'db config.http_strip_domains',
			'http_case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_configured' =>			'in '.ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED,
			'ldap_host' =>					'db config.ldap_host',
			'ldap_port' =>					'int32',
			'ldap_base_dn' =>				'db config.ldap_base_dn',
			'ldap_bind_dn' =>				'db config.ldap_bind_dn',
			'ldap_search_attribute' =>		'db config.ldap_search_attribute',
			'ldap_bind_password' =>			'db config.ldap_bind_password',
			'saml_auth_enabled' =>			'in '.ZBX_AUTH_SAML_DISABLED.','.ZBX_AUTH_SAML_ENABLED,
			'saml_idp_entityid' =>			'db config.saml_idp_entityid',
			'saml_sso_url' =>				'db config.saml_sso_url',
			'saml_slo_url' =>				'db config.saml_slo_url',
			'saml_username_attribute' =>	'db config.saml_username_attribute',
			'saml_sp_entityid' =>			'db config.saml_sp_entityid',
			'saml_nameid_format' =>			'db config.saml_nameid_format',
			'saml_sign_messages' =>			'in 0,1',
			'saml_sign_assertions' =>		'in 0,1',
			'saml_sign_authn_requests' =>	'in 0,1',
			'saml_sign_logout_requests' =>	'in 0,1',
			'saml_sign_logout_responses' =>	'in 0,1',
			'saml_encrypt_nameid' =>		'in 0,1',
			'saml_encrypt_assertions' =>	'in 0,1',
			'saml_case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'passwd_min_length' =>			'int32',
			'passwd_check_rules' =>			'int32|ge 0|le '.(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE)
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * Validate is user allowed to change configuration.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction() {
		$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();
		$openssl_status = (new CFrontendSetup())->checkPhpOpenSsl();

		$data = [
			'action_submit' => 'authentication.update',
			'action_passw_change' => 'authentication.edit',
			'ldap_error' => ($ldap_status['result'] == CFrontendSetup::CHECK_OK) ? '' : $ldap_status['error'],
			'ldap_test_password' => '',
			'ldap_test_user' => CWebUser::$data['username'],
			'saml_error' => ($openssl_status['result'] == CFrontendSetup::CHECK_OK) ? '' : $openssl_status['error'],
			'change_bind_password' => 0,
			'form_refresh' => 0
		];

		$auth_params = [
			CAuthenticationHelper::AUTHENTICATION_TYPE,
			CAuthenticationHelper::HTTP_AUTH_ENABLED,
			CAuthenticationHelper::HTTP_LOGIN_FORM,
			CAuthenticationHelper::HTTP_STRIP_DOMAINS,
			CAuthenticationHelper::HTTP_CASE_SENSITIVE,
			CAuthenticationHelper::LDAP_CASE_SENSITIVE,
			CAuthenticationHelper::LDAP_CONFIGURED,
			CAuthenticationHelper::LDAP_HOST,
			CAuthenticationHelper::LDAP_PORT,
			CAuthenticationHelper::LDAP_BASE_DN,
			CAuthenticationHelper::LDAP_BIND_DN,
			CAuthenticationHelper::LDAP_SEARCH_ATTRIBUTE,
			CAuthenticationHelper::LDAP_BIND_PASSWORD,
			CAuthenticationHelper::SAML_AUTH_ENABLED,
			CAuthenticationHelper::SAML_IDP_ENTITYID,
			CAuthenticationHelper::SAML_SSO_URL,
			CAuthenticationHelper::SAML_SLO_URL,
			CAuthenticationHelper::SAML_USERNAME_ATTRIBUTE,
			CAuthenticationHelper::SAML_SP_ENTITYID,
			CAuthenticationHelper::SAML_NAMEID_FORMAT,
			CAuthenticationHelper::SAML_SIGN_MESSAGES,
			CAuthenticationHelper::SAML_SIGN_ASSERTIONS,
			CAuthenticationHelper::SAML_SIGN_AUTHN_REQUESTS,
			CAuthenticationHelper::SAML_SIGN_LOGOUT_REQUESTS,
			CAuthenticationHelper::SAML_SIGN_LOGOUT_RESPONSES,
			CAuthenticationHelper::SAML_ENCRYPT_NAMEID,
			CAuthenticationHelper::SAML_ENCRYPT_ASSERTIONS,
			CAuthenticationHelper::SAML_CASE_SENSITIVE,
			CAuthenticationHelper::PASSWD_MIN_LENGTH,
			CAuthenticationHelper::PASSWD_CHECK_RULES
		];
		$auth = [];
		foreach ($auth_params as $param) {
			$auth[$param] = CAuthenticationHelper::get($param);
		}

		if ($this->hasInput('form_refresh')) {
			$data['ldap_bind_password'] = '';
			$this->getInputs($data, [
				'form_refresh',
				'change_bind_password',
				'db_authentication_type',
				'authentication_type',
				'http_auth_enabled',
				'http_login_form',
				'http_strip_domains',
				'http_case_sensitive',
				'ldap_case_sensitive',
				'ldap_configured',
				'ldap_host',
				'ldap_port',
				'ldap_base_dn',
				'ldap_bind_dn',
				'ldap_search_attribute',
				'ldap_bind_password',
				'ldap_test_user',
				'ldap_test_password',
				'saml_auth_enabled',
				'saml_idp_entityid',
				'saml_sso_url',
				'saml_slo_url',
				'saml_username_attribute',
				'saml_sp_entityid',
				'saml_nameid_format',
				'saml_sign_messages',
				'saml_sign_assertions',
				'saml_sign_authn_requests',
				'saml_sign_logout_requests',
				'saml_sign_logout_responses',
				'saml_encrypt_nameid',
				'saml_encrypt_assertions',
				'saml_case_sensitive',
				'passwd_min_length',
				'passwd_check_rules'
			]);

			$data += $auth;
		}
		else {
			$data += $auth;
			$data['db_authentication_type'] = $data['authentication_type'];
			$data['change_bind_password'] = ($data['ldap_bind_password'] === '') ? 1 : 0;
		}

		$data['ldap_enabled'] = ($ldap_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED);
		$data['saml_enabled'] = ($openssl_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of authentication'));
		$this->setResponse($response);
	}
}
