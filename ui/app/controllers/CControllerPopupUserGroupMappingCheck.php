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


class CControllerPopupUserGroupMappingCheck extends CController {

	protected function init(): void {
		$this->disableSIDValidation();

		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'roleid' =>				'required|db users.roleid',
			'name' =>				'required|string',
			'user_groups' =>		'array_id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid user group mapping configuration'),
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
			'name' => $this->getInput('name')
		];

		$user_groups = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $this->getInput('user_groups', '')
		]);

		$data['usrgrpid'] = implode(', ',array_column($user_groups, 'usrgrpid'));
		$data['user_group_name'] = implode(', ',array_column($user_groups, 'name'));

		$user_role = API::Role()->get([
				'output' => ['name', 'roleid'],
				'roleids' => $this->getInput('roleid')
			]);

		$data['role_name'] = $user_role[0]['name'];
		$data['roleid'] = $user_role[0]['roleid'];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
