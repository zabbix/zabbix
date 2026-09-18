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


class CControllerUserDeviceInitView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'admin_mode' => ['boolean', 'required'],
			'qrdata' => ['object', 'required',
				'fields' => [
					'uuid' => ['string', 'required', 'use' => [CUuidV7Validator::class]],
					'expires_at' => ['integer', 'required'],
					'url' => ['string', 'required', 'not_empty']
				],
				'when' => ['admin_mode', 'in' => [0]]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules(), true);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (CWebUser::isGuest() || !CSettingsHelper::isMobileDevicesEnabled()) {
			return false;
		}

		if ($this->getInput('admin_mode', 0) === '1') {
			return ($this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
				&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES)
			);
		}

		return $this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN);
	}

	protected function doAction(): void {
		$data = [
			'admin_mode' => $this->getInput('admin_mode', 0) ? 1 : 0,
			'qrdata' => $this->getInput('qrdata', []),
			'js_validation_rules' => (new CFormValidator(CControllerUserDeviceInit::getValidationRules()))->getRules(),
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
