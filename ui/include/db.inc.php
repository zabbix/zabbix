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


/**
 * Creates global database connection.
 *
 * @param string $error returns a message in case of an error
 * @param bool   $debug turns On or Off trace calls when making connections. Suggested debug mode Off during Zabbix setup
 *
 * @return bool
 */
function DBconnect(&$error) {
	global $DB;

	if (isset($DB['DB'])) {
		$error = _('Cannot create another database connection.');
		return false;
	}

	$DB['DB'] = null; // global db handler
	$DB['TRANSACTIONS'] = 0; // level of a nested transaction
	$DB['TRANSACTION_NO_FAILED_SQLS'] = true; // true - if no statements failed in transaction, false - there are failed statements
	$DB['SELECT_COUNT'] = 0; // stats
	$DB['EXECUTE_COUNT'] = 0;

	if (!isset($DB['TYPE'])) {
		$error = 'Unknown database type.';
		return false;
	}

	$db_types = [
		ZBX_DB_MYSQL => MysqlDbBackend::class,
		ZBX_DB_POSTGRESQL => PostgresqlDbBackend::class,
		ZBX_DB_ORACLE => OracleDbBackend::class
	];

	if (!array_key_exists($DB['TYPE'], $db_types)) {
		$error = 'Unsupported database';
		return false;
	}

	$db = new $db_types[$DB['TYPE']];

	if ($DB['ENCRYPTION']) {
		$db->setConnectionSecurity($DB['KEY_FILE'], $DB['CERT_FILE'], $DB['CA_FILE'], $DB['VERIFY_HOST'],
			$DB['CIPHER_LIST']
		);
	}

	$DB['DB'] = $db->connect($DB['SERVER'], $DB['PORT'], $DB['USER'], $DB['PASSWORD'], $DB['DATABASE'], $DB['SCHEMA']);

	if ($DB['DB']) {
		$db->init();
	}

	if ($db->getError() || ($DB['ENCRYPTION'] && !$db->isConnectionSecure()) || !$db->checkDbVersion()
			|| !$db->checkConfig()) {

		$error = $db->getError();
		return false;
	}

	return true;
}

function DBclose() {
	global $DB;

	$result = false;

	if (isset($DB['DB']) && !empty($DB['DB'])) {
		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
				$result = mysqli_close($DB['DB']);
				break;
			case ZBX_DB_POSTGRESQL:
				$result = pg_close($DB['DB']);
				break;
			case ZBX_DB_ORACLE:
				$result = oci_close($DB['DB']);
				break;
		}
	}
	unset($DB['DB']);
	return $result;
}

function DBstart() {
	global $DB;

	$result = false;

	if ($DB['TRANSACTIONS'] != 0) {
		info('POSSIBLE ERROR: Used incorrect logic in database processing, started subtransaction!');
		return $result;
	}

	$DB['TRANSACTIONS']++;
	$DB['TRANSACTION_NO_FAILED_SQLS'] = true;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return $result;
	}

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = DBexecute('BEGIN');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('BEGIN');
			break;
		case ZBX_DB_ORACLE:
			$result = true;
			break;
	}
	return $result;
}

/**
 * Closes transaction.
 *
 * @param string $doCommit True - do commit, rollback otherwise. Rollback is also always performed if a sql failed within this transaction.
 *
 * @return bool True - successful commit, False - otherwise
 */
function DBend($doCommit = true) {
	global $DB;

	$result = false;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return $result;
	}

	if ($DB['TRANSACTIONS'] == 0) {
		info('POSSIBLE ERROR: Used incorrect logic in database processing, transaction not started!');
		return $result;
	}

	$DBresult = $doCommit && $DB['TRANSACTION_NO_FAILED_SQLS'];

	if ($DBresult) {
		$DBresult = DBcommit();
	}
	else {
		DBrollback();
	}

	$DB['TRANSACTIONS'] = 0;

	return (!is_null($doCommit) && $DBresult) ? $doCommit : $DBresult;
}

function DBcommit() {
	global $DB;

	$result = false;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = DBexecute('COMMIT');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('COMMIT');
			break;
		case ZBX_DB_ORACLE:
			$result = oci_commit($DB['DB']);
			break;
	}
	return $result;
}

