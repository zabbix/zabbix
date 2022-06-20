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


class CControllerPopupLdapEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>			'required|int32',
			'userdirectoryid' =>	'db userdirectory.userdirectoryid',
			'name' =>				'db userdirectory.name',
			'host' =>				'db userdirectory.host',
			'port' =>				'db userdirectory.port|ge '.ZBX_MIN_PORT_NUMBER.'|le '.ZBX_MAX_PORT_NUMBER,
			'base_dn' =>			'db userdirectory.base_dn',
			'bind_dn' =>			'db userdirectory.bind_dn',
			'bind_password' =>		'db userdirectory.bind_password',
			'search_attribute' =>	'db userdirectory.search_attribute',
			'start_tls' =>			'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'search_filter' =>		'db userdirectory.search_filter',
			'case_sensitive' =>		'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
			'description' =>		'db userdirectory.description',
			'add_ldap_server' =>	'in 0,1'
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
		$data = [
			'row_index' => $this->getInput('row_index', 0),
			'name' => $this->getInput('name', ''),
			'host' => $this->getInput('host', ''),
			'port' => $this->getInput('port', '389'),
			'base_dn' => $this->getInput('base_dn', ''),
			'search_attribute' => $this->getInput('search_attribute', ''),
			'start_tls' => $this->getInput('start_tls', ZBX_AUTH_START_TLS_OFF),
			'bind_dn' => $this->getInput('bind_dn', ''),
			'description' => $this->getInput('description', ''),
			'search_filter' => $this->getInput('search_filter', ''),
			'add_ldap_server' => $this->getInput('add_ldap_server', 1),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		if ($this->hasInput('userdirectoryid')) {
			$data['userdirectoryid'] = $this->getInput('userdirectoryid');
		}

		if ($this->hasInput('bind_password')) {
			$data['bind_password'] = $this->getInput('bind_password');
		}

		$data['advanced_configuration'] = $data['start_tls'] != ZBX_AUTH_START_TLS_OFF || $data['search_filter'] !== '';

		$this->setResponse(new CControllerResponseData($data));
	}
}
