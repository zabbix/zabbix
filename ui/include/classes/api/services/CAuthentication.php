<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
	 * @var array
	 */
	private $output_fields = ['authentication_type', 'http_auth_enabled', 'http_login_form', 'http_strip_domains',
		'http_case_sensitive', 'ldap_auth_enabled', 'ldap_case_sensitive', 'ldap_userdirectoryid', 'saml_auth_enabled',
		'saml_case_sensitive', 'passwd_min_length', 'passwd_check_rules', 'jit_provision_interval', 'saml_jit_status',
		'ldap_jit_status', 'disabled_usrgrpid'
	];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array
	 */
	public function get(array $options): array {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'output' =>	['type' => API_OUTPUT, 'in' => implode(',', $this->output_fields), 'default' => API_OUTPUT_EXTEND]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if ($options['output'] === API_OUTPUT_EXTEND) {
			$options['output'] = $this->output_fields;
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
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'authentication_type' =>		['type' => API_INT32, 'in' => ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP],
			'http_auth_enabled' =>			['type' => API_INT32, 'in' => ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED],
			'http_login_form' =>			['type' => API_INT32, 'in' => ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP],
			'http_strip_domains' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'http_strip_domains')],
			'http_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'ldap_auth_enabled' =>			['type' => API_INT32, 'in' => ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED],
			'ldap_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'ldap_userdirectoryid' =>		['type' => API_ID],
			'saml_auth_enabled' =>			['type' => API_INT32, 'in' => ZBX_AUTH_SAML_DISABLED.','.ZBX_AUTH_SAML_ENABLED],
			'saml_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'passwd_min_length' =>			['type' => API_INT32, 'in' => '1:70', 'default' => DB::getDefault('config', 'passwd_min_length')],
			'passwd_check_rules' =>			['type' => API_INT32, 'in' => '0:'.(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE), 'default' => DB::getDefault('config', 'passwd_check_rules')],
			'disabled_usrgrpid' =>			['type' => API_ID],
			'jit_provision_interval' =>		['type' => API_TIME_UNIT, 'flags' => API_NOT_EMPTY | API_TIME_UNIT_WITH_YEAR, 'in' => implode(':', [SEC_PER_HOUR, 25 * SEC_PER_YEAR])],
			'saml_jit_status' =>			['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])],
			'ldap_jit_status' =>			['type' => API_INT32, 'in' => implode(',', [JIT_PROVISIONING_DISABLED, JIT_PROVISIONING_ENABLED])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $auth, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$output_fields = $this->output_fields;
		$output_fields[] = 'configid';

		$db_auth = DB::select('config', ['output' => $output_fields]);
		$db_auth = reset($db_auth);
		$auth += $db_auth;

		// Check if at least one LDAP server exists.
		if ($auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_ENABLED) {
			$ldap_servers_exists = (bool) API::UserDirectory()->get([
				'countOutput' => true,
				'filter' => ['idp_type' => IDP_TYPE_LDAP]
			]);

			if (!$ldap_servers_exists) {
				static::exception(ZBX_API_ERROR_PARAMETERS, _('At least one LDAP server must exist.'));
			}
		}

		// Check if default LDAP server exists.
		if ($auth['ldap_userdirectoryid'] != 0) {
			$default_ldap_exists = (bool) API::UserDirectory()->get([
				'countOutput' => true,
				'userdirectoryids' => [$auth['ldap_userdirectoryid']],
				'filter' => ['idp_type' => IDP_TYPE_LDAP]
			]);

			if (!$default_ldap_exists) {
				static::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/ldap_userdirectoryid', _('referred object does not exist'))
				);
			}
		}

		// Check if no disabled LDAP is set as default authentication method.
		if ($auth['authentication_type'] == ZBX_AUTH_LDAP && $auth['ldap_auth_enabled'] == ZBX_AUTH_LDAP_DISABLED) {
			static::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Incorrect value for field "%1$s": %2$s.', '/authentication_type', _('LDAP must be enabled'))
			);
		}

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

		return $db_auth;
	}
}
