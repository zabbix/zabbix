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


class CControllerMiscConfigEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'url' =>							'db config.url',
			'discovery_groupid' =>				'db config.discovery_groupid',
			'default_inventory_mode' =>			'db config.default_inventory_mode',
			'alert_usrgrpid' =>					'db config.alert_usrgrpid',
			'snmptrap_logging' =>				'db config.snmptrap_logging',
			'login_attempts' =>					'db config.login_attempts',
			'login_block' =>					'db config.login_block',
			'validate_uri_schemes' =>			'db config.validate_uri_schemes',
			'uri_valid_schemes' =>				'db config.uri_valid_schemes',
			'x_frame_header_enabled' =>			'in 0,1',
			'x_frame_options' =>				'db config.x_frame_options',
			'iframe_sandboxing_enabled' =>		'db config.iframe_sandboxing_enabled',
			'iframe_sandboxing_exceptions' =>	'db config.iframe_sandboxing_exceptions',
			'vault_provider' =>					'db config.vault_provider'
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
			'url' => $this->getInput('url', CSettingsHelper::get(CSettingsHelper::URL)),
			'discovery_groupid' => $this->getInput('discovery_groupid', CSettingsHelper::get(
				CSettingsHelper::DISCOVERY_GROUPID
			)),
			'default_inventory_mode' => $this->getInput('default_inventory_mode', CSettingsHelper::get(
				CSettingsHelper::DEFAULT_INVENTORY_MODE
			)),
			'alert_usrgrpid' => $this->getInput('alert_usrgrpid', CSettingsHelper::get(
				CSettingsHelper::ALERT_USRGRPID
			)),
			'snmptrap_logging' => $this->getInput('snmptrap_logging', CSettingsHelper::get(
				CSettingsHelper::SNMPTRAP_LOGGING
			)),
			'login_attempts' => $this->getInput('login_attempts', CSettingsHelper::get(
				CSettingsHelper::LOGIN_ATTEMPTS
			)),
			'login_block' => $this->getInput('login_block', CSettingsHelper::get(CSettingsHelper::LOGIN_BLOCK)),
			'validate_uri_schemes' => $this->getInput('validate_uri_schemes', CSettingsHelper::get(
				CSettingsHelper::VALIDATE_URI_SCHEMES
			)),
			'uri_valid_schemes' => $this->getInput('uri_valid_schemes', CSettingsHelper::get(
				CSettingsHelper::URI_VALID_SCHEMES
			)),
			'iframe_sandboxing_enabled' => $this->getInput('iframe_sandboxing_enabled', CSettingsHelper::get(
				CSettingsHelper::IFRAME_SANDBOXING_ENABLED
			)),
			'iframe_sandboxing_exceptions' => $this->getInput('iframe_sandboxing_exceptions', CSettingsHelper::get(
				CSettingsHelper::IFRAME_SANDBOXING_EXCEPTIONS
			)),
			'vault_provider' => $this->getInput('vault_provider', CSettingsHelper::get(CSettingsHelper::VAULT_PROVIDER))
		];

		$x_frame_options = $this->getInput('x_frame_options', CSettingsHelper::get(CSettingsHelper::X_FRAME_OPTIONS));
		$data['x_frame_header_enabled'] = strcasecmp('null', $x_frame_options) == 0 ? 0 : 1;
		$data['x_frame_options'] = $data['x_frame_header_enabled'] == 1	? $x_frame_options : '';

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

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Other configuration parameters'));
		$this->setResponse($response);
	}
}
