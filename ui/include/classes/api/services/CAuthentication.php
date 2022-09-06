<?php declare(strict_types = 0);
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
		'http_case_sensitive', 'ldap_configured', 'ldap_case_sensitive', 'ldap_userdirectoryid', 'saml_auth_enabled',
		'saml_idp_entityid', 'saml_sso_url', 'saml_slo_url', 'saml_username_attribute', 'saml_sp_entityid',
		'saml_nameid_format', 'saml_sign_messages', 'saml_sign_assertions', 'saml_sign_authn_requests',
		'saml_sign_logout_requests', 'saml_sign_logout_responses', 'saml_encrypt_nameid', 'saml_encrypt_assertions',
		'saml_case_sensitive', 'passwd_min_length', 'passwd_check_rules'
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
			'ldap_configured' =>			['type' => API_INT32, 'in' => ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED],
			'ldap_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'ldap_userdirectoryid' =>		['type' => API_ID],
			'saml_auth_enabled' =>			['type' => API_INT32, 'in' => ZBX_AUTH_SAML_DISABLED.','.ZBX_AUTH_SAML_ENABLED],
			'saml_idp_entityid' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'saml_idp_entityid')],
			'saml_sso_url' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'saml_sso_url')],
			'saml_slo_url' =>				['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'saml_slo_url')],
			'saml_username_attribute' =>	['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'saml_username_attribute')],
			'saml_sp_entityid' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('config', 'saml_sp_entityid')],
			'saml_nameid_format' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('config', 'saml_nameid_format')],
			'saml_sign_messages' =>			['type' => API_INT32, 'in' => '0,1'],
			'saml_sign_assertions' =>		['type' => API_INT32, 'in' => '0,1'],
			'saml_sign_authn_requests' =>	['type' => API_INT32, 'in' => '0,1'],
			'saml_sign_logout_requests' =>	['type' => API_INT32, 'in' => '0,1'],
			'saml_sign_logout_responses' =>	['type' => API_INT32, 'in' => '0,1'],
			'saml_encrypt_nameid' =>		['type' => API_INT32, 'in' => '0,1'],
			'saml_encrypt_assertions' =>	['type' => API_INT32, 'in' => '0,1'],
			'saml_case_sensitive' =>		['type' => API_INT32, 'in' => ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE],
			'passwd_min_length' =>			['type' => API_INT32, 'in' => '1:70', 'default' => DB::getDefault('config', 'passwd_min_length')],
			'passwd_check_rules' =>			['type' => API_INT32, 'in' => '0:'.(PASSWD_CHECK_CASE | PASSWD_CHECK_DIGITS | PASSWD_CHECK_SPECIAL | PASSWD_CHECK_SIMPLE), 'default' => DB::getDefault('config', 'passwd_check_rules')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $auth, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		if (array_key_exists('ldap_userdirectoryid', $auth) && $auth['ldap_userdirectoryid'] != 0) {
			$exists = (bool) API::UserDirectory()->get(['userdirectoryids' => $auth['ldap_userdirectoryid']]);

			if (!$exists) {
				static::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', '/ldap_userdirectoryid', _('referred object does not exist'))
				);
			}
		}

		$output_fields = $this->output_fields;
		$output_fields[] = 'configid';

		$db_auth = DB::select('config', ['output' => $output_fields]);
		$db_auth = reset($db_auth);

		if (array_key_exists('authentication_type', $auth)) {
			$auth += $db_auth;

			if ($auth['authentication_type'] == ZBX_AUTH_LDAP
					&& $auth['ldap_configured'] == ZBX_AUTH_LDAP_DISABLED) {
				static::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', '/authentication_type', _('LDAP must be enabled'))
				);
			}

			$have_userdirectories = $auth['ldap_userdirectoryid'] != 0
				|| API::UserDirectory()->get(['output' => ['userdirectoryid'], 'limit' => 1]);

			if (!$have_userdirectories) {
				static::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Incorrect value for field "%1$s": %2$s.', '/authentication_type', _('no LDAP servers'))
				);
			}
		}

		return $db_auth;
	}
}
