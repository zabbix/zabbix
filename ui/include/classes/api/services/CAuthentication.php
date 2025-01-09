<?php declare(strict_types = 0);
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


/**
 * Class containing methods for operations with authentication parameters.
 */
class CAuthentication extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'config';
	protected $tableAlias = 'c';

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function get(array $options): array {
		$output_fields = self::getOutputFields();

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $output_fields;
		}

		$db_auth = [];

		$result = DBselect($this->createSelectQuery($this->tableName(), $options));
		while ($row = DBfetch($result)) {
			$db_auth[] = $row;
		}
		$db_auth = $this->unsetExtraFields($db_auth, ['configid'], []);

		return $db_auth[0];
	}

	/**
	 * Get the fields of the Authentication API object that are used by parts of the UI where authentication is not
	 * required.
	 */
	public static function getPublic(): array {
		global $ALLOW_HTTP_AUTH;

		$output_fields = ['authentication_type', 'http_auth_enabled', 'http_login_form', 'http_strip_domains',
			'http_case_sensitive', 'saml_auth_enabled', 'saml_case_sensitive', 'saml_jit_status', 'disabled_usrgrpid',
			'mfa_status', 'mfaid', 'ldap_userdirectoryid'
		];

		$db_auth = DB::select('config', ['output' => $output_fields])[0];

		if (!$ALLOW_HTTP_AUTH) {
			$db_auth['http_auth_enabled'] = (string) ZBX_AUTH_HTTP_DISABLED;
		}

		return $db_auth;
	}

	/**
	 * @param array $auth
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function update(array $auth): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'authentication', __FUNCTION__)
			);
		}

		$db_auth = $this->validateUpdate($auth);

		$upd_config = DB::getUpdatedValues('config', $auth, $db_auth);

		if ($upd_config) {
			DB::update('config', [
				'values' => $upd_config,
				'where' => ['configid' => $db_auth['configid']]
			]);
		}

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_AUTHENTICATION,
			[['configid' => $db_auth['configid']] + $auth], [$db_auth['configid'] => $db_auth]
		);

		return array_keys($auth);
	}

	/**
	 * @param array  $auth
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	protected function validateUpdate(array $auth): array {
		global $ALLOW_HTTP_AUTH;

		$db_auth = DB::select('config', ['output' => array_merge(['configid'], self::getOutputFields())])[0];
		$auth += array_diff_key($db_auth, array_flip(['configid']));

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'authentication_type' =>		['type' => API_INT32, 'in' => ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP],
			'ldap_auth_enabled' =>			['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'authentication_type', 'in' => ZBX_AUTH_LDAP], 'type' => API_INT32, 'in' => ZBX_AUTH_LDAP_ENABLED],
												['else' => true, 'type' => API_INT32, 'in' => ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED]
			]],
			'ldap_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'ldap_userdirectoryid' =>		['type' => API_ID],
			'saml_auth_enabled' =>			['type' => API_INT32, 'in' => ZBX_AUTH_SAML_DISABLED.','.ZBX_AUTH_SAML_ENABLED],
			'saml_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'passwd_min_length' =>			['type' => API_INT32, 'in' => '1:70', 'default' => DB::getDefault('config', 'passwd_min_length')],
			'passwd_check_rules' =>			['type' => API_INT32, 'in' => '0:'.(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE), 'default' => DB::getDefault('config', 'passwd_check_rules')],
			'disabled_usrgrpid' =>			['type' => API_ID],
			'jit_provision_interval' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR])],
			'saml_jit_status' =>			['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
			'ldap_jit_status' =>			['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
			'mfa_status' =>					['type' => API_INT32, 'in' => implode(',', [MFA_DISABLED, MFA_ENABLED])],
			'mfaid' =>						['type' => API_ID]
		]];

		if ($ALLOW_HTTP_AUTH) {
			$api_input_rules['fields'] += [
				'http_auth_enabled' =>		['type' => API_INT32, 'in' => ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED],
				'http_login_form' =>		['type' => API_INT32, 'in' => ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP],
				'http_strip_domains' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'http_strip_domains')],
				'http_case_sensitive' =>	['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE]
			];
		}

		if (!CApiInputValidator::validate($api_input_rules, $auth, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkUserDirectoryid($auth, $db_auth);

		// Check if deprovisioning user group exists and is set properly.
		if ($auth['disabled_usrgrpid']) {
			$groups = API::UserGroup()->get([
				'output' => ['users_status'],
				'usrgrpids' => [$auth['disabled_usrgrpid']]
			]);

			if (!$groups) {
				static::exception(ZBX_API_ERROR_PERMISSIONS,
					_('No permissions to referred object or it does not exist!')
				);
			}

			if ($groups[0]['users_status'] != GROUP_STATUS_DISABLED) {
				static::exception(ZBX_API_ERROR_PARAMETERS, _('Deprovisioned users group cannot be enabled.'));
			}
		}
		else {
			$ldap_jit_enabled = $auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED
				&& $auth['ldap_jit_status'] == JIT_PROVISIONING_ENABLED;
			$saml_jit_enabled = $auth['saml_auth_enabled'] == ZBX_AUTH_SAML_ENABLED
				&& $auth['saml_jit_status'] == JIT_PROVISIONING_ENABLED;

			if ($ldap_jit_enabled || $saml_jit_enabled) {
				static::exception(ZBX_API_ERROR_PARAMETERS, _('Deprovisioned users group cannot be empty.'));
			}
		}

		self::checkMfaExists($auth, $db_auth);
		self::checkMfaid($auth, $db_auth);

		return $db_auth;
	}

	private static function checkUserDirectoryid(array $auth, array $db_auth): void {
		if ($auth['ldap_userdirectoryid'] != 0) {
			if (bccomp($auth['ldap_userdirectoryid'], $db_auth['ldap_userdirectoryid']) != 0) {
				$default_ldap_exists = API::UserDirectory()->get([
					'output' => [],
					'userdirectoryids' => [$auth['ldap_userdirectoryid']],
					'filter' => ['idp_type' => IDP_TYPE_LDAP]
				]);

				if (!$default_ldap_exists) {
					static::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', '/ldap_userdirectoryid', _('object does not exist'))
					);
				}
			}
		}
		elseif ($auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Default LDAP server must be specified.'));
		}
	}

	private static function checkMfaExists(array $auth, array $db_auth): void {
		if ($auth['mfa_status'] == MFA_ENABLED) {
			$mfa_count = DB::select('mfa', ['countOutput' => true]);

			if ($mfa_count == 0) {
				static::exception(ZBX_API_ERROR_PARAMETERS, _('At least one MFA method must exist.'));
			}
		}
	}

	private static function checkMfaid(array $auth, array $db_auth): void {
		$mfaid_changed = bccomp($auth['mfaid'], $db_auth['mfaid']) != 0;

		if (($auth['mfa_status'] == MFA_DISABLED && $auth['mfaid'] != 0 && $mfaid_changed)
				|| ($auth['mfa_status'] == MFA_ENABLED && ($mfaid_changed || $auth['mfaid'] == 0))) {
			$db_mfas = DB::select('mfa', [
				'output' => ['mfaid'],
				'mfaids' => $auth['mfaid']
			]);

			if (!$db_mfas) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', '/mfaid',
					_('object does not exist')
				));
			}
		}
	}

	/**
	 * Gets output fields based on if HTTP authentication support is enabled or not.
	 *
	 * @return array
	 */
	public static function getOutputFields(): array {
		global $ALLOW_HTTP_AUTH;

		$output_fields = ['authentication_type', 'ldap_auth_enabled', 'ldap_case_sensitive', 'ldap_userdirectoryid',
			'saml_auth_enabled', 'saml_case_sensitive', 'passwd_min_length', 'passwd_check_rules', 'disabled_usrgrpid',
			'jit_provision_interval', 'saml_jit_status', 'ldap_jit_status', 'mfa_status', 'mfaid'
		];

		$http_output_fields = ['http_auth_enabled', 'http_login_form', 'http_strip_domains', 'http_case_sensitive'];

		return $ALLOW_HTTP_AUTH ? array_merge($output_fields, $http_output_fields) : $output_fields;
	}
}
