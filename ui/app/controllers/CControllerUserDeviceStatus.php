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


class CControllerUserDeviceStatus extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput() {
		$fields = [
			'uuid' =>	'required|not_empty|db device.uuid'
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

	protected function checkPermissions() {
		if (CWebUser::isGuest() || !CSettingsHelper::isMobileDevicesEnabled()) {
			return false;
		}

		return CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)
			|| (CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
				&& CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES));
	}

	protected function doAction() {
		$options = [
			'output' => ['deviceid'],
			'filter' => ['uuid' => $this->getInput('uuid'), 'status' => ZBX_DEVICE_STATUS_ACTIVATED]
		];

		if (!CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
				|| !CWebUser::checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES)) {
			$options['userids'] = [CWebUser::$data['userid']];
		}

		$device = API::Device()->get($options);
		$output = [];

		if ($device) {
			$success = ['title' => _('Device added')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
