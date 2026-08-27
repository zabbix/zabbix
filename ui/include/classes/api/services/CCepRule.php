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
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'resettimewindows' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
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
				$sql_parts['join']['cw'] = ['type' => 'left', 'table' => 'cep_rule_window', 'using' => 'cep_ruleid'];
				$sql_parts['select']['window_type'] =
					dbConditionCoalesce('cw.type', CCepRuleHelper::WINDOW_NONE, 'window_type');
			}

			if (in_array('error', $options['output'])) {
				$sql_parts['join']['crr'] = ['table' => 'cep_rule_rtdata', 'using' => 'cep_ruleid'];
				$sql_parts = $this->addQuerySelect('crr.error', $sql_parts);
			}

			if ($options['selectFilter'] !== null && $options['selectFilter']) {
				$sql_parts = $this->addQuerySelect('cr.evaltype', $sql_parts);

				if (array_intersect($options['selectFilter'], ['eval_formula', 'formula', 'conditions'])) {
					$sql_parts = $this->addQuerySelect('cr.formula', $sql_parts);
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
					$sql_parts['join']['cw'] = ['type' => 'left', 'table' => 'cep_rule_window', 'using' => 'cep_ruleid'];
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
			$cep_rule['filter'] = [];

			if ($has_evaltype) {
				$cep_rule['filter']['evaltype'] = $cep_rule['evaltype'];
			}
		}
		unset($cep_rule);

		$has_eval_formula = in_array('eval_formula', $options['selectFilter']);
		$has_formula = in_array('formula', $options['selectFilter']);
		$has_conditions = in_array('conditions', $options['selectFilter']);

		if (!$has_eval_formula && !$has_formula && !$has_conditions) {
			return;
		}

		$conditions_options = [
			'output' => ['cep_conditionid', 'cep_ruleid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
				'severity', 'host', 'host_group', 'time_period'
			],
			'filter' => ['cep_ruleid' => array_keys($cep_rules)]
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

			if ($cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::sortConditionsByFormula($conditions, $cep_rule['formula']);

				$eval_formula = $cep_rule['formula'];
			}
			else {
				CConditionHelper::sortCepRuleConditions($conditions);

				$eval_formula = CConditionHelper::getEvalFormula($conditions, 'type', (int) $cep_rule['evaltype']);
			}

			CConditionHelper::addFormulaIds($conditions, $eval_formula);
			CConditionHelper::replaceConditionIds($eval_formula, $conditions);

			if ($has_eval_formula) {
				$cep_rule['filter']['eval_formula'] = $eval_formula;
			}

			if ($has_formula) {
				$cep_rule['filter']['formula'] = $cep_rule['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION
					? $eval_formula
					: '';
			}

			if ($has_conditions) {
				$cep_rule['filter']['conditions'] = array_values($conditions);
			}
		}
		unset($cep_rule);
	}

	private static function addRelatedWindow(array $options, array &$cep_rules): void {
		if ($options['selectWindow'] === null) {
			return;
		}

		foreach ($cep_rules as &$cep_rule) {
			$cep_rule['window'] = self::getWindowDefaults();
		}
		unset($cep_rule);

		$window_options = [
			'output' => array_merge(['cep_ruleid'], $options['selectWindow']),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)]
		];
		$resource = DBselect(DB::makeSql('cep_rule_window', $window_options));

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
				$has_filter ? ['evaltype', 'formula'] : []
			),
			'filter' => ['cep_ruleid' => array_keys($cep_rules)],
			'sortfield' => ['sortorder']
		];
		$resource = DBselect(DB::makeSql('cep_operation', $operation_options));

		$op_filters = [];

		while ($row = DBfetch($resource)) {
			$cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']] =
				array_diff_key($row, array_flip(['cep_ruleid', 'cep_operationid', 'evaltype', 'formula']));

			if ($has_filter) {
				$cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']]['filter'] = [
					'evaltype' => $row['evaltype'],
					'eval_formula' => '',
					'formula' => $row['formula'],
					'conditions' => []
				];

				$op_filters[$row['cep_operationid']] =
					&$cep_rules[$row['cep_ruleid']]['operations'][$row['cep_operationid']]['filter'];
			}
		}

		if ($op_filters) {
			$filter_options = [
				'output' => ['cep_operation_conditionid', 'cep_operationid', 'type', 'operator', 'tag', 'tag_value'],
				'filter' => ['cep_operationid' => array_keys($op_filters)]
			];
			$resource = DBselect(DB::makeSql('cep_operation_condition', $filter_options));

			while ($row = DBfetch($resource)) {
				$op_filters[$row['cep_operationid']]['conditions'][$row['cep_operation_conditionid']] =
					array_diff_key($row, array_flip(['cep_operationid', 'cep_operation_conditionid']));
			}

			foreach ($op_filters as &$op_filter) {
				if ($op_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					CConditionHelper::sortConditionsByFormula($op_filter['conditions'], $op_filter['formula']);

					$op_filter['eval_formula'] = $op_filter['formula'];
				}
				else {
					CConditionHelper::sortCepRuleOperationConditions($op_filter['conditions']);

					$op_filter['eval_formula'] = CConditionHelper::getEvalFormula($op_filter['conditions'], 'type',
						(int) $op_filter['evaltype']
					);
				}

				CConditionHelper::addFormulaIds($op_filter['conditions'], $op_filter['eval_formula']);
				CConditionHelper::replaceConditionIds($op_filter['eval_formula'], $op_filter['conditions']);

				if ($op_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$op_filter['formula'] = $op_filter['eval_formula'];
				}

				$op_filter['conditions'] = array_values($op_filter['conditions']);
			}
			unset($op_filter);
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

		self::updateFilters($cep_rules);
		self::updateWindow($cep_rules);
		self::updateOperations($cep_rules);

		self::convertWindowTagsToString($cep_rules);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CEP_RULE, $cep_rules);

		return ['cep_ruleids' => array_column($cep_rules, 'cep_ruleid')];
	}

	private function validateCreate(array &$cep_rules): void {
		if (!CApiInputValidator::validate(self::getValidationRules(), $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($cep_rules);
		CConditionHelper::checkFilterFormula($cep_rules);
		self::validateWindow($cep_rules);
		self::validateOperations($cep_rules);
	}

	private static function getValidationRules(bool $is_update = false): array {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$specific_rules = $is_update
			? [
				'cep_ruleid' =>	['type' => API_ANY]
			]
			: [];

		return ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => $specific_rules + [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'name')],
			'filter' =>			self::getFilterValidationRules(),
			'window_type' =>	['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::WINDOW_NONE, CCepRuleHelper::WINDOW_SIMPLE, CCepRuleHelper::WINDOW_CAUSE_SYMPTOM, CCepRuleHelper::WINDOW_TAG_MATCH, CCepRuleHelper::WINDOW_PATTERN_MATCH])] + ($is_update ? [] : ['default' => CCepRuleHelper::WINDOW_NONE]),
			'window' =>			['type' => API_ANY],
			'operations' =>		['type' => API_ANY],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::EXECUTION_CONTINUE, CCepRuleHelper::EXECUTION_STOP])],
			'sortorder' =>		['type' => API_INT32, 'flags' => $api_required],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::STATUS_ENABLED, CCepRuleHelper::STATUS_DISABLED])]
		]];
	}

	private static function getFilterValidationRules(): array {
		return ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
			'formula' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_rule', 'formula')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'formula')]
			]],
			'conditions' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
										'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
									] + self::getFilterConditionValidationFields()],
									['else' => true, 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
										'formulaid' => ['type' => API_STRING_UTF8, 'in' => '']
									] + self::getFilterConditionValidationFields()]
			]]
		]];
	}

	private static function getFilterConditionValidationFields(): array {
		return [
			'type' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CCepRuleHelper::CONDITION_EVENT_NAME, CCepRuleHelper::CONDITION_TAG, CCepRuleHelper::CONDITION_TAG_VALUE, CCepRuleHelper::CONDITION_SEVERITY, CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP, CCepRuleHelper::CONDITION_TIME_PERIOD])],
			'operator' =>		['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_EVENT_NAME, CCepRuleHelper::CONDITION_HOST, CCepRuleHelper::CONDITION_HOST_GROUP])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TAG_VALUE])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_SEVERITY])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LESS_EQUAL, CONDITION_OPERATOR_MORE_EQUAL])],
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TIME_PERIOD])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_IN, CONDITION_OPERATOR_NOT_IN])]
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
									['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::CONDITION_TIME_PERIOD])], 'type' => API_TIME_PERIOD, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_condition', 'time_period')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'time_period')]
			]]
		];
	}

	private static function validateWindow(array &$cep_rules, ?array $db_cep_rules = null): void {
		$is_update = $db_cep_rules !== null;

		foreach ($cep_rules as $i => &$cep_rule) {
			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
				self::validateNoneWindow($cep_rule, '/'.($i + 1));

				continue;
			}

			$api_required = 0;

			if (!$is_update
					&& ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
						|| $cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH)) {
				$api_required = API_REQUIRED;
			}

			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
				'window' =>	['type' => API_OBJECT, 'flags' => $api_required | API_ALLOW_UNEXPECTED, 'fields' => [
					'group_by_tags' =>	['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])] + ($is_update ? [] : ['default' => DB::getDefault('cep_rule_window', 'group_by_tags')])
				]]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $cep_rule, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$db_cep_rule = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']] : null;

			if ($is_update) {
				self::addRequiredWindowFieldsByWindowType($cep_rule, $db_cep_rule);

				if (array_key_exists('window', $cep_rule)) {
					$cep_rule['window'] += ['group_by_tags' => $db_cep_rule['window']['group_by_tags']];

					self::addRequiredWindowFieldsByGroupByTagsField($cep_rule, $db_cep_rule);
				}
				else {
					continue;
				}
			}
			elseif (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			self::validateSupportedWindow($cep_rule, '/'.($i + 1), $is_update);
		}
	}

	private static function validateNoneWindow(array &$cep_rule, string $path): void {
		$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
			'window' =>	['type' => API_OBJECT, 'fields' => [
				'duration' =>				['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'duration')],
				'capacity' =>				['type' => API_INT32, 'in' => DB::getDefault('cep_rule_window', 'capacity')],
				'group_by_host_group' =>	['type' => API_INT32, 'in' => DB::getDefault('cep_rule_window', 'group_by_host_group')],
				'group_by_host' =>			['type' => API_INT32, 'in' => DB::getDefault('cep_rule_window', 'group_by_host')],
				'group_by_tags' =>			['type' => API_INT32, 'in' => DB::getDefault('cep_rule_window', 'group_by_tags')],
				'tags' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'tags')],
				'event_count_tag' =>		['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'event_count_tag')],
				'script' =>					['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'script')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $cep_rule, $path, $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function addRequiredWindowFieldsByWindowType(array &$cep_rule, array $db_cep_rule): void {
		if ($cep_rule['window_type'] != $db_cep_rule['window_type']
				&& $cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			$cep_rule += ['window' => []];

			$cep_rule['window'] += ['script' => $db_cep_rule['window']['script']];
		}
	}

	private static function addRequiredWindowFieldsByGroupByTagsField(array &$cep_rule, array $db_cep_rule): void {
		if ($cep_rule['window']['group_by_tags'] != $db_cep_rule['window']['group_by_tags']
				&& $cep_rule['window']['group_by_tags'] == CCepRuleHelper::GROUP_BY_YES) {
			$cep_rule['window'] += ['tags' => $db_cep_rule['window']['tags']];
		}
	}

	private static function validateSupportedWindow(array &$cep_rule, string $path, bool $is_update): void {
		$api_required = $is_update ? 0 : API_REQUIRED;

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'duration' =>				['type' => API_TIME_UNIT, 'flags' => API_ALLOW_USER_MACRO, 'in' => '1:'.SEC_PER_YEAR, 'length' => DB::getFieldLength('cep_rule_window', 'duration')],
			'capacity' =>				['type' => API_INT32, 'flags' => API_ALLOW_USER_MACRO, 'in' => '0:'.ZBX_MAX_INT32, 'length' => DB::getFieldLength('cep_rule_window', 'capacity')],
			'group_by_host_group' =>	['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])],
			'group_by_host' =>			['type' => API_INT32, 'in' => implode(',', [CCepRuleHelper::GROUP_BY_NO, CCepRuleHelper::GROUP_BY_YES])],
			'group_by_tags' =>			['type' => API_ANY],
			'tags' =>					['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'group_by_tags', 'in' => implode(',', [CCepRuleHelper::GROUP_BY_YES])], 'type' => API_STRINGS_UTF8, 'flags' => $api_required | API_NOT_EMPTY | API_NORMALIZE],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'tags')]
			]],
			'event_count_tag' =>		$cep_rule['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM
											? ['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule_window', 'event_count_tag')]
											: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'event_count_tag')],
			'script' =>					$cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH
											? ['type' => API_STRING_UTF8, 'flags' => $api_required | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule_window', 'script')]
											: ['type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule_window', 'script')]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $cep_rule['window'], $path.'/window', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}

	private static function validateOperations(array &$cep_rules, ?array $db_cep_rules = null): void {
		$is_update = $db_cep_rules !== null;

		foreach ($cep_rules as $i1 => &$cep_rule) {
			if ($is_update && $cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']
					&& !array_key_exists('operations', $cep_rule)) {
				self::addOperations($cep_rule, $db_cep_rules[$cep_rule['cep_ruleid']]);
			}

			$api_required = 0;
			$api_not_empty = 0;

			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE
					|| $cep_rule['window_type'] == CCepRuleHelper::WINDOW_SIMPLE
					|| $cep_rule['window_type'] == CCepRuleHelper::WINDOW_TAG_MATCH
					|| $cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH) {
				$api_required = $is_update ? 0 : API_REQUIRED;
				$api_not_empty = API_NOT_EMPTY;
			}

			if ($api_required == 0 && !array_key_exists('operations', $cep_rule)) {
				continue;
			}

			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
				'operations' =>	['type' => API_OBJECTS, 'flags' => $api_required | $api_not_empty | API_ALLOW_UNEXPECTED, 'uniq' => [['sortorder']], 'fields' => [
					'sortorder' =>		['type' => API_INT32, 'flags' => API_REQUIRED],
					'execute_when' =>	['type' => API_INT32, 'in' => implode(',', CCepRuleHelper::EXECUTE_WHEN_BY_WINDOW_TYPE[$cep_rule['window_type']]), 'flags' => API_REQUIRED]
				]]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $cep_rule, '/'.($i1 + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}

			$cep_rule_path = '/'.($i1 + 1);

			foreach ($cep_rule['operations'] as $i2 => &$operation) {
				$api_input_rules = self::getOperationValidationRules($operation);
				$path = $cep_rule_path.'/operations/'.($i2 + 1);

				if (!CApiInputValidator::validate($api_input_rules, $operation, $path, $error)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $error);
				}
			}
			unset($operation);

			self::checkPatternMatchOperations($cep_rule, $cep_rule_path);
			self::checkOperationOrder($cep_rule, $cep_rule_path);
		}
		unset($cep_rule);
	}

	private static function addOperations(array &$cep_rule, array $db_cep_rule): void {
		$cep_rule['operations'] = [];

		foreach ($db_cep_rule['operations'] as $operation) {
			$operation['filter']['conditions'] = array_values($operation['filter']['conditions']);

			$cep_rule['operations'][] = $operation;
		}
	}

	private static function getOperationValidationRules(array $operation): array {
		return ['type' => API_OBJECT, 'fields' => [
			'sortorder' =>			['type' => API_ANY],
			'execute_when' =>		['type' => API_ANY],
			'filter' =>				self::getOperationFilterValidationRules($operation),
			'type' =>				['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', CCepRuleHelper::OPERATION_TYPES_BY_EXECUTE_WHEN[$operation['execute_when']])],
			'event_name' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_SET_NAME])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'event_name')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'event_name')]
			]],
			'severity' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_SET_SEVERITY])], 'type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))],
										['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'severity')]
			]],
			'suppress_duration' =>	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_SUPPRESS])], 'type' => API_TIME_UNIT, 'flags' => API_REQUIRED | API_TIME_UNIT_WITH_YEAR, 'length' => DB::getFieldLength('cep_operation', 'suppress_duration')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'suppress_duration')]
			]],
			'tag' =>				['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE, CCepRuleHelper::OP_INCREASE_TAG_VALUE, CCepRuleHelper::OP_DECREASE_TAG_VALUE, CCepRuleHelper::OP_RENAME_TAG, CCepRuleHelper::OP_REMOVE_TAG])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'tag')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag')]
			]],
			'new_tag' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_RENAME_TAG])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'new_tag')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'new_tag')]
			]],
			'tag_value' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [CCepRuleHelper::OP_ADD_TAG, CCepRuleHelper::OP_SET_TAG, CCepRuleHelper::OP_SET_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation', 'tag_value')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'tag_value')]
			]]
		]];
	}

	private static function getOperationFilterValidationRules(array $operation): array {
		$uniq_by_values = [
			['type' => [ZBX_CONDITION_TYPE_EVENT_OPEN]],
			['type' => [ZBX_CONDITION_TYPE_EVENT_FIRST]],
			['type' => [ZBX_CONDITION_TYPE_EVENT_LAST]],
			['type' => [ZBX_CONDITION_TYPE_EVENT_SYMPTOM]],
			['type' => [ZBX_CONDITION_TYPE_EVENT_COPIED]],
			['type' => [ZBX_CONDITION_TYPE_EVENT_SUPPRESSED]]
		];

		return ['type' => API_OBJECT, 'fields' => [
			'evaltype' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
			'formula' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation', 'formula')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'formula')]
			]],
			'conditions' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
								['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq' => [['formulaid']], 'uniq_by_values' => $uniq_by_values, 'fields' => [
									'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
								] + self::getOperationFilterConditionValidationFields($operation)],
								['else' => true, 'type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'uniq_by_values' => $uniq_by_values, 'fields' => [
									'formulaid' => ['type' => API_STRING_UTF8, 'in' => '']
								] + self::getOperationFilterConditionValidationFields($operation)]

			]]
		]];
	}

	private static function getOperationFilterConditionValidationFields(array $operation): array {
		return [
			'type' =>		['type' => API_INT32, 'in' => implode(',', CCepRuleHelper::OPERATION_CONDITION_TYPES_BY_EXECUTE_WHEN[$operation['execute_when']]), 'flags' => API_REQUIRED],
			'operator' =>	['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CONDITION_TYPE_EVENT_TAG])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])],
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL])],
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CONDITION_TYPE_EVENT_OPEN, ZBX_CONDITION_TYPE_EVENT_FIRST, ZBX_CONDITION_TYPE_EVENT_LAST, ZBX_CONDITION_TYPE_EVENT_SYMPTOM, ZBX_CONDITION_TYPE_EVENT_COPIED, ZBX_CONDITION_TYPE_EVENT_SUPPRESSED])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO])]
			]],
			'tag' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CONDITION_TYPE_EVENT_TAG, ZBX_CONDITION_TYPE_EVENT_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_condition', 'tag')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation_condition', 'tag')]
			]],
			'tag_value' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CONDITION_TYPE_EVENT_TAG_VALUE])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_operation_condition', 'tag_value')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation_condition', 'tag_value')]
			]]
		];
	}

	private static function checkPatternMatchOperations(array $cep_rule, string $cep_rule_path): void {
		if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH) {
			$execute_whens = array_column($cep_rule['operations'], 'execute_when', 'execute_when');

			if (!array_key_exists(CCepRuleHelper::WHEN_PATTERN_MATCHED, $execute_whens)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					$cep_rule_path.'/operations', _('at least one operation must execute when pattern matched')
				));
			}
		}
	}

	private static function checkOperationOrder(array $cep_rule, string $cep_rule_path): void {
		$order = array_flip(CCepRuleHelper::EXECUTE_WHEN_ORDER);

		CArrayHelper::sort($cep_rule['operations'], ['field' => 'sortorder', 'order' => ZBX_SORT_UP]);
		$prev_i = null;

		foreach ($cep_rule['operations'] as $i => $operation) {
			if ($prev_i !== null
					&& $order[$operation['execute_when']] < $order[$cep_rule['operations'][$prev_i]['execute_when']]) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
					$cep_rule_path.'/operations/'.($i + 1).'/sortorder',
					_('operations must be grouped by "execute_when" in ascending order')
				));
			}

			$prev_i = $i;
		}
	}

	private static function checkDuplicates(array $cep_rules, ?array $db_cep_rules = null): void {
		$names = [];

		foreach ($cep_rules as $cep_rule) {
			if (!array_key_exists('name', $cep_rule)) {
				continue;
			}

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

	private static function updateFilters(array &$cep_rules, ?array $db_cep_rules = null): void {
		self::updateFilterConditions($cep_rules, $db_cep_rules);

		$upd_cep_rules = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule)) {
				continue;
			}

			$db_cep_rule = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']] : null;

			$upd_cep_rule = [];

			if ($db_cep_rule === null || $cep_rule['filter']['evaltype'] != $db_cep_rule['filter']['evaltype']) {
				$upd_cep_rule['evaltype'] = $cep_rule['filter']['evaltype'];
			}

			if ($cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$replace_formulaids = $db_cep_rule === null
					|| $cep_rule['filter']['formula'] !== $db_cep_rule['filter']['formula'];

				if (!$replace_formulaids) {
					foreach ($cep_rule['filter']['conditions'] as $condition) {
						$db_condition = $db_cep_rule['filter']['conditions'][$condition['cep_conditionid']];

						if ($condition['formulaid'] != $db_condition['formulaid']) {
							$replace_formulaids = true;

							break;
						}
					}
				}

				if ($replace_formulaids) {
					$upd_cep_rule['formula'] = $cep_rule['filter']['formula'];
					CConditionHelper::replaceFormulaIds($upd_cep_rule['formula'],
						array_column($cep_rule['filter']['conditions'], null, 'cep_conditionid')
					);
				}
			}
			elseif ($db_cep_rule !== null
					&& $db_cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$upd_cep_rule['formula'] = DB::getDefault('cep_rule', 'formula');
				$cep_rule['filter']['formula'] = DB::getDefault('cep_rule', 'formula');
			}

			if ($upd_cep_rule) {
				$upd_cep_rules[] = [
					'values' => $upd_cep_rule,
					'where' => ['cep_ruleid' => $cep_rule['cep_ruleid']]
				];
			}
		}
		unset($cep_rule);

		if ($upd_cep_rules) {
			DB::update('cep_rule', $upd_cep_rules);
		}
	}

	private static function updateFilterConditions(array &$cep_rules, ?array $db_cep_rules = null): void {
		$ins_conditions = [];
		$upd_conditions = [];
		$del_conditionids = [];

		$condition_defaults = array_intersect_key(DB::getDefaults('cep_condition'),
			array_flip(['event_name', 'tag', 'tag_value', 'severity', 'host', 'host_group', 'time_period'])
		);

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('filter', $cep_rule)) {
				continue;
			}

			if ($cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::resetFormulaIds($cep_rule['filter']['formula'], $cep_rule['filter']['conditions']);
			}

			$db_cep_rule = $db_cep_rules !== null ? $db_cep_rules[$cep_rule['cep_ruleid']] : null;
			$db_conditions = $db_cep_rule !== null ? $db_cep_rule['filter']['conditions'] : [];

			foreach ($cep_rule['filter']['conditions'] as &$condition) {
				if ($db_cep_rule !== null && $cep_rule['filter']['evaltype'] != $db_cep_rule['filter']['evaltype']
						&& $db_cep_rule['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$condition += ['formulaid' => ''];
				}

				if ($db_conditions) {
					$condition['cep_conditionid'] = key($db_conditions);

					$upd_condition = DB::getUpdatedValues('cep_condition', $condition + $condition_defaults,
						$db_conditions[$condition['cep_conditionid']]
					);

					if ($upd_condition) {
						$upd_conditions[] = [
							'values' => $upd_condition,
							'where' => ['cep_conditionid' => $condition['cep_conditionid']]
						];
					}

					unset($db_conditions[$condition['cep_conditionid']]);
				}
				else {
					$ins_conditions[] = ['cep_ruleid' => $cep_rule['cep_ruleid']] + $condition;
				}
			}
			unset($condition);

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
			if (!array_key_exists('filter', $cep_rule)) {
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
		if ($db_cep_rules !== null) {
			self::addWindowFieldDefaultsByWindowType($cep_rules, $db_cep_rules);
			self::addWindowFieldDefaultsByGroupByTagField($cep_rules, $db_cep_rules);
		}

		$del_cep_ruleids = [];
		$upd_windows = [];
		$ins_windows = [];

		foreach ($cep_rules as $cep_rule) {
			if ($db_cep_rules === null
					|| $cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				$cep_rule += ['window' => []];
			}

			if (!array_key_exists('window', $cep_rule)) {
				continue;
			}

			if (array_key_exists('tags', $cep_rule['window'])) {
				$cep_rule['window']['tags'] = implode(PHP_EOL, $cep_rule['window']['tags']);
			}

			if ($db_cep_rules !== null) {
				if ($cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
					if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
						$del_cep_ruleids[] = $cep_rule['cep_ruleid'];

						continue;
					}
					elseif ($db_cep_rules[$cep_rule['cep_ruleid']]['window_type'] == CCepRuleHelper::WINDOW_NONE) {
						$ins_windows[] = [
							'cep_ruleid' => $cep_rule['cep_ruleid'],
							'type' => $cep_rule['window_type']
						] + $cep_rule['window'];

						continue;
					}
				}

				$upd_window = DB::getUpdatedValues('cep_rule_window',
					['type' => $cep_rule['window_type']] + $cep_rule['window'],
					['type' => $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']]
						+ $db_cep_rules[$cep_rule['cep_ruleid']]['window']
				);

				if ($upd_window) {
					$upd_windows[] = [
						'values' => $upd_window,
						'where' => ['cep_ruleid' => $cep_rule['cep_ruleid']]
					];
				}
			}
			else {
				if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
					continue;
				}

				$ins_windows[] = [
					'cep_ruleid' => $cep_rule['cep_ruleid'],
					'type' => $cep_rule['window_type']
				] + $cep_rule['window'];
			}
		}

		if ($del_cep_ruleids) {
			DB::delete('cep_rule_window', ['cep_ruleid' => $del_cep_ruleids]);
		}

		if ($upd_windows) {
			DB::update('cep_rule_window', $upd_windows);
		}

		if ($ins_windows) {
			DB::insert('cep_rule_window', $ins_windows, false);
		}
	}

	private static function addWindowFieldDefaultsByWindowType(array &$cep_rules, array $db_cep_rules): void {
		$defaults = self::getWindowDefaults();

		foreach ($cep_rules as &$cep_rule) {
			if ($cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE) {
					$cep_rule += ['window' => []];

					$cep_rule['window'] += $defaults;
				}
				elseif ($db_cep_rules[$cep_rule['cep_ruleid']]['window_type'] == CCepRuleHelper::WINDOW_CAUSE_SYMPTOM) {
					$cep_rule += ['window' => []];

					$cep_rule['window'] += ['event_count_tag' => $defaults['event_count_tag']];
				}
				elseif ($db_cep_rules[$cep_rule['cep_ruleid']]['window_type'] == CCepRuleHelper::WINDOW_PATTERN_MATCH) {
					$cep_rule += ['window' => []];

					$cep_rule['window'] += ['script' => $defaults['script']];
				}
			}
		}
		unset($cep_rule);
	}

	private static function getWindowDefaults(): array {
		return [
			'duration' => DB::getDefault('cep_rule_window', 'duration'),
			'capacity' => DB::getDefault('cep_rule_window', 'capacity'),
			'group_by_host_group' => DB::getDefault('cep_rule_window', 'group_by_host_group'),
			'group_by_host' => DB::getDefault('cep_rule_window', 'group_by_host'),
			'group_by_tags' => DB::getDefault('cep_rule_window', 'group_by_tags'),
			'tags' => [],
			'event_count_tag' => DB::getDefault('cep_rule_window', 'event_count_tag'),
			'script' => DB::getDefault('cep_rule_window', 'script')
		];
	}

	private static function addWindowFieldDefaultsByGroupByTagField(array &$cep_rules, array $db_cep_rules): void {
		foreach ($cep_rules as &$cep_rule) {
			if ($cep_rule['window_type'] == CCepRuleHelper::WINDOW_NONE || !array_key_exists('window', $cep_rule)) {
				continue;
			}

			$db_cep_rule = $db_cep_rules[$cep_rule['cep_ruleid']];

			if ($cep_rule['window']['group_by_tags'] != $db_cep_rule['window']['group_by_tags']
					&& $db_cep_rule['window']['group_by_tags'] == CCepRuleHelper::GROUP_BY_YES) {
				$cep_rule['window'] += ['tags' => []];
			}
		}
		unset($cep_rule);
	}

	private static function updateOperations(array &$cep_rules, ?array $db_cep_rules = null): void {
		$filter_defaults = array_intersect_key(DB::getDefaults('cep_operation'), array_flip(['evaltype', 'formula']))
			+ ['conditions' => []];

		$defaults = array_intersect_key(DB::getDefaults('cep_operation'),
			array_flip(['execute_when', 'type', 'event_name', 'severity', 'suppress_duration', 'tag', 'new_tag',
				'tag_value'
			])
		) + ['filter' => $filter_defaults];

		$del_operationids = [];
		$upd_operations = [];
		$ins_operations = [];

		foreach ($cep_rules as &$cep_rule) {
			if (!array_key_exists('operations', $cep_rule)) {
				continue;
			}

			$db_operations = $db_cep_rules !== null
				? array_column($db_cep_rules[$cep_rule['cep_ruleid']]['operations'], null, 'sortorder')
				: [];

			foreach ($cep_rule['operations'] as &$operation) {
				if (array_key_exists($operation['sortorder'], $db_operations)) {
					$operation['cep_operationid'] = $db_operations[$operation['sortorder']]['cep_operationid'];

					$operation += $defaults;

					$upd_operation =
						DB::getUpdatedValues('cep_operation', $operation, $db_operations[$operation['sortorder']]);

					if ($upd_operation) {
						$upd_operations[] = [
							'values' => $upd_operation,
							'where' => ['cep_operationid' => $operation['cep_operationid']]
						];
					}

					unset($db_operations[$operation['sortorder']]);
				}
				else {
					$ins_operations[] = ['cep_ruleid' => $cep_rule['cep_ruleid']] + $operation;
				}
			}
			unset($operation);

			$del_operationids = array_merge($del_operationids, array_column($db_operations, 'cep_operationid'));
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
							$db_operations[$operation['cep_operationid']]['filter'] = $filter_defaults;
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

	private static function updateOperationFilters(array &$operations, ?array $db_operations): void {
		self::updateOperationFilterConditions($operations, $db_operations);

		$upd_operations = [];

		foreach ($operations as &$operation) {
			if (!array_key_exists('filter', $operation)) {
				continue;
			}

			$db_operation = $db_operations !== null ? $db_operations[$operation['cep_operationid']] : null;

			$upd_operation = [];

			if ($db_operation === null || $operation['filter']['evaltype'] != $db_operation['filter']['evaltype']) {
				$upd_operation['evaltype'] = $operation['filter']['evaltype'];
			}

			if ($operation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$replace_formulaids = $db_operation === null
					|| $operation['filter']['formula'] !== $db_operation['filter']['formula'];

				if (!$replace_formulaids) {
					foreach ($operation['filter']['conditions'] as $condition) {
						$db_condition = $db_operation['filter']['conditions'][$condition['cep_operation_conditionid']];

						if ($condition['formulaid'] != $db_condition['formulaid']) {
							$replace_formulaids = true;

							break;
						}
					}
				}

				if ($replace_formulaids) {
					$upd_operation['formula'] = $operation['filter']['formula'];
					CConditionHelper::replaceFormulaIds($upd_operation['formula'],
						array_column($operation['filter']['conditions'], null, 'cep_operation_conditionid')
					);
				}
			}
			elseif ($db_operation !== null
					&& $db_operation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				$upd_operation['formula'] = DB::getDefault('cep_operation', 'formula');
				$operation['filter']['formula'] = DB::getDefault('cep_operation', 'formula');
			}

			if ($upd_operation) {
				$upd_operations[] = [
					'values' => $upd_operation,
					'where' => ['cep_operationid' => $operation['cep_operationid']]
				];
			}
		}
		unset($operation);

		if ($upd_operations) {
			DB::update('cep_operation', $upd_operations);
		}
	}

	private static function updateOperationFilterConditions(array &$operations, ?array $db_operations): void {
		$ins_conditions = [];
		$upd_conditions = [];
		$del_conditionids = [];

		$condition_defaults = array_intersect_key(DB::getDefaults('cep_operation_condition'),
			array_flip(['tag', 'tag_value'])
		);

		foreach ($operations as &$operation) {
			if (!array_key_exists('filter', $operation)) {
				continue;
			}

			if ($operation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::resetFormulaIds($operation['filter']['formula'], $operation['filter']['conditions']);
			}

			$db_operation = $db_operations !== null ? $db_operations[$operation['cep_operationid']] : null;
			$db_conditions = $db_operation !== null ? $db_operation['filter']['conditions'] : [];

			foreach ($operation['filter']['conditions'] as &$condition) {
				if ($db_operation !== null && $operation['filter']['evaltype'] != $db_operation['filter']['evaltype']
						&& $db_operation['filter']['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
					$condition += ['formulaid' => ''];
				}

				if ($db_conditions) {
					$condition['cep_operation_conditionid'] = key($db_conditions);

					$upd_condition = DB::getUpdatedValues('cep_operation_condition', $condition + $condition_defaults,
						$db_conditions[$condition['cep_operation_conditionid']]
					);

					if ($upd_condition) {
						$upd_conditions[] = [
							'values' => $upd_condition,
							'where' => ['cep_operation_conditionid' => $condition['cep_operation_conditionid']]
						];
					}

					unset($db_conditions[$condition['cep_operation_conditionid']]);
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

		if ($upd_conditions) {
			DB::update('cep_operation_condition', $upd_conditions);
		}

		if ($ins_conditions) {
			$conditionids = DB::insert('cep_operation_condition', $ins_conditions);
		}

		foreach ($operations as &$operation) {
			if (!array_key_exists('filter', $operation)) {
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

	private static function convertWindowTagsToString(array &$cep_rules, ?array &$db_cep_rules = null): void {
		foreach ($cep_rules as &$cep_rule) {
			if (array_key_exists('window', $cep_rule) && array_key_exists('tags', $cep_rule['window'])) {
				$cep_rule['window']['tags'] = implode(PHP_EOL, $cep_rule['window']['tags']);
			}

			if ($db_cep_rules !== null && array_key_exists('window', $db_cep_rules[$cep_rule['cep_ruleid']])
					&& array_key_exists('tags', $db_cep_rules[$cep_rule['cep_ruleid']]['window'])) {
				$db_cep_rules[$cep_rule['cep_ruleid']]['window']['tags'] =
					implode(PHP_EOL, $db_cep_rules[$cep_rule['cep_ruleid']]['window']['tags']);
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

		$db_cep_rules = DBfetchArrayAssoc(DBselect(
			'SELECT cr.cep_ruleid,cr.name,cr.stop,cr.sortorder,cr.description,cr.status,'.
				dbConditionCoalesce('cw.type', CCepRuleHelper::WINDOW_NONE, 'window_type').
			' FROM cep_rule cr'.
			' LEFT JOIN cep_rule_window cw ON cr.cep_ruleid=cw.cep_ruleid'.
			' WHERE '.dbConditionId('cr.cep_ruleid', array_column($cep_rules, 'cep_ruleid'))
		), 'cep_ruleid');

		if (count($cep_rules) != count($db_cep_rules)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
		}

		$cep_rules = $this->extendObjectsByKey($cep_rules, $db_cep_rules, 'cep_ruleid', ['window_type']);

		if (!CApiInputValidator::validate(self::getValidationRules(true), $cep_rules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($cep_rules, $db_cep_rules);
		CConditionHelper::checkFilterFormula($cep_rules);

		self::addAffectedObjects($cep_rules, $db_cep_rules);

		self::validateWindow($cep_rules, $db_cep_rules);
		self::validateOperations($cep_rules, $db_cep_rules);
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
				$cep_ruleids[] = $cep_rule['cep_ruleid'];
				$db_cep_rules[$cep_rule['cep_ruleid']]['filter'] = [];
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => ['cep_ruleid', 'evaltype', 'formula'],
			'cep_ruleids' => $cep_ruleids
		];
		$resource = DBselect(DB::makeSql('cep_rule', $options));

		while ($row = DBfetch($resource)) {
			$db_cep_rules[$row['cep_ruleid']]['filter'] =
				array_diff_key($row, array_flip(['cep_ruleid'])) + ['conditions' => []];
		}

		$options = [
			'output' => ['cep_conditionid', 'cep_ruleid', 'type', 'operator', 'event_name', 'tag', 'tag_value',
				'severity', 'host', 'host_group', 'time_period'
			],
			'filter' => ['cep_ruleid' => $cep_ruleids]
		];
		$resource = DBselect(DB::makeSql('cep_condition', $options));

		while ($row = DBfetch($resource)) {
			$db_cep_rules[$row['cep_ruleid']]['filter']['conditions'][$row['cep_conditionid']] =
				array_diff_key($row, array_flip(['cep_ruleid']));
		}

		foreach ($cep_ruleids as $cep_ruleid) {
			$db_filter = &$db_cep_rules[$cep_ruleid]['filter'];

			if ($db_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::sortConditionsByFormula($db_filter['conditions'], $db_filter['formula']);

				CConditionHelper::addFormulaIds($db_filter['conditions'], $db_filter['formula']);
				CConditionHelper::replaceConditionIds($db_filter['formula'], $db_filter['conditions']);
			}
			else {
				CConditionHelper::sortCepRuleConditions($db_filter['conditions']);

				foreach ($db_filter['conditions'] as &$condition) {
					$condition['formulaid'] = '';
				}
				unset($condition);
			}

			unset($db_filter);
		}
	}

	private static function addAffectedWindow(array $cep_rules, array &$db_cep_rules): void {
		$cep_ruleids = [];

		foreach ($cep_rules as $cep_rule) {
			if (array_key_exists('window', $cep_rule)
					|| $cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				if ($db_cep_rules[$cep_rule['cep_ruleid']]['window_type'] == CCepRuleHelper::WINDOW_NONE) {
					$db_cep_rules[$cep_rule['cep_ruleid']]['window'] = self::getWindowDefaults();
				}
				else {
					$cep_ruleids[] = $cep_rule['cep_ruleid'];
				}
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => array_merge(['cep_ruleid'], self::WINDOW_OUTPUT_FIELDS),
			'filter' => ['cep_ruleid' => $cep_ruleids]
		];
		$resource = DBselect(DB::makeSql('cep_rule_window', $options));

		while ($row = DBfetch($resource)) {
			$row['tags'] = $row['tags'] !== '' ? explode("\n", $row['tags']) : [];

			$db_cep_rules[$row['cep_ruleid']]['window'] = array_diff_key($row, array_flip(['cep_ruleid']));
		}
	}

	private static function addAffectedOperations(array $cep_rules, array &$db_cep_rules): void {
		$cep_ruleids = [];

		foreach ($cep_rules as $cep_rule) {
			if (array_key_exists('operations', $cep_rule)
					|| $cep_rule['window_type'] != $db_cep_rules[$cep_rule['cep_ruleid']]['window_type']) {
				$cep_ruleids[] = $cep_rule['cep_ruleid'];
				$db_cep_rules[$cep_rule['cep_ruleid']]['operations'] = [];
			}
		}

		if (!$cep_ruleids) {
			return;
		}

		$options = [
			'output' => array_merge(['cep_operationid', 'cep_ruleid', 'evaltype', 'formula'],
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
			$db_operation['filter'] = [
				'evaltype' => $db_operation['evaltype'],
				'formula' => $db_operation['formula'],
				'conditions' => []
			];

			unset($db_operation['evaltype'], $db_operation['formula']);
		}
		unset($db_operation);

		$options = [
			'output' => ['cep_operation_conditionid', 'cep_operationid', 'type', 'tag', 'operator', 'tag_value'],
			'filter' => ['cep_operationid' => array_keys($db_operations)]
		];
		$resource = DBselect(DB::makeSql('cep_operation_condition', $options));

		while ($row = DBfetch($resource)) {
			$db_operations[$row['cep_operationid']]['filter']['conditions'][$row['cep_operation_conditionid']] =
				array_diff_key($row, array_flip(['cep_operationid']));
		}

		foreach ($db_operations as &$db_operation) {
			$db_filter = &$db_operation['filter'];

			if ($db_filter['evaltype'] == CONDITION_EVAL_TYPE_EXPRESSION) {
				CConditionHelper::sortConditionsByFormula($db_filter['conditions'], $db_filter['formula']);

				CConditionHelper::addFormulaIds($db_filter['conditions'], $db_filter['formula']);
				CConditionHelper::replaceConditionIds($db_filter['formula'], $db_filter['conditions']);
			}
			else {
				CConditionHelper::sortCepRuleOperationConditions($db_filter['conditions']);

				foreach ($db_filter['conditions'] as &$condition) {
					$condition['formulaid'] = '';
				}
				unset($condition);
			}

			unset($db_filter);
		}
		unset($db_operation);
	}

	private static function updateForce(array $cep_rules, ?array &$db_cep_rules = null): void {
		$upd_cep_rules = [];

		foreach ($cep_rules as $cep_rule) {
			$upd_cep_rule = DB::getUpdatedValues('cep_rule', $cep_rule, $db_cep_rules[$cep_rule['cep_ruleid']]);

			if ($upd_cep_rule) {
				$upd_cep_rules[] = [
					'values' => $upd_cep_rule,
					'where' => ['cep_ruleid' => $cep_rule['cep_ruleid']]
				];
			}
		}

		if ($upd_cep_rules) {
			DB::update('cep_rule', $upd_cep_rules);
		}

		self::updateFilters($cep_rules, $db_cep_rules);
		self::updateWindow($cep_rules, $db_cep_rules);
		self::updateOperations($cep_rules, $db_cep_rules);

		self::convertWindowTagsToString($cep_rules, $db_cep_rules);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_CEP_RULE, $cep_rules, $db_cep_rules);
	}

	public function delete(array $cep_ruleids): array {
		$this->validateDelete($cep_ruleids, $db_cep_rules);

		DB::delete('cep_condition', ['cep_ruleid' => $cep_ruleids]);
		DB::delete('cep_rule_window', ['cep_ruleid' => $cep_ruleids]);
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

	public function resetTimeWindows(array $cep_rule): array {
		$this->validateResetTimeWindows($cep_rule);

		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$zabbix_server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT,
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::CONNECT_TIMEOUT)),
			timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::SOCKET_TIMEOUT)), ZBX_SOCKET_BYTES_LIMIT
		);

		$result = $zabbix_server->resetCepRule($cep_rule, self::getAuthIdentifier());

		if ($result === false) {
			self::exception(ZBX_API_ERROR_INTERNAL, $zabbix_server->getError());
		}

		return $cep_rule;
	}

	private function validateResetTimeWindows(array $cep_rule): void {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'cep_ruleid' =>	['type' => API_ID, 'flags' => API_REQUIRED]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $cep_rule, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_cep_rule = DB::select('cep_rule', [
			'output' =>	[],
			'cep_ruleids' => $cep_rule['cep_ruleid']
		]);

		if (!$db_cep_rule) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_('No permissions to referred object or it does not exist!')
			);
		}
	}
}
