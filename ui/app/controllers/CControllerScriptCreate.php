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


class CControllerScriptCreate extends CController {

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules());

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add script'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	public static function getValidationRules(): array {
		$api_uniq = ['script.get', ['name' => '{name}', 'menu_path' => '{menu_path}']];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'name' => ['db scripts.name', 'required', 'not_empty'],
			'scope' => ['db scripts.scope', 'required', 'in' => [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST,
				ZBX_SCRIPT_SCOPE_EVENT
			]],
			'type' => [
				['db scripts.type', 'required',
					'in' => [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
						ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK, ZBX_SCRIPT_TYPE_URL
					],
					'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
				],
				['db scripts.type', 'required',
					'in' => [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
						ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK
					],
					'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_ACTION]]
				]
			],
			'execute_on' => ['db scripts.execute_on', 'required',
				'in' => [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT]]
			],
			'menu_path' => ['db scripts.menu_path', 'required',
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
				'use' => [CMenuPathValidator::class, ['strict' => true]]
			],
			'authtype' => ['db scripts.authtype', 'required',
				'in' => [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_SSH]]
			],
			'username' => ['db scripts.username', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET]]
			],
			'password' => [
				['db scripts.password', 'required', 'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_TELNET]]],
				['db scripts.password', 'required', 'when' => ['authtype', 'in' => [ITEM_AUTHTYPE_PASSWORD]]]
			],
			'publickey' => ['db scripts.publickey', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [ZBX_SCRIPT_TYPE_SSH]],
					['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
				]
			],
			'privatekey' => ['db scripts.privatekey', 'required', 'not_empty',
				'when' => [
					['type', 'in' => [ZBX_SCRIPT_TYPE_SSH]],
					['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
				]
			],
			'passphrase' => ['db scripts.password', 'required',
				'when' => ['authtype', 'in' => [ITEM_AUTHTYPE_PUBLICKEY]]
			],
			'port' => ['db scripts.port', 'required',
				'use' => [CPortParser::class, ['usermacros' => true]],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET]],
				'messages' => ['use' => _('Incorrect port.')]
			],
			'command' => ['db scripts.command', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET]]
			],
			'commandipmi' => ['db scripts.command', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_IPMI]]
			],
			'parameters' => ['objects', 'required', 'uniq' => ['name'],
				'fields' => [
					'value' => ['db script_param.value'],
					'name' => [
						['db script_param.name'],
						['db script_param.name', 'required', 'not_empty', 'when' => ['value', 'not_empty']]
					]
				],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_WEBHOOK]],
				'messages' => ['uniq' => _('Name is not unique.')]
			],
			'script' => ['db scripts.command', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_WEBHOOK]]
			],
			'timeout' => ['db scripts.timeout', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['min' => 1, 'max' => SEC_PER_MIN]],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_WEBHOOK]]
			],
			'url' => ['db scripts.url', 'required', 'not_empty',
				'use' => [CUrlValidator::class, [
					'user_macro' => true,
					'manualinput_macro' => true,
					'scheme' => CSettingsHelper::getAllowedUriSchemes()
				]],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_URL]]
			],
			'new_window' => ['db scripts.new_window', 'required',
				'in' => [ZBX_SCRIPT_URL_NEW_WINDOW_NO, ZBX_SCRIPT_URL_NEW_WINDOW_YES],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_URL]]
			],
			'description' => ['db scripts.description', 'required'],
			'host_access' => ['db scripts.host_access', 'required',
				'in' => [PERM_READ, PERM_READ_WRITE],
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
			],
			'groupid' => ['db scripts.groupid'],
			'usrgrpid' => ['db scripts.usrgrpid',
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
			],
			'manualinput' => ['db scripts.manualinput', 'required',
				'in' => [ZBX_SCRIPT_MANUALINPUT_DISABLED, ZBX_SCRIPT_MANUALINPUT_ENABLED],
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
			],
			'manualinput_prompt' => ['db scripts.manualinput_prompt', 'required', 'not_empty',
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['manualinput', 'in' => [ZBX_SCRIPT_MANUALINPUT_ENABLED]]
				]
			],
			'manualinput_validator_type' => ['db scripts.manualinput_validator_type', 'required',
				'in' => [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING, ZBX_SCRIPT_MANUALINPUT_TYPE_LIST],
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['manualinput', 'in' => [ZBX_SCRIPT_MANUALINPUT_ENABLED]]
				]
			],
			'manualinput_default_value' => ['db scripts.manualinput_default_value', 'required',
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['manualinput', 'in' => [ZBX_SCRIPT_MANUALINPUT_ENABLED]],
					['manualinput_validator_type', 'in' => [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING]]
				]
			],
			'manualinput_validator' => ['db scripts.manualinput_validator', 'required', 'not_empty',
				'use' => [CRegexValidator::class, []],
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['manualinput', 'in' => [ZBX_SCRIPT_MANUALINPUT_ENABLED]],
					['manualinput_validator_type', 'in' => [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING]]
				]
			],
			'dropdown_options' => ['db scripts.manualinput_validator', 'required', 'not_empty',
				'use' => [CUniqueValuesValidator::class],
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['manualinput', 'in' => [ZBX_SCRIPT_MANUALINPUT_ENABLED]],
					['manualinput_validator_type', 'in' => [ZBX_SCRIPT_MANUALINPUT_TYPE_LIST]]
				]
			],
			'enable_confirmation' => ['boolean'],
			'confirmation' => ['db scripts.confirmation', 'required', 'not_empty',
				'when' => [
					['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
					['enable_confirmation', true]
				]
			]
		]];
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS);
	}

	protected function doAction(): void {
		$script = $this->convertFormInputForApi($this->getInputAll() + ['groupid' => 0, 'confirmation' => '']);
		$result = (bool) API::Script()->create($script);
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Script added');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot add script'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function convertFormInputForApi(array $input): array {
		unset($input['enable_confirmation']);

		if (array_key_exists('dropdown_options', $input)) {
			$user_input_values = array_map('trim', explode(',', $input['dropdown_options']));
			$input['manualinput_validator'] = implode(',', $user_input_values);
			unset($input['dropdown_options']);
		}

		$renamed_fields = [
			'passphrase' => 'password',
			'commandipmi' => 'command',
			'script' => 'command'
		];

		foreach ($renamed_fields as $old_field => $new_field) {
			if (array_key_exists($old_field, $input)) {
				$input[$new_field] = $input[$old_field];
				unset($input[$old_field]);
			}
		}

		if (array_key_exists('parameters', $input)) {
			$input['parameters'] = $this->removeEmptyParameters($input['parameters']);
		}

		return $input;
	}

	protected function removeEmptyParameters(array $parameters): array {
		return array_filter($parameters,
			static fn ($parameter) => $parameter['name'] !== '' || $parameter['value'] !== ''
		);
	}
}
