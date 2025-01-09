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


class CControllerAuthenticationUpdate extends CController {

	/**
	 * @var CControllerResponseRedirect
	 */
	private $response;

	private const PROVISION_ENABLED_FIELDS = ['group_basedn', 'group_member', 'group_membership',  'group_name',
		'user_username', 'user_lastname', 'uer_ref_attr', 'provision_groups', 'provision_media'
	];

	protected function init() {
		$this->response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'authentication.edit')
			->getUrl()
		);
	}

	protected function checkInput() {
		global $ALLOW_HTTP_AUTH;

		$fields = [
			'form_refresh' =>					'int32',
			'authentication_type' =>			'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'disabled_usrgrpid' =>				'id',
			'ldap_auth_enabled' =>				'in '.ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED,
			'ldap_servers' =>					'array',
			'ldap_default_row_index' =>			'int32',
			'ldap_case_sensitive' =>			'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_removed_userdirectoryids' =>	'array_id',
			'ldap_jit_status' =>				'in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'jit_provision_interval' =>			'db config.jit_provision_interval|time_unit_year '.implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR]),
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
			'saml_provision_status' =>			'in '.JIT_PROVISIONING_DISABLED.','.JIT_PROVISIONING_ENABLED,
			'saml_group_name' =>				'db userdirectory_saml.group_name',
			'saml_user_username' =>				'db userdirectory_saml.user_username',
			'saml_user_lastname' =>				'db userdirectory_saml.user_lastname',
			'saml_provision_groups' =>			'array',
			'saml_provision_media' =>			'array',
			'scim_status' =>					'in '.ZBX_AUTH_SCIM_PROVISIONING_DISABLED.','.ZBX_AUTH_SCIM_PROVISIONING_ENABLED,
			'passwd_min_length' =>				'int32',
			'passwd_check_rules' =>				'array',
			'mfa_status' =>						'in '.MFA_DISABLED.','.MFA_ENABLED,
			'mfa_methods' =>					'array',
			'mfa_default_row_index' =>			'int32',
			'mfa_removed_mfaids' =>				'array_id'
		];

		if ($ALLOW_HTTP_AUTH) {
			$fields += [
				'http_auth_enabled' =>		'in '.ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED,
				'http_login_form' =>		'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
				'http_strip_domains' =>		'db config.http_strip_domains',
				'http_case_sensitive' =>	'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE
			];
		}

		$ret = $this->validateInput($fields);

		if ($ret) {
			$ret = $this->validateLdap() && $this->validateSamlAuth() && $this->validateMfa();
		}

		if (!$ret) {
			if (CMessageHelper::getTitle() === null) {
				CMessageHelper::setErrorTitle(_('Cannot update authentication'));
			}
			$this->response->setFormData($this->getInputAll());
			$this->setResponse($this->response);
		}

		return $ret;
	}

	/**
	 * Validate LDAP settings.
	 *
	 * @return bool
	 */
	private function validateLdap(): bool {
		if ($this->getInput('ldap_auth_enabled', DB::getDefault('config', 'ldap_auth_enabled')) ==
				ZBX_AUTH_LDAP_ENABLED) {
			$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();

			if ($ldap_status['result'] != CFrontendSetup::CHECK_OK) {
				error($ldap_status['error']);

				return false;
			}

			$ldap_servers = $this->getInput('ldap_servers', []);

			if (!$ldap_servers) {
				error(_('At least one LDAP server must exist.'));

				return false;
			}

			if (!$this->hasInput('ldap_default_row_index')
					|| !array_key_exists($this->getInput('ldap_default_row_index'), $ldap_servers)) {
				error(_('Default LDAP server must be specified.'));

				return false;
			}

			foreach ($ldap_servers as $ldap_server) {
				if (!array_key_exists('provision_status', $ldap_server)
						|| $ldap_server['provision_status'] != JIT_PROVISIONING_ENABLED) {
					continue;
				}

				if (!array_key_exists('provision_groups', $ldap_server)
						|| !$this->validateProvisionGroups($ldap_server['provision_groups'])) {
					error(_('Invalid LDAP JIT provisioning user group mapping configuration.'));

					return false;
				}

				if (array_key_exists('provision_media', $ldap_server)
						&& !$this->validateProvisionMedia($ldap_server['provision_media'])) {
					error(_('Invalid LDAP JIT provisioning media type mapping configuration.'));

					return false;
				}
			}
		}
		elseif ($this->getInput('authentication_type', DB::getDefault('config', 'authentication_type')) ==
				ZBX_AUTH_LDAP) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'authentication_type', _('LDAP is not configured')));

			return false;
		}

		return true;
	}

	/**
	 * Validate SAML authentication settings.
	 *
	 * @return bool
	 */
	private function validateSamlAuth() {
		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_ENABLED) == ZBX_AUTH_SAML_DISABLED) {
			return true;
		}

		$openssl_status = (new CFrontendSetup())->checkPhpOpenSsl();
		if ($openssl_status['result'] != CFrontendSetup::CHECK_OK) {
			error($openssl_status['error']);

			return false;
		}

		$this->getInputs($saml_fields, [
			'idp_entityid',
			'sso_url',
			'username_attribute',
			'sp_entityid'
		]);

		if ($this->getInput('saml_provision_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
			$saml_fields['saml_group_name'] = $this->getInput('saml_group_name', '');

			if (!$this->validateProvisionGroups($this->getInput('saml_provision_groups', []))) {
				error(_('Invalid SAML JIT provisioning user group mapping configuration.'));

				return false;
			}

			if (!$this->validateProvisionMedia($this->getInput('saml_provision_media', []))) {
				error(_('Invalid SAML JIT provisioning media type mapping configuration.'));

				return false;
			}
		}

		foreach ($saml_fields as $field_name => $field_value) {
			if ($field_value === '') {
				error(_s('Incorrect value for field "%1$s": %2$s.', $field_name, _('cannot be empty')));

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate MFA settings.
	 *
	 * @return bool
	 */
	private function validateMfa(): bool {
		$default_mfa = $this->hasInput('mfa_default_row_index') ? $this->getInput('mfa_default_row_index') : null;
		$error = $this->getInput('mfa_status', MFA_DISABLED) == MFA_ENABLED
			&& !array_key_exists($default_mfa, $this->getInput('mfa_methods', []));

		if ($error) {
			error(_('Default MFA method must be specified.'));
		}

		return !$error;
	}

	/**
	 * Validate is user allowed to change configuration.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	/**
	 * In case of error, convert array back to integer (string) so edit form does not fail.
	 *
	 * @return array
	 */
	public function getInputAll() {
		$input = parent::getInputAll();
		$rules = $input['passwd_check_rules'];
		$input['passwd_check_rules'] = 0x00;

		foreach ($rules as $rule) {
			$input['passwd_check_rules'] |= $rule;
		}

		// CNewValidator thinks int32 must be a string type integer.
		$input['passwd_check_rules'] = (string) $input['passwd_check_rules'];

		return $input;
	}

	protected function doAction() {
		$result = false;

		try {
			DBstart();

			$result = $this->processSamlConfiguration();

			$ldap_userdirectoryid = 0;
			if ($result) {
				$ldap_servers = $this->getInput('ldap_servers', []);

				if ($ldap_servers) {
					$ldap_userdirectoryids = $this->processLdapServers($ldap_servers);
					$ldap_default_row_index = $this->getInput('ldap_default_row_index');

					if (!$ldap_userdirectoryids) {
						$result = false;
					}
					else {
						$ldap_userdirectoryid = $ldap_userdirectoryids[$ldap_default_row_index];
					}
				}
			}

			$mfaid = 0;
			if ($result) {
				$mfa_methods = $this->getInput('mfa_methods', []);

				if ($mfa_methods) {
					$mfaids = $this->processMfaMethods($mfa_methods);

					if (!$mfaids) {
						$result = false;
					}
					else {
						$mfaid = $mfaids[$this->getInput('mfa_default_row_index', 0)];
					}
				}
			}

			if ($result) {
				$result = $this->processGeneralAuthenticationSettings($ldap_userdirectoryid, $mfaid);
			}

			if ($result && $this->hasInput('ldap_removed_userdirectoryids')) {
				$result = (bool) API::UserDirectory()->delete($this->getInput('ldap_removed_userdirectoryids'));
			}

			if ($result && $this->hasInput('mfa_removed_mfaids')) {
				$result = (bool) API::Mfa()->delete($this->getInput('mfa_removed_mfaids'));
			}

			if (!$result) {
				throw new Exception();
			}

			$result = DBend(true);
		}
		catch (Exception $e) {
			DBend(false);
		}

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Authentication settings updated'));
		}
		else {
			if (CMessageHelper::getTitle() === null) {
				CMessageHelper::setErrorTitle(_('Cannot update authentication'));
			}
			$this->response->setFormData($this->getInputAll());
		}

		$this->setResponse($this->response);
	}

	private function processGeneralAuthenticationSettings(int $ldap_userdirectoryid, int $mfaid): bool {
		global $ALLOW_HTTP_AUTH;

		$auth_params = [
			CAuthenticationHelper::AUTHENTICATION_TYPE,
			CAuthenticationHelper::DISABLED_USER_GROUPID,
			CAuthenticationHelper::LDAP_AUTH_ENABLED,
			CAuthenticationHelper::LDAP_USERDIRECTORYID,
			CAuthenticationHelper::LDAP_CASE_SENSITIVE,
			CAuthenticationHelper::LDAP_JIT_STATUS,
			CAuthenticationHelper::JIT_PROVISION_INTERVAL,
			CAuthenticationHelper::SAML_AUTH_ENABLED,
			CAuthenticationHelper::SAML_JIT_STATUS,
			CAuthenticationHelper::SAML_CASE_SENSITIVE,
			CAuthenticationHelper::PASSWD_MIN_LENGTH,
			CAuthenticationHelper::PASSWD_CHECK_RULES,
			CAuthenticationHelper::MFA_STATUS,
			CAuthenticationHelper::MFAID
		];

		$fields = [
			'authentication_type' => ZBX_AUTH_INTERNAL,
			'disabled_usrgrpid' => 0,
			'ldap_auth_enabled' => ZBX_AUTH_LDAP_DISABLED,
			'ldap_userdirectoryid' => $ldap_userdirectoryid,
			'saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED,
			'passwd_min_length' => DB::getDefault('config', 'passwd_min_length'),
			'passwd_check_rules' => DB::getDefault('config', 'passwd_check_rules'),
			'mfa_status' => MFA_DISABLED,
			'mfaid' => $mfaid
		];

		if ($ALLOW_HTTP_AUTH) {
			$auth_params = array_merge($auth_params, [
				CAuthenticationHelper::HTTP_AUTH_ENABLED,
				CAuthenticationHelper::HTTP_LOGIN_FORM,
				CAuthenticationHelper::HTTP_STRIP_DOMAINS,
				CAuthenticationHelper::HTTP_CASE_SENSITIVE
			]);

			$fields['http_auth_enabled'] = ZBX_AUTH_HTTP_DISABLED;

			if ($this->getInput('http_auth_enabled', ZBX_AUTH_HTTP_DISABLED) == ZBX_AUTH_HTTP_ENABLED) {
				$fields += [
					'http_case_sensitive' => 0,
					'http_login_form' => 0,
					'http_strip_domains' => ''
				];
			}
		}

		if ($this->getInput('ldap_auth_enabled', ZBX_AUTH_LDAP_DISABLED) == ZBX_AUTH_LDAP_ENABLED) {
			$fields += [
				'ldap_jit_status' => JIT_PROVISIONING_DISABLED,
				'ldap_case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE
			];

			if ($this->getInput('ldap_jit_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
				$fields['jit_provision_interval'] = DB::getDefault('config', 'jit_provision_interval');
			}
		}

		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) == ZBX_AUTH_SAML_ENABLED) {
			$fields += [
				'saml_case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE,
				'saml_jit_status' => JIT_PROVISIONING_DISABLED
			];
		}

		$auth = [];
		foreach ($auth_params as $param) {
			$auth[$param] = CAuthenticationHelper::get($param);
		}

		$data = $fields + $auth;
		$this->getInputs($data, array_keys($fields));

		$rules = $data['passwd_check_rules'];
		$data['passwd_check_rules'] = 0x00;

		foreach ($rules as $rule) {
			$data['passwd_check_rules'] |= $rule;
		}

		$data = array_diff_assoc($data, $auth);
		$result = true;

		if ($data) {
			$result = (bool) API::Authentication()->update($data);

			if ($result && array_key_exists('authentication_type', $data)) {
				$this->invalidateSessions();
			}

			CAuthenticationHelper::reset();
		}

		return $result;
	}

	/**
	 * Updates existing LDAP servers, creates new ones, removes deleted ones.
	 *
	 * @param array $ldap_servers
	 *
	 * @return array
	 */
	private function processLdapServers(array $ldap_servers): array {
		$ins_ldap_servers = [];
		$upd_ldap_servers = [];
		$userdirectoryid_map = [];

		foreach ($ldap_servers as $row_index => $ldap_server) {
			if (!array_key_exists('provision_status', $ldap_server)
					|| $ldap_server['provision_status'] != JIT_PROVISIONING_ENABLED) {
				$ldap_server = array_diff_key($ldap_server, array_flip(self::PROVISION_ENABLED_FIELDS));
			}

			if (array_key_exists('userdirectoryid', $ldap_server)) {
				$userdirectoryid_map[$row_index] = $ldap_server['userdirectoryid'];
				$upd_ldap_servers[] = $ldap_server + ['provision_media' => []];
			}
			else {
				$userdirectoryid_map[$row_index] = null;
				$ins_ldap_servers[] = ['idp_type' => IDP_TYPE_LDAP] + $ldap_server;
			}
		}

		$result = $upd_ldap_servers ? API::UserDirectory()->update($upd_ldap_servers) : [];
		$result = $result !== false && $ins_ldap_servers ? API::UserDirectory()->create($ins_ldap_servers) : $result;

		if ($result) {
			foreach ($userdirectoryid_map as $row_index => $userdirectoryid) {
				if ($userdirectoryid === null) {
					$userdirectoryid_map[$row_index] = array_shift($result['userdirectoryids']);
				}
			}

			return $userdirectoryid_map;
		}
		else {
			return [];
		}
	}

	/**
	 * Updates existing MFA methods, creates new ones, removes deleted ones.
	 *
	 * @param array $mfa_methods
	 *
	 * @return array
	 */
	private function processMfaMethods(array $mfa_methods): array {
		$ins_mfa_methods = [];
		$upd_mfa_methods = [];
		$mfaid_map = [];

		foreach ($mfa_methods as $row_index => $mfa_method) {
			if (array_key_exists('mfaid', $mfa_method)) {
				$mfaid_map[$row_index] = $mfa_method['mfaid'];
				$upd_mfa_methods[] = $mfa_method;
			}
			else {
				$mfaid_map[$row_index] = null;
				$ins_mfa_methods[] = $mfa_method;
			}
		}

		$result = $upd_mfa_methods ? API::Mfa()->update($upd_mfa_methods) : [];
		$result = ($result !== false && $ins_mfa_methods) ? API::Mfa()->create($ins_mfa_methods) : $result;

		if (!$result) {
			return [];
		}

		if ($ins_mfa_methods) {
			foreach ($mfaid_map as $row_index => $mfaid) {
				if ($mfaid === null) {
					$mfaid_map[$row_index] = array_shift($result['mfaids']);
				}
			}
		}

		return $mfaid_map;
	}

	/**
	 * Retrieves SAML configuration fields and creates or updates SAML configuration.
	 *
	 * @return bool
	 */
	private function processSamlConfiguration(): bool {
		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) != ZBX_AUTH_SAML_ENABLED) {
			return true;
		}

		$saml_data = [
			'idp_entityid' => '',
			'sso_url' => '',
			'slo_url' => '',
			'username_attribute' => '',
			'sp_entityid' => '',
			'nameid_format' => '',
			'sign_messages' => 0,
			'sign_assertions' => 0,
			'sign_authn_requests' => 0,
			'sign_logout_requests' => 0,
			'sign_logout_responses' => 0,
			'encrypt_nameid' => 0,
			'encrypt_assertions' => 0,
			'provision_status' => JIT_PROVISIONING_DISABLED,
			'scim_status' => ZBX_AUTH_SCIM_PROVISIONING_DISABLED
		];
		$this->getInputs($saml_data, array_keys($saml_data));

		if ($this->getInput('saml_provision_status', JIT_PROVISIONING_DISABLED) == JIT_PROVISIONING_ENABLED) {
			$provisioning_fields = [
				'saml_provision_status' => JIT_PROVISIONING_ENABLED,
				'saml_group_name' => '',
				'saml_user_username' => '',
				'saml_user_lastname' => '',
				'saml_provision_groups' => [],
				'saml_provision_media' => []
			];
			$this->getInputs($provisioning_fields, array_keys($provisioning_fields));
			$provisioning_fields = CArrayHelper::renameKeys($provisioning_fields, [
				'saml_group_name' => 'group_name',
				'saml_user_username' => 'user_username',
				'saml_user_lastname' => 'user_lastname',
				'saml_provision_status' => 'provision_status',
				'saml_provision_groups' => 'provision_groups',
				'saml_provision_media' => 'provision_media'
			]);
			$saml_data = array_merge($saml_data, $provisioning_fields);
		}

		$db_saml = API::UserDirectory()->get([
			'output' => ['userdirectoryid'],
			'filter' => ['idp_type' => IDP_TYPE_SAML]
		]);

		if ($db_saml) {
			$result = API::UserDirectory()->update(['userdirectoryid' => $db_saml[0]['userdirectoryid']] + $saml_data);
		}
		else {
			$result = API::UserDirectory()->create($saml_data + ['idp_type' => IDP_TYPE_SAML]);
		}

		return $result !== false;
	}

	/**
	 * Mark all active GROUP_GUI_ACCESS_INTERNAL sessions, except current user sessions, as ZBX_SESSION_PASSIVE.
	 *
	 * @return bool
	 */
	private function invalidateSessions() {
		$result = true;
		$internal_auth_user_groups = API::UserGroup()->get([
			'output' => [],
			'filter' => [
				'gui_access' => GROUP_GUI_ACCESS_INTERNAL
			],
			'preservekeys' => true
		]);

		$internal_auth_users = API::User()->get([
			'output' => [],
			'usrgrpids' => array_keys($internal_auth_user_groups),
			'preservekeys' => true
		]);
		unset($internal_auth_users[CWebUser::$data['userid']]);

		if ($internal_auth_users) {
			$result = DB::update('sessions', [
				'values' => ['status' => ZBX_SESSION_PASSIVE],
				'where' => ['userid' => array_keys($internal_auth_users)]
			]);
		}

		return $result;
	}

	private function validateProvisionGroups(array $provision_group): bool {
		foreach ($provision_group as $group) {
			if (!is_array($group)) {
				return false;
			}

			if (!array_key_exists('user_groups', $group) || !is_array($group['user_groups'])
					|| !array_key_exists('roleid', $group) || !ctype_digit($group['roleid'])
					|| !array_key_exists('name', $group) || !is_string($group['name']) || $group['name'] === '') {
				return false;
			}
		}

		return true;
	}

	private function validateProvisionMedia(array $provision_media): bool {
		if (!$provision_media) {
			return true;
		}

		foreach ($provision_media as $media) {
			if (!is_array($media)
					|| !array_key_exists('name', $media) || !is_string($media['name']) || $media['name'] === ''
					|| !array_key_exists('attribute', $media) || !is_string($media['attribute'])
					|| $media['attribute'] === ''
					|| !array_key_exists('mediatypeid', $media) || !ctype_digit($media['mediatypeid'])) {
				return false;
			}
		}

		return true;
	}
}
