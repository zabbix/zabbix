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

	protected function init(): void {
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules(existing: $this->getAction() === 'ceprule.update'));

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = array_filter([
				'form_errors' => $form_errors,
				'error' => !$form_errors
					? [
						'title' => static::class === CControllerCepRuleCreate::class
							? _('Cannot add complex event processing rule')
							: _('Cannot update complex event processing rule'),
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
					: null
			]);

			$this->setResponse(new CControllerResponseData(['main_block' => json_encode($response)]));
		}

		return $ret;
	}

	protected function prepareApiRequest(): array {
		$request = $this->getInputAll();
		unset($request['_cep_rule_reset']);

		if (!in_array($request['window_type'], [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
				CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH])) {
			unset($request['window']);
		}

		if (array_key_exists('cepruleid', $request)) {
			$request['cep_ruleid'] = $request['cepruleid'];
			unset($request['cepruleid']);
		}

		if (array_key_exists('window', $request)) {
			if (array_key_exists('capacity_enabled', $request['window'])) {
				unset($request['window']['capacity_enabled']);
			}

			if (array_key_exists('event_count_tag_enabled', $request['window'])) {
				unset($request['window']['event_count_tag_enabled']);
			}


			// TODO: awaiting API changes.. remapping.
			if (array_key_exists(0, $request['window']['tags'])) {
				$request['window']['tag'] =  $request['window']['tags'][0];
			}
		}

		if (array_key_exists('filter', $request)) {
			if (array_key_exists('conditions', $request['filter'])) {
				array_walk($request['filter']['conditions'], function (array &$condition) {
					$is_tag_type = $condition['type'] == CCepRuleHelper::CONDITION_TAG
						|| $condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE;
					$condition['operator'] = $is_tag_type ? $condition['tag_operator'] : $condition['operator'];

					$is_exists_operator = $condition['operator'] == CONDITION_OPERATOR_EXISTS
						|| $condition['operator'] == CONDITION_OPERATOR_NOT_EXISTS;

					if ($condition['type'] == CCepRuleHelper::CONDITION_TAG && !$is_exists_operator) {
						$condition['type'] = CCepRuleHelper::CONDITION_TAG_VALUE;
					}

					unset($condition['tag_operator'], $condition['formulaid']);
				});
				$request['filter']['conditions'] = array_values($request['filter']['conditions']);
			}
		}

		return $request;
	}

	public static function getValidationRules(bool $existing = true): array {
		$api_uniq = !$existing
			? ['ceprule.get', ['name' => '{name}']]
			: ['ceprule.get', ['name' => '{name}'], 'cepruleid'];

		return ['object', 'api_uniq' => $api_uniq, 'fields' => [
			'_cep_rule_reset' => ['boolean'],
			'cepruleid' => ['db cep_rule.cep_ruleid'],
			'name' => ['db cep_rule.name', 'required', 'not_empty'],
			'filter' => ['object', 'fields' => [
				'evaltype' => ['db cep_rule.evaltype', 'required',
					'in' => [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR,
						CONDITION_EVAL_TYPE_EXPRESSION
					]
				],
				'conditions' => ['objects', 'fields' => self::getConditionValidationFields()],
				'formula' => ['db cep_rule.formula', 'required', 'not_empty',
					'use' => [CConditionFormulaParser::class, []],
					'when' => ['evaltype', 'in' => [CONDITION_EVAL_TYPE_EXPRESSION]]
				]
			]],
			'window_type' => ['db cep_window.type', 'required',
				'in' => [CCepRuleHelper::WINDOW_NONE, CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]
			],
			'window' => ['object', 'fields' => [
				'duration' => ['db cep_window.duration', 'required', 'not_empty',
					'use' => [CTimeUnitValidator::class, ['max' => SEC_PER_YEAR, 'min' => 1, 'usermacros' => true,
						'lldmacros' => false, 'accept_zero' => false, 'with_year' => false
					]],
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				],
				'capacity_enabled' => ['boolean', 'required',
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				],
				'capacity' => ['db cep_window.capacity', 'required', 'not_empty',
					'use' => [CNumberValidator::class, ['min' => 1, 'max' => ZBX_MAX_INT64, 'with_float' => false, 'usermacros' => true, 'lldmacros' => false]],
					'when' => [
						['capacity_enabled', 'in' => [1]],
						['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]
					]
				]],
				'group_by_host_group' => ['integer', 'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO],
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				],
				'group_by_host' => ['integer', 'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO],
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				],
				'group_by_tag' => ['integer', 'in' => [CCepRuleHelper::GROUP_BY_YES, CCepRuleHelper::GROUP_BY_NO],
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				],
				'tags' => ['array', 'required', 'not_empty', 'field' => ['string', 'not_empty'],
					'when' => ['group_by_tag', 'in' => [CCepRuleHelper::GROUP_BY_YES]]
				],
				'event_count_tag_enabled' => ['integer', 'required', 'in' => [0, 1],
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_CAUSE_SYMPTOM]]
				],
				'event_count_tag' => ['db cep_window.event_count_tag', 'required', 'not_empty',
					'when' => ['event_count_tag_enabled', 'in' => [1]]
				],
				'script' => ['db cep_window.script', 'required', 'not_empty',
					'when' => ['../window_type', 'in' => [CCepRuleHelper::WINDOW_PATTERN_MATCH]]
				]
			]],
			'operations' => [
				['objects', 'required', 'fields' => self::getOperationValidationFields()],
				['objects', 'required', 'not_empty', 'fields' => self::getOperationValidationFields(), 'when' => [
					'window_type', 'in' => [CCepRuleHelper::WINDOW_NONE, CCepRuleHelper::WINDOW_SIMPLE,
						CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH
					]
				]]
			],
			'stop' => ['db cep_rule.stop', 'required', 'in' => [CCepRuleHelper::EXECUTION_CONTINUE, CCepRuleHelper::EXECUTION_STOP]],
			'sortorder' => ['db cep_rule.sortorder', 'required',
				'use' => [CNumberValidator::class, ['min' => 1, 'max' => ZBX_MAX_INT64, 'with_float' => false, 'usermacros' => false, 'lldmacros' => false]]
			],
			'description' => ['db cep_rule.description'],
			'status' => ['db cep_rule.status', 'required', 'in' => [CCepRuleHelper::STATUS_ENABLED, CCepRuleHelper::STATUS_DISABLED]]
		]];
	}

	public static function getOperationValidationFields(): array {
		// Note: the correct fix is needed to be done in IV-core client side (BE has no issues) -
		// when "objects" has no "fields" in rules definition it currently deteles "fields" instead of merging.
		return [
			'execute_when' => ['db cep_operation.execute_when', 'required', 'in' => [
				CCepRuleHelper::WHEN_EVENT_OCCURRED, CCepRuleHelper::WHEN_EVENT_EVICTED,
				CCepRuleHelper::WHEN_WINDOW_CLOSED, CCepRuleHelper::WHEN_TAGS_CORRELATED,
				CCepRuleHelper::WHEN_PATTERN_MATCHED
			]],
			'tags' => ['objects', 'fields' => [
				'tag' => ['db cep_operation_condition.tag', 'required', 'not_empty'],
				'operator' => ['db cep_operation_condition.operator', 'required', 'in' => [TAG_OPERATOR_EXISTS,
					TAG_OPERATOR_EQUAL, TAG_OPERATOR_LIKE, TAG_OPERATOR_NOT_EXISTS, TAG_OPERATOR_NOT_EQUAL,
					TAG_OPERATOR_NOT_LIKE
				]],
				'value' => ['db cep_operation_condition.value', 'required']
			]],
			'type' => array_map(fn(int $execute_when) => ['db cep_operation.type', 'required',
				'in' => CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN[$execute_when],
				'when' => ['execute_when', 'in' => [$execute_when]]
			], array_keys(CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN)),
			'evaltype' => ['db cep_operation.evaltype', 'required', 'in' => [CONDITION_EVAL_TYPE_AND_OR,
				CONDITION_EVAL_TYPE_OR
			]],
			'event_name' => ['db cep_operation.event_name', 'required', 'not_empty', 'when' => ['type',
				'in' => [CCepRuleHelper::OP_SET_NAME]
			]],
			'tag' => ['db cep_operation.tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE,
				CCepRuleHelper::OP_INCREASE_TAG_VALUE, CCepRuleHelper::OP_DECREASE_TAG_VALUE,
				CCepRuleHelper::OP_RENAME_TAG, CCepRuleHelper::OP_REMOVE_TAG
			]]],
			'new_tag' => ['db cep_operation.new_tag', 'required', 'not_empty', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_RENAME_TAG
			]]],
			'tag_value' => ['db cep_operation.tag_value', 'required', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE,
				CCepRuleHelper::OP_INCREASE_TAG_VALUE, CCepRuleHelper::OP_DECREASE_TAG_VALUE,
				CCepRuleHelper::OP_RENAME_TAG, CCepRuleHelper::OP_REMOVE_TAG
			]]],
			'severity' => ['db cep_operation.severity', 'required', 'when' => ['type', 'in' => [
				CCepRuleHelper::OP_SET_SEVERITY
			]]],
			'suppress_until' => ['string',
				'use' => [CAbsoluteTimeValidator::class, ['min' => 0, 'max' => ZBX_MAX_DATE]],
				'when' => ['type', 'in' => [CCepRuleHelper::OP_SUPPRESS]]
			],
			'sortorder' => ['db cep_operation.sortorder', 'required']
		];
	}

	public static function getConditionValidationFields(): array {
		return [
			'type' => ['integer', 'required', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME,
				CCepRuleHelper::CONDITION_TAG, CCepRuleHelper::CONDITION_SEVERITY, CCepRuleHelper::CONDITION_HOST,
				CCepRuleHelper::CONDITION_HOST_GROUP, CCepRuleHelper::CONDITION_TIME_PERIOD
			]],
			'operator' => [
				[
					'integer', 'required', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE
					],
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME,
						CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP
					]]
				],
				[
					'integer', 'required', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
						CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_MORE_EQUAL
					],
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_SEVERITY]]
				],
				[
					'integer', 'required',
					'in' => [CONDITION_OPERATOR_IN, CONDITION_OPERATOR_NOT_IN],
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TIME_PERIOD]]
				]
			],
			'event_name' => ['db cep_condition.event_name', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME]]
			],
			'tag_operator' => ['integer', 'required', 'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
					CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS,
					CONDITION_OPERATOR_NOT_EXISTS, CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
				],
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
			],
			'tag' => ['db cep_condition.tag', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
			],
			'tag_value' => ['db cep_condition.tag_value',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
			],
			'host' => ['db cep_condition.host', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_HOST]]
			],
			'host_group' => ['db cep_condition.host_group', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_HOST_GROUP]]
			],
			'severity' => ['db cep_condition.severity', 'required',
				'in' => [TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_WARNING,
					TRIGGER_SEVERITY_AVERAGE, TRIGGER_SEVERITY_HIGH, TRIGGER_SEVERITY_DISASTER],
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_SEVERITY]]
			],
			'time_period' => ['db cep_condition.time_period', 'required', 'not_empty',
				'use' => [CTimePeriodParser::class, ['usermacros' => false, 'lldmacros' => false]],
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TIME_PERIOD]]
			],
			'formulaid' => ['string', 'required', 'not_empty']
		];
	}
}
