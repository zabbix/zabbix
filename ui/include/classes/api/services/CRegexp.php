<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 * Class containing methods for operations with regular expressions.
 */
class CRegexp extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'create' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_SUPER_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_SUPER_ADMIN]
	];

	protected $tableName = 'regexps';
	protected $tableAlias = 'r';
	protected $sortColumns = ['regexpid', 'name'];

	/**
	 * @param array $options
	 *
	 * @throws APIException if the input is invalid.
	 *
	 * @return array|int
	 */
	public function get(array $options = []) {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'regexp', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'regexpids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['regexpid', 'name']],
			'search' =>					['type' => API_FILTER, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => ['name', 'test_string']],
			'searchByAny' =>			['type' => API_BOOLEAN, 'default' => false],
			'startSearch' =>			['type' => API_FLAG, 'default' => false],
			'excludeSearch' =>			['type' => API_FLAG, 'default' => false],
			'searchWildcardsEnabled' =>	['type' => API_BOOLEAN, 'default' => false],
			// output
			'output' =>					['type' => API_OUTPUT, 'in' => implode(',', ['regexpid', 'name', 'test_string']), 'default' => API_OUTPUT_EXTEND],
			'selectExpressions' =>		['type' => API_OUTPUT, 'flags' => API_ALLOW_NULL, 'in' => implode(',', ['expression', 'expression_type', 'exp_delimiter', 'case_sensitive']), 'default' => null],
			'countOutput' =>			['type' => API_FLAG, 'default' => false],
			// sort and limit
			'sortfield' =>				['type' => API_STRINGS_UTF8, 'flags' => API_NORMALIZE, 'in' => implode(',', $this->sortColumns), 'uniq' => true, 'default' => []],
			'sortorder' =>				['type' => API_SORTORDER, 'default' => []],
			'limit' =>					['type' => API_INT32, 'flags' => API_ALLOW_NULL, 'in' => '1:'.ZBX_MAX_INT32, 'default' => null],
			// flags
			'preservekeys' =>			['type' => API_BOOLEAN, 'default' => false]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $options, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_regexs = [];

		$result = DBselect($this->createSelectQuery($this->tableName, $options));

		while ($row = DBfetch($result)) {
			if ($options['countOutput']) {
				return $row['rowscount'];
			}

			$db_regexs[$row['regexpid']] = $row;
		}

		if ($db_regexs) {
			$db_regexs = $this->addRelatedObjects($options, $db_regexs);
			$db_regexs = $this->unsetExtraFields($db_regexs, ['regexpid'], $options['output']);

			if (!$options['preservekeys']) {
				$db_regexs = array_values($db_regexs);
			}
		}

		return $db_regexs;
	}

	/**
	 * @param array $regexs
	 *
	 * @return array
	 */
	public function create(array $regexs): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'regexp', __FUNCTION__)
			);
		}

		$this->validateCreate($regexs);

		$regexids = DB::insert('regexps', $regexs);

		foreach ($regexs as $index => &$regex) {
			$regex['regexpid'] = $regexids[$index];
		}
		unset($regex);

		$this->updateExpressions($regexs, __FUNCTION__);

		self::addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_REGEXP, $regexs);

		return ['regexpids' => $regexids];
	}

	/**
	 * @param array $regexs
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateCreate(array &$regexs): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['name']], 'fields' => [
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
			'test_string' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('regexps', 'test_string')],
			'expressions' =>		['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['expression_type', 'expression']], 'fields' => [
				'expression_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
				'expression' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])], 'type' => API_REGEX, 'length' => DB::getFieldLength('expressions', 'expression')],
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('expressions', 'expression')]
				]],
				'exp_delimiter' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_ANY_INCLUDED])], 'type' => API_STRING_UTF8, 'in' => '\\,,.,/'],
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])], 'type' => API_STRING_UTF8, 'in' => '']
				]],
				'case_sensitive' =>		['type' => API_INT32, 'in' => '0,1']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $regexs, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		self::checkDuplicates($regexs);
	}

	/**
	 * Check for unique global regular expression names.
	 *
	 * @param array      $regexs
	 * @param array|null $db_regexs
	 *
	 * @throws APIException if global regular expression names are not unique.
	 */
	protected static function checkDuplicates(array $regexs, ?array $db_regexs = null): void {
		$names = [];

		foreach ($regexs as $regex) {
			if (!array_key_exists('name', $regex)) {
				continue;
			}

			if ($db_regexs === null || $regex['name'] !== $db_regexs[$regex['regexpid']]['name']) {
				$names[] = $regex['name'];
			}
		}

		if (!$names) {
			return;
		}

		$duplicate = DBfetch(DBselect('SELECT r.name FROM regexps r WHERE '.dbConditionString('r.name', $names), 1));

		if ($duplicate) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Regular expression "%1$s" already exists.', $duplicate['name'])
			);
		}
	}

	/**
	 * Update table "expressions" and populate regex.expressions by "expressionid" property.
	 *
	 * @param array $regexs
	 * @param string $method
	 */
	protected function updateExpressions(array &$regexs, string $method, ?array $db_regexs = null): void {
		$ins_expressions = [];
		$upd_expressions = [];
		$del_expressionids = [];

		foreach ($regexs as &$regex) {
			if (!array_key_exists('expressions', $regex)) {
				continue;
			}

			$db_expressions = ($method === 'update') ? $db_regexs[$regex['regexpid']]['expressions'] : [];

			foreach ($regex['expressions'] as &$expression) {
				$db_expression = current(
					array_filter($db_expressions, function (array $db_expression) use ($expression): bool {
						return ($expression['expression_type'] == $db_expression['expression_type']
							&& $expression['expression'] === $db_expression['expression']);
					})
				);

				/**
				 * Set default value for expression delimiter.
				 * Because Zabbix agent 2 cannot work with regular expression when delimiter is empty.
				 * Bugfix for Zabbix agent 2 5.0.22 and less.
				 */
				$expression += ['exp_delimiter' => ','];

				if ($db_expression) {
					$expression['expressionid'] = $db_expression['expressionid'];
					unset($db_expressions[$db_expression['expressionid']]);

					$upd_expression = DB::getUpdatedValues('expressions', $expression, $db_expression);

					if ($upd_expression) {
						$upd_expressions[] = [
							'values' => $upd_expression,
							'where' => ['expressionid' => $db_expression['expressionid']]
						];
					}
				}
				else {
					$ins_expressions[] = ['regexpid' => $regex['regexpid']] + $expression;
				}

				/**
				 * Unset exp_delimiter from array for audit log records.
				 */
				if ($expression['expression_type'] != EXPRESSION_TYPE_ANY_INCLUDED) {
					unset($expression['exp_delimiter']);
				}
			}
			unset($expression);

			$del_expressionids = array_merge($del_expressionids, array_keys($db_expressions));
		}
		unset($regex);

		if ($del_expressionids) {
			DB::delete('expressions', ['expressionid' => $del_expressionids]);
		}

		if ($upd_expressions) {
			DB::update('expressions', $upd_expressions);
		}

		if ($ins_expressions) {
			$expressionids = DB::insert('expressions', $ins_expressions);
		}

		foreach ($regexs as &$regex) {
			if (!array_key_exists('expressions', $regex)) {
				continue;
			}

			foreach ($regex['expressions'] as &$expression) {
				if (!array_key_exists('expressionid', $expression)) {
					$expression['expressionid'] = array_shift($expressionids);
				}
			}
			unset($expression);
		}
		unset($regex);
	}

	/**
	 * @param array $regexs
	 *
	 * @return array
	 */
	public function update(array $regexs): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'regexp', __FUNCTION__)
			);
		}

		$this->validateUpdate($regexs, $db_regexs);

		$upd_regexs = [];
		foreach ($regexs as $regex) {
			$db_regex = $db_regexs[$regex['regexpid']];

			$upd_regex = DB::getUpdatedValues('regexps', $regex, $db_regex);

			if ($upd_regex) {
				$upd_regexs[] = [
					'values' => $upd_regex,
					'where' => ['regexpid' => $regex['regexpid']]
				];
			}
		}

		if ($upd_regexs) {
			DB::update('regexps', $upd_regexs);
		}

		$this->updateExpressions($regexs, __FUNCTION__, $db_regexs);

		self::addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_REGEXP, $regexs, $db_regexs);

		return ['regexpids' => array_column($regexs, 'regexpid')];
	}

	/**
	 * @param array $regexs
	 * @param array $db_regexs
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateUpdate(array &$regexs, ?array &$db_regexs = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['regexpid'], ['name']], 'fields' => [
			'regexpid' =>			['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>				['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
			'test_string' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('regexps', 'test_string')],
			'expressions' =>		['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY, 'uniq' => [['expression_type', 'expression']], 'fields' => [
				'expression_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
				'expression' =>			['type' => API_MULTIPLE, 'flags' => API_REQUIRED, 'rules' => [
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])], 'type' => API_REGEX, 'length' => DB::getFieldLength('expressions', 'expression')],
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED])], 'type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('expressions', 'expression')]
				]],
				'exp_delimiter' =>		['type' => API_MULTIPLE, 'rules' => [
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_ANY_INCLUDED])], 'type' => API_STRING_UTF8, 'in' => '\\,,.,/'],
											['if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])], 'type' => API_STRING_UTF8, 'in' => '']
				]],
				'case_sensitive' =>		['type' => API_INT32, 'in' => '0,1']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $regexs, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_regexs = $this->get([
			'output' => ['regexpid', 'name', 'test_string'],
			'regexpids' => array_column($regexs, 'regexpid'),
			'preservekeys' => true
		]);

		if (count($db_regexs) != count($regexs)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		self::checkDuplicates($regexs, $db_regexs);

		self::addAffectedObjects($regexs, $db_regexs);
	}

	/**
	 * @param array $regexpids
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @return array
	 */
	public function delete(array $regexpids): array {
		if (self::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			self::exception(ZBX_API_ERROR_PERMISSIONS,
				_s('No permissions to call "%1$s.%2$s".', 'regexp', __FUNCTION__)
			);
		}

		$api_input_rules = ['type' => API_IDS, 'flags' => API_NOT_EMPTY, 'uniq' => true];

		if (!CApiInputValidator::validate($api_input_rules, $regexpids, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$db_regexs = DB::select('regexps', [
			'output' => ['regexpid', 'name'],
			'regexpids' => $regexpids,
			'preservekeys' => true
		]);

		if (count($db_regexs) != count($regexpids)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		DB::delete('regexps', ['regexpid' => $regexpids]);

		self::addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_REGEXP, $db_regexs);

		return ['regexpids' => $regexpids];
	}

	protected function addRelatedObjects(array $options, array $db_regexs) {
		$db_regexs = parent::addRelatedObjects($options, $db_regexs);

		if ($options['selectExpressions'] !== null) {
			foreach ($db_regexs as &$db_regex) {
				$db_regex['expressions'] = [];
			}
			unset($db_regex);

			$db_expressions = API::getApiService()->select('expressions', [
				'output' => $this->outputExtend($options['selectExpressions'], ['regexpid']),
				'filter' => ['regexpid' => array_keys($db_regexs)]
			]);

			foreach ($db_expressions as $db_expression) {
				$regexpid = $db_expression['regexpid'];
				unset($db_expression['expressionid'], $db_expression['regexpid']);

				$db_regexs[$regexpid]['expressions'][] = $db_expression;
			}
		}

		return $db_regexs;
	}

	/**
	 * Add the existing expressions to $db_regexs whether these are affected by the update.
	 *
	 * @param array $regexs
	 * @param array $db_regexs
	 */
	private static function addAffectedObjects(array $regexs, array &$db_regexs): void {
		$regexids = [];

		foreach ($regexs as $regex) {
			if (array_key_exists('expressions', $regex)) {
				$regexids[] = $regex['regexpid'];
				$db_regexs[$regex['regexpid']]['expressions'] = [];
			}
		}

		if ($regexids) {
			$options = [
				'output' => ['expressionid', 'regexpid', 'expression', 'expression_type', 'exp_delimiter',
					'case_sensitive'
				],
				'filter' => ['regexpid' => $regexids]
			];
			$db_expressions = DBselect(DB::makeSql('expressions', $options));

			while ($db_expression = DBfetch($db_expressions)) {
				$db_regexs[$db_expression['regexpid']]['expressions'][$db_expression['expressionid']] =
					array_diff_key($db_expression, array_flip(['regexpid']));
			}
		}
	}
}
