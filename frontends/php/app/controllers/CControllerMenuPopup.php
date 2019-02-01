<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CControllerMenuPopup extends CController {

	protected function checkInput() {
		$fields = [
			'type' => 'required|in host',
			'data' => 'required|array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$data = $this->getInput('data');

		$output = [];

		switch ($this->getInput('type')) {
			case 'host':
				$has_goto = !array_key_exists('has_goto', $data) || $data['has_goto'];

				$hosts = $has_goto
					? API::Host()->get([
						'output' => ['hostid', 'status'],
						'selectGraphs' => API_OUTPUT_COUNT,
						'selectScreens' => API_OUTPUT_COUNT,
						'hostids' => $data['hostid']
					])
					: API::Host()->get([
						'output' => ['hostid'],
						'hostids' => $data['hostid']
					]);

				if ($hosts) {
					$scripts = API::Script()->getScriptsByHosts([$data['hostid']])[$data['hostid']];

					$output['data'] = CMenuPopupHelper::getHost($hosts[0], $scripts, (bool) $has_goto);
				}
				else {
					error(_('No permissions to referred object or it does not exist!'));
				}
				break;
		}

		if (($messages = getMessages()) !== null) {
			$output['errors'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
	}
}
