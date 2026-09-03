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


class CControllerCepRuleEdit extends CController {

	private array $ceprule = [];

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_CONFIGURATION_CEPRULES)) {
			return false;
		}

		$this->ceprule = self::fetchCepRule($this->hasInput('cepruleid') ? $this->getInput('cepruleid') : null);

		if (!$this->ceprule) {
			return false;
		}

		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function checkInput(): bool {
		$fields = [
			'cepruleid' => 'db cep_rule.cep_ruleid'
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

	protected function doAction(): void {
		$ceprule = self::withDefaults($this->ceprule);

		if ($this->hasInput('cepruleid')) {
			$rules = (new CFormValidator(
				CControllerCepRuleUpdate::getValidationRules()
			))->getRules();
			$rules_for_clone = (new CFormValidator(
				CControllerCepRuleCreate::getValidationRules()
			))->getRules();
		}
		else {
			$rules = (new CFormValidator(
				CControllerCepRuleCreate::getValidationRules()
			))->getRules();
			$rules_for_clone = $rules;
		}

		$data = [
			'js_validation_rules' => $rules,
			'js_validation_rules_for_clone' => $rules_for_clone,
			'condition_js_validation_rules' => self::getConditionPopupValidationRules(),
			'operation_js_validation_rules' => self::getOperationPopupValidationRules(),
			'operation_condition_js_validation_rules' => self::getOperationConditionValidationRules(),
			'ceprule' => $ceprule,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle($ceprule['cepruleid'] === null
			? _('New complex event processing')
			: _('Complex event processing')
		);

		$this->setResponse($response);
	}

	protected static function fetchCepRule(?string $cepruleid): array {
		if ($cepruleid !== null) {
			$ceprules = API::CepRule()->get([
				'cep_ruleids' => $cepruleid,
				'output' => ['cep_ruleid', 'name', 'description', 'window_type', 'status', 'stop', 'sortorder'],
				'selectOperations' => ['sortorder', 'execute_when', 'filter', 'type', 'event_name', 'severity',
					'suppress_duration', 'tag', 'new_tag', 'tag_value'],
				'selectFilter' => ['formula', 'evaltype', 'conditions'],
				'selectWindow' => ['duration', 'capacity', 'script', 'group_by_host_group', 'group_by_host',
					'group_by_tags', 'event_count_tag', 'tags'
				]
			]);
		}
		else {
			$ceprules = [DB::getDefaults('cep_rule') + [
				'filter' => [
					'formula' => '',
					'evaltype' => DB::getDefault('cep_rule', 'evaltype'),
					'conditions' => []
				],
				'window_type' => CCepRuleHelper::WINDOW_NONE,
				'window' => ['tags' => []] + DB::getDefaults('cep_rule_window'),
				'operations' => []
			]];
		}

		if (!$ceprules) {
			return [];
		}

		return $ceprules[0];
	}

	protected static function withDefaults(array $ceprule): array {
		$ceprule['window'] += DB::getDefaults('cep_rule_window');

		// Unlimited capacity's default value is "0".
		$ceprule['window']['capacity_enabled'] = $ceprule['window']['capacity'] != 0;
		if (!$ceprule['window']['capacity_enabled']) {
			$ceprule['window']['capacity'] = '10'; // Pre-fill a convenient default upon change into limited setting.
		}

		if ($ceprule['window']['duration'] == 0) {
			$ceprule['window']['duration'] = '10m';
		}

		// Consistent naming with URL and fields.
		$ceprule['cepruleid'] = array_key_exists('cep_ruleid', $ceprule) ? $ceprule['cep_ruleid'] : null;
		unset($ceprule['cep_ruleid']);

		foreach ($ceprule['filter']['conditions'] as &$condition) {
			if ($condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE) {
				$condition['tag_name'] = $condition['tag'];
				unset($condition['tag']);
			}
		}
		unset($condition);

		if (array_key_exists('operations', $ceprule)) {
			array_walk($ceprule['operations'], function(array &$operation) {

				foreach ($operation['filter']['conditions'] as &$condition) {
					if ($condition['type'] == CCepRuleHelper::CONDITION_TAG_VALUE) {
						$condition['tag_name'] = $condition['tag'];
						unset($condition['tag']);
					}
				}
				unset($condition);


				if ($operation['suppress_duration'] == ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE) {
					$operation['suppress_time_option'] = ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE;
					$operation['suppress_duration'] = '';
				}
				else {
					$operation['suppress_time_option'] = ZBX_PROBLEM_SUPPRESS_TIME_DEFINITE;
				}

				$operation['old_tag'] = '';
				$operation['tag_name'] = '';

				switch ($operation['type']) {
					case CCepRuleHelper::OP_RENAME_TAG:
						$operation['old_tag'] = $operation['tag'];
						$operation['tag'] = '';
						break;

					case CCepRuleHelper::OP_ADD_TAG:
					case CCepRuleHelper::OP_SET_TAG:
					case CCepRuleHelper::OP_SET_TAG_VALUE:
						$operation['tag_name'] = $operation['tag'];
						$operation['tag'] = '';
						break;
				}
			});
		}

		return $ceprule;
	}

	protected static function getOperationPopupValidationRules(): array {
		return (new CFormValidator(['object', 'fields' => [
			'window_type' => ['db cep_rule_window.type', 'required', 'in' => [CCepRuleHelper::WINDOW_NONE,
				CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH,
				CCepRuleHelper::WINDOW_PATTERN_MATCH
			]],
			'execute_when' => array_map(fn(int $window_type) => ['db cep_operation.execute_when', 'required',
				'in' => CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE[$window_type],
				'messages' => ['in' => _s(
					'Execute when type not allowed for window type "%1$s".',
					CCepRuleHelper::getWindowLabelString(compact('window_type'))
				)],
				'when' => ['window_type', 'in' => [$window_type]]
			], array_keys(CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE)),
			'filter' => ['object', 'fields' => CControllerCepRuleGeneral::getOperationFilterValidationFields()],
			'type' => array_map(fn(int $execute_when) => ['db cep_operation.type', 'required',
				'in' => CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN[$execute_when],
				'when' => ['execute_when', 'in' => [$execute_when]],
				'messages' => ['in' => _('Invalid operation.')]
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
			'suppress_time_option' => ['integer', 'required',
				'in' => [ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE, ZBX_PROBLEM_SUPPRESS_TIME_DEFINITE],
				'when' => ['type', 'in' => [CCepRuleHelper::OP_SUPPRESS]]
			],
			'suppress_duration' => ['db cep_operation.suppress_duration', 'required', 'not_empty',
				'use' => [CTimeUnitValidator::class, ['max' => 5 * SEC_PER_YEAR, 'min' => 1, 'with_year' => true]],
				'when' => ['suppress_time_option', 'in' => [ZBX_PROBLEM_SUPPRESS_TIME_DEFINITE]]
			],
			'sortorder' => ['db cep_operation.sortorder', 'required']
		]]))->getRules();
	}

	protected static function getConditionPopupValidationRules(): array {
		return (new CFormValidator(['object', 'fields' => [
			'type' => ['integer', 'required', 'in' => [
				CCepRuleHelper::CONDITION_EVENT_NAME, CCepRuleHelper::CONDITION_TAG,
				CCepRuleHelper::CONDITION_TAG_VALUE, CCepRuleHelper::CONDITION_SEVERITY,
				CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP,
				CCepRuleHelper::CONDITION_TIME_PERIOD
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
				],
				[
					'integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE],
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
				],
				[
					'integer', 'required',
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL,
						CONDITION_OPERATOR_LESS_EQUAL],
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
				]
			],
			'event_name' => ['db cep_condition.event_name', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_EVENT_NAME]]
			],
			'tag' => ['db cep_condition.tag', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG]]
			],
			'tag_name' => ['db cep_condition.tag', 'required', 'not_empty',
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
			],
			'tag_value' => [
				['db cep_condition.tag_value', 'required',
					'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]]
				],
				['db cep_condition.tag_value', 'required', 'not_empty',
					'when' => [
						['type', 'in' => [CCepRuleHelper::CONDITION_TAG_VALUE]],
						['operator', 'in' => [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE,
							CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
						]]
					]
				]
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
				'messages' => ['use' => _('Invalid period.')],
				'when' => ['type', 'in' => [CCepRuleHelper::CONDITION_TIME_PERIOD]]
			]
		]]))->getRules();
	}

	public static function getOperationConditionValidationRules(): array {
		return (new CFormValidator(['objects', 'fields' => [
			'execute_when' => ['db cep_operation.execute_when', 'required', 'in' => [
				CCepRuleHelper::WHEN_EVENT_OCCURRED, CCepRuleHelper::WHEN_EVENT_ADDED,
				CCepRuleHelper::WHEN_EVENT_EVICTED, CCepRuleHelper::WHEN_WINDOW_CLOSED,
				CCepRuleHelper::WHEN_PATTERN_MATCHED
			]],
			'type' => [
				[
					'integer', 'required', 'in' => [
						ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE, ZBX_CONDITION_TYPE_EVENT_OPEN,
						ZBX_CONDITION_TYPE_EVENT_SYMPTOM, ZBX_CONDITION_TYPE_EVENT_COPIED,
						ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
					],
					'when' => ['execute_when', 'in' => [CCepRuleHelper::WHEN_EVENT_OCCURRED]],
					'messages' => ['in' => _('Invalid value.')]
				],
				[
					'integer', 'required', 'in' => [
						ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE, ZBX_CONDITION_TYPE_EVENT_OPEN,
						ZBX_CONDITION_TYPE_EVENT_FIRST, ZBX_CONDITION_TYPE_EVENT_LAST, ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
						ZBX_CONDITION_TYPE_EVENT_COPIED, ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
					],
					'when' => ['execute_when', 'not_in' => [CCepRuleHelper::WHEN_EVENT_OCCURRED]],
					'messages' => ['in' => _('Invalid value.')]
				]
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
					'in' => [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
					],
					'when' => ['type',
						'in' => [ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]
					]
				]
			],
			'tag' => ['db cep_operation_condition.tag', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG]]
			],
			'tag_name' => ['db cep_operation_condition.tag', 'required', 'not_empty',
				'when' => ['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]]
			],
			'tag_value' => [
				['db cep_operation_condition.tag_value', 'required',
					'when' => ['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]]
				],
				['db cep_operation_condition.tag_value', 'required', 'not_empty',
					'when' => [
						['type', 'in' => [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE]],
						['operator', 'in' => [CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE,
							CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL
						]]
					]
				]
			]
		]]))->getRules();
	}
}
