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


class CControllerTimeoutsEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'timeout_zabbix_agent' =>		'db config.timeout_zabbix_agent',
			'timeout_simple_check' =>		'db config.timeout_simple_check',
			'timeout_snmp_agent' =>			'db config.timeout_snmp_agent',
			'timeout_external_check' =>		'db config.timeout_external_check',
			'timeout_db_monitor' =>			'db config.timeout_db_monitor',
			'timeout_http_agent' =>			'db config.timeout_http_agent',
			'timeout_ssh_agent' =>			'db config.timeout_ssh_agent',
			'timeout_telnet_agent' =>		'db config.timeout_telnet_agent',
			'timeout_script' =>				'db config.timeout_script',
			'timeout_browser' =>			'db config.timeout_browser',
			'socket_timeout' =>				'db config.socket_timeout',
			'connect_timeout' =>			'db config.connect_timeout',
			'media_type_test_timeout' =>	'db config.media_type_test_timeout',
			'script_timeout' =>				'db config.script_timeout',
			'item_test_timeout' =>			'db config.item_test_timeout',
			'report_test_timeout' =>		'db config.report_test_timeout'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$data = [
			'timeout_zabbix_agent' => $this->getInput('timeout_zabbix_agent', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_ZABBIX_AGENT
			)),
			'timeout_simple_check' => $this->getInput('timeout_simple_check', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_SIMPLE_CHECK
			)),
			'timeout_snmp_agent' => $this->getInput('timeout_snmp_agent', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_SNMP_AGENT
			)),
			'timeout_external_check' => $this->getInput('timeout_external_check', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_EXTERNAL_CHECK
			)),
			'timeout_db_monitor' => $this->getInput('timeout_db_monitor', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_DB_MONITOR
			)),
			'timeout_http_agent' => $this->getInput('timeout_http_agent', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_HTTP_AGENT
			)),
			'timeout_ssh_agent' => $this->getInput('timeout_ssh_agent', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_SSH_AGENT
			)),
			'timeout_telnet_agent' => $this->getInput('timeout_telnet_agent', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_TELNET_AGENT
			)),
			'timeout_script' => $this->getInput('timeout_script', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_SCRIPT
			)),
			'timeout_browser' => $this->getInput('timeout_browser', CSettingsHelper::get(
				CSettingsHelper::TIMEOUT_BROWSER
			)),
			'socket_timeout' => $this->getInput('socket_timeout', CSettingsHelper::get(
				CSettingsHelper::SOCKET_TIMEOUT
			)),
			'connect_timeout' => $this->getInput('connect_timeout', CSettingsHelper::get(
				CSettingsHelper::CONNECT_TIMEOUT
			)),
			'media_type_test_timeout' => $this->getInput('media_type_test_timeout', CSettingsHelper::get(
				CSettingsHelper::MEDIA_TYPE_TEST_TIMEOUT
			)),
			'script_timeout' => $this->getInput('script_timeout', CSettingsHelper::get(
				CSettingsHelper::SCRIPT_TIMEOUT
			)),
			'item_test_timeout' => $this->getInput('item_test_timeout', CSettingsHelper::get(
				CSettingsHelper::ITEM_TEST_TIMEOUT
			)),
			'report_test_timeout' => $this->getInput('report_test_timeout', CSettingsHelper::get(
				CSettingsHelper::SCHEDULED_REPORT_TEST_TIMEOUT
			))
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of timeouts'));
		$this->setResponse($response);
	}
}