function DBrollback() {
	global $DB;

	$result = false;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = DBexecute('ROLLBACK');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('ROLLBACK');
			break;
		case ZBX_DB_ORACLE:
			$result = oci_rollback($DB['DB']);
			break;
	}
	return $result;
}

/**
 * Select data from DB. Use function DBexecute for non-selects.
 *
 * Example:
 * DBselect('select * from users')
 * DBselect('select * from users',50,200)
 *
 * @param string $query
 * @param int $limit    max number of record to return
 * @param int $offset   return starting from $offset record
 *
 * @return resource|false
 */
function DBselect($query, $limit = null, $offset = 0) {
	global $DB;

	if (!array_key_exists('DB', $DB) || $DB['DB'] === null) {
		return false;
	}

	$query = DBaddLimit($query, $limit, $offset);

	if ($query === false) {
		return false;
	}

	$time_start = microtime(true);

	$DB['SELECT_COUNT']++;

	$result = false;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			try {
				$result = mysqli_query($DB['DB'], $query);
			}
			catch (mysqli_sql_exception $e) {
				error('Error in query ['.$query.'] ['.$e->getMessage().']', 'sql');
			}

			break;

		case ZBX_DB_POSTGRESQL:
			if (!$result = pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']', 'sql');
			}

			break;

		case ZBX_DB_ORACLE:
			$result = oci_parse($DB['DB'], $query);

			if ($result === false) {
				$e = oci_error();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']', 'sql');

				break;
			}

			if (!@oci_execute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = oci_error($result);
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']', 'sql');
			}

			break;
	}

	if (!$result) {
		$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
	}

	if (CApiService::$userData !== null && array_key_exists('debug_mode', CApiService::$userData)
			&& CApiService::$userData['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
		CProfiler::getInstance()->profileSql(microtime(true) - $time_start, $query);
	}

	return $result;
}

/**
 * Add the LIMIT clause to the given query.
 *
 * NOTE:
 * LIMIT and OFFSET records
 *
 * Example: select 6-15 row.
 *
 * MySQL:
 * SELECT a FROM tbl LIMIT 5,10
 * SELECT a FROM tbl LIMIT 10 OFFSET 5
 *
 * PostgreSQL:
 * SELECT a FROM tbl LIMIT 10 OFFSET 5
 *
 * Oracle:
 * SELECT a FROM tbl WHERE rownum < 15 // ONLY < 15
 * SELECT * FROM (SELECT * FROM tbl) WHERE rownum BETWEEN 6 AND 15
 *
 * @param $query
 * @param int $limit    max number of record to return
 * @param int $offset   return starting from $offset record
 *
 * @return bool|string
 */
function DBaddLimit($query, $limit = 0, $offset = 0) {
	global $DB;

	if ((isset($limit) && ($limit < 0 || !zbx_ctype_digit($limit))) || $offset < 0 || !zbx_ctype_digit($offset)) {
		$moreDetails = isset($limit) ? ' Limit ['.$limit.'] Offset ['.$offset.']' : ' Offset ['.$offset.']';
		error('Incorrect parameters for limit and/or offset. Query ['.$query.']'.$moreDetails, 'sql');

		return false;
	}

	// Process limit and offset
	if (isset($limit)) {
		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
			case ZBX_DB_POSTGRESQL:
				$query .= ' LIMIT '.intval($limit);
				$query .= $offset != 0 ? ' OFFSET '.intval($offset) : '';
				break;
			case ZBX_DB_ORACLE:
				$till = $offset + $limit;
				$query = 'SELECT * FROM ('.$query.') WHERE rownum BETWEEN '.intval($offset).' AND '.intval($till);
				break;
		}
	}

	return $query;
}

/**
 * @param $query
 *
 * @return bool
 */
