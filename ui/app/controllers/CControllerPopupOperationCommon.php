<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CControllerPopupOperationCommon extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = $this->getCheckInputs();

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->getInput('actionid', '0')) {
			return true;
		}

		return (bool) API::Action()->get([
			'output' => [],
			'actionids' => $this->getInput('actionid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		if ($this->hasInput('validate')) {
			$operation = $this->getInput('operation', []) + [
				'recovery' => $this->getInput('type'),
				'eventsource' => $this->getInput('source'),
				'operationtype' => $this->getInput('operationtype', OPERATION_TYPE_MESSAGE),
			];

			$operation = $this->transformInputs($operation);

			if (!API::Action()->validateOperationsIntegrity($operation)) {
				$output = [];
				if (($messages = getMessages()) !== null) {
					$output['errors'] = $messages->toString();
				}

				return $this->setResponse(
					(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
				);
			}

			return $this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'form' => $this->getFormDetails(),
						'inputs' => $operation
					])
				]))->disableView()
			);
		}

		$data = $this->getInput('operation', [
			'operationtype' => OPERATION_TYPE_MESSAGE,
			'esc_period' => 0,
			'esc_step_from' => 1,
			'esc_step_to' => 1,
			'evaltype' => 0
		]);

		$data += ['operationtype' => $this->getInput('operationtype', OPERATION_TYPE_MESSAGE)];

		if (hasRequest('opcondition')) {
			$conditions = [];
			if (hasRequest('operation')) {
				$oper = getRequest('operation');
			}
			if (array_key_exists('opconditions', $oper)) {
				$conditions = $oper['opconditions'];
			}
			$new_condition = getRequest('opcondition');

			foreach ($conditions as $condition) {
				if (isset($new_condition) && $new_condition['conditiontype'] == $condition['conditiontype']) {
					switch ($new_condition['conditiontype']) {
						case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
							if ($new_condition['value'] === $condition['value']) {
								unset($new_condition);
							}
							break;
					}
				}
			}

			if (isset($new_condition)) {
				$conditions[] = $new_condition;
			}

			$data['opconditions'] = $conditions;

			try {
				CAction::validateOperationConditions($data['opconditions']);
			}
			catch (APIException $e) {
				return $this->setResponse(
					(new CControllerResponseData([
						'main_block' => json_encode([
							'errors' => $e->getMessage()
						])
					]))->disableView()
				);
			}
		}

		$output = [
			'title' => _('Operation details'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'data' => $data,
			'type' => $this->getInput('type'),
			'source' => $this->getInput('source'),
			'actionid' => $this->getInput('actionid', '0'),
			'update' => $this->getInput('update', '0'),
			'allowed_operations' => getAllowedOperations($this->getInput('source')),
			'operationtype' => $this->getInput('operationtype', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		return $this->setResponse(new CControllerResponseData($output));
	}

	/**
	 * Transform some variables.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	protected function transformInputs(array $data) {
		switch ($data['operationtype']) {
			case OPERATION_TYPE_COMMAND:
				// Converting new logic for targets to old.
				if (array_key_exists('opcommand_grp', $data)) {
					$opcommmand_groups = [];
					foreach ($data['opcommand_grp'] as $value) {
						$opcommmand_groups[$value]['groupid'] = $value;
					}

					$data['opcommand_grp'] = $opcommmand_groups;
				}

				if (array_key_exists('opcommand_hst', $data)) {
					$opcommmand_hosts = [];
					foreach ($data['opcommand_hst'] as $value) {
						$opcommmand_hosts[$value]['hostid'] = $value;
					}

					$data['opcommand_hst'] = $opcommmand_hosts;
				}

				if (array_key_exists('opcommand_chst', $data)) {
					unset($data['opcommand_chst']);
					$data['opcommand_hst'][0]['hostid'] = '0';
				}
				break;

			case OPERATION_TYPE_GROUP_ADD:
			case OPERATION_TYPE_GROUP_REMOVE:
				$data['opgroup'] = [];

				if (array_key_exists('groupids', $data)) {
					foreach ($data['groupids'] as $groupid) {
						$data['opgroup'][] = ['groupid' => $groupid];
					}
					unset($data['groupids']);
				}
				break;

			case OPERATION_TYPE_TEMPLATE_ADD:
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				$data['optemplate'] = [];

				if (array_key_exists('templateids', $data)) {
					foreach ($data['templateids'] as $templateid) {
						$data['optemplate'][] = ['templateid' => $templateid];
					}
					unset($data['templateids']);
				}
				break;
		}

		return $data;
	}
}
