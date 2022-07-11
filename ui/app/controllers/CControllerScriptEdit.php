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


class CControllerScriptEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'scriptid' =>				'db scripts.scriptid',
			'name' =>					'db scripts.name',
			'scope' =>					'db scripts.scope| in '.implode(',', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]),
			'type' =>					'db scripts.type|in '.implode(',', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH, ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK]),
			'execute_on' =>				'db scripts.execute_on|in '.implode(',', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER, ZBX_SCRIPT_EXECUTE_ON_PROXY]),
			'menu_path' =>				'db scripts.menu_path',
			'authtype' =>				'db scripts.authtype|in '.implode(',', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]),
			'username' =>				'db scripts.username',
			'password' =>				'db scripts.password',
			'publickey' =>				'db scripts.publickey',
			'privatekey' =>				'db scripts.privatekey',
			'passphrase' =>				'db scripts.password',
			'port' =>					'db scripts.port',
			'command' =>				'db scripts.command',
			'commandipmi' =>			'db scripts.command',
			'parameters' =>				'array',
			'script' => 				'db scripts.command',
			'timeout' => 				'db media_type.timeout',
			'description' =>			'db scripts.description',
			'host_access' =>			'db scripts.host_access|in '.implode(',', [PERM_READ, PERM_READ_WRITE]),
			'groupid' =>				'db scripts.groupid',
			'usrgrpid' =>				'db scripts.usrgrpid',
			'hgstype' =>				'in 0,1',
			'confirmation' =>			'db scripts.confirmation',
			'enable_confirmation' =>	'in 1',
			'form_refresh' =>			'int32'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_SCRIPTS)) {
			return false;
		}

		if ($this->hasInput('scriptid')) {
			return (bool) API::Script()->get([
				'output' => [],
				'scriptids' => $this->getInput('scriptid'),
				'editable' => true
			]);
		}

		return true;
	}

	protected function doAction() {
		// Default values.
		$data = [
			'sid' => $this->getUserSID(),
			'scriptid' => 0,
			'name' => '',
			'scope' => ZBX_SCRIPT_SCOPE_ACTION,
			'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
			'menu_path' => '',
			'authtype' => ITEM_AUTHTYPE_PASSWORD,
			'username' => '',
			'password' => '',
			'passphrase' => '',
			'publickey' => '',
			'privatekey' => '',
			'port' => '',
			'command' => '',
			'commandipmi' => '',
			'parameters' => [],
			'script' => '',
			'timeout' => DB::getDefault('scripts', 'timeout'),
			'description' => '',
			'usrgrpid' => 0,
			'groupid' => 0,
			'host_access' => PERM_READ,
			'confirmation' => '',
			'enable_confirmation' => false,
			'hgstype' => 0,
			'actions' => []
		];

		// Get values from the database.
		if ($this->hasInput('scriptid')) {
			$scripts = API::Script()->get([
				'output' => ['scriptid', 'name', 'command', 'host_access', 'usrgrpid', 'groupid', 'description',
					'confirmation', 'type', 'execute_on', 'timeout', 'scope', 'port', 'authtype', 'username',
					'password', 'publickey', 'privatekey', 'menu_path', 'parameters'
				],
				'scriptids' => $this->getInput('scriptid'),
				'selectActions' => []
			]);

			if ($scripts) {
				$script = $scripts[0];

				$data['scriptid'] = $script['scriptid'];
				$data['name'] = $script['name'];
				$data['command'] = ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
						|| $script['type'] == ZBX_SCRIPT_TYPE_SSH
						|| $script['type'] == ZBX_SCRIPT_TYPE_TELNET)
					? $script['command']
					: '';
				$data['commandipmi'] = ($script['type'] == ZBX_SCRIPT_TYPE_IPMI) ? $script['command'] : '';
				$data['script'] = ($script['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) ? $script['command'] : '';
				$data['host_access'] = $script['host_access'];
				$data['usrgrpid'] = $script['usrgrpid'];
				$data['hgstype'] = ($script['groupid'] != 0) ? 1 : 0;
				$data['groupid'] = $script['groupid'];
				$data['description'] = $script['description'];
				$data['enable_confirmation'] = ($script['confirmation'] !== '');
				$data['confirmation'] = $script['confirmation'];
				$data['type'] = $script['type'];
				$data['execute_on'] = $script['execute_on'];
				$data['timeout'] = $script['timeout'];
				$data['scope'] = $script['scope'];
				$data['authtype'] = $script['authtype'];
				$data['username'] = $script['username'];
				$data['password'] = ($script['authtype'] == ITEM_AUTHTYPE_PASSWORD) ? $script['password'] : '';
				$data['passphrase'] = ($script['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) ? $script['password'] : '';
				$data['publickey'] = $script['publickey'];
				$data['privatekey'] = $script['privatekey'];
				$data['port'] = $script['port'];
				$data['menu_path'] = $script['menu_path'];
				$data['parameters'] = $script['parameters'];
				$data['actions'] = $script['actions'];

				if ($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
					CArrayHelper::sort($data['parameters'], ['name']);
				}
			}
		}

		// Overwrite with input variables.
		$this->getInputs($data, ['name', 'command', 'commandipmi', 'script', 'host_access', 'usrgrpid', 'hgstype',
			'groupid', 'description', 'enable_confirmation', 'confirmation', 'type', 'execute_on', 'timeout', 'scope',
			'port', 'authtype', 'username', 'password', 'publickey', 'privatekey', 'passphrase', 'menu_path',
			'parameters'
		]);

		if ($this->hasInput('form_refresh') && array_key_exists('name', $data['parameters'])
				&& array_key_exists('value', $data['parameters'])) {
			$data['parameters'] = array_map(function ($name, $value) {
					return compact('name', 'value');
				}, $data['parameters']['name'], $data['parameters']['value']
			);
		}

		// Get host group.
		if ($data['groupid'] == 0) {
			$data['hostgroup'] = null;
		}
		else {
			$hostgroups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => [$data['groupid']]
			]);
			$hostgroup = $hostgroups[0];

			$data['hostgroup'][] = [
				'id' => $hostgroup['groupid'],
				'name' => $hostgroup['name']
			];
		}

		// Get list of user groups.
		$usergroups = API::UserGroup()->get([
			'output' => ['usrgrpid', 'name']
		]);

		CArrayHelper::sort($usergroups, ['name']);

		$data['usergroups'] = array_column($usergroups, 'name', 'usrgrpid');

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
