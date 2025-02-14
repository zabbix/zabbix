<?php
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


class CControllerMacrosEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_MACROS);
	}

	protected function doAction(): void {
		$data = [
			'macros' => array_values(order_macros(API::UserMacro()->get([
				'output' => ['globalmacroid', 'macro', 'value', 'type', 'description'],
				'globalmacro' => true
			]), 'macro')),
			'js_validation_rules' => (new CFormValidator(CControllerMacrosUpdate::getValidationRules()))->getRules()
		];

		if (!$data['macros']) {
			$data['macros'][] = ['macro' => '', 'value' => '', 'description' => '', 'type' => ZBX_MACRO_TYPE_TEXT];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of macros'));
		$this->setResponse($response);
	}
}
