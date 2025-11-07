<?php
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


class CApiService {

	/**
	 * Authorized user data.
	 *
	 * @var array
	 */
	public static $userData;

	public const ACCESS_RULES = [];
	public const OUTPUT_FIELDS = [];

	/**
	 * The name of the table.
	 *
	 * @var string
	 */
	protected $tableName;

	/**
	 * The alias of the table.
	 *
	 * @var string
	 */
	protected $tableAlias = 't';

	/**
	 * The name of the field used as a private key.
	 *
	 * @var string
	 */
	protected $pk;

	/**
	 * An array of field that can be used for sorting.
	 *
	 * @var array
	 */
	protected $sortColumns = [];

	/**
	 * An array of allowed get() options that are supported by all APIs.
	 *
	 * @var array
	 */
	protected $globalGetOptions = [];

	/**
	 * An array containing all of the allowed get() options for the current API.
	 *
	 * @var array
	 */
	protected $getOptions = [];

	/**
	 * An array containing all of the error strings.
	 *
	 * @var array
	 */
	protected $errorMessages = [];

	public function __construct() {
		// set the PK of the table
		$this->pk = $this->pk($this->tableName());

		$this->globalGetOptions = [
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> false,
			'excludeSearch'			=> false,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_EXTEND,
			'countOutput'			=> false,
			'groupCount'			=> false,
			'preservekeys'			=> false,
			'limit'					=> null
		];
		$this->getOptions = $this->globalGetOptions;
	}

	/**
	 * Returns the name of the database table that contains the objects.
	 *
	 * @return string
	 */
	public function tableName() {
		return $this->tableName;
	}

	/**
	 * Returns the alias of the database table that contains the objects.
	 *
	 * @return string
	 */
	protected function tableAlias() {
		return $this->tableAlias;
	}

	/**
	 * Returns the table name with the table alias. If the $tableName and $tableAlias
	 * parameters are not given, the name and the alias of the current table will be used.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 *
	 * @return string
	 */
	protected function tableId($tableName = null, $tableAlias = null) {
		$tableName = $tableName ? $tableName : $this->tableName();
		$tableAlias = $tableAlias ? $tableAlias : $this->tableAlias();

		return $tableName.' '.$tableAlias;
	}

	/**
	 * Prepends the table alias to the given field name. If no $tableAlias is given,
	 * the alias of the current table will be used.
	 *
	 * @param string $fieldName
	 * @param string $tableAlias
	 *
	 * @return string
	 */
	protected function fieldId($fieldName, $tableAlias = null) {
		$tableAlias = $tableAlias ? $tableAlias : $this->tableAlias();

		return $tableAlias.'.'.$fieldName;
	}

	/**
	 * Returns the name of the field that's used as a private key. If the $tableName is not given,
	 * the PK field of the given table will be returned.
	 *
	 * @param string $tableName;
	 *
	 * @return string
	 */
	public function pk($tableName = null) {
		if ($tableName) {
			$schema = $this->getTableSchema($tableName);

			return $schema['key'];
		}

		return $this->pk;
	}

	/**
	 * Returns the name of the option that refers to the PK column. If the $tableName parameter
	 * is not given, the Pk option of the current table will be returned.
	 *
	 * @param string $tableName
	 *
	 * @return string
	 */
	public function pkOption($tableName = null) {
		return $this->pk($tableName).'s';
	}

	/**
	 * Returns an array that describes the schema of the database table. If no $tableName
	 * is given, the schema of the current table will be returned.
	 *
	 * @param $tableName;
	 *
	 * @return array
	 */
	protected function getTableSchema($tableName = null) {
		$tableName = $tableName ? $tableName : $this->tableName();

		return DB::getSchema($tableName);
	}

	/**
	 * Returns true if the table has the given field. If no $tableName is given,
	 * the current table will be used.
	 *
	 * @param string $fieldName
	 * @param string $tableName
	 *
	 * @return boolean
	 */
	protected function hasField($fieldName, $tableName = null) {
		$schema = $this->getTableSchema($tableName);

		return isset($schema['fields'][$fieldName]);
	}

	/**
	 * Returns a translated error message.
	 *
	 * @param $id
	 *
	 * @return string
	 */
	protected function getErrorMsg($id) {
		return $this->errorMessages[$id];
	}

