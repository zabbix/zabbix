<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CZBXAPI {

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
	protected $sortColumns = array();

	/**
	 * An array of allowed get() options that are supported by all APIs.
	 *
	 * @var array
	 */
	protected $globalGetOptions = array();

	/**
	 * An array containing all of the allowed get() options for the current API.
	 *
	 * @var array
	 */
	protected $getOptions = array();

	/**
	 * An array containing all of the error strings.
	 *
	 * @var array
	 */
	protected $errorMessages = array();

	public function __construct() {
		// set the PK of the table
		$this->pk = $this->pk($this->tableName());

		$this->globalGetOptions = array(
			'nodeids'				=> null,
			// filter
			'filter'				=> null,
			'search'				=> null,
			'searchByAny'			=> null,
			'startSearch'			=> null,
			'excludeSearch'			=> null,
			'searchWildcardsEnabled'=> null,
			// output
			'output'				=> API_OUTPUT_REFER,
			'countOutput'			=> null,
			'groupCount'			=> null,
			'preservekeys'			=> null,
			'limit'					=> null
		);
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
		$tableName = !empty($tableName) ? $tableName : $this->tableName();
		$tableAlias = !empty($tableAlias) ? $tableAlias : $this->tableAlias();

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
		$tableAlias = ($tableAlias) ? $tableAlias : $this->tableAlias();

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
		$tableName = ($tableName) ? $tableName : $this->tableName();

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
	 * @param string $tableName
	 * @param string|array $fields  either a single field name, or an array of fields
	 * @param string $output
	 *
	 * @return mixed
	 */
	protected function extendOutputOption($tableName, $fields, $output) {
		$fields = (array) $fields;

		foreach ($fields as $field) {
			if ($output == API_OUTPUT_REFER) {
				$output = array(
					$this->pk($tableName),
					$field
				);
			}
			if (is_array($output) && !in_array($field, $output)) {
				$output[] = $field;
			}
		}

		return $output;
	}

	/**
	 * Unsets the fields that haven't been explicitly asked for by the user, but
	 * have been included in the resulting object for whatever reasons.
	 *
	 * If the $option parameter is set to API_OUTPUT_EXTEND or to API_OUTPUT_REFER, return the result as is.
	 * If the $option parameter is an array of fields, return only them.
	 *
	 * @param array $object			The object from the database
	 * @param array $output			The original requested output
	 *
	 * @return array				The resulting object
	 */
	protected function unsetExtraFields(array $object, $output) {
		// if specific fields where requested, return only them
		if (is_array($output)) {
			foreach ($object as $field => $value) {
				if (!in_array($field, $output)) {
					unset($object[$field]);
				}
			}
		}

		return $object;
	}

	/**
	 * Constructs an SQL SELECT query for a specific table from the given API options, executes it and returns
	 * the result.
	 *
	 * TODO: add global 'countOutput' support
	 *
	 * @param string $tableName
	 * @param array $options
	 *
	 * @return array
	 */
	protected function select($tableName, array $options) {
		$limit = isset($options['limit']) ? $options['limit'] : null;

		$sql = $this->createSelectQuery($tableName, $options);
		$query = DBSelect($sql, $limit);

		$objects = DBfetchArray($query);

		if (isset($options['preservekeys'])) {
			$rs = array();
			foreach ($objects as $object) {
				$rs[$object[$this->pk($tableName)]] = $this->unsetExtraFields($object, $options['output']);
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
	 * @param $tableName
	 * @param array $options
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
	 * @param array $options
	 *
	 * @return array         The resulting SQL parts array
	 */
	protected function createSelectQueryParts($tableName, $tableAlias, array $options) {
		// extend default options
		$options = zbx_array_merge($this->globalGetOptions, $options);

		$sqlParts = array(
			'select' => array($this->fieldId($this->pk($tableName), $tableAlias)),
			'from' => array($this->tableId($tableName, $tableAlias)),
			'where' => array(),
			'group' => array(),
			'order' => array(),
			'limit' => null
		);

		// add filter options
		$sqlParts = $this->applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

		// add output options
		$sqlParts = $this->applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		// add node options
		$sqlParts = $this->applyQueryNodeOptions($tableName, $tableAlias, $options, $sqlParts);

		// add sort options
		$sqlParts = $this->applyQuerySortOptions($tableName, $tableAlias, $options, $sqlParts);

		return $sqlParts;
	}

	/**
	 * Creates a SELECT SQL query from the given SQL parts array.
	 *
	 * @param array $sqlParts  An SQL parts array
	 *
	 * @return string          The resulting SQL query
	 */
	protected function createSelectQueryFromParts(array $sqlParts) {
		// build query
		$sqlSelect = implode(',', array_unique($sqlParts['select']));
		$sqlFrom = implode(',', array_unique($sqlParts['from']));
		$sqlWhere = (!empty($sqlParts['where'])) ? ' WHERE '.implode(' AND ', array_unique($sqlParts['where'])) : '';
		$sqlGroup = (!empty($sqlParts['group'])) ? ' GROUP BY '.implode(',', array_unique($sqlParts['group'])) : '';
		$sqlOrder = (!empty($sqlParts['order'])) ? ' ORDER BY '.implode(',', array_unique($sqlParts['order'])) : '';
		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				$sqlWhere.
				$sqlGroup.
				$sqlOrder;

		return $sql;
	}

	/**
	 * Modifies the SQL parts to implement all of the output related options.
	 *
	 * @param string $tableName
	 * @param string $tableAlias
	 * @param array $options
	 * @param array $sqlParts
	 *
	 * @return array         The resulting SQL parts array
	 */
	protected function applyQueryOutputOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$pkFieldId = $this->fieldId($this->pk($tableName), $tableAlias);

		// count
		if (isset($options['countOutput'])) {
			$sqlParts['select'] = array('COUNT(DISTINCT '.$pkFieldId.') AS rowscount');

			// select columns used by group count
			if ($options['groupCount'] !== null) {
				foreach ($sqlParts['group'] as $fields) {
					$sqlParts['select'][] = $fields;
				}
			}
		}
		// custom output
		elseif (is_array($options['output'])) {
			// the pk field must always be included for the API to work properly
			$sqlParts['select'] = array($pkFieldId);
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
			$sqlParts['select'][] = $this->fieldId('*', $tableAlias);
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
	 * @return array         The resulting SQL parts array
	 */
	protected function applyQueryFilterOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$pkOption = $this->pkOption($tableName);
		$tableId = $this->tableId($tableName, $tableAlias);

		// pks
		if (isset($options[$pkOption])) {
			zbx_value2array($options[$pkOption]);
			$sqlParts['where'][] = DBcondition($this->fieldId($this->pk($tableName), $tableAlias), $options[$pkOption]);
		}

		// filters
		if (is_array($options['filter'])) {
			zbx_db_filter($tableId, $options, $sqlParts);
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search($tableId, $options, $sqlParts);
		}

		return $sqlParts;
	}

	/**
	 * Modifies the SQL parts to implement all of the node related options.
	 *
	 * @param $tableName
	 * @param $tableAlias
	 * @param array $options
	 * @param array $sqlParts
	 *
	 * @return array
	 */
	protected function applyQueryNodeOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		$pkOption = $this->pkOption($tableName);
		$pkFieldId = $this->fieldId($this->pk($tableName), $tableAlias);

		// if no specific ids are given, apply the node filter
		if (!isset($options[$pkOption])) {
			$nodeids = (isset($options['nodeids'])) ? $options['nodeids'] : get_current_nodeid();
			$sqlParts['where'][] = DBin_node($pkFieldId, $nodeids);
		}

		return $sqlParts;
	}

	/**
	 * Modifies the SQL parts to implement all of the sorting related options.
	 * Soring is currently only supported for CZBXAPI::get() methods.
	 *
	 * @param $tableName
	 * @param $tableAlias
	 * @param array $options
	 * @param array $sqlParts
	 *
	 * @return array
	 */
	protected function applyQuerySortOptions($tableName, $tableAlias, array $options, array $sqlParts) {
		if ($this->sortColumns && $options['countOutput'] === null) {
			zbx_db_sorting($sqlParts, $options, $this->sortColumns, $tableAlias);
		}

		return $sqlParts;
	}

	/**
	 * Adds the given field to the SELECT part of the $sqlParts array if it's not already present.
	 *
	 * @param string $fieldId
	 * @param array $sqlParts
	 *
	 * @return array
	 */
	protected function addQuerySelect($fieldId, array $sqlParts) {
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
	 * @param $fieldId
	 * @param array $sqlParts
	 * @param string $sortorder     sort direction, ZBX_SORT_UP or ZBX_SORT_DOWN
	 *
	 * @return array
	 */
	protected function addQueryOrder($fieldId, array $sqlParts, $sortorder = null) {
		// some databases require the sortable column to be present in the SELECT part of the query
		$sqlParts = $this->addQuerySelect($fieldId, $sqlParts);

		$sqlParts['order'][] = $fieldId.(($sortorder) ? ' '.$sortorder : '');

		return $sqlParts;
	}

	/**
	 * Adds the related objects requested by "select*" options to the resulting object set.
	 *
	 * @param array $options
	 * @param array $result     an object hash with PKs as keys
	 * @return array mixed
	 */
	protected function addRelatedObjects(array $options, array $result) {
		// must be implemented in each API separately

		return $result;
	}

	/**
	 * Deletes the object with the given PKs with respect to relative objects.
	 *
	 * The method must be extended to handle relative objects.
	 *
	 * @param array $pks
	 */
	protected function deleteByPks(array $pks) {
		DB::delete($this->tableName(), array(
			$this->pk() => $pks
		));
	}

	/**
	 * Fetches the fields given in $fields from the database and extends the objects with the loaded data.
	 *
	 * @param $tableName
	 * @param array $objects
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function extendObjects($tableName, array $objects, array $fields) {
		$dbObjects = API::getApi()->select($tableName, array(
			'output' => $fields,
			$this->pkOption($tableName) => zbx_objectValues($objects, $this->pk($tableName)),
			'preservekeys' => true
		));

		foreach ($objects as &$object) {
			$pk = $object[$this->pk($tableName)];
			if (isset($dbObjects[$pk])) {
				check_db_fields($dbObjects[$pk], $object);
			}
		}

		return $objects;
	}

	/**
	 * An extendObjects() wrapper for singular objects.
	 *
	 * @see extendObjects()
	 *
	 * @param $tableName
	 * @param array $object
	 * @param array $fields
	 *
	 * @return mixed
	 */
	protected function extendObject($tableName, array $object, array $fields) {
		$objects = $this->extendObjects($tableName, array($object), $fields);
		return reset($objects);
	}

	/**
	 * Checks if the object has any fields, that are not defined in the schema or in $additionalFields.
	 *
	 * @param $tableName
	 * @param array $object
	 * @param $error
	 * @param array $extraFields    an array of field names, that are not present in the schema, but may be
	 *                              used in requests
	 *
	 * @throws APIException
	 */
	protected function checkUnsupportedFields($tableName, array $object, $error, array $extraFields = array()) {
		$extraFields = array_flip($extraFields);

		foreach ($object as $field => $value) {
			if (!DB::hasField($tableName, $field) && !isset($extraFields[$field])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $error);
			}
		}
	}

	/**
	 * Throws an API exception.
	 *
	 * @param type $code
	 * @param type $error
	 */
	protected static function exception($code = ZBX_API_ERROR_INTERNAL, $error = '') {
		throw new APIException($code, $error);
	}
}
