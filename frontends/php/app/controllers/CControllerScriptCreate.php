<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
			'name' =>					'db scripts.name',
			'type' =>					'db scripts.type        |in '.ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT.','.ZBX_SCRIPT_TYPE_IPMI,
			'execute_on' =>				'db scripts.execute_on  |in '.ZBX_SCRIPT_EXECUTE_ON_AGENT.','.ZBX_SCRIPT_EXECUTE_ON_SERVER.','.ZBX_SCRIPT_EXECUTE_ON_PROXY,
			'command' =>				'db scripts.command     |flags '.P_CRLF,
			'commandipmi' =>			'db scripts.command     |flags '.P_CRLF,
			'description' =>			'db scripts.description',
			'host_access' =>			'db scripts.host_access |in '.PERM_READ.','.PERM_READ_WRITE,
			'groupid' =>				'db scripts.groupid',
			'usrgrpid' =>				'db scripts.usrgrpid',
			'hgstype' =>				'                        in 0,1',
			'confirmation' =>			'db scripts.confirmation|not_empty',
			'enable_confirmation' =>	'                        in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect('zabbix.php?action=script.edit');
					$response->setFormData($this->getInputAll());
					$response->setMessageError(_('Cannot add script'));
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
		return ($this->getUserType() == USER_TYPE_SUPER_ADMIN);
	}

	protected function doAction() {
		$script = [];

		$this->getInputs($script, ['command', 'description', 'usrgrpid', 'groupid', 'host_access', 'confirmation']);
		$script['name'] = trimPath($this->getInput('name', ''));
		$script['type'] = $this->getInput('type', ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT);
		$script['execute_on'] = $this->getInput('execute_on', ZBX_SCRIPT_EXECUTE_ON_SERVER);

		if ($script['type'] == ZBX_SCRIPT_TYPE_IPMI) {
			if ($this->hasInput('commandipmi')) {
				$script['command'] = $this->getInput('commandipmi');
			}
			$script['execute_on'] = ZBX_SCRIPT_EXECUTE_ON_SERVER;
		}

		if ($this->getInput('hgstype', 1) == 0) {
			$script['groupid'] = 0;
		}

		$result = (bool) API::Script()->create($script);

		if ($result) {
			$response = new CControllerResponseRedirect('zabbix.php?action=script.list&uncheck=1');
			$response->setMessageOk(_('Script added'));
		}
		else {
			$response = new CControllerResponseRedirect('zabbix.php?action=script.edit');
			$response->setFormData($this->getInputAll());
			$response->setMessageError(_('Cannot add script'));
		}
		$this->setResponse($response);
	}
}
