<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CControllerServiceStatusRuleValidate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'row_index' => ['integer', 'required'],
			'type' => ['db service_status_rule.type', 'required',
				'in' => array_keys(CServiceHelper::getStatusRuleTypeOptions())
			],
			'limit_value' => [
				['db service_status_rule.limit_value', 'required', 'min' => 1, 'max' => 1000000,
					'when' => ['type', 'in' => [
						ZBX_SERVICE_STATUS_RULE_TYPE_N_GE, ZBX_SERVICE_STATUS_RULE_TYPE_N_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_W_GE, ZBX_SERVICE_STATUS_RULE_TYPE_W_L
					]]
				],
				['db service_status_rule.limit_value', 'required', 'min' => 1, 'max' => 100,
					'when' => ['type', 'in' => [
						ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_NP_L,
						ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE, ZBX_SERVICE_STATUS_RULE_TYPE_WP_L
					]]
				]
			],
			'limit_status' => ['db service_status_rule.limit_status', 'required',
				'in' => array_keys(CServiceHelper::getStatusNames())
			],
			'new_status' => ['db service_status_rule.new_status', 'required',
				'in' => array_keys(CServiceHelper::getProblemStatusNames())
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
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES);
	}

	protected function doAction(): void {
		$data = [
			'body' => [
				'row_index' => $this->getInput('row_index'),
				'name' => CServiceHelper::formatStatusRuleType((int) $this->getInput('type'),
					(int) $this->getInput('new_status'), (int) $this->getInput('limit_value'),
					(int) $this->getInput('limit_status')
				),
				'type' => $this->getInput('type'),
				'limit_value' => $this->getInput('limit_value'),
				'limit_status' => $this->getInput('limit_status'),
				'new_status' => $this->getInput('new_status')
			]
		];

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