function DBexecute($query): bool {
	global $DB;

	if (!array_key_exists('DB', $DB) || $DB['DB'] === null) {
		return false;
	}

	$time_start = microtime(true);

	$DB['EXECUTE_COUNT']++;

	$result = false;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			try {
				$result = mysqli_query($DB['DB'], $query);
			}
			catch (mysqli_sql_exception $e) {
				error('Error in query ['.$query.'] ['.$e->getMessage().']', 'sql');
			}

			break;

		case ZBX_DB_POSTGRESQL:
			if (!$result = (bool) pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']', 'sql');
			}

			break;

		case ZBX_DB_ORACLE:
			$result = oci_parse($DB['DB'], $query);

			if ($result === false) {
				$e = oci_error();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']', 'sql');

				break;
			}

			if (!@oci_execute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = oci_error($result);
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']', 'sql');
			}

			$result = true;

			break;
	}

	if (!$result) {
		$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
	}

	if (CApiService::$userData !== null && array_key_exists('debug_mode', CApiService::$userData)
			&& CApiService::$userData['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
		CProfiler::getInstance()->profileSql(microtime(true) - $time_start, $query);
	}

	return (bool) $result;
}

/**
 * Return the next data set from a DB resource or false if there are no more results.
 *
 * @param mixed $cursor        A DB-specific resource returned by DBselect or DBexecute.
 * @param bool  $convertNulls  Convert all null values to string zeros.
 *
 * @return array|bool
 */
function DBfetch($cursor, $convertNulls = true) {
	global $DB;

	if (!array_key_exists('DB', $DB) || $DB['DB'] === null || $cursor === false) {
		return false;
	}

	$result = false;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = mysqli_fetch_assoc($cursor);

			if (!$result) {
				mysqli_free_result($cursor);
			}

			break;

		case ZBX_DB_POSTGRESQL:
			if ($result = pg_fetch_assoc($cursor)) {
				$i = 0;

				foreach ($result as &$value) {
					if (pg_field_type($cursor, $i++) === 'bytea') {
						$value = pg_unescape_bytea($value);
					}
				}
				unset($value);
			}
			else {
				pg_free_result($cursor);
			}

			break;

		case ZBX_DB_ORACLE:
			if ($row = oci_fetch_assoc($cursor)) {
				$result = [];

				foreach ($row as $key => $value) {
					$field_type = strtolower(oci_field_type($cursor, $key));

					// Since Oracle reports nulls for empty strings, convert those back to empty strings.
					$value = (str_in_array($field_type, ['varchar', 'varchar2', 'blob', 'clob']) && is_null($value))
						? ''
						: $value;

					if (is_object($value) && (strpos($field_type, 'lob') !== false)) {
						$value = $value->load();
					}

					$result[strtolower($key)] = $value;
				}
			}

			break;
	}

	if ($result) {
		if ($convertNulls) {
			foreach ($result as $key => $val) {
				if (is_null($val)) {
					$result[$key] = '0';
				}
			}
		}

		return $result;
	}

	return false;
}

function zbx_sql_mod($x, $y) {
	return ' MOD('.$x.','.$y.')';
}

function get_dbid($table, $field) {
	// PGSQL on transaction failure on all queries returns false..
	global $DB;

	if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && $DB['TRANSACTIONS'] && !$DB['TRANSACTION_NO_FAILED_SQLS']) {
		return 0;
	}

	$found = false;

	$min = 0;
	$max = ZBX_DB_MAX_ID;

	do {
		$dbSelect = DBselect('SELECT i.nextid FROM ids i WHERE i.table_name='.zbx_dbstr($table).' AND i.field_name='.zbx_dbstr($field));
		if (!$dbSelect) {
			return false;
		}

		$row = DBfetch($dbSelect);
		if (!$row) {
			$row = DBfetch(DBselect('SELECT MAX('.$field.') AS id FROM '.$table.' WHERE '.$field.' BETWEEN '.$min.' AND '.$max));
			if (!$row || ($row['id'] == 0)) {
				DBexecute("INSERT INTO ids (table_name,field_name,nextid) VALUES ('$table','$field',$min)");
			}
			else {
				DBexecute("INSERT INTO ids (table_name,field_name,nextid) VALUES ('$table','$field',".$row['id'].')');
			}
			continue;
		}
		else {
			$ret1 = $row['nextid'];
			if (bccomp($ret1, $min) < 0 || !bccomp($ret1, $max) < 0) {
				DBexecute('DELETE FROM ids WHERE table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field));
				continue;
			}

			$sql = 'UPDATE ids SET nextid=nextid+1 WHERE table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field);
			DBexecute($sql);

			$row = DBfetch(DBselect('SELECT i.nextid FROM ids i WHERE i.table_name='.zbx_dbstr($table).' AND i.field_name='.zbx_dbstr($field)));
			if (!$row || is_null($row['nextid'])) {
				// should never be here
				continue;
			}
			else {
				$ret2 = $row['nextid'];
				if (bccomp(bcadd($ret1, 1, 0), $ret2, 0) == 0) {
					$found = true;
				}
			}
		}
	}
	while (false == $found);

	return $ret2;
}

