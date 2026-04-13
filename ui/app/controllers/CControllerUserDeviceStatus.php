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
		if (!CTemporaryMobileFeatureHelper::isEnabled()) {
			return false;
		}

		if (!CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN)
				|| !CWebUser::checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		$device = API::Device()->get([
			'output' => ['deviceid'],
			'filter' => ['uuid' => $this->getInput('uuid')]
		]);

		$output = [];

		if ($device) {
			$success = ['title' => _('Device linked')];

			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			$output['error'] = [
				'title' => _('Device is not linked'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
