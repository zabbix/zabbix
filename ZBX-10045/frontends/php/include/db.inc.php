<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

	$result = true;

	$DB['DB'] = null; // global db handler
	$DB['TRANSACTIONS'] = 0; // level of a nested transation
	$DB['TRANSACTION_NO_FAILED_SQLS'] = true; // true - if no statements failed in transaction, false - there are failed statements
	$DB['SELECT_COUNT'] = 0; // stats
	$DB['EXECUTE_COUNT'] = 0;

	if (!isset($DB['TYPE'])) {
		$error = 'Unknown database type.';
		$result = false;
	}
	else {
		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
				$DB['DB'] = @mysqli_connect($DB['SERVER'], $DB['USER'], $DB['PASSWORD'], $DB['DATABASE'], $DB['PORT']);
				if (!$DB['DB']) {
					$error = 'Error connecting to database: '.trim(mysqli_connect_error());
					$result = false;
				}
				else {
					DBexecute('SET NAMES utf8');
				}

				if ($result) {
					$dbBackend = new MysqlDbBackend();
				}
				break;
			case ZBX_DB_POSTGRESQL:
				$pg_connection_string =
					(!empty($DB['SERVER']) ? 'host=\''.pg_connect_escape($DB['SERVER']).'\' ' : '').
					'dbname=\''.pg_connect_escape($DB['DATABASE']).'\' '.
					(!empty($DB['USER']) ? 'user=\''.pg_connect_escape($DB['USER']).'\' ' : '').
					(!empty($DB['PASSWORD']) ? 'password=\''.pg_connect_escape($DB['PASSWORD']).'\' ' : '').
					(!empty($DB['PORT']) ? 'port='.pg_connect_escape($DB['PORT']) : '');

				$DB['DB']= @pg_connect($pg_connection_string);
				if (!$DB['DB']) {
					$error = 'Error connecting to database.';
					$result = false;
				}
				else {
					$schemaSet = DBexecute('SET search_path = '.zbx_dbstr($DB['SCHEMA'] ? $DB['SCHEMA'] : 'public'), true);

					if(!$schemaSet) {
						clear_messages();
						$error = pg_last_error();
						$result = false;
					}
					else {
						if (false !== ($pgsql_version = pg_parameter_status('server_version'))) {
							if ((int) $pgsql_version >= 9) {
								// change the output format for values of type bytea from hex (the default) to escape
								DBexecute('SET bytea_output = escape');
							}
						}
					}
				}

				if ($result) {
					$dbBackend = new PostgresqlDbBackend();
				}
				break;
			case ZBX_DB_ORACLE:
				$connect = '';
				if (!empty($DB['SERVER'])) {
					$connect = '//'.$DB['SERVER'];

					if ($DB['PORT'] != '0') {
						$connect .= ':'.$DB['PORT'];
					}
					if ($DB['DATABASE']) {
						$connect .= '/'.$DB['DATABASE'];
					}
				}

				$DB['DB'] = @oci_connect($DB['USER'], $DB['PASSWORD'], $connect);
				if ($DB['DB']) {
					DBexecute('ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.zbx_dbstr('. '));
				}
				else {
					$ociError = oci_error();
					$error = 'Error connecting to database: '.$ociError['message'];
					$result = false;
				}

				if ($result) {
					$dbBackend = new OracleDbBackend();
				}
				break;
			case ZBX_DB_DB2:
				$connect = '';
				$connect .= 'DATABASE='.$DB['DATABASE'].';';
				$connect .= 'HOSTNAME='.$DB['SERVER'].';';
				$connect .= 'PORT='.$DB['PORT'].';';
				$connect .= 'PROTOCOL=TCPIP;';
				$connect .= 'UID='.$DB['USER'].';';
				$connect .= 'PWD='.$DB['PASSWORD'].';';

				$DB['DB'] = @db2_connect($connect, $DB['USER'], $DB['PASSWORD']);
				if (!$DB['DB']) {
					$error = 'Error connecting to database: '.db2_conn_errormsg();
					$result = false;
				}
				else {
					$options = [
						'db2_attr_case' => DB2_CASE_LOWER,
					];
					db2_set_option($DB['DB'], $options, 1);
					if (isset($DB['SCHEMA']) && $DB['SCHEMA'] != '') {
						DBexecute('SET CURRENT SCHEMA='.zbx_dbstr($DB['SCHEMA']));
					}
				}

				if ($result) {
					$dbBackend = new Db2DbBackend();
				}
				break;
			case ZBX_DB_SQLITE3:
				if (file_exists($DB['DATABASE'])) {
					init_sqlite3_access();
					lock_sqlite3_access();
					try{
						$DB['DB'] = @new SQLite3($DB['DATABASE'], SQLITE3_OPEN_READWRITE);
					}
					catch (Exception $e) {
						$error = 'Error connecting to database.';
						$result = false;
					}
					unlock_sqlite3_access();
				}
				else {
					$error = 'Missing database';
					$result = false;
				}

				if ($result) {
					$dbBackend = new SqliteDbBackend();
				}
				break;
			default:
				$error = 'Unsupported database';
				$result = false;
		}
	}

	if ($result && (!$dbBackend->checkDbVersion() || !$dbBackend->checkConfig())) {
		$error = $dbBackend->getError();
		$result = false;
	}

	if (false == $result) {
		$DB['DB'] = null;
	}

	return $result;
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
			case ZBX_DB_DB2:
				$result = db2_close($DB['DB']);
				break;
			case ZBX_DB_SQLITE3:
				lock_sqlite3_access();
				$DB['DB']->close();
				unlock_sqlite3_access();
				$result = true;
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
		case ZBX_DB_DB2:
			$result = db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_OFF);
			break;
		case ZBX_DB_SQLITE3:
			lock_sqlite3_access();
			$result = DBexecute('BEGIN');
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
		case ZBX_DB_DB2:
			$result = db2_commit($DB['DB']);
			if ($result) {
				db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_ON);
			}
			break;
		case ZBX_DB_SQLITE3:
			$result = DBexecute('COMMIT');
			unlock_sqlite3_access();
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
		case ZBX_DB_DB2:
			$result = db2_rollback($DB['DB']);
			db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_ON);
			break;
		case ZBX_DB_SQLITE3:
			$result = DBexecute('ROLLBACK');
			unlock_sqlite3_access();
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
 * @return resource or object, False if failed
 */
