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


class CControllerMiscConfigEdit extends CController {

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
			'url' => CSettingsSchema::getDefault('url'),
			'discovery_groupid' => null,
			'default_inventory_mode' => CSettingsSchema::getDefault('default_inventory_mode'),
			'alert_usrgrpid' => null,
			'snmptrap_logging' => CSettingsSchema::getDefault('snmptrap_logging'),
			'login_attempts' => CSettingsSchema::getDefault('login_attempts'),
			'login_block' => CSettingsSchema::getDefault('login_block'),
			'vault_provider' => CSettingsSchema::getDefault('vault_provider'),
			'proxy_secrets_provider' => CSettingsSchema::getDefault('proxy_secrets_provider'),
			'validate_uri_schemes' => CSettingsSchema::getDefault('validate_uri_schemes'),
			'uri_valid_schemes' => CSettingsSchema::getDefault('uri_valid_schemes'),
			'x_frame_options' => CSettingsSchema::getDefault('x_frame_options'),
			'iframe_sandboxing_enabled' => CSettingsSchema::getDefault('iframe_sandboxing_enabled'),
			'iframe_sandboxing_exceptions' => CSettingsSchema::getDefault('iframe_sandboxing_exceptions')
		];
	}

	private function processXframeOptionConfig(array &$config): void {
		if (strcasecmp('null', $config['x_frame_options']) == 0) {
			$config['x_frame_header_enabled'] = 0;
			$config['x_frame_options'] = '';
		}
		else {
			$config['x_frame_header_enabled'] = 1;
		}
	}

	protected function doAction(): void {
		$default_values = $this->getDefaultValues();
		$data = [];

		foreach ($default_values as $key => $default_value) {
			$data[$key] = CSettingsHelper::get($key);
		}

		$this->processXframeOptionConfig($default_values);
		$this->processXframeOptionConfig($data);

		$data['discovery_group_data'] = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'filter' => ['flags' => ZBX_FLAG_DISCOVERY_NORMAL],
			'groupids' => $data['discovery_groupid'],
			'editable' => true
		]);
		$data['discovery_group_data'] = CArrayHelper::renameObjectsKeys($data['discovery_group_data'],
			['groupid' => 'id']
		);

		$data['alert_usrgrp_data'] = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name'],
			'usrgrpids' => $data['alert_usrgrpid']
		]);
		$data['alert_usrgrp_data'] = CArrayHelper::renameObjectsKeys($data['alert_usrgrp_data'], ['usrgrpid' => 'id']);

		$data['js_validation_rules'] = (new CFormValidator(CControllerMiscConfigUpdate::getValidationRules()))
			->getRules();
		$data['default_values'] = $default_values;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Other configuration parameters'));
		$this->setResponse($response);
	}
}
