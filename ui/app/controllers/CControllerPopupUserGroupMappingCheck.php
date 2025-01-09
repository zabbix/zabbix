<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerPopupUserGroupMappingCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'roleid' =>				'required|db users.roleid',
			'name' =>				'required|string|not_empty',
			'user_groups' =>		'required|array_id'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Invalid user group mapping configuration.'),
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
		$this->getInputs($data, ['name']);

		$user_groups = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $this->getInput('user_groups')
		]);

		$user_role = API::Role()->get([
			'output' => ['name', 'roleid'],
			'roleids' => $this->getInput('roleid')
		]);

		if ($user_role && count($user_groups) == count($this->getInput('user_groups'))) {
			$data['role_name'] = $user_role[0]['name'];
			$data['roleid'] = $user_role[0]['roleid'];
			$data['user_groups'] = $user_groups;
		}
		else {
			$data['error'] = [
				'messages' => [_('No permissions to referred object or it does not exist!')]
			];
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
