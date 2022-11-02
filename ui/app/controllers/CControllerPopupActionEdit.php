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


class CControllerPopupActionEdit extends CController {

	protected function checkInput(): bool {
		$eventsource = [
			EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
			EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
		];

		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', $eventsource),
			'actionid' =>		'db actions.actionid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		switch ($this->getInput('eventsource')) {
			case EVENT_SOURCE_TRIGGERS:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
				break;

			case EVENT_SOURCE_DISCOVERY:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);
				break;

			case EVENT_SOURCE_AUTOREGISTRATION:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);
				break;

			case EVENT_SOURCE_INTERNAL:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);
				break;

			case EVENT_SOURCE_SERVICE:
				$has_permission =  $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
				break;
		}

		if (!$has_permission) {
			return false;
		}

		if ($this->hasInput('actionid')) {
			$this->action = API::Action()->get([
				'output' => [
					'actionid', 'name', 'esc_period', 'eventsource', 'status', 'pause_suppressed', 'notify_if_canceled'
				],
				'actionids' => $this->getInput('actionid'),
				'selectOperations' => 'extend',
				'selectRecoveryOperations' => 'extend',
				'selectUpdateOperations' => 'extend',
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
		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);

		if ($this->action !== null) {
			$formula = array_key_exists('formula', $this->action['filter'])
				? $this->action['filter']['formula']
				: '';

			sortOperations($eventsource, $this->action['operations']);

			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->action['actionid'],
				'action' => [
					'name' => $this->action['name'],
					'esc_period' => $this->action['esc_period'],
					'eventsource' => $eventsource,
					'status' => $this->action['status'],
					'operations' => $this->action['operations'],
					'recovery_operations' => $this->action['recovery_operations'],
					'update_operations' => $this->action['update_operations'],
					'filter' => $this->action['filter'],
					'pause_suppressed' => $this->action['pause_suppressed'],
					'notify_if_canceled' =>  $this->action['notify_if_canceled']
				],
				'formula' => $formula,
				'allowedOperations' => getAllowedOperations($eventsource)
			];
			foreach ($data['action']['filter']['conditions'] as  $row_index => &$condition) {
				$condition_names = actionConditionValueToString([$data['action']]);
				$data['condition_name'][] = $condition_names[0][$row_index];
				$condition += [
					'row_index' => $row_index,
					'name' => $condition_names[0][$row_index]
				];
			}
			unset ($condition);

			$data['action']['filter']['conditions'] = CConditionHelper::sortConditionsByFormulaId(
				$data['action']['filter']['conditions']
			);
		}
		else {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->getInput('actionid', ''),
				'action' => [
					'name' => '',
					'esc_period' => DB::getDefault('actions', 'esc_period'),
					'eventsource' => $eventsource,
					'status' => '',
					'operations' => [],
					'recovery_operations' => [],
					'update_operations' => [],
					'filter' => [
						'conditions' => [],
						'evaltype' => ''
					],
					'pause_suppressed' => ACTION_PAUSE_SUPPRESSED_TRUE,
					'notify_if_canceled' =>  ACTION_NOTIFY_IF_CANCELED_TRUE
				],
				'formula' => $this->getInput('formula', ''),
				'allowedOperations' => getAllowedOperations($eventsource)
			];
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