	/**
	 * Adds the given fields to the "output" option if it's not already present.
	 *
	 * @param string $output
	 * @param array $fields        either a single field name, or an array of fields
	 *
	 * @return mixed
	 */
	protected function outputExtend($output, array $fields) {
		if ($output === null) {
			return $fields;
		}
		// if output is set to extend, it already contains that field; return it as is
		elseif ($output === API_OUTPUT_EXTEND) {
			return $output;
		}

		// if output is an array, add the additional fields
		return array_keys(array_flip(array_merge($output, $fields)));
	}

	/**
	 * Returns true if the given field is requested in the output parameter.
	 *
	 * @param $field
	 * @param $output
	 *
	 * @return bool
	 */
	protected function outputIsRequested($field, $output) {
		switch ($output) {
			// if all fields are requested, just return true
			case API_OUTPUT_EXTEND:
				return true;

			// return false if nothing or an object count is requested
			case API_OUTPUT_COUNT:
			case null:
				return false;

			// if an array of fields is passed, check if the field is present in the array
			default:
				return is_array($output) ? in_array($field, $output) : false;
		}
	}

	/**
	 * Unset those fields of the objects, which are not requested for the $output.
	 *
	 * @param array        $objects
	 * @param array        $fields
	 * @param string|array $output   requested output
	 *
	 * @return array
	 */
	protected function unsetExtraFields(array $objects, array $fields, $output = []) {
		// find the fields that have not been requested
		$extraFields = [];
		foreach ($fields as $field) {
			if (!$this->outputIsRequested($field, $output)) {
				$extraFields[] = $field;
			}
		}

		// unset these fields
		if ($extraFields) {
			foreach ($objects as &$object) {
				foreach ($extraFields as $field) {
					unset($object[$field]);
				}
			}
			unset($object);
		}

		return $objects;
	}

	/**
	 * Creates a relation map for the given objects.
	 *
	 * If the $table parameter is set, the relations will be loaded from a database table, otherwise the map will be
	 * built from two base object properties.
	 *
	 * @param array  $objects			a hash of base objects
	 * @param string $baseField			the base object ID field
	 * @param string $foreignField		the related objects ID field
	 * @param string $table				table to load the relation from
	 *
	 * @return CRelationMap
	 */
	protected function createRelationMap(array $objects, $baseField, $foreignField, $table = null) {
		$relationMap = new CRelationMap();

		// create the map from a database table
		if ($table) {
			$res = DBselect(API::getApiService()->createSelectQuery($table, [
				'output' => [$baseField, $foreignField],
				'filter' => [$baseField => array_keys($objects)]
			]));
			while ($relation = DBfetch($res)) {
				$relationMap->addRelation($relation[$baseField], $relation[$foreignField]);
			}
		}

		// create a map from the base objects
		else {
			foreach ($objects as $object) {
				$relationMap->addRelation($object[$baseField], $object[$foreignField]);
			}
		}

		return $relationMap;
	}

	/**
	 * Constructs an SQL SELECT query for a specific table from the given API options, executes it and returns
	 * the result.
	 *
	 * TODO: add global 'countOutput' support
	 *
	 * @param string $tableName
	 * @param array  $options
	 *
	 * @return array
	 */
	protected function select($tableName, array $options) {
		$limit = isset($options['limit']) ? $options['limit'] : null;

		$sql = $this->createSelectQuery($tableName, $options);

		$objects = DBfetchArray(DBSelect($sql, $limit));

		if (array_key_exists('preservekeys', $options) && $options['preservekeys']) {
			$rs = [];
			foreach ($objects as $object) {
				$rs[$object[$this->pk($tableName)]] = $object;
			}

			return $rs;
		}
		else {
			return $objects;
		}
	}

	/**
	 * Creates an SQL SELECT query from the given options.
	 *
	 * @param string $tableName
	 * @param array  $options
	 *
	 * @return string
	 */
	protected function createSelectQuery($tableName, array $options) {
		$sqlParts = $this->createSelectQueryParts($tableName, $this->tableAlias(), $options);

		return self::createSelectQueryFromParts($sqlParts);
	}

