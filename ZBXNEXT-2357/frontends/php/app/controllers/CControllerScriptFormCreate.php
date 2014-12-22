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

class CControllerScriptFormCreate extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'name' =>				'fatal|db:scripts.name        |required_if:form,1',
			'type' =>				'fatal|db:scripts.type        |required_if:form,1|in_int:0,1',
			'execute_on' =>			'fatal|db:scripts.execute_on  |required_if:form,1|required_if:type,'.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.'|in_int:0,1',
			'command' =>			'fatal|db:scripts.command     |required_if:type,'.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
			'commandipmi' =>		'fatal|db:scripts.command     |required_if:type,'.ZBX_SCRIPT_TYPE_IPMI,
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
			access_deny();
		}
	}

	protected function doAction() {
		if ($this->hasInput('form')) {
			$this->data = array(
				'scriptid' => 0,
				'name' => $this->getInput('name'),
				'type' => $this->getInput('type'),
				'execute_on' => $this->getInput('execute_on'),
				'command' => $this->getInput('command'),
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
			$this->data = array(
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
		}

		$this->data['command'] = ($this->data['type'] == ZBX_SCRIPT_TYPE_IPMI) ? $this->getInput('commandipmi', '') : $this->data['command'];

		// get host group
		if ($this->data['groupid'] == 0) {
			$this->data['hostgroup'] = null;
		}
		else {
			$hostgroups = API::HostGroup()->get(array(
				'groupids' => array($this->data['groupid']),
				'output' => array('groupid', 'name')
			));
			$hostgroup = $hostgroups[0];

			$this->data['hostgroup'][] = array(
				'id' => $hostgroup['groupid'],
				'name' => $hostgroup['name']
			);
		}

		// get list of user groups
		$usergroups = API::UserGroup()->get(array(
			'output' => array('usrgrpid', 'name')
		));
		order_result($usergroups, 'name');
		$this->data['usergroups'] = $usergroups;

		$this->setResponse(new CControllerResponseData($this->data));
	}
}
