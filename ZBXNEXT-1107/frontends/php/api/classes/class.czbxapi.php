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
?>
<?php
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
	protected $tableAlias;


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


	public function __construct() {
		// set the PK of the table
		$this->pk = $this->pk($this->tableName());

		$this->globalGetOptions = array(
			'nodeids'					=> null,

			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,

			// output
			'output'					=> API_OUTPUT_REFER,
			'limit'						=> null
		);
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
		$tableName = ($tableName) ? $tableName : $this->tableName();
		$tableAlias = ($tableAlias) ? $tableAlias : $this->tableAlias();

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
	 * Adds the given field to the "output" option if it's not already present.
	 *
	 * @param string $tableName
	 * @param string $field
	 * @param string $output
	 *
	 * @return mixed
	 */
	protected function extendOutputOption($tableName, $field, $output) {
		if ($output == API_OUTPUT_SHORTEN || $output == API_OUTPUT_REFER) {
			$output = array(
				$this->pk($tableName),
				$field
			);
		}
		if (is_array($output) && !in_array($field, $output)) {
			$output[] = $field;
		}

		return $output;
	}


	/**
	 * Unsets the fields that haven't been explicitly asked for by the user, but
	 * have been included in the resulting object for whatever reasons.
	 *
	 * If the $option parameter is set to API_OUTPUT_SHORT, return only the private key.
	 * If the $option parameter is set to API_OUTPUT_EXTEND or to API_OUTPUT_REFER, return the result as is.
	 * If the $option parameter is an array of fields, return only them.
	 *
	 * @param string $tableName      The table that stores the object
	 * @param array $object          The object from the database
	 * @param array $output          The original requested output
	 *
	 * @return array           The resulting object
	 */
	protected function unsetExtraFields($tableName, array $object, $output) {

		// for API_OUTPUT_SHORTEN return only the private key
		if ($output == API_OUTPUT_SHORTEN) {
			$pkField = $this->pk($tableName);

			if (isset($object[$pkField])) {
				$object = array($pkField => $object[$pkField]);
			}
			else {
				$object = array();
			}
		}
		// if specific fields where requested, return only them
		elseif (is_array($output)) {
			foreach ($object as $field => $value) {
				if (!in_array($field, $output)) {
					unset($object[$field]);
				}
			}
		}

		return $object;
	}


	/**
	 * Constructs an SQL SELECT query from the given options, executes it and returns the result.
	 *
	 * TODO: add global 'preservekeys' support
	 * TODO: add global 'countOutput' support
	 *
	 * @param string $tableName
	 * @param array $options
	 *
	 * @return array
	 */
	public function select($tableName, array $options) {
		$limit = (isset($options['limit'])) ? $options['limit'] : null;

		$sql = $this->createSelectQuery($tableName, $options);
		$query = DBSelect($sql, $limit);

		return DBfetchArray($query);
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
		$sqlParts = $this->createSelectQueryParts($tableName, 't', $options);

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

		// add output options
		$sqlParts = $this->applyQueryOutputOptions($tableName, $tableAlias, $options, $sqlParts);

		// add filter options
		$sqlParts = $this->applyQueryFilterOptions($tableName, $tableAlias, $options, $sqlParts);

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
		$sqlWhere = $sqlParts['where'] ? implode(' AND ', $sqlParts['where']) : '';
		$sqlGroup = $sqlParts['group'] ? ' GROUP BY '.implode(',', $sqlParts['group']) : '';
		$sqlOrder = $sqlParts['order'] ? ' ORDER BY '.implode(',', $sqlParts['order']) : '';
		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
			' FROM '.$sqlFrom.
			' WHERE '.$sqlWhere.
			$sqlGroup.
			$sqlOrder;

		return $sql;
	}


	/**
	 * Modifies the SQL parts to implement all of the ouput related options.
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
		}
		// custom output
		elseif (is_array($options['output'])) {
			$sqlParts['select'] = array();
			foreach ($options['output'] as $field) {
				if ($this->hasField($field, $tableName)) {
					$sqlParts['select'][] = $this->fieldId($field, $tableAlias);
				}
			}

			// make sure the id is included if the 'preservekeys' option is enabled
			if (isset($options['preservekeys'])) {
				$sqlParts['select'][] = $pkFieldId;
			}
		}
		// extended output
		elseif ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select'] = array($this->fieldId('*', $tableAlias));
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
			$nodeids = (isset($options['nodeids'])) ? $options[$pkOption] : get_current_nodeid();
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
		if ($this->sortColumns) {
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
	protected function extendQuerySelect($fieldId, array $sqlParts) {
		list($tableAlias,) = explode('.', $fieldId);

		if (!in_array($fieldId, $sqlParts['select']) && !in_array($this->fieldId('*', $tableAlias), $sqlParts['select'])) {
			$sqlParts['select'][] = $fieldId;
		}

		return $sqlParts;
	}


	/**
	 * Adds the related objects requested by "select*" options to the resulting object set.
	 *
	 * @param array $options
	 * @param array $result
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
	 * Throws an API exception.
	 *
	 * @param type $code
	 * @param type $error
	 */
	protected static function exception($code = ZBX_API_ERROR_INTERNAL, $error = '') {
		throw new APIException($code, $error);
	}
}
?>
