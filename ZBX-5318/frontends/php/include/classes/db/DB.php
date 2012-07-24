<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


class DB {

	const SCHEMA_FILE = 'include/schema.inc.php';

	const DBEXECUTE_ERROR = 1;
	const RESERVEIDS_ERROR = 2;
	const SCHEMA_ERROR = 3;
	const INPUT_ERROR = 4;

	const TABLE_TYPE_CONFIG = 1;
	const TABLE_TYPE_HISTORY = 2;

	const FIELD_TYPE_INT = 'int';
	const FIELD_TYPE_CHAR = 'char';
	const FIELD_TYPE_ID = 'id';
	const FIELD_TYPE_FLOAT = 'float';
	const FIELD_TYPE_UINT = 'uint';
	const FIELD_TYPE_BLOB = 'blob';
	const FIELD_TYPE_TEXT = 'text';

	private static $schema = null;
	private static $nodeId = null;
	private static $maxNodeId = null;
	private static $minNodeId = null;

	private static function exception($code, $error) {
		throw new DBException($error, $code);
	}

	/**
	 * Initializes nodes.
	 *
	 * @static
	 */
	public static function init() {
		global $ZBX_LOCALNODEID;

		if (is_null(self::$nodeId)) {
			self::$nodeId = get_current_nodeid(false);
			self::$minNodeId = bcadd(bcmul(self::$nodeId, '100000000000000'), bcmul($ZBX_LOCALNODEID, '100000000000'), 0);
			self::$maxNodeId = bcadd(self::$minNodeId, '99999999999', 0);
		}
	}


	/**
	 * Reserve ids for primary key of passed table.
	 * If record for table does not exist or value is out of range, ids record is recreated
	 * using maximum id from table or minimum allowed value.
	 *
	 * @throw APIException
	 * @static
	 *
	 * @param string $table table name
	 * @param int $count number of ids to reserve
	 *
	 * @return string
	 */
	protected static function reserveIds($table, $count) {
		global $DB;

		self::init();

		$tableSchema = self::getSchema($table);
		$id_name = $tableSchema['key'];

		$sql = 'SELECT nextid'.
				' FROM ids'.
				' WHERE nodeid='.self::$nodeId.
					' AND table_name='.zbx_dbstr($table).
					' AND field_name='.zbx_dbstr($id_name);

		// SQLite3 does not support this syntax. Since we are in transaction, it can be ignored.
		if ($DB['TYPE'] != ZBX_DB_SQLITE3) {
			$sql = $sql.' FOR UPDATE';
		}

		$res = DBfetch(DBselect($sql));
		if ($res) {
			$maxNextId = bcadd($res['nextid'], $count, 0);
			if (bccomp($maxNextId, self::$maxNodeId, 0) == 1 || bccomp($maxNextId, self::$minNodeId, 0) == -1) {
				$nextid = self::refreshIds($table, $count);
			}
			else {
				$sql = 'UPDATE ids'.
						' SET nextid=nextid+'.$count.
						' WHERE nodeid='.self::$nodeId.
							' AND table_name='.zbx_dbstr($table).
							' AND field_name='.zbx_dbstr($id_name);
				if (!DBexecute($sql)) {
					self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
				}
				$nextid = bcadd($res['nextid'], 1, 0);
			}
		}
		else {
			$nextid = self::refreshIds($table, $count);
		}
		return $nextid;
	}

	/**
	 * Refresh id record for given table.
	 * Record is deleted and then created again with value of maximum id from table or minimu allowed.
	 *
	 * @throw APIException
	 * @static
	 *
	 * @param string $table table name
	 * @param int $count number of ids to reserve
	 *
	 * @return string
	 */
	private static function refreshIds($table, $count) {
		self::init();

		$tableSchema = self::getSchema($table);
		$id_name = $tableSchema['key'];

		$sql = 'DELETE FROM ids'.
				' WHERE nodeid='.self::$nodeId.
				' AND table_name='.zbx_dbstr($table).
				' AND field_name='.zbx_dbstr($id_name);
		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
		}

