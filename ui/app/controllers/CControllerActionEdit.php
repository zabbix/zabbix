<?php declare(strict_types = 0);
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


class CControllerActionEdit extends CController {

	/**
	 * @var mixed
	 */
	private $action;

	protected function init() {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
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
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);
				break;

			case EVENT_SOURCE_AUTOREGISTRATION:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);
				break;

			case EVENT_SOURCE_INTERNAL:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);
				break;

			case EVENT_SOURCE_SERVICE:
				$has_permission = $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
				break;
		}

		if (!$has_permission) {
			return false;
		}

		$this->action = null;

		if ($this->hasInput('actionid')) {
			$operation_options = [
				'operationtype', 'opcommand', 'opcommand_grp', 'opcommand_hst', 'opmessage', 'opmessage_usr',
				'opmessage_grp'
			];

			$db_actions = API::Action()->get([
				'output' => ['actionid', 'name', 'esc_period', 'eventsource', 'status', 'pause_suppressed',
					'notify_if_canceled', 'pause_symptoms'
				],
				'actionids' => $this->getInput('actionid'),
				'selectOperations' => ['operationtype', 'esc_step_from', 'esc_step_to', 'esc_period', 'evaltype',
					'opcommand', 'opcommand_grp', 'opcommand_hst', 'opgroup', 'opmessage', 'optemplate', 'opinventory',
					'opconditions', 'opmessage_usr', 'opmessage_grp', 'optag'
				],
				'selectRecoveryOperations' => $operation_options,
				'selectUpdateOperations' => $operation_options,
				'selectFilter' => ['conditions', 'formula', 'evaltype']
			]);

			if (!$db_actions) {
				return false;
			}

			$db_action = reset($db_actions);

			if ($db_action['eventsource'] != $this->getInput('eventsource')) {
				return false;
			}

			$this->action = $db_action;
		}

		return true;
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);

		if ($this->action !== null) {
			$formula = array_key_exists('formula', $this->action['filter']) ? $this->action['filter']['formula'] : '';

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
					'pause_symptoms' => $this->action['pause_symptoms'],
					'pause_suppressed' => $this->action['pause_suppressed'],
					'notify_if_canceled' => $this->action['notify_if_canceled']
				],
				'formula' => $formula,
				'allowedOperations' => getAllowedOperations($eventsource)
			];

			foreach ($data['action']['filter']['conditions'] as $row_index => &$condition) {
				$condition_names = actionConditionValueToString([$data['action']]);
				$data['condition_name'][] = $condition_names[0][$row_index];
				$condition += [
					'row_index' => $row_index,
					'name' => $condition_names[0][$row_index]
				];
			}
			unset($condition);

			sortOperations($eventsource, $data['action']['operations']);

			foreach ($data['action']['operations'] as &$operation) {
				$operation['recovery'] = ACTION_OPERATION;
				$data['descriptions']['operation'] = getActionOperationData($data['action']['operations']);
			}
			unset($operation);

			foreach ($data['action']['recovery_operations'] as &$operation) {
				$operation['recovery'] = ACTION_RECOVERY_OPERATION;
				$data['descriptions']['recovery'] = getActionOperationData($data['action']['recovery_operations']);
			}
			unset($operation);

			foreach ($data['action']['update_operations'] as &$operation) {
				$operation['recovery'] = ACTION_UPDATE_OPERATION;
				$data['descriptions']['update'] = getActionOperationData($data['action']['update_operations']);
			}
			unset($operation);
		}
		else {
			$data = [
				'eventsource' => $eventsource,
				'actionid' => $this->getInput('actionid', 0),
				'action' => [
					'name' => '',
					'esc_period' => DB::getDefault('actions', 'esc_period'),
					'eventsource' => $eventsource,
					'status' => 0,
					'operations' => [],
					'recovery_operations' => [],
					'update_operations' => [],
					'filter' => [
						'conditions' => [],
						'evaltype' => ''
					],
					'pause_symptoms' => ACTION_PAUSE_SYMPTOMS_TRUE,
					'pause_suppressed' => ACTION_PAUSE_SUPPRESSED_TRUE,
					'notify_if_canceled' => ACTION_NOTIFY_IF_CANCELED_TRUE
				],
				'formula' => $this->getInput('formula', ''),
				'allowedOperations' => getAllowedOperations($eventsource)
			];
		}
		$data['user'] = ['debug_mode' => $this->getDebugMode()];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
