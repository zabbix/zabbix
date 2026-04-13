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


class CControllerTrigDisplayEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	private function getDefaultvalues(): array {
		return [
			'custom_color' => CSettingsSchema::getDefault(CSettingsHelper::CUSTOM_COLOR),
			'problem_unack_color' => CSettingsSchema::getDefault(CSettingsHelper::PROBLEM_UNACK_COLOR),
			'problem_ack_color' =>  CSettingsSchema::getDefault(CSettingsHelper::PROBLEM_ACK_COLOR),
			'ok_unack_color' => CSettingsSchema::getDefault(CSettingsHelper::OK_UNACK_COLOR),
			'ok_ack_color' => CSettingsSchema::getDefault(CSettingsHelper::OK_ACK_COLOR),
			'problem_unack_style' => CSettingsSchema::getDefault(CSettingsHelper::PROBLEM_UNACK_STYLE),
			'problem_ack_style' => CSettingsSchema::getDefault(CSettingsHelper::PROBLEM_ACK_STYLE),
			'ok_unack_style' => CSettingsSchema::getDefault(CSettingsHelper::OK_UNACK_STYLE),
			'ok_ack_style' => CSettingsSchema::getDefault(CSettingsHelper::OK_ACK_STYLE),
			'ok_period' => CSettingsSchema::getDefault(CSettingsHelper::OK_PERIOD),
			'blink_period' => CSettingsSchema::getDefault(CSettingsHelper::BLINK_PERIOD),
			'severity_name_0' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_0),
			'severity_color_0' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_0),
			'severity_name_1' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_1),
			'severity_color_1' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_1),
			'severity_name_2' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_2),
			'severity_color_2' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_2),
			'severity_name_3' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_3),
			'severity_color_3' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_3),
			'severity_name_4' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_4),
			'severity_color_4' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_4),
			'severity_name_5' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_NAME_5),
			'severity_color_5' => CSettingsSchema::getDefault(CSettingsHelper::SEVERITY_COLOR_5)
		];
	}

	protected function doAction(): void {
		$default_values = $this->getDefaultvalues();
		$data = [];

		foreach ($default_values as $key => $default_value) {
			$data[$key] = CSettingsHelper::get($key);
		}

		$data['js_validation_rules'] = (new CFormValidator(CControllerTrigDisplayUpdate::getValidationRules()))
			->getRules();
		$data['default_values'] = $default_values;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of trigger displaying options'));

		$this->setResponse($response);
	}
}
