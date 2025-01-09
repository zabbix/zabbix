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


class CControllerScriptCreate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>						'required|db scripts.name|not_empty',
			'scope' =>						'db scripts.scope| in '.implode(',', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]),
			'type' =>						'required|db scripts.type|in '.implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK, ZBX_SCRIPT_TYPE_URL]),
			'execute_on' =>					'db scripts.execute_on|in '.implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY]),
			'menu_path' =>					'db scripts.menu_path',
			'authtype' =>					'db scripts.authtype|in '.implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'username' =>					'db scripts.username',
			'password' =>					'db scripts.password',
			'publickey' =>					'db scripts.publickey',
			'privatekey' =>					'db scripts.privatekey',
			'passphrase' =>					'db scripts.password',
			'port' =>						'db scripts.port',
			'command' =>					'db scripts.command|flags '.P_CRLF,
			'commandipmi' =>				'db scripts.command|flags '.P_CRLF,
			'parameters' =>					'array',
			'script' => 					'db scripts.command|flags '.P_CRLF,
			'timeout' => 					'db scripts.timeout|time_unit '.implode(':', [1, SEC_PER_MIN]),
			'url' => 						'db scripts.url',
			'new_window' => 				'db scripts.new_window|in '.ZBX_SCRIPT_URL_NEW_WINDOW_YES,
			'description' =>				'db scripts.description',
			'host_access' =>				'db scripts.host_access|in '.implode(',', [PERM_READ, PERM_READ_WRITE]),
			'groupid' =>					'db scripts.groupid',
			'usrgrpid' =>					'db scripts.usrgrpid',
			'hgstype' =>					'in 0,1',
			'manualinput' =>				'db scripts.manualinput|in '.ZBX_SCRIPT_MANUALINPUT_ENABLED,
			'manualinput_prompt' =>			'db scripts.manualinput_prompt',
			'manualinput_validator_type' =>	'db scripts.manualinput_validator_type|in '.implode(',', [ZBX_SCRIPT_MANUALINPUT_TYPE_STRING, ZBX_SCRIPT_MANUALINPUT_TYPE_LIST]),
			'manualinput_default_value' =>	'db scripts.manualinput_default_value|string',
			'manualinput_validator' =>		'db scripts.manualinput_validator',
			'dropdown_options' =>			'db scripts.manualinput_validator',
			'enable_confirmation' =>		'in 1',
			'confirmation' =>				'db scripts.confirmation|not_empty'
		];

		$ret = $this->validateInput($fields);

		if (!CSettingsHelper::isGlobalScriptsEnabled()
				&& $this->getInput('execute_on', ZBX_SCRIPT_EXECUTE_ON_SERVER) == ZBX_SCRIPT_EXECUTE_ON_SERVER) {
			error(_('Global script execution on Zabbix server is disabled by server configuration.'));

			$ret = false;
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot add script'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS);
	}

	protected function doAction(): void {
		$script = [];

		$this->getInputs($script, ['name', 'description', 'groupid']);

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
				$parameters = $this->getInput('parameters', []);

				if (array_key_exists('name', $parameters) && array_key_exists('value', $parameters)) {
					$script['parameters'] = array_map(function ($name, $value) {
							return compact('name', 'value');
						}, $parameters['name'], $parameters['value']
					);
				}
				break;

			case ZBX_SCRIPT_TYPE_URL:
				$script['url'] = $this->getInput('url', '');
				$script['new_window'] = $this->hasInput('new_window')
					? ZBX_SCRIPT_URL_NEW_WINDOW_YES
					: ZBX_SCRIPT_URL_NEW_WINDOW_NO;
				break;
		}

		if ($this->getInput('hgstype', 1) == 0) {
			$script['groupid'] = 0;
		}

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
}
