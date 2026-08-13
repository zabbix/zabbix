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
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'cep_rule';
	protected $tableAlias = 'cr';
	protected $sortColumns = ['cep_ruleid', 'name', 'stop', 'sortorder', 'status'];

	public const OUTPUT_FIELDS = ['cep_ruleid', 'name', 'window_type', 'stop', 'sortorder', 'description', 'status',
		'error'
	];

	public const FILTER_OUTPUT_FIELDS = ['evaltype', 'eval_formula', 'formula', 'conditions'];

	public const WINDOW_OUTPUT_FIELDS = ['duration', 'capacity', 'group_by_host_group', 'group_by_host',
		'group_by_tags', 'tags', 'event_count_tag', 'script'
	];

	public const OPERATIONS_OUTPUT_FIELDS = ['sortorder', 'execute_when', 'filter', 'type', 'event_name',
		'severity', 'suppress_duration', 'tag', 'new_tag', 'tag_value'
	];

	public function get(array $options = []): array|string {
		$this->validateGet($options);

		$resource = DBselect($this->createSelectQuery('cep_rule', $options), $options['limit']);

		if ($options['countOutput']) {
			return DBfetch($resource)['rowscount'];
		}

		$cep_rules = [];
		while ($row = DBfetch($resource)) {
			$cep_rules[$row['cep_ruleid']] = $row;
		}

		if ($cep_rules) {
			$cep_rules = $this->addRelatedObjects($options, $cep_rules);
			$cep_rules = $this->unsetExtraFields($cep_rules, ['cep_ruleid', 'formula', 'evaltype'], $options['output']);
		}

		return $options['preservekeys'] ? $cep_rules : array_values($cep_rules);
	}

	private function validateGet(array &$options): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// Filter.
			'cep_ruleids' =>			['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getFilterFields('cep_rule', self::OUTPUT_FIELDS), ['window_type'])],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => array_merge(DB::getSearchFields('cep_rule', self::OUTPUT_FIELDS), DB::getSearchFields('cep_rule_rtdata', self::OUTPUT_FIELDS))],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'excludeSearch' =>			['type' => API_BOOLEAN, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// Output.
			'output' =>					['type' => API_OUTPUT, 'flags' => API_NORMALIZE, 'in' => implode(',', self::OUTPUT_FIELDS), 'default' => API_OUTPUT_EXTEND],
			'countOutput' =>			['type' => API_BOOLEAN, 'default' => false],
			'selectFilter' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::FILTER_OUTPUT_FIELDS), 'default' => null],
			'selectWindow' =>			['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::WINDOW_OUTPUT_FIELDS), 'default' => null],
			'selectOperations' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'in' => implode(',', self::OPERATIONS_OUTPUT_FIELDS), 'default' => null],
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
			if (in_array('window_type', $options['output'])) {
				$sql_parts['join']['cw'] = ['type' => 'left', 'table' => 'cep_window', 'using' => 'cep_ruleid'];
				$sql_parts['select']['window_type'] =
					dbConditionCoalesce('cw.type', CCepRuleHelper::WINDOW_NONE, 'window_type');
			}

			if (in_array('error', $options['output'])) {
				$sql_parts['join']['crr'] = ['type' => 'left', 'table' => 'cep_rule_rtdata', 'using' => 'cep_ruleid'];
				$sql_parts = $this->addQuerySelect(dbConditionCoalesce('crr.error', '', 'error'), $sql_parts);
			}

			if ($options['selectFilter'] !== null) {
				if (in_array('formula', $options['selectFilter']) || in_array('eval_formula', $options['selectFilter'])
						|| in_array('conditions', $options['selectFilter'])) {
					$sql_parts = $this->addQuerySelect('cr.formula', $sql_parts);
					$sql_parts = $this->addQuerySelect('cr.evaltype', $sql_parts);
				}

				if (in_array('evaltype', $options['selectFilter'])) {
					$sql_parts = $this->addQuerySelect('cr.evaltype', $sql_parts);
				}
			}
		}

		return $sql_parts;
	}

	protected function applyQueryFilterOptions($table_name, $table_alias, array $options, array $sql_parts) {
		$sql_parts = parent::applyQueryFilterOptions($table_name, $table_alias, $options, $sql_parts);

		if ($options['filter'] !== null) {
			if (array_key_exists('window_type', $options['filter']) && $options['filter']['window_type'] !== null) {
				$window_type_values = (array) $options['filter']['window_type'];

				if ($window_type_values) {
					$sql_parts['join']['cw'] = ['type' => 'left', 'table' => 'cep_window', 'using' => 'cep_ruleid'];
					$sql_parts['where']['window_type'] = in_array(CCepRuleHelper::WINDOW_NONE, $window_type_values)
						? dbConditionInt('cw.type', $window_type_values).' OR cw.cep_ruleid IS NULL'
						: dbConditionInt('cw.type', $window_type_values);
				}
			}
		}

		if ($options['search'] !== null) {
			if (array_key_exists('error', $options['search']) && $options['search']['error'] !== null) {
				$sql_parts['join']['crr'] = ['type' => 'left', 'table' => 'cep_rule_rtdata', 'using' => 'cep_ruleid'];
				zbx_db_search('cep_rule_rtdata crr', ['search' => ['error' => $options['search']['error']]] + $options,
					$sql_parts
				);
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

	private static function addRelatedFilter(array $options, array &$cep_rules): void {
		if ($options['selectFilter'] === null) {
			return;
		}

		$has_evaltype = in_array('evaltype', $options['selectFilter']);

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['filter'] = $has_evaltype ? ['evaltype' => $cep_rule['evaltype']] : [];
		}
		unset($cep_rule);

		$has_formula = in_array('formula', $options['selectFilter']);
		$has_eval_formula = in_array('eval_formula', $options['selectFilter']);
		$has_conditions = in_array('conditions', $options['selectFilter']);

		if (!$has_formula && !$has_eval_formula && !$has_conditions) {
			return;
		}

		$conditions_options = [
			'output' => ['cep_ruleid', 'cep_conditionid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
				'severity', 'host', 'host_group', 'time_period'
			],
			'filter' => ['cep_ruleid' => array_keys($cep_rules)],
			'sortfield' => ['cep_conditionid']
		];
		$resource = DBselect(DB::makeSql('cep_condition', $conditions_options));

		$cep_conditions = [];
		while ($row = DBfetch($resource)) {
			$cep_conditions[$row['cep_ruleid']][$row['cep_conditionid']] =
				array_diff_key($row, array_flip(['cep_ruleid', 'cep_conditionid']));
		}

		foreach ($cep_rules as &$cep_rule) {
			$conditions = array_key_exists($cep_rule['cep_ruleid'], $cep_conditions)
				? $cep_conditions[$cep_rule['cep_ruleid']]
				: [];
			$eval_formula = $cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
				? $cep_rule['formula']
				: CConditionHelper::getEvalFormula($conditions, 'type', (int) $cep_rule['evaltype']);

			CConditionHelper::addFormulaIds($conditions, $eval_formula);
			CConditionHelper::replaceConditionIds($eval_formula, $conditions);

			$filter = &$cep_rule['filter'];

			if ($has_formula) {
				$filter['formula'] = $cep_rule['formula'];
			}

			if ($has_eval_formula) {
				$filter['eval_formula'] = $eval_formula;
			}

			if ($has_conditions) {
				$filter['conditions'] = array_values($conditions);
			}
		}
		unset($cep_rule);
	}

	private static function addRelatedWindow(array $options, array &$cep_rules): void {
		if ($options['selectWindow'] === null) {
			return;
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['window'] = [];
			$cep_rule['window']['tags'] = [];
		}
		unset($cep_rule);

		$window_options = [
			'output' => array_merge(['cep_ruleid'], $options['selectWindow']),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)]
		];
		$resource = DBselect(DB::makeSql('cep_window', $window_options));

		while ($row = DBfetch($resource)) {
			if (array_key_exists('tags', $row)) {
				$row['tags'] = $row['tags'] !== '' ? explode("\n", $row['tags']) : [];
			}

			$cep_rules[$row['cep_ruleid']]['window'] = array_diff_key($row, array_flip(['cep_ruleid']));
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

		$has_filter = in_array('filter', $options['selectOperations']);

		$operation_options = [
			'output' => array_merge(
				['cep_operationid', 'cep_ruleid'],
				array_diff($options['selectOperations'], ['filter']),
				$has_filter ? ['evaltype'] : []
			),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)],
			'sortfield' => ['cep_operationid']
		];
		$resource = DBselect(DB::makeSql('cep_operation', $operation_options));
		$operation_ruleids = [];

		while ($row = DBfetch($resource)) {
			$operation = array_diff_key($row, array_flip(['cep_ruleid', 'cep_operationid', 'evaltype']));

			if ($has_filter) {
				$operation['filter'] = [
					'evaltype' => $row['evaltype'],
					'conditions' => []
				];
				$operation_ruleids[$row['cep_operationid']] = $row['cep_ruleid'];
			}

			$cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] = $operation;
		}

		if ($operation_ruleids) {
			$filter_options = [
				'output' => ['cep_operationid', 'type', 'operator', 'tag', 'value'],
				'filter' => ['cep_operationid' => array_keys($operation_ruleids)]
			];
			$resource = DBselect(DB::makeSql('cep_operation_condition', $filter_options));

			while ($row = DBfetch($resource)) {
				$cep_ruleid = $operation_ruleids[$row['cep_operationid']];

				$cep_rules[$cep_ruleid]['operations'][$row['cep_operationid']]['filter']['conditions'][] =
					array_diff_key($row, array_flip(['cep_operationid', 'cep_operation_conditionid']));
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

		$ins_cep_rule_rtdata = [];

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['cep_ruleid'] = array_shift($cep_ruleids);

			$ins_cep_rule_rtdata[] = ['cep_ruleid' => $cep_rule['cep_ruleid']];
		}
		unset($cep_rule);

		DB::insertBatch('cep_rule_rtdata', $ins_cep_rule_rtdata, false);

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
									CCepRuleHelper::WINDOW_NONE,
									CCepRuleHelper::WINDOW_SIMPLE,
									CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
									CCepRuleHelper::WINDOW_TAG_MATCH,
									CCepRuleHelper::WINDOW_PATTERN_MATCH
								])] + ($is_update ? [] : ['default' => CCepRuleHelper::WINDOW_NONE]),
			'window' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										CCepRuleHelper::WINDOW_SIMPLE,
										CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
										CCepRuleHelper::WINDOW_TAG_MATCH,
										CCepRuleHelper::WINDOW_PATTERN_MATCH
									])], 'type' => API_OBJECT, 'flags' => $api_required | API_NOT_EMPTY | API_ALLOW_UNEXPECTED, 'fields' => []],
									['else' => true, 'type' => API_OBJECT, 'fields' => [], 'unset' => true]
			]],
			'operations' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										CCepRuleHelper::WINDOW_NONE,
										CCepRuleHelper::WINDOW_SIMPLE,
										CCepRuleHelper::WINDOW_TAG_MATCH,
										CCepRuleHelper::WINDOW_PATTERN_MATCH
									])], 'type' => API_OBJECTS, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []],
									['if' => ['field' => 'window_type', 'in' => implode(',', [
										CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
									])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE | API_ALLOW_UNEXPECTED, 'fields' => []]
			]],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::EXECUTION_CONTINUE, CCepRuleHelper::EXECUTION_STOP])],
			'sortorder' =>		['type' => API_INT32, 'in' => ZBX_MIN_INT32.':'.ZBX_MAX_INT32],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::STATUS_ENABLED, CCepRuleHelper::STATUS_DISABLED])]
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
		$db_filter_defaults = array_intersect_key(DB::getDefaults('cep_rule'), array_flip(['evaltype', 'formula']));

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

			$cep_rule['filter'] += $db_filter_defaults + ['conditions' => []];
		}
		unset($cep_rule);
	}

	private static function getConditionValidationFields(): array {
		return [
			'type' =>			['type' => API_INT32, 'in' => implode(',', [
									CCepRuleHelper::CONDITION_EVENT_NAME,
									CCepRuleHelper::CONDITION_TAG,
									CCepRuleHelper::CONDITION_TAG_VALUE,
									CCepRuleHelper::CONDITION_SEVERITY,
									CCepRuleHelper::CONDITION_HOST,
									CCepRuleHelper::CONDITION_HOST_GROUP,
									CCepRuleHelper::CONDITION_TIME_PERIOD
								]), 'flags' => API_REQUIRED],
			'operator' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [
										CCepRuleHelper::CONDITION_EVENT_NAME,
										CCepRuleHelper::CONDITION_HOST,
										CCepRuleHelper::CONDITION_HOST_GROUP
									])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EXISTS,
										CONDITION_OPERATOR_NOT_EXISTS
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG_VALUE])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LIKE,
										CONDITION_OPERATOR_NOT_LIKE,
										CONDITION_OPERATOR_MORE_EQUAL,
										CONDITION_OPERATOR_LESS_EQUAL
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_SEVERITY])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_EQUAL,
										CONDITION_OPERATOR_NOT_EQUAL,
										CONDITION_OPERATOR_LESS_EQUAL,
										CONDITION_OPERATOR_MORE_EQUAL
									])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TIME_PERIOD])], 'type' => API_INT32, 'in' => implode(',', [
										CONDITION_OPERATOR_IN,
										CONDITION_OPERATOR_NOT_IN
									])],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_condition', 'operator')]
			]],
			'event_name' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_EVENT_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'event_name')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'event_name')]
			]],
			'tag' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG, CCepRuleHelper::CONDITION_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'tag')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'tag')]
			]],
			'tag_value' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'tag_value')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'tag_value')]
			]],
			'severity' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_SEVERITY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
									['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_condition', 'severity')]
			]],
			'host' =>			['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_HOST])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'host')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host')]
			]],
			'host_group' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_HOST_GROUP])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'host_group')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'host_group')]
			]],
			'time_period' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TIME_PERIOD])], 'type' => API_TIME_PERIOD, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'time_period')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'time_period')]
			]]
		];
	}

	private static function validateWindow(array &$cep_rules, ?array $db_cep_rules = null): void {
		$is_update = $db_cep_rules !== null;
		$api_required = $is_update ? 0x00 : API_REQUIRED;

		self::addRequiredWindowFieldsByWindowType($cep_rules, $db_cep_rules);

		foreach ($cep_rules as $i => &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)) {
				if ($is_update && $cep_rule['window_type'] == $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
					continue;
				}

				if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
					$cep_rule += ['window' => []];

					continue;
				}
			}

			$path = '/'.($i + 1).'/window';

			$grouping_allowed = in_array($cep_rule['window_type'], [
				CCepRuleHelper::WINDOW_SIMPLE,
				CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
				CCepRuleHelper::WINDOW_TAG_MATCH,
				CCepRuleHelper::WINDOW_PATTERN_MATCH
			]);
			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_REQUIRED, 'fields' => [
				'duration' =>				['type' => API_TIME_UNIT, 'flags' => $api_required | API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_YEAR, 'length' => DB::getFieldLength('cep_window', 'duration')],
				'capacity' =>				['type' => API_INT32, 'flags' => API_ALLOW_USER_MACRO, 'in' => '0:'.ZBX_MAX_INT64, 'length' => DB::getFieldLength('cep_window', 'capacity')],
				'group_by_host_group' =>	$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_host_group')],
				'group_by_host' =>			$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_host')],
				'group_by_tags' =>			$grouping_allowed
												? ['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])]
												: ['type' => API_INT32, 'in' => DB::getDefault('cep_window', 'group_by_tags')],
				'tags' =>					$grouping_allowed
												? ['type' => API_MULTIPLE, 'rules' => [
													['if' => ['field' => 'group_by_tags', 'in' => (string) CCepRuleHelper::GROUP_BY_YES], 'type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE],
													['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'tags')]
												]]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'tags')],
				'event_count_tag' =>		$cep_rule['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
												? ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window', 'event_count_tag')]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'event_count_tag')],
				'script' =>					$cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH
												? ['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'script')]
												: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'script')]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $cep_rule['window'], $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
				$grouping_fields = array_flip(['group_by_host_group', 'group_by_host', 'group_by_tags']);

				if (!array_filter(array_intersect_key($cep_rule['window'], $grouping_fields))) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path, _('at least one of "group_by_host_group", "group_by_host" or "group_by_tags" parameters must be enabled')
					));
				}
			}
		}
		unset($cep_rule);
	}

	private static function addRequiredWindowFieldsByWindowType(array &$cep_rules, ?array $db_cep_rules = null): void {
		$rule_indexes = [];

		foreach ($cep_rules as $i => &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			if ($db_cep_rules !== null
					&& $cep_rule['window_type'] == $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']
					&& in_array($cep_rule['window_type'], [
						CCepRuleHelper::WINDOW_SIMPLE,
						CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
						CCepRuleHelper::WINDOW_TAG_MATCH,
						CCepRuleHelper::WINDOW_PATTERN_MATCH
					])) {
				$rule_indexes[$cep_rule['cep_ruleid']] = $i;
			}
			else {
				$cep_rule['window'] += ['group_by_tags' => DB::getDefault('cep_window', 'group_by_tags')];
			}
		}
		unset($cep_rule);

		if (!$rule_indexes) {
			return;
		}

		$options = [
			'output' => ['cep_ruleid', 'group_by_host_group', 'group_by_host', 'group_by_tags', 'tags'],
			'filter' => ['cep_ruleid' => array_keys($rule_indexes)]
		];
		$resource = DBselect(DB::makeSql('cep_window', $options));

		while ($row = DBfetch($resource)) {
			$rule_index = $rule_indexes[$row['cep_ruleid']];
			$cep_rules[$rule_index]['window'] += array_intersect_key($row,
				array_flip(['group_by_host_group', 'group_by_host', 'group_by_tags', 'tags'])
			);

			if ($cep_rules[$rule_index]['window']['group_by_tags'] == 0) {
				unset($cep_rules[$rule_indexes[$row['cep_ruleid']]]['window']['tags']);
			}
		}
	}

	protected static function checkFilterFormula(array $object, string $path): void {
		if ($object['filter']['evaltype'] != CONDITION_EVAL_TYPE_EXPRESSION) {
			return;
		}

		$path .= '/filter';
		$condition_formula_parser = new CConditionFormulaParser();
		$condition_formula_parser->parse($object['filter']['formula']);
		$constants = array_unique(array_column($condition_formula_parser->getConstants(), 'value'));
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
				$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
					'execute_when' =>	['type' => API_INT32, 'in' => implode(',', CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE[$cep_rule['window_type']]), 'flags' => API_REQUIRED]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $operation, $path.'/'.($j + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}

				$api_input_rules = ['type' => API_OBJECT, 'fields' => [
					'sortorder' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => ZBX_MIN_INT32.':'.ZBX_MAX_INT32],
					'execute_when' =>	['type' => API_ANY],
					'filter' =>			self::getOperationFilterValidationRules($operation['execute_when']),
					'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN[$operation['execute_when']])],
					'event_name' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_SET_NAME
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'event_name')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'event_name')]
					]],
					'severity' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_SET_SEVERITY
											])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'severity')]
					]],
					'suppress_duration' =>	['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_SUPPRESS
											])], 'type' => API_TIME_UNIT, 'flags' => API_REQUIRED],
											['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'suppress_duration')]
					]],
					'tag' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_ADD_TAG,
												CCepRuleHelper::OP_SET_TAG,
												CCepRuleHelper::OP_SET_TAG_VALUE,
												CCepRuleHelper::OP_INCREASE_TAG_VALUE,
												CCepRuleHelper::OP_DECREASE_TAG_VALUE,
												CCepRuleHelper::OP_RENAME_TAG,
												CCepRuleHelper::OP_REMOVE_TAG
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'tag')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag')]
					]],
					'new_tag' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_RENAME_TAG
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'new_tag')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'new_tag')]
					]],
					'tag_value' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												CCepRuleHelper::OP_ADD_TAG,
												CCepRuleHelper::OP_SET_TAG,
												CCepRuleHelper::OP_SET_TAG_VALUE,
												CCepRuleHelper::OP_INCREASE_TAG_VALUE,
												CCepRuleHelper::OP_DECREASE_TAG_VALUE,
												CCepRuleHelper::OP_RENAME_TAG,
												CCepRuleHelper::OP_REMOVE_TAG
											])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation', 'tag_value')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag_value')]
					]]
				]];

				if (!CApiInputValidator::validate($api_input_rules, $operation, $path.'/'.($j + 1), $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
			unset($operation);
		}
		unset($cep_rule);
	}

	private static function getOperationFilterValidationRules(int $execute_when): array {
		return ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR])],
			'conditions' =>		[
									'type' => API_OBJECTS,
									'flags' => API_REQUIRED | API_NORMALIZE,
									'uniq_by_values' => [
										['type' => [ZBX_CONDITION_TYPE_EVENT_OPEN]],
										['type' => [ZBX_CONDITION_TYPE_EVENT_FIRST]],
										['type' => [ZBX_CONDITION_TYPE_EVENT_LAST]],
										['type' => [ZBX_CONDITION_TYPE_EVENT_SYMPTOM]],
										['type' => [ZBX_CONDITION_TYPE_EVENT_COPIED]],
										['type' => [ZBX_CONDITION_TYPE_EVENT_SUPPRESSED]]
									],
									'fields' => [
				'type' =>				['type' => API_INT32, 'in' => implode(',', CCepRuleHelper::OPERATION_CONDITION_TYPES_BY_EXECUTE_WHEN[$execute_when]), 'flags' => API_REQUIRED],
				'operator' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CONDITION_TYPE_EVENT_TAG
											])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS])],
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CONDITION_TYPE_EVENT_TAG_VALUE
											])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL])],
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CONDITION_TYPE_EVENT_OPEN,
												ZBX_CONDITION_TYPE_EVENT_FIRST,
												ZBX_CONDITION_TYPE_EVENT_LAST,
												ZBX_CONDITION_TYPE_EVENT_SYMPTOM,
												ZBX_CONDITION_TYPE_EVENT_COPIED,
												ZBX_CONDITION_TYPE_EVENT_SUPPRESSED
											])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO])]
				]],
				'tag' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CONDITION_TYPE_EVENT_TAG,
												ZBX_CONDITION_TYPE_EVENT_TAG_VALUE
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_condition', 'tag')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation_condition', 'tag')]
				]],
				'value' =>				['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', 'in' => implode(',', [
												ZBX_CONDITION_TYPE_EVENT_TAG_VALUE
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation_condition', 'value')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation_condition', 'value')]
				]]
									]
			]
		]];
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

			$upd_rule = array_intersect_key($cep_rule['filter'], array_flip(['evaltype', 'formula']));

			if ($cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::replaceFormulaIds($upd_rule['formula'],
					array_column($cep_rule['filter']['conditions'], null, 'cep_conditionid')
				);
			}

			$upd_rule = DB::getUpdatedValues('cep_rule', $upd_rule,
				$db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']]['filter'] : $db_defaults
			);

			if ($upd_rule) {
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

			$db_conditions = $db_cep_rules !== null
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
		$del_windows = [];
		$upd_windows = [];
		$ins_windows = [];

		if ($db_cep_rules !== null) {
			self::addWindowFieldDefaultsByType($cep_rules, $db_cep_rules);
		}

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			$cep_ruleid = $cep_rule['cep_ruleid'];

			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
				if ($db_cep_rules !== null
						&& $db_cep_rules[$cep_ruleid]['window_type'] != CCepRuleHelper::WINDOW_NONE) {
					$del_windows[] = $cep_ruleid;
				}
			}
			elseif ($db_cep_rules === null
					|| $db_cep_rules[$cep_ruleid]['window_type'] == CCepRuleHelper::WINDOW_NONE) {

				if (!array_key_exists('tags', $cep_rule['window'])) {
					$cep_rule['window']['tags'] = [];
				}

				$ins_windows[] = [
					'cep_ruleid' => $cep_ruleid,
					'type' => $cep_rule['window_type'],
					'tags' => implode(PHP_EOL, $cep_rule['window']['tags'])
				] + $cep_rule['window'];
			}
			else {
				$upd_window = DB::getUpdatedValues('cep_window',
					['type' => $cep_rule['window_type']] + $cep_rule['window'],
					$db_cep_rules[$cep_ruleid]['window']
				);

				if ($upd_window) {
					if (array_key_exists('tags', $upd_window)) {
						$upd_window['tags'] = implode(PHP_EOL, (array) $upd_window['tags']);
					}

					$upd_windows[] = [
						'values' => $upd_window,
						'where' => ['cep_ruleid' => $cep_ruleid]
					];
				}
			}
		}
		unset($cep_rule);

		if ($del_windows) {
			DB::delete('cep_window', ['cep_ruleid' => $del_windows]);
		}

		if ($upd_windows) {
			DB::update('cep_window', $upd_windows);
		}

		if ($ins_windows) {
			DB::insert('cep_window', $ins_windows, false);
		}
	}

	private static function addWindowFieldDefaultsByType(array &$cep_rules, array $db_cep_rules): void {
		$db_defaults = DB::getDefaults('cep_window');

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('window', $cep_rule)
					|| $cep_rule['window_type'] == $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				continue;
			}

			$allowed_fields = ['duration', 'capacity'];

			if (in_array($cep_rule['window_type'], [
				CCepRuleHelper::WINDOW_SIMPLE,
				CCepRuleHelper::WINDOW_CAUSE_SYMPTOM,
				CCepRuleHelper::WINDOW_TAG_MATCH,
				CCepRuleHelper::WINDOW_PATTERN_MATCH
			])) {
				$allowed_fields[] = 'group_by_host_group';
				$allowed_fields[] = 'group_by_host';
				$allowed_fields[] = 'group_by_tags';
				$allowed_fields[] = 'tags';
			}

			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
				$allowed_fields[] = 'event_count_tag';
			}
			elseif ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH) {
				$allowed_fields[] = 'script';
			}

			$cep_rule['window'] += array_diff_key($db_defaults, array_flip($allowed_fields));

			if (!array_key_exists('tags', $cep_rule['window']) || $cep_rule['window']['tags'] === '') {
				$cep_rule['window']['tags'] = [];
			}
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
						return array_diff_key($operation, array_flip(['sortorder'])) ==
							array_diff_key($db_operation, array_flip(['cep_operationid', 'sortorder']));
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
			DB::delete('cep_operation_condition', ['cep_operationid' => $del_operationids]);
			DB::delete('cep_operation', ['cep_operationid' => $del_operationids]);
		}

		if ($upd_operations) {
			DB::update('cep_operation', $upd_operations);
		}

		if ($ins_operations) {
			$operationids = DB::insert('cep_operation', $ins_operations);
		}

		$operations = [];
		$db_operations = null;

		if ($db_cep_rules !== null) {
			$db_operations = [];
		}

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			foreach ($cep_rule['operations'] as &$operation) {
				if (!array_key_exists('cep_operationid', $operation)) {
					$operation['cep_operationid'] = array_shift($operationids);

					if ($db_cep_rules !== null) {
						$db_operations[$operation['cep_operationid']] = [
							'cep_operationid' => $operation['cep_operationid']
						];

						if (array_key_exists('filter', $operation)) {
							$db_operations[$operation['cep_operationid']]['filter'] = [
								'evaltype' => DB::getDefault('cep_operation', 'evaltype'),
								'conditions' => []
							];
						}
					}
				}
				else {
					$db_operations[$operation['cep_operationid']] =
						$db_cep_rules[$cep_rule['cep_ruleid']]['operations'][$operation['cep_operationid']];
				}

				$operations[] = &$operation;
			}
			unset($operation);
		}
		unset($cep_rule);

		if ($operations) {
			self::updateOperationFilters($operations, $db_operations);
		}
	}

	private static function updateOperationFilters(array $operations, ?array $db_operations): void {
		$upd_operations = [];

		foreach ($operations as $operation) {
			if (!array_key_exists('filter', $operation)) {
				continue;
			}

			$db_operation = $db_operations !== null
				? $db_operations[$operation['cep_operationid']]
				: ['filter' => ['evaltype' => DB::getDefault('cep_operation', 'evaltype')]];

			$upd_operation = DB::getUpdatedValues('cep_operation', $operation['filter'], $db_operation['filter']);

			if ($upd_operation) {
				$upd_operations[] = [
					'values' => $upd_operation,
					'where' => ['cep_operationid' => $operation['cep_operationid']]
				];
			}
		}

		if ($upd_operations) {
			DB::update('cep_operation', $upd_operations);
		}

		self::updateOperationFilterConditions($operations, $db_operations);
	}

	private static function updateOperationFilterConditions(array $operations, ?array $db_operations): void {
		$ins_conditions = [];
		$del_conditionids = [];

		foreach ($operations as &$operation) {
			if (!array_key_exists('filter', $operation) || !array_key_exists('conditions', $operation['filter'])) {
				continue;
			}

			$db_conditions = $db_operations !== null
				? $db_operations[$operation['cep_operationid']]['filter']['conditions']
				: [];

			foreach ($operation['filter']['conditions'] as &$condition) {
				$db_conditionid = self::getOperationConditionId($condition, $db_conditions);

				if ($db_conditionid !== null) {
					$condition['cep_operation_conditionid'] = $db_conditionid;
					unset($db_conditions[$db_conditionid]);
				}
				else {
					$ins_conditions[] = ['cep_operationid' => $operation['cep_operationid']] + $condition;
				}
			}
			unset($condition);

			$del_conditionids = array_merge($del_conditionids, array_keys($db_conditions));
		}
		unset($operation);

		if ($del_conditionids) {
			DB::delete('cep_operation_condition', ['cep_operation_conditionid' => $del_conditionids]);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_operation_condition', $ins_conditions);
		}

		foreach ($operations as &$operation) {
			if (!array_key_exists('filter', $operation) || !array_key_exists('conditions', $operation['filter'])) {
				continue;
			}

			foreach ($operation['filter']['conditions'] as &$condition) {
				if (!array_key_exists('cep_operation_conditionid', $condition)) {
					$condition['cep_operation_conditionid'] = array_shift($conditionids);
				}
			}
			unset($condition);
		}
		unset($operation);
	}

	private static function getOperationConditionId(array $condition, array $db_conditions): ?string {
		$condition += [
			'tag' => DB::getDefault('cep_operation_condition', 'tag'),
			'value' => DB::getDefault('cep_operation_condition', 'value')
		];

		foreach ($db_conditions as $db_condition) {
			if (!DB::getUpdatedValues('cep_operation_condition', $condition, $db_condition)) {
				return $db_condition['cep_operation_conditionid'];
			}
		}

		return null;
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

		$db_cep_rules = DBfetchArrayAssoc(DBselect(
			'SELECT cr.cep_ruleid,cr.name,cr.evaltype,cr.formula,cr.stop,cr.sortorder,cr.description,cr.status,'.
				dbConditionCoalesce('cw.type', CCepRuleHelper::WINDOW_NONE, 'window_type').
			' FROM cep_rule cr'.
			' LEFT JOIN cep_window cw ON cr.cep_ruleid=cw.cep_ruleid'.
			' WHERE '.dbConditionId('cr.cep_ruleid', array_column($cep_rules, 'cep_ruleid'))
		), 'cep_ruleid');

		if (count($cep_rules) != count($db_cep_rules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$cep_rules = $this->extendObjectsByKey($cep_rules, $db_cep_rules, 'cep_ruleid', ['name', 'window_type']);

		if (!CApiInputValidator::validate(self::getValidationRules(true), $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($cep_rules, $db_cep_rules);

		self::validateFilter($cep_rules);
		self::validateWindow($cep_rules, $db_cep_rules);
		self::validateOperations($cep_rules);

		self::addAffectedObjects($cep_rules, $db_cep_rules);
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

				$db_cep_rules[$cep_ruleid]['filter'] =
					array_intersect_key($db_cep_rules[$cep_ruleid], array_flip(['evaltype', 'formula']))
					+ ['conditions' => []];

				$cep_ruleids[] = $cep_ruleid;
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

		foreach ($cep_ruleids as $cep_ruleid) {
			self::addFilterConditionFormulaids($db_cep_rules[$cep_ruleid]['filter']);
		}
	}

	private static function addFilterConditionFormulaids(array &$filter): void {
		if ($filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
			CConditionHelper::addFormulaIds($filter['conditions'], $filter['formula']);
			CConditionHelper::replaceConditionIds($filter['formula'], $filter['conditions']);
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
				$cep_ruleids[] = $cep_rule['cep_ruleid'];
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => array_merge(['cep_ruleid'], self::WINDOW_OUTPUT_FIELDS),
			'filter' => ['cep_ruleid' => $cep_ruleids]
		];
		$resource = DBselect(DB::makeSql('cep_window', $options));

		while ($row = DBfetch($resource)) {
			$row['tags'] = $row['tags'] !== '' ? explode("\n", $row['tags']) : [];

			$db_cep_rules[$row['cep_ruleid']]['window'] = array_diff_key($row, array_flip(['cep_ruleid']));
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
			'output' => array_merge(['cep_operationid', 'cep_ruleid'],
				array_diff(self::OPERATIONS_OUTPUT_FIELDS, ['filter'])
			),
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'sortfield' => ['sortorder']
		];
		$resource = DBSelect(DB::makeSql('cep_operation', $options));
		$db_operations = [];

		while ($row = DBfetch($resource)) {
			$db_cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] =
				array_diff_key($row, array_flip(['cep_ruleid']));

			$db_operations[$row['cep_operationid']] =
				&$db_cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']];
		}

		if (!$db_operations) {
			return;
		}

		self::addAffectedOperationFilter($db_operations);
	}

	private static function addAffectedOperationFilter(array &$db_operations): void {
		foreach ($db_operations as &$db_operation) {
			$db_operation['filter'] = [];
		}
		unset($db_operation);

		$options = [
			'output' => ['cep_operationid', 'evaltype'],
			'cep_operationids' => array_keys($db_operations)
		];
		$resource = DBselect(DB::makeSql('cep_operation', $options));

		while ($row = DBfetch($resource)) {
			$db_operations[$row['cep_operationid']]['filter']['evaltype'] = $row['evaltype'];
		}

		$options = [
			'output' => ['cep_operation_conditionid', 'cep_operationid', 'type', 'tag', 'operator', 'value'],
			'filter' => ['cep_operationid' => array_keys($db_operations)]
		];
		$resource = DBselect(DB::makeSql('cep_operation_condition', $options));

		while ($row = DBfetch($resource)) {
			$db_operations[$row['cep_operationid']]['filter']['conditions'][$row['cep_operation_conditionid']] =
				array_diff_key($row, array_flip(['cep_operationid']));
		}
	}

	private static function updateForce(array $cep_rules, ?array &$db_cep_rules = null): void {
		$tags_formatted = function(array $cep_rule): array {
			if (array_key_exists('window', $cep_rule) && array_key_exists('tags', $cep_rule['window'])) {
				$cep_rule['window']['tags'] = implode(PHP_EOL, $cep_rule['window']['tags']);
			}

			return $cep_rule;
		};

		$upd_rules = [];

		foreach ($cep_rules as $cep_rule) {
			$db_cep_rule = $db_cep_rules[$cep_rule['cep_ruleid']];
			$upd_rule = DB::getUpdatedValues('cep_rule', $cep_rule, $db_cep_rule);

			if ($upd_rule) {
				$upd_rules[] = [
					'values' => $tags_formatted($upd_rule),
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

		$window_tags_newlines = function(array $records): array {
			return array_map(function(array $record) {
				if (array_key_exists('window', $record) && array_key_exists('tags', $record['window'])) {
					$record['window']['tags'] = implode(PHP_EOL, (array) $record['window']['tags']);
				}

				return $record;
			}, $records);
		};

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CEP_RULE, $window_tags_newlines($cep_rules),
			$window_tags_newlines($db_cep_rules)
		);
	}

	public function delete(array $cep_ruleids): array {
		$this->validateDelete($cep_ruleids, $db_cep_rules);

		DB::delete('cep_condition', ['cep_ruleid' => $cep_ruleids]);
		DB::delete('cep_window', ['cep_ruleid' => $cep_ruleids]);
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
			'cep_ruleids' => $cep_ruleids,
			'preservekeys' => true
		]);

		if (count($db_cep_rules) != count($cep_ruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}

	private static function deleteAffectedOperations(array $cep_ruleids): void {
		$operationids = array_keys(DB::select('cep_operation', [
			'output' => [],
			'filter' => ['cep_ruleid' => $cep_ruleids],
			'preservekeys' => true
		]));

		if ($operationids) {
			DB::delete('cep_operation_condition', ['cep_operationid' => $operationids]);
			DB::delete('cep_operation', ['cep_operationid' => $operationids]);
		}
	}
}
