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
	protected $sortColumns = ['cep_ruleid', 'name', 'sortorder', 'status'];

	private const OPERATIONS = [
		ZBX_CEP_OP_SET_NAME,
		ZBX_CEP_OP_CLOSE,
		ZBX_CEP_OP_DISCARD,
		ZBX_CEP_OP_SET_SEVERITY,
		ZBX_CEP_OP_INCREASE_SEVERITY,
		ZBX_CEP_OP_DECREASE_SEVERITY,
		ZBX_CEP_OP_SUPPRESS,
		ZBX_CEP_OP_COPY,
		ZBX_CEP_OP_ADD_TAG,
		ZBX_CEP_OP_SET_TAG,
		ZBX_CEP_OP_SET_TAG_VALUE,
		ZBX_CEP_OP_INCREASE_TAG_VALUE,
		ZBX_CEP_OP_DECREASE_TAG_VALUE,
		ZBX_CEP_OP_RENAME_TAG,
		ZBX_CEP_OP_REMOVE_TAG
	];

	public function get($options = []): array|string {
	}

	private static function addRelatedObjects(array $options, array $result): void {

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

		$cepruleids = DB::insert('cep_rule', $ceprules);

		foreach ($ceprules as $index => &$ceprule) {
			$ceprule['cep_ruleid'] = $cepruleids[$index];
		}
		unset($ceprule);

		self::updateConditions($ceprules);
		self::updateWindow($ceprules);
		self::updateOperations($ceprules);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CEP_RULE, $ceprules);

		return ['cep_ruleids' => $cepruleids];
	}

	private function validateCreate(array &$ceprules): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'name')],
			'evaltype' => 		['type' => API_INT32, 'in' => implode(',', [
									CONDITION_EVAL_TYPE_AND_OR,
									CONDITION_EVAL_TYPE_AND,
									CONDITION_EVAL_TYPE_OR,
									CONDITION_EVAL_TYPE_EXPRESSION
								]), 'default' => CONDITION_EVAL_TYPE_AND_OR],
			'formula' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'formula')]
			]],
			'conditions' => 	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [ZBX_CEP_WINDOW_TAG_MATCH])], 'type' => API_OBJECT, 'flags' => API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
										'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED]
									] + self::getConditionFields()],
									['else' => true, 'type' => API_OBJECT, 'flags' => API_NORMALIZE, 'fields' => self::getConditionFields()]
			]],
			'window_type' =>	['type' => API_INT32, 'in' => implode(',', [
									ZBX_CEP_WINDOW_NONE,
									ZBX_CEP_WINDOW_SIMPLE,
									ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
									ZBX_CEP_WINDOW_TAG_MATCH,
									ZBX_CEP_WINDOW_PATTERN_MATCH
								]), 'default' => ZBX_CEP_WINDOW_NONE],
			'window' =>			['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => []],
			'operations' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
				'when' => 			['type' => API_INT32, 'in' => implode(',', [
										ZBX_CEP_OP_ON_EVENT_OCCURRED,
										ZBX_CEP_OP_ON_EVENT_EVICTED,
										ZBX_CEP_OP_ON_WINDOW_CLOSED,
										ZBX_CEP_WHEN_TAGS_CORRELATED,
										ZBX_CEP_WHEN_PATTERN_MATCHED
									]), 'default' => ZBX_CEP_OP_ON_EVENT_OCCURRED],
				'tags' =>			['type' => API_OBJECTS, 'flags' => API_NORMALIZE, 'fields' => [
					'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_tag', 'tag')],
					'operator' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
					'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation_tag', 'value')]
				]],
				'type' =>			['type' => API_INT32, 'in' => implode(',', self::OPERATIONS), 'default' => ZBX_CEP_OP_SET_NAME],
				'evaltype' => 		['type' => API_INT32, 'in'=> implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND])],
				'value1_str' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', implode(',', [
												ZBX_CEP_OP_ADD_TAG,
												ZBX_CEP_OP_SET_TAG,
												ZBX_CEP_OP_SET_TAG_VALUE,
												ZBX_CEP_OP_INCREASE_TAG_VALUE,
												ZBX_CEP_OP_DECREASE_TAG_VALUE,
												ZBX_CEP_OP_RENAME_TAG,
												ZBX_CEP_OP_REMOVE_TAG
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'value1_str')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'value1_str')]
				]],
				'value2_str' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', implode(',', [
												ZBX_CEP_OP_ADD_TAG,
												ZBX_CEP_OP_SET_TAG,
												ZBX_CEP_OP_SET_TAG_VALUE,
												ZBX_CEP_OP_RENAME_TAG
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'value2_str')],
											['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_operation', 'value1_str')]
				]],
				'value_int' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', implode(',', [ZBX_CEP_OP_SET_SEVERITY])], 'type' => API_INT32, 'in' => implode(',', [
											TRIGGER_SEVERITY_NOT_CLASSIFIED,
											TRIGGER_SEVERITY_INFORMATION,
											TRIGGER_SEVERITY_WARNING,
											TRIGGER_SEVERITY_AVERAGE,
											TRIGGER_SEVERITY_HIGH,
											TRIGGER_SEVERITY_DISASTER
										])],
										['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_operation', 'value_int')]
				]],
				'sortorder' =>		['type' => API_INT32]
			]],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP])],
			'sortorder' =>		['type' => API_INT32],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_rule', 'description')],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $ceprules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::validateWindow($ceprules);

		self::checkDuplicates($ceprules);
		self::checkFormula($ceprules);
		self::checkWindowFormula($ceprules);
	}

	private static function getConditionFields(): array {
		return [
			'type' =>		['type' => API_INT32, 'in' => implode(',', [
								ZBX_CEP_CONDITION_EVENT_NAME,
								ZBX_CEP_CONDITION_TAG_NAME,
								ZBX_CEP_CONDITION_TAG_VALUE,
								ZBX_CEP_CONDITION_SEVERITY,
								ZBX_CEP_CONDITION_HOST,
								ZBX_CEP_CONDITION_HOST_GROUP,
								ZBX_CEP_CONDITION_TIME_PERIOD
							]), 'default' => ZBX_CEP_CONDITION_EVENT_NAME],
			'operator' =>	['type' => API_INT32, 'in' => implode(',', [
								CONDITION_OPERATOR_EQUAL,
								CONDITION_OPERATOR_NOT_EQUAL,
								CONDITION_OPERATOR_LIKE,
								CONDITION_OPERATOR_NOT_LIKE,
								CONDITION_OPERATOR_IN,
								CONDITION_OPERATOR_MORE_EQUAL,
								CONDITION_OPERATOR_LESS_EQUAL,
								CONDITION_OPERATOR_NOT_IN,
								CONDITION_OPERATOR_NOT_EXISTS
							]), 'default' => CONDITION_OPERATOR_EQUAL],
			'value1_str' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'operator', 'in' => implode(',', [
									ZBX_CEP_CONDITION_EVENT_NAME,
									ZBX_CEP_CONDITION_TAG_NAME,
									ZBX_CEP_CONDITION_TAG_VALUE,
									ZBX_CEP_CONDITION_HOST,
									ZBX_CEP_CONDITION_HOST_GROUP,
									ZBX_CEP_CONDITION_TIME_PERIOD
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_condition', 'value1_str')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'value1_str')]
			]],
			'value2_str' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [ZBX_CEP_CONDITION_TAG_VALUE])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_condition', 'value2_str')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_condition', 'value2_str')]
			]],
			'value_int' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [ZBX_CEP_CONDITION_SEVERITY])], 'type' => API_INT32],
								['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_condition', 'value_int')]
			]]
		];
	}

	private static function validateWindow(array &$ceprules): void {
		foreach ($ceprules as $i => &$ceprule) {
			$api_input_rules = ['type' => API_OBJECT, 'flags' => API_ALLOW_UNEXPECTED, 'fields' => [
				'window' => $ceprule['window_type'] == ZBX_CEP_WINDOW_NONE
					? ['type' => API_OBJECT, 'fields' => [], 'unset' => true]
					: ['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => self::getWindowFields($ceprule['window_type'])]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $ceprule, '/'.($i + 1), $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
		unset($ceprule);
	}

	private static function getWindowFields(int $window_type): array {
		$fields = [];

		if (in_array($window_type, [
			ZBX_CEP_WINDOW_SIMPLE,
			ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
			ZBX_CEP_WINDOW_TAG_MATCH,
			ZBX_CEP_WINDOW_PATTERN_MATCH
		])) {
			$fields['duration'] = ['type' => API_INT32, 'in' => '1:'.ZBX_MAX_INT32, 'default' => 1];
			$fields['capacity'] = ['type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32];
		}

		if ($window_type == ZBX_CEP_WINDOW_TAG_MATCH) {
			$fields += [
				'evaltype' =>	['type' => API_INT32, 'in' => implode(',', [
									CONDITION_EVAL_TYPE_AND_OR,
									CONDITION_EVAL_TYPE_AND,
									CONDITION_EVAL_TYPE_OR,
									CONDITION_EVAL_TYPE_EXPRESSION
								]), 'default' => CONDITION_EVAL_TYPE_AND_OR],
				'formula' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' =>implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
									['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_rule', 'value1_str')]
				]],
				'conditions' =>	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => [
										'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED | API_NOT_EMPTY]
									] + self::getWindowConditionFields()],
									['else' => true, 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => self::getWindowConditionFields()]
				]]
			];
		}

		if ($window_type == ZBX_CEP_WINDOW_PATTERN_MATCH) {
			$fields['script'] = ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'script')];
		}

		if (in_array($window_type, [ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_PATTERN_MATCH])) {
			$fields['group_by'] = ['type' => API_INT32, 'in' => '0:'.(ZBX_CEP_GROUP_BY_HOSTGROUP | ZBX_CEP_GROUP_BY_HOST | ZBX_CEP_GROUP_BY_TAG), 'default' => DB::getDefault('cep_window', 'group_by')];
		}
		elseif ($window_type == ZBX_CEP_WINDOW_CAUSE_SYMPTOM) {
			$fields['group_by'] = ['type' => API_INT32, 'in' => ZBX_CEP_GROUP_BY_HOSTGROUP.':'.(ZBX_CEP_GROUP_BY_HOSTGROUP | ZBX_CEP_GROUP_BY_HOST | ZBX_CEP_GROUP_BY_TAG), 'flags' => API_REQUIRED];
		}

		$fields += [
			'group_tag' =>			['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => array_key_exists('group_by', $data) && $data['group_by'] & ZBX_CEP_GROUP_BY_TAG, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'group_tag')],
										['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window', 'group_tag')]
			]],
			'symptom_num_tag' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window', 'symptom_num_tag')]
		];

		return $fields;
	}

	private static function getWindowConditionFields(): array {
		return [
			'type' =>		['type' => API_INT32, 'in' => implode(',', [
								ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG,
								ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
							]), 'default' => ZBX_CEP_WINDOW_CONDITION_TAG_PAIR],
			'operator' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG
								])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL])],
								['if' => ['field' => 'type', implode(',', [
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
								])], 'type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
								['else' => true, 'type' => API_INT32, 'in' => DB::getDefault('cep_window_condition', 'operator')]
			]],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window_condition', 'name')],
			'value' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
								])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window_condition', 'value')],
								['else' => true, 'type' => API_STRING_UTF8, 'in' => DB::getDefault('cep_window_condition', 'value')]
			]]
		];
	}

	private static function checkDuplicates(array $ceprules, ?array $db_cepruleids = null): void {
		$names = [];

		foreach ($ceprules as $ceprule) {
			if ($db_cepruleids === null || $ceprule['name'] !== $db_cepruleids[$ceprule['cep_ruleid']]['name']) {
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

	private static function checkFormula(array $ceprules): void {
		$condition_formula_parser = new CConditionFormula();

		foreach ($ceprules as $i => $ceprule) {
			if (!array_key_exists('formula', $ceprule)) {
				continue;
			}

			$path = '/'.($i + 1);
			$condition_formula_parser->parse($ceprule['formula']);
			$constants = array_column($condition_formula_parser->constants, 'value', 'value');

			if (count($ceprule['formula']) != count($constants)) {
				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Invalid parameter "%1$s": %2$s.', $path.'/conditions', _('incorrect number of conditions'))
				);
			}

			foreach ($ceprule['conditions'] as $j => $condition) {
				if (!array_key_exists($condition['formulaid'], $constants)) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _s('Invalid parameter "%1$s": %2$s.',
						$path.'/conditions/'.($j + 1).'/formulaid', _('an identifier is not defined in the formula')
					));
				}
			}
		}
	}

	private static function updateConditions(array $ceprules, ?array $db_ceprules = null): void {
	}

	private static function updateWindow(array &$ceprules, ?array $db_ceprules = null): void {
		$del_windowids = [];
		$ins_windows = [];
		$upd_windows = [];

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
					$ins_windows = ['cep_ruleid' => $cepruleid] + $ceprule['window'];
				}
				else {
					$db_window = $db_ceprules[$cepruleid]['window'];
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
	}

	private static function updateOperations(array &$ceprules, ?array $db_ceprules = null): void {

	}

	private static function addAffectedObjects(array $ceprules, ?array &$db_ceprules = null): void {
	}

	public function delete(array $cepruleids): array {
		$this->validateDelete($cepruleids, $db_cepruleids);

		DB::delete('cep_rule', ['cep_ruleid' => $cepruleids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_CEP_RULE, $db_cepruleids);

		return ['cep_ruleids' => $cepruleids];
	}

	private function validateDelete(array &$cepruleids, ?array &$db_cepruleids): void {
		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $cepruleids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_cepruleids = $this->get([
			'output' => ['cep_ruleid', 'name'],
			'cep_ruleids' => $cepruleids
		]);

		if (count($db_cepruleids) != count($cepruleids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
	}
}
