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
			'form_refresh' =>					'string',
			'change_bind_password' =>			'in 0,1',
			'db_authentication_type' =>			'string',
			'authentication_type' =>			'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'http_auth_enabled' =>				'in '.ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED,
			'http_login_form' =>				'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
			'http_strip_domains' =>				'db config.http_strip_domains',
			'http_case_sensitive' =>			'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_configured' =>				'in '.ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED,
			'ldap_servers' =>					'array',
			'ldap_default_row_index' =>			'int32',
			'ldap_case_sensitive' =>			'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_removed_userdirectoryids' =>	'array',
			'saml_auth_enabled' =>				'in '.ZBX_AUTH_SAML_DISABLED.','.ZBX_AUTH_SAML_ENABLED,
			'saml_scim_enabled' =>				'in '.ZBX_AUTH_SCIM_PROVISIONING_DISABLED.','.ZBX_AUTH_SCIM_PROVISIONING_ENABLED,
			'saml_idp_entityid' =>				'db config.saml_idp_entityid',
			'saml_sso_url' =>					'db config.saml_sso_url',
			'saml_slo_url' =>					'db config.saml_slo_url',
			'saml_username_attribute' =>		'db config.saml_username_attribute',
			'saml_sp_entityid' =>				'db config.saml_sp_entityid',
			'saml_nameid_format' =>				'db config.saml_nameid_format',
			'saml_sign_messages' =>				'in 0,1',
			'saml_sign_assertions' =>			'in 0,1',
			'saml_sign_authn_requests' =>		'in 0,1',
			'saml_sign_logout_requests' =>		'in 0,1',
			'saml_sign_logout_responses' =>		'in 0,1',
			'saml_encrypt_nameid' =>			'in 0,1',
			'saml_encrypt_assertions' =>		'in 0,1',
			'saml_case_sensitive' =>			'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'saml_group_name_attribute' =>		'db config.saml_group_name_attribute',
			'saml_user_name_attribute' =>		'db config.saml_user_name_attribute',
			'saml_user_lastname_attribute' =>	'db config.saml_user_lastname_attribute',
			'saml_authorization_token' =>		'db config.saml_authorization_token',
			'saml_groups' =>					'array',
			'passwd_min_length' =>				'int32',
			'passwd_check_rules' =>				'int32|ge 0|le '.(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE)
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
			CAuthenticationHelper::LDAP_CONFIGURED,
			CAuthenticationHelper::LDAP_USERDIRECTORYID,
			CAuthenticationHelper::LDAP_CASE_SENSITIVE,
			CAuthenticationHelper::SAML_AUTH_ENABLED,
			CAuthenticationHelper::SAML_SCIM_ENABLED,
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
			CAuthenticationHelper::SAML_GROUP_NAME_ATTRIBUTE,
			CAuthenticationHelper::SAML_USER_NAME_ATTRIBUTE,
			CAuthenticationHelper::SAML_USER_LASTNAME_ATTRIBUTE,
			CAuthenticationHelper::SAML_AUTHORIZATION_TOKEN,
			CAuthenticationHelper::PASSWD_MIN_LENGTH,
			CAuthenticationHelper::PASSWD_CHECK_RULES
		];
		$auth = [];
		foreach ($auth_params as $param) {
			$auth[$param] = CAuthenticationHelper::get($param);
		}

		if ($this->hasInput('form_refresh')) {
			$this->getInputs($data, [
				'form_refresh',
				'change_bind_password',
				'authentication_type',
				'http_auth_enabled',
				'http_login_form',
				'http_strip_domains',
				'http_case_sensitive',
				'ldap_configured',
				'ldap_case_sensitive',
				'saml_auth_enabled',
				'saml_scim_enable',
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
				'saml_group_name_attribute',
				'saml_user_name_attribute',
				'saml_user_lastname_attribute',
				'saml_authorization_token',
				'passwd_min_length',
				'passwd_check_rules'
			]);

			$data['ldap_servers'] = $this->getLdapServerUserGroupCount($this->getInput('ldap_servers', []));
			$data['ldap_default_row_index'] = $this->getInput('ldap_default_row_index', 0);
			$data['ldap_removed_userdirectoryids'] = $this->getInput('ldap_removed_userdirectoryids', []);

			$data += $auth;
		}
		else {
			$data += $auth;

			$data['ldap_servers'] = API::UserDirectory()->get([
				'output' => ['userdirectoryid', 'name', 'description', 'host', 'port', 'base_dn', 'search_attribute',
					'search_filter', 'start_tls', 'bind_dn'
				],
				'selectUsrgrps' => API_OUTPUT_COUNT,
				'sortfield' => ['name'],
				'sortorder' => ZBX_SORT_UP
			]);

			$data['ldap_default_row_index'] = array_search($data[CAuthenticationHelper::LDAP_USERDIRECTORYID],
				array_column($data['ldap_servers'], 'userdirectoryid')
			);
			$data['ldap_removed_userdirectoryids'] = [];
		}

		unset($data[CAuthenticationHelper::LDAP_USERDIRECTORYID]);
		$data['ldap_enabled'] = ($ldap_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED);
		$data['saml_enabled'] = ($openssl_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED);
		$data['db_authentication_type'] = CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE);
		$data['saml_allow_scim'] = $data['saml_group_name_attribute'] === '';

		$user_groups = [
			[
				'name' => 'SAML group',
				'usrgrpid' => 14
			],
			[
				'name' => '	Zabbix administrators',
				'usrgrpid' => 7
			]
		];

		$user_role = [
			[
				'roleid' => 2,
				'name' => 'Admin role'
			]
		];

		$group_name = implode(', ',array_column($user_groups, 'name'));
		$group_id = implode(', ',array_column($user_groups, 'usrgrpid'));

		$data['saml_groups'] = [
			[
				'idp_group_name' => 'Developers',
				'user_group_name' => 'Zabbix SAML',
				'user_group_id' => '123',
				'role_name' => 'Admin role',
				'roleid' => '2',
				'is_fallback' => 0
			],
			[
				'idp_group_name' => 'Fallback group',
				'user_group_name' => $group_name,
				'role_name' => $user_role[0]['name'],
				'roleid' => $user_role[0]['roleid'],
				'usrgrpid' => $group_id,
				'is_fallback' => 1,
				'fallback_status' => 0
			]
		];

		$data['saml_media_type_mappings'] = [
			[
				'media_type_mapping_name' => 'Email media type',
				'media_type_name' => 'Email',
				'media_type_attribute' => 'userEmail',
				'mediatypeid' => 1
			]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of authentication'));
		$this->setResponse($response);
	}

	private function getLdapServerUserGroupCount(array $ldap_servers): array {
		$ldap_serverids = array_column($ldap_servers, 'userdirectoryid');

		$db_ldap_servers = $ldap_serverids
			? API::UserDirectory()->get([
				'output' => ['userdirectoryid'],
				'selectUsrgrps' => API_OUTPUT_COUNT,
				'userdirectoryids' => $ldap_serverids,
				'preservekeys' => true
			])
			: [];

		foreach ($ldap_servers as &$ldap_server) {
			$ldap_server['usrgrps'] = 0;

			if (array_key_exists('userdirectoryid', $ldap_server)
					&& array_key_exists($ldap_server['userdirectoryid'], $db_ldap_servers)) {
				$ldap_server['usrgrps'] = $db_ldap_servers[$ldap_server['userdirectoryid']]['usrgrps'];
			}
		}
		unset($ldap_server);

		return $ldap_servers;
	}
}
