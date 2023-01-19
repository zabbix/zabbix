<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
	const FIELD_TYPE_NCLOB = 'nclob';
	const FIELD_TYPE_CUID = 'cuid';

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
				case ZBX_DB_ORACLE:
					self::$dbBackend = new OracleDbBackend();
					break;
			}
		}

		return self::$dbBackend;
	}

	private static function exception($code, $error) {
		throw new DBException($error, $code);
	}

	/**
	 * Reserve ids for primary key of passed table.
	 * If record for table does not exist or value is out of range, ids record is created
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
	 * Refresh id record for given table.
	 * Record is deleted and then created again with value of maximum id from table or minimum allowed.
	 *
	 * @throw APIException
	 *
	 * @static
	 *
	 * @param string $table table name
	 * @param int    $count number of ids to reserve
	 *
	 * @return string
	 */
	private static function refreshIds($table, $count) {
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
	 * @param string $table_name
	 *
	 * @return string
	 */
	public static function getPk(string $table_name): string {
		$schema = self::getSchema($table_name);

		return $schema['key'];
	}

	/**
	 * Returns true if the table $tableName has the $fieldName field defined in its schema.
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

	/**
	 * Check that a field is present in a database table.
	 *
	 * @static
	 *
	 * @param string $table_name
	 * @param string $field_name
	 *
	 * @return bool
	 */
	public static function dbFieldExists(string $table_name, string $field_name) {
		return self::getDbBackend()->dbFieldExists($table_name, $field_name);
	}

	/**
	 * Returns length of the field.
	 *
	 * @static
	 *
	 * @param string $table_name
	 * @param string $field_name
	 *
	 * @return int
	 */
	public static function getFieldLength($table_name, $field_name) {
		global $DB;

		$schema = self::getSchema($table_name);

		if ($schema['fields'][$field_name]['type'] == self::FIELD_TYPE_TEXT) {
			return ($DB['TYPE'] == ZBX_DB_ORACLE) ? 2048 : 65535;
		}

		if ($schema['fields'][$field_name]['type'] == self::FIELD_TYPE_NCLOB) {
			return 65535;
		}

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
		$fields = array_intersect_key(DB::getSchema($table_name)['fields'], $new_values);

		foreach ($fields as $name => $spec) {
			if (!array_key_exists($name, $old_values)) {
				$updated_values[$name] = $new_values[$name];
				continue;
			}

			switch ($spec['type']) {
				case DB::FIELD_TYPE_ID:
					if (bccomp($new_values[$name], $old_values[$name]) != 0) {
						$updated_values[$name] = $new_values[$name];
					}
					break;

				case DB::FIELD_TYPE_INT:
				case DB::FIELD_TYPE_UINT:
				case DB::FIELD_TYPE_FLOAT:
					if ($new_values[$name] != $old_values[$name]) {
						$updated_values[$name] = $new_values[$name];
					}
					break;

				default:
					if ($new_values[$name] !== $old_values[$name]) {
						$updated_values[$name] = $new_values[$name];
					}
					break;
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
				switch ($tableSchema['fields'][$field]['type']) {
					case self::FIELD_TYPE_CUID:
					case self::FIELD_TYPE_CHAR:
						$length = mb_strlen($values[$field]);

						if ($length > $tableSchema['fields'][$field]['length']) {
							self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is %4$d characters.',
								$values[$field], $field, $length, $tableSchema['fields'][$field]['length']));
						}
						$values[$field] = zbx_dbstr($values[$field]);
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
						if ($DB['TYPE'] == ZBX_DB_ORACLE) {
							$length = mb_strlen($values[$field]);

							if ($length > 2048) {
								self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is %4$d characters.',
									$values[$field], $field, $length, 2048));
							}
						}
						$values[$field] = zbx_dbstr($values[$field]);
						break;

					case self::FIELD_TYPE_NCLOB:
						// Using strlen because 4000 bytes is largest possible string literal in oracle query.
						if ($DB['TYPE'] == ZBX_DB_ORACLE && strlen($values[$field]) > ORACLE_MAX_STRING_SIZE) {
							$chunks = zbx_dbstr(self::chunkMultibyteStr($values[$field], ORACLE_MAX_STRING_SIZE));
							$values[$field] = 'TO_NCLOB('.implode(') || TO_NCLOB(', $chunks).')';
						}
						else {
							$values[$field] = zbx_dbstr($values[$field]);
						}
						break;

					case self::FIELD_TYPE_BLOB:
						switch ($DB['TYPE']) {
							case ZBX_DB_MYSQL:
								$values[$field] = zbx_dbstr($values[$field]);
								break;

							case ZBX_DB_POSTGRESQL:
								$values[$field] = "'".pg_escape_bytea($DB['DB'], $values[$field])."'";
								break;

							case ZBX_DB_ORACLE:
								// Do nothing; Check CImage.php to see how to update BLOB data with ORACLE DB.
								break;
						}
				}
			}
		}
	}

	/**
	 * @param string $str
	 * @param int $chunk_size
	 *
	 * @return array
	 */
	public static function chunkMultibyteStr(string $str, int $chunk_size): array {
		$chunks = [];
		$offset = 0;
		$size = strlen($str);

		while ($offset < $size) {
			$chunk = mb_strcut($str, $offset, $chunk_size);
			$chunks[] = $chunk;
			$offset = strlen($chunk) + $offset;
		}

		return $chunks;
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
	 * @static
	 *
	 * @param array $table_schema
	 *
	 * @return array
	 */
	private static function getMandatoryFields(array $table_schema): array {
		global $DB;

		$mandatory_fields = [];

		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
				foreach ($table_schema['fields'] as $name => $field) {
					if ($field['type'] == self::FIELD_TYPE_TEXT || $field['type'] == self::FIELD_TYPE_NCLOB) {
						$mandatory_fields += [$name => $field['default']];
					}
				}
				break;

			case ZBX_DB_ORACLE:
				foreach ($table_schema['fields'] as $name => $field) {
					if ($field['type'] == self::FIELD_TYPE_BLOB) {
						$mandatory_fields += [$name => 'EMPTY_BLOB()'];
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

		if ($table_schema['fields'][$table_schema['key']]['type'] === DB::FIELD_TYPE_ID) {
			$id = self::reserveIds($table, count($values));
		}

		foreach ($values as $key => &$row) {
			switch ($table_schema['fields'][$table_schema['key']]['type']) {
				case DB::FIELD_TYPE_ID:
					$resultids[$key] = $id;
					$row = [$table_schema['key'] => $id] + $row;
					$id = bcadd($id, 1, 0);
					break;

				case DB::FIELD_TYPE_CUID:
					$id = CCuid::generate();
					$resultids[$key] = $id;
					$row = [$table_schema['key'] => $id] + $row;
					break;
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
	 * @static
	 *
	 * @param string $tableName
	 * @param string $pk
	 * @param array $values
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
	 * @static
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
			DB::delete($tableName, [
				$pk => array_keys($oldRecords)
			]);
		}

		return $newRecords;
	}

	/**
	 * Replaces the records given in $groupedOldRecords with the ones given in $groupedNewRecords.
	 *
	 * This method can be used to replace related objects in one-to-many relations. Both old and new records
	 * must be grouped by the ID of the record they belong to. The records will be matched by position, instead of
	 * the primary key as in DB::replace(). That is, the first new record will update the first old one, second new
	 * record - the second old one, etc. Since the records are matched by position, the new records should not contain
	 * primary keys.
	 *
	 * Example 1:
	 * $old = array(2 => array( array('gitemid' => 1, 'color' => 'FF0000') ));
	 * $new = array(2 => array( array('color' => '00FF00') ));
	 * var_dump(DB::replaceByPosition('items', $old, $new));
	 * // array(array('gitemid' => 1, 'color' => '00FF00'))
	 *
	 * The new record updated the old one.
	 *
	 * Example 2:
	 * $old = array(2 => array( array('gitemid' => 1, 'color' => 'FF0000') ));
	 * $new = array(
	 *     2 => array(
	 *         array('color' => '00FF00'),
	 *         array('color' => '0000FF')
	 *     )
	 * );
	 * var_dump(DB::replaceByPosition('items', $old, $new));
	 * // array(array('gitemid' => 1, 'color' => '00FF00'), array('gitemid' => 2, 'color' => '0000FF'))
	 *
	 * The first record was updated, the second one - created.
	 *
	 * Example 3:
	 * $old = array(
	 *     2 => array(
	 *         array('gitemid' => 1, 'color' => 'FF0000'),
	 *         array('gitemid' => 2, 'color' => '0000FF')
	 *     )
	 * );
	 * $new = array(2 => array( array('color' => '00FF00') ));
	 * var_dump(DB::replaceByPosition('items', $old, $new));
	 * // array(array('gitemid' => 1, 'color' => '00FF00'))
	 *
	 * The first record was updated, the second one - deleted.
	 *
	 * @param string 	$tableName			table to update
	 * @param array 	$groupedOldRecords	grouped old records
	 * @param array 	$groupedNewRecords	grouped new records
	 *
	 * @return array	array of new records not grouped (!).
	 */
	public static function replaceByPosition($tableName, array $groupedOldRecords, array $groupedNewRecords) {
		$pk = self::getPk($tableName);

		$allOldRecords = [];
		$allNewRecords = [];
		foreach ($groupedNewRecords as $key => $newRecords) {
			// if records exist for the parent object - replace them, otherwise create new records
			if (isset($groupedOldRecords[$key])) {
				$oldRecords = $groupedOldRecords[$key];

				// updated the records by position
				$newRecords = self::mergeRecords($oldRecords, $newRecords, $pk);

				foreach ($oldRecords as $record) {
					$allOldRecords[] = $record;
				}
			}

			foreach ($newRecords as $record) {
				$allNewRecords[] = $record;
			}
		}

		// replace the old records with the new ones
		return self::replace($tableName, $allOldRecords, $allNewRecords);
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
	 * Replace each record in $oldRecords with a corresponding record in $newRecords, but keep the old record IDs.
	 * The records are match by position, that is, the first new record, replaces the first old record and etc.
	 * If there are less $newRecords than $oldRecords, the remaining old records will be discarded.
	 *
	 * @param array 	$oldRecords		array of old records
	 * @param array 	$newRecords		array of new records
	 * @param string 	$pk				name of the private key column
	 *
	 * @return array	array of new records with the primary keys from the old ones
	 */
	protected static function mergeRecords(array $oldRecords, array $newRecords, $pk) {
		$result = [];
		foreach ($newRecords as $i => $record) {
			if (isset($oldRecords[$i])) {
				$record[$pk] = $oldRecords[$i][$pk];
			}

			$result[] = $record;
		}

		return $result;
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

		if ($field_name === 'name' && self::dbFieldExists($table_name, 'name_upper')) {
			return $table_alias.'.name_upper';
		}

		return 'UPPER('.$table_alias.'.'.$field_name.')';
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

			switch ($field_schema['type']) {
				case self::FIELD_TYPE_ID:
					$sql_parts['where'][] = dbConditionId($field_name, $options[$pk_option]);
					break;

				case self::FIELD_TYPE_INT:
				case self::FIELD_TYPE_UINT:
					$sql_parts['where'][] = dbConditionInt($field_name, $options[$pk_option]);
					break;

				default:
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

		$table_schema = DB::getSchema($table_name);
		$unsupported_types = [self::FIELD_TYPE_INT, self::FIELD_TYPE_ID, self::FIELD_TYPE_FLOAT, self::FIELD_TYPE_UINT,
			self::FIELD_TYPE_BLOB
		];

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

			if (in_array($field_schema['type'], $unsupported_types)) {
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
				$pattern = $start.$pattern.'%';

				if ($DB['TYPE'] == ZBX_DB_ORACLE && $field_schema['type'] === DB::FIELD_TYPE_NCLOB
						&& strlen($pattern) > ORACLE_MAX_STRING_SIZE) {
					$chunks = zbx_dbstr(DB::chunkMultibyteStr($pattern, ORACLE_MAX_STRING_SIZE));
					$pattern = 'TO_NCLOB('.implode(') || TO_NCLOB(', $chunks).')';
				}
				else {
					$pattern = zbx_dbstr($pattern);
				}

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

			if ($field_schema['type'] == self::FIELD_TYPE_TEXT || $field_schema['type'] == self::FIELD_TYPE_NCLOB) {
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

			switch ($field_schema['type']) {
				case self::FIELD_TYPE_ID:
					$filter[] = dbConditionId(self::fieldId($field_name, $table_alias), $value);
					break;

				case self::FIELD_TYPE_INT:
				case self::FIELD_TYPE_UINT:
					$filter[] = dbConditionInt(self::fieldId($field_name, $table_alias), $value);
					break;

				default:
					$filter[] = dbConditionString(self::fieldId($field_name, $table_alias), $value);
			}
		}

		if ($filter) {
			$sql_parts['where'][] = implode(' AND ', $filter);
		}

		return $sql_parts;
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
