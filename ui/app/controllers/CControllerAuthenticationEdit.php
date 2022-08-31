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
			'saml_jit_status' =>				'in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'idp_entityid' =>					'db userdirectory_saml.idp_entityid',
			'sso_url' =>						'db userdirectory_saml.sso_url',
			'slo_url' =>						'db userdirectory_saml.slo_url',
			'username_attribute' =>				'db userdirectory_saml.username_attribute',
			'sp_entityid' =>					'db userdirectory_saml.sp_entityid',
			'nameid_format' =>					'db userdirectory_saml.nameid_format',
			'sign_messages' =>					'in 0,1',
			'sign_assertions' =>				'in 0,1',
			'sign_authn_requests' =>			'in 0,1',
			'sign_logout_requests' =>			'in 0,1',
			'sign_logout_responses' =>			'in 0,1',
			'encrypt_nameid' =>					'in 0,1',
			'encrypt_assertions' =>				'in 0,1',
			'saml_case_sensitive' =>			'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'saml_group_name' =>				'db userdirectory_saml.group_name',
			'saml_user_username' =>				'db userdirectory_saml.user_username',
			'saml_user_lastname' =>				'db userdirectory_saml.user_lastname',
			'saml_provision_groups' =>			'array',
			'saml_provision_media' =>			'array',
			'scim_status' =>					'in '.ZBX_AUTH_SCIM_PROVISIONING_DISABLED.','.ZBX_AUTH_SCIM_PROVISIONING_ENABLED,
			'scim_token' =>						'db userdirectory_saml.scim_token',
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
			CAuthenticationHelper::LDAP_JIT_STATUS,
			CAuthenticationHelper::JIT_PROVISION_INTERVAL,
			CAuthenticationHelper::SAML_AUTH_ENABLED,
			CAuthenticationHelper::SAML_JIT_STATUS,
			CAuthenticationHelper::SAML_CASE_SENSITIVE,
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
				'jit_provision_interval',
				'ldap_jit_status',
				'saml_auth_enabled',
				'saml_jit_enable',
				'idp_entityid',
				'sso_url',
				'slo_url',
				'username_attribute',
				'sp_entityid',
				'nameid_format',
				'sign_messages',
				'sign_assertions',
				'sign_authn_requests',
				'sign_logout_requests',
				'sign_logout_responses',
				'encrypt_nameid',
				'encrypt_assertions',
				'saml_case_sensitive',
				'saml_group_name',
				'saml_user_username',
				'saml_user_lastname',
				'scim_status',
				'scim_token',
				'passwd_min_length',
				'passwd_check_rules'
			]);

			$data['saml_provision_groups'] = $this->getInput('saml_provision_groups', []);
