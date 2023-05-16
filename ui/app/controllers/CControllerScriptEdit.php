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


class CControllerScriptEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'scriptid' =>	'db scripts.scriptid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
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

	protected function doAction(): void {
		// Default values.
		$data = [
			'scriptid' => null,
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
			'url' => '',
			'new_window' => ZBX_SCRIPT_URL_NEW_WINDOW_YES,
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
					'password', 'publickey', 'privatekey', 'menu_path', 'parameters', 'url', 'new_window'
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
				$data['url'] = $script['url'];
				$data['new_window'] = $script['new_window'];
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

		$data['parameters'] = array_values($data['parameters']);

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
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
