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


class CControllerPopupActionOperationsList extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'esc_period' =>			'db actions.esc_period|not_empty',
			'operations'=>			'array',
			'recovery_operations'=>	'array',
			'update_operations'=>	'array',
			'new_operation' =>		'array',
			'eventsource' =>		'required|db actions.eventsource|in '.implode(',', [
										EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
										EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
									]),
			'actionid'=>			'db actions.actionid'
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

	protected function checkPermissions(): bool {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid')
			]);
		}

		return false;
	}

	protected function doAction(): void {
		$data = [];
		$data['esc_period'] = $this->getInput('esc_period', DB::getDefault('actions', 'esc_period'));
		$eventsource = $this->getInput('eventsource');
		$new_operation = $this->getInput('new_operation')['operation'] ?? null;

		$operations = $this->getInput('operations', []);

		$unique_operations = [
			OPERATION_TYPE_HOST_ADD => 0,
			OPERATION_TYPE_HOST_REMOVE => 0,
			OPERATION_TYPE_HOST_ENABLE => 0,
			OPERATION_TYPE_HOST_DISABLE => 0,
			OPERATION_TYPE_HOST_INVENTORY => 0
		];

		if ($new_operation) {
			$result = true;

			if (array_key_exists($new_operation['operationtype'], $unique_operations)) {
				$unique_operations[$new_operation['operationtype']]++;

				foreach ($operations as $operationId => $operation) {
					if ($new_operation['row_index'] == $operationId) {
						unset($new_operation['row_index']);

						if ($operation === $new_operation) {
							$unique_operations[$operation['operationtype']]--;
						}

						$new_operation['row_index'] = $operationId;
					}
					else {
						if (array_key_exists($operation['operationtype'], $unique_operations)
								&& (!array_key_exists('id', $new_operation)
								|| bccomp($new_operation['id'], $operationId) != 0)) {
							$unique_operations[$operation['operationtype']]++;
						}
					}
				}

				if ($unique_operations[$new_operation['operationtype']] > 1) {
					$result = false;
					CMessageHelper::addError(
						_s('Operation "%1$s" already exists.', operation_type2str($new_operation['operationtype']))
					);
				}
			}

			if ($new_operation['recovery'] == ACTION_OPERATION) {
				$data['recovery'] = ACTION_OPERATION;
				$data['operations'] = $this->getInput('operations', []);
			}
			if ($new_operation['recovery'] == ACTION_RECOVERY_OPERATION) {
				$data['recovery'] = ACTION_RECOVERY_OPERATION;
				$data['operations'] = $this->getInput('recovery_operations', []);
			}
			elseif ($new_operation['recovery'] == ACTION_UPDATE_OPERATION) {
				$data['recovery'] = ACTION_UPDATE_OPERATION;
				$data['operations'] = $this->getInput('update_operations', []);
			}

			if ($new_operation['row_index'] != -1 && $result === true) {
				$data['operations'][(int) $new_operation['row_index']] = $new_operation;
			}
			elseif ($result === true) {
				$data['operations'][] = $new_operation;
			}

			if ($new_operation['recovery'] == ACTION_OPERATION) {
				sortOperations($eventsource, $data['operations']);
			}
			else {
				CArrayHelper::sort($data['operations'], ['operationtype']);
			}
		}
		else {
			$data['recovery'] = ACTION_OPERATION;
			$data['operations'] = $this->getInput('operations', []);
		}

		$data['action']['operations'] = [];

		foreach ($data['operations'] as $operation) {
			if ($operation['recovery'] == ACTION_OPERATION) {
				$data['action']['operations'][] = $operation;
				$data['descriptions'] = getActionOperationData($data['action']['operations']);
			}
			elseif ($operation['recovery'] == ACTION_RECOVERY_OPERATION) {
				$data['action']['recovery_operations'][] = $operation;
				$data['descriptions'] = getActionOperationData($data['action']['recovery_operations']);
			}
			elseif ($operation['recovery'] == ACTION_UPDATE_OPERATION) {
				$data['action']['update_operations'][] = $operation;
				$data['descriptions'] = getActionOperationData($data['action']['update_operations']);
			}
		}

		$data['allowedOperations'] = getAllowedOperations($eventsource);
		$data['eventsource'] = $eventsource;
		$data['action']['esc_period'] = $data['esc_period'];
		$data['action']['eventsource'] = $eventsource;

		$this->setResponse(new CControllerResponseData($data));
	}
}
