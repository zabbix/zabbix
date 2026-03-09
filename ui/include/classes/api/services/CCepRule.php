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

	public function get($options = []): array|string {
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

		foreach ($ceprules as $action) {
			if (array_key_exists('filter', $action)) {
				$action['evaltype'] = $action['filter']['evaltype'];
			}

			$ins_ceprules[] = $action;
		}

		$cepruleids = DB::insert('cep_rule', $ins_ceprules);

		foreach ($ceprules as $index => &$action) {
			$action['cep_ruleid'] = $cepruleids[$index];
		}
		unset($action);

		self::updateFilter($ceprules);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_CEP_RULE, $ceprules);

		return ['cep_ruleids' => $cepruleids];
	}

	private function validateCreate(array &$ceprules): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name'], ['sortorder']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('cep_rule', 'name')],
			'description' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('items', 'description')],
			'window' =>			['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [ZBX_CEP_WINDOW_NONE, ZBX_CEP_WINDOW_SIMPLE, ZBX_CEP_WINDOW_CAUSE_SYMPTOM, ZBX_CEP_WINDOW_TAG_MATCH, ZBX_CEP_WINDOW_PATTERN_MATCH])],
			'evaltype' =>		['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_AND, CONDITION_EVAL_TYPE_OR, CONDITION_EVAL_TYPE_EXPRESSION])],
			'formula' =>		['type' => API_MULTIPLE, 'rules' => [
									['if' => ['field' => 'evaltype', 'in' => CONDITION_EVAL_TYPE_EXPRESSION], 'type' => API_COND_FORMULA, 'flags' => API_REQUIRED, 'length' => DB::getFieldLength('cep_rule', 'formula')],
									['else' => true, 'type' => API_UNEXPECTED]
			]],
			'status' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_STATUS_ENABLED, ZBX_CEP_STATUS_DISABLED])],
			'stop' =>			['type' => API_INT32, 'in' => implode(',', [ZBX_CEP_EXECUTION_CONTINUE, ZBX_CEP_EXECUTION_STOP])]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $ceprules, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($ceprules);
		self::checkFilter($ceprules);
		self::checkOperations($ceprules);
	}

	private static function checkDuplicates(array $ceprules, ?array $db_cepruleids = null): void {
		$names = [];

		foreach ($ceprules as $action) {
			if ($db_cepruleids === null || $action['name'] !== $db_cepruleids[$action['cep_ruleid']]['name']) {
				$names[] = $action['name'];
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
