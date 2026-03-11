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

		foreach ($ceprules as &$ceprule) {
			$ceprule['window_type'] = array_key_exists('window', $ceprule)
				? ZBX_CEP_WINDOW_NONE
				: $ceprule['window']['type'];
		}
		unset($ceprule);

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
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'conditions' => 	['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => implode(',', [ZBX_CEP_WINDOW_TAG_MATCH])], 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => self::getConditionFields()],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'window' =>			['type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'type' =>			['type' => API_INT32, 'in' => implode(',', [
										ZBX_CEP_WINDOW_NONE,
										ZBX_CEP_WINDOW_SIMPLE,
										ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
										ZBX_CEP_WINDOW_TAG_MATCH,
										ZBX_CEP_WINDOW_PATTERN_MATCH
									]), 'default' => ZBX_CEP_WINDOW_NONE],
				'duration' => 		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [
											ZBX_CEP_WINDOW_SIMPLE,
											ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
											ZBX_CEP_WINDOW_TAG_MATCH,
											ZBX_CEP_WINDOW_PATTERN_MATCH
										])], 'type' => API_INT32, 'in' => '1:'.ZBX_MAX_INT32],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'capacity' => 		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [
											ZBX_CEP_WINDOW_SIMPLE,
											ZBX_CEP_WINDOW_CAUSE_SYMPTOM,
											ZBX_CEP_WINDOW_TAG_MATCH,
											ZBX_CEP_WINDOW_PATTERN_MATCH
										])], 'type' => API_INT32, 'in' => '0:'.ZBX_MAX_INT32],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'script' => 		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_WINDOW_PATTERN_MATCH])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'script')],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'group_by' => 		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [
											ZBX_CEP_WINDOW_SIMPLE,
											ZBX_CEP_WINDOW_PATTERN_MATCH
										])], 'type' => API_INT32, 'in' => '0:'.(ZBX_CEP_GROUP_BY_HOSTGROUP | ZBX_CEP_GROUP_BY_HOST | ZBX_CEP_GROUP_BY_TAG), 'default' => 0],
										['if' => ['field' => 'type', 'in' => implode(',', [
											ZBX_CEP_WINDOW_CAUSE_SYMPTOM
										])], 'type' => API_INT32, 'in' => ZBX_CEP_GROUP_BY_HOSTGROUP.':'.(ZBX_CEP_GROUP_BY_HOSTGROUP | ZBX_CEP_GROUP_BY_HOST | ZBX_CEP_GROUP_BY_TAG), 'flags' => API_REQUIRED],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'group_tag' => 		['type' => API_MULTIPLE, 'rules' => [
										['if' => static fn(array $data): bool => array_key_exists('group_by', $data) && $data['group_by'] & ZBX_CEP_GROUP_BY_TAG, 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window', 'group_tag')],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'symptom_num_tag' => ['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'type', 'in' => implode(',', [ZBX_CEP_WINDOW_CAUSE_SYMPTOM])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window', 'symptom_num_tag')],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'evaltype' => 		['type' => API_INT32, 'in' => implode(',', [
										CONDITION_EVAL_TYPE_AND_OR,
										CONDITION_EVAL_TYPE_AND,
										CONDITION_EVAL_TYPE_OR,
										CONDITION_EVAL_TYPE_EXPRESSION
									]), 'default' => CONDITION_EVAL_TYPE_AND_OR],
				'formula' =>		['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' =>implode(',', [CONDITION_EVAL_TYPE_EXPRESSION])], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'formula')],
										['else' => true, 'type' => API_UNEXPECTED]
				]],
				'conditions' => 	['type' => API_MULTIPLE, 'rules' => [
										['if' => ['field' => 'evaltype', 'in' => implode(',', [ZBX_CEP_WINDOW_TAG_MATCH])], 'type' => API_OBJECT, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['formulaid']], 'fields' => self::getWindowConditionFields()],
										['else' => true, 'type' => API_UNEXPECTED]
				]]
			]],
			'operations' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY | API_NORMALIZE, 'fields' => [
				'when' => 			['type' => API_INT32, 'in' => implode(',', [
										ZBX_CEP_OP_ON_EVENT_OCCURRED,
										ZBX_CEP_OP_ON_EVENT_EVICTED,
										ZBX_CEP_OP_ON_WINDOW_CLOSED
									]), 'default' => ZBX_CEP_OP_ON_EVENT_OCCURRED],
				'evaltype' => 		['type' => API_INT32, 'in'=> implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND])],
				'tags' =>			['type' => API_OBJECTS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null, 'fields' => [
					'tag' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation_tag', 'tag')],
					'operator' =>		['type' => API_INT32, 'in' => implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL])],
					'value' =>			['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_operation_tag', 'value')]
				]],
				'type' =>			['type' => API_INT32, 'in' => implode(',', self::OPERATIONS), 'default' => ZBX_CEP_OP_SET_NAME],
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
											['else' => true, 'type' => API_UNEXPECTED]
				]],
				'value2_str' =>			['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'type', implode(',', [
												ZBX_CEP_OP_ADD_TAG,
												ZBX_CEP_OP_SET_TAG,
												ZBX_CEP_OP_SET_TAG_VALUE,
												ZBX_CEP_OP_RENAME_TAG
											])], 'type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_operation', 'value2_str')],
											['else' => true, 'type' => API_UNEXPECTED]
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
										['else' => true, 'type' => API_UNEXPECTED]
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

		self::checkDuplicates($ceprules);
		self::checkFormula($ceprules);
		self::checkWindowFormula($ceprules);
	}

	private static function getConditionFields(): array {
		return [
			'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED],
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
								['else' => true, 'type' => API_UNEXPECTED]
			]],
			'value2_str' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [ZBX_CEP_CONDITION_TAG_VALUE])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_condition', 'value2_str')],
								['else' => true, 'type' => API_UNEXPECTED]
			]],
			'value_int' =>	['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [ZBX_CEP_CONDITION_SEVERITY])], 'type' => API_INT32],
								['else' => true, 'type' => API_UNEXPECTED]
			]]
		];
	}

	private static function getWindowConditionFields(): array {
		return [
			'formulaid' =>	['type' => API_COND_FORMULAID, 'flags' => API_REQUIRED],
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
								['else' => true, 'type' => API_UNEXPECTED]
			]],
			'name' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_window_condition', 'name')],
			'value' =>		['type' => API_MULTIPLE, 'rules' => [
								['if' => ['field' => 'type', implode(',', [
									ZBX_CEP_WINDOW_CONDITION_TAG_PAIR,
									ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE
								])], 'type' => API_STRING_UTF8, 'length' => DB::getFieldLength('cep_window_condition', 'value')],
								['else' => true, 'type' => API_UNEXPECTED]
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

	private static function updateConditions(array $ceprules, ?array $db_actions = null): void {
	}

	private static function updateWindow(array $ceprules, ?array $db_actions = null): void {
	}

	private static function updateOperations(array &$ceprules, ?array $db_actions = null): void {

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
