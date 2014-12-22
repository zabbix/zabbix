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

class CControllerScriptUpdate extends CController {

	protected function checkInput() {
		$fields = array(
			'form' =>				'fatal|in_int:1',
			'scriptid' =>			'fatal|db:scripts.scriptid    |required',
			'name' =>				'      db:scripts.name        |required|not_empty',
			'type' =>				'fatal|db:scripts.type        |required|in_int:0,1',
			'execute_on' =>			'fatal|db:scripts.execute_on  |required|required_if:type,'.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.'|in_int:0,1',
			'command' =>			'      db:scripts.command     |required_if:type,'.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
			'commandipmi' =>		'      db:scripts.command     |required_if:type,'.ZBX_SCRIPT_TYPE_IPMI,
			'description' =>		'      db:scripts.description |required',
			'host_access' =>		'fatal|db:scripts.host_access |required|in_int:0,1,2,3',
			'groupid' =>			'fatal|db:scripts.groupid     |required_if:hgstype,1',
			'usrgrpid' =>			'fatal|db:scripts.usrgrpid    |required',
			'hgstype' =>			'fatal|                        required|in_int:0,1',
			'confirmation' =>		'      db:scripts.confirmation|required_if:enable_confirmation,1|not_empty',
			'enable_confirmation' =>'fatal|in_int:1'
		);

		$result = $this->validateInput($fields);

		if (!$result) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('scripts.php?action=script.formedit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update script'));
					$this->setResponse($response);
					break;
				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $result;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			access_deny();
		}

		$scripts = API::Script()->get(array(
			'scriptids' => $this->getInput('scriptid'),
			'output' => array()
		));
		if (!$scripts) {
			access_deny();
		}
	}

	protected function doAction() {
		$script = array(
			'scriptid' => $this->getInput('scriptid'),
			'name' => $this->getInput('name'),
			'type' => $this->getInput('type'),
			'execute_on' => $this->getInput('execute_on'),
			'command' =>  ($this->getInput('type') == ZBX_SCRIPT_TYPE_IPMI) ? $this->getInput('commandipmi') : $this->getInput('command'),
			'description' => $this->getInput('description'),
			'usrgrpid' => $this->getInput('usrgrpid'),
			'groupid' => ($this->getInput('hgstype') == 0) ? 0 : $this->getInput('groupid'),
			'host_access' => $this->getInput('host_access'),
			'confirmation' => $this->getInput('confirmation', '')
		);

		DBstart();

		$result = API::Script()->update($script);

		if ($result) {
			$scriptId = reset($result['scriptids']);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCRIPT, ' Name ['.$this->getInput('name').'] id ['.$scriptId.']');
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('scripts.php?action=script.list&uncheck=1');
			$response->setMessageOk(_('Script updated'));
		}
		else {
			$response = new CControllerResponseRedirect('scripts.php?action=script.formedit&scriptid='.$this->getInput('scriptid'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update script'));
		}
		$this->setResponse($response);
	}
}
