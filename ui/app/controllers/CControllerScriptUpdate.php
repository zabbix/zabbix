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


class CControllerScriptUpdate extends CController {

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
					'title' => _('Cannot update script'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	public static function getValidationRules(): array {
		$api_uniq = ['script.get', ['name' => '{name}', 'menu_path' => '{menu_path}'], 'scriptid'];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'scriptid' => ['db scripts.scriptid', 'required'],
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
			'execute_on' => ['db scripts.execute_on',
				'in' => [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT]]
			],
			'menu_path' => ['db scripts.menu_path',
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
			'password' => ['db scripts.password'],
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
			'passphrase' => ['db scripts.password'],
			'port' => ['db scripts.port',
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
			'parameters' => ['objects', 'uniq' => ['name'], 'fields' => [
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
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_URL]]
			],
			'new_window' => ['db scripts.new_window',
				'in' => [ZBX_SCRIPT_URL_NEW_WINDOW_NO, ZBX_SCRIPT_URL_NEW_WINDOW_YES],
				'when' => ['type', 'in' => [ZBX_SCRIPT_TYPE_URL]]
			],
			'description' => ['db scripts.description'],
			'host_access' => ['db scripts.host_access',
				'in' => [PERM_READ, PERM_READ_WRITE],
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
			],
			'groupid' => ['db scripts.groupid'],
			'usrgrpid' => ['db scripts.usrgrpid',
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]]
			],
			'manualinput' => ['db scripts.manualinput', 'required',
				'when' => ['scope', 'in' => [ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]],
				'in' => [ZBX_SCRIPT_MANUALINPUT_DISABLED, ZBX_SCRIPT_MANUALINPUT_ENABLED]
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
		$script = [];

		$this->getInputs($script, ['scriptid', 'name', 'description']);

		$script['groupid'] = $this->getInput('groupid', 0);
		$script['scope'] = $this->getInput('scope', ZBX_SCRIPT_SCOPE_ACTION);
		$script['type'] = $this->getInput('type', ZBX_SCRIPT_TYPE_WEBHOOK);

		if ($script['scope'] != ZBX_SCRIPT_SCOPE_ACTION) {
			$script['menu_path'] = trimPath($this->getInput('menu_path', ''));
			$script['host_access'] = $this->getInput('host_access', PERM_READ);
			$script['confirmation'] = $this->getInput('confirmation', '');
			$script['usrgrpid'] = $this->getInput('usrgrpid', 0);

			$script['manualinput'] =
				$this->getInput('manualinput', ZBX_SCRIPT_MANUALINPUT_DISABLED) == ZBX_SCRIPT_MANUALINPUT_ENABLED
					? ZBX_SCRIPT_MANUALINPUT_ENABLED
					: ZBX_SCRIPT_MANUALINPUT_DISABLED;

			if ($script['manualinput'] == ZBX_SCRIPT_MANUALINPUT_ENABLED) {
				$script['manualinput_prompt'] = $this->getInput('manualinput_prompt');
				$script['manualinput_validator_type'] = $this->getInput('manualinput_validator_type');

				if ($script['manualinput_validator_type'] == ZBX_SCRIPT_MANUALINPUT_TYPE_LIST) {
					$user_input_values = array_map('trim', explode(',', $this->getInput('dropdown_options', [])));
					$script['manualinput_validator'] = implode(',', $user_input_values);
				}
				else {
					$script['manualinput_validator'] = $this->getInput('manualinput_validator', '');
					$script['manualinput_default_value'] = trim($this->getInput('manualinput_default_value'));
				}
			}
		}

		switch ($script['type']) {
			case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
				$script['command'] = $this->getInput('command', '');
				$script['execute_on'] = $this->getInput('execute_on', ZBX_SCRIPT_EXECUTE_ON_PROXY);
				break;

			case ZBX_SCRIPT_TYPE_IPMI:
				$script['command'] = $this->getInput('commandipmi', '');
				break;

			case ZBX_SCRIPT_TYPE_SSH:
				$script['command'] = $this->getInput('command', '');
				$script['username'] = $this->getInput('username', '');
				$script['port'] = $this->getInput('port', '');
				$script['authtype'] = $this->getInput('authtype', ITEM_AUTHTYPE_PASSWORD);

				if ($script['authtype'] == ITEM_AUTHTYPE_PASSWORD) {
					$script['password'] = $this->getInput('password', '');
				}
				else {
					$script['publickey'] = $this->getInput('publickey', '');
					$script['privatekey'] = $this->getInput('privatekey', '');
					$script['password'] = $this->getInput('passphrase', '');
				}
				break;

			case ZBX_SCRIPT_TYPE_TELNET:
				$script['command'] = $this->getInput('command', '');
				$script['username'] = $this->getInput('username', '');
				$script['password'] = $this->getInput('password', '');
				$script['port'] = $this->getInput('port', '');
				break;

			case ZBX_SCRIPT_TYPE_WEBHOOK:
				$script['command'] = $this->getInput('script', '');
				$script['timeout'] = $this->getInput('timeout', DB::getDefault('scripts', 'timeout'));
				$script['parameters'] = $this->removeEmptyParameters($this->getInput('parameters', []));
				break;

			case ZBX_SCRIPT_TYPE_URL:
				$script['url'] = $this->getInput('url', '');
				$script['new_window'] = $this->getInput('new_window', ZBX_SCRIPT_URL_NEW_WINDOW_NO);
				break;
		}

		$result = (bool) API::Script()->update($script);
		$output = [];

		if ($result) {
			$output['success']['title'] = _('Script updated');

			if ($messages = get_and_clear_messages()) {
				$output['success']['messages'] = array_column($messages, 'message');
			}
		}
		else {
			$output['error'] = [
				'title' => _('Cannot update script'),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}

	protected function removeEmptyParameters(array $parameters): array {
		return array_filter($parameters,
			static fn ($parameter) => $parameter['name'] !== '' || $parameter['value'] !== ''
		);
	}
}
