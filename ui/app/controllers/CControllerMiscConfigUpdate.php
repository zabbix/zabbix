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


class CControllerMiscConfigUpdate extends CController {

	protected function checkInput() {
		$fields = [
			'url' =>							'setting url',
			'discovery_groupid' =>				'required|setting discovery_groupid',
			'default_inventory_mode' =>			'required|in '.HOST_INVENTORY_DISABLED.','.HOST_INVENTORY_MANUAL.','.HOST_INVENTORY_AUTOMATIC,
			'alert_usrgrpid' =>					'setting alert_usrgrpid',
			'snmptrap_logging' =>				'required|in 0,1',
			'login_attempts' =>					'required|ge 1|le 32',
			'login_block' =>					'required|time_unit '.implode(':', [30, SEC_PER_HOUR]),
			'validate_uri_schemes' =>			'required|in 0,1',
			'uri_valid_schemes' =>				'setting uri_valid_schemes',
			'x_frame_header_enabled' =>			'required|in 0,1',
			'x_frame_options' =>				'setting x_frame_options',
			'iframe_sandboxing_enabled' =>		'required|in 0,1',
			'iframe_sandboxing_exceptions' =>	'setting iframe_sandboxing_exceptions',
			'vault_provider' =>					'in '.ZBX_VAULT_TYPE_HASHICORP.','.ZBX_VAULT_TYPE_CYBERARK,
			'proxy_secrets_provider' =>			'in '.ZBX_PROXY_SECRETS_PROVIDER_SERVER.','.ZBX_PROXY_SECRETS_PROVIDER_PROXY
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('x_frame_header_enabled') == 1) {
				$fields['x_frame_options'] = 'required|not_empty';
			}

			$ret = $this->validateInput($fields);
		}

		if (!$ret) {
			switch ($this->getValidationResult()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))->setArgument('action', 'miscconfig.edit')
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
			CSettingsHelper::IFRAME_SANDBOXING_ENABLED => $this->getInput('iframe_sandboxing_enabled'),
			CSettingsHelper::VAULT_PROVIDER => $this->getInput('vault_provider', ZBX_VAULT_TYPE_HASHICORP),
			CSettingsHelper::PROXY_SECRETS_PROVIDER => $this->getInput('proxy_secrets_provider',
				ZBX_PROXY_SECRETS_PROVIDER_SERVER
			)
		];

		$settings[CSettingsHelper::ALERT_USRGRPID] = $this->getInput('alert_usrgrpid', 0);

		if ($settings[CSettingsHelper::VALIDATE_URI_SCHEMES] == 1) {
			$settings[CSettingsHelper::URI_VALID_SCHEMES] = $this->getInput('uri_valid_schemes',
				CSettingsSchema::getDefault('uri_valid_schemes')
			);
		}

		$settings[CSettingsHelper::X_FRAME_OPTIONS] = $this->getInput('x_frame_header_enabled') == 1
			? $this->getInput('x_frame_options')
			: 'null';

		if ($settings[CSettingsHelper::IFRAME_SANDBOXING_ENABLED] == 1) {
			$settings[CSettingsHelper::IFRAME_SANDBOXING_EXCEPTIONS] = $this->getInput('iframe_sandboxing_exceptions',
				CSettingsSchema::getDefault('iframe_sandboxing_exceptions')
			);
		}

		$result = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))->setArgument('action', 'miscconfig.edit')
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
