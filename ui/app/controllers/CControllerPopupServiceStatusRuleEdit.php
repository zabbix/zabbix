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


class CControllerPopupServiceStatusRuleEdit extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	private static function getValidationRules(): array {
		return ['objects', 'fields' => [
			'form_refresh' => ['integer', 'in' => ['0', '1']],
			'edit' => ['integer', 'in 1'],
			'row_index' => ['integer', 'required'],
			'new_status' => ['integer', 'in' => array_keys(CServiceHelper::getProblemStatusNames())],
			'type' => ['integer', 'in' => array_keys(CServiceHelper::getStatusRuleTypeOptions())],
			'limit_value' => ['integer'],
			'limit_status' => ['integer', 'in' => array_keys(CServiceHelper::getStatusNames())]
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

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($response)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES);
	}

	protected function doAction(): void {
		$form = [
			'new_status' => $this->getInput('new_status', TRIGGER_SEVERITY_NOT_CLASSIFIED),
			'type' => $this->getInput('type', ZBX_SERVICE_STATUS_RULE_TYPE_N_GE),
			'limit_value' => $this->getInput('limit_value', 1),
			'limit_status' => $this->getInput('limit_status', ZBX_SEVERITY_OK)
		];

		$data = [
			'is_edit' => $this->hasInput('edit'),
			'row_index' => $this->getInput('row_index'),
			'form' => $form,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(
				CControllerServiceStatusRuleValidate::getValidationRules())
			)->getRules()
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
