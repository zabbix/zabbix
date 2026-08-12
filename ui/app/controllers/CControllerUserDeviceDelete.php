<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


class CControllerUserDeviceDelete extends CController {

	private $device;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'deviceid' =>	'required|db device.deviceid',
			'force' =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (CWebUser::isGuest() || !CSettingsHelper::isMobileDevicesEnabled()) {
			return false;
		}

		if (!$this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN) &&
				!($this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
					&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES))) {
			return false;
		}

		if ($this->hasInput('force') &&
				!($this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
					&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES))) {
			return false;
		}

		$filter = [
			'deviceids' => [$this->getInput('deviceid')],
			'output' => ['deviceid', 'uuid', 'userid']
		];

		if (!$this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
				|| !$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES)) {
			$filter['userids'] = [CWebUser::$data['userid']];
		}

		$devices = API::Device()->get($filter);

		if ($devices) {
			$this->device = reset($devices);

			if ($this->device['userid'] == CWebUser::$data['userid']
					&& !$this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)) {
				return false;
			}
		}

		return true;
	}

	protected function doAction(): void {
		$result = false;
		$only_local_device = false;

		if ($this->device !== null) {
			$data = ['uuid' => $this->device['uuid']];

			if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
				$data['force'] = $this->getInput('force', 0) == 1;
			}

			try {
				$result = API::Device()->offboard($data);
			}
			catch (Exception $e) {
				error($e->getMessage());

				if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN &&
						$e->getCode() == ZBX_API_ERROR_NO_EXTERNAL_ENTITY) {
					$only_local_device = true;
				}
			}
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));
		}

		$output = $result
			? ['success' => [
				'title' => _('Device removed'),
				'action' => 'delete',
				'messages' => array_column(get_and_clear_messages(), 'message')
			]]
			: [
				'error' => [
					'title' => _('Cannot remove device'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				],
				'only_local_device' => $only_local_device
			];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
