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


class CControllerUserDeviceInit extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'admin_mode' => ['boolean'],
			'userid' => ['db users.userid', 'required']
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add a device'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest() || !CTemporaryMobileFeatureHelper::isEnabled()) {
			return false;
		}

		if (CWebUser::$data['userid'] == $this->getInput('userid')) {
			return $this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_OWN);
		}

		return $this->checkAccess(CRoleHelper::DEVICES_ACTIONS_MANAGE_USER)
			&& $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_LINKED_DEVICES);
	}

	protected function initFakeDevice (): array {
		return [
			'enrollment_token_expires_at' => time() + 300,
			'uuid' => '018f9c6a7c1b7f3a9b2d5c4f8e6a1d90',
			'server_id' => '018f9c6a-7c1b-7f3a-9b2d-5c4f8e6a1d90',
			'enrollment_token' => 'BD0qfl9qmxw_CkWfEaK1DJg0PH3hilZ3mtp8NvHiklFZ9ijGbhx0lD0KNsVVkT3bg8EjbUMoW8bfE2Jio8mU0o',
			'mobile_enrollment_token' => 'BD0qfl9qmxw_CkWfEaK1DJg0PH3hilZ3mtp8NvHiklFZ9ijGbhx0lD0KNsVVkT3bg8EjbUMoW8bfE2Jio8mU0o',
			'bridge_adapter_encryption_key' => [
				'kty' => 'EC',
				'crv' => 'P-256',
				'x' => 'V4D0HUpQ7fqiXLyLw4CivPSUAL3tbbyH_bFeRaBT7NY',
				'y' => 'kZdlarx9bsd7g28If2wNobwRYoBJFvaBDuI2pTyyIKs',
				'kid' => 'serverKid',
				'alg' => '1'
			],
			'bridge_enrollment_url' => '165.22.83.177:8443'
		];
	}

	protected function doAction() {
		$userid = $this->getInput('userid');

		// TODO: uncomment and use actual init
		//$device = API::Device()->init(['userid' => $userid]);
		$device = $this->initFakeDevice();
		$output = [];

		if ($device) {
			$output = [
				'expires_at' => $device['enrollment_token_expires_at'],
				'expires_at_text' => zbx_date2str(TIME_FORMAT, $device['enrollment_token_expires_at']),
				'uuid' => $device['uuid'],
				'url' => (new CUrl('zabbix://v' . ZABBIX_MOBILE_VERSION . '/link_device'))
					->setArgument('ver', ZABBIX_API_VERSION)
					->setArgument('sid', $device['server_id'])
					->setArgument('name',
						APP::getConfig()['ZBX_SERVER_NAME'] === '' ? 'Zabbix' : APP::getConfig()['ZBX_SERVER_NAME']
					)
					->setArgument('did', $device['uuid'])
					->setArgument('url', $device['bridge_enrollment_url'])
					->setArgument('met', $device['mobile_enrollment_token'])
					->setArgument('zet', $device['enrollment_token'])
					->setArgument('bek', base64_encode(json_encode($device['bridge_adapter_encryption_key'])))
					->getUrl()
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add a device'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