function DBselect($query, $limit = null, $offset = 0) {
	global $DB;

	$result = false;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return $result;
	}

	// add the LIMIT clause
	if(!$query = DBaddLimit($query, $limit, $offset)) {
		return false;
	}

	$time_start = microtime(true);
	$DB['SELECT_COUNT']++;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			if (!$result = mysqli_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.mysqli_error($DB['DB']).']');
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']');
			}
			break;
		case ZBX_DB_ORACLE:
			if (!$result = oci_parse($DB['DB'], $query)) {
				$e = @oci_error();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			elseif (!@oci_execute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = oci_error($result);
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			break;
		case ZBX_DB_DB2:
			$options = [];
			if ($DB['TRANSACTIONS']) {
				$options['autocommit'] = DB2_AUTOCOMMIT_OFF;
			}

			if (!$result = db2_prepare($DB['DB'], $query)) {
				$e = @db2_stmt_errormsg($result);
				error('SQL error ['.$query.'] in ['.$e.']');
			}
			elseif (true !== @db2_execute($result, $options)) {
				$e = @db2_stmt_errormsg($result);
				error('SQL error ['.$query.'] in ['.$e.']');
				$result = false;
			}
			break;
		case ZBX_DB_SQLITE3:
			if ($DB['TRANSACTIONS'] == 0) {
				lock_sqlite3_access();
			}
			if (false === ($result = $DB['DB']->query($query))) {
				error('Error in query ['.$query.'] Error code ['.$DB['DB']->lastErrorCode().'] Message ['.$DB['DB']->lastErrorMsg().']');
			}
			if ($DB['TRANSACTIONS'] == 0) {
				unlock_sqlite3_access();
			}
			break;
	}

	// $result is false only if an error occurred
	if ($DB['TRANSACTION_NO_FAILED_SQLS'] && !$result) {
		$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
	}

	CProfiler::getInstance()->profileSql(microtime(true) - $time_start, $query);
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
 * Oracle, DB2:
 * SELECT a FROM tbe WHERE rownum < 15 // ONLY < 15
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
		error('Incorrect parameters for limit and/or offset. Query ['.$query.']'.$moreDetails);

		return false;
	}

	// Process limit and offset
	if (isset($limit)) {
		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
			case ZBX_DB_POSTGRESQL:
			case ZBX_DB_SQLITE3:
				$query .= ' LIMIT '.intval($limit).' OFFSET '.intval($offset);
				break;
			case ZBX_DB_ORACLE:
			case ZBX_DB_DB2:
				$till = $offset + $limit;
				$query = 'SELECT * FROM ('.$query.') WHERE rownum BETWEEN '.intval($offset).' AND '.intval($till);
				break;
		}
	}

	return $query;
}

