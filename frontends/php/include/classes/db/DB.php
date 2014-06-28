<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class DB {

	const SCHEMA_FILE = 'schema.inc.php';

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

	/**
	 * @var DbBackend
	 */
	private static $dbBackend;

	/**
	 * Get necessary DB class.
	 *
	 * @return DbBackend
	 */
	public static function getDbBackend() {
		global $DB;

		if (!self::$dbBackend) {
			switch ($DB['TYPE']) {
				case ZBX_DB_MYSQL:
					self::$dbBackend = new MysqlDbBackend();
					break;
				case ZBX_DB_POSTGRESQL:
					self::$dbBackend = new PostgresqlDbBackend();
					break;
				case ZBX_DB_ORACLE:
					self::$dbBackend = new OracleDbBackend();
					break;
				case ZBX_DB_DB2:
					self::$dbBackend = new Db2DbBackend();
					break;
				case ZBX_DB_SQLITE3:
					self::$dbBackend = new SqliteDbBackend();
					break;
			}
		}

		return self::$dbBackend;
	}

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
			if (self::$nodeId == 0) {
				self::$minNodeId = 0;
				self::$maxNodeId = ZBX_STANDALONE_MAX_IDS;
			}
			else {
				self::$minNodeId = bcadd(
					bcmul(self::$nodeId, ZBX_DM_MAX_HISTORY_IDS), bcmul($ZBX_LOCALNODEID, ZBX_DM_MAX_CONFIG_IDS), 0
				);
				self::$maxNodeId = bcadd(self::$minNodeId, bcsub(ZBX_DM_MAX_CONFIG_IDS, 1), 0);
			}
		}
	}

	/**
	 * Reserve ids for primary key of passed table.
	 * If record for table does not exist or value is out of range, ids record is recreated
	 * using maximum id from table or minimum allowed value.
	 *
	 * @throw APIException
	 *
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
	 * Record is deleted and then created again with value of maximum id from table or minimum allowed.
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
				' WHERE '.$id_name.' BETWEEN '.self::$minNodeId.' AND '.self::$maxNodeId;
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
			self::$schema = include(dirname(__FILE__).'/../../'.self::SCHEMA_FILE);
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

		return $schema['key'];
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

			if (isset($tableSchema['fields'][$field]['ref_table'])) {
				if ($tableSchema['fields'][$field]['null']) {
					$values[$field] = ($values[$field] == '0') ? NULL : $values[$field];
				}
			}

			if (is_null($values[$field])) {
				if ($tableSchema['fields'][$field]['null']) {
					$values[$field] = 'NULL';
				}
				elseif (isset($tableSchema['fields'][$field]['default'])) {
					$values[$field] = zbx_dbstr($tableSchema['fields'][$field]['default']);
				}
				else {
					self::exception(self::DBEXECUTE_ERROR,
						_s('Field "%1$s" cannot be set to NULL.', $field)
					);
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

			$sqlWhere[] = dbConditionString($field, zbx_toArray($value));
		}

		// build query
		$sql = 'SELECT * FROM '.$tableName;
		if ($sqlWhere) {
			$sql .= ' WHERE '.implode(' AND ', $sqlWhere);
		}

		return DBfetchArray(DBSelect($sql));
	}

	/**
	 * Insert data into DB.
	 *
	 * @param string $table
	 * @param array  $values pair of fieldname => fieldvalue
	 * @param bool   $getids
	 *
	 * @return array    an array of ids with the keys preserved
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
	 * Insert batch data into DB.
	 *
	 * @param string $table
	 * @param array  $values pair of fieldname => fieldvalue
	 * @param bool   $getids
	 *
	 * @return array    an array of ids with the keys preserved
	 */
	public static function insertBatch($table, $values, $getids = true) {
		if (empty($values)) {
			return true;
		}

		$resultIds = array();

		$tableSchema = self::getSchema($table);
		$values = self::addMissingFields($tableSchema, $values);

		if ($getids) {
			$id = self::reserveIds($table, count($values));
		}

		$newValues = array();
		foreach ($values as $key => $row) {
			if ($getids) {
				$resultIds[$key] = $id;
				$row[$tableSchema['key']] = $id;
				$values[$key][$tableSchema['key']] = $id;
				$id = bcadd($id, 1, 0);
			}
			self::checkValueTypes($table, $row);
			$newValues[] = $row;
		}

		$fields = array_keys(reset($newValues));

		$sql = self::getDbBackend()->createInsertQuery($table, $fields, $newValues);

		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s".', $sql));
		}

		return $resultIds;
	}

	/**
	 * Update data in DB.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $data[...]['values'] pair of fieldname => fieldvalue for SET clause
	 * @param array $data[...]['where'] pair of fieldname => fieldvalue for WHERE clause
	 *
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

			// where condition processing
			$sqlWhere = array();
			foreach ($row['where'] as $field => $values) {
				if (!isset($tableSchema['fields'][$field]) || is_null($values)) {
					self::exception(self::DBEXECUTE_ERROR, _s('Incorrect field "%1$s" name or value in where statement for table "%2$s".', $field, $table));
				}
				$values = zbx_toArray($values);
				sort($values); // sorting ids to prevent deadlocks when two transactions depend on each other

				$sqlWhere[] = dbConditionString($field, $values);
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
	 * @param string $pk
	 * @param array $values
	 *
	 * @return bool
	 */
	public static function updateByPk($tableName, $pk, array $values) {
		return self::update($tableName, array(
			'where' => array(self::getPk($tableName) => $pk),
			'values' => $values
		));
	}

	/**
	 * Saves the given records to the database. If the record has the primary key set, it is updated, otherwise - a new
	 * record is inserted. For new records the newly generated PK is added to the result.
	 *
	 * @static
	 *
	 * @param $tableName
	 * @param $data
	 *
	 * @return array    the same records, that have been passed with the primary keys set for new records
	 */
	public static function save($tableName, array $data) {
		$pk = self::getPk($tableName);

		$newRecords = array();
		foreach ($data as $key => $record) {
			// if the pk is set - update the record
			if (isset($record[$pk])) {
				self::updateByPk($tableName, $record[$pk], $record);
			}
			// if no pk is set, create the record later
			else {
				$newRecords[$key] = $data[$key];
			}
		}

		// insert the new records
		if ($newRecords) {
			$newIds = self::insert($tableName, $newRecords);
			foreach ($newIds as $key => $id) {
				$data[$key][$pk] = $id;
			}
		}

		return $data;
	}

	/**
	 * Replaces the records given in $oldRecords with the ones in $newRecords.
	 *
	 * If a record with the same primary key as a new one already exists in the old records, the record is updated
	 * only if they are different. For new records the newly generated PK is added to the result. Old records that are
	 * not present in the new records are deleted.
	 *
	 * All of the records must have the primary key defined.
	 *
	 * @static
	 *
	 * @param $tableName
	 * @param array $oldRecords
	 * @param array $newRecords
	 *
	 * @return array    the new records, that have been passed with the primary keys set for newly inserted records
	 */
	public static function replace($tableName, array $oldRecords, array $newRecords) {
		$pk = self::getPk($tableName);
		$oldRecords = zbx_toHash($oldRecords, $pk);

		$modifiedRecords = array();
		foreach ($newRecords as $record) {
			// if it's a new or modified record - save it later
			if (!isset($record[$pk]) || self::recordModified($tableName, $oldRecords[$record[$pk]], $record)) {
				$modifiedRecords[] = $record;
			}

			// remove the existing records from the collection, the remaining ones will be deleted
			if(isset($record[$pk])) {
				unset($oldRecords[$record[$pk]]);
			}
		}

		// save modified records
		if ($modifiedRecords) {
			$modifiedRecords = self::save($tableName, $modifiedRecords);
		}

		// delete remaining records
		if ($oldRecords) {
			DB::delete($tableName, array(
				$pk => array_keys($oldRecords)
			));
		}

		return $modifiedRecords;
	}

	/**
	 * Compares the fields, that are present in both records, and returns true if any of the values differ.
	 *
	 * @static
	 * @param $tableName
	 * @param array $oldRecord
	 * @param array $newRecord
	 *
	 * @return bool
	 */
	public static function recordModified($tableName, array $oldRecord, array $newRecord) {
		foreach ($oldRecord as $field => $value) {
			if (self::hasField($tableName, $field)
					&& isset($newRecord[$field])
					&& (string) $value !== (string) $newRecord[$field]) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete data from DB.
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

			$sqlWhere[] = dbConditionString($field, $values);
		}

		$sql = 'DELETE FROM '.$table.' WHERE '.implode(($use_or ? ' OR ' : ' AND '), $sqlWhere);
		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s"', $sql));
		}

		return true;
	}

	/**
	 * Check if $type is numeric field type.
	 *
	 * @param int $type
	 *
	 * @return bool
	 */
	public static function isNumericFieldType($type) {
		switch ($type) {
			case self::FIELD_TYPE_ID:
			case self::FIELD_TYPE_INT:
			case self::FIELD_TYPE_UINT:
				return true;
		}

		return false;
	}
}
