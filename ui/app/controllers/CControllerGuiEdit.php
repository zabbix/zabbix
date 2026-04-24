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


class CControllerGuiEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	private function getDefaultValues(): array {
		return [
			'default_lang' => CSettingsSchema::getDefault('default_lang'),
			'default_timezone' => CSettingsSchema::getDefault('default_timezone'),
			'default_theme' => CSettingsSchema::getDefault('default_theme'),
			'search_limit' => CSettingsSchema::getDefault('search_limit'),
			'max_overview_table_size' => CSettingsSchema::getDefault('max_overview_table_size'),
			'max_in_table' => CSettingsSchema::getDefault('max_in_table'),
			'server_check_interval' => CSettingsSchema::getDefault('server_check_interval'),
			'work_period' => CSettingsSchema::getDefault('work_period'),
			'show_technical_errors' => CSettingsSchema::getDefault('show_technical_errors'),
			'history_period' => CSettingsSchema::getDefault('history_period'),
			'period_default' => CSettingsSchema::getDefault('period_default'),
			'max_period' => CSettingsSchema::getDefault('max_period')
		];
	}

	protected function doAction(): void {
		$default_values = $this->getDefaultValues();
		$data = [];

		foreach ($default_values as $key => $default_value) {
			$data[$key] = CSettingsHelper::get($key);
		}

		$data['timezones'] = [
				ZBX_DEFAULT_TIMEZONE => CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(), _('System'))
			] + CTimezoneHelper::getList();
		$data['js_validation_rules'] = (new CFormValidator(CControllerGuiUpdate::getValidationRules()))->getRules();
		$data['default_values'] = $default_values;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of GUI'));
		$this->setResponse($response);
	}
}
