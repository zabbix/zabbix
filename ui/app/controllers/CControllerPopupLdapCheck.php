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


class CControllerPopupLdapCheck extends CController {

	protected function init(): void {
		$this->disableSIDValidation();

		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' => 'required|int32',
			'name' => 'required|string',
			'host' => 'required|string',
			'port' => 'required|int32',
			'base_dn' => 'required|string',
			'search_attribute' => 'required|string',
			'userfilter' => 'string',
			'start_tls' => 'in 0,1',
			'bind_dn' => 'string',
			'bind_password' => 'string',
			'case_sensitive' => 'in 0,1',
			'description' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid LDAP configuration'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUTHENTICATION);
	}

	protected function doAction(): void {

		$data = [
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
			'userfilter' => ''
		];

		$this->getInputs($data, array_keys($data));

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
