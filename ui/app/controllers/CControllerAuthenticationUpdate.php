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


class CControllerAuthenticationUpdate extends CController {

	/**
	 * @var CControllerResponseRedirect
	 */
	private $response;

	protected function init() {
		$this->response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'authentication.edit')
			->getUrl()
		);
	}

	protected function checkInput() {
		$fields = [
			'form_refresh' =>				'string',
			'ldap_test_user' =>				'string',
			'ldap_test_password' =>			'string',
			'ldap_test' =>					'in 1',
			'db_authentication_type' =>		'int32',
			'change_bind_password' =>		'in 0,1',
			'authentication_type' =>		'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'http_case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_configured' =>			'in '.ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED,
			'ldap_host' =>					'db config.ldap_host',
			'ldap_port' =>					'int32',
			'ldap_base_dn' =>				'db config.ldap_base_dn',
			'ldap_bind_dn' =>				'db config.ldap_bind_dn',
			'ldap_search_attribute' =>		'db config.ldap_search_attribute',
			'ldap_bind_password' =>			'db config.ldap_bind_password',
			'http_auth_enabled' =>			'in '.ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED,
			'http_login_form' =>			'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
			'http_strip_domains' =>			'db config.http_strip_domains',
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
			'passwd_check_rules' =>			'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->response->setFormData($this->getInputAll());
			$this->setResponse($this->response);
		}

		return $ret;
	}

	/**
	 * Validate default authentication. Do not allow user to change default authentication to LDAP if LDAP is not
	 * configured.
	 *
	 * @return bool
	 */
	private function validateDefaultAuth() {
		$data = [
			'ldap_configured' => ZBX_AUTH_LDAP_DISABLED,
			'authentication_type' => ZBX_AUTH_INTERNAL
		];
		$this->getInputs($data, array_keys($data));

		$is_valid = ($data['authentication_type'] != ZBX_AUTH_LDAP
				|| $data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED);

		if (!$is_valid) {
			CMessageHelper::setErrorTitle(_s('Incorrect value for field "%1$s": %2$s.', 'authentication_type',
				_('LDAP is not configured')
			));
		}

		return $is_valid;
	}

	/**
	 * Validate LDAP settings.
	 *
	 * @return bool
	 */
	private function validateLdap() {
		$is_valid = true;
		$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();
		$ldap_fields = ['ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_search_attribute', 'ldap_configured'];
		$ldap_auth_original = [
			'ldap_host' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_HOST),
			'ldap_port' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_PORT),
			'ldap_base_dn' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_BASE_DN),
			'ldap_search_attribute' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_SEARCH_ATTRIBUTE),
			'ldap_configured' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_CONFIGURED),
			'ldap_bind_dn' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_BIND_DN),
			'ldap_bind_password' => CAuthenticationHelper::get(CAuthenticationHelper::LDAP_BIND_PASSWORD)
		];
		$ldap_auth = $ldap_auth_original;
		$this->getInputs($ldap_auth, array_merge($ldap_fields, ['ldap_bind_dn', 'ldap_bind_password']));
		$ldap_auth_changed = array_diff_assoc($ldap_auth, $ldap_auth_original);

		if (!$ldap_auth_changed && !$this->hasInput('ldap_test')) {
			return $is_valid;
		}

		if ($this->getInput('ldap_bind_password', '') !== '') {
			$ldap_fields[] = 'ldap_bind_dn';
		}

		foreach ($ldap_fields as $field) {
			if (trim($ldap_auth[$field]) === '') {
				CMessageHelper::setErrorTitle(_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty')));
				$is_valid = false;
				break;
			}
		}

		if ($is_valid
				&& ($ldap_auth['ldap_port'] < ZBX_MIN_PORT_NUMBER
					|| $ldap_auth['ldap_port'] > ZBX_MAX_PORT_NUMBER)) {
			CMessageHelper::setErrorTitle(_s(
				'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.', $this->getInput('ldap_port'),
				'ldap_port', ZBX_MIN_PORT_NUMBER, ZBX_MAX_PORT_NUMBER
			));
			$is_valid = false;
		}

		if ($ldap_status['result'] != CFrontendSetup::CHECK_OK) {
			CMessageHelper::setErrorTitle($ldap_status['error']);
			$is_valid = false;
		}
		elseif ($is_valid) {
			$ldap_validator = new CLdapAuthValidator([
				'conf' => [
					'host' => $ldap_auth['ldap_host'],
					'port' => $ldap_auth['ldap_port'],
					'base_dn' => $ldap_auth['ldap_base_dn'],
					'bind_dn' => $ldap_auth['ldap_bind_dn'],
					'bind_password' => $ldap_auth['ldap_bind_password'],
					'search_attribute' => $ldap_auth['ldap_search_attribute']
				],
				'detailed_errors' => true
			]);

			$login = $ldap_validator->validate([
				'username' => $this->getInput('ldap_test_user', CWebUser::$data['username']),
				'password' => $this->getInput('ldap_test_password', '')
			]);

			if (!$login) {
				CMessageHelper::setErrorTitle($ldap_validator->getError());
				$is_valid = false;
			}
		}

		return $is_valid;
	}

	/**
	 * Validate SAML authentication settings.
	 *
	 * @return bool
	 */
	private function validateSamlAuth() {
		$openssl_status = (new CFrontendSetup())->checkPhpOpenSsl();

		if ($openssl_status['result'] != CFrontendSetup::CHECK_OK) {
			CMessageHelper::setErrorTitle($openssl_status['error']);

			return false;
		}

		$saml_fields = ['saml_idp_entityid', 'saml_sso_url', 'saml_sp_entityid', 'saml_username_attribute'];
		$saml_auth = [
			'saml_idp_entityid' => CAuthenticationHelper::get(CAuthenticationHelper::SAML_IDP_ENTITYID),
			'saml_sso_url' => CAuthenticationHelper::get(CAuthenticationHelper::SAML_SSO_URL),
			'saml_sp_entityid' => CAuthenticationHelper::get(CAuthenticationHelper::SAML_SP_ENTITYID),
			'saml_username_attribute' => CAuthenticationHelper::get(CAuthenticationHelper::SAML_USERNAME_ATTRIBUTE)
		];
		$this->getInputs($saml_auth, $saml_fields);

		foreach ($saml_fields as $field) {
			if (trim($saml_auth[$field]) === '') {
				CMessageHelper::setErrorTitle(_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty')));

				return false;
			}
		}

		return true;
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
		$auth_valid = ($this->getInput('ldap_configured', '') == ZBX_AUTH_LDAP_ENABLED)
			? $this->validateLdap()
			: $this->validateDefaultAuth();

		if ($auth_valid && $this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) == ZBX_AUTH_SAML_ENABLED) {
			if (!$this->validateSamlAuth()) {
				$auth_valid = false;
			}
		}

		if (!$auth_valid) {
			$this->response->setFormData($this->getInputAll());
			$this->setResponse($this->response);

			return;
		}

		// Only ZBX_AUTH_LDAP have 'Test' option.
		if ($this->hasInput('ldap_test')) {
			CMessageHelper::setSuccessTitle(_('LDAP login successful'));
			$this->response->setFormData($this->getInputAll());
			$this->setResponse($this->response);

			return;
		}

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

		$fields = [
			'authentication_type' => ZBX_AUTH_INTERNAL,
			'ldap_configured' => ZBX_AUTH_LDAP_DISABLED,
			'http_auth_enabled' => ZBX_AUTH_HTTP_DISABLED,
			'saml_auth_enabled' => ZBX_AUTH_SAML_DISABLED,
			'passwd_min_length' => DB::getDefault('config', 'passwd_min_length'),
			'passwd_check_rules' => DB::getDefault('config', 'passwd_check_rules')
		];

		if ($this->getInput('http_auth_enabled', ZBX_AUTH_HTTP_DISABLED) == ZBX_AUTH_HTTP_ENABLED) {
			$fields += [
				'http_case_sensitive' => 0,
				'http_login_form' => 0,
				'http_strip_domains' => ''
			];
		}

		if ($this->getInput('ldap_configured', ZBX_AUTH_LDAP_DISABLED) == ZBX_AUTH_LDAP_ENABLED) {
			$fields += [
				'ldap_host' => '',
				'ldap_port' => '',
				'ldap_base_dn' => '',
				'ldap_bind_dn' => '',
				'ldap_search_attribute' => '',
				'ldap_case_sensitive' => 0
			];

			if ($this->hasInput('ldap_bind_password')) {
				$fields['ldap_bind_password'] = '';
			}
			else {
				unset($auth[CAuthenticationHelper::LDAP_BIND_PASSWORD]);
			}
		}

		if ($this->getInput('saml_auth_enabled', ZBX_AUTH_SAML_DISABLED) == ZBX_AUTH_SAML_ENABLED) {
			$fields += [
				'saml_idp_entityid' => '',
				'saml_sso_url' => '',
				'saml_slo_url' => '',
				'saml_username_attribute' => '',
				'saml_sp_entityid' => '',
				'saml_nameid_format' => '',
				'saml_sign_messages' => 0,
				'saml_sign_assertions' => 0,
				'saml_sign_authn_requests' => 0,
				'saml_sign_logout_requests' => 0,
				'saml_sign_logout_responses' => 0,
				'saml_encrypt_nameid' => 0,
				'saml_encrypt_assertions' => 0,
				'saml_case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE
			];
		}

		$data = $fields + $auth;
		$this->getInputs($data, array_keys($fields));

		$rules = $data['passwd_check_rules'];
		$data['passwd_check_rules'] = 0x00;

		foreach ($rules as $rule) {
			$data['passwd_check_rules'] |= $rule;
		}

		$data = array_diff_assoc($data, $auth);

		if (array_key_exists('ldap_bind_dn', $data) && trim($data['ldap_bind_dn']) === '') {
			$data['ldap_bind_password'] = '';
		}

		if ($data) {
			$result = API::Authentication()->update($data);

			if ($result) {
				if (array_key_exists('authentication_type', $data)) {
					$this->invalidateSessions();
				}

				CMessageHelper::setSuccessTitle(_('Authentication settings updated'));
			}
			else {
				$this->response->setFormData($this->getInputAll());
				CMessageHelper::setErrorTitle(_('Cannot update authentication'));
			}
		}
		else {
			CMessageHelper::setSuccessTitle(_('Authentication settings updated'));
		}

		$this->setResponse($this->response);
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
			DBstart();
			$result = DB::update('sessions', [
				'values' => ['status' => ZBX_SESSION_PASSIVE],
				'where' => ['userid' => array_keys($internal_auth_users)]
			]);
			$result = DBend($result);
		}

		return $result;
	}
}
