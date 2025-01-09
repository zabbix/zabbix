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


class CControllerActionConditionCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>			'required|int32',
			'type' =>				'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION,
			'source' =>				'db actions.eventsource|required|in '.implode(',', [
										EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
										EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
									]),
			'condition_type' =>		'db conditions.conditiontype|in '.implode(',', array_keys(condition_type2str())),
			'trigger_context' =>	'in '.implode(',', ['host', 'template']),
			'operator' =>			'db conditions.operator|in '.implode(',', array_keys(condition_operator2str())),
			'value' =>				'',
			'value2' =>				'db conditions.value2|not_empty'
		];

		$ret = $this->validateInput($fields) && $this->validateCondition();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function validateCondition(): bool {
		$validator = new CActionCondValidator();
		$is_valid = $validator->validate([
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->hasInput('value') ? $this->getInput('value') : null,
			'value2' => $this->hasInput('value2') ? $this->getInput('value2') : null,
			'operator' => $this->getInput('operator')
		]);

		if (!$is_valid) {
			error($validator->getError());
		}

		return $is_valid;
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$value = $this->getInput('value', '');
		$value2 = $this->getInput('value2', '');
		$condition = [
			'conditiontype' => $this->getInput('condition_type'),
			'operator' => $this->getInput('operator'),
			'value' => $value,
			'value2' => $value2
		];

		if (is_array($value)) {
			foreach ($value as $condition_value) {
				$condition['value'] = $condition_value;
				$action = $this->getDefaultAction();
				$action['filter']['conditions'] = [$condition];
				$actionConditionStringValues[] = actionConditionValueToString([$action])[0];
			}
		}
		else {
			$action = $this->getDefaultAction();
			$action['filter']['conditions'] = [$condition];
			$actionConditionStringValues = actionConditionValueToString([$action])[0];
		}

		$data = [
			'title' => _('New condition'),
			'command' => '',
			'row_index' => $this->getInput('row_index'),
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $value,
			'value2' => $value2,
			'operator' => $this->getInput('operator'),
			'eventsource' => $this->getInput('source'),
			'allowed_conditions' => get_conditions_by_eventsource($this->getInput('source')),
			'trigger_context' => $this->getInput('trigger_context', 'host'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'name' => $actionConditionStringValues
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}

	/**
	 * Returns default Action object.
	 *
	 * @return array
	 */
	protected function getDefaultAction(): array {
		return [
			'name' => '',
			'esc_period' => '',
			'eventsource' => '',
			'status' => '',
			'operations' => [],
			'recovery_operations' => [],
			'update_operations' => [],
			'filter' => [],
			'pause_suppressed' => '',
			'notify_if_canceled' => ''
		];
	}
}
