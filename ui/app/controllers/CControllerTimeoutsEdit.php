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


class CControllerTimeoutsEdit extends CController {

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
			'timeout_zabbix_agent' => CSettingsSchema::getDefault('timeout_zabbix_agent'),
			'timeout_simple_check' => CSettingsSchema::getDefault('timeout_simple_check'),
			'timeout_snmp_agent' => CSettingsSchema::getDefault('timeout_snmp_agent'),
			'timeout_external_check' => CSettingsSchema::getDefault('timeout_external_check'),
			'timeout_db_monitor' => CSettingsSchema::getDefault('timeout_db_monitor'),
			'timeout_http_agent' => CSettingsSchema::getDefault('timeout_http_agent'),
			'timeout_ssh_agent' => CSettingsSchema::getDefault('timeout_ssh_agent'),
			'timeout_telnet_agent' => CSettingsSchema::getDefault('timeout_telnet_agent'),
			'timeout_script' => CSettingsSchema::getDefault('timeout_script'),
			'timeout_browser' => CSettingsSchema::getDefault('timeout_browser'),
			'socket_timeout' => CSettingsSchema::getDefault('socket_timeout'),
			'connect_timeout' => CSettingsSchema::getDefault('connect_timeout'),
			'media_type_test_timeout' => CSettingsSchema::getDefault('media_type_test_timeout'),
			'script_timeout' => CSettingsSchema::getDefault('script_timeout'),
			'item_test_timeout' => CSettingsSchema::getDefault('item_test_timeout'),
			'report_test_timeout' => CSettingsSchema::getDefault('report_test_timeout')
		];
	}

	protected function doAction(): void {
		$default_values = $this->getDefaultValues();
		$data = [];

		foreach ($default_values as $key => $default_value) {
			$data[$key] = CSettingsHelper::get($key);
		}

		$data['js_validation_rules'] = (new CFormValidator(CControllerTimeoutsUpdate::getValidationRules()))
			->getRules();
		$data['default_values'] = $default_values;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of timeouts'));
		$this->setResponse($response);
	}
}
