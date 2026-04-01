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
			'userid' => ['db users.userid', 'required',
				'when' => ['admin_mode', 'in' => [1]]
			]
		]];
	}

	protected function checkInput() {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot link a device'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		// TODO: check access
		return true;
	}

	protected function initFakeDevice (): array {
		return [
			'expires_at' => time() + 3600,
			'uuid' => 'dev-id-123',
			'server_id' => 'server-id-123',
			'enrollment_token' => 'BET',
			'mobile_enrollment_token' => 'MET',
			'bridge_enrollment_key' => ['b' => 'b', 'e' => 'e', 'k' => 'k'],
			'enrollment_url' => 'URL'
		];
	}

	protected function doAction() {
		$userid = $this->getInput('userid', CWebUser::$data['userid']);

		// TODO: uncomment and use actual init
		//$device = API::Device()->init(['userid' => $userid]);
		$device = $this->initFakeDevice();
		$output = [];

		if ($device) {
			$output = [
				'expires_at' => zbx_date2str(TIME_FORMAT, $device['expires_at']),
				'uuid' => $device['uuid'],
				'url' => (new CUrl('zabbix://v' . ZABBIX_MOBILE_VERSION . '/link_device'))
					->setArgument('ver', ZABBIX_API_VERSION)
					->setArgument('sid', $device['server_id'])
					->setArgument('did', $device['uuid'])
					->setArgument('url', $device['enrollment_url'])
					->setArgument('met', $device['mobile_enrollment_token'])
					->setArgument('zet', $device['enrollment_token'])
					->setArgument('bek', base64_encode(json_encode($device['bridge_enrollment_key'])))
					->getUrl()
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot link a device'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));

	}
}
