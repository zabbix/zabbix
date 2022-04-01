<?php declare(strict_types = 1);
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


class CControllerPopupLdapEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'row_index' => 'required|int32',
			'name' => 'string',
			'host' => 'string',
			'port' => 'int32',
			'base_dn' => 'string',
			'search_attribute' => 'string',
			'userfilter' => 'string',
			'start_tls' => 'in 0,1',
			'bind_dn' => 'string',
			'bind_password' => 'string',
			'case_sensitive' => 'in 0,1',
			'description' => 'string',
			'ldap_configured' => 'in 0,1',
			'add_ldap_server' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$defaults = [
			'row_index' => 0,
			'name' => '',
			'host' => '',
			'port' => '',
			'base_dn' => '',
			'search_attribute' => '',
			'start_tls' => ZBX_AUTH_START_TLS_OFF,
			'bind_dn' => '',
			'bind_password' => '',
			'case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE,
			'description' => '',
			'userfilter' => '',
			'ldap_configured' => 0,
			'add_ldap_server' => 1
		];

		$this->getInputs($defaults, array_keys($defaults));

		$data = [
			'advanced_configuration' => ($defaults['start_tls'] == ZBX_AUTH_START_TLS_ON
				|| $defaults['userfilter'] !== ''
			),
			'is_password_set' => ($defaults['bind_password'] !== ''),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		] + $defaults;

		$this->setResponse(new CControllerResponseData($data));
	}
}