function zbx_db_search($table, $options, &$sql_parts) {
	global $DB;
	list($table, $tableShort) = explode(' ', $table);

	$tableSchema = DB::getSchema($table);
	if (!$tableSchema) {
		info(_s('Error in search request for table "%1$s".', $table));
	}

	$start = $options['startSearch'] ? '' : '%';
	$exclude = $options['excludeSearch'] ? ' NOT' : '';
	$glue = $options['searchByAny'] ? ' OR ' : ' AND ';

	$search = [];
	foreach ($options['search'] as $field => $patterns) {
		if (!isset($tableSchema['fields'][$field]) || $patterns === null) {
			continue;
		}

		$patterns = array_filter((array)$patterns, function($pattern) {
			return ($pattern !== '');
		});

		if (!$patterns) {
			continue;
		}

		if ($tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_CHAR
				&& $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_NCLOB
				&& $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_TEXT
				&& $tableSchema['fields'][$field]['type'] !== DB::FIELD_TYPE_CUID) {
			continue;
		}

		$fieldSearch = [];
		foreach ($patterns as $pattern) {
			// escaping parameter that is about to be used in LIKE statement
			$pattern = mb_strtoupper(strtr($pattern, ['!' => '!!', '%' => '!%', '_' => '!_']));

			$pattern = !$options['searchWildcardsEnabled']
				? $start.$pattern.'%'
				: str_replace('*', '%', $pattern);

			if ($DB['TYPE'] == ZBX_DB_ORACLE && $tableSchema['fields'][$field]['type'] === DB::FIELD_TYPE_NCLOB
					&& strlen($pattern) > ORACLE_MAX_STRING_SIZE) {
				$chunks = zbx_dbstr(DB::chunkMultibyteStr($pattern, ORACLE_MAX_STRING_SIZE));
				$pattern = 'TO_NCLOB('.implode(') || TO_NCLOB(', $chunks).')';
			}
			else {
				$pattern = zbx_dbstr($pattern);
			}

			$fieldSearch[] = 'UPPER('.$tableShort.'.'.$field.')'.$exclude.' LIKE '.$pattern." ESCAPE '!'";
		}

		$search[$field] = '('.implode($glue, $fieldSearch).')';
	}

	if ($search) {
		if (isset($sql_parts['where']['search'])) {
			$search[] = $sql_parts['where']['search'];
		}

		$sql_parts['where']['search'] = '('.implode($glue, $search).')';
		return true;
	}

	return false;
}

/**
 * Checks whether all $db_fields keys exists as $args keys.
 *
 * If $db_fields element value is given and corresponding $args is not then it is assigned to $args element.
 *
 * @param $dbFields
 * @param $args
 *
 * @return bool
 */
function check_db_fields($dbFields, &$args) {
	if (!is_array($args)) {
		return false;
	}

	foreach ($dbFields as $field => $def) {
		if (!isset($args[$field])) {
			if (is_null($def)) {
				return false;
			}
			else {
				$args[$field] = $def;
			}
		}
	}

	return true;
}

/**
 * Create condition SQL for field matching against numeric values.
 *
 * @param string $field_name
 * @param array  $values
 * @param bool   $not_in        Create inverse condition.
 * @param bool   $zero_to_null  Cast zero to null.
 *
 * @return string
 */
