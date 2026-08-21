<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


abstract class CControllerCepRuleGeneral extends CController {

	protected function prepareApiRequest(): array {
		$request = $this->getInputAll();

		if (array_key_exists('cepruleid', $request)) {
			$request['cep_ruleid'] = $request['cepruleid'];
			unset($request['cepruleid']);
		}

		if (array_key_exists('window', $request)) {
			if ($request['window']['capacity_enabled'] == 0) {
				$request['window']['capacity'] = '0';
			}

			unset($request['window']['capacity_enabled']);

			if (array_key_exists('event_count_tag_enabled', $request['window'])) {
				if ($request['window']['event_count_tag_enabled'] == 0) {
					$request['window']['event_count_tag'] = '';
				}

				unset($request['window']['event_count_tag_enabled']);
			}
		}

		if (array_key_exists('filter', $request)) {
			if (array_key_exists('conditions', $request['filter'])) {
				array_walk($request['filter']['conditions'], function (array &$condition) {
					if ($condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE) {
						$condition['tag'] = $condition['tag_name'];
						unset($condition['tag_name']);
					}
				});
				$request['filter']['conditions'] = array_values($request['filter']['conditions']);
			}
		}

		if (array_key_exists('operations', $request)) {
			$request['operations'] = array_values($request['operations']);
			array_walk($request['operations'], function(array &$operation) {
				if (!array_key_exists('conditions', $operation['filter'])) {
					$operation['filter']['conditions'] = [];
				}

				if ($operation['type'] == CCepRuleHelper::OP_SUPPRESS
						&& array_key_exists('suppress_duration', $operation)
						&& $operation['suppress_duration'] === '') {
					$operation['suppress_duration'] = DB::getDefault('cep_operation', 'suppress_duration');
				}

				switch ($operation['type']) {
					case CCepRuleHelper::OP_RENAME_TAG:
						$operation['tag'] = $operation['old_tag'];
						unset($operation['old_tag']);
						break;

					case CCepRuleHelper::OP_ADD_TAG:
					case CCepRuleHelper::OP_SET_TAG:
					case CCepRuleHelper::OP_SET_TAG_VALUE:
						$operation['tag'] = $operation['tag_name'];
						unset($operation['tag_name']);
						break;
				}
			});
		}

		return $request;
	}

	protected static function getOperationValidationFields(): array {
		return [
			'execute_when' => array_map(fn(int $window_type) => ['db cep_operation.execute_when', 'required',
				'in' => CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE[$window_type],
				'messages' => ['in' => _s(
					'Execute when type not allowed for window type "%1$s".',
					CCepRuleHelper::getWindowLabelString(compact('window_type'))
				)],
				'when' => ['../window_type', 'in' => [$window_type]]
			], array_keys(CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE)),
			'filter' => ['object', 'fields' => self::getOperationFilterValidationFields()],
			'type' => array_map(fn(int $execute_when) => ['db cep_operation.type', 'required',
				'in' => CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN[$execute_when],
				'when' => ['execute_when', 'in' => [$execute_when]]
			], array_keys(CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN)),
			'event_name' => ['db cep_operation.event_name', 'required', 'not_empty', 'when' => ['type',
				'in' => [CCepRuleHelper::OP_SET_NAME]
			]],
			'tag' => ['db cep_operation.tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_INCREASE_TAG_VALUE, CCepRuleHelper::OP_DECREASE_TAG_VALUE,
				CCepRuleHelper::OP_REMOVE_TAG
			]]],
			'old_tag' => ['db cep_operation.tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_RENAME_TAG
			]]],
			'new_tag' => ['db cep_operation.new_tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_RENAME_TAG
			]]],
			'tag_name' => ['db cep_operation.tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE
			]]],
			'tag_value' => ['db cep_operation.tag_value', 'required', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE
			]]],
			'severity' => ['db cep_operation.severity', 'required',
				'in' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING,
					TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER],
				'when' => ['type', 'in' => [CCepRuleHelper::OP_SET_SEVERITY]
			]],
			'suppress_duration' => ['db cep_operation.suppress_duration',
				'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_YEAR, 'min' => 1, 'usermacros' => false,
					'lldmacros' => false, 'accept_zero' => true, 'with_year' => false
				]],
				'when' => ['type', 'in' => [CCepRuleHelper::OP_SUPPRESS]]
			],
			'sortorder' => ['db cep_operation.sortorder', 'required']
		];
	}

	public static function getOperationFilterValidationFields(): array {
		return [
			'evaltype' => ['db cep_operation.evaltype', 'required', 'in' => [
				CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR
			]],
			'conditions' => ['objects', 'fields' => [
				'type' => ['integer', 'required', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG,
					ZBX_CONDITION_TYPE_EVENT_TAG_VALUE, ZBX_CONDITION_TYPE_EVENT_OPEN,
					ZBX_CONDITION_TYPE_EVENT_FIRST, ZBX_CONDITION_TYPE_EVENT_LAST, ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
					ZBX_CONDITION_TYPE_EVENT_COPIED, ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
				]],
				'tag' => ['db cep_operation_condition.tag', 'required', 'not_empty',
					'when' => ['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]]
				],
				'operator' => [
					['db cep_operation_condition.operator', 'required',
						'in' => [CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO],
						'when' => ['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_OPEN, ZBX_CONDITION_TYPE_EVENT_FIRST,
							ZBX_CONDITION_TYPE_EVENT_LAST, ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
							ZBX_CONDITION_TYPE_EVENT_COPIED, ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
						]]
					],
					['db cep_operation_condition.operator', 'required',
						'in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_LIKE,
							CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_NOT_LIKE,
							CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
						],
						'when' => ['type',
							'in' => [ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]
						]
					]
				],
				'value' => ['db cep_operation_condition.tag_value', 'required',
					'when' => [
						['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]],
						['operator', 'not_in' => [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS]]
					]
				]
			]]
		];
	}
}
