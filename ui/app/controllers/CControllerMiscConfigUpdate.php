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


class CControllerMiscConfigUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'url' => ['setting url', 'required'],
			'discovery_groupid' => ['setting discovery_groupid', 'required'],
			'default_inventory_mode' => ['setting default_inventory_mode', 'required',
				'in' => [HOST_INVENTORY_DISABLED, HOST_INVENTORY_MANUAL, HOST_INVENTORY_AUTOMATIC]
			],
			'alert_usrgrpid' => ['setting alert_usrgrpid'],
			'snmptrap_logging' => ['boolean', 'required'],
			'login_attempts' => ['setting login_attempts', 'required', 'min' => 1, 'max' => 32],
			'login_block' => ['setting login_block', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 30, 'max' => SEC_PER_HOUR]]
			],
			'vault_provider' => ['setting vault_provider', 'required',
				'in' => [ZBX_VAULT_TYPE_HASHICORP, ZBX_VAULT_TYPE_CYBERARK]
			],
			'proxy_secrets_provider' => ['setting proxy_secrets_provider', 'required',
				'in' => [ZBX_PROXY_SECRETS_PROVIDER_SERVER, ZBX_PROXY_SECRETS_PROVIDER_PROXY]
			],
			'validate_uri_schemes' => ['boolean', 'required'],
			'uri_valid_schemes' => ['setting uri_valid_schemes', 'required',
				'when' => ['validate_uri_schemes', 'in' => [1]]
			],
			'x_frame_header_enabled' => ['boolean', 'required'],
			'x_frame_options' => ['setting x_frame_options', 'required', 'not_empty',
				'when' => ['x_frame_header_enabled', 'in' => [1]]
			],
			'iframe_sandboxing_enabled' => ['boolean', 'required'],
			'iframe_sandboxing_exceptions' => ['setting iframe_sandboxing_exceptions', 'required',
				'when' => ['iframe_sandboxing_enabled', 'in' => [1]]
			]
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot update configuration'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$settings = $this->getInputAll();
		$settings['alert_usrgrpid'] = $this->getInput('alert_usrgrpid', 0);
		unset($settings['x_frame_header_enabled']);

		if ($this->getInput('x_frame_header_enabled', 0) == 0) {
			$settings['x_frame_options'] = 'null';
		}

		$result = API::Settings()->update($settings);

		$output = [];

		if ($result) {
			$output['success'] = [
				'title' => _('Configuration updated')
			];
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update configuration'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