function DBexecute($query, $skip_error_messages = 0) {
	global $DB;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return false;
	}

	$result = false;
	$time_start = microtime(true);

	$DB['EXECUTE_COUNT']++;

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			if (!$result = mysqli_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.mysqli_error($DB['DB']).']');
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = (bool) pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']');
			}
			break;
		case ZBX_DB_ORACLE:
			if (!$result = oci_parse($DB['DB'], $query)) {
				$e = @oci_error();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			elseif (!@oci_execute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = oci_error($result);
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			else {
				$result = true; // function must return boolean
			}
			break;
		case ZBX_DB_DB2:
			if (!$result = db2_prepare($DB['DB'], $query)) {
				$e = @db2_stmt_errormsg($result);
				error('SQL error ['.$query.'] in ['.$e.']');
			}
			elseif (true !== @db2_execute($result)) {
				$e = @db2_stmt_errormsg($result);
				error('SQL error ['.$query.'] in ['.$e.']');
			}
			else {
				$result = true; // function must return boolean
			}
			break;
		case ZBX_DB_SQLITE3:
			if ($DB['TRANSACTIONS'] == 0) {
				lock_sqlite3_access();
			}
			if (!$result = $DB['DB']->exec($query)) {
				error('Error in query ['.$query.'] Error code ['.$DB['DB']->lastErrorCode().'] Message ['.$DB['DB']->lastErrorMsg().']');
			}
			if ($DB['TRANSACTIONS'] == 0) {
				unlock_sqlite3_access();
			}
			break;
	}
	if ($DB['TRANSACTIONS'] != 0 && !$result) {
		$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
	}

	CProfiler::getInstance()->profileSql(microtime(true) - $time_start, $query);
	return (bool) $result;
}

/**
 * Returns the next data set from a DB resource or false if there are no more results.
 *
 * @param resource $cursor
 * @param bool $convertNulls	convert all null values to string zeroes
 *
 * @return array|bool
 */