		$sql = 'SELECT MAX('.$id_name.') AS id'.
				' FROM '.$table.
				' WHERE '.$id_name.'>='.self::$minNodeId.
				' AND '.$id_name.'<='.self::$maxNodeId;
		$row = DBfetch(DBselect($sql));

		$nextid = ($row && $row['id']) ? $row['id'] : self::$minNodeId;

		$maxNextId = bcadd($nextid, $count, 0);
		if (bccomp($maxNextId, self::$maxNodeId, 0) == 1) {
			self::exception(self::RESERVEIDS_ERROR, __METHOD__.' ID greater than maximum allowed for table "'.$table.'"');
		}

		$sql = 'INSERT INTO ids (nodeid,table_name,field_name,nextid)'.
				' VALUES ('.self::$nodeId.','.zbx_dbstr($table).','.zbx_dbstr($id_name).','.$maxNextId.')';
		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
		}

		$nextid = bcadd($nextid, 1, 0);
		return $nextid;
	}

	/**
	 * Returns the array describing the database schema.
	 *
	 * If the $table parameter is passed, the method will return the schema for the given table,
	 * otherwise - for the whole database.
	 *
	 * @static
	 *
	 * @throws APIException if the given table does not exist
	 *
	 * @param string $table
	 *
	 * @return array
	 */
	public static function getSchema($table = null) {
		if (is_null(self::$schema)) {

			self::$schema = include(Z::getRootDir().'/'.self::SCHEMA_FILE);
		}

		if (is_null($table)) {
			return self::$schema;
		}
		elseif (isset(self::$schema[$table])) {
			return self::$schema[$table];
		}
		else {
			self::exception(self::SCHEMA_ERROR, _s('Table "%1$s" does not exist.', $table));
		}
	}

	/**
	 * Returns the names of the fields that are used as the primary key of the table.
	 *
	 * @static
	 *
	 * @param string $tableName
	 *
	 * @return string|array
	 */
	protected static function getPk($tableName) {
		$schema = self::getSchema($tableName);
		if (strpos($schema['key'], ',') !== false) {
			return explode(',', $schema['key']);
		}
		else {
			return $schema['key'];
		}
	}

	/**
	 * Returns true if the table $tableName has the $fieldName field.
	 *
	 * @static
	 *
	 * @param string $tableName
	 * @param string $fieldName
	 *
	 * @return bool
	 */
	public static function hasField($tableName, $fieldName) {
		$schema = self::getSchema($tableName);

		return isset($schema['fields'][$fieldName]);
	}

	private static function addMissingFields($tableSchema, $values) {
		global $DB;

		if ($DB['TYPE'] == ZBX_DB_MYSQL) {
			foreach ($tableSchema['fields'] as $name => $field) {
				if ($field['type'] == DB::FIELD_TYPE_TEXT && !$field['null']) {
					foreach ($values as &$value) {
						if (!isset($value[$name])) {
							$value[$name] = '';
						}
					}
					unset($value);
				}
			}
		}

		return $values;
	}

	public static function getDefaults($table) {
		$table = self::getSchema($table);

		$defaults = array();
		foreach ($table['fields'] as $name => $field) {
			if (isset($field['default'])) {
				$defaults[$name] = $field['default'];
			}
		}
		return $defaults;
	}

	public static function checkValueTypes($table, &$values) {
		global $DB;
		$tableSchema = self::getSchema($table);

		foreach ($values as $field => $value) {
			if (!isset($tableSchema['fields'][$field])) {
				unset($values[$field]);
				continue;
			}

			if (is_null($values[$field])) {
				if ($tableSchema['fields'][$field]['null']) {
					$values[$field] = 'NULL';
				}
				elseif (isset($tableSchema['fields'][$field]['default'])) {
					$values[$field] = $tableSchema['fields'][$field]['default'];
				}
				else {
					self::exception(self::DBEXECUTE_ERROR, _s('Mandatory field "%1$s" is missing in table "%2$s".', $field, $table));
				}
			}

			if (isset($tableSchema['fields'][$field]['ref_table'])) {
				if ($tableSchema['fields'][$field]['null']) {
					$values[$field] = zero2null($values[$field]);
				}
			}

			if ($values[$field] === 'NULL') {
				if (!$tableSchema['fields'][$field]['null']) {
					self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "NULL" for NOT NULL field "%1$s".', $field));
				}
			}
			else {
				switch ($tableSchema['fields'][$field]['type']) {
					case self::FIELD_TYPE_CHAR:
						$length = zbx_strlen($values[$field]);
						$values[$field] = zbx_dbstr($values[$field]);

						if ($length > $tableSchema['fields'][$field]['length']) {
							self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is %4$d characters.',
								$values[$field], $field, $length, $tableSchema['fields'][$field]['length']));
						}
						break;
					case self::FIELD_TYPE_ID:
					case self::FIELD_TYPE_UINT:
						if (!zbx_ctype_digit($values[$field])) {
							self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for unsigned int field "%2$s".', $values[$field], $field));
						}
						$values[$field] = zbx_dbstr($values[$field]);
						break;
					case self::FIELD_TYPE_INT:
						if (!zbx_is_int($values[$field])) {
							self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for int field "%2$s".', $values[$field], $field));
						}
						$values[$field] = zbx_dbstr($values[$field]);
						break;
					case self::FIELD_TYPE_FLOAT:
						if (!is_numeric($values[$field])) {
							self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for float field "%2$s".', $values[$field], $field));
						}
						$values[$field] = zbx_dbstr($values[$field]);
						break;
					case self::FIELD_TYPE_TEXT:
						$length = zbx_strlen($values[$field]);
						$values[$field] = zbx_dbstr($values[$field]);

						if ($DB['TYPE'] == ZBX_DB_DB2) {
							if ($length > 2048) {
								self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is 2048 characters.',
									$values[$field], $field, $length));
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Returns the records that match the given criteria.
	 *
	 * @static
	 *
	 * @param string $tableName
	 * @param array $criteria   An associative array of field-value pairs, where value can be either a single value
	 *                          or an array (IN)
	 *
	 * @return array
	 */
	public static function find($tableName, array $criteria = array()) {
		// build the WHERE part
		$sqlWhere = array();
		foreach ($criteria as $field => $value) {
			// check if the table has this field
			if (!self::hasField($tableName, $field)) {
				self::exception(self::DBEXECUTE_ERROR, _s('Table "%1$s" doesn\'t have a field named "%2$s".', $tableName, $field));
			}

			$sqlWhere[] = DBcondition($field, zbx_toArray($value));
		}

		// build query
		$sql = 'SELECT * FROM '.$tableName;
		if ($sqlWhere) {
			$sql .= ' WHERE '.implode(' AND ', $sqlWhere);
		}

		return DBfetchArray(DBSelect($sql));
	}

	/**
	 * Insert data into DB
	 *
	 * @param string $table
	 * @param array  $values pair of fieldname => fieldvalue
	 * @param bool   $getids
	 *
	 * @return array of ids
	 */
	public static function insert($table, $values, $getids = true) {
		if (empty($values)) {
			return true;
		}
		$resultIds = array();

		if ($getids) {
			$id = self::reserveIds($table, count($values));
		}

		$tableSchema = self::getSchema($table);

		$values = self::addMissingFields($tableSchema, $values);

		foreach ($values as $key => $row) {
			if ($getids) {
				$resultIds[$key] = $id;
				$row[$tableSchema['key']] = $id;
				$id = bcadd($id, 1, 0);
			}

			self::checkValueTypes($table, $row);

			$sql = 'INSERT INTO '.$table.' ('.implode(',', array_keys($row)).')'.
					' VALUES ('.implode(',', array_values($row)).')';

			if (!DBexecute($sql)) {
				self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s".', $sql));
			}
		}
		return $resultIds;
	}

	/**
	 * Update data in DB
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $data[...]['values'] pair of fieldname => fieldvalue for SET clause
	 * @param array $data[...]['where'] pair of fieldname => fieldvalue for WHERE clause
	 * @return array of ids
	 */
	public static function update($table, $data) {
		if (empty($data)) {
			return true;
		}
		$tableSchema = self::getSchema($table);

		$data = zbx_toArray($data);
		foreach ($data as $row) {
			// check
			self::checkValueTypes($table, $row['values']);
			if (empty($row['values'])) {
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%1$s" without values.', $table));
			}

			// set creation
			$sqlSet = '';
			foreach ($row['values'] as $field => $value) {
				$sqlSet .= ' '.$field.'='.$value.',';
			}
			$sqlSet = rtrim($sqlSet, ',');

			if (!isset($row['where']) || empty($row['where']) || !is_array($row['where'])) {
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%1$s" without where condition.', $table));
			}

			// where condition proccess
			$sqlWhere = array();
			foreach ($row['where'] as $field => $values) {
				if (!isset($tableSchema['fields'][$field]) || is_null($values)) {
					self::exception(self::DBEXECUTE_ERROR, _s('Incorrect field "%1$s" name or value in where statement for table "%2$s".', $field, $table));
				}
				$values = zbx_toArray($values);
				sort($values); // sorting ids to prevent deadlocks when two transactions depends from each other

				$sqlWhere[] = DBcondition($field, $values);
			}

			// sql execution
			$sql = 'UPDATE '.$table.' SET '.$sqlSet.' WHERE '.implode(' AND ', $sqlWhere);
			if (!DBexecute($sql)) {
				self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s".', $sql));
			}
		}
		return true;
	}

	/**
	 * Updates the values by the given PK.
	 *
	 * @static
	 *
	 * @param string $tableName
	 * @param mixed $pk         A single PK value or an associative array of values,
	 *                          e.g. array('field1' => 'value1', 'field2' => 'value2')
	 * @param array $values
	 *
	 * @return bool
	 */
	public static function updateByPk($tableName, $pk, array $values) {
		$dbPkNames = self::getPk($tableName);

		if (is_array($pk)) {
			if (!is_array($dbPkNames)) {
				self::exception(self::INPUT_ERROR, _s('Table "%1$s" has a simple primary key, composite is given.', $tableName));
			}

			if (!array_equal(array_keys($pk), $dbPkNames)) {
				self::exception(self::INPUT_ERROR, _s('Incorrect primary keys for table "%1$s".', $tableName));
			}
		}
		else {
			if (is_array($dbPkNames)) {
				self::exception(self::INPUT_ERROR, _s('Table "%1$s" has a composite primary key, simple is given.', $tableName));
			}

			$pk = array($dbPkNames => $pk);
		}

		return self::update($tableName, array(
			'where' => $pk,
			'values' => $values
		));
	}

	/**
	 * Delete data from DB
	 *
	 * Example:
	 * DB::delete('applications', array('applicationid'=>array(1, 8, 6)));
	 * DELETE FROM applications WHERE applicationid IN (1, 8, 6)
	 *
	 * DB::delete('applications', array('applicationid'=>array(1), 'templateid'=array(10)));
	 * DELETE FROM applications WHERE applicationid IN (1) AND templateid IN (10)
	 *
	 * @param string $table
	 * @param array  $wheres pair of fieldname => fieldvalues
	 * @param bool   $use_or
	 *
	 * @return bool
	 */
	public static function delete($table, $wheres, $use_or = false) {
		if (empty($wheres) || !is_array($wheres)) {
			self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform delete statement on table "%1$s" without where condition.', $table));
		}
		$table_schema = self::getSchema($table);

		$sqlWhere = array();
		foreach ($wheres as $field => $values) {
			if (!isset($table_schema['fields'][$field]) || is_null($values)) {
				self::exception(self::DBEXECUTE_ERROR, _s('Incorrect field "%1$s" name or value in where statement for table "%2$s".', $field, $table));
			}
			$values = zbx_toArray($values);
			sort($values); // sorting ids to prevent deadlocks when two transactions depends from each other

			$sqlWhere[] = DBcondition($field, $values);
		}

		$sql = 'DELETE FROM '.$table.' WHERE '.implode(($use_or ? ' OR ' : ' AND '), $sqlWhere);
		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s"', $sql));
		}
		return true;
	}
}
