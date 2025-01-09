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


class DB {

	const SCHEMA_FILE = 'schema.inc.php';

	const DBEXECUTE_ERROR = 1;
	const RESERVEIDS_ERROR = 2;
	const SCHEMA_ERROR = 3;
	const INIT_ERROR = 4;

	const FIELD_TYPE_INT = 0x01;
	const FIELD_TYPE_CHAR = 0x02;
	const FIELD_TYPE_ID = 0x04;
	const FIELD_TYPE_FLOAT = 0x08;
	const FIELD_TYPE_UINT = 0x10;
	const FIELD_TYPE_BLOB = 0x20;
	const FIELD_TYPE_TEXT = 0x40;
	const FIELD_TYPE_CUID = 0x80;

	const SUPPORTED_FILTER_TYPES = self::FIELD_TYPE_INT | self::FIELD_TYPE_CHAR | self::FIELD_TYPE_ID |
		self::FIELD_TYPE_FLOAT | self::FIELD_TYPE_UINT | self::FIELD_TYPE_CUID;
	const SUPPORTED_SEARCH_TYPES = self::FIELD_TYPE_CHAR | self::FIELD_TYPE_TEXT | self::FIELD_TYPE_CUID;

	private static $schema = null;

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
			}
		}

		return self::$dbBackend;
	}

	private static function exception($code, $error) {
		throw new DBException($error, $code);
	}

	/**
	 * Reserve IDs for primary key of passed table.
	 * If record for table does not exist or value is out of range, ids record is created using maximum ID from table
	 * or minimum allowed value.
	 *
	 * @param string $table table name
	 * @param int $count number of IDs to reserve
	 *
	 * @throws APIException
	 *
	 * @return string
	 */
	public static function reserveIds($table, $count) {
		global $DB;

		$tableSchema = self::getSchema($table);
		$id_name = $tableSchema['key'];

		$sql = 'SELECT nextid'.
				' FROM ids'.
				' WHERE table_name='.zbx_dbstr($table).
					' AND field_name='.zbx_dbstr($id_name).
				' FOR UPDATE';

		$res = DBfetch(DBselect($sql));

		if ($res) {
			$maxNextId = bcadd($res['nextid'], $count, 0);

			if (bccomp($maxNextId, ZBX_DB_MAX_ID) == 1) {
				$nextid = self::refreshIds($table, $count);
			}
			else {
				$sql = 'UPDATE ids'.
						' SET nextid='.$maxNextId.
						' WHERE table_name='.zbx_dbstr($table).
							' AND field_name='.zbx_dbstr($id_name);

				if (!DBexecute($sql)) {
					self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
				}

				$nextid = bcadd($res['nextid'], 1, 0);
			}
		}

		/*
		 * Detect either the query is executable at all? If query is valid and schema is correct but query still cannot
		 * be executed, then there is a good chance that previous transaction has left row level lock unreleased or it
		 * is still running. In such a case execution must be stopped, otherwise it will call self::refreshIds method.
		 */
		elseif (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR,
				_('Your database is not working properly. Please wait a few minutes and try to repeat this action. If the problem still persists, please contact system administrator. The problem might be caused by long running transaction or row level lock accomplished by your database management system.')
			);
		}
		// If query is executable, but still returns false, only then call refreshIds.
		else {
			$nextid = self::refreshIds($table, $count);
		}

		return $nextid;
	}

	/**
	 * Refresh ID record for given table.
	 * Record is deleted and then created again with value of maximum ID from table or minimum allowed.
	 *
	 * @param string $table table name
	 * @param int    $count number of IDs to reserve
	 *
	 * @throws APIException
	 *
	 * @return string
	 */
	public static function refreshIds($table, $count) {
		$tableSchema = self::getSchema($table);
		$id_name = $tableSchema['key'];

		// when we reach the maximum ID, we try to refresh them to check if any IDs have been freed
		$sql = 'DELETE FROM ids WHERE table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($id_name);

		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
		}

		$row = DBfetch(DBselect('SELECT MAX('.$id_name.') AS id FROM '.$table));

		$nextid = ($row && $row['id']) ? $row['id'] : 0;

		$maxNextId = bcadd($nextid, $count, 0);

		if (bccomp($maxNextId, ZBX_DB_MAX_ID) == 1) {
			self::exception(
				self::RESERVEIDS_ERROR, __METHOD__.' ID greater than maximum allowed for table "'.$table.'"'
			);
		}

		$sql = 'INSERT INTO ids (table_name,field_name,nextid)'.
				' VALUES ('.zbx_dbstr($table).','.zbx_dbstr($id_name).','.$maxNextId.')';

		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
		}

		$nextid = bcadd($nextid, 1, 0);

		return $nextid;
	}

	/**
	 * Returns an array describing the database schema.
	 *
	 * If the $table parameter is passed, the method will return the schema for the given table,
	 * otherwise - for the whole database.
	 *
	 * @param string|null $table
	 *
	 * @throws DBException if the given table does not exist.
	 *
	 * @return array
	 */
	public static function getSchema(?string $table = null): array {
		if (self::$schema === null) {
			self::$schema = include __DIR__.'/../../'.self::SCHEMA_FILE;
		}

		if ($table === null) {
			return self::$schema;
		}

		if (!array_key_exists($table, self::$schema)) {
			self::exception(self::SCHEMA_ERROR, _s('Table "%1$s" does not exist.', $table));
		}

		return self::$schema[$table];
	}

	/**
	 * Returns the names of the fields that are used as the primary key of the table.
	 *
	 * @param string $table_name
	 *
	 * @return string
	 */
	public static function getPk(string $table_name): string {
		$schema = self::getSchema($table_name);

		return $schema['key'];
	}

	/**
	 * Returns true if the table $tableName has the $fieldName field.
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

	/**
	 * Returns length of the field.
	 *
	 * @param string $table_name
	 * @param string $field_name
	 *
	 * @return int
	 */
	public static function getFieldLength($table_name, $field_name) {
		$schema = self::getSchema($table_name);

		return $schema['fields'][$field_name]['length'];
	}

	public static function getDefaults($table) {
		$table = self::getSchema($table);

		$defaults = [];
		foreach ($table['fields'] as $name => $field) {
			if (isset($field['default'])) {
				$defaults[$name] = $field['default'];
			}
		}
		return $defaults;
	}

	/**
	 * Returns the default value of the given field.
	 *
	 * @param string $table		name of the table
	 * @param string $field		name of the field
	 *
	 * @return string|null
	 */
	public static function getDefault($table, $field) {
		$table = self::getSchema($table);
		$field = $table['fields'][$field];

		return isset($field['default']) ? $field['default'] : null;
	}

	/**
	 * Get the updated values of a record by correctly comparing the new and old ones, taking field types into account.
	 *
	 * @param string $table_name
	 * @param array  $new_values
	 * @param array  $old_values
	 *
	 * @return array
	 */
	public static function getUpdatedValues(string $table_name, array $new_values, array $old_values): array {
		$updated_values = [];

		// Discard field names not existing in the target table.
		$fields = array_intersect_key(self::getSchema($table_name)['fields'], $new_values);

		foreach ($fields as $name => $spec) {
			if (!array_key_exists($name, $old_values)) {
				$updated_values[$name] = $new_values[$name];
				continue;
			}

			if ($spec['type'] & self::FIELD_TYPE_ID) {
				if (bccomp($new_values[$name], $old_values[$name]) != 0) {
					$updated_values[$name] = $new_values[$name];
				}
			}
			elseif ($spec['type'] & (self::FIELD_TYPE_INT | self::FIELD_TYPE_UINT | self::FIELD_TYPE_FLOAT)) {
				if ($new_values[$name] != $old_values[$name]) {
					$updated_values[$name] = $new_values[$name];
				}
			}
			elseif ($new_values[$name] !== $old_values[$name]) {
				$updated_values[$name] = $new_values[$name];
			}
		}

		return $updated_values;
	}

	private static function checkValueTypes($tableSchema, &$values) {
		global $DB;

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
				if ($tableSchema['fields'][$field]['type'] & (self::FIELD_TYPE_CUID | self::FIELD_TYPE_CHAR)) {
					$length = mb_strlen($values[$field]);

					if ($length > $tableSchema['fields'][$field]['length']) {
						self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is %4$d characters.',
							$values[$field], $field, $length, $tableSchema['fields'][$field]['length']));
					}
					$values[$field] = zbx_dbstr($values[$field]);
				}
				elseif ($tableSchema['fields'][$field]['type'] & (self::FIELD_TYPE_ID | self::FIELD_TYPE_UINT)) {
					if (!zbx_ctype_digit($values[$field])) {
						self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for unsigned int field "%2$s".', $values[$field], $field));
					}
					$values[$field] = zbx_dbstr($values[$field]);
				}
				elseif ($tableSchema['fields'][$field]['type'] & self::FIELD_TYPE_INT) {
					if (!zbx_is_int($values[$field])) {
						self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for int field "%2$s".', $values[$field], $field));
					}
					$values[$field] = zbx_dbstr($values[$field]);
				}
				elseif ($tableSchema['fields'][$field]['type'] & self::FIELD_TYPE_FLOAT) {
					if (!is_numeric($values[$field])) {
						self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for float field "%2$s".', $values[$field], $field));
					}
					$values[$field] = zbx_dbstr($values[$field]);
				}
				elseif ($tableSchema['fields'][$field]['type'] & self::FIELD_TYPE_TEXT) {
					$values[$field] = zbx_dbstr($values[$field]);
				}
				elseif ($tableSchema['fields'][$field]['type'] & self::FIELD_TYPE_BLOB) {
					switch ($DB['TYPE']) {
						case ZBX_DB_MYSQL:
							$values[$field] = zbx_dbstr($values[$field]);
							break;

						case ZBX_DB_POSTGRESQL:
							$values[$field] = "'".pg_escape_bytea($DB['DB'], $values[$field])."'";
							break;
					}
				}
			}
		}
	}

	/**
	 * Returns the records that match the given criteria.
	 *
	 * @param string $tableName
	 * @param array $criteria   An associative array of field-value pairs, where value can be either a single value
	 *                          or an array (IN)
	 *
	 * @return array
	 */
	public static function find($tableName, array $criteria = []) {
		// build the WHERE part
		$sqlWhere = [];
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
		$table_schema = self::getSchema($table);
		$fields = array_reduce($values, 'array_merge', []);
		$fields = array_intersect_key($fields, $table_schema['fields']);

		foreach ($fields as $field => &$value) {
			$value = array_key_exists('default', $table_schema['fields'][$field])
				? $table_schema['fields'][$field]['default']
				: null;
		}
		unset($value);

		foreach ($values as $key => &$row) {
			$row = array_merge($fields, $row);
		}
		unset($row);

		return self::insertBatch($table, $values, $getids);
	}

	/**
	 * Returns the list of mandatory fields with default values for INSERT statements.
	 *
	 * @param array $table_schema
	 *
	 * @return array
	 */
	private static function getMandatoryFields(array $table_schema): array {
		global $DB;

		$mandatory_fields = [];

		if ($DB['TYPE'] === ZBX_DB_MYSQL) {
			foreach ($table_schema['fields'] as $name => $field) {
				if ($field['type'] & self::FIELD_TYPE_TEXT) {
					$mandatory_fields += [$name => $field['default']];
				}
			}
		}

		return $mandatory_fields;
	}

	/**
	 * Add IDs to inserted rows.
	 *
	 * @param string $table
	 * @param array  $values
	 *
	 * @return array An array of IDs with the keys preserved.
	 */
	private static function addIds(string $table, array &$values): array {
		$table_schema = self::getSchema($table);
		$resultids = [];

		if ($table_schema['fields'][$table_schema['key']]['type'] & self::FIELD_TYPE_ID) {
			$id = self::reserveIds($table, count($values));
		}

		foreach ($values as $key => &$row) {
			if ($table_schema['fields'][$table_schema['key']]['type'] & self::FIELD_TYPE_ID) {
				$resultids[$key] = $id;
				$row = [$table_schema['key'] => $id] + $row;
				$id = bcadd($id, 1, 0);
			}
			elseif ($table_schema['fields'][$table_schema['key']]['type'] & self::FIELD_TYPE_CUID) {
				$id = CCuid::generate();
				$resultids[$key] = $id;
				$row = [$table_schema['key'] => $id] + $row;
			}
		}
		unset($row);

		return $resultids;
	}

	/**
	 * Insert batch data into DB.
	 *
	 * @param string $table
	 * @param array  $values pair of fieldname => fieldvalue
	 * @param bool   $getids
	 *
	 * @return array An array of IDs with the keys preserved.
	 */
	public static function insertBatch($table, $values, $getids = true) {
		if (empty($values)) {
			return true;
		}

		$resultids = [];
		$table_schema = self::getSchema($table);
		$mandatory_fields = self::getMandatoryFields($table_schema);

		if ($getids) {
			$resultids = self::addIds($table, $values);
		}

		foreach ($values as &$row) {
			$row += $mandatory_fields;

			self::checkValueTypes($table_schema, $row);
			self::uppercaseValues($table, $row);
		}
		unset($row);

		$sql = self::getDbBackend()->createInsertQuery($table, array_keys(reset($values)), $values);

		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s".', $sql));
		}

		return $resultids;
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
			self::checkValueTypes($tableSchema, $row['values']);
			if (empty($row['values'])) {
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%1$s" without values.', $table));
			}

			self::uppercaseValues($table, $row['values']);

			// set creation
			$sqlSet = '';
			foreach ($row['values'] as $field => $value) {
				if ($sqlSet !== '') {
					$sqlSet .= ',';
				}
				$sqlSet .= $field.'='.$value;
			}

			if (!isset($row['where']) || empty($row['where']) || !is_array($row['where'])) {
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%1$s" without where condition.', $table));
			}

			// where condition processing
			$sqlWhere = [];
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
	 * @param string $tableName
	 * @param string $pk
	 * @param array  $values
	 *
	 * @return bool
	 */
	public static function updateByPk($tableName, $pk, array $values) {
		return self::update($tableName, [
			'where' => [self::getPk($tableName) => $pk],
			'values' => $values
		]);
	}

	/**
	 * Saves the given records to the database. If the record has the primary key set, it is updated, otherwise - a new
	 * record is inserted. For new records the newly generated PK is added to the result.
	 *
	 * @param $tableName
	 * @param $data
	 *
	 * @return array    the same records, that have been passed with the primary keys set for new records
	 */
	public static function save($tableName, array $data) {
		$pk = self::getPk($tableName);

		$newRecords = [];
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
	 * @param string $tableName
	 * @param array  $oldRecords
	 * @param array  $newRecords
	 *
	 * @return array    the new records, that have been passed with the primary keys set for newly inserted records
	 */
	public static function replace($tableName, array $oldRecords, array $newRecords) {
		$pk = self::getPk($tableName);
		$oldRecords = zbx_toHash($oldRecords, $pk);

		$modifiedRecords = [];
		foreach ($newRecords as $key => $record) {
			// if it's a new or modified record - save it later
			if (!isset($record[$pk]) || self::recordModified($tableName, $oldRecords[$record[$pk]], $record)) {
				$modifiedRecords[$key] = $record;
			}

			// remove the existing records from the collection, the remaining ones will be deleted
			if(isset($record[$pk])) {
				unset($oldRecords[$record[$pk]]);
			}
		}

		// save modified records
		if ($modifiedRecords) {
			$modifiedRecords = self::save($tableName, $modifiedRecords);

			// add the new IDs to the new records
			foreach ($modifiedRecords as $key => $record) {
				$newRecords[$key][$pk] = $record[$pk];
			}
		}

		// delete remaining records
		if ($oldRecords) {
			self::delete($tableName, [
				$pk => array_keys($oldRecords)
			]);
		}

		return $newRecords;
	}

	/**
	 * Compares the fields, that are present in both records, and returns true if any of the values differ.
	 *
	 * @param string $tableName
	 * @param array  $oldRecord
	 * @param array  $newRecord
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
	 * DB::delete('items', ['itemid' => [1, 8, 6]]);
	 * DELETE FROM items WHERE itemid IN (1, 8, 6)
	 *
	 * DB::delete('items', ['itemid' => [1], 'templateid' => [10]]);
	 * DELETE FROM items WHERE itemid IN (1) AND templateid IN (10)
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

		$sqlWhere = [];
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
	 * @param string $table_name
	 * @param array  $options
	 * @param string $table_alias
	 *
	 * @return string
	 */
	public static function makeSql($table_name, array &$options, $table_alias = null) {
		$defaults = [
			'output' => [],
			'countOutput' => false,
			'filter' => [],
			'search' => [],
			'startSearch' => false,
			'searchByAny' => false,
			'sortfield' => [],
			'sortorder' => [],
			'limit' => null,
			'preservekeys' => false
		];

		if ($array_diff = array_diff_key($options, $defaults)) {
			unset($array_diff[self::getPk($table_name).'s']);
			if ($array_diff) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: unsupported option "%s".', [__FUNCTION__, key($array_diff)])
				);
			}
		}

		$options = zbx_array_merge($defaults, $options);

		$sql_parts = self::createSelectQueryParts($table_name, $options, $table_alias);

		return 'SELECT '.implode(',', $sql_parts['select']).
				' FROM '.implode(',', $sql_parts['from']).
				($sql_parts['where'] ? ' WHERE '.implode(' AND ', $sql_parts['where']) : '').
				($sql_parts['order'] ? ' ORDER BY '.implode(',', $sql_parts['order']) : '');
	}

	/**
	 * @param string $table_name
	 * @param array  $options
	 * @param string $table_alias
	 *
	 * @return array
	 */
	public static function select($table_name, array $options, $table_alias = null) {
		$db_result = DBSelect(self::makeSql($table_name, $options, $table_alias), $options['limit']);

		if ($options['countOutput']) {
			return DBfetch($db_result)['rowscount'];
		}

		$result = [];
		$field_names = array_flip($options['output']);

		if ($options['preservekeys']) {
			$pk = self::getPk($table_name);

			while ($db_row = DBfetch($db_result)) {
				$result[$db_row[$pk]] = array_intersect_key($db_row, $field_names);
			}
		}
		else {
			while ($db_row = DBfetch($db_result)) {
				$result[] = array_intersect_key($db_row, $field_names);
			}
		}

		return $result;
	}

	/**
	 * Returns the table name with the table alias.
	 *
	 * @param string $table_name
	 * @param string $table_alias
	 *
	 * @return string
	 */
	private static function tableId($table_name, $table_alias = null) {
		return($table_alias !== null) ? $table_name.' '.$table_alias : $table_name;
	}

	/**
	 * Prepends the table alias to the given field name.
	 *
	 * @param string $field_name
	 * @param string $table_alias
	 *
	 * @return string
	 */
	private static function fieldId($field_name, $table_alias = null) {
		return ($table_alias !== null) ? $table_alias.'.'.$field_name : $field_name;
	}

	/**
	 * Convert field to uppercase or substitute it with its pre-upcased variant.
	 *
	 * @param string      $field_name
	 * @param string      $table_name
	 * @param string|null $table_alias
	 *
	 * @return string
	 */
	public static function uppercaseField(string $field_name, string $table_name, string $table_alias = null): string {
		if ($table_alias === null) {
			$table_alias = $table_name;
		}

		if ($field_name === 'name' && self::hasField($table_name, 'name_upper')) {
			return $table_alias.'.name_upper';
		}

		if ($field_name === 'name_resolved' && self::hasField($table_name, 'name_resolved_upper')) {
			return $table_alias.'.name_resolved_upper';
		}

		return 'UPPER('.$table_alias.'.'.$field_name.')';
	}

	/**
	 * Convert field values to uppercase.
	 *
	 * @param string $table_name
	 * @param array  $row
	 */
	public static function uppercaseValues(string $table_name, array &$row): void {
		if (array_key_exists('name_resolved', $row) && self::hasField($table_name, 'name_resolved_upper')) {
			$row['name_resolved_upper'] = 'UPPER('.$row['name_resolved'].')';
		}
	}

	/**
	 * Builds an SQL parts array from the given options.
	 *
	 * @param string $table_name
	 * @param array  $options
	 * @param string $table_alias
	 *
	 * @return array		The resulting SQL parts array
	 */
	private static function createSelectQueryParts($table_name, array $options, $table_alias = null) {
		$sql_parts = [
			'select' => [],
			'from' => [self::tableId($table_name, $table_alias)],
			'where' => [],
			'order' => []
		];

		// add output options
		$sql_parts = self::applyQueryOutputOptions($table_name, $options, $table_alias, $sql_parts);

		// add filter options
		$sql_parts = self::applyQueryFilterOptions($table_name, $options, $table_alias, $sql_parts);

		// add search options
		$sql_parts = self::applyQuerySearchOptions($table_name, $options, $table_alias, $sql_parts);

		// add sort options
		$sql_parts = self::applyQuerySortOptions($table_name, $options, $table_alias, $sql_parts);

		return $sql_parts;
	}

	/**
	 * Modifies the SQL parts to implement all of the output related options.
	 *
	 * @param string      $table_name
	 * @param array       $options
	 * @param string|null $table_alias
	 * @param array       $sql_parts
	 *
	 * @throws APIException
	 * @throws DBException
	 * @return array
	 */
	private static function applyQueryOutputOptions($table_name, array $options, $table_alias, array $sql_parts) {
		if ($options['countOutput']) {
			$sql_parts['select'][] = 'COUNT('.self::fieldId('*', $table_alias).') AS rowscount';
		}
		else {
			$table_schema = self::getSchema($table_name);
			$select = [];
			$select[self::fieldId(self::getPk($table_name), $table_alias)] = true;

			foreach ($options['output'] as $field_name) {
				if (!array_key_exists($field_name, $table_schema['fields'])) {
					self::exception(self::SCHEMA_ERROR,
						vsprintf('%s: field "%s.%s" does not exist.', [__FUNCTION__, $table_name, $field_name])
					);
				}

				$select[self::fieldId($field_name, $table_alias)] = true;
			}

			$sql_parts['select'] = array_keys($select);
		}

		return $sql_parts;
	}

	/**
	 * Modifies the SQL parts to implement all the filter related options.
	 *
	 * @param string      $table_name
	 * @param array       $options
	 * @param string|null $table_alias
	 * @param array       $sql_parts
	 *
	 * @throws APIException
	 * @return array
	 */
	private static function applyQueryFilterOptions($table_name, array $options, $table_alias, array $sql_parts) {
		$table_schema = self::getSchema($table_name);
		$pk = self::getPk($table_name);
		$pk_option = $pk.'s';

		// pks
		if (array_key_exists($pk_option, $options)) {
			if (!is_array($options[$pk_option])) {
				$options[$pk_option] = [$options[$pk_option]];
			}

			$field_schema = $table_schema['fields'][$pk];
			$field_name = self::fieldId($pk, $table_alias);

			if ($field_schema['type'] & self::FIELD_TYPE_ID) {
				$sql_parts['where'][] = dbConditionId($field_name, $options[$pk_option]);
			}
			elseif ($field_schema['type'] & (self::FIELD_TYPE_INT | self::FIELD_TYPE_UINT)) {
				$sql_parts['where'][] = dbConditionInt($field_name, $options[$pk_option]);
			}
			else {
				$sql_parts['where'][] = dbConditionString($field_name, $options[$pk_option]);
			}
		}

		// filters
		if (is_array($options['filter'])) {
			$sql_parts = self::dbFilter($table_name, $options, $table_alias, $sql_parts);
		}

		return $sql_parts;
	}

	/**
	 * Modifies the SQL parts to implement all of the search related options.
	 *
	 * @param string      $table_name
	 * @param array       $options
	 * @param array       $options['search']
	 * @param bool        $options['startSearch']
	 * @param bool        $options['searchByAny']
	 * @param string|null $table_alias
	 * @param array       $sql_parts
	 *
	 * @throws APIException
	 * @throws DBException
	 * @return array
	 */
	private static function applyQuerySearchOptions($table_name, array $options, $table_alias, array $sql_parts) {
		global $DB;

		$table_schema = self::getSchema($table_name);

		$start = $options['startSearch'] ? '' : '%';
		$glue = $options['searchByAny'] ? ' OR ' : ' AND ';

		$search = [];

		foreach ($options['search'] as $field_name => $patterns) {
			if (!array_key_exists($field_name, $table_schema['fields'])) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: field "%s.%s" does not exist.', [__FUNCTION__, $table_name, $field_name])
				);
			}

			$field_schema = $table_schema['fields'][$field_name];

			if (($field_schema['type'] & self::SUPPORTED_SEARCH_TYPES) == 0) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: field "%s.%s" has an unsupported type.', [__FUNCTION__, $table_name, $field_name])
				);
			}

			if ($patterns === null) {
				continue;
			}

			foreach ((array) $patterns as $pattern) {
				// escaping parameter that is about to be used in LIKE statement
				$pattern = mb_strtoupper(strtr($pattern, ['!' => '!!', '%' => '!%', '_' => '!_']));
				$pattern = zbx_dbstr($start.$pattern.'%');

				$search[] = self::uppercaseField($field_name, $table_name, $table_alias).' LIKE '.$pattern." ESCAPE '!'";
			}
		}

		if ($search) {
			$sql_parts['where'][] = ($options['searchByAny'] && count($search) > 1)
				? '('.implode($glue, $search).')'
				: implode($glue, $search);
		}

		return $sql_parts;
	}

	/**
	 * Apply filter conditions to sql built query.
	 *
	 * @param string      $table_name
	 * @param array       $options
	 * @param string|null $table_alias
	 * @param array       $sql_parts
	 *
	 * @throws APIException
	 * @throws DBException
	 * @return array
	 */
	private static function dbFilter($table_name, $options, $table_alias, $sql_parts) {
		$table_schema = self::getSchema($table_name);
		$filter = [];

		foreach ($options['filter'] as $field_name => $value) {
			if (!array_key_exists($field_name, $table_schema['fields'])) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: field "%s.%s" does not exist.', [__FUNCTION__, $table_name, $field_name])
				);
			}

			$field_schema = $table_schema['fields'][$field_name];

			if (($field_schema['type'] & self::SUPPORTED_FILTER_TYPES) == 0) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: field "%s.%s" has an unsupported type.', [__FUNCTION__, $table_name, $field_name])
				);
			}

			if ($value === null) {
				continue;
			}

			if (!is_array($value)) {
				$value = [$value];
			}

			if ($field_schema['type'] & self::FIELD_TYPE_ID) {
				$filter[] = dbConditionId(self::fieldId($field_name, $table_alias), $value);
			}
			elseif ($field_schema['type'] & (self::FIELD_TYPE_INT | self::FIELD_TYPE_UINT)) {
				$filter[] = dbConditionInt(self::fieldId($field_name, $table_alias), $value);
			}
			else {
				$filter[] = dbConditionString(self::fieldId($field_name, $table_alias), $value);
			}
		}

		if ($filter) {
			$sql_parts['where'][] = implode(' AND ', $filter);
		}

		return $sql_parts;
	}

	/**
	 * Get array of the field names by which filtering is supported from the given table.
	 * If $output_fields parameter is given, get filterable fields among them in scope of the given table name.
	 *
	 * @param string     $table_name
	 * @param array|null $output_fields
	 *
	 * @return array
	 */
	public static function getFilterFields(string $table_name, array $output_fields = null): array {
		$table_schema = self::getSchema($table_name);

		if ($output_fields !== null) {
			$table_schema['fields'] = array_intersect_key($table_schema['fields'], array_flip($output_fields));
		}

		$filter_fields = [];

		foreach ($table_schema['fields'] as $field_name => $field_schema) {
			if ($field_schema['type'] & self::SUPPORTED_FILTER_TYPES) {
				$filter_fields[] = $field_name;
			}
		}

		return $filter_fields;
	}

	/**
	 * Get array of the field names by which searching is supported from the given table.
	 * If $output_fields parameter is given, get searchable fields among them in scope of the given table name.
	 *
	 * @param string     $table_name
	 * @param array|null $output_fields
	 *
	 * @return array
	 */
	public static function getSearchFields(string $table_name, array $output_fields = null): array {
		$table_schema = self::getSchema($table_name);

		if ($output_fields !== null) {
			$table_schema['fields'] = array_intersect_key($table_schema['fields'], array_flip($output_fields));
		}

		$search_fields = [];

		foreach ($table_schema['fields'] as $field_name => $field_schema) {
			if ($field_schema['type'] & self::SUPPORTED_SEARCH_TYPES) {
				$search_fields[] = $field_name;
			}
		}

		return $search_fields;
	}

	/**
	 * Modifies the SQL parts to implement all of the sorting related options.
	 *
	 * @param string      $table_name
	 * @param array       $options
	 * @param string|null $table_alias
	 * @param array       $sql_parts
	 *
	 * @throws APIException
	 * @throws DBException
	 * @return array
	 */
	private static function applyQuerySortOptions($table_name, array $options, $table_alias, array $sql_parts) {
		$table_schema = self::getSchema($table_name);

		foreach ($options['sortfield'] as $index => $field_name) {
			if (!array_key_exists($field_name, $table_schema['fields'])) {
				self::exception(self::SCHEMA_ERROR,
					vsprintf('%s: field "%s.%s" does not exist.', [__FUNCTION__, $table_name, $field_name])
				);
			}

			$sortorder = '';
			if (array_key_exists($index, $options['sortorder']) && $options['sortorder'][$index] == ZBX_SORT_DOWN) {
				$sortorder = ' '.ZBX_SORT_DOWN;
			}

			$sql_parts['order'][] = self::fieldId($field_name, $table_alias).$sortorder;
		}

		return $sql_parts;
	}
}
