<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
			'form_refresh' => 'string',
			'ldap_test_user' => 'string',
			'ldap_test_password' => 'string',
			'change_bind_password' => 'in 0,1',
			'db_authentication_type' => 'string',
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
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction() {
		$ldap_status = (new CFrontendSetup())->checkPhpLdapModule();

		$data = [
			'action_submit' => 'authentication.update',
			'action_passw_change' => 'authentication.edit',
			'ldap_error' => ($ldap_status['result'] == CFrontendSetup::CHECK_OK) ? '' : $ldap_status['error'],
			'ldap_test_password' => '',
			'ldap_test_user' => CWebUser::$data['alias'],
			'change_bind_password' => 0,
			'form_refresh' => 0
		];

		if ($this->hasInput('form_refresh')) {
			$data['ldap_bind_password'] = '';
			$this->getInputs($data, [
				'form_refresh',
				'change_bind_password',
				'db_authentication_type',
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
				'ldap_test_user',
				'ldap_test_password',
				'http_auth_enabled',
				'http_login_form',
				'http_strip_domains'
			]);

			$data += select_config();
		}
		else {
			$data += select_config();
			$data['db_authentication_type'] = $data['authentication_type'];
			$data['change_bind_password'] = ($data['ldap_bind_password'] === '') ? 1 : 0;
		}

		$data['ldap_enabled'] = ($ldap_status['result'] == CFrontendSetup::CHECK_OK
				&& $data['ldap_configured'] == ZBX_AUTH_LDAP_ENABLED);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of authentication'));
		$this->setResponse($response);
	}
}
