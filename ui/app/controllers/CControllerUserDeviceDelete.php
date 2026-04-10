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

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'deviceids' =>	'required|array_db device.deviceid'
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
		if ($this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
				&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES)) {
			return true;
		}

		if ($this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)) {
			$num_devices = API::Device()->get([
				'deviceids' => $this->getInput('deviceids'),
				'userids' => [CWebUser::$data['userid']],
				'countOutput' => true
			]);

			return $num_devices == count($this->getInput('deviceids'));
		}

		return false;
	}

	protected function doAction(): void {
		$deviceids = $this->getInput('deviceids');

		$failed_messages = [];
		$success_messages = [];
		$failed_deviceids = [];
		$success_deviceids = [];

		foreach ($deviceids as $deviceid) {
			$result = API::Device()->offboard(['deviceid' => $deviceid]);
			$messages = array_column(get_and_clear_messages(), 'message');

			if ($result) {
				$success_messages = array_unique(array_merge($success_messages, $messages));
				$success_deviceids[] = $deviceid;
			}
			else {
				$failed_messages = array_unique(array_merge($failed_messages, $messages));
				$failed_deviceids[] = $deviceid;
			}
		}

		if (count($success_deviceids) == 0) {
			$output['error'] = [
				'title' => _n('Cannot unlink device', 'Cannot unlink devices', count($failed_deviceids)),
				'messages' => $failed_messages
			];
		}
		else {
			$output['success'] = [
				'title' => _n('Device unlinked', 'Devices unlinked', count($success_deviceids)),
				'action' => 'delete',
				'messages' => $success_messages
			];

			if (count($failed_deviceids) > 0) {
				$output['success']['error_messages'] = array_merge(
					[_n('Cannot unlink device', 'Cannot unlink devices', count($failed_deviceids))],
					$failed_messages
				);
			}
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
