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
			'userdirectoryid' => 'id',
			'name' => 'required|string|not_empty',
			'host' => 'required|string|not_empty',
			'port' => 'required|int32',
			'base_dn' => 'required|string|not_empty',
			'search_attribute' => 'required|string|not_empty',
			'userfilter' => 'string',
			'start_tls' => 'in '.ZBX_AUTH_START_TLS_OFF.','.ZBX_AUTH_START_TLS_ON,
			'bind_dn' => 'string',
			'bind_password' => 'string',
			'case_sensitive' => 'in '.ZBX_AUTH_CASE_INSENSITIVE.','.ZBX_AUTH_CASE_SENSITIVE,
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
			'body' => [
				'row_index' => $this->getInput('row_index'),
				'name' => $this->getInput('name'),
				'host' => $this->getInput('host'),
				'port' => $this->getInput('port'),
				'base_dn' => $this->getInput('base_dn'),
				'search_attribute' => $this->getInput('search_attribute'),
				'start_tls' => $this->getInput('start_tls', ZBX_AUTH_START_TLS_OFF), // TODO VM: unchecked value should solve this, or be removed.
				'bind_dn' => $this->getInput('bind_dn', ''),
				'case_sensitive' => $this->getInput('case_sensitive', ZBX_AUTH_CASE_INSENSITIVE),
				'description' => $this->getInput('description', ''),
				'userfilter' => $this->getInput('userfilter', '')
			]
		];

		if ($this->hasInput('userdirectoryid')) {
			$data['body']['userdirectoryid'] = $this->getInput('userdirectoryid');
		}

		if ($this->hasInput('bind_password')) {
			$data['body']['bind_password'] = $this->getInput('bind_password');
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
