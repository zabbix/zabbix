<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CApiService {

	public static $userData;

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
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_EXTEND,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,
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

			if (strpos($schema['key'], ',') !== false) {
				throw new Exception('Composite private keys are not supported in this API version.');
			}

			return $schema['key'];
		}

		return $this->pk;
	}

	/**
	 * Returns the name of the option that refers the PK column. If the $tableName parameter
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
				return in_array($field, $output);
		}
	}

	/**
	 * Unsets fields $fields from the given objects if they are not requested in $output.
	 *
	 * @param array        $objects
	 * @param array        $fields
	 * @param string|array $output		desired output
	 *
	 * @return array
	 */
	protected function unsetExtraFields(array $objects, array $fields, $output) {
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

		if (isset($options['preservekeys'])) {
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
	 * @return array
	 */
	protected function createSelectQuery($tableName, array $options) {
		$sqlParts = $this->createSelectQueryParts($tableName, $this->tableAlias(), $options);

		return $this->createSelectQueryFromParts($sqlParts);
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
			'from' => [$this->tableId($tableName, $tableAlias)],
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
	 * Creates a SELECT SQL query from the given SQL parts array.
	 *
	 * @param array $sqlParts	An SQL parts array
	 *
	 * @return string			The resulting SQL query
	 */
	protected function createSelectQueryFromParts(array $sqlParts) {
		// build query
		$sqlSelect = implode(',', array_unique($sqlParts['select']));
		$sqlFrom = implode(',', array_unique($sqlParts['from']));
		$sqlWhere = empty($sqlParts['where']) ? '' : ' WHERE '.implode(' AND ', array_unique($sqlParts['where']));
		$sqlGroup = empty($sqlParts['group']) ? '' : ' GROUP BY '.implode(',', array_unique($sqlParts['group']));
		$sqlOrder = empty($sqlParts['order']) ? '' : ' ORDER BY '.implode(',', array_unique($sqlParts['order']));

		return 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				$sqlWhere.
				$sqlGroup.
				$sqlOrder;
	}

	/**
	 * Modifies the SQL parts to implement all of the output related options.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array  $options
	 * @param array  $sqlParts
	 *
	 * @return array		The resulting SQL parts array
	 */
	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$pkFieldId = $this->fieldId($this->pk($tableName), $tableAlias);

		// count
		if (isset($options['countOutput']) && !$this->requiresPostSqlFiltering($options)) {
			$sqlParts['select'] = ['COUNT(DISTINCT '.$pkFieldId.') AS rowscount'];

			// select columns used by group count
			if (isset($options['groupCount'])) {
				foreach ($sqlParts['group'] as $fields) {
					$sqlParts['select'][] = $fields;
				}
			}
		}
		// custom output
		elseif (is_array($options['output'])) {
			// the pk field must always be included for the API to work properly
			$sqlParts['select'] = [$pkFieldId];
			foreach ($options['output'] as $field) {
				if ($this->hasField($field, $tableName)) {
					$sqlParts['select'][] = $this->fieldId($field, $tableAlias);
				}
			}

			$sqlParts['select'] = array_unique($sqlParts['select']);
		}
		// extended output
		elseif ($options['output'] == API_OUTPUT_EXTEND) {
			// TODO: API_OUTPUT_EXTEND must return ONLY the fields from the base table
			$sqlParts = $this->addQuerySelect($this->fieldId('*', $tableAlias), $sqlParts);
		}

		return $sqlParts;
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
	 * Modifies the SQL parts to implement all of the sorting related options.
	 * Sorting is currently only supported for CApiService::get() methods.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array  $options
	 * @param array  $sqlParts
	 *
	 * @return array
	 */
	protected function applyQuerySortOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if ($this->sortColumns && !zbx_empty($options['sortfield'])) {
			$options['sortfield'] = is_array($options['sortfield'])
				? array_unique($options['sortfield'])
				: [$options['sortfield']];

			foreach ($options['sortfield'] as $i => $sortfield) {
				// validate sortfield
				if (!str_in_array($sortfield, $this->sortColumns)) {
					throw new APIException(ZBX_API_ERROR_INTERNAL, _s('Sorting by field "%1$s" not allowed.', $sortfield));
				}

				// add sort field to order
				$sortorder = '';
				if (is_array($options['sortorder'])) {
					if (!empty($options['sortorder'][$i])) {
						$sortorder = ($options['sortorder'][$i] == ZBX_SORT_DOWN) ? ' '.ZBX_SORT_DOWN : '';
					}
				}
				else {
					$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN) ? ' '.ZBX_SORT_DOWN : '';
				}

				$sqlParts = $this->applyQuerySortField($sortfield, $sortorder, $tableAlias, $sqlParts);
			}
		}

		return $sqlParts;
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
		if (count($sqlParts['from']) > 1
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
	 * Adds the given field to the ORDER BY part of the $sqlParts array.
	 *
	 * @param string $fieldId
	 * @param array  $sqlParts
	 * @param string $sortorder		sort direction, ZBX_SORT_UP or ZBX_SORT_DOWN
	 *
	 * @return array
	 */
	protected function addQueryOrder($fieldId, array $sqlParts, $sortorder = null) {
		// some databases require the sortable column to be present in the SELECT part of the query
		$sqlParts = $this->addQuerySelect($fieldId, $sqlParts);

		$sqlParts['order'][$fieldId] = $fieldId.($sortorder ? ' '.$sortorder : '');

		return $sqlParts;
	}

	/**
	 * Adds the related objects requested by "select*" options to the resulting object set.
	 *
	 * @param array $options
	 * @param array $result		an object hash with PKs as keys

	 * @return array mixed
	 */
	protected function addRelatedObjects(array $options, array $result) {
		// must be implemented in each API separately

		return $result;
	}

	/**
	 * Deletes the object with the given IDs with respect to relative objects.
	 *
	 * The method must be extended to handle relative objects.
	 *
	 * @param array $ids
	 */
	protected function deleteByIds(array $ids) {
		DB::delete($this->tableName(), [
			$this->pk() => $ids
		]);
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
	 * An extendObjects() wrapper for singular objects.
	 *
	 * @see extendObjects()
	 *
	 * @param string $tableName
	 * @param array  $object
	 * @param array  $fields
	 *
	 * @return mixed
	 */
	protected function extendObject($tableName, array $object, array $fields) {
		$objects = $this->extendObjects($tableName, [$object], $fields);

		return reset($objects);
	}

	/**
	 * For each object in $objects the method copies fields listed in $fields that are not present in the target
	 * object from from the source object.
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
	 * Checks that each object has a valid ID.
	 *
	 * @param array $objects
	 * @param $idField			name of the field that contains the id
	 * @param $messageRequired	error message if no ID is given
	 * @param $messageEmpty		error message if the ID is empty
	 * @param $messageInvalid	error message if the ID is invalid
	 */
	protected function checkObjectIds(array $objects, $idField, $messageRequired, $messageEmpty, $messageInvalid) {
		$idValidator = new CIdValidator([
			'messageEmpty' => $messageEmpty,
			'messageInvalid' => $messageInvalid
		]);
		foreach ($objects as $object) {
			if (!isset($object[$idField])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _params($messageRequired, [$idField]));
			}

			$this->checkValidator($object[$idField], $idValidator);
		}
	}

	/**
	 * Checks if the object has any fields, that are not defined in the schema or in $extraFields.
	 *
	 * @param string $tableName
	 * @param array  $object
	 * @param string $error
	 * @param array  $extraFields	an array of field names, that are not present in the schema, but may be
	 *								used in requests
	 *
	 * @throws APIException
	 */
	protected function checkUnsupportedFields($tableName, array $object, $error, array $extraFields = []) {
		$extraFields = array_flip($extraFields);

		foreach ($object as $field => $value) {
			if (!DB::hasField($tableName, $field) && !isset($extraFields[$field])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
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
	 * @static
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
		trigger_error($error, E_USER_NOTICE);
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
			// skip missing fields and text fields (not supported by Oracle)
			// skip empty values
			if (!isset($tableSchema['fields'][$field]) || $tableSchema['fields'][$field]['type'] == DB::FIELD_TYPE_TEXT
					|| zbx_empty($value)) {
				continue;
			}

			zbx_value2array($value);

			$fieldName = $this->fieldId($field, $tableShort);
			$filter[$field] = DB::isNumericFieldType($tableSchema['fields'][$field]['type'])
				? dbConditionInt($fieldName, $value)
				: dbConditionString($fieldName, $value);
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
	 * Check if a set of parameters contains a deprecated parameter or a a parameter with a deprecated value.
	 * If $value is not set, the method will trigger a deprecated notice if $params contains the $paramName key.
	 * If $value is set, the method will trigger a notice if the value of the parameter is equal to the deprecated value
	 * or the parameter is an array and contains a deprecated value.
	 *
	 * @param array  $params
	 * @param string $paramName
	 * @param string $value
	 *
	 * @return void
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
}
