<?php declare(strict_types = 0);
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


class CControllerTimeoutsUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'timeout_zabbix_agent' => ['setting timeout_zabbix_agent', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_simple_check' => ['setting timeout_simple_check', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_snmp_agent' => ['setting timeout_snmp_agent', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_external_check' => ['setting timeout_external_check', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_db_monitor' => ['setting timeout_db_monitor', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_http_agent' =>	['setting timeout_http_agent', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_ssh_agent' => ['setting timeout_ssh_agent', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_telnet_agent' => ['setting timeout_telnet_agent', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_script' => ['setting timeout_script', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'timeout_browser' => ['setting timeout_browser', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600, 'usermacros' => true]]
			],
			'socket_timeout' => ['setting socket_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 300]]
			],
			'connect_timeout' => ['setting connect_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 30]]
			],
			'media_type_test_timeout' => ['setting media_type_test_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 300]]
			],
			'script_timeout' => ['setting script_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 300]]
			],
			'item_test_timeout' => ['setting item_test_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 600]]
			],
			'report_test_timeout' => ['setting report_test_timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => 300]]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$result = API::Settings()->update($this->getInputAll());

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Configuration updated')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