	/**
	 * Builds an SQL parts array from the given options.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array  $options
	 *
	 * @return array		The resulting SQL parts array
	 */
	protected function createSelectQueryParts($tableName, $tableAlias, array $options) {
		// extend default options
		$options = zbx_array_merge($this->globalGetOptions, $options);

		$sqlParts = [
			'select' => [$this->fieldId($this->pk($tableName), $tableAlias)],
			'from' => $this->tableId($tableName, $tableAlias),
			'where' => [],
			'group' => [],
			'order' => [],
			'limit' => null
		];

		// add filter options
		$sqlParts = $this->applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// add output options
		$sqlParts = $this->applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		// add sort options
		$sqlParts = $this->applyQuerySortOptions($tableName, $tableAlias, $options, $sqlParts);

		return $sqlParts;
	}

	/**
	 * Returns DISTINCT modifier for sql statements with multiple joins and without aggregations.
	 */
	private static function dbDistinct(array $sql_parts): bool {
		if (array_key_exists('join', $sql_parts)) {
			foreach ($sql_parts['join'] as $join) {
				$r_table = DB::getSchema($join['table']);
				$r_table_key = explode(',', $r_table['key']);
				sort($r_table_key);

				$r_table_fields = array_key_exists('using', $join) ? (array) $join['using'] : $join['on'];
				sort($r_table_fields);

				// Apply DISTINCT when table linked by non-unique column(s).
				if ($r_table_key !== $r_table_fields) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Creates a SELECT SQL query from the given SQL parts array.
	 */
	protected static function createSelectQueryFromParts(array $sql_parts): string {
		if (array_key_exists('left_join', $sql_parts)) {
			trigger_error('The CApiService database framework no longer supports "left_join". '.
				'Please use "join" with the "type" => "left" option instead.', E_USER_ERROR
			);
		}

		$sql_from = ' FROM '.$sql_parts['from'];
		$from_alias = explode(' ', $sql_parts['from']);
		$from_alias = end($from_alias);

		if (array_key_exists('join', $sql_parts)) {
			foreach ($sql_parts['join'] as $r_alias => $join) {
				$l_alias = array_key_exists('left_table', $join) ? $join['left_table'] : $from_alias;

				$sql_join_conditions = [];

				if (array_key_exists('using', $join)) {
					foreach ((array) $join['using'] as $field) {
						$sql_join_conditions[] = $l_alias.'.'.$field.'='.$r_alias.'.'.$field;
					}
				}
				else {
					foreach ($join['on'] as $l_field => $r_field) {
						$sql_join_conditions[] = $l_alias.'.'.$l_field.'='.$r_alias.'.'.$r_field;
					}
				}

				if (array_key_exists('type', $join) && $join['type'] === 'left') {
					$sql_from .= ' LEFT';
				}
				$sql_from .= ' JOIN '.$join['table'].' '.$r_alias.' ON '.implode(' AND ', $sql_join_conditions);
			}
		}

		$sql_select = ($sql_parts['distinct'] ? 'DISTINCT ' : '').implode(',', array_unique($sql_parts['select']));
		$sql_where = $sql_parts['where'] ? ' WHERE '.implode(' AND ', array_unique($sql_parts['where'])) : '';
		$sql_group = $sql_parts['group'] ? ' GROUP BY '.implode(',', array_unique($sql_parts['group'])) : '';
		$sql_order = $sql_parts['order'] ? ' ORDER BY '.implode(',', array_unique($sql_parts['order'])) : '';

		return 'SELECT '.$sql_select.$sql_from.$sql_where.$sql_group.$sql_order;
	}

	/**
	 * Modifies the SQL parts to implement all the output related options.
	 *
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @throws Exception
	 *
	 * @return array  The resulting SQL parts array.
	 */
	protected function applyQueryOutputOptions(string $table_name, string $table_alias, array $options,
			array $sql_parts) {
		$pk = $this->pk($table_name);
		$pk_composite = strpos($pk, ',') !== false;

		$sql_parts['distinct'] = self::dbDistinct($sql_parts);

		if (array_key_exists('countOutput', $options) && $options['countOutput']
				&& !$this->requiresPostSqlFiltering($options)) {
			$has_joins = array_key_exists('join', $sql_parts) && $sql_parts['join'];

			if ($pk_composite && $has_joins) {
				throw new Exception('Joins with composite primary keys are not supported in this API version.');
			}

			$sql_parts['select'] = $has_joins
				? ['COUNT('.($sql_parts['distinct'] ? 'DISTINCT ' : '').$this->fieldId($pk, $table_alias).')'.
					' AS rowscount']
				: ['COUNT(*) AS rowscount'];

			$sql_parts['distinct'] = false;

			// Select columns used by group count.
			if (array_key_exists('groupCount', $options) && $options['groupCount']) {
				foreach ($sql_parts['group'] as $fields) {
					$sql_parts['select'][] = $fields;
				}
			}
			elseif (array_key_exists('groupBy', $options) && $options['groupBy']) {
				foreach ($options['groupBy'] as $field) {
					if ($this->hasField($field, $table_name)) {
						$field = $this->fieldId($field, $table_alias);

						array_unshift($sql_parts['select'], $field);
						$sql_parts['group'][] = $field;
					}
				}
			}
		}
		elseif (array_key_exists('groupBy', $options) && $options['groupBy']) {
			$sql_parts['select'] = [];

			foreach ($options['groupBy'] as $field) {
				if ($this->hasField($field, $table_name)) {
					$field = $this->fieldId($field, $table_alias);

					array_unshift($sql_parts['select'], $field);
					$sql_parts['group'][] = $field;
				}
			}
		}
		// custom output
		elseif (is_array($options['output'])) {
			$sql_parts['select'] = $pk_composite ? [] : [$this->fieldId($pk, $table_alias)];

			foreach ($options['output'] as $field) {
				if ($this->hasField($field, $table_name)) {
					$sql_parts['select'][] = $this->fieldId($field, $table_alias);
				}
			}

			$sql_parts['select'] = array_unique($sql_parts['select']);
		}
		// extended output
		elseif ($options['output'] == API_OUTPUT_EXTEND) {
			// TODO: API_OUTPUT_EXTEND must return ONLY the fields from the base table
			$sql_parts = $this->addQuerySelect($this->fieldId('*', $table_alias), $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * Modifies the SQL parts to implement all of the filter related options.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array $options
	 * @param array $sqlParts
	 *
	 * @return array		The resulting SQL parts array
	 */
	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$pkOption = $this->pkOption($tableName);
		$tableId = $this->tableId($tableName, $tableAlias);

		// pks
		if (isset($options[$pkOption])) {
			zbx_value2array($options[$pkOption]);
			$sqlParts['where'][] = dbConditionString($this->fieldId($this->pk($tableName), $tableAlias), $options[$pkOption]);
		}

		// filters
		if (is_array($options['filter'])) {
			$this->dbFilter($tableId, $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search($tableId, $options, $sqlParts);
		}

		return $sqlParts;
	}

	/**
	 * Modifies the SQL parts to implement all the sorting related options.
	 * Sorting is currently only supported for CApiService::get() methods.
	 *
	 * @param string $table_name
	 * @param string $table_alias
	 * @param array  $options
	 * @param array  $sql_parts
	 *
	 * @throws APIException
	 *
	 * @return array
	 */
	protected function applyQuerySortOptions(string $table_name, string $table_alias, array $options,
			array $sql_parts): array {
		$count_output = array_key_exists('countOutput', $options) && $options['countOutput'];
		$group_by = array_key_exists('groupBy', $options) && $options['groupBy'];
		$aggregate_sort_columns = [];

		if ($count_output && $group_by) {
			$aggregate_sort_columns[] = 'rowscount';
		}

		$sort_columns = $group_by ? array_merge($options['groupBy'], $aggregate_sort_columns) : $this->sortColumns;

		if ($sort_columns && !zbx_empty($options['sortfield'])) {
			$options['sortfield'] = is_array($options['sortfield'])
				? array_unique($options['sortfield'])
				: [$options['sortfield']];

			foreach ($options['sortfield'] as $i => $sortfield) {
				// Validate sortfield.
				if (!str_in_array($sortfield, $sort_columns)) {
					throw new APIException(ZBX_API_ERROR_INTERNAL,
						_s('Sorting by field "%1$s" not allowed.', $sortfield)
					);
				}

				// Add sort field to order.
				$sortorder = '';
				if (is_array($options['sortorder'])) {
					if (!empty($options['sortorder'][$i])) {
						$sortorder = $options['sortorder'][$i] === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '';
					}
				}
				else {
					$sortorder = $options['sortorder'] === ZBX_SORT_DOWN ? ' '.ZBX_SORT_DOWN : '';
				}

				if (in_array($sortfield, $aggregate_sort_columns)) {
					$sql_parts['order'][$sortfield] = $sortfield.$sortorder;
				}
				else {
					$sql_parts = $this->applyQuerySortField($sortfield, $sortorder, $table_alias, $sql_parts);
				}
			}
		}

		return $sql_parts;
	}

	/**
	 * Adds a specific property from the 'sortfield' parameter to the $sqlParts array.
	 *
	 * @param string $sortfield
	 * @param string $sortorder
	 * @param string $alias
	 * @param array  $sqlParts
	 *
	 * @return array
	 */
	protected function applyQuerySortField($sortfield, $sortorder, $alias, array $sqlParts) {
		// add sort field to select if distinct is used
		if (array_key_exists('join', $sqlParts) && $sqlParts['join']
				&& !str_in_array($alias.'.'.$sortfield, $sqlParts['select'])
				&& !str_in_array($alias.'.*', $sqlParts['select'])) {

			$sqlParts['select'][$sortfield] = $alias.'.'.$sortfield;
		}

		$sqlParts['order'][$alias.'.'.$sortfield] = $alias.'.'.$sortfield.$sortorder;

		return $sqlParts;
	}

	/**
	 * Adds the given field to the SELECT part of the $sqlParts array if it's not already present.
	 * If $sqlParts['select'] not present it is created and field appended.
	 *
	 * @param string $fieldId
	 * @param array  $sqlParts
	 *
	 * @return array
	 */
	protected function addQuerySelect($fieldId, array $sqlParts) {
		if (!isset($sqlParts['select'])) {
			return ['select' => [$fieldId]];
		}

		list($tableAlias, $field) = explode('.', $fieldId);

		if (!in_array($fieldId, $sqlParts['select']) && !in_array($this->fieldId('*', $tableAlias), $sqlParts['select'])) {
			// if we want to select all of the columns, other columns from this table can be removed
			if ($field == '*') {
				foreach ($sqlParts['select'] as $key => $selectFieldId) {
					list($selectTableAlias,) = explode('.', $selectFieldId);

					if ($selectTableAlias == $tableAlias) {
						unset($sqlParts['select'][$key]);
					}
				}
			}

			$sqlParts['select'][] = $fieldId;
		}

		return $sqlParts;
	}

	/**
	 * Adds the related objects requested by "select*" options to the resulting object set.
	 *
	 * @param array $options
	 * @param array $result   An object hash with PKs as keys.
	 *
	 * @return array mixed
	 */
	protected function addRelatedObjects(array $options, array $result) {
		// must be implemented in each API separately

		return $result;
	}

	/**
	 * Fetches the fields given in $fields from the database and extends the objects with the loaded data.
	 *
	 * @param string $tableName
	 * @param array  $objects
	 * @param array  $fields
	 *
	 * @return array
	 */
	protected function extendObjects($tableName, array $objects, array $fields) {
		if ($objects) {
			$dbObjects = API::getApiService()->select($tableName, [
				'output' => $fields,
				$this->pkOption($tableName) => zbx_objectValues($objects, $this->pk($tableName)),
				'preservekeys' => true
			]);

			foreach ($objects as &$object) {
				$pk = $object[$this->pk($tableName)];
				if (isset($dbObjects[$pk])) {
					check_db_fields($dbObjects[$pk], $object);
				}
			}
			unset($object);
		}

		return $objects;
	}

	/**
	 * For each object in $objects the method copies fields listed in $fields that are not present in the target
	 * object from the source object.
	 *
	 * Matching objects in both arrays must have the same keys.
	 *
	 * @param array  $objects
	 * @param array  $sourceObjects
	 *
	 * @return array
	 */
	protected function extendFromObjects(array $objects, array $sourceObjects, array $fields) {
		$fields = array_flip($fields);

		foreach ($objects as $key => &$object) {
			if (isset($sourceObjects[$key])) {
				$object += array_intersect_key($sourceObjects[$key], $fields);
			}
		}
		unset($object);

		return $objects;
	}

	/**
	 * For each object in $objects the method copies fields listed in $fields that are not present in the target
	 * object from the source object.
	 *
	 * @param array  $objects
	 * @param array  $source
	 * @param string $field_name
	 * @param array  $fields
	 *
	 * @return array
	 */
	protected function extendObjectsByKey(array $objects, array $source, $field_name, array $fields) {
		$fields = array_flip($fields);

		foreach ($objects as &$object) {
			if (array_key_exists($field_name, $object) && array_key_exists($object[$field_name], $source)) {
				$object += array_intersect_key($source[$object[$field_name]], $fields);
			}
		}
		unset($object);

		return $objects;
	}

	/**
	 * Checks if an object contains any of the given parameters.
	 *
	 * Example:
	 * checkNoParameters($item, array('templateid', 'state'), _('Cannot set "%1$s" for item "%2$s".'), $item['name']);
	 * If any of the parameters 'templateid' or 'state' are present in the object, it will be placed in "%1$s"
	 * and $item['name'] will be placed in "%2$s".
	 *
	 * @throws APIException			if any of the parameters are present in the object
	 *
	 * @param array  $object
	 * @param array  $params		array of parameters to check
	 * @param string $error
	 * @param string $objectName
	 */
	protected function checkNoParameters(array $object, array $params, $error, $objectName) {
		foreach ($params as $param) {
			if (array_key_exists($param, $object)) {
				$error = _params($error, [$param, $objectName]);
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
	}

	/**
	 * Throws an API exception.
	 *
	 * @param int    $code
	 * @param string $error
	 */
	protected static function exception($code = ZBX_API_ERROR_INTERNAL, $error = '') {
		throw new APIException($code, $error);
	}

	/**
	 * Triggers a deprecated notice. Should be called when a deprecated parameter or method is used.
	 * The notice will not be displayed in the result returned by an API method.
	 *
	 * @param string $error		error text
	 */
	protected function deprecated($error) {
		trigger_error($error, E_USER_DEPRECATED);
	}

	/**
	 * Apply filter conditions to sql built query.
	 *
	 * @param string $table
	 * @param array  $options
	 * @param array  $sqlParts
	 *
	 * @return bool
	 */
	protected function dbFilter($table, $options, &$sqlParts) {
		list($table, $tableShort) = explode(' ', $table);

		$tableSchema = DB::getSchema($table);

		$filter = [];
		foreach ($options['filter'] as $field => $value) {
			if ($value === null || !array_key_exists($field, $tableSchema['fields'])
					|| ($tableSchema['fields'][$field]['type'] & DB::SUPPORTED_FILTER_TYPES) == 0) {
				continue;
			}

			$values = [];

			if ($tableSchema['fields'][$field]['type'] & DB::FIELD_TYPE_INT) {
				foreach ((array) $value as $val) {
					if (!is_int($val) && (!is_string($val) || !preg_match('/^'.ZBX_PREG_INT.'$/', $val))) {
						continue;
					}

					if ($val < ZBX_MIN_INT32 || $val > ZBX_MAX_INT32) {
						continue;
					}

					$values[] = $val;
				}
			}
			elseif ($tableSchema['fields'][$field]['type'] & DB::FIELD_TYPE_ID) {
				foreach ((array) $value as $val) {
					if (!is_int($val) && (!is_string($val) || !ctype_digit($val))) {
						continue;
					}

					if ($val < 0 || bccomp((string) $val, ZBX_DB_MAX_ID) > 0) {
						continue;
					}

					$values[] = $val;
				}
			}
			elseif ($tableSchema['fields'][$field]['type'] & DB::FIELD_TYPE_UINT) {
				foreach ((array) $value as $val) {
					if (!is_int($val) && (!is_string($val) || !ctype_digit($val))) {
						continue;
					}

					if (bccomp((string) $val, ZBX_MIN_INT64) < 0 || bccomp((string) $val, ZBX_MAX_INT64) > 0) {
						continue;
					}

					$values[] = $val;
				}
			}
			elseif ($tableSchema['fields'][$field]['type'] & DB::FIELD_TYPE_FLOAT) {
				foreach ((array) $value as $val) {
					if (!is_numeric($val)) {
						continue;
					}

					$values[] = $val;
				}
			}
			else {
				$values = (array) $value;
			}

			$fieldName = $this->fieldId($field, $tableShort);
			if ($tableSchema['fields'][$field]['type'] & DB::FIELD_TYPE_ID) {
				$filter[$field] = dbConditionId($fieldName, $values);
			}
			elseif ($tableSchema['fields'][$field]['type'] & (DB::FIELD_TYPE_INT | DB::FIELD_TYPE_UINT)) {
				$filter[$field] = dbConditionInt($fieldName, $values);
			}
			else {
				$filter[$field] = dbConditionString($fieldName, $values);
			}
		}

		if ($filter) {
			if (isset($sqlParts['where']['filter'])) {
				$filter[] = $sqlParts['where']['filter'];
			}

			if (is_null($options['searchByAny']) || $options['searchByAny'] === false || count($filter) == 1) {
				$sqlParts['where']['filter'] = implode(' AND ', $filter);
			}
			else {
				$sqlParts['where']['filter'] = '('.implode(' OR ', $filter).')';
			}

			return true;
		}

		return false;
	}

	/**
	 * Converts a deprecated parameter to a new one in the $params array. If both parameter are used,
	 * the new parameter will override the deprecated one.
	 * If a deprecated parameter is used, a notice will be triggered in the frontend.
	 *
	 * @param array  $params
	 * @param string $deprecatedParam
	 * @param string $newParam
	 *
	 * @return array
	 */
	protected function convertDeprecatedParam(array $params, $deprecatedParam, $newParam) {
		if (isset($params[$deprecatedParam])) {
			self::deprecated('Parameter "'.$deprecatedParam.'" is deprecated.');

			// if the new parameter is not used, use the deprecated one instead
			if (!isset($params[$newParam])) {
				$params[$newParam] = $params[$deprecatedParam];
			}

			// unset the deprecated parameter
			unset($params[$deprecatedParam]);
		}

		return $params;
	}

	/**
	 * Check if a set of parameters contains a deprecated parameter or a parameter with a deprecated value.
	 * If $value is not set, the method will trigger a deprecated notice if $params contains the $paramName key.
	 * If $value is set, the method will trigger a notice if the value of the parameter is equal to the deprecated value
	 * or the parameter is an array and contains a deprecated value.
	 *
	 * @param array  $params
	 * @param string $paramName
	 * @param string $value
	 */
	protected function checkDeprecatedParam(array $params, $paramName, $value = null) {
		if (isset($params[$paramName])) {
			if ($value === null) {
				self::deprecated('Parameter "'.$paramName.'" is deprecated.');
			}
			elseif (is_array($params[$paramName]) && in_array($value, $params[$paramName]) || $params[$paramName] == $value) {
				self::deprecated('Value "'.$value.'" for parameter "'.$paramName.'" is deprecated.');
			}
		}
	}

	/**
	 * Runs the given validator and throws an exception if it fails.
	 *
	 * @param $value
	 * @param CValidator $validator
	 */
	protected function checkValidator($value, CValidator $validator) {
		if (!$validator->validate($value)) {
			self::exception(ZBX_API_ERROR_INTERNAL, $validator->getError());
		}
	}

	/**
	 * Runs the given partial validator and throws an exception if it fails.
	 *
	 * @param array $array
	 * @param CPartialValidatorInterface $validator
	 * @param array $fullArray
	 */
	protected function checkPartialValidator(array $array, CPartialValidatorInterface $validator, $fullArray = []) {
		if (!$validator->validatePartial($array, $fullArray)) {
			self::exception(ZBX_API_ERROR_INTERNAL, $validator->getError());
		}
	}

	/**
	 * Adds a deprecated property to an array of resulting objects if it's requested in $output. The value for the
	 * deprecated property will be taken from the new one.
	 *
	 * @param array        $objects
	 * @param string       $deprecatedProperty
	 * @param string       $newProperty
	 * @param string|array $output
	 *
	 * @return array
	 */
	protected function handleDeprecatedOutput(array $objects, $deprecatedProperty, $newProperty, $output) {
		if ($this->outputIsRequested($deprecatedProperty, $output)) {
			foreach ($objects as &$object) {
				$object[$deprecatedProperty] = $object[$newProperty];
			}
			unset($object);
		}

		return $objects;
	}

	/**
	 * Fetch data from DB.
	 * If post SQL filtering is necessary, several queries will be executed. SQL limit is calculated so that minimum
	 * amount of queries would be executed and minimum amount of unnecessary data retrieved.
	 *
	 * @param string $query		SQL query
	 * @param array  $options	API call parameters
	 *
	 * @return array
	 */
	protected function customFetch($query, array $options) {
		if ($this->requiresPostSqlFiltering($options)) {
			$offset = 0;

			// we think that taking twice as necessary elements in first query is fair guess, this cast to int as well
			$limit = $options['limit'] ? 2 * $options['limit'] : null;

			// we use $minLimit for setting minimum limit twice as big for each consecutive query to not run in lots
			// of queries for some cases
			$minLimit = $limit;
			$allElements = [];

			do {
				// fetch group of elements
				$elements = DBfetchArray(DBselect($query, $limit, $offset));

				// we have potentially more elements
				$hasMore = ($limit && count($elements) === $limit);

				$elements = $this->applyPostSqlFiltering($elements, $options);

				// truncate element set after post SQL filtering, if enough elements or more retrieved via SQL query
				if ($options['limit'] && count($allElements) + count($elements) >= $options['limit']) {
					$allElements += array_slice($elements, 0, $options['limit'] - count($allElements), true);
					break;
				}

				$allElements += $elements;

				// calculate $limit and $offset for next query
				if ($limit) {
					$offset += $limit;
					$minLimit *= 2;

					// take care of division by zero
					$elemCount = count($elements) ? count($elements) : 1;

					// we take $limit as $minLimit or reasonable estimate to get all necessary data in two queries
					// with high probability
					$limit = max($minLimit, round($limit / $elemCount * ($options['limit'] - count($allElements)) * 2));
				}
			} while ($hasMore);

			return $allElements;
		}
		else {
			return DBfetchArray(DBselect($query, $options['limit']));
		}
	}

	/**
	 * Checks if post SQL filtering necessary.
	 *
	 * @param array $options	API call parameters
	 *
	 * @return bool				true if filtering necessary false otherwise
	 */
	protected function requiresPostSqlFiltering(array $options) {
		// must be implemented in each API separately

		return false;
	}

	/**
	 * Removes elements which could not be removed within SQL query.
	 *
	 * @param array $elements	list of elements on whom perform filtering
	 * @param array $options	API call parameters
	 *
	 * @return array			input array $elements with some elements removed
	 */
	protected function applyPostSqlFiltering(array $elements, array $options) {
		// must be implemented in each API separately

		return $elements;
	}

	/**
	 * Legacy method to add audit log records.
	 *
	 * @param int   $action        CAudit::ACTION_*
	 * @param int   $resourcetype  CAudit::RESOURCE_*
	 * @param array $objects
	 * @param array $objects_old
	 */
	protected function addAuditBulk($action, $resourcetype, array $objects, ?array $objects_old = null) {
		CAuditOld::addBulk(self::$userData['userid'], self::$userData['userip'], self::$userData['username'], $action,
			$resourcetype, $objects, $objects_old
		);
	}

	/**
	 * Add audit log records.
	 *
	 * @param int   $action       CAudit::ACTION_*
	 * @param int   $resource     CAudit::RESOURCE_*
	 * @param array $objects      (optional)
	 * @param array $objects_old  (optional)
	 */
	protected static function addAuditLog(int $action, int $resource, array $objects = [],
			array $objects_old = []): void {
		CAudit::log(self::$userData['userid'], self::$userData['userip'], self::$userData['username'], $action,
			$resource, $objects, $objects_old
		);
	}

	/**
	 * Add audit log records on behalf of the given user.
	 *
	 * @param string|null $userid
	 * @param string      $ip
	 * @param string      $username
	 * @param int         $action       CAudit::ACTION_*
	 * @param int         $resource     CAudit::RESOURCE_*
	 * @param array       $objects      (optional)
	 * @param array       $objects_old  (optional)
	 */
	protected static function addAuditLogByUser(?string $userid, string $ip, string $username, int $action,
			int $resource, array $objects = [], array $objects_old = []): void {
		CAudit::log($userid, $ip, $username, $action, $resource, $objects, $objects_old);
	}

	/**
	 * Check access to specific access rule.
	 *
	 * @param string $rule_name  Rule name.
	 *
	 * @return bool  Returns true if user has access to specified rule, and false otherwise.
	 *
	 * @throws Exception
	 */
	protected static function checkAccess(string $rule_name): bool {
		return (self::$userData && CRoleHelper::checkAccess($rule_name, self::$userData['roleid']));
	}

	/**
	 * Return user session ID or user API token.
	 *
	 * @return string
	 */
	public static function getAuthIdentifier(): string {
		return array_key_exists('sessionid', self::$userData) ? self::$userData['sessionid'] : self::$userData['token'];
	}
}
