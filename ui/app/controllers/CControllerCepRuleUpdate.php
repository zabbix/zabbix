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


class CControllerCepRuleUpdate extends CController {
	protected function checkPermissions() {
		throw new \Exception('Not implemented');
	}

	protected function checkInput() {
		throw new \Exception('Not implemented');
	}

	protected function doAction() {
		throw new \Exception('Not implemented');
	}

	public static function getValidationRules(): array {
		// TODO: state what fields have macro and usermacro support
		$api_uniq = ['ceprule.get', ['name' => '{name}'], 'cepruleid'];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'cepruleid' => ['db cep_rule.cep_ruleid'],
			'name' => ['db cep_rule.name', 'required', 'not_empty'],
			'filter' => ['object', 'fields' => [
				'evaltype' => ['db cep_rule.evaltype', 'required',
					'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]
				],
				'conditions' => ['objects', 'fields' => [
					'type' => ['integer', 'required', 'in' => [ZBX_CEP_CONDITION_EVENT_NAME, ZBX_CEP_CONDITION_TAG_NAME, ZBX_CEP_CONDITION_TAG_VALUE, ZBX_CEP_CONDITION_SEVERITY, ZBX_CEP_CONDITION_HOST, ZBX_CEP_CONDITION_HOST_GROUP, ZBX_CEP_CONDITION_TIME_PERIOD]],
					'operator' => [
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE],
							'when' => ['type', 'in' => [ZBX_CEP_CONDITION_EVENT_NAME, ZBX_CEP_CONDITION_HOST, ZBX_CEP_CONDITION_HOST_GROUP]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_NOT_EXISTS],
							'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TAG_NAME]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_MORE_EQUAL],
							'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TAG_VALUE]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_MORE_EQUAL],
							'when' => ['type', 'in' => [ZBX_CEP_CONDITION_SEVERITY]]
						],
						[
							'integer', 'required',
							'in' => [CONDITION_OPERATOR_IN, CONDITION_OPERATOR_NOT_IN],
							'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TIME_PERIOD]]
						],
					],
					'event_name' => ['db cep_condition.event_name', 'required',
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_EVENT_NAME]]
					],
					'tag' => ['db cep_condition.tag', 'required', 'not_empty', // tag name can never be empty, whilst such case may be still be evaluated correctly with CEP condition operators.
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TAG_NAME]]
					],
					'tag_value' => ['db cep_condition.tag_value', 'required',
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TAG_VALUE]]
					],
					'host' => ['db cep_condition.host', 'required', 'not_empty',
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_HOST]]
					],
					'host_group' => ['db cep_condition.tag_value', 'required', 'not_empty',
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_HOST_GROUP]]
					],
					'severity' => ['db cep_condition.severity', 'required',
						'in' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING, TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER, TRIGGER_SEVERITY_COUNT],
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_SEVERITY]]
					],
					'time_period' => ['db cep_condition.time_period', 'required',
						'use' => [CTimePeriodParser::class, ['usermacros' => false, 'lldmacros' => false]],
						'when' => ['type', 'in' => [ZBX_CEP_CONDITION_TIME_PERIOD]]
					],
					'formulaid' => ['string', 'required', 'not_empty',
						// DEV ticket on relative order in depth:
						// [RULES ERROR] Only fields defined prior to this can be used for "when" checks (Path: /formulaid)
						/* 'when' => ['../evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]] */
					],
				]],
				'formula' => ['db cep_rule.formula', 'required', 'not_empty',
					/* 'use' => [CConditionFormula::class, []], // Only parser for syntax check. TODO: we had a work in progress on validator that is not merged yet? */
					'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
				]
			], /*'use' => [CConditionValidator::class, []] // TODO: something that asserts integrity beyond syntax, i.e. - if formula has all conditions */],
			'window_type' => ['db cep_rule.window_type', 'required',
				'in' => [ZBX_CEP_WINDOW_NONE, ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_TAG_MATCH, ZBX_CEP_WINDOW_PATTERN_MATCH]
			],
			// Type: "CEP rule window" object.
			'window' => ['object', 'fields' => [
				'duration' => ['db cep_window.duration', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, [
						// TODO: specify correct constraints
						'max' => null, 'min' => 1, 'usermacros' => false, 'lldmacros' => false, 'accept_zero' => false, 'with_year' => false
					]],
					'when' => ['../window_type', 'in' => [ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_TAG_MATCH, ZBX_CEP_WINDOW_PATTERN_MATCH]]
				],
				'capacity_unlimited' => ['boolean', 'required',
					'when' => ['../window_type', 'in' => [ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_TAG_MATCH, ZBX_CEP_WINDOW_PATTERN_MATCH]]
				], // field never sent to API - maybe rather implicitly unlimited if capacity = 0 or '' ?
				'capacity' => ['db cep_window.capacity', 'required', 'not_empty',
					'when' => [
						['capacity_unlimited', 'in' => [1]],
						['../window_type', 'in' => [ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_TAG_MATCH, ZBX_CEP_WINDOW_PATTERN_MATCH]
					]
				]],
				'group_by_host_group' => ['integer', 'in' => [ZBX_CEP_GROUP_BY_YES, ZBX_CEP_GROUP_BY_NO]],
				'group_by_host' => ['integer', 'in' => [ZBX_CEP_GROUP_BY_YES, ZBX_CEP_GROUP_BY_NO]],
				'group_by_tag' => ['integer', 'in' => [ZBX_CEP_GROUP_BY_YES, ZBX_CEP_GROUP_BY_NO]],
				'group_by_tag_name' => ['string', 'required', 'not_empty',
					'when' => ['group_by_tag', 'in' => [ZBX_CEP_GROUP_BY_YES]]
				],
				'tag' => ['db cep_window.tag', 'required', 'not_empty',
					'when' => [
						['group_by_tag', 'in' => [1]],
						['../window_type', 'in' => [ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_PATTERN_MATCH]]
					]
				],
				'event_count_tag_enabled' => ['integer', 'required', 'in' => [0, 1],
					'when' => ['../window_type', 'in' => [ZBX_CEP_WINDOW_CAUSE_SYMPTOM]]
				],
				'event_count_tag' => ['db cep_window.event_count_tag', 'required', 'not_empty',
					'when' => [
						['event_count_tag_enabled', 'in' => [1]],
						['../window_type', 'in' => [ZBX_CEP_WINDOW_CAUSE_SYMPTOM]]
					]
				],
				'filter' => ['object',
					'when' => ['../window_type', 'in' => [ZBX_CEP_WINDOW_TAG_MATCH]],
					'fields' => [
						'evaltype' => ['db cep_rule.evaltype', 'required',
							'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION]
						],
						// Type: List of "CEP rule window condition" objects.
						'conditions' => ['objects', 'fields' => [
							'type' => ['integer', 'required', 'in' => [ZBX_CEP_WINDOW_CONDITION_TAG_PAIR, ZBX_CEP_WINDOW_CONDITION_OLD_TAG, ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE]],
							'operator' => [
								[
									'integer', 'required',
									'in' => [CONDITION_OPERATOR_EQUAL],
									'when' => ['type', 'in' => [ZBX_CEP_WINDOW_CONDITION_TAG_PAIR, ZBX_CEP_WINDOW_CONDITION_OLD_TAG]]
								],
								[
									'integer', 'required',
									'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL],
									'when' => ['type', 'in' => [ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE]]
								],
							],
							'past_tag' => ['db cep_window_condition.past_tag', 'required', 'not_empty'],
							'tag' => ['db cep_window_condition.tag', 'required', 'not_empty',
								'when' => ['type', 'in' => [ZBX_CEP_WINDOW_CONDITION_TAG_PAIR]]
							],
							'tag_value' => ['db cep_window_condition.tag_value', 'required', 'not_empty',
								'when' => ['type', 'in' => [ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE]]
							],
						]],
						'formula' => ['db cep_rule.formula', 'required', 'not_empty',
							'use' => [CConditionFormulaParser::class, []], // Only parser for syntax check. TODO: we had work in progress on validator that is not merged yet?
							'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
						]
					]
				],
				'script' => ['db cep_window.script', 'required', 'not_empty',
					'when' => ['../window_type', 'in' => [ZBX_CEP_WINDOW_PATTERN_MATCH]]
				],
			]],
			// Type: List of "CEP rule operation" objects.
			// TODO: 'NOT required', "allow_empty" if window_type == ZBX_CEP_WINDOW_CAUSE_SYMPTOM - needs to duplicate the whole set?
			'operations' => ['objects', 'required', 'not_empty', 'fields' => [
				'execute_when' => [
					[
						'db cep_operation.execute_when', 'required',
						'in' => [ZBX_CEP_OP_WHEN_EVENT_OCCURRED, ZBX_CEP_OP_WHEN_EVENT_EVICTED, ZBX_CEP_OP_WHEN_WINDOW_CLOSED, ZBX_CEP_WHEN_TAGS_CORRELATED, ZBX_CEP_OP_WHEN_PATTERN_MATCHED],
					],
				],
				// Type: List of "CEP rule operation tag" objects.
				'tags' => ['objects', 'fields' => [
					'tag' => ['db cep_operation_tag.tag', 'required', 'not_empty'],
					'operator' => ['db cep_operation_tag.operator', 'required',
						// TODO: likely more operators need be added, i.e. CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE
						'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL],
					],
					'value' => ['db cep_operation_tag.value', 'required']
				]],
				'type' => [
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_SET_NAME, ZBX_CEP_OP_CLOSE, ZBX_CEP_OP_DISCARD, ZBX_CEP_OP_SET_SEVERITY, ZBX_CEP_OP_INCREASE_SEVERITY, ZBX_CEP_OP_DECREASE_SEVERITY, ZBX_CEP_OP_SUPPRESS, ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG],
						'when' => ['execute_when', 'in' => [ZBX_CEP_OP_WHEN_EVENT_OCCURRED]]
					],
					/* TODO: "3, 8, 9 - supported for execute_when "Event evicted" (1) with capacity restriction"
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_DISCARD, ZBX_CEP_OP_COPY_FIRST, ZBX_CEP_OP_COPY_LAST],
						'when' => [
							['execute_when', 'in' => [ZBX_CEP_OP_WHEN_EVENT_EVICTED]],
							['reason', 'in' => ['capacity']], // TODO: no mechanism yet - additional field or more detailed execute_when constants...
						]
					]*/
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_SET_NAME, ZBX_CEP_OP_CLOSE, ZBX_CEP_OP_SET_SEVERITY, ZBX_CEP_OP_INCREASE_SEVERITY, ZBX_CEP_OP_DECREASE_SEVERITY, ZBX_CEP_OP_SUPPRESS, ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG],
						'when' => ['execute_when', 'in' => [ZBX_CEP_OP_WHEN_EVENT_EVICTED]]
					],
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_SET_NAME, ZBX_CEP_OP_CLOSE, ZBX_CEP_OP_DISCARD, ZBX_CEP_OP_SET_SEVERITY, ZBX_CEP_OP_INCREASE_SEVERITY, ZBX_CEP_OP_DECREASE_SEVERITY, ZBX_CEP_OP_SUPPRESS, ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG],
						'when' => ['execute_when', 'in' => [ZBX_CEP_OP_WHEN_WINDOW_CLOSED]]
					],
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_SET_NAME, ZBX_CEP_OP_CLOSE, ZBX_CEP_OP_SET_SEVERITY, ZBX_CEP_OP_INCREASE_SEVERITY, ZBX_CEP_OP_DECREASE_SEVERITY, ZBX_CEP_OP_SUPPRESS, ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG],
						'when' => ['execute_when', 'in' => [ZBX_CEP_WHEN_TAGS_CORRELATED]]
					],
					['db cep_operation.type', 'required',
						'in' => [ZBX_CEP_OP_COPY_FIRST, ZBX_CEP_OP_COPY_LAST],
						'when' => ['execute_when', 'in' => [ZBX_CEP_OP_WHEN_PATTERN_MATCHED]]
					]
				],
				'evaltype' => ['db cep_operation.evaltype', 'required', 'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]],
				'event_type' => ['db cep_operation.event_type', 'required', 'in' => [0, 1],
					'when' => [
						['execute_when', 'in' => [ZBX_CEP_OP_WHEN_EVENT_OCCURRED]],
						/* ['../window_type', 'in' => [ZBX_CEP_WINDOW_CAUSE_SYMPTOM]] */
					]
				],
				'event_name' => ['db cep_operation.event_name', 'required',
					'when' => ['type', 'in' => [ZBX_CEP_OP_SET_NAME]]
				],
				'tag' => ['db cep_operation.tag', 'required',
					'when' => ['type', 'in' => [ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG]]
				],
				'new_tag' => ['db cep_operation.new_tag', 'required',
					'when' => ['type', 'in' => [ZBX_CEP_OP_RENAME_TAG]]
				],
				'tag_value' => ['db cep_operation.tag_value', 'required',
					'when' => ['type', 'in' => [ZBX_CEP_OP_ADD_TAG, ZBX_CEP_OP_SET_TAG, ZBX_CEP_OP_SET_TAG_VALUE, ZBX_CEP_OP_INCREASE_TAG_VALUE, ZBX_CEP_OP_DECREASE_TAG_VALUE, ZBX_CEP_OP_RENAME_TAG, ZBX_CEP_OP_REMOVE_TAG]]
				],
				'severity' => ['db cep_operation.severity', 'required',
					'when' => ['type', 'in' => [ZBX_CEP_OP_SET_SEVERITY]]
				],
			]],
			'stop' => ['db cep_rule.stop', 'required', 'in' => [0, 1]], // TODO: no constants?
			'sortorder' => ['db cep_rule.sortorder', 'required',
				'use' => [CNumberValidator::class, ['min' => 1, 'max' => ZBX_MAX_INT64, 'with_float' => false, 'usermacros' => false, 'lldmacros' => false]]
				// TODO: ZBX_MAX_INT64 or ZBX_MAX_INT32 ? What will server handle?
			],
			'description' => ['db cep_rule.description'],
			'status' => ['db cep_rule.status', 'required', 'in' => [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED]],
		]];
	}
}
