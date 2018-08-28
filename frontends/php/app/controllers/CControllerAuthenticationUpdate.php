<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'form_refresh' => 'string',
			'ldap_test_user' => 'string',
			'ldap_test_password' => 'string',
			'ldap_test' => 'in 1',
			'change_bind_password' => 'in 0,1',
			'authentication_type' => 'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'http_case_sensitive' => 'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_case_sensitive' => 'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'ldap_configured' => 'in '.ZBX_AUTH_LDAP_DISABLED.','.ZBX_AUTH_LDAP_ENABLED,
			'ldap_host' => 'db config.ldap_host',
			'ldap_port' => 'int32',
			'ldap_base_dn' => 'db config.ldap_base_dn',
			'ldap_bind_dn' => 'db config.ldap_bind_dn',
			'ldap_search_attribute' => 'db config.ldap_search_attribute',
			'ldap_bind_password' => 'db config.ldap_bind_password',
			'http_auth_enabled' => 'in '.ZBX_AUTH_HTTP_DISABLED.','.ZBX_AUTH_HTTP_ENABLED,
			'http_login_form' => 'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
			'http_strip_domains' => 'db config.http_strip_domains'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('ldap_configured', '') == ZBX_AUTH_LDAP_ENABLED) {
			$ret = $this->validateLdap();
		}

		if (!$ret) {
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
	private function validateLdap() {
		$is_valid = true;
		$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();
		$ldap_fields = ['ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_bind_dn', 'ldap_search_attribute',
			'ldap_bind_password', 'ldap_configured'
		];
		$config = select_config();
		$this->getInputs($config, $ldap_fields);
		$ldap_settings_changed = array_diff_assoc($config, select_config());

		if (!$ldap_settings_changed && !$this->hasInput('ldap_test')) {
			return $is_valid;
		}

		foreach($ldap_fields as $field) {
			if (trim($config[$field]) === '') {
				$this->response->setMessageError(_s('Incorrect value for field "%1$s": cannot be empty.', $field));
				$is_valid = false;
				break;
			}
		}

		if ($is_valid && ($config['ldap_port'] < 0 || $config['ldap_port'] > 65535)) {
			$this->response->setMessageError(_s(
				'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.', $this->getInput('ldap_port'),
				'ldap_port', 0, 65535
			));
			$is_valid = false;
		}

		if ($ldap_status['result'] != CFrontendSetup::CHECK_OK) {
			$this->response->setMessageError($ldap_status['error']);
			$is_valid = false;
		}
		elseif ($is_valid) {
			$ldap_validator = new CLdapAuthValidator([
				'conf' => [
					'host' => $config['ldap_host'],
					'port' => $config['ldap_port'],
					'base_dn' => $config['ldap_base_dn'],
					'bind_dn' => $config['ldap_bind_dn'],
					'bind_password' => $config['ldap_bind_password'],
					'search_attribute' => $config['ldap_search_attribute']
				]
			]);

			$login = $ldap_validator->validate([
				'user' => $this->getInput('ldap_test_user', CWebUser::$data['alias']),
				'password' => $this->getInput('ldap_test_password', '')
			]);

			if (!$login) {
				$this->response->setMessageError($this->hasInput('test')
					? _('LDAP login was not successful')
					: _('Login name or password is incorrect!')
				);
				$is_valid = false;
			}
		}

		return $is_valid;
	}

	/**
	 * Validate is user allowed to change configuration.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction() {
		$data = [
			'http_case_sensitive' => 0,
			'http_auth_enabled' => 0,
			'ldap_configured' => 0,
			'ldap_case_sensitive' => 0,
		];

		$this->getInputs($data, [
			'authentication_type',
			'http_case_sensitive',
			'ldap_case_sensitive',
			'ldap_configured',
			'ldap_host',
			'ldap_port',
			'ldap_base_dn',
			'ldap_bind_dn',
			'ldap_search_attribute',
			'ldap_bind_password',
			'http_auth_enabled',
			'http_login_form',
			'http_strip_domains'
		]);

		if ($data['ldap_configured'] != ZBX_AUTH_LDAP_ENABLED) {
			$data = array_merge($data, [
				'ldap_host' => '',
				'ldap_port' => '389',
				'ldap_base_dn' => '',
				'ldap_bind_dn' => '',
				'ldap_search_attribute' => '',
				'ldap_bind_password' => '',
			]);
		}

		if ($this->hasInput('ldap_test')) {
			// Only ZBX_AUTH_LDAP have 'Test' option.
			$this->response->setMessageOk(_('LDAP login successful'));
			$this->response->setFormData($this->getInputAll());
		}
		else {
			$config = select_config();
			$data = array_diff_assoc($data, $config);

			if ($data) {
				$result = update_config($data);

				if ($result) {
					if (array_key_exists('authentication_type', $data)
							&& $config['authentication_type'] != $data['authentication_type']) {
						$this->invalidateSessions();
					}

					$this->response->setMessageOk(_('Authentication settings updated'));
					add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, _('Authentication method changed'));
				}
				else {
					$this->response->setFormData($this->getInputAll());
					$this->response->setMessageError(_('Cannot update authentication'));
				}
			}
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
