<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
			'type' =>					'db scripts.type|in '.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.','.ZBX_SCRIPT_TYPE_IPMI.','.ZBX_SCRIPT_TYPE_WEBHOOK,
			'execute_on' =>				'db scripts.execute_on|in '.ZBX_SCRIPT_EXECUTE_ON_AGENT.','.ZBX_SCRIPT_EXECUTE_ON_SERVER.','.ZBX_SCRIPT_EXECUTE_ON_PROXY,
			'command' =>				'db scripts.command',
			'commandipmi' =>			'db scripts.command',
			'parameters' =>				'array',
			'script' => 				'db scripts.command',
			'timeout' => 				'db media_type.timeout',
			'description' =>			'db scripts.description',
			'host_access' =>			'db scripts.host_access|in '.PERM_READ.','.PERM_READ_WRITE,
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
		// default values
		$data = [
			'sid' => $this->getUserSID(),
			'scriptid' => 0,
			'name' => '',
			'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
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
			'hgstype' => 0
		];

		// get values from the dabatase
		if ($this->hasInput('scriptid')) {
			$scripts = API::Script()->get([
				'output' => ['scriptid', 'name', 'type', 'execute_on', 'command', 'description', 'usrgrpid', 'groupid',
					'host_access', 'confirmation', 'timeout', 'parameters'
				],
				'scriptids' => $this->getInput('scriptid')
			]);
			if ($scripts) {
				$script = $scripts[0];

				$data['scriptid'] = $script['scriptid'];
				$data['name'] = $script['name'];
				$data['type'] = $script['type'];
				$data['execute_on'] = $script['execute_on'];
				$data['command'] = ($script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) ? $script['command'] : '';
				$data['commandipmi'] = ($script['type'] == ZBX_SCRIPT_TYPE_IPMI) ? $script['command'] : '';
				$data['parameters'] = $script['parameters'];
				$data['script'] = ($script['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) ? $script['command'] : '';
				$data['timeout'] = $script['timeout'];
				$data['description'] = $script['description'];
				$data['usrgrpid'] = $script['usrgrpid'];
				$data['groupid'] = $script['groupid'];
				$data['host_access'] = $script['host_access'];
				$data['confirmation'] = $script['confirmation'];
				$data['enable_confirmation'] = ($script['confirmation'] !== '');
				$data['hgstype'] = ($script['groupid'] != 0) ? 1 : 0;
			}
		}

		// overwrite with input variables
		$this->getInputs($data, ['name', 'type', 'execute_on', 'command', 'commandipmi', 'parameters', 'script',
			'timeout', 'description', 'usrgrpid', 'groupid', 'host_access', 'confirmation', 'enable_confirmation',
			'hgstype'
		]);

		if ($this->hasInput('form_refresh') && array_key_exists('name', $data['parameters'])
				&& array_key_exists('value', $data['parameters'])) {
			$data['parameters'] = array_map(function ($name, $value) {
					return compact('name', 'value');
				}, $data['parameters']['name'], $data['parameters']['value']
			);
		}

		// get host group
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

		// get list of user groups
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
