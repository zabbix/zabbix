<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing methods for operations with regular expressions.
 */
class CRegexp extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
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
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			// filter
			'regexpids' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE, 'default' => null],
			'filter' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'regexpid' =>				['type' => API_IDS, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
			'search' =>					['type' => API_OBJECT, 'flags' => API_ALLOW_NULL, 'default' => null, 'fields' => [
				'name' =>					['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE],
				'test_string' =>			['type' => API_STRINGS_UTF8, 'flags' => API_ALLOW_NULL | API_NORMALIZE]
			]],
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
		$this->validateCreate($regexs);

		$regexids = DB::insertBatch('regexps', $regexs);

		foreach ($regexs as $index => &$regex) {
			$regex['regexpid'] = $regexids[$index];
		}
		unset($regex);

		$this->updateExpressions($regexs, __FUNCTION__);

		$this->addAuditLog(CAudit::ACTION_ADD, CAudit::RESOURCE_REGEXP, $regexs);

		return ['regexpids' => $regexids];
	}

	/**
	 * @param array $regexs
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateCreate(array &$regexs): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['regexpid', 'name']], 'fields' => [
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
			'test_string' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('regexps', 'test_string')],
			'expressions' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED, 'fields' => [
				'expression' =>			['type' => API_MULTIPLE, 'rules' => [
					[
						'if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
						'type' => API_REGEX,
						'length' => DB::getFieldLength('expressions', 'expression'),
						'flags' => API_REQUIRED
					],
					[
						'if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED])],
						'type' => API_STRING_UTF8,
						'length' => DB::getFieldLength('expressions', 'expression'),
						'flags' => API_REQUIRED | API_NOT_EMPTY
					],
				]],
				'expression_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
				'exp_delimiter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('expressions', 'exp_delimiter')],
				'case_sensitive' =>		['type' => API_INT32, 'in' => '0,1']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $regexs, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['expression', 'expression_type']], 'fields' => [
			'expression' =>			['type' => API_STRING_UTF8],
			'expression_type' =>	['type' => API_INT32]
		]];

		foreach (array_column($regexs, 'expressions') as $index => $expressions) {
			$path = '/'.$index.'/expressions';
			if (!CApiInputValidator::validateUniqueness($api_input_rules, $expressions, $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}

		$this->checkDuplicateNames(array_column($regexs, 'name'));
	}

	/**
	 * Check uniqueness of regular expressions.
	 *
	 * @param array $regex_names
	 *
	 * @throws APIException  if regular expression already exists.
	 */
	protected function checkDuplicateNames(array $regex_names): void {
		$duplicate = DBfetch(DBselect(
			'SELECT r.name'.
			' FROM regexps r'.
			' WHERE '.dbConditionString('r.name', $regex_names),
			1
		));

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
	protected function updateExpressions(array &$regexs, string $method, array $db_regexs = null): void {
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
						return ($expression['expression'] === $db_expression['expression']
							&& $expression['expression_type'] == $db_expression['expression_type']);
					})
				);

				if ($db_expression) {
					$expression['expressionid'] = $db_expression['expressionid'];

					$upd_expression = DB::getUpdatedValues('expressions', $expression, $db_expression);

					if ($upd_expression) {
						$upd_expressions[] = [
							'values' => $upd_expression,
							'where' => ['expressionid' => $db_expression['expressionid']]
						];
					}

					unset($db_expressions[$db_expression['expressionid']]);
				}
				else {
					$ins_expressions[] = [
						'regexpid' => $regex['regexpid'],
						'expression' => $expression['expression'],
						'expression_type' => $expression['expression_type'],
						'exp_delimiter' => $expression['exp_delimiter'],
						'case_sensitive' => $expression['case_sensitive']
					];
				}
			}
			unset($expression);

			$del_expressionids = array_merge($del_expressionids, array_column($db_expressions, 'expressionid'));
		}
		unset($regex);

		if ($del_expressionids) {
			DB::delete('expressions', ['expressionid' => $del_expressionids]);
		}

		if ($upd_expressions) {
			DB::update('expressions', $upd_expressions);
		}

		if ($ins_expressions) {
			$expressionids = DB::insertBatch('expressions', $ins_expressions);
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

		$this->addAuditLog(CAudit::ACTION_UPDATE, CAudit::RESOURCE_REGEXP, $regexs, $db_regexs);

		return ['regexpids' => array_column($regexs, 'regexpid')];
	}

	/**
	 * @param array $regexs
	 * @param array $db_regexs
	 *
	 * @throws APIException  if the input is invalid
	 */
	protected function validateUpdate(array &$regexs, array &$db_regexs = null): void {
		$api_input_rules = ['type' => API_OBJECTS, 'flags' => API_NOT_EMPTY | API_NORMALIZE, 'uniq' => [['regexpid'], ['name']], 'fields' => [
			'regexpid' =>		['type' => API_ID, 'flags' => API_REQUIRED],
			'name' =>			['type' => API_STRING_UTF8, 'flags' => API_NOT_EMPTY, 'length' => DB::getFieldLength('regexps', 'name')],
			'test_string' =>	['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('regexps', 'test_string')],
			'expressions' =>	['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'fields' => [
				'expression' =>			['type' => API_MULTIPLE, 'rules' => [
					[
						'if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
						'type' => API_REGEX,
						'length' => DB::getFieldLength('expressions', 'expression'),
						'flags' => API_REQUIRED
					],
					[
						'if' => ['field' => 'expression_type', 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED])],
						'type' => API_STRING_UTF8,
						'length' => DB::getFieldLength('expressions', 'expression'),
						'flags' => API_REQUIRED | API_NOT_EMPTY
					],
				]],
				'expression_type' =>	['type' => API_INT32, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'in' => implode(',', [EXPRESSION_TYPE_INCLUDED, EXPRESSION_TYPE_ANY_INCLUDED, EXPRESSION_TYPE_NOT_INCLUDED, EXPRESSION_TYPE_TRUE, EXPRESSION_TYPE_FALSE])],
				'exp_delimiter' =>		['type' => API_STRING_UTF8, 'length' => DB::getFieldLength('expressions', 'exp_delimiter')],
				'case_sensitive' =>		['type' => API_INT32, 'in' => '0,1']
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $regexs, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}

		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['expression', 'expression_type']], 'fields' => [
			'expression' =>			['type' => API_STRING_UTF8],
			'expression_type' =>	['type' => API_INT32]
		]];

		foreach (array_column($regexs, 'expressions') as $index => $expressions) {
			$path = '/'.$index.'/expressions';
			if (!CApiInputValidator::validateUniqueness($api_input_rules, $expressions, $path, $error)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}

		$db_regexs = $this->get([
			'output' => ['regexpid', 'name', 'test_string'],
			'regexpids' => array_column($regexs, 'regexpid'),
			'preservekeys' => true
		]);

		$names = [];

		foreach ($regexs as $regex) {
			if (!array_key_exists($regex['regexpid'], $db_regexs)) {
				self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
			}

			if (array_key_exists('name', $regex) && $regex['name'] !== $db_regexs[$regex['regexpid']]['name']) {
				$names[] = $regex['name'];
			}
		}

		if ($names) {
			$this->checkDuplicateNames($names);
		}

		$this->addAffectedObjects($regexs, $db_regexs);
	}

	/**
	 * @param array $regexpids
	 *
	 * @throws APIException if the input is invalid
	 *
	 * @return array
	 */
	public function delete(array $regexpids): array {
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

		$this->addAuditLog(CAudit::ACTION_DELETE, CAudit::RESOURCE_REGEXP, $db_regexs);

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
				'output' => $this->outputExtend($options['selectExpressions'], ['regexpid', 'expression',
					'expression_type', 'exp_delimiter', 'case_sensitive'
				]),
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
	private function addAffectedObjects(array $regexs, array &$db_regexs): void {
		$regexids = [];

		foreach ($regexs as $regex) {
			if (array_key_exists('expressions', $regex)) {
				$regexids[] = $regex['regexpid'];
				$db_regexs[$regex['regexpid']]['expressions'] = [];
			}
		}

		if ($regexids) {
			$options = [
				'output' => ['expressionid', 'regexpid', 'expression', 'expression_type', 'exp_delimiter', 'case_sensitive'],
				'filter' => ['regexpid' => $regexids]
			];
			$db_expressions = DBselect(DB::makeSql('expressions', $options));

			while ($db_expression = DBfetch($db_expressions)) {
				$db_regexs[$db_expression['regexpid']]['expressions'][$db_expression['expressionid']] = [
					'expressionid' => $db_expression['expressionid'],
					'expression' => $db_expression['expression'],
					'expression_type' => $db_expression['expression_type'],
					'exp_delimiter' => $db_expression['exp_delimiter'],
					'case_sensitive' => $db_expression['case_sensitive']
				];
			}
		}
	}
}
