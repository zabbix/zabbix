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


class CControllerPopupScriptExec extends CController {

	protected function checkInput() {
		$fields = [
			'hostid' =>			'db hosts.hostid',
			'scriptid' =>		'db scripts.scriptid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (bool) API::Host()->get([
			'output' => [],
			'hostids' => $this->getInput('hostid')
		]);
	}

	protected function doAction() {
		$scriptid = $this->getInput('scriptid');
		$hostid = $this->getInput('hostid');

		$data = [
			'title' => _('Scripts'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$scripts = API::Script()->get([
			'scriptids' => $scriptid,
			'output' => ['name', 'command']
		]);

		if ($scripts) {
			$script = $scripts[0];

			$macros_data = CMacrosResolverHelper::resolve([
				'config' => 'scriptConfirmation',
				'data' => [$hostid => [$scriptid => $script['command']]]
			]);

			$data['title'] = $script['name'];
			$data['command'] = $macros_data[$hostid][$scriptid];

			$result = API::Script()->execute([
				'hostid' => $hostid,
				'scriptid' => $scriptid
			]);

			if (!$result) {
				error(_('Cannot execute script'));
			}
			elseif ($result['response'] === 'failed') {
				error($result['value']);
			}
			else {
				$data['message'] = $result['value'];
			}
		}
		else {
			error(_('No permissions to referred object or it does not exist!'));
		}

		$data['errors'] = getMessages();

		$this->setResponse(new CControllerResponseData($data));
	}
}
