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
			'usrgrpid' =>			'array_id',
			'roleid' =>				'db users.roleid',
			'name' =>				'string',
			'is_fallback' =>		'required|in '.GROUP_MAPPING_REGULAR.','.GROUP_MAPPING_FALLBACK,
			'fallback_status' =>	'in '.GROUP_MAPPING_FALLBACK_OFF.','.GROUP_MAPPING_FALLBACK_ON
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'error' => [
							'title' => _('Invalid user group mapping configuration.'),
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
			'name' => $this->getInput('name', ''),
			'add_group' => $this->getInput('add_group', 0),
			'user' => ['debug_mode' => $this->getDebugMode()],
			'name_label' => _('LDAP group pattern'), // TODO: should be moved to the view.
			'is_fallback' => $this->getInput('is_fallback'),
			'fallback_status' => $this->getInput('fallback_status', GROUP_MAPPING_FALLBACK_OFF)
		];

		$data['user_groups'] = $this->hasInput('usrgrpid')
			? API::UserGroup()->get([
				'output' => ['usrgrpid', 'name'],
				'usrgrpids' => $this->getInput('usrgrpid')
			])
			: [];

		$data['user_groups'] = CArrayHelper::renameObjectsKeys($data['user_groups'], ['usrgrpid' => 'id']);

		$data['user_role'] = $this->hasInput('roleid')
			? API::Role()->get([
				'output' => ['roleid', 'name'],
				'roleids' => [$this->getInput('roleid')]
			])
			: [];

		$data['user_role'] = CArrayHelper::renameObjectsKeys($data['user_role'], ['roleid' => 'id']);

		$this->setResponse(new CControllerResponseData($data));
	}
}