function DBfetch($cursor, $convertNulls = true) {
	global $DB;

	$result = false;

	if (!isset($DB['DB']) || empty($DB['DB']) || is_bool($cursor)) {
		return $result;
	}

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = mysqli_fetch_assoc($cursor);
			if (!$result) {
				mysqli_free_result($cursor);
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = pg_fetch_assoc($cursor)) {
				pg_free_result($cursor);
			}
			break;
		case ZBX_DB_ORACLE:
			if ($row = oci_fetch_assoc($cursor)) {
				$result = [];
				foreach ($row as $key => $value) {
					$field_type = strtolower(oci_field_type($cursor, $key));
					// Oracle does not support NULL values for string fields, so if the string is empty, it will return NULL
					// convert it to an empty string to be consistent with other databases
					$value = (str_in_array($field_type, ['varchar', 'varchar2', 'blob', 'clob']) && is_null($value)) ? '' : $value;

					if (is_object($value) && (strpos($field_type, 'lob') !== false)) {
						$value = $value->load();
					}
					$result[strtolower($key)] = $value;
				}
			}
			break;
		case ZBX_DB_DB2:
			if (!$result = db2_fetch_assoc($cursor)) {
				db2_free_result($cursor);
			}
			else {
				// cast all of the values to string to be consistent with other DB drivers: all of them return
				// only strings.
				foreach ($result as &$value) {
					if ($value !== null) {
						$value = (string) $value;
					}
				}
				unset($value);
			}
			break;
		case ZBX_DB_SQLITE3:
			if ($DB['TRANSACTIONS'] == 0) {
				lock_sqlite3_access();
			}
			if (!$result = $cursor->fetchArray(SQLITE3_ASSOC)) {
				unset($cursor);
			}
			else {
				// cast all of the values to string to be consistent with other DB drivers: all of them return
				// only strings.
				foreach ($result as &$value) {
					$value = (string) $value;
				}
				unset($value);
			}
			if ($DB['TRANSACTIONS'] == 0) {
				unlock_sqlite3_access();
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
	global $DB;

	switch ($DB['TYPE']) {
		case ZBX_DB_SQLITE3:
			return ' (('.$x.') % ('.$y.'))';
		default:
			return ' MOD('.$x.','.$y.')';
	}
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

function zbx_db_distinct($sql_parts) {
	if (count($sql_parts['from']) > 1) {
		return ' DISTINCT ';
	}
	else {
		return ' ';
	}
}

function zbx_db_search($table, $options, &$sql_parts) {
	list($table, $tableShort) = explode(' ', $table);

	$tableSchema = DB::getSchema($table);
	if (!$tableSchema) {
		info(_s('Error in search request for table "%1$s".', $table));
	}

	$start = is_null($options['startSearch']) ? '%' : '';
	$exclude = is_null($options['excludeSearch']) ? '' : ' NOT ';
	$glue = (!$options['searchByAny']) ? ' AND ' : ' OR ';

	$search = [];
	foreach ($options['search'] as $field => $patterns) {
		if (!isset($tableSchema['fields'][$field]) || zbx_empty($patterns)) {
			continue;
		}
		if ($tableSchema['fields'][$field]['type'] != DB::FIELD_TYPE_CHAR
			&& $tableSchema['fields'][$field]['type'] != DB::FIELD_TYPE_TEXT) {
			continue;
		}

		$fieldSearch = [];
		foreach ((array) $patterns as $pattern) {
			if (zbx_empty($pattern)) {
				continue;
			}

			// escaping parameter that is about to be used in LIKE statement
			$pattern = str_replace("!", "!!", $pattern);
			$pattern = str_replace("%", "!%", $pattern);
			$pattern = str_replace("_", "!_", $pattern);

			if (!$options['searchWildcardsEnabled']) {
				$fieldSearch[] =
					' UPPER('.$tableShort.'.'.$field.') '.
					$exclude.' LIKE '.
					zbx_dbstr($start.mb_strtoupper($pattern).'%').
					" ESCAPE '!'";
			}
			else {
				$pattern = str_replace("*", "%", $pattern);
				$fieldSearch[] =
					' UPPER('.$tableShort.'.'.$field.') '.
					$exclude.' LIKE '.
					zbx_dbstr(mb_strtoupper($pattern)).
					" ESCAPE '!'";
			}
		}

		$search[$field] = '( '.implode($glue, $fieldSearch).' )';
	}

	if (!empty($search)) {
		if (isset($sql_parts['where']['search'])) {
			$search[] = $sql_parts['where']['search'];
		}

		$sql_parts['where']['search'] = '( '.implode($glue, $search).' )';
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
 * Takes an initial part of SQL query and appends a generated WHERE condition.
 * The WHERE condition is generated from the given list of values as a mix of
 * <fieldname> BETWEEN <id1> AND <idN>" and "<fieldname> IN (<id1>,<id2>,...,<idN>)" elements.
 *
 * In some frontend places we can get array with bool as input values parameter. This is fail!
 * Therefore we need check it and return 1=0 as temporary solution to not break the frontend.
 *
 * @param string $fieldName		field name to be used in SQL WHERE condition
 * @param array  $values		array of numerical values sorted in ascending order to be included in WHERE
 * @param bool   $notIn			builds inverted condition
 * @param bool   $sort			values mandatory must be sorted
 *
 * @return string
 */
function dbConditionInt($fieldName, array $values, $notIn = false, $sort = true) {
	$MAX_EXPRESSIONS = 950; // maximum  number of values for using "IN (id1>,<id2>,...,<idN>)"
	$MIN_NUM_BETWEEN = 4; // minimum number of consecutive values for using "BETWEEN <id1> AND <idN>"

	if (is_bool(reset($values))) {
		return '1=0';
	}

	$values = array_keys(array_flip($values));

	if ($sort) {
		natsort($values);

		$values = array_values($values);
	}

	$betweens = [];
	$data = [];

	for ($i = 0, $size = count($values); $i < $size; $i++) {
		$between = [];

		// analyze by chunk
		if (isset($values[$i + $MIN_NUM_BETWEEN])
				&& bccomp(bcadd($values[$i], $MIN_NUM_BETWEEN), $values[$i + $MIN_NUM_BETWEEN]) == 0) {
			for ($sizeMinBetween = $i + $MIN_NUM_BETWEEN; $i < $sizeMinBetween; $i++) {
				$between[] = $values[$i];
			}

			$i--; // shift 1 back

			// analyze by one
			for (; $i < $size; $i++) {
				if (isset($values[$i + 1]) && bccomp(bcadd($values[$i], 1), $values[$i + 1]) == 0) {
					$between[] = $values[$i + 1];
				}
				else {
					break;
				}
			}

			$betweens[] = $between;
		}
		else {
			$data[] = $values[$i];
		}
	}

	// concatenate conditions
	$dataSize = count($data);
	$betweenSize = count($betweens);

	$condition = '';
	$operatorAnd = $notIn ? ' AND ' : ' OR ';

	if ($betweens) {
		$operatorNot = $notIn ? 'NOT ' : '';

		foreach ($betweens as $between) {
			$between = $operatorNot.$fieldName.' BETWEEN '.zbx_dbstr($between[0]).' AND '.zbx_dbstr(end($between));

			$condition .= $condition ? $operatorAnd.$between : $between;
		}
	}

	if ($dataSize == 1) {
		$operator = $notIn ? '!=' : '=';

		$condition .= ($condition ? $operatorAnd : '').$fieldName.$operator.zbx_dbstr($data[0]);
	}
	else {
		$operatorNot = $notIn ? ' NOT' : '';
		$data = array_chunk($data, $MAX_EXPRESSIONS);

		foreach ($data as $chunk) {
			$chunkIns = '';

			foreach ($chunk as $value) {
				$chunkIns .= ','.zbx_dbstr($value);
			}

			$chunkIns = $fieldName.$operatorNot.' IN ('.substr($chunkIns, 1).')';

			$condition .= $condition ? $operatorAnd.$chunkIns : $chunkIns;
		}
	}

	return (($dataSize && $betweenSize) || $betweenSize > 1 || $dataSize > $MAX_EXPRESSIONS) ? '('.$condition.')' : $condition;
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
 * @param bool     $asHash
 *
 * @return array
 */
function DBfetchColumn($cursor, $column, $asHash = false) {
	$result = [];

	while ($dbResult = DBfetch($cursor)) {
		if ($asHash) {
			$result[$dbResult[$column]] = $dbResult[$column];
		}
		else {
			$result[] = $dbResult[$column];
		}
	}

	return $result;
}

/**
 * Initialize access to SQLite3 database.
 *
 * The function creates a semaphore for exclusive SQLite3 access. It is
 * shared between Zabbix front-end and Zabbix Server.
 *
 * @return bool
 */
function init_sqlite3_access() {
	global $DB;

	$DB['SEM_ID'] = sem_get(ftok($DB['DATABASE'], 'z'), 1, 0660);
}

/**
 * Get exclusive lock on SQLite3 database.
 *
 * @return bool
 */
function lock_sqlite3_access() {
	global $DB;

	sem_acquire($DB['SEM_ID']);
}

/**
 * Release exclusive lock on SQLite3 database.
 *
 * @return bool
 */
function unlock_sqlite3_access() {
	global $DB;

	sem_release($DB['SEM_ID']);
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
 * Works for ibmdb2, mysql, oracle, postgresql, sqlite.
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
		case ZBX_DB_DB2:
			if (is_array($var)) {
				foreach ($var as $vnum => $value) {
					$var[$vnum] = "'".db2_escape_string($value)."'";
				}
				return $var;
			}
			return "'".db2_escape_string($var)."'";

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

		case ZBX_DB_SQLITE3:
			if (is_array($var)) {
				foreach ($var as $vnum => $value) {
					$var[$vnum] = "'".$DB['DB']->escapeString($value)."'";
				}
				return $var;
			}
			return "'".$DB['DB']->escapeString($var)."'";

		default:
			return false;
	}
}

/**
 * Creates db dependent string with sql expression that casts passed value to bigint.
 * Works for ibmdb2, mysql, oracle, postgresql, sqlite.
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
		case ZBX_DB_DB2:
		case ZBX_DB_POSTGRESQL:
		case ZBX_DB_SQLITE3:
			return 'CAST('.$field.' AS BIGINT)';

		case ZBX_DB_MYSQL:
			return 'CAST('.$field.' AS UNSIGNED)';

		case ZBX_DB_ORACLE:
			return 'CAST('.$field.' AS NUMBER(20))';

		default:
			return false;
	}
}
