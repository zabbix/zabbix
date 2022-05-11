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


class CControllerScriptCreate extends CController {

	protected function checkInput() {
		$fields = [
			'name' =>					'required|db scripts.name|not_empty',
			'scope' =>					'db scripts.scope| in '.implode(',', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]),
			'type' =>					'required|db scripts.type|in '.implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK]),
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
			'description' =>			'db scripts.description',
			'host_access' =>			'db scripts.host_access|in '.implode(',', [PERM_READ, PERM_READ_WRITE]),
			'groupid' =>				'db scripts.groupid',
			'usrgrpid' =>				'db scripts.usrgrpid',
			'hgstype' =>				'in 0,1',
			'confirmation' =>			'db scripts.confirmation|not_empty',
			'enable_confirmation' =>	'in 1',
			'form_refresh' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=script.edit');
					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot add script'));
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS);
	}

	protected function doAction() {
		$script = [];

		$this->getInputs($script, ['name', 'description', 'groupid']);
		$script['scope'] = $this->getInput('scope', ZBX_SCRIPT_SCOPE_ACTION);
		$script['type'] = $this->getInput('type', ZBX_SCRIPT_TYPE_WEBHOOK);

		if ($script['scope'] != ZBX_SCRIPT_SCOPE_ACTION) {
			$script['menu_path'] = trimPath($this->getInput('menu_path', ''));
			$script['host_access'] = $this->getInput('host_access', PERM_READ);
			$script['confirmation'] = $this->getInput('confirmation', '');
			$script['usrgrpid'] = $this->getInput('usrgrpid', 0);
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
		}

		if ($this->getInput('hgstype', 1) == 0) {
			$script['groupid'] = 0;
		}

		$result = (bool) API::Script()->create($script);

		if ($result) {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))
				->setArgument('action', 'script.list')
				->setArgument('page', CPagerHelper::loadPage('script.list', null))
			);
			$response->setFormData(['uncheck' => '1']);
			CMessageHelper::setSuccessTitle(_('Script added'));
		}
		else {
			$response = new CControllerResponseRedirect((new CUrl('zabbix.php'))->setArgument('action', 'script.edit'));
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot add script'));
		}

		$this->setResponse($response);
	}
}
