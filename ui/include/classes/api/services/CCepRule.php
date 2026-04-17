<?php
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


/**
 * Class containing methods for operations with actions.
 */
class CCepRule extends CApiService {

	public const ACCESS_RULES = [
		'get' => 	['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	public const OUTPUT_FIELDS = ['cep_ruleid'];

	protected $tableName = 'cep_rule';
	protected $tableAlias = 'cr';
	protected $sortColumns = ['cep_ruleid', 'name', 'stop', 'sortorder', 'status'];

	private const OPERATION_TYPES_BY_EXECUTE_WHEN = [
		ZBX_CEP_OP_WHEN_EVENT_OCCURRED => [
			ZBX_CEP_OP_SET_NAME,
			ZBX_CEP_OP_CLOSE,
			ZBX_CEP_OP_DISCARD,
			ZBX_CEP_OP_SET_SEVERITY,
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_ADD_TAG,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_RENAME_TAG,
			ZBX_CEP_OP_REMOVE_TAG
		],
		ZBX_CEP_OP_WHEN_EVENT_EVICTED => [
			ZBX_CEP_OP_SET_NAME,
			ZBX_CEP_OP_CLOSE,
			ZBX_CEP_OP_SET_SEVERITY,
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_ADD_TAG,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_RENAME_TAG,
			ZBX_CEP_OP_REMOVE_TAG
		],
		'ZBX_CEP_OP_WHEN_EVENT_EVICTED_WITH_ZBX_CEP_EVICTION_CAUSE_CAPACITY' => [
			ZBX_CEP_OP_SET_NAME,
			ZBX_CEP_OP_CLOSE,
			ZBX_CEP_OP_DISCARD,
			ZBX_CEP_OP_SET_SEVERITY,
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_COPY_FIRST,
			ZBX_CEP_OP_COPY_LAST,
			ZBX_CEP_OP_ADD_TAG,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_RENAME_TAG,
			ZBX_CEP_OP_REMOVE_TAG
		],
		ZBX_CEP_OP_WHEN_WINDOW_CLOSED => [
			ZBX_CEP_OP_SET_NAME,
			ZBX_CEP_OP_CLOSE,
			ZBX_CEP_OP_DISCARD,
			ZBX_CEP_OP_SET_SEVERITY,
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_ADD_TAG,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_RENAME_TAG,
			ZBX_CEP_OP_REMOVE_TAG
		],
		ZBX_CEP_OP_WHEN_TAGS_CORRELATED => [
			ZBX_CEP_OP_SET_NAME,
			ZBX_CEP_OP_CLOSE,
			ZBX_CEP_OP_SET_SEVERITY,
			ZBX_CEP_OP_INCREASE_SEVERITY,
			ZBX_CEP_OP_DECREASE_SEVERITY,
			ZBX_CEP_OP_SUPPRESS,
			ZBX_CEP_OP_ADD_TAG,
			ZBX_CEP_OP_SET_TAG,
			ZBX_CEP_OP_SET_TAG_VALUE,
			ZBX_CEP_OP_INCREASE_TAG_VALUE,
			ZBX_CEP_OP_DECREASE_TAG_VALUE,
			ZBX_CEP_OP_RENAME_TAG,
			ZBX_CEP_OP_REMOVE_TAG
		],
		ZBX_CEP_OP_WHEN_PATTERN_MATCHED => [
			ZBX_CEP_OP_COPY_FIRST,
			ZBX_CEP_OP_COPY_LAST
		]
	];

	public function get($options = []): array|string {
	}

	protected function addRelatedObjects(array $options, array $result): void {

	}

	/**
	 * @param array $ceprules
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $ceprules): array {
		$this->validateCreate($ceprules);

		$ins_ceprules = [];

		foreach ($ceprules as $ceprule) {
			if (array_key_exists('filter', $ceprule)) {
				$ceprule['evaltype'] = $ceprule['filter']['evaltype'];
			}

			$ins_ceprules[] = $ceprule;
		}

		$cepruleids = DB::insert('cep_rule', $ins_ceprules);

		foreach ($ceprules as $index => &$ceprule) {
			$ceprule['cep_ruleid'] = $cepruleids[$index];
		}
		unset($ceprule);

		self::updateFilter($ceprules);
		self::updateWindow($ceprules);
		self::updateOperations($ceprules);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CEP_RULE, $ceprules);

		return ['cep_ruleids' => $cepruleids];
	}

	private function validateCreate(array &$ceprules): void {
		if (!CApiInputValidator::validate(self::getValidationRules(), $ceprules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($ceprules);
		self::checkFilter($ceprules);
		self::validateWindow($ceprules);
		self::checkWindowFilter($ceprules);
		self::validateOperations($ceprules);
	}

	private static function getValidationRules(bool $is_update = false): array {
		$flag_required = $is_update ? 0 : API_REQUIRED | API_NOT_EMPTY;

		$fields = $is_update
			? ['cep_ruleid' => 	['type' => API_ANY]]
			: [];
		$fields += [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => $flag_required, 'length' => DB::getFieldLength('cep_rule', 'name')],
			'filter' =>			['type' => API_OBJECT, 'fields' => [
				'evaltype' =>		['type' => API_INT32, 'in' => implode(',', [
										CONDITION_EVAL_TYPE_AND_OR,
										CONDITION_EVAL_TYPE_AND,
										CONDITION_EVAL_TYPE_OR,
										CONDITION_EVAL_TYPE_EXPRESSION
									]), 'default' => CONDITION_EVAL_TYPE_AND_OR],
				'formula' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' => implode(',', [
											CONDITION_EVAL_TYPE_EXPRESSION
										])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'formula')]
				]],
				'conditions' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' => implode(',', [
											CONDITION_EVAL_TYPE_EXPRESSION
										])], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
											'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED | API_NOT_EMPTY]
										] + self::getConditionFields()],
										['else' => true, 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => self::getConditionFields()]
				]]
			]],
			'window_type' =>	['type' => API_INT32, 'in' => implode(',', [
									ZBX_CEP_WINDOW_NONE,
									ZBX_CEP_WINDOW_SIMPLE,
									ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
									ZBX_CEP_WINDOW_TAG_MATCH,
									ZBX_CEP_WINDOW_PATTERN_MATCH
								])] + ($is_update ? [] : ['default' => ZBX_CEP_WINDOW_NONE]),
			'window' =>			['type' => API_ANY],
			'operations' =>		['type' => API_OBJECTS, 'flags' => $flag_required | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP])],
			'sortorder' =>		['type' => API_INT32, 'in' => ZBX_MIN_INT64.':'.ZBX_MAX_INT64],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED])]
		];

		return ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => $fields];
	}

	private static function getConditionFields(): array {
		return [
			'type' =>			['type' => API_INT32, 'in' => implode(',', [
									ZBX_CEP_CONDITION_EVENT_NAME,
									ZBX_CEP_CONDITION_TAG_NAME,
									ZBX_CEP_CONDITION_TAG_VALUE,
									ZBX_CEP_CONDITION_SEVERITY,
									ZBX_CEP_CONDITION_HOST,
									ZBX_CEP_CONDITION_HOST_GROUP,
									ZBX_CEP_CONDITION_TIME_PERIOD
								]), 'default' => ZBX_CEP_CONDITION_EVENT_NAME],
			'operator' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_CONDITION_EVENT_NAME,
										ZBX_CEP_CONDITION_HOST,
										ZBX_CEP_CONDITION_HOST_GROUP
									])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TAG_NAME])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE,
										CONDITION_OPERATOR_NOT_EXISTS
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TAG_VALUE])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE,
										CONDITION_OPERATOR_LESS_EQUAL,
										CONDITION_OPERATOR_MORE_EQUAL
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_SEVERITY])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LESS_EQUAL,
										CONDITION_OPERATOR_MORE_EQUAL
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TIME_PERIOD])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_IN,
										CONDITION_OPERATOR_NOT_IN
									])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_condition', 'operator')]
			]],
			'event_name' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_EVENT_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'event_name')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'event_name')]
			]],
			'tag' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TAG_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'tag')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'tag')]
			]],
			'tag_value' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'tag_value')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'tag_value')]
			]],
			'severity' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_SEVERITY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_condition', 'severity')]
			]],
			'host' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_HOST])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'host')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host')]
			]],
			'host_group' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_HOST_GROUP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'host_group')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host_group')]
			]],
			'time_period' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TIME_PERIOD])], 'type' => API_TIME_PERIOD, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'time_period')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'time_period')]
			]]
		];
	}

	private static function validateWindow(array &$ceprules): void {
		foreach ($ceprules as $i => &$ceprule) {
			$path = '/'.($i + 1);

			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
				'window' => $ceprule['window_type'] == ZBX_CEP_WINDOW_NONE
					? ['type' => API_OBJECT, 'fields' => [], 'unset' => true]
					: ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => self::getWindowFields($ceprule['window_type'])]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $ceprule, $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if ($ceprule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM) {
				if (!array_filter(array_intersect_key($ceprule['window'],
					array_flip(['group_by_host_group', 'group_by_host', 'group_by_tag'])
				))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/window', _('grouping criteria is missing')
					));
				}
			}

			if ($ceprule['window_type'] == ZBX_CEP_WINDOW_NONE) {
				continue;
			}

			$ceprule['window']['duration'] = timeUnitToSeconds($ceprule['window']['duration'], API_TIME_UNIT_WITH_YEAR);

			foreach (['group_by_host_group', 'group_by_host', 'group_by_tag'] as $field) {
				if (array_key_exists($field, $ceprule['window'])) {
					$ceprule['window'][$field] = (int) $ceprule['window'][$field];
				}
			}
		}
		unset($ceprule);
	}

	private static function getWindowFields(int $window_type): array {
		return [
			'duration' => ['type' => API_TIME_UNIT, 'flags' => API_TIME_UNIT_WITH_YEAR, 'in' => '1:'.SEC_PER_YEAR, 'default' => '1'],
			'capacity' => ['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT64],
			'filter' => $window_type != ZBX_CEP_WINDOW_NONE
				? ['type' => API_OBJECT, 'fields' => [
					'evaltype' =>	['type' => API_INT32, 'in' => implode(',', [
										CONDITION_EVAL_TYPE_AND_OR,
										CONDITION_EVAL_TYPE_AND,
										CONDITION_EVAL_TYPE_OR,
										CONDITION_EVAL_TYPE_EXPRESSION
									]), 'default' => CONDITION_EVAL_TYPE_AND_OR],
					'formula' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' =>implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'formula')]
					]],
					'conditions' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
											'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED | API_NOT_EMPTY]
										] + self::getWindowConditionFields()],
										['else' => true, 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => self::getWindowConditionFields()]
					]]
				]]
				: ['type' => API_OBJECT, 'fields' => [], 'unset' => true],
			'script' => $window_type == ZBX_CEP_WINDOW_PATTERN_MATCH
				? ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'script')]
				: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'script')],
			'group_by_host_group' => in_array($window_type, [
				ZBX_CEP_WINDOW_SIMPLE,
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
				ZBX_CEP_WINDOW_PATTERN_MATCH
			])
				? ['type' => API_BOOLEAN]
				: ['type' => API_BOOLEAN, 'in' => DB::getDefault('cep_window', 'group_by_host_group')],
			'group_by_host' => in_array($window_type, [
				ZBX_CEP_WINDOW_SIMPLE,
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
				ZBX_CEP_WINDOW_PATTERN_MATCH
			])
				? ['type' => API_BOOLEAN]
				: ['type' => API_BOOLEAN, 'in' => DB::getDefault('cep_window', 'group_by_host')],
			'group_by_tag' => in_array($window_type, [
				ZBX_CEP_WINDOW_SIMPLE,
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
				ZBX_CEP_WINDOW_PATTERN_MATCH
			])
				? ['type' => API_BOOLEAN, 'default' => false]
				: ['type' => API_BOOLEAN],
			'tag' =>	['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'group_by_tag', 'in' => (string) true], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'tag')],
							['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'tag')]
			]],
			'event_count_tag' => $window_type == ZBX_CEP_WINDOW_CAUSE_SYMPTOM
				? ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window', 'event_count_tag')]
				: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'event_count_tag')]
		];
	}

	private static function getWindowConditionFields(): array {
		return [
			'type' =>		['type' => API_INT32, 'in' => implode(',', [
								ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
							]), 'default' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR],
			'past_tag' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window_condition', 'past_tag')],
			'operator' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG
								])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL])],
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
								])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
								['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_window_condition', 'operator')]
			]],
			'tag_value' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE,
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_window_condition', 'tag_value')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window_condition', 'tag_value')]
			]],
			'tag' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_window_condition', 'tag')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window_condition', 'tag')]
			]]
		];
	}

	private static function validateOperations(array $ceprules): void {
		foreach ($ceprules as $i => &$ceprule) {
			if (!array_key_exists('operations', $ceprule)) {
				continue;
			}

			$path = '/'.($i + 1).'/operations';

			$allowed_execute_when = [ZBX_CEP_OP_WHEN_EVENT_OCCURRED];
			if ($ceprule['window_type'] != ZBX_CEP_WINDOW_NONE) {
				$allowed_execute_when[] = ZBX_CEP_OP_WHEN_EVENT_EVICTED;
			}
			$additional_when = match ($ceprule['window_type']) {
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM => ZBX_CEP_OP_WHEN_WINDOW_CLOSED,
				ZBX_CEP_WINDOW_TAG_MATCH => ZBX_CEP_OP_WHEN_TAGS_CORRELATED,
				ZBX_CEP_WINDOW_PATTERN_MATCH => ZBX_CEP_OP_WHEN_PATTERN_MATCHED,
				default => null
			};
			if ($additional_when !== null) {
				$allowed_execute_when[] = $additional_when;
			}

			foreach ($ceprule['operations'] as $j => &$operation) {
				$operation_path = $path.'/'.($j + 1);

				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'execute_when' =>	['type' => API_INT32, 'in' => implode(',', $allowed_execute_when), 'flags' => API_REQUIRED],
					'event_type' => $ceprule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM
						? ['type' => API_MULTIPLE, 'rules' => [
							['if' => ['field' => 'execute_when', 'in' => implode(',', [ZBX_CEP_OP_WHEN_EVENT_OCCURRED])], 'type' => API_INT32, 'in' => implode(',', [
								ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
								ZBX_CEP_EXECUTE_EVENT_TYPE_CAUSE,
								ZBX_CEP_EXECUTE_EVENT_TYPE_SYMPTOM
							])],
							['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'event_type')]
						]]
						: ['type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'event_type')],
					'eviction_cause' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'execute_when', 'in' => implode(',', [ZBX_CEP_OP_WHEN_EVENT_EVICTED])], 'type' => API_INT32, 'in' => implode(',', [
												ZBX_CEP_EVICTION_CAUSE_ANY,
												ZBX_CEP_EVICTION_CAUSE_DURATION,
												ZBX_CEP_EVICTION_CAUSE_CAPACITY
											]), 'default' => ZBX_CEP_EVICTION_CAUSE_ANY],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'eviction_cause')]
					]],
					'tags' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
						'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_tag', 'tag')],
						'operator' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
						'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation_tag', 'value')]
					]],
					'type' =>			['type' => API_ANY, 'flags' => API_REQUIRED],
					'evaltype' => 		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND])],
					'event_name' =>		['type' => API_ANY],
					'tag' =>			['type' => API_ANY],
					'new_tag' =>		['type' => API_ANY],
					'tag_value' =>		['type' => API_ANY],
					'severity' =>		['type' => API_ANY],
					'sortorder' =>		['type' => API_INT32, 'in' => ZBX_MIN_INT64.':'.ZBX_MAX_INT64]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $operation, $operation_path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$execute_when_index = $operation['execute_when'];
				if ($operation['execute_when'] == ZBX_CEP_OP_WHEN_EVENT_EVICTED
						&& $operation['eviction_cause'] == ZBX_CEP_EVICTION_CAUSE_CAPACITY) {
					$execute_when_index = 'ZBX_CEP_OP_WHEN_EVENT_EVICTED_WITH_ZBX_CEP_EVICTION_CAUSE_CAPACITY';
				}

				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'type' =>	['type' => API_INT32, 'in' => implode(',', self::OPERATION_TYPES_BY_EXECUTE_WHEN[$execute_when_index])]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $operation, $operation_path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				self::validateOperationFieldsByType($operation, $operation_path);
			}
			unset($operation);
		}
		unset($ceprule);
	}

	private static function validateOperationFieldsByType(array &$operation, string $operation_path): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'type' =>			['type' => API_ANY],
			'event_name' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_OP_SET_NAME
									])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation', 'event_name')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'event_name')]
			]],
			'tag' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_OP_ADD_TAG,
										ZBX_CEP_OP_SET_TAG,
										ZBX_CEP_OP_SET_TAG_VALUE,
										ZBX_CEP_OP_INCREASE_TAG_VALUE,
										ZBX_CEP_OP_DECREASE_TAG_VALUE,
										ZBX_CEP_OP_RENAME_TAG,
										ZBX_CEP_OP_REMOVE_TAG
									])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation', 'tag')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag')]
			]],
			'new_tag' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_OP_RENAME_TAG
									])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'new_tag')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'new_tag')]
			]],
			'tag_value' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_OP_ADD_TAG,
										ZBX_CEP_OP_SET_TAG,
										ZBX_CEP_OP_SET_TAG_VALUE
									])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation', 'tag_value')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag_value')]
			]],
			'severity' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										ZBX_CEP_OP_SET_SEVERITY
									])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'severity')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $operation, $operation_path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function checkDuplicates(array $ceprules, ?array $db_ceprules = null): void {
		$names = [];

		foreach ($ceprules as $ceprule) {
			if ($db_ceprules === null || $ceprule['name'] !== $db_ceprules[$ceprule['cep_ruleid']]['name']) {
				$names[] = $ceprule['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicates = DB::select('cep_rule', [
			'output' => ['name'],
			'filter' => ['name' => $names],
			'limit' => 1
		]);

		if ($duplicates) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _s('CEP rule "%1$s" already exists.', $duplicates[0]['name']));
		}
	}

	private static function checkFilter(array $ceprules): void {
		$condition_formula_parser = new CConditionFormula();

		foreach ($ceprules as $i => $ceprule) {
			if (!array_key_exists('filter', $ceprule)) {
				continue;
			}

			$path = '/'.($i + 1).'/filter';

			if ($ceprule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$condition_formula_parser->parse($ceprule['filter']['formula']);
				$constants = array_column($condition_formula_parser->constants, 'value', 'value');

				if (count($ceprule['filter']['conditions']) != count($constants)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', $path.'/conditions', _('incorrect number of conditions'))
					);
				}

				foreach ($ceprule['filter']['conditions'] as $j => $condition) {
					if (!array_key_exists($condition['formulaid'], $constants)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
						));
					}
				}
			}
		}
	}

	private static function checkWindowFilter(array $ceprules): void {
		$condition_formula_parser = new CConditionFormula();

		foreach ($ceprules as $i => $ceprule) {
			if (!array_key_exists('window', $ceprule) || !array_key_exists('filter', $ceprule['window'])) {
				continue;
			}

			$path = '/'.($i + 1).'/window/filter';

			if ($ceprule['window']['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$condition_formula_parser->parse($ceprule['window']['filter']['formula']);
				$constants = array_column($condition_formula_parser->constants, 'value', 'value');

				if (count($ceprule['window']['filter']['conditions']) != count($constants)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Invalid parameter "%1$s": %2$s.', $path.'/conditions', _('incorrect number of conditions'))
					);
				}

				foreach ($ceprule['window']['filter']['conditions'] as $j => $condition) {
					if (!array_key_exists($condition['formulaid'], $constants)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
							$path.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
						));
					}
				}
			}
		}
	}

	private static function updateFilter(array &$ceprules, ?array $db_ceprules = null): void {
		$ins_conditions = [];
		$upd_conditions = [];
		$del_conditionids = [];
		$db_defaults = DB::getDefaults('cep_condition');

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('filter', $ceprule) || !array_key_exists('conditions', $ceprule['filter'])) {
				continue;
			}

			$db_conditions = $db_ceprules !== null ? $db_ceprules[$ceprule['cep_ruleid']]['filter']['conditions'] : [];

			foreach ($ceprule['filter']['conditions'] as &$condition) {
				if ($db_condition = array_shift($db_conditions)) {
					$condition['cep_conditionid'] = $db_condition['cep_conditionid'];
					$condition += $db_defaults;
					$upd_condition = DB::getUpdatedValues('cep_condition', $condition, $db_condition);

					if ($upd_condition) {
						$upd_conditions[] = [
							'values' => $upd_condition,
							'where' => ['cep_conditionid' => $db_condition['cep_conditionid']]
						];
					}
				}
				else {
					$ins_conditions[] = ['cep_ruleid' => $ceprule['cep_ruleid']] + $condition;
				}
			}
			unset($condition);

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($ceprule);

		if ($del_conditionids) {
			DB::delete('cep_condition', ['cep_conditionid' => $del_conditionids]);
		}

		if ($upd_conditions) {
			DB::update('cep_condition', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_condition', $ins_conditions);
		}

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('filter', $ceprule) || !array_key_exists('conditions', $ceprule['filter'])) {
				continue;
			}

			foreach ($ceprule['filter']['conditions'] as &$condition) {
				if (!array_key_exists('cep_conditionid', $condition)) {
					$condition['cep_conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($ceprule);

		self::updateFilterFormula($ceprules, $db_ceprules);
	}

	private static function updateFilterFormula(array &$ceprules, ?array $db_ceprules = null): void {
		$upd_ceprules = [];

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('filter', $ceprule)
					|| $ceprule['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				continue;
			}

			CConditionHelper::replaceFormulaIds($ceprule['filter']['formula'],
				array_column($ceprule['filter']['conditions'], null, 'cep_conditionid')
			);

			$db_formula = $db_ceprules !== null ? $db_ceprules[$ceprule['cep_ruleid']]['filter']['formula'] : '';

			if ($ceprule['filter']['formula'] !== $db_formula) {
				$upd_ceprules[] = [
					'values' => ['formula' => $ceprule['filter']['formula']],
					'where' => ['cep_ruleid' => $ceprule['cep_ruleid']]
				];
			}
		}
		unset($ceprule);

		if ($upd_ceprules) {
			DB::update('cep_rule', $upd_ceprules);
		}
	}

	private static function updateWindow(array &$ceprules, ?array $db_ceprules = null): void {
		$del_windowids = [];
		$upd_windows = [];
		$ins_windows = [];
		$db_defaults = DB::getDefaults('cep_window');

		foreach ($ceprules as &$ceprule) {
			$cepruleid = $ceprule['cep_ruleid'];

			if ($ceprule['window_type'] == ZBX_CEP_WINDOW_NONE) {
				if ($db_ceprules !== null && $db_ceprules[$cepruleid]['window_type'] != ZBX_CEP_WINDOW_NONE) {
					$del_windowids[] = $db_ceprules[$cepruleid]['window']['cep_windowid'];
				}
			}
			else {
				if (!array_key_exists('window', $ceprule)) {
					continue;
				}

				if ($db_ceprules === null || $db_ceprules[$cepruleid]['window_type'] == ZBX_CEP_WINDOW_NONE) {
					$ins_windows[] = ['cep_ruleid' => $cepruleid] + $ceprule['window'];
				}
				else {
					$db_window = $db_ceprules[$cepruleid]['window'];
					$ceprule['window'] += $db_defaults;
					$upd_window = DB::getUpdatedValues('cep_window', $ceprule['window'], $db_window);

					if ($upd_window) {
						$upd_windows[] = ['cep_windowid' => $db_window['cep_windowid']] + $upd_window;
					}
				}
			}
		}
		unset($ceprule);

		if ($del_windowids) {
			DB::delete('cep_window', ['cep_windowid' => $del_windowids]);
		}

		if ($upd_windows) {
			DB::update('cep_window', $upd_windows);
		}

		if ($ins_windows) {
			$windowids = DB::insert('cep_window', $ins_windows, true);
		}

		foreach ($ceprules as &$ceprule) {
			if (array_key_exists('window', $ceprule) && !array_key_exists('cep_windowid', $ceprule['window'])) {
				$ceprule['window']['cep_windowid'] = array_shift($windowids);
			}
		}
		unset($ceprule);

		self::updateWindowConditions($ceprules, $db_ceprules);
		self::updateWindowFormula($ceprules, $db_ceprules);
	}

	private static function updateWindowConditions(array &$ceprules, ?array $db_ceprules = null): void {
		$del_conditionids = [];
		$upd_conditions = [];
		$ins_conditions = [];
		$db_defaults = DB::getDefaults('cep_window_condition');

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('window', $ceprule) || !array_key_exists('conditions', $ceprule['window'])) {
				continue;
			}

			$cepruleid = $ceprule['cep_ruleid'];
			$db_ceprule = $db_ceprules !== null ? $db_ceprules[$cepruleid] : [];
			$db_ceprule += ['window' => []];
			$db_ceprule['window'] += ['conditions' => []];
			$db_conditions = $db_ceprule['window']['conditions'];

			foreach ($ceprule['window']['conditions'] as &$condition) {
				if ($db_condition = array_shift($db_conditions)) {
					$condition['cep_window_conditionid'] = $db_condition['cep_window_conditionid'];
					$condition += $db_defaults;
					$upd_condition = DB::getUpdatedValues('cep_window_condition', $condition, $db_condition);

					if ($upd_condition) {
						$upd_conditions[] = [
							'values' => $upd_condition,
							'where' => ['cep_window_conditionid' => $db_condition['cep_window_conditionid']]
						];
					}
				}
				else {
					$ins_conditions[] = ['cep_windowid' => $ceprule['window']['cep_windowid']] + $condition;
				}
			}
			unset($condition);

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($ceprule);

		if ($del_conditionids) {
			DB::delete('cep_window_condition', ['cep_window_conditionid' => $del_conditionids]);
		}

		if ($upd_conditions) {
			DB::update('cep_window_condition', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_window_condition', $ins_conditions);
		}

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('window', $ceprule) || !array_key_exists('conditions', $ceprule['window'])) {
				continue;
			}

			foreach ($ceprule['window']['conditions'] as &$condition) {
				if (!array_key_exists('cep_window_conditionid', $condition)) {
					$condition['cep_window_conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($ceprule);
	}

	private static function updateWindowFormula(array &$ceprules, ?array $db_ceprules = null): void {
		$upd_windows = [];

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('window', $ceprule)
					|| !array_key_exists('filter', $ceprule['window'])
					|| $ceprule['window']['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
				continue;
			}

			CConditionHelper::replaceFormulaIds($ceprule['window']['formula'],
				array_column($ceprule['window']['conditions'], null, 'cep_window_conditionid')
			);

			$db_ceprule = $db_ceprules === null ? [] : $db_ceprules[$ceprule['cep_ruleid']];
			$db_ceprule += ['window' => []];
			$db_ceprule['window'] += ['formula' => ''];

			if ($ceprule['window']['formula'] !== $db_ceprule['window']['formula']) {
				$upd_windows[] = [
					'values' => ['formula' => $ceprule['window']['formula']],
					'where' => ['cep_windowid' => $ceprule['window']['cep_windowid']]
				];
			}
		}
		unset($ceprule);

		if ($upd_windows) {
			DB::update('cep_window', $upd_windows);
		}
	}

	private static function updateOperations(array &$ceprules, ?array $db_ceprules = null): void {
		$del_operationids = [];
		$upd_operations = [];
		$ins_operations = [];
		$db_defaults = DB::getDefaults('cep_operation');

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('operations', $ceprule)) {
				continue;
			}

			$cepruleid = $ceprule['cep_ruleid'];
			$db_operations = $db_ceprules !== null ? $db_ceprules[$cepruleid]['operations'] : [];

			foreach ($ceprule['operations'] as &$operation) {
				if ($db_operation = array_shift($db_operations)) {
					$operation['cep_operationid'] = $db_operation['cep_operationid'];
					$operation += $db_defaults;
					$upd_operation = DB::getUpdatedValues('cep_operation', $operation, $db_operation);

					if ($upd_operation) {
						$upd_operation[] = [
							'values' => $upd_operation,
							'where' => ['cep_operationid' => $db_operation['cep_operationid']]
						];
					}
				}
				else {
					$ins_operations[] = ['cep_ruleid' => $cepruleid] + $operation;
				}
			}
			unset($operation);

			$del_operationids = array_merge($del_operationids, array_keys($db_operations));
		}
		unset($ceprule);

		if ($del_operationids) {
			DB::delete('cep_operation', ['cep_operationid' => $del_operationids]);
		}

		if ($upd_operations) {
			DB::update('cep_operation', $upd_operations);
		}

		if ($ins_operations) {
			$operationids = DB::insert('cep_operation', $ins_operations);
		}

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('operations', $ceprule)) {
				continue;
			}

			foreach ($ceprule['operations'] as &$operation) {
				if (!array_key_exists('cep_operationid', $operation)) {
					$operation['cep_operationid'] = array_shift($operationids);
				}
			}
			unset($operation);
		}
		unset($ceprule);

		self::updateOperationTags($ceprules, $db_ceprules);
	}

	private static function updateOperationTags(array &$ceprules, ?array $db_ceprules = null): void {
		$del_tagids = [];
		$upd_tags = [];
		$ins_tags = [];
		$db_defaults = DB::getDefaults('cep_operation_tag');

		foreach ($ceprules as &$ceprule) {
			if (!array_key_exists('operations', $ceprule)) {
				continue;
			}

			$cepruleid = $ceprule['cep_ruleid'];
			$db_operations = $db_ceprules !== null ? $db_ceprules[$cepruleid]['operations'] : [];

			foreach ($ceprule['operations'] as &$operation) {
				if (!array_key_exists('tags', $operation)) {
					continue;
				}

				$db_operation = array_key_exists($operation['cep_operationid'], $db_operations)
					? $db_operations[$operation['cep_operationid']]
					: [];
				$db_operation += ['tags' => []];
				$db_tags = $db_operation['tags'];

				foreach ($operation['tags'] as &$tag) {
					if ($db_tag = array_shift($db_tags)) {
						$tag['cep_operation_tagid'] = $db_tag['cep_operation_tagid'];
						$tag += $db_defaults;
						$upd_tag = DB::getUpdatedValues('cep_operation_tag', $tag, $db_tag);

						if ($upd_tag) {
							$upd_tags[] = [
								'values' => $upd_tag,
								'where' => ['cep_operation_tagid' => $db_tag['cep_operation_tagid']]
							];
						}
					}
					else {
						$ins_tags[] = ['cep_operationid' => $operation['cep_operationid']] + $tag;
					}
				}
				unset($tag);

				$del_tagids = array_merge($del_tagids, array_keys($db_tags));
			}
			unset($operation);
		}
		unset($ceprule);

		if ($del_tagids) {
			DB::delete('cep_operation_tag', ['cep_operation_tagid' => $del_tagids]);
		}

		if ($upd_tags) {
			DB::update('cep_operation_tag', $upd_tags);
		}

		if ($ins_tags) {
			$tagids = DB::insert('cep_operation_tag', $ins_tags);
		}

		foreach ($ceprules as &$ceprule) {
			if (array_key_exists('operations', $ceprule)) {
				foreach ($ceprule['operations'] as &$operation) {
					if (array_key_exists('tags', $operation)) {
						foreach ($operation['tags'] as &$tag) {
							if (!array_key_exists('cep_operation_tagid', $tag)) {
								$tag['cep_operation_tagid'] = array_shift($tagids);
							}
						}
						unset($tag);
					}
				}
				unset($operation);
			}
		}
		unset($ceprule);
	}

	public function update(array $ceprules): array {
		$this->validateUpdate($ceprules, $db_ceprules);

		self::updateForce($ceprules, $db_ceprules);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CEP_RULE, $ceprules, $db_ceprules);

		return ['cep_ruleids' => array_column($ceprules, 'cep_ruleid')];
	}

	private function validateUpdate(array &$ceprules, ?array &$db_ceprules = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['cep_ruleid']], 'fields' => [
			'cep_ruleid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $ceprules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_ceprules = $this->get([
			'output' => ['cep_ruleid', 'name', 'evaltype', 'formula', 'window_type', 'stop', 'sortorder', 'description',
				'status'
			],
			'cep_ruleids' => array_column($ceprules, 'cepruleid'),
			'preservekeys' => true
		]);

		if (count($ceprules) != count($db_ceprules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$ceprules = $this->extendObjectsByKey($ceprules, $db_ceprules, 'cep_ruleid', ['name', 'window_type']);

		if (!CApiInputValidator::validate(self::getValidationRules(true), $ceprules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($ceprules, $db_ceprules);

		self::addAffectedObjects($ceprules, $db_ceprules);

		self::checkFilter($ceprules);
		self::validateWindow($ceprules);
		self::checkWindowFilter($ceprules);
		self::validateOperations($ceprules);
	}

	private static function addAffectedObjects(array $ceprules, ?array &$db_ceprules = null): void {
		foreach ($ceprules as $ceprule) {
			$cepruleid = $ceprule['cep_ruleid'];

			if (array_key_exists('filter', $ceprule) && $db_ceprules !== null) {
				$db_ceprules[$cepruleid]['filter'] =
					array_intersect_key($db_ceprules[$cepruleid], array_flip(['evaltype', 'formula'])) +
					['conditions' => []];
			}
		}

		self::addAffectedFilterConditions($ceprules, $db_ceprules);
		self::addAffectedWindow($ceprules, $db_ceprules);
		self::addAffectedOperations($ceprules, $db_ceprules);
	}

	private static function addAffectedFilterConditions(array $ceprules, ?array &$db_ceprules = null): void {
		$cepruleids = [];

		foreach ($ceprules as $ceprule) {
			if (!array_key_exists('filter', $ceprule) || !array_key_exists('conditions', $ceprule['filter'])) {
				continue;
			}

			$cepruleids[] = $ceprule['cep_ruleid'];
			$db_ceprules[$ceprule['cep_ruleid']]['filter']['conditions'] = [];
		}

		if (!$cepruleids) {
			return;
		}

		$options = [
			'output' => ['cep_conditionid', 'cep_ruleid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
				'severity', 'host', 'host_group', 'time_period'
			],
			'filter' => ['cep_ruleid' => $cepruleids],
			'sortfield' => ['cep_conditionid']
		];
		$db_conditions = DB::select('cep_condition', $options);

		while ($row = DBfetch($db_conditions)) {
			$db_ceprules[$row['cep_ruleid']]['filter']['conditions'][$row['cep_conditionid']] =
				array_diff_key($row, array_flip(['cep_ruleid']));
		}
	}

	private static function addAffectedWindow(array $ceprules, ?array &$db_ceprules = null): void {
		$cepruleids = [];

		foreach ($ceprules as $ceprule) {
			$cepruleid = $ceprule['cep_ruleid'];

			if (array_key_exists('window', $ceprule)
					|| ($db_ceprules !== null
							&& $ceprule['window_type'] != $db_ceprules[$cepruleid]['window_type'])
							&& $db_ceprules[$cepruleid]['window_type'] != ZBX_CEP_WINDOW_NONE) {
				$cepruleids[] = $cepruleid;
				$db_ceprules[$cepruleid]['window'] = [];
			}
		}

		if (!$cepruleids) {
			return;
		}

		$options = [
			'output' => ['cep_windowid', 'cep_ruleid', 'duration', 'capacity', 'script', 'group_by_host_group',
				'group_by_host', 'group_by_tag', 'tag', 'event_count_tag'
			],
			'filter' => ['cep_ruleid' => $cepruleids]
		];
		$db_windows = DB::select('cep_window', $options);

		while ($row = DBfetch($db_windows)) {
			$db_ceprules[$row['cep_ruleid']]['window'] = array_diff_key($row, array_flip(['cep_ruleid']));
		}

		self::addAffectedWindowConditions($ceprules, $db_ceprules);
	}

	private static function addAffectedWindowConditions(array $ceprules, array &$db_ceprules): void {
		$windowids = [];

		foreach ($ceprules as $ceprule) {
			if (!array_key_exists('window', $ceprule) || !array_key_exists('conditions', $ceprule['window'])) {
				continue;
			}

			$cepruleid = $ceprule['cep_ruleid'];
			$db_ceprules[$cepruleid]['window']['conditions'] = [];

			if (array_key_exists('cep_windowid', $db_ceprules[$cepruleid]['window'])) {
				$windowids[$db_ceprules[$cepruleid]['window']['cep_windowid']] = $cepruleid;
			}
		}

		if (!$windowids) {
			return;
		}

		$options = [
			'output' => ['cep_windowid', 'type', 'operator', 'name', 'value'],
			'filter' => ['cep_windowid' => array_keys($windowids)],
			'sortfield' => ['cep_window_conditionid']
		];
		$db_windows = DB::select('cep_window_condition', $options);

		while ($row = DBfetch($db_windows)) {
			$cepruleid = $windowids[$row['cep_windowid']];

			$db_ceprules[$cepruleid]['window']['conditions'][$row['cep_window_conditionid']] =
				array_diff_key($row, array_flip(['cep_windowid']));
		}
	}

	private static function addAffectedOperations(array $ceprules, ?array &$db_ceprules = null): void {
		$cepruleids = [];

		foreach ($ceprules as $ceprule) {
			$cepruleid = $ceprule['cep_ruleid'];

			if (array_key_exists('operations', $ceprule)) {
				$cepruleids[] = $cepruleid;
				$db_ceprules[$cepruleid]['operations'] = [];
			}
		}

		if (!$cepruleids) {
			return;
		}

		$db_windows = DB::select('cep_operation', [
			'output' => ['cep_operationid', 'cep_ruleid', 'execute_when', 'event_type', 'eviction_cause', 'type',
				'evaltype', 'event_name', 'tag', 'new_tag', 'tag_value', 'severity', 'sortorder'
			],
			'filter' => ['cep_ruleid' => $cepruleids],
			'sortfield' => ['sortorder']
		]);
		$operation_rules = [];

		while ($row = DBfetch($db_windows)) {
			$db_ceprules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] =
				array_diff_key($row, array_flip(['cep_ruleid'])) + ['tags' => []];

			$operation_rules[$row['cep_operationid']] = $row['cep_ruleid'];
		}

		if ($operation_rules) {
			$db_tags = DB::select('cep_operation_tag', [
				'output' => ['cep_operation_tagid', 'cep_operationid', 'tag', 'operator', 'value'],
				'filter' => ['cep_operationid' => array_keys($operation_rules)],
				'sortfield' => ['sortorder']
			]);

			while ($row = DBfetch($db_tags)) {
				$cepruleid = $operation_rules[$row['cep_operationid']];
				$db_ceprules[$cepruleid]['operations'][$row['cep_operationid']]['tags'][$row['cep_operation_tagid']] =
					array_diff($row, array_flip(['cep_operationid']));
			}
		}
	}

	private static function updateForce(array $ceprules, ?array &$db_ceprules = null): void {
		$upd_ceprules = [];

		foreach ($ceprules as $ceprule) {
			$db_ceprule = $db_ceprules[$ceprule['cep_ruleid']];

			if (array_key_exists('filter', $ceprule)) {
				$ceprule['evaltype'] = $ceprule['filter']['evaltype'];
			}

			$upd_ceprule = DB::getUpdatedValues('cep_rule', $ceprule, $db_ceprule);

			if ($upd_ceprule) {
				$upd_ceprules[] = [
					'values' => $upd_ceprule,
					'where' => ['cep_ruleid' => $ceprule['cep_ruleid']]
				];
			}
		}

		if ($upd_ceprules) {
			DB::update('cep_rule', $upd_ceprules);
		}

		self::updateFilter($ceprules, $db_ceprules);
		self::updateWindow($ceprules, $db_ceprules);
		self::updateOperations($ceprules, $db_ceprules);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CEP_RULE, $ceprules, $db_ceprules);
	}

	public function delete(array $cepruleids): array {
		$this->validateDelete($cepruleids, $db_ceprules);

		DB::delete('cep_rule', ['cep_ruleid' => $cepruleids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_CEP_RULE, $db_ceprules);

		return ['cep_ruleids' => $cepruleids];
	}

	private function validateDelete(array &$cepruleids, ?array &$db_ceprules): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $cepruleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_ceprules = $this->get([
			'output' => ['cep_ruleid', 'name'],
			'cep_ruleids' => $cepruleids
		]);

		if (count($db_ceprules) != count($cepruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
