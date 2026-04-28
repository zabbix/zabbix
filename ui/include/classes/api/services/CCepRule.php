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
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	public const OUTPUT_FIELDS = ['cep_ruleid'];

	protected $tableName = 'cep_rule';
	protected $tableAlias = 'cr';
	protected $sortColumns = ['cep_ruleid', 'name', 'stop', 'sortorder', 'status'];

	private const EXECUTE_WHEN_BY_WINDOW_TYPE = [
		ZBX_CEP_WINDOW_NONE => [
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED
		],
		ZBX_CEP_WINDOW_SIMPLE => [
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
			ZBX_CEP_OP_WHEN_EVENT_EVICTED
		],
		ZBX_CEP_WINDOW_CAUSE_SYMPTOM => [
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
			ZBX_CEP_OP_WHEN_EVENT_EVICTED,
			ZBX_CEP_OP_WHEN_WINDOW_CLOSED
		],
		ZBX_CEP_WINDOW_TAG_MATCH => [
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
			ZBX_CEP_OP_WHEN_EVENT_EVICTED,
			ZBX_CEP_OP_WHEN_TAGS_CORRELATED
		],
		ZBX_CEP_WINDOW_PATTERN_MATCH => [
			ZBX_CEP_OP_WHEN_EVENT_OCCURRED,
			ZBX_CEP_OP_WHEN_EVENT_EVICTED,
			ZBX_CEP_OP_WHEN_PATTERN_MATCHED
		]
	];

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
			ZBX_CEP_EVICTION_CAUSE_ANY => [
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
			ZBX_CEP_EVICTION_CAUSE_DURATION => [
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
			ZBX_CEP_EVICTION_CAUSE_CAPACITY => [
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
			]
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
		$this->validateGet($options);

		$cep_rules = [];

		$res = DBselect($this->createSelectQuery('cep_rule', $options));

		while ($row = DBfetch($res)) {
			if ($options['countOutput']) {
				$cep_rules = $row['rowscount'];
			}
			else {
				$cep_rules[$row['cep_ruleid']] = $row;
			}
		}

		if ($options['countOutput']) {
			return $cep_rules;
		}

		if ($cep_rules) {
			$cep_rules = $this->addRelatedObjects($options, $cep_rules);
			$cep_rules = $this->unsetExtraFields($cep_rules, ['formula', 'evaltype']);
		}

		if (!$options['preservekeys']) {
			$cep_rules = array_values($cep_rules);
		}

		return $cep_rules;
	}

	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filter.
			'cep_ruleids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['cep_ruleid', 'name', 'window_type', 'stop', 'sortorder', 'status']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'description']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['cep_ruleid', 'name', 'window_type', 'stop', 'sortorder', 'description', 'status', 'filter']), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			'selectFilter' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', ['conditions', 'evaltype', 'eval_formula', 'formula']), 'default' => null],
			'selectWindow' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', ['duration', 'capacity', 'filter', 'script', 'group_by_host_group', 'group_by_host', 'group_by_tag', 'tag', 'event_count_tag']), 'default' => null],
			'selectOperations' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', ['step', 'execute_when', 'event_type', 'eviction_cause', 'type', 'evaltype', 'event_name', 'tag', 'new_tag', 'tag_value', 'severity', 'tags']), 'default' => null],
			// Sort and limit.
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// Flags.
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	protected function applyQueryOutputOptions($table_name, $table_alias, array $options, array $sql_parts): array {
		$sql_parts = parent::applyQueryOutputOptions($table_name, $table_alias, $options, $sql_parts);

		if (!$options['countOutput']) {
			if ($this->outputIsRequested('formula', $options['selectFilter'])
					|| $this->outputIsRequested('eval_formula', $options['selectFilter'])
					|| $this->outputIsRequested('conditions', $options['selectFilter'])) {

				$sql_parts = $this->addQuerySelect('cr.formula', $sql_parts);
				$sql_parts = $this->addQuerySelect('cr.evaltype', $sql_parts);
			}

			if ($this->outputIsRequested('evaltype', $options['selectFilter'])) {
				$sql_parts = $this->addQuerySelect('cr.evaltype', $sql_parts);
			}
		}

		return $sql_parts;
	}

	protected function addRelatedObjects(array $options, array $cep_rules): array {
		self::addRelatedFilter($options, $cep_rules);
		self::addRelatedWindow($options, $cep_rules);
		self::addRelatedOperations($options, $cep_rules);

		return $cep_rules;
	}

	private function addRelatedFilter(array $options, array &$cep_rules): void {
		if ($options['selectFilter'] === null) {
			return;
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['filter'] = [];
		}
		unset($cep_rule);

		$has_conditions = in_array('conditions', $options['selectFilter']);
		$has_evaltype = in_array('evaltype', $options['selectFilter']);
		$has_eval_formula = in_array('eval_formula', $options['selectFilter']);
		$has_formula = in_array('formula', $options['selectFilter']);

		if ($has_conditions || $has_eval_formula || $has_formula) {
			$conditions_options = [
				'output' => ['cep_ruleid', 'cep_conditionid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
					'severity', 'host', 'host_group', 'time_period'
				],
				'filter' => ['cep_ruleid' => array_keys($cep_rules)],
				'sortfield' => ['cep_conditionid'],
				'preservekeys' => true
			];
			$db_conditions = DBselect(DB::makeSql('cep_condition', $conditions_options));
			$cep_conditions = [];

			while ($db_condition = DBfetch($db_conditions)) {
				$cep_conditions[$db_condition['cep_ruleid']][$db_condition['cep_conditionid']] =
					array_diff_key($db_condition, array_flip(['cep_ruleid', 'cep_conditionid']));
			}

			foreach ($cep_rules as &$cep_rule) {
				$eval_formula = '';
				$filter = [];
				$conditions = array_key_exists($cep_rule['cep_ruleid'], $cep_conditions)
					? array_values($cep_conditions[$cep_rule['cep_ruleid']])
					: [];

				if ($cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$eval_formula = $cep_rule['formula'];
				}
				else {
					$eval_formula =
						CConditionHelper::getEvalFormula($conditions, 'type', (int)$cep_rule['evaltype']);
				}

				CConditionHelper::addFormulaIds($conditions, $eval_formula);
				CConditionHelper::replaceConditionIds($eval_formula, $conditions);

				if ($has_conditions) {
					$filter['conditions'] = $conditions;
				}

				if ($has_evaltype) {
					$filter['evaltype'] = $cep_rule['evaltype'];
				}

				if ($has_eval_formula) {
					$filter['eval_formula'] = '';
				}

				if ($has_formula) {
					$filter['formula'] = $cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
						? $eval_formula
						: '';
				}

				$cep_rule['filter'] = $filter;
			}
		}
	}

	private static function addRelatedWindow(array $options, array &$cep_rules): void {
		if ($options['selectWindow'] === null) {
			return;
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['window'] = [];
		}
		unset($cep_rule);

		$has_filter = in_array('filter', $options['selectWindow']);
		$output_fields = ['cep_ruleid', 'cep_windowid'];

		if ($has_filter) {
			$output_fields = array_merge($output_fields, ['evaltype', 'formula']);
			unset($options['selectWindow'][array_search('filter', $options['selectWindow'])]);
		}

		$window_options = [
			'output' => array_merge($output_fields, $options['selectWindow']),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)]
		];
		$resource = DBselect(DB::makeSql('cep_window', $window_options));

		$window_ruleids = [];
		while ($row = DBfetch($resource)) {
			$window = array_diff_key($row, array_flip(['cep_ruleid', 'cep_windowid', 'formula', 'evaltype']));
			if ($has_filter) {
				$window['filter']['evaltype'] = $row['evaltype'];
				$window['filter']['formula'] = $row['formula'];

				$window_ruleids[$row['cep_windowid']] = $row['cep_ruleid'];
			}
			$cep_rules[$row['cep_ruleid']]['window'] = $window;
		}

		if (!$window_ruleids) {
			return;
		}

		$window_conditions_options = [
			'output' => ['cep_windowid', 'type', 'past_tag', 'operator', 'tag', 'tag_value'],
			'filter' => ['cep_windowid' => array_keys($window_ruleids)]
		];
		$conditions_resource = DBselect(DB::makeSql('cep_window_condition', $window_conditions_options));

		while ($row = DBfetch($conditions_resource)) {
			$cep_ruleid = $window_ruleids[$row['cep_windowid']];

			$cep_rules[$cep_ruleid]['window']['filter']['conditions'][] =
				array_diff_key($row, array_flip(['cep_window_conditionid', 'cep_windowid']));
		}
	}

	private static function addRelatedOperations(array $options, array &$cep_rules): void {
		if ($options['selectOperations'] === null) {
			return;
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['operations'] = [];
		}
		unset($cep_rule);

		$has_tags = in_array('tags', $options['selectOperations']);
		if ($has_tags) {
			unset($options['selectOperations'][array_search('tags', $options['selectOperations'])]);
		}

		$operations_options = [
			'output' => array_merge(['cep_ruleid', 'cep_operationid'], $options['selectOperations']),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)],
			'sortfield' => ['cep_operationid']
		];
		$resource = DBselect(DB::makeSql('cep_operation', $operations_options));

		$operation_ruleids = [];
		while ($row = DBfetch($resource)) {
			if ($has_tags) {
				$row['tags'] = [];
				$operation_ruleids[$row['cep_operationid']] = $row['cep_ruleid'];
			}

			$cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] =
				array_diff_key($row, array_flip(['cep_ruleid', 'cep_operationid']));
		}

		if ($has_tags) {
			$operation_tags_options = [
				'output' => ['cep_operationid', 'tag', 'operator', 'value'],
				'filter' => ['cep_operationid' => array_keys($operation_ruleids)]
			];
			$resource = DBselect(DB::makeSql('cep_operation_tag', $operation_tags_options));
			while ($row = DBfetch($resource)) {
				$cep_ruleid = $operation_ruleids[$row['cep_operationid']];

				$cep_rules[$cep_ruleid]['operations'][$row['cep_operationid']]['tags'][] =
					array_diff_key($row, array_flip(['cep_operationid', 'cep_operation_tagid']));
			}
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['operations'] = array_values($cep_rule['operations']);
		}
		unset($cep_rule);
	}

	/**
	 * @param array $cep_rules
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	public function create(array $cep_rules): array {
		$this->validateCreate($cep_rules);

		$cep_ruleids = DB::insert('cep_rule', $cep_rules);

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['cep_ruleid'] = array_shift($cep_ruleids);
		}
		unset($cep_rule);

		self::updateFilter($cep_rules);
		self::updateWindow($cep_rules);
		self::updateOperations($cep_rules);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CEP_RULE, $cep_rules);

		return ['cep_ruleids' => array_column($cep_rules, 'cep_ruleid')];
	}

	private function validateCreate(array &$cep_rules): void {
		if (!CApiInputValidator::validate(self::getValidationRules(), $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($cep_rules);
		self::validateFilter($cep_rules);
		self::validateWindow($cep_rules);
		self::validateOperations($cep_rules);
	}

	private static function getValidationRules(bool $is_update = false): array {
		$api_required = $is_update ? 0x00 : API_REQUIRED | API_NOT_EMPTY;

		$fields = $is_update
			? ['cep_ruleid' => 	['type' => API_ANY]]
			: [];
		$fields += [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'name')],
			'filter' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
			'window_type' =>	['type' => API_INT32, 'in' => implode(',', [
									ZBX_CEP_WINDOW_NONE,
									ZBX_CEP_WINDOW_SIMPLE,
									ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
									ZBX_CEP_WINDOW_TAG_MATCH,
									ZBX_CEP_WINDOW_PATTERN_MATCH
								])] + ($is_update ? [] : ['default' => ZBX_CEP_WINDOW_NONE]),
			'window' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										ZBX_CEP_WINDOW_SIMPLE,
										ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
										ZBX_CEP_WINDOW_TAG_MATCH,
										ZBX_CEP_WINDOW_PATTERN_MATCH
									])], 'type' => API_OBJECT, 'flags' => $api_required | API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => []],
									['else' => true, 'type' => API_OBJECT, 'fields' => [], 'unset' => true]
			]],
			'operations' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										ZBX_CEP_WINDOW_NONE,
										ZBX_CEP_WINDOW_SIMPLE,
										ZBX_CEP_WINDOW_TAG_MATCH,
										ZBX_CEP_WINDOW_PATTERN_MATCH
									])], 'type' => API_OBJECTS, 'flags' => $api_required | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []],
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										ZBX_CEP_WINDOW_CAUSE_SYMPTOM
									])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []]
			]],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP])],
			'sortorder' =>		['type' => API_INT32, 'in' => ZBX_MIN_INT32.':'.ZBX_MAX_INT32],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED])]
		];

		return ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => $fields];
	}

	private static function validateFilter(array &$cep_rules): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>		['type' => API_INT32, 'in' => implode(',', [
									CONDITION_EVAL_TYPE_AND_OR,
									CONDITION_EVAL_TYPE_AND,
									CONDITION_EVAL_TYPE_OR,
									CONDITION_EVAL_TYPE_EXPRESSION
								]), 'flags' => API_REQUIRED],
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
									] + self::getConditionValidationFields()],
									['else' => true, 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => self::getConditionValidationFields()]
			]]
		]];

		foreach ($cep_rules as $i => &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule)) {
				continue;
			}

			if ($cep_rule['filter']) {
				if (!CApiInputValidator::validate($api_input_rules, $cep_rule['filter'], '/'.($i + 1).'/filter', $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				self::checkFilterFormula($cep_rule, '/'.($i + 1));
			}
		}
		unset($cep_rule);
	}

	private static function getConditionValidationFields(): array {
		return [
			'type' =>			['type' => API_INT32, 'in' => implode(',', [
									ZBX_CEP_CONDITION_EVENT_NAME,
									ZBX_CEP_CONDITION_TAG_NAME,
									ZBX_CEP_CONDITION_TAG_VALUE,
									ZBX_CEP_CONDITION_SEVERITY,
									ZBX_CEP_CONDITION_HOST,
									ZBX_CEP_CONDITION_HOST_GROUP,
									ZBX_CEP_CONDITION_TIME_PERIOD
								]), 'flags' => API_REQUIRED],
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
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_EVENT_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'event_name')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'event_name')]
			]],
			'tag' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TAG_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'tag')],
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
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_HOST])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'host')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host')]
			]],
			'host_group' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_HOST_GROUP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'host_group')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host_group')]
			]],
			'time_period' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_CONDITION_TIME_PERIOD])], 'type' => API_TIME_PERIOD, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'time_period')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'time_period')]
			]]
		];
	}

	private static function validateWindow(array &$cep_rules, ?array $db_cep_rules = null): void {
		$is_update = $db_cep_rules !== null;
		$api_required = $is_update ? 0x00 : API_REQUIRED;

		foreach ($cep_rules as $i => &$cep_rule) {
			if ($cep_rule['window_type'] == ZBX_CEP_WINDOW_NONE) {
				continue;
			}

			if ($is_update && !array_key_exists('window', $cep_rule)
					&& $cep_rule['window_type'] == $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				continue;
			}

			$path = '/'.($i + 1).'/window';

			$grouping_allowed = in_array($cep_rule['window_type'], [
				ZBX_CEP_WINDOW_SIMPLE,
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
				ZBX_CEP_WINDOW_PATTERN_MATCH
			]);

			if ($grouping_allowed && array_key_exists('window', $cep_rule)) {
				$cep_rule['window'] += $is_update
						&& $cep_rule['window_type'] == $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']
					? array_intersect_key($db_cep_rules[$cep_rule['cep_ruleid']]['window'],
						array_flip(['group_by_host_group', 'group_by_host', 'group_by_tag', 'tag'])
					)
					: ['group_by_tag' => DB::getDefault('cep_window', 'group_by_tag')];
			}

			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'duration' =>				['type' => API_TIME_UNIT, 'flags' => $api_required, 'in' => '1:'.SEC_PER_YEAR],
				'capacity' =>				['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT64],
				'filter' =>					$cep_rule['window_type'] == ZBX_CEP_WINDOW_TAG_MATCH
												? ['type' => API_OBJECT, 'fields' => [
													'evaltype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [
																		CONDITION_EVAL_TYPE_AND_OR,
																		CONDITION_EVAL_TYPE_AND,
																		CONDITION_EVAL_TYPE_OR,
																		CONDITION_EVAL_TYPE_EXPRESSION
																	])],
													'formula' =>	['type' => API_MULTIPLE, 'rules' => [
																		['if' => ['field' => 'evaltype', 'in' =>implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
																		['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'formula')]
													]],
													'conditions' =>	['type' => API_MULTIPLE, 'rules' => [
																		['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
																			'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED | API_NOT_EMPTY]
																		] + self::getWindowConditionValidationFields()],
																		['else' => true, 'type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => self::getWindowConditionValidationFields()]
													]]
												]]
												: ['type' => API_OBJECT, 'fields' => [], 'unset' => true],
				'event_count_tag' =>		$cep_rule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM
												? ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window', 'event_count_tag')]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'event_count_tag')],
				'script' =>					$cep_rule['window_type'] == ZBX_CEP_WINDOW_PATTERN_MATCH
												? ['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'script')]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'script')],
				'group_by_host_group' =>	$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_GROUP_BY_NO, ZBX_CEP_GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_host_group')],
				'group_by_host' =>			$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_GROUP_BY_NO, ZBX_CEP_GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_host')],
				'group_by_tag' =>			$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_GROUP_BY_NO, ZBX_CEP_GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_tag')],
				'tag' =>					$grouping_allowed
												? ['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'group_by_tag', 'in' => (string) ZBX_CEP_GROUP_BY_YES], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'tag')],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'tag')]
												]]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'tag')]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $cep_rule['window'], $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if (array_key_exists('filter', $cep_rule['window'])) {
				self::checkFilterFormula($cep_rule['window'], $path);
			}

			if ($cep_rule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM) {
				$grouping_fields = array_flip(['group_by_host_group', 'group_by_host', 'group_by_tag']);

				if (!array_filter(array_intersect_key($cep_rule['window'], $grouping_fields))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path, _('at least one of "group_by_host_group", "group_by_host" or "group_by_tag" parameters must be enabled')
					));
				}
			}
		}
		unset($cep_rule);
	}

	private static function getWindowConditionValidationFields(): array {
		return [
			'type' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [
								ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
							])],
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
			'tag' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_window_condition', 'tag')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window_condition', 'tag')]
			]],
			'tag_value' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_window_condition', 'tag_value')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window_condition', 'tag_value')]
			]]
		];
	}

	protected static function checkFilterFormula(array $object, string $path): void {
		if ($object['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return;
		}

		$path .= '/filter';
		$condition_formula_parser = new CConditionFormula();
		$condition_formula_parser->parse($object['filter']['formula']);
		$constants = array_unique(array_column($condition_formula_parser->constants, 'value'));
		$condition_formulaids = array_column($object['filter']['conditions'], 'formulaid');

		foreach ($constants as $constant) {
			if (!in_array($constant, $condition_formulaids)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.', $path.'/formula',
					_s('missing filter condition "%1$s"', $constant)
				));
			}
		}

		foreach ($object['filter']['conditions'] as $j => $condition) {
			if (!in_array($condition['formulaid'], $constants)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					$path.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
				));
			}
		}
	}

	private static function validateOperations(array &$cep_rules): void {
		foreach ($cep_rules as $i => &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			$path = '/'.($i + 1).'/operations';

			foreach ($cep_rule['operations'] as $j => &$operation) {
				$operation_path = $path.'/'.($j + 1);

				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'execute_when' =>	['type' => API_INT32, 'in' => implode(',', self::EXECUTE_WHEN_BY_WINDOW_TYPE[$cep_rule['window_type']]), 'flags' => API_REQUIRED],
					'eviction_cause' =>	$cep_rule['window_type'] != ZBX_CEP_WINDOW_NONE
											? ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'execute_when', 'in' => implode(',', [ZBX_CEP_OP_WHEN_EVENT_EVICTED])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [
													ZBX_CEP_EVICTION_CAUSE_ANY,
													ZBX_CEP_EVICTION_CAUSE_DURATION,
													ZBX_CEP_EVICTION_CAUSE_CAPACITY
												])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'eviction_cause')]
											]]
											: ['type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'eviction_cause')],
				]];

				if (!CApiInputValidator::validate($api_input_rules, $operation, $operation_path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$types = $operation['execute_when'] == ZBX_CEP_OP_WHEN_EVENT_EVICTED
					? self::OPERATION_TYPES_BY_EXECUTE_WHEN[$operation['execute_when']][$operation['eviction_cause']]
					: self::OPERATION_TYPES_BY_EXECUTE_WHEN[$operation['execute_when']];

				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'step' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => ZBX_MIN_INT32.':'.ZBX_MAX_INT32],
					'execute_when' =>	['type' => API_ANY],
					'eviction_cause' =>	['type' => API_ANY],
					'event_type' =>		$cep_rule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM
											? ['type' => API_MULTIPLE, 'rules' => [
												['if' => ['field' => 'execute_when', 'in' => implode(',', [ZBX_CEP_OP_WHEN_EVENT_OCCURRED])], 'type' => API_INT32, 'in' => implode(',', [
													ZBX_CEP_EXECUTE_EVENT_TYPE_ANY,
													ZBX_CEP_EXECUTE_EVENT_TYPE_CAUSE,
													ZBX_CEP_EXECUTE_EVENT_TYPE_SYMPTOM
												])],
												['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'event_type')]
											]]
											: ['type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'event_type')],
					'evaltype' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND])],
					'tags' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['tag', 'value']], 'fields' => [
						'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_tag', 'tag')],
						'operator' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
						'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation_tag', 'value'), 'default' => '']
					]],
					'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', $types)],
					'event_name' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CEP_OP_SET_NAME
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'event_name')],
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
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'tag')],
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
												ZBX_CEP_OP_SET_TAG_VALUE,
												ZBX_CEP_OP_INCREASE_TAG_VALUE,
												ZBX_CEP_OP_DECREASE_TAG_VALUE,
												ZBX_CEP_OP_RENAME_TAG,
												ZBX_CEP_OP_REMOVE_TAG
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
			unset($operation);
		}
		unset($cep_rule);
	}

	private static function checkDuplicates(array $cep_rules, ?array $db_cep_rules = null): void {
		$names = [];

		foreach ($cep_rules as $cep_rule) {
			if ($db_cep_rules === null || $cep_rule['name'] !== $db_cep_rules[$cep_rule['cep_ruleid']]['name']) {
				$names[] = $cep_rule['name'];
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

	private static function updateFilter(array &$cep_rules, ?array $db_cep_rules = null): void {
		self::updateFilterConditions($cep_rules, $db_cep_rules);

		$db_defaults = DB::getDefaults('cep_rule');
		$upd_rules = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule)) {
				continue;
			}

			$db_cep_rule = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']] : [];

			if (!$db_cep_rule) {
				continue;
			}

			$upd_rule = [];

			if ($cep_rule['filter']) {
				if ($cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					CConditionHelper::replaceFormulaIds($cep_rule['filter']['formula'],
						array_column($cep_rule['filter']['conditions'], null, 'cep_conditionid')
					);

					$upd_rule['formula'] = $cep_rule['filter']['formula'];
				}
				elseif ($db_cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$upd_rule['formula'] = $db_defaults['formula'];
				}

				if ($db_cep_rule['evaltype'] != $cep_rule['filter']['evaltype']) {
					$upd_rule['evaltype'] = $cep_rule['filter']['evaltype'];
				}
			}
			else {
				if ($db_cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$upd_rule['formula'] = $db_defaults['formula'];
				}

				if ($db_cep_rule['evaltype'] != $db_defaults['evaltype']) {
					$upd_rule['evaltype'] = $db_defaults['evaltype'];
				}
			}

			if ($upd_rule) {
				$cep_rule += ['filter' => []];
				$cep_rule['filter'] = $upd_rule + $cep_rule['filter'];

				$upd_rules[] = [
					'values' => $upd_rule,
					'where' => ['cep_ruleid' => $cep_rule['cep_ruleid']]
				];
			}
		}
		unset($cep_rule);

		if ($upd_rules) {
			DB::update('cep_rule', $upd_rules);
		}
	}

	private static function updateFilterConditions(array &$cep_rules, ?array $db_cep_rules = null): void {
		$ins_conditions = [];
		$upd_conditions = [];
		$del_conditionids = [];
		$db_defaults = DB::getDefaults('cep_condition');

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule)) {
				continue;
			}

			$db_conditions = $db_cep_rules !== null && array_key_exists('filter', $db_cep_rules[$cep_rule['cep_ruleid']])
				? $db_cep_rules[$cep_rule['cep_ruleid']]['filter']['conditions']
				: [];

			if (array_key_exists('conditions', $cep_rule['filter'])) {
				foreach ($cep_rule['filter']['conditions'] as &$condition) {
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
						$ins_conditions[] = ['cep_ruleid' => $cep_rule['cep_ruleid']] + $condition;
					}
				}
				unset($condition);
			}

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($cep_rule);

		if ($del_conditionids) {
			DB::delete('cep_condition', ['cep_conditionid' => $del_conditionids]);
		}

		if ($upd_conditions) {
			DB::update('cep_condition', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_condition', $ins_conditions);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule) || !array_key_exists('conditions', $cep_rule['filter'])) {
				continue;
			}

			foreach ($cep_rule['filter']['conditions'] as &$condition) {
				if (!array_key_exists('cep_conditionid', $condition)) {
					$condition['cep_conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($cep_rule);
	}

	private static function updateWindow(array &$cep_rules, ?array $db_cep_rules = null): void {
		$del_windowids = [];
		$upd_windows = [];
		$ins_windows = [];

		if ($db_cep_rules !== null) {
			self::addWindowFieldDefaultsByType($cep_rules, $db_cep_rules);
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_ruleid = $cep_rule['cep_ruleid'];

			if ($cep_rule['window_type'] == ZBX_CEP_WINDOW_NONE) {
				if ($db_cep_rules !== null && $db_cep_rules[$cep_ruleid]['window_type'] != ZBX_CEP_WINDOW_NONE) {
					$del_windowids[] = $db_cep_rules[$cep_ruleid]['window']['cep_windowid'];
				}
			}
			elseif (array_key_exists('window', $cep_rule)) {
				if ($db_cep_rules === null || $db_cep_rules[$cep_ruleid]['window_type'] == ZBX_CEP_WINDOW_NONE) {
					$ins_windows[] = ['cep_ruleid' => $cep_ruleid] + $cep_rule['window'];
				}
				else {
					$db_window = $db_cep_rules[$cep_ruleid]['window'];
					$cep_rule['window']['cep_windowid'] = $db_window['cep_windowid'];
					$upd_window = DB::getUpdatedValues('cep_window', $cep_rule['window'], $db_window);

					if ($upd_window) {
						$upd_windows[] = [
							'values' => $upd_window,
							'where' => ['cep_windowid' => $db_window['cep_windowid']]
						];
					}
				}
			}
		}
		unset($cep_rule);

		if ($del_windowids) {
			DB::delete('cep_window_condition', ['cep_windowid' => $del_windowids]);
			DB::delete('cep_window', ['cep_windowid' => $del_windowids]);
		}

		if ($upd_windows) {
			DB::update('cep_window', $upd_windows);
		}

		if ($ins_windows) {
			$windowids = DB::insert('cep_window', $ins_windows);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (array_key_exists('window', $cep_rule) && !array_key_exists('cep_windowid', $cep_rule['window'])) {
				$cep_rule['window']['cep_windowid'] = array_shift($windowids);
			}
		}
		unset($cep_rule);

		self::updateWindowFilter($cep_rules, $db_cep_rules);
	}

	private static function addWindowFieldDefaultsByType(array &$cep_rules, array $db_cep_rules): void {
		$db_defaults = DB::getDefaults('cep_window');

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			$db_cep_rule = $db_cep_rules[$cep_rule['cep_ruleid']];

			if ($cep_rule['window_type'] == $db_cep_rule['window_type']) {
				continue;
			}

			$allowed_fields = ['duration', 'capacity'];

			if (in_array($cep_rule['window_type'], [
				ZBX_CEP_WINDOW_SIMPLE,
				ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
				ZBX_CEP_WINDOW_PATTERN_MATCH
			])) {
				$allowed_fields[] = 'group_by_host_group';
				$allowed_fields[] = 'group_by_host';
				$allowed_fields[] = 'group_by_tag';
				$allowed_fields[] = 'tag';
			}

			if ($cep_rule['window_type'] == ZBX_CEP_WINDOW_CAUSE_SYMPTOM) {
				$allowed_fields[] = 'event_count_tag';
			}
			elseif ($cep_rule['window_type'] == ZBX_CEP_WINDOW_TAG_MATCH) {
				$allowed_fields[] = 'filter';
			}
			elseif ($cep_rule['window_type'] == ZBX_CEP_WINDOW_PATTERN_MATCH) {
				$allowed_fields[] = 'script';
			}

			$cep_rule['window'] += array_diff_key($db_defaults, array_flip($allowed_fields));
		}
		unset($cep_rule);
	}

	private static function updateWindowFilter(array &$cep_rules, ?array $db_cep_rules = null): void {
		self::updateWindowFilterConditions($cep_rules, $db_cep_rules);

		$db_defaults = DB::getDefaults('cep_window');
		$upd_windows = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			$db_window = $db_cep_rules !== null && array_key_exists('window', $db_cep_rules[$cep_rule['cep_ruleid']])
				? $db_cep_rules[$cep_rule['cep_ruleid']]['window']
				: [];

			if (!$db_window) {
				continue;
			}

			$upd_window = [];

			if (array_key_exists('filter', $cep_rule['window'])) {
				if ($cep_rule['window']['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					CConditionHelper::replaceFormulaIds($cep_rule['window']['filter']['formula'],
						array_column($cep_rule['window']['filter']['conditions'], null, 'cep_conditionid')
					);

					$upd_window['formula'] = $cep_rule['window']['filter']['formula'];
				}
				elseif ($db_window['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$upd_window['formula'] = $db_defaults['formula'];
				}

				if ($db_window['evaltype'] != $cep_rule['window']['filter']['evaltype']) {
					$upd_window['evaltype'] = $cep_rule['window']['filter']['evaltype'];
				}
			}
			else {
				if ($db_window['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$upd_window['formula'] = $db_defaults['formula'];
				}

				if ($db_window['evaltype'] != $db_defaults['evaltype']) {
					$upd_window['evaltype'] = $db_defaults['evaltype'];
				}
			}

			if ($upd_window) {
				$cep_rule['window'] += ['filter' => []];
				$cep_rule['window']['filter'] = $upd_window + $cep_rule['window']['filter'];

				$upd_windows[] = [
					'values' => $upd_window,
					'where' => ['cep_windowid' => $cep_rule['window']['cep_windowid']]
				];
			}
		}
		unset($cep_rule);

		if ($upd_windows) {
			DB::update('cep_window', $upd_windows);
		}
	}

	private static function updateWindowFilterConditions(array &$cep_rules, ?array $db_cep_rules = null): void {
		$db_defaults = DB::getDefaults('cep_window_condition');
		$del_conditionids = [];
		$upd_conditions = [];
		$ins_conditions = [];

		foreach ($cep_rules as &$cep_rule) {
			if ($db_cep_rules !== null) {
				if ($cep_rule['window_type'] == ZBX_CEP_WINDOW_NONE
						&& $db_cep_rules[$cep_rule['cep_ruleid']]['window_type'] == $cep_rule['window_type']) {
					continue;
				}
			}
			elseif (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			$db_conditions = $db_cep_rules !== null && array_key_exists('window', $db_cep_rules[$cep_rule['cep_ruleid']])
					&& array_key_exists('filter', $db_cep_rules[$cep_rule['cep_ruleid']]['window'])
					&& array_key_exists('conditions', $db_cep_rules[$cep_rule['cep_ruleid']]['window']['filter'])
				? $db_cep_rules[$cep_rule['cep_ruleid']]['window']['filter']['conditions']
				: [];

			if (array_key_exists('window', $cep_rule) && array_key_exists('filter', $cep_rule['window'])
					&& array_key_exists('conditions', $cep_rule['window']['filter'])) {
				foreach ($cep_rule['window']['filter']['conditions'] as &$condition) {
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
						$ins_conditions[] = ['cep_windowid' => $cep_rule['window']['cep_windowid']] + $condition;
					}
				}
				unset($condition);
			}

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($cep_rule);

		if ($del_conditionids) {
			DB::delete('cep_window_condition', ['cep_window_conditionid' => $del_conditionids]);
		}

		if ($upd_conditions) {
			DB::update('cep_window_condition', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_window_condition', $ins_conditions);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('window', $cep_rule) || !array_key_exists('filter', $cep_rule['window'])
					|| !array_key_exists('conditions', $cep_rule['window']['filter'])) {
				continue;
			}

			foreach ($cep_rule['window']['filter']['conditions'] as &$condition) {
				if (!array_key_exists('cep_window_conditionid', $condition)) {
					$condition['cep_window_conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($cep_rule);
	}

	private static function updateOperations(array &$cep_rules, ?array $db_cep_rules = null): void {
		$db_defaults = DB::getDefaults('cep_operation');
		$del_operationids = [];
		$upd_operations = [];
		$ins_operations = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			$db_operations = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']]['operations'] : [];

			foreach ($cep_rule['operations'] as &$operation) {
				$operation += $db_defaults;

				$db_operation = current(
					array_filter($db_operations, static function (array $db_operation) use ($operation): bool {
						return array_diff_key($operation, array_flip(['step'])) ==
							array_diff_key($db_operation, array_flip(['cep_operationid', 'step']));
					})
				);

				if ($db_operation) {
					$operation['cep_operationid'] = $db_operation['cep_operationid'];
					unset($db_operations[$db_operation['cep_operationid']]);

					$upd_operation = DB::getUpdatedValues('cep_operation', $operation, $db_operation);

					if ($upd_operation) {
						$upd_operations[] = [
							'values' => $upd_operation,
							'where' => ['cep_operationid' => $db_operation['cep_operationid']]
						];
					}
				}
				else {
					$ins_operations[] = ['cep_ruleid' => $cep_rule['cep_ruleid']] + $operation;
				}
			}
			unset($operation);

			$del_operationids = array_merge($del_operationids, array_keys($db_operations));
		}
		unset($cep_rule);

		if ($del_operationids) {
			DB::delete('cep_operation_tag', ['cep_operationid' => $del_operationids]);
			DB::delete('cep_operation', ['cep_operationid' => $del_operationids]);
		}

		if ($upd_operations) {
			DB::update('cep_operation', $upd_operations);
		}

		if ($ins_operations) {
			$operationids = DB::insert('cep_operation', $ins_operations);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			foreach ($cep_rule['operations'] as &$operation) {
				if (!array_key_exists('cep_operationid', $operation)) {
					$operation['cep_operationid'] = array_shift($operationids);
				}
			}
			unset($operation);
		}
		unset($cep_rule);

		self::updateOperationTags($cep_rules, $db_cep_rules);
	}

	private static function updateOperationTags(array &$cep_rules, ?array $db_cep_rules = null): void {
		$db_defaults = DB::getDefaults('cep_operation_tag');
		$del_tagids = [];
		$upd_tags = [];
		$ins_tags = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			$db_operations = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']]['operations'] : [];

			foreach ($cep_rule['operations'] as &$operation) {
				if (!array_key_exists('tags', $operation)) {
					continue;
				}

				$db_tags = $db_operations && array_key_exists($operation['cep_operationid'], $db_operations)
						&& array_key_exists('tags', $db_operations[$operation['cep_operationid']])
					? $db_operations[$operation['cep_operationid']]['tags']
					: [];

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
		unset($cep_rule);

		if ($del_tagids) {
			DB::delete('cep_operation_tag', ['cep_operation_tagid' => $del_tagids]);
		}

		if ($upd_tags) {
			DB::update('cep_operation_tag', $upd_tags);
		}

		if ($ins_tags) {
			$tagids = DB::insert('cep_operation_tag', $ins_tags);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (array_key_exists('operations', $cep_rule)) {
				foreach ($cep_rule['operations'] as &$operation) {
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
		unset($cep_rule);
	}

	public function update(array $cep_rules): array {
		$this->validateUpdate($cep_rules, $db_cep_rules);

		self::updateForce($cep_rules, $db_cep_rules);

		return ['cep_ruleids' => array_column($cep_rules, 'cep_ruleid')];
	}

	private function validateUpdate(array &$cep_rules, ?array &$db_cep_rules = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'uniq' => [['cep_ruleid']], 'fields' => [
			'cep_ruleid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_cep_rules = DB::select('cep_rule', [
			'output' => ['cep_ruleid', 'name', 'evaltype', 'formula', 'window_type', 'stop', 'sortorder', 'description',
				'status'
			],
			'cep_ruleids' => array_column($cep_rules, 'cep_ruleid'),
			'preservekeys' => true
		]);

		if (count($cep_rules) != count($db_cep_rules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$cep_rules = $this->extendObjectsByKey($cep_rules, $db_cep_rules, 'cep_ruleid', ['name', 'window_type']);

		if (!CApiInputValidator::validate(self::getValidationRules(true), $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($cep_rules, $db_cep_rules);

		self::addAffectedObjects($cep_rules, $db_cep_rules);

		self::validateFilter($cep_rules);
		self::validateWindow($cep_rules, $db_cep_rules);
		self::validateOperations($cep_rules);
	}

	private static function addAffectedObjects(array $cep_rules, array &$db_cep_rules): void {
		self::addAffectedFilter($cep_rules, $db_cep_rules);
		self::addAffectedWindow($cep_rules, $db_cep_rules);
		self::addAffectedOperations($cep_rules, $db_cep_rules);
	}

	private static function addAffectedFilter(array $cep_rules, array &$db_cep_rules): void {
		$cep_ruleids = [];

		foreach ($cep_rules as $cep_rule) {
			if (array_key_exists('filter', $cep_rule)) {
				$cep_ruleid = $cep_rule['cep_ruleid'];

				if ($db_cep_rules[$cep_ruleid]['window_type'] != ZBX_CEP_WINDOW_NONE) {
					$db_cep_rules[$cep_ruleid]['filter'] =
						array_intersect_key($db_cep_rules[$cep_ruleid], array_flip(['evaltype', 'formula']));

					$cep_ruleids[] = $cep_ruleid;
				}
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => ['cep_conditionid', 'cep_ruleid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
				'severity', 'host', 'host_group', 'time_period'
			],
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'sortfield' => ['cep_conditionid']
		];
		$resource = DBselect(DB::makeSql('cep_condition', $options));

		while ($row = DBfetch($resource)) {
			$db_cep_rules[$row['cep_ruleid']]['filter']['conditions'][$row['cep_conditionid']] =
				array_diff_key($row, array_flip(['cep_ruleid']));
		}

		foreach ($cep_ruleids as $cep_ruleid => $foo) {
			self::addFilterConditionFormulaids($db_cep_rules[$cep_ruleid]['filter']);
		}
	}

	private static function addFilterConditionFormulaids(array &$filter): void {
		if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			CConditionHelper::addFormulaIds($filter['conditions'], $filter['formula']);
		}
		else {
			foreach ($filter['conditions'] as &$condition) {
				$condition['formulaid'] = '';
			}
			unset($condition);
		}
	}

	private static function addAffectedWindow(array $cep_rules, array &$db_cep_rules): void {
		$cep_ruleids = [];

		foreach ($cep_rules as $cep_rule) {
			if (array_key_exists('window', $cep_rule) || (array_key_exists('window_type', $cep_rule)
					&& $cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type'])) {
				$cep_ruleid = $cep_rule['cep_ruleid'];

				if ($db_cep_rules[$cep_ruleid]['window_type'] != ZBX_CEP_WINDOW_NONE) {
					$cep_ruleids[] = $cep_ruleid;
				}
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => ['cep_windowid', 'cep_ruleid', 'duration', 'capacity', 'evaltype' ,'formula', 'script',
				'group_by_host_group', 'group_by_host', 'group_by_tag', 'tag', 'event_count_tag'
			],
			'filter' => ['cep_ruleid' => $cep_ruleids]
		];
		$resource = DBselect(DB::makeSql('cep_window', $options));
		$window_ruleids = [];

		while ($row = DBfetch($resource)) {
			$cep_ruleid = $row['cep_ruleid'];
			$db_cep_rules[$cep_ruleid]['window'] = array_diff_key($row, array_flip(['cep_ruleid']));

			if ($db_cep_rules[$cep_ruleid]['window_type'] == ZBX_CEP_WINDOW_TAG_MATCH) {
				$db_cep_rules[$cep_ruleid]['window']['filter'] =
					array_intersect_key($row, array_flip(['evaltype', 'formula']));

				$window_ruleids[$row['cep_windowid']] = $cep_ruleid;
			}
		}

		if (!$window_ruleids) {
			return;
		}

		$options = [
			'output' => ['cep_window_conditionid', 'cep_windowid', 'type', 'operator', 'past_tag', 'tag_value', 'tag'],
			'filter' => ['cep_windowid' => array_keys($window_ruleids)],
			'sortfield' => ['cep_window_conditionid']
		];
		$resource = DBselect(DB::makeSql('cep_window_condition', $options));

		while ($row = DBfetch($resource)) {
			$cep_ruleid = $window_ruleids[$row['cep_windowid']];

			$db_cep_rules[$cep_ruleid]['window']['filter']['conditions'][$row['cep_window_conditionid']] =
				array_diff_key($row, array_flip(['cep_windowid']));
		}

		foreach ($window_ruleids as $cep_ruleid) {
			self::addFilterConditionFormulaids($db_cep_rules[$cep_ruleid]['window']['filter']);
		}
	}

	private static function addAffectedOperations(array $cep_rules, array &$db_cep_rules): void {
		$cep_ruleids = [];

		foreach ($cep_rules as $cep_rule) {
			$cep_ruleid = $cep_rule['cep_ruleid'];

			if (array_key_exists('operations', $cep_rule)) {
				$cep_ruleids[] = $cep_ruleid;

				$db_cep_rules[$cep_ruleid]['operations'] = [];
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => ['cep_operationid', 'cep_ruleid', 'step', 'execute_when', 'event_type', 'eviction_cause', 'type',
				'evaltype', 'event_name', 'tag', 'new_tag', 'tag_value', 'severity'
			],
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'sortfield' => ['step']
		];
		$resource = DBSelect(DB::makeSql('cep_operation', $options));
		$operation_rules = [];

		while ($row = DBfetch($resource)) {
			$db_cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] =
				array_diff_key($row, array_flip(['cep_ruleid']));

			$operation_rules[$row['cep_operationid']] = $row['cep_ruleid'];
		}

		if (!$operation_rules) {
			return;
		}

		$options = [
			'output' => ['cep_operation_tagid', 'cep_operationid', 'tag', 'operator', 'value'],
			'filter' => ['cep_operationid' => array_keys($operation_rules)],
			'sortfield' => ['cep_operation_tagid']
		];
		$resource = DBselect(DB::makeSql('cep_operation_tag', $options));

		while ($row = DBfetch($resource)) {
			$cep_ruleid = $operation_rules[$row['cep_operationid']];

			$db_cep_rules[$cep_ruleid]['operations'][$row['cep_operationid']]['tags'][$row['cep_operation_tagid']] =
				array_diff($row, array_flip(['cep_operationid']));
		}
	}

	private static function updateForce(array $cep_rules, ?array &$db_cep_rules = null): void {
		$upd_rules = [];

		foreach ($cep_rules as $cep_rule) {
			$db_cep_rule = $db_cep_rules[$cep_rule['cep_ruleid']];
			$upd_rule = DB::getUpdatedValues('cep_rule', $cep_rule, $db_cep_rule);

			if ($upd_rule) {
				$upd_rules[] = [
					'values' => $upd_rule,
					'where' => ['cep_ruleid' => $cep_rule['cep_ruleid']]
				];
			}
		}

		if ($upd_rules) {
			DB::update('cep_rule', $upd_rules);
		}

		self::updateFilter($cep_rules, $db_cep_rules);
		self::updateWindow($cep_rules, $db_cep_rules);
		self::updateOperations($cep_rules, $db_cep_rules);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CEP_RULE, $cep_rules, $db_cep_rules);
	}

	public function delete(array $cep_ruleids): array {
		$this->validateDelete($cep_ruleids, $db_cep_rules);

		DB::delete('cep_condition', ['cep_ruleid' => $cep_ruleids]);
		self::deleteAffectedWindows($cep_ruleids);
		self::deleteAffectedOperations($cep_ruleids);
		DB::delete('cep_rule', ['cep_ruleid' => $cep_ruleids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_CEP_RULE, $db_cep_rules);

		return ['cep_ruleids' => $cep_ruleids];
	}

	private function validateDelete(array &$cep_ruleids, ?array &$db_cep_rules): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $cep_ruleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_cep_rules = $this->get([
			'output' => ['cep_ruleid', 'name'],
			'cep_ruleids' => $cep_ruleids
		]);

		if (count($db_cep_rules) != count($cep_ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	private static function deleteAffectedWindows(array $cep_ruleids): void {
		$windowids = array_keys(DB::select('cep_window', [
			'output' => [],
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'preservekeys' => true
		]));

		if ($windowids) {
			DB::delete('cep_window_condition', ['cep_windowid' => $windowids]);
			DB::delete('cep_window', ['cep_windowid' => $windowids]);
		}
	}

	private static function deleteAffectedOperations(array $cep_ruleids): void {
		$operationids = array_keys(DB::select('cep_operation', [
			'output' => [],
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'preservekeys' => true
		]));

		if ($operationids) {
			DB::delete('cep_operation_tag', ['cep_operationid' => $operationids]);
			DB::delete('cep_operation', ['cep_operationid' => $operationids]);
		}
	}
}
