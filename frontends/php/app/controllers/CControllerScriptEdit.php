<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	protected function checkInput() {
		$fields = array(
			'scriptid' =>			'db scripts.scriptid',
			'name' =>				'db scripts.name',
			'type' =>				'db scripts.type        |in 0,1',
			'execute_on' =>			'db scripts.execute_on  |in 0,1',
			'command' =>			'db scripts.command',
			'commandipmi' =>		'db scripts.command',
			'description' =>		'db scripts.description',
			'host_access' =>		'db scripts.host_access |in 2,3',
			'groupid' =>			'db scripts.groupid',
			'usrgrpid' =>			'db scripts.usrgrpid',
			'hgstype' =>			'                        in 0,1',
			'confirmation' =>		'db scripts.confirmation',
			'enable_confirmation' =>'                        in 1'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->hasInput('scriptid')) {
			return (bool)API::Script()->get(array(
				'output' => array(),
				'scriptids' => $this->getInput('scriptid'),
				'editable' => true
			));
		}
		else {
			return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
		}
	}

	protected function doAction() {
		// default values
		$data = array(
			'scriptid' => 0,
			'name' => '',
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
			'execute_on' => 0,
			'command' => '',
			'commandipmi' => '',
			'description' => '',
			'usrgrpid' => 0,
			'groupid' => 0,
			'host_access' => 0,
			'confirmation' => '',
			'enable_confirmation' => 0,
			'hgstype' => 0
		);

		// get values from the dabatase
		if ($this->hasInput('scriptid')) {
			$scripts = API::Script()->get(array(
				'output' => array('scriptid', 'name', 'type', 'execute_on', 'command', 'description', 'usrgrpid', 'groupid', 'host_access', 'confirmation'),
				'scriptids' => $this->getInput('scriptid')
			));
			$script = $scripts[0];

			$data = array(
				'scriptid' => $script['scriptid'],
				'name' => $script['name'],
				'type' => $script['type'],
				'execute_on' => $script['execute_on'],
				'command' => $script['type'] == ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT ? $script['command'] : '',
				'commandipmi' => $script['type'] == ZBX_SCRIPT_TYPE_IPMI ? $script['command'] : '',
				'description' => $script['description'],
				'usrgrpid' => $script['usrgrpid'],
				'groupid' => $script['groupid'],
				'host_access' => $script['host_access'],
				'confirmation' => $script['confirmation'],
				'enable_confirmation' => $script['confirmation'] !== '',
				'hgstype' => $script['groupid'] != 0 ? 1 : 0
			);
		}

		// overwrite with input variables
		$this->getInputs($data, array(
			'name',
			'type',
			'execute_on',
			'command',
			'commandipmi',
			'description',
			'usrgrpid',
			'groupid',
			'host_access',
			'confirmation',
			'enable_confirmation',
			'hgstype'
		));

		// get host group
		if ($data['groupid'] == 0) {
			$data['hostgroup'] = null;
		}
		else {
			$hostgroups = API::HostGroup()->get(array(
				'groupids' => array($data['groupid']),
				'output' => array('groupid', 'name')
			));
			$hostgroup = $hostgroups[0];

			$data['hostgroup'][] = array(
				'id' => $hostgroup['groupid'],
				'name' => $hostgroup['name']
			);
		}

		// get list of user groups
		$usergroups = API::UserGroup()->get(array(
			'output' => array('usrgrpid', 'name')
		));
		order_result($usergroups, 'name');
		$data['usergroups'] = $usergroups;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of scripts'));
		$this->setResponse($response);
	}
}