function dbConditionInt($field_name, array $values, $not_in = false, $zero_to_null = false) {
	global $DB;

	$MIN_NUM_BETWEEN = 4; // Minimum number of consecutive values for using "BETWEEN <id1> AND <idN>".
	$MAX_NUM_IN = 950; // Maximum number of values for using "IN (<id1>,<id2>,...,<idN>)".

	if (is_bool(reset($values))) {
		return $not_in ? '1=1' : '1=0';
	}

	$values = array_flip($values);

	$has_zero = false;

	if ($zero_to_null && array_key_exists(0, $values)) {
		$has_zero = true;
		unset($values[0]);
	}

	$values = array_keys($values);
	natsort($values);
	$values = array_values($values);

	$intervals = [];
	$singles = [];

	if ($DB['TYPE'] == ZBX_DB_ORACLE) {
		// For better performance, use "BETWEEN" constructs for sequential integer values, for Oracle database.

		for ($i = 0, $size = count($values); $i < $size; $i++) {
			if ($i + $MIN_NUM_BETWEEN < $size && bcsub($values[$i + $MIN_NUM_BETWEEN], $values[$i]) == $MIN_NUM_BETWEEN) {
				$interval_first = $values[$i];

				// Search for the last sequential integer value.
				for ($i += $MIN_NUM_BETWEEN; $i < $size && bcsub($values[$i], $values[$i - 1]) == 1; $i++);
				$i--;

				$interval_last = $values[$i];

				// Save the first and last values of the sequential interval.
				$intervals[] = [dbQuoteInt($interval_first), dbQuoteInt($interval_last)];
			}
			else {
				$singles[] = dbQuoteInt($values[$i]);
			}
		}
	}
	else {
		// For better performance, use only "IN" constructs all other databases, except Oracle.

		$singles = array_map(function($value) {
			return dbQuoteInt($value);
		}, $values);
	}

	$condition = '';

	// Process intervals.

	foreach ($intervals as $interval) {
		if ($condition !== '') {
			$condition .= $not_in ? ' AND ' : ' OR ';
		}

		$condition .= ($not_in ? 'NOT ' : '').$field_name.' BETWEEN '.$interval[0].' AND '.$interval[1];
	}

	// Process individual values.

	$single_chunks = array_chunk($singles, $MAX_NUM_IN);

	foreach ($single_chunks as $chunk) {
		if ($condition !== '') {
			$condition .= $not_in ? ' AND ' : ' OR ';
		}

		if (count($chunk) == 1) {
			$condition .= $field_name.($not_in ? '!=' : '=').$chunk[0];
		}
		else {
			$condition .= $field_name.($not_in ? ' NOT' : '').' IN ('.implode(',', $chunk).')';
		}
	}

	if ($has_zero) {
		if ($condition !== '') {
			$condition .= $not_in ? ' AND ' : ' OR ';
		}

		$condition .= $field_name.($not_in ? ' IS NOT NULL' : ' IS NULL');
	}

	if (!$not_in) {
		if ((int) $has_zero + count($intervals) + count($single_chunks) > 1) {
			$condition = '('.$condition.')';
		}
	}

	return $condition;
}

/**
 * Takes an initial part of SQL query and appends a generated WHERE condition.
 *
 * @param string $fieldName		field name to be used in SQL WHERE condition
 * @param array  $values		array of numerical values sorted in ascending order to be included in WHERE
 * @param bool   $notIn			builds inverted condition
 *
 * @return string
 */
function dbConditionId($fieldName, array $values, $notIn = false) {
	return dbConditionInt($fieldName, $values, $notIn, true);
}

/**
 * Takes an initial part of SQL query and appends a generated WHERE condition.
 *
 * @param string $fieldName		field name to be used in SQL WHERE condition
 * @param array  $values		array of string values sorted in ascending order to be included in WHERE
 * @param bool   $notIn			builds inverted condition
 *
 * @return string
 */
function dbConditionString($fieldName, array $values, $notIn = false) {
	switch (count($values)) {
		case 0:
			return '1=0';
		case 1:
			return $notIn
				? $fieldName.'!='.zbx_dbstr(reset($values))
				: $fieldName.'='.zbx_dbstr(reset($values));
	}

	$in = $notIn ? ' NOT IN ' : ' IN ';
	$concat = $notIn ? ' AND ' : ' OR ';
	$items = array_chunk($values, 950);

	$condition = '';
	foreach ($items as $values) {
		$condition .= !empty($condition) ? ')'.$concat.$fieldName.$in.'(' : '';
		$condition .= implode(',', zbx_dbstr($values));
	}

	return '('.$fieldName.$in.'('.$condition.'))';
}

/**
 * Quote a value if not an integer or out of BC Math bounds.
 *
 * @param mixed $value  Either the original or quoted value.
 */
