<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerPopupScriptExec extends CController {

	protected function checkInput() {
		$fields = [
			'scriptid' =>		'db scripts.scriptid',
			'hostid' =>			'db hosts.hostid',
			'eventid' =>		'db events.eventid',
			'manualinput' =>	'string'
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

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::ACTIONS_EXECUTE_SCRIPTS)) {
			return false;
		}

		if ($this->hasInput('hostid')) {
			return (bool) API::Host()->get([
				'output' => [],
				'hostids' => $this->getInput('hostid')
			]);
		}

		if ($this->hasInput('eventid')) {
			return (bool) API::Event()->get([
				'output' => [],
				'eventids' => $this->getInput('eventid')
			]);
		}

		return false;
	}

	protected function doAction() {
		$scriptid = $this->getInput('scriptid');
		$hostid = $this->getInput('hostid', '');
		$eventid = $this->getInput('eventid', '');
		$manualinput = $this->hasInput('manualinput') ? $this->getInput('manualinput') : null;

		$data = [
			'title' => _('Scripts'),
			'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
			'output' => '',
			'debug' => [],
			'messages' => null,
			'success' => false,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$scripts = API::Script()->get([
			'output' => ['name', 'type'],
			'scriptids' => $scriptid
		]);

		if ($scripts) {
			$script = $scripts[0];
			$data['title'] = $script['name'];
			$data['type'] =  $script['type'];

			$execution_params = ['scriptid' => $scriptid];

			if ($hostid) {
				$execution_params['hostid'] = $hostid;
			}
			elseif ($eventid) {
				$execution_params['eventid'] = $eventid;
			}

			if ($manualinput !== null) {
				$execution_params['manualinput'] = $manualinput;
			}

			$result = API::Script()->execute($execution_params);

			if ($result) {
				if ($data['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
					$value = json_decode($result['value']);
					$result['value'] = json_last_error() ? $result['value'] : json_encode($value, JSON_PRETTY_PRINT);
				}

				$data['output'] = $result['value'];
				$data['debug'] = $result['debug'];
				$data['success'] = true;
				info(_('Script execution successful.'));
			}
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));
		}

		$data['messages'] = getMessages($data['success'], $data['success'] ? null : _('Cannot execute script.'));

		$this->setResponse(new CControllerResponseData($data));
	}
}
