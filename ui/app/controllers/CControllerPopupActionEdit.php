<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once __DIR__ .'/../../include/actions.inc.php';

class CControllerPopupActionEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource' => 'in '.implode(',', [
					EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
					EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
				]),
			'g_actionid' => 'array_id',
			'actionid' => 'string',
			'filter_set' => 'string',
			'filter_rst' =>	'string',
			'add_condition' => 'string',
			'filter_name' =>'string',
			'new_condition' => 'string',
			'filter_status' =>'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_ACTIONS)) {
			return false;
		}

		if ($this->hasInput('actionid')) {
			$this->action = API::Action()->get([
				'output' => ['actionid', 'name', 'esc_period', 'eventsource', 'status', 'pause_suppressed', 'notify_if_canceled'],
				'actionids' => $this->getInput('actionid'),
				'selectOperations' => 'extend',
				'selectFilter' => 'extend'
			]);

			if (!$this->action) {
				return false;
			}
			$this->action = $this->action[0];
		}
		else {
			$this->action = null;
		}

		return true;

	}

	protected function doAction(): void {
		// todo : add functions for creating correct condition name

		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);

		if ($this->action !== null) {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->action['actionid'],
				'action' => [
					'name' => $this->action['name'],
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'status' => $this->action['status'],
					'operations' => $this->action['operations'],
					'filter' => $this->action['filter']
				]
			];
		}
		else {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->getInput('actionid', ''),
				'action' => [
					'name' => '',
					'esc_period' => DB::getDefault('actions', 'esc_period'),
					'eventsource' => $eventsource,
					'status' =>'',
					'operations' => [],
					'filter' => [
						'conditions' => []
					]
				],
				'formula' => $this->getInput('formula', '')
			];
		}

		$response = new CControllerResponseData($data);

		$this->setResponse($response);
	}
}
