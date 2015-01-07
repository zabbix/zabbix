<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerScriptFormEdit extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'scriptid' =>			'fatal|db:scripts.scriptid    |required',
			'name' =>				'fatal|db:scripts.name        |required_if:form,1',
			'type' =>				'fatal|db:scripts.type        |required_if:form,1|in_int:0,1',
			'execute_on' =>			'fatal|db:scripts.execute_on  |required_if:form,1|required_if:type,'.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.'|in_int:0,1',
			'command' =>			'fatal|db:scripts.command     |required_if:form,1',
			'commandipmi' =>		'fatal|db:scripts.command     |required_if:form,1',
			'description' =>		'fatal|db:scripts.description |required_if:form,1',
			'host_access' =>		'fatal|db:scripts.host_access |required_if:form,1|in_int:2,3',
			'groupid' =>			'fatal|db:scripts.groupid     |required_if:hgstype,1',
			'usrgrpid' =>			'fatal|db:scripts.usrgrpid    |required_if:form,1',
			'hgstype' =>			'fatal|                        required_if:form,1|in_int:0,1',
			'confirmation' =>		'fatal|db:scripts.confirmation|required_if:enable_confirmation,1',
			'enable_confirmation' =>'fatal|in_int:1'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $result;
	}


	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$scripts = API::Script()->get(array(
			'output' => array(),
			'scriptids' => $this->getInput('scriptid')
		));
		if (!$scripts) {
			return false;
		}

		return true;
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$data = array(
				'scriptid' => $this->getInput('scriptid'),
				'name' => $this->getInput('name'),
				'type' => $this->getInput('type'),
				'execute_on' => $this->getInput('execute_on'),
				'command' => $this->getInput('command'),
				'commandipmi' => $this->getInput('commandipmi'),
				'description' => $this->getInput('description'),
				'usrgrpid' => $this->getInput('usrgrpid'),
				'groupid' => $this->getInput('groupid'),
				'host_access' => $this->getInput('host_access'),
				'confirmation' => $this->getInput('confirmation'),
				'enable_confirmation' => $this->getInput('enable_confirmation'),
				'hgstype' => $this->getInput('groupid') != 0 ? 1 : 0
			);
		}
		else {
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
