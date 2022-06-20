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


class CControllerPopupLdapTestEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'userdirectoryid' =>	'db userdirectory.userdirectoryid',
			'host' =>				'required|db userdirectory.host|not_empty',
			'port' =>				'required|db userdirectory.port|ge '.ZBX_MIN_PORT_NUMBER.'|le '.ZBX_MAX_PORT_NUMBER,
			'base_dn' =>			'required|db userdirectory.base_dn|not_empty',
			'bind_dn' =>			'db userdirectory.bind_dn',
			'bind_password' =>		'db userdirectory.bind_password',
			'search_attribute' =>	'required|db userdirectory.search_attribute|not_empty',
			'start_tls' =>			'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'search_filter' =>		'db userdirectory.search_filter'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid LDAP configuration'),
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$data = [
			'ldap_config' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'test_username' => CWebUser::$data['username']
		];

		$this->getInputs($data['ldap_config'], ['userdirectoryid', 'host', 'port', 'base_dn', 'bind_dn',
			'bind_password', 'search_attribute', 'start_tls', 'search_filter','test_username', 'test_password'
		]);

		$this->setResponse(new CControllerResponseData($data));
	}
}
