<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$fields = [
			'scriptid' =>				'fatal|required|db scripts.scriptid',
			'name' =>					'               db scripts.name',
			'type' =>					'               db scripts.type        |in '.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.','.ZBX_SCRIPT_TYPE_IPMI,
			'execute_on' =>				'               db scripts.execute_on  |in '.ZBX_SCRIPT_EXECUTE_ON_AGENT.','.ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'command' =>				'               db scripts.command',
			'commandipmi' =>			'               db scripts.command',
			'description' =>			'               db scripts.description',
			'host_access' =>			'               db scripts.host_access |in 0,1,2,3',
			'groupid' =>				'               db scripts.groupid',
			'usrgrpid' =>				'               db scripts.usrgrpid',
			'hgstype' =>				'                                       in 0,1',
			'confirmation' =>			'               db scripts.confirmation|not_empty',
			'enable_confirmation' =>	'                                       in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=script.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot update script'));
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
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		return (bool) API::Script()->get([
			'output' => [],
			'scriptids' => $this->getInput('scriptid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$script = [];

		$this->getInputs($script, ['scriptid', 'name', 'type', 'execute_on', 'command', 'description', 'usrgrpid',
			'groupid', 'host_access'
		]);
		$script['confirmation'] = $this->getInput('confirmation', '');

		if ($this->getInput('type', ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT) == ZBX_SCRIPT_TYPE_IPMI
				&& $this->hasInput('commandipmi')) {
			$script['command'] = $this->getInput('commandipmi');
		}

		if ($this->getInput('hgstype', 1) == 0) {
			$script['groupid'] = 0;
		}

		DBstart();

		$result = API::Script()->update($script);

		if ($result) {
			$scriptId = reset($result['scriptids']);
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_SCRIPT,
				'Name ['.$this->getInput('name', '').'] id ['.$scriptId.']'
			);
		}

		$result = DBend($result);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=script.list&uncheck=1');
			$response->setMessageOk(_('Script updated'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=script.edit&scriptid='.$this->getInput('scriptid'));
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot update script'));
		}
		$this->setResponse($response);
	}
}