sdii($data['saml_provision_groups']);
			if ($data['saml_provision_groups'] != []) {
				$data['saml_provision_groups'] = $this->extendProvisionGroups($data['saml_provision_groups']);
			}
			sdii($data['saml_provision_groups']);

			$data['saml_provision_media'] = $this->getInput('saml_provision_media', []);

			if ($data['saml_provision_media'] != []) {
				foreach ($data['saml_provision_media'] as &$saml_media) {
					$media = API::MediaType()->get([
						'output' => ['name'],
						'mediatypeids' => $saml_media['mediatypeid']
					]);
					$saml_media['mediatype_name'] = $media[0]['name'];
				}
				unset($saml_media);
			}

			$data['saml_userdirectoryid'] = '';
			$data['ldap_servers'] = $this->getLdapServerUserGroupCount($this->getInput('ldap_servers', []));
			$data['ldap_default_row_index'] = $this->getInput('ldap_default_row_index', 0);
			$data['ldap_removed_userdirectoryids'] = $this->getInput('ldap_removed_userdirectoryids', []);

			$data += $auth;
		}
		else {
			$data += $auth;

			$saml_configuration = API::UserDirectory()->get([
				'output' => 'extend',
				'filter' => [
					'idp_type' => IDP_TYPE_SAML
				],
				'selectProvisionGroups' => 'extend',
				'selectProvisionMedia' => 'extend'
			]);
			if ($saml_configuration) {
				$saml_configuration += $saml_configuration[0];

				$data['saml_userdirectoryid'] = $saml_configuration['userdirectoryid'];
				$data['saml_group_name'] = $saml_configuration['group_name'];
				$data['saml_user_username'] = $saml_configuration['user_username'];
				$data['saml_user_lastname'] = $saml_configuration['user_lastname'];
				$data['saml_provision_groups'] = $this->extendProvisionGroups($saml_configuration['provision_groups']);
				$data['saml_provision_media'] = [];

				foreach ($saml_configuration['provision_media'] as $media) {
					$db_media = API::MediaType()->get([
						'output' => ['name'],
						'mediatypeids' => $media['mediatypeid']
					]);

					$data['saml_provision_media'][] = $media + [
							'mediatype_name' => $db_media[0]['name'],
						];
				}

				unset($saml_configuration['userdirectoryid'], $saml_configuration['group_name'],
					$saml_configuration['user_username'], $saml_configuration['user_lastname'],
					$saml_configuration['provision_groups'], $saml_configuration['provision_media']);

				$data += $saml_configuration;
			}
			else {
				$saml_settings = [
					'saml_userdirectoryid' => '',
					'idp_entityid' => '',
					'sso_url' => '',
					'slo_url' => '',
					'username_attribute' => '',
					'sp_entityid' => '',
					'nameid_format' => '',
					'sign_messages' => '',
					'sign_assertions' => '',
					'sign_authn_requests' => '',
					'sign_logout_requests' => '',
					'sign_logout_responses' => '',
					'encrypt_nameid' => '',
					'encrypt_assertions' => '',
					'saml_group_name' => '',
					'saml_user_username' => '',
					'saml_user_lastname' => '',
					'scim_status' => '',
					'scim_token' => ''
				];

				$default_group = [
					[
						'is_fallback' => GROUP_MAPPING_FALLBACK,
						'fallback_status' => GROUP_MAPPING_FALLBACK_OFF,
						'name' => 'Fallback group',
						'roleid' => 1,
						'user_groups' => [
							['usrgrpid' => 7],
							['usrgrpid' => 8]
						]
					]
				];
				$saml_settings['saml_provision_groups'] = $this->extendProvisionGroups($default_group);
				$saml_settings['saml_provision_media'] = [];

				$data += $saml_settings;
			}

			$data['ldap_servers'] = API::UserDirectory()->get([
				'output' => ['userdirectoryid', 'name', 'description', 'host', 'port', 'base_dn', 'search_attribute',
					'search_filter', 'start_tls', 'bind_dn'
				],
				'filter' => [
					'idp_type' => IDP_TYPE_LDAP
				],
				'selectUsrgrps' => API_OUTPUT_COUNT,
				'sortfield' => ['name'],
				'sortorder' => ZBX_SORT_UP
			]);

			$data['ldap_default_row_index'] = '';

			if ($data['ldap_servers']) {
				$data['ldap_default_row_index'] = array_search($data[CAuthenticationHelper::LDAP_USERDIRECTORYID],
					array_column($data['ldap_servers'], 'userdirectoryid')
				);
			}

			$data['ldap_removed_userdirectoryids'] = [];
		}

		$data['saml_allow_jit'] = $data['saml_group_name'] !== '';

		unset($data[CAuthenticationHelper::LDAP_USERDIRECTORYID]);
		$data['ldap_enabled'] = ($ldap_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED);
		$data['saml_enabled'] = ($openssl_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED);
		$data['db_authentication_type'] = CAuthenticationHelper::get(CAuthenticationHelper::AUTHENTICATION_TYPE);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of authentication'));
		$this->setResponse($response);
	}

	private function getLdapServerUserGroupCount(array $ldap_servers): array {
		$ldap_serverids = array_column($ldap_servers, 'userdirectoryid');

		$db_ldap_servers = $ldap_serverids
			? API::UserDirectory()->get([
				'output' => ['userdirectoryid'],
				'idp_type' => IDP_TYPE_LDAP,
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

	private function extendProvisionGroups(array $provision_groups): array {
		$extended_provision_groups = [];

		foreach ($provision_groups as $provision_group) {
			if ($provision_group['is_fallback'] == 1) {
				$provision_group['name'] = 'Fallback group';
			}

			$role = API::Role()->get([
				'output' => ['name'],
				'roleids' => $provision_group['roleid']
			]);

			if (!array_column($provision_group['user_groups'], 'usrgrpid')) {
				$user_groups = [];
				foreach ($provision_group['user_groups'] as $usrgrpid) {
					$user_groups[]= ['usrgrpid' => $usrgrpid];
				}
				$provision_group['user_groups'] = $user_groups;
			}

			$user_groups = API::UserGroup()->get([
				'output' => ['name', 'usrgrpid'],
				'usrgrpids' => array_column($provision_group['user_groups'], 'usrgrpid')
			]);

			$extended_provision_groups[] = array_merge($provision_group, [
				'role_name' => $role[0]['name'],
				'user_groups' => $user_groups
			]);
		}

		return $extended_provision_groups;
	}
}
