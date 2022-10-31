<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerMiscConfigUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'url' =>							'db config.url',
			'discovery_groupid' =>				'required|db config.discovery_groupid',
			'default_inventory_mode' =>			'required|in '.HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC,
			'alert_usrgrpid' =>					'db config.alert_usrgrpid',
			'snmptrap_logging' =>				'required|db config.snmptrap_logging|in 0,1',
			'login_attempts' =>					'required|db config.login_attempts|ge 1|le 32',
			'login_block' =>					'required|db config.login_block|time_unit '.implode(':', [30, SEC_PER_HOUR]),
			'validate_uri_schemes' =>			'required|db config.validate_uri_schemes|in 0,1',
			'uri_valid_schemes' =>				'db config.uri_valid_schemes',
			'x_frame_options' =>				'required|db config.x_frame_options|not_empty',
			'iframe_sandboxing_enabled' =>		'required|db config.iframe_sandboxing_enabled|in 0,1',
			'iframe_sandboxing_exceptions' =>	'db config.iframe_sandboxing_exceptions',
			'socket_timeout' =>					'required|db config.socket_timeout|time_unit '.implode(':', [1, 300]),
			'connect_timeout' =>				'required|db config.connect_timeout|time_unit '.implode(':', [1, 30]),
			'media_type_test_timeout' =>		'required|db config.media_type_test_timeout|time_unit '.implode(':', [1, 300]),
			'script_timeout' =>					'required|db config.script_timeout|time_unit '.implode(':', [1, 300]),
			'item_test_timeout' =>				'required|db config.item_test_timeout|time_unit '.implode(':', [1, 300]),
			'report_test_timeout' =>			'required|db config.report_test_timeout|time_unit '.implode(':', [1, 300]),
			'vault_provider' =>					'db config.vault_provider|in '.ZBX_VAULT_TYPE_HASHICORP.','.ZBX_VAULT_TYPE_CYBERARK
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->getValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
						->setArgument('action', 'miscconfig.edit')
					);

					$response->setFormData($this->getInputAll() + [
						'discovery_groupid' => '0',
						'alert_usrgrpid' => '0'
					]);
					CMessageHelper::setErrorTitle(_('Cannot update configuration'));

					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction() {
		$settings = [
			CSettingsHelper::URL => $this->getInput('url'),
			CSettingsHelper::DISCOVERY_GROUPID => $this->getInput('discovery_groupid'),
			CSettingsHelper::DEFAULT_INVENTORY_MODE => $this->getInput('default_inventory_mode'),
			CSettingsHelper::SNMPTRAP_LOGGING => $this->getInput('snmptrap_logging'),
			CSettingsHelper::LOGIN_ATTEMPTS => $this->getInput('login_attempts'),
			CSettingsHelper::LOGIN_BLOCK => $this->getInput('login_block'),
			CSettingsHelper::VALIDATE_URI_SCHEMES => $this->getInput('validate_uri_schemes'),
			CSettingsHelper::X_FRAME_OPTIONS => $this->getInput('x_frame_options'),
			CSettingsHelper::IFRAME_SANDBOXING_ENABLED => $this->getInput('iframe_sandboxing_enabled'),
			CSettingsHelper::SOCKET_TIMEOUT => $this->getInput('socket_timeout'),
			CSettingsHelper::CONNECT_TIMEOUT => $this->getInput('connect_timeout'),
			CSettingsHelper::MEDIA_TYPE_TEST_TIMEOUT => $this->getInput('media_type_test_timeout'),
			CSettingsHelper::SCRIPT_TIMEOUT => $this->getInput('script_timeout'),
			CSettingsHelper::ITEM_TEST_TIMEOUT => $this->getInput('item_test_timeout'),
			CSettingsHelper::SCHEDULED_REPORT_TEST_TIMEOUT => $this->getInput('report_test_timeout'),
			CSettingsHelper::VAULT_PROVIDER => $this->getInput('vault_provider', ZBX_VAULT_TYPE_HASHICORP)
		];

		$settings[CSettingsHelper::ALERT_USRGRPID] = $this->getInput('alert_usrgrpid', 0);

		if ($settings[CSettingsHelper::VALIDATE_URI_SCHEMES] == 1) {
			$settings[CSettingsHelper::URI_VALID_SCHEMES] = $this->getInput('uri_valid_schemes',
				DB::getSchema('config')['fields']['uri_valid_schemes']['default']
			);
		}

		if ($settings[CSettingsHelper::IFRAME_SANDBOXING_ENABLED] == 1) {
			$settings[CSettingsHelper::IFRAME_SANDBOXING_EXCEPTIONS] = $this->getInput('iframe_sandboxing_exceptions',
				DB::getSchema('config')['fields']['iframe_sandboxing_exceptions']['default']
			);
		}

		$result = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
			->setArgument('action', 'miscconfig.edit')
		);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Configuration updated'));
		}
		else {
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));
			$response->setFormData($this->getInputAll());
		}

		$this->setResponse($response);
	}
}
