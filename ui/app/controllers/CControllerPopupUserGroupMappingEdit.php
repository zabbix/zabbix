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


class CControllerPopupUserGroupMappingEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'add_group' =>			'in 1',
			'usrgrpid' =>			'string',
			'roleid' =>				'db users.roleid',
			'idp_group_name' =>		'string',
			'name_label' =>			'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid user group mappin configuration'),
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
			'idp_group_name' => $this->getInput('idp_group_name', ''),
			'add_group' => $this->getInput('add_group', ''),
			'user' => ['debug_mode' => $this->getDebugMode()],
			'name_label' => $this->getInput('name_label')
		];

		$user_groups = explode(',', $this->getInput('usrgrpid', ''));

		$data['user_groups'] = $user_groups
			? API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $user_groups
			])
			: [];

		$data['user_groups'] = CArrayHelper::renameObjectsKeys($data['user_groups'], ['usrgrpid' => 'id']);

		$user_role = $this->getInput('roleid', '');

		$data['user_role'] = $user_role
			? API::Role()->get([
				'output' => ['roleid', 'name'],
				'roleids' => [$user_role]
			])
			: [];
		$data['user_role'] = CArrayHelper::renameObjectsKeys($data['user_role'], ['roleid' => 'id']);

		$this->setResponse(new CControllerResponseData($data));
	}
}