function dbQuoteInt($value) {
	if (!ctype_digit((string) $value) || bccomp($value, ZBX_MAX_UINT64) > 0) {
		$value = zbx_dbstr($value);
	}

	return $value;
}

/**
 * Return SQL for COALESCE like select. For fields with type NCHAR, NVARCHAR or NTEXT in Oracle NVL should be used
 * instead of COALESCE because it will not check that all arguments have same type.
 *
 * @param string     $field_name       Field name to be used in returned query part.
 * @param int|string $default_value    Default value to be returned.
 * @param string     $alias            Alias to be used in 'AS' query part.
 * @return string
 */
function dbConditionCoalesce($field_name, $default_value, $alias = '') {
	global $DB;

	if (is_string($default_value)) {
		$default_value = ($default_value == '') ? '\'\'' : zbx_dbstr($default_value);
	}

	$query = (($DB['TYPE'] == ZBX_DB_ORACLE) ? 'NVL(' : 'COALESCE(').$field_name.','.$default_value.')';

	if ($alias) {
		$query .= ' AS '.$alias;
	}

	return $query;
}

/**
 * Transform DB cursor to array.
 *
 * @return array
 */
function DBfetchArray($cursor) {
	$result = [];
	while ($row = DBfetch($cursor)) {
		$result[] = $row;
	}
	return $result;
}

/**
 * Transform DB cursor to array.
 *
 * @return array
 */
function DBfetchArrayAssoc($cursor, $field) {
	$result = [];
	while ($row = DBfetch($cursor)) {
		$result[$row[$field]] = $row;
	}
	return $result;
}

/**
 * Fetch only values from one column to array.
 *
 * @param resource $cursor
 * @param string   $column
 *
 * @return array
 */
function DBfetchColumn($cursor, $column) {
	$result = [];

	while ($dbResult = DBfetch($cursor)) {
		$result[] = $dbResult[$column];
	}

	return $result;
}

/**
 * Returns true if both IDs are equal.
 *
 * @param $id1
 * @param $id2
 *
 * @return bool
 */
function idcmp($id1, $id2) {
	return (string) $id1 === (string) $id2;
}

/**
 * Escapes the value to be used in the PostgreSQL connection string for the pg_connect() function.
 *
 * @param $string
 *
 * @return string
 */
function pg_connect_escape($string) {
	return addcslashes($string, "'\\");
}

/**
 * Escape string for safe usage in SQL queries.
 * Works for mysql, oracle, postgresql.
 *
 * @param array|string $var
 *
 * @return array|bool|string
 */
function zbx_dbstr($var) {
	global $DB;

	if (!isset($DB['TYPE'])) {
		return false;
	}

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			if (is_array($var)) {
				foreach ($var as $vnum => $value) {
					$var[$vnum] = "'".mysqli_real_escape_string($DB['DB'], $value)."'";
				}
				return $var;
			}
			return "'".mysqli_real_escape_string($DB['DB'], $var)."'";

		case ZBX_DB_ORACLE:
			if (is_array($var)) {
				foreach ($var as $vnum => $value) {
					$var[$vnum] = "'".preg_replace('/\'/', '\'\'', $value)."'";
				}
				return $var;
			}
			return "'".preg_replace('/\'/','\'\'',$var)."'";

		case ZBX_DB_POSTGRESQL:
			if (is_array($var)) {
				foreach ($var as $vnum => $value) {
					$var[$vnum] = "'".pg_escape_string($value)."'";
				}
				return $var;
			}
			return "'".pg_escape_string($var)."'";

		default:
			return false;
	}
}

/**
 * Creates db dependent string with sql expression that casts passed value to bigint.
 * Works for mysql, oracle, postgresql.
 *
 * @param int $field
 *
 * @return bool|string
 */
function zbx_dbcast_2bigint($field) {
	global $DB;

	if (!isset($DB['TYPE'])) {
		return false;
	}

	switch ($DB['TYPE']) {
		case ZBX_DB_POSTGRESQL:
			return 'CAST('.$field.' AS BIGINT)';

		case ZBX_DB_MYSQL:
			return 'CAST('.$field.' AS UNSIGNED)';

		case ZBX_DB_ORACLE:
			return 'CAST('.$field.' AS NUMBER(20))';

		default:
			return false;
	}
}
