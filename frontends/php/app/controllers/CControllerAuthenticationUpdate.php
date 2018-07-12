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
			->setArgument('action', 'administration.auth.edit')
			->getUrl()
		);

		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'user' => 'string',
			'user_password' => 'string',
			'test' => 'in 1',
			'authentication_type' => 'in '.ZBX_AUTH_INTERNAL.','.ZBX_AUTH_LDAP,
			'login_case_sensitive' => 'in 0,'.ZBX_AUTH_CASE_MATCH,
			'ldap_host' => 'db config.ldap_host',
			'ldap_port' => 'int32',
			'ldap_base_dn' => 'db config.ldap_base_dn',
			'ldap_bind_dn' => 'db config.ldap_bind_dn',
			'ldap_search_attribute' => 'db config.ldap_search_attribute',
			'ldap_bind_password' => 'db config.ldap_bind_password',
			'http_auth_enabled' => 'in 0,'.ZBX_AUTH_HTTP_ENABLED,
			'http_login_form' => 'in '.ZBX_AUTH_FORM_ZABBIX.','.ZBX_AUTH_FORM_HTTP,
			'http_strip_domains' => 'db config.http_strip_domains'
		];

		$ret = $this->validateInput($fields);
		/*
			Validate LDAP settings if there are groups with GROUP_GUI_ACCESS_LDAP or system default authentication
			method is ZBX_AUTH_LDAP. Method will set errors, if any exists, on $response property.
		*/
		$ret = $ret && $this->validateLdap();

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
		$isvalid = true;
		$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();
		$ldap_fields = ['ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_bind_dn', 'ldap_search_attribute',
			'ldap_bind_password'
		];
		$config = select_config();
		$this->getInputs($config, $ldap_fields);

		if (!$this->hasInput('test')) {
			// Do not validate LDAP connection if there was no changes in LDAP settings.
			$ldap_settings_changed = array_diff_assoc($config, select_config());

			if (!$ldap_settings_changed) {
				return $isvalid;
			}

			$ldap_groups_count = (int) API::UserGroup()->get([
				'output' => [],
				'filter' => [
					'gui_access' => GROUP_GUI_ACCESS_LDAP
				],
				'countOutput' => true
			]);

			if ($this->getInput('authentication_type', ZBX_AUTH_INTERNAL) != ZBX_AUTH_LDAP && $ldap_groups_count === 0) {
				return $isvalid;
			}
		}

		foreach($ldap_fields as $field) {
			if (trim($config[$field]) == '') {
				$this->response->setMessageError(_s('Incorrect value for field "%1$s": cannot be empty.', $field));
				$isvalid = false;
				break;
			}
		}

		if($isvalid && ($config['ldap_port'] < 0 || $config['ldap_port'] > 65535)) {
			$this->response->setMessageError(_s(
				'Incorrect value "%1$s" for "%2$s" field: must be between %3$s and %4$s.', $this->getInput('ldap_port'),
				'ldap_port', 0, 65535
			));
			$isvalid = false;
		}

		if ($ldap_status['result'] != CFrontendSetup::CHECK_OK) {
			$this->response->setMessageError($ldap_status['error']);
			$isvalid = false;
		}
		else if ($isvalid) {
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
				'user' => $this->getInput('user', CWebUser::$data['alias']),
				'password' => $this->getInput('user_password', '')
			]);

			if (!$login) {
				$this->response->setMessageError($this->hasInput('test')
					? _('LDAP login was not successful')
					: _('Login name or password is incorrect!')
				);
				$isvalid = false;
			}
		}

		return $isvalid;
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
			'login_case_sensitive' => 0,
			'http_auth_enabled' => 0
		];

		$this->getInputs($data, [
			'authentication_type',
			'login_case_sensitive',
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

		if ($this->hasInput('test')) {
			// Only ZBX_AUTH_LDAP have'Test' option.
			$this->response->setMessageOk(_('LDAP login successful'));
		}
		else if ($data) {
			$config = select_config();
			$result = update_config($data);

			if ($result) {
				if ($config['authentication_type'] != $data['authentication_type']) {
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
