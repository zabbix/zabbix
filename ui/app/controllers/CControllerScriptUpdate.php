<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


class CControllerScriptUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkInput(): bool {
		$fields = [
			'scriptid' =>				'fatal|required|db scripts.scriptid',
			'name' =>					'required|db scripts.name|not_empty',
			'scope' =>					'db scripts.scope| in '.implode(',', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]),
			'type' =>					'required|db scripts.type|in '.implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK, ZBX_SCRIPT_TYPE_URL]),
			'execute_on' =>				'db scripts.execute_on|in '.implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY]),
			'menu_path' =>				'db scripts.menu_path',
			'authtype' =>				'db scripts.authtype|in '.implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'username' =>				'db scripts.username',
			'password' =>				'db scripts.password',
			'publickey' =>				'db scripts.publickey',
			'privatekey' =>				'db scripts.privatekey',
			'passphrase' =>				'db scripts.password',
			'port' =>					'db scripts.port',
			'command' =>				'db scripts.command|flags '.P_CRLF,
			'commandipmi' =>			'db scripts.command|flags '.P_CRLF,
			'parameters' =>				'array',
			'script' => 				'db scripts.command|flags '.P_CRLF,
			'timeout' => 				'db scripts.timeout|time_unit '.implode(':', [1, SEC_PER_MIN]),
			'url' => 					'db scripts.url',
			'new_window' => 			'db scripts.new_window|in '.ZBX_SCRIPT_URL_NEW_WINDOW_YES,
			'description' =>			'db scripts.description',
			'host_access' =>			'db scripts.host_access|in '.implode(',', [PERM_READ, PERM_READ_WRITE]),
			'groupid' =>				'db scripts.groupid',
			'usrgrpid' =>				'db scripts.usrgrpid',
			'hgstype' =>				'in 0,1',
			'enable_user_input' =>		'db scripts.manualinput|in '.SCRIPT_MANUALINPUT_ENABLED,
			'input_prompt' =>			'db scripts.manualinput_prompt',
			'input_type' =>				'db scripts.manualinput_validator_type|in '.implode(',', [SCRIPT_MANUALINPUT_TYPE_STRING, SCRIPT_MANUALINPUT_TYPE_LIST]),
			'default_input' =>			'db scripts.manualinput_default_value|string',
			'input_validation' =>		'db scripts.manualinput_validator',
			'dropdown_options' =>		'db scripts.manualinput_validator',
			'enable_confirmation' =>	'in 1',
			'confirmation' =>			'db scripts.confirmation|not_empty'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			if ($this->getInput('scope') != ZBX_SCRIPT_SCOPE_ACTION && $this->hasInput('enable_user_input')) {
				if ($this->getInput('input_prompt', '') === '') {
					info(_s('Incorrect value for field "%1$s": %2$s.', _('input_prompt'), _('cannot be empty')));

					$ret = false;
				}

				if ($this->getInput('input_type') == SCRIPT_MANUALINPUT_TYPE_LIST
						&& $this->getInput('dropdown_options', '') === '') {
					info(_s('Incorrect value for field "%1$s": %2$s.', _('dropdown_options'), _('cannot be empty')));

					$ret = false;
				}
				else if ($this->getInput('input_type') == SCRIPT_MANUALINPUT_TYPE_STRING
						&& $this->getInput('input_validation', '') === '') {
					info(_s('Incorrect value for field "%1$s": %2$s.', _('input_validation'), _('cannot be empty')));

					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'title' => _('Cannot update script'),
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
		$result = false;
		$output = [];

		$this->getInputs($script, ['scriptid', 'name', 'description', 'groupid']);

		$script['scope'] = $this->getInput('scope', ZBX_SCRIPT_SCOPE_ACTION);
		$script['type'] = $this->getInput('type', ZBX_SCRIPT_TYPE_WEBHOOK);

		if ($script['scope'] != ZBX_SCRIPT_SCOPE_ACTION) {
			$script['menu_path'] = trimPath($this->getInput('menu_path', ''));
			$script['host_access'] = $this->getInput('host_access', PERM_READ);
			$script['confirmation'] = $this->getInput('confirmation', '');
			$script['usrgrpid'] = $this->getInput('usrgrpid', 0);

			$script['manualinput'] = $this->hasInput('enable_user_input')
				? SCRIPT_MANUALINPUT_ENABLED
				: SCRIPT_MANUALINPUT_DISABLED;

			if ($script['manualinput']) {
				$script['manualinput_validator_type'] = $this->getInput('input_type');

				if ($script['manualinput_validator_type'] == SCRIPT_MANUALINPUT_TYPE_LIST) {
					// Check if values are unique.
					$user_input_values = array_map('trim', explode(",", $this->getInput('dropdown_options')));

					if (array_unique($user_input_values) !== $user_input_values) {
						error(_('Dropdown options must be unique.'));
					}

					$script['manualinput_validator'] = implode(',', $user_input_values);
				}
				else {
					$default_input = trim($this->getInput('default_input', ''));
					$input_validation = $this->getInput('input_validation');
					$regular_expression = '/'.str_replace('/', '\/', $input_validation).'/';

					if (@preg_match($regular_expression, '') === false) {
						error(
							_s('Incorrect value for field "%1$s": %2$s.', _('input_validation'),
								_('invalid regular expression')
							));
					}
					elseif (!preg_match($regular_expression, $default_input)) {
						error(
							_s('Incorrect value for field "%1$s": %2$s.', 'default_input',
								_s('input does not match the provided pattern: %1$s', $input_validation)
							)
						);
					}

					$script['manualinput_default_value'] = trim($default_input);
					$script['manualinput_validator'] = $input_validation;
				}
				$script['manualinput_prompt'] = $this->getInput('input_prompt');
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
				$script['parameters'] = [];
				$parameters = $this->getInput('parameters', []);

				if (array_key_exists('name', $parameters) && array_key_exists('value', $parameters)) {
					$script['parameters'] = array_map(function ($name, $value) {
							return compact('name', 'value');
						},
						$parameters['name'],
						$parameters['value']
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

		if (count(filter_messages()) == 0) {
			$result = (bool) API::Script()->update($script);
		}

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
}
