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


class CControllerPopupLdapTestEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'host' => 'required|string',
			'port' => 'required|int32',
			'base_dn' => 'required|string',
			'search_attribute' => 'required|string',
			'bind_dn' => 'string',
			'bind_password' => 'string',
			'case_sensitive' => 'in 0,1',
			'userfilter' => 'string',
			'start_tls' => 'in 0,1'
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

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {
		$ldap_config = [
			'host' => '',
			'port' => '',
			'base_dn' => '',
			'search_attribute' => '',
			'bind_dn' => '',
			'bind_password' => '',
			'case_sensitive' => ZBX_AUTH_CASE_INSENSITIVE,
			'userfilter' => '',
			'start_tls' => ZBX_AUTH_START_TLS_OFF
		];

		$this->getInputs($ldap_config, array_keys($ldap_config));

		$data = [
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'test_user' => CWebUser::$data['username'],
			'ldap_config' => $ldap_config
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
