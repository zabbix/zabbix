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
if (!isset($DB)) {
	$DB = array();
	if (isset($DB_TYPE)) {
		$DB['TYPE'] = $DB_TYPE;
	}
	if (isset($DB_SERVER)) {
		$DB['SERVER'] = $DB_SERVER;
	}
	if (isset($DB_PORT)) {
		$DB['PORT'] = $DB_PORT;
	}
	if (isset($DB_DATABASE)) {
		$DB['DATABASE'] = $DB_DATABASE;
	}
	if (isset($DB_USER)) {
		$DB['USER'] = $DB_USER;
	}
	if (isset($DB_PASSWORD)) {
		$DB['PASSWORD'] = $DB_PASSWORD;
	}
}

/**
 * Creates global database connection
 *
 * @param string $error returns a message in case of an error
 * @return bool
 */
function DBconnect(&$error) {
	global $DB;
	if (isset($DB['DB'])) {
		$error=_('Cannot create another database connection.');
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
		$DB['TYPE'] = zbx_strtoupper($DB['TYPE']);

		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
				$mysql_server = $DB['SERVER'].(!empty($DB['PORT']) ? ':'.$DB['PORT'] : '');

				if (!$DB['DB'] = mysql_connect($mysql_server, $DB['USER'], $DB['PASSWORD'])) {
					$error = 'Error connecting to database ['.mysql_error().']';
					$result = false;
				}
				else {
					if (!mysql_select_db($DB['DATABASE'])) {
						$error = 'Error database in selection ['.mysql_error().']';
						$result = false;
					}
					else {
						DBexecute('SET NAMES utf8');
					}
				}
				break;
			case ZBX_DB_POSTGRESQL:
				$pg_connection_string =
					(!empty($DB['SERVER']) ? 'host=\''.$DB['SERVER'].'\' ' : '').
					'dbname=\''.$DB['DATABASE'].'\' '.
					(!empty($DB['USER']) ? 'user=\''.$DB['USER'].'\' ' : '').
					(!empty($DB['PASSWORD']) ? 'password=\''.$DB['PASSWORD'].'\' ' : '').
					(!empty($DB['PORT']) ? 'port='.$DB['PORT'] : '');

				$DB['DB']= pg_connect($pg_connection_string);
				if (!$DB['DB']) {
					$error = 'Error connecting to database';
					$result = false;
				}
				elseif (false !== ($pgsql_version = pg_parameter_status('server_version'))) {
					if ((int) $pgsql_version >= 9) {
						// change the output format for values of type bytea from hex (the default) to escape
						DBexecute('set bytea_output = escape');
					}
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

				$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], $connect);
				if (!$DB['DB']) {
					$error = 'Error connecting to database';
					$result = false;
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

				$DB['DB'] = db2_connect($connect, $DB['USER'], $DB['PASSWORD']);
				if (!$DB['DB']) {
					$error = 'Error connecting to database';
					$result = false;
				}
				else {
					$options = array(
						'db2_attr_case' => DB2_CASE_LOWER,
					);
					db2_set_option($DB['DB'], $options, 1);
					if (isset($DB['SCHEMA']) && ($DB['SCHEMA'] != '')) {
						DBexecute("SET CURRENT SCHEMA='".$DB['SCHEMA']."'");
					}
				}
				break;
			case ZBX_DB_SQLITE3:
				if (file_exists($DB['DATABASE'])) {
					init_sqlite3_access();
					lock_sqlite3_access();
					try{
						$DB['DB'] = new SQLite3($DB['DATABASE'], SQLITE3_OPEN_READWRITE);
					}
					catch (Exception $e) {
						$error = 'Error connecting to database';
						$result = false;
					}
					unlock_sqlite3_access();
				}
				else {
					$error = 'Missing database';
					$result = false;
				}
				break;
			default:
				$error = 'Unsupported database';
				$result = false;
		}
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
				$result = mysql_close($DB['DB']);
				break;
			case ZBX_DB_POSTGRESQL:
				$result = pg_close($DB['DB']);
				break;
			case ZBX_DB_ORACLE:
				$result = ocilogoff($DB['DB']);
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

function DBloadfile($file, &$error) {
	if (!file_exists($file)) {
		$error = 'DBloadfile. Missing file ['.$file.']';
		return false;
	}

	$fl = file($file);

	foreach ($fl as $n => $l) {
		if (substr($l, 0, 2) == '--') {
			unset($fl[$n]);
		}
	}
	$fl = explode(";\n", implode("\n", $fl));
	unset($fl[count($fl)-1]);

	foreach ($fl as $sql) {
		if (empty($sql)) {
			continue;
		}
		if (!DBexecute($sql, 0)) {
			$error = '';
			return false;
		}
	}
	return true;
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
			$result = DBexecute('begin');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('begin');
			break;
		case ZBX_DB_ORACLE:
			$result = true;
			break;
		case ZBX_DB_DB2:
			$result = db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_OFF);
			break;
		case ZBX_DB_SQLITE3:
			lock_sqlite3_access();
			$result = DBexecute('begin');
			break;
	}
	return $result;
}

/**
 * Closes transaction
 *
 * @param string $doCommit True - do commit, rollback otherwise. Rollback is also always performed if a sql failed within this transaction.
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
			$result = DBexecute('commit');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('commit');
			break;
		case ZBX_DB_ORACLE:
			$result = ocicommit($DB['DB']);
			break;
		case ZBX_DB_DB2:
			$result = db2_commit($DB['DB']);
			if ($result) {
				db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_ON);
			}
			break;
		case ZBX_DB_SQLITE3:
			$result = DBexecute('commit');
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
			$result = DBexecute('rollback');
			break;
		case ZBX_DB_POSTGRESQL:
			$result = DBexecute('rollback');
			break;
		case ZBX_DB_ORACLE:
			$result = ocirollback($DB['DB']);
			break;
		case ZBX_DB_DB2:
			$result = db2_rollback($DB['DB']);
			db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_ON);
			break;
		case ZBX_DB_SQLITE3:
			$result = DBexecute('rollback');
			unlock_sqlite3_access();
			break;
	}
	return $result;
}

/* NOTE:
	LIMIT and OFFSET records

	Example: select 6-15 row.

	MySQL:
		SELECT a FROM tbl LIMIT 5,10
		SELECT a FROM tbl LIMIT 10 OFFSET 5
	PostgreSQL:
		SELECT a FROM tbl LIMIT 10 OFFSET 5
	Oracle:
		SELECT a FROM tbe WHERE ROWNUM < 15 // ONLY < 15
		SELECT * FROM (SELECT ROWNUM as RN, * FROM tbl) WHERE RN BETWEEN 6 AND 15
//*/
/**
 * Select data from DB. Use function DBexecute for non-selects.
 *
 * Example:
 * DBselect('select * from users')
 * DBselect('select * from users',50,200)
 *
 * @param string $query
 * @param integer $limit max number of record to return
 * @param integer $offset return starting from $offset record
 * @return resource or object, False if failed
 */
function &DBselect($query, $limit = null, $offset = 0) {
	global $DB;
	$result = false;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return $result;
	}

	if ((isset($limit) && ($limit < 0 || !zbx_ctype_digit($limit))) || $offset < 0 || !zbx_ctype_digit($offset)) {
		$moreDetails = isset($limit) ? ' Limit ['.$limit.'] Offset ['.$offset.']' : ' Offset ['.$offset.']';
		error('Incorrect parameters for limit and/or offset. Query ['.$query.']'.$moreDetails);
		return $result;
	}

	$time_start = microtime(true);
	$DB['SELECT_COUNT']++;

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

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			if (!$result = mysql_query($query, $DB['DB'])) {
				error('Error in query ['.$query.'] ['.mysql_error().']');
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']');
			}
			break;
		case ZBX_DB_ORACLE:
			if (!$result = OCIParse($DB['DB'], $query)) {
				$e = @ocierror();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			elseif (!@OCIExecute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = ocierror($result);
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			break;
		case ZBX_DB_DB2:
			$options = array();
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

	// $result is false only if an error occured
	if ($DB['TRANSACTION_NO_FAILED_SQLS'] && !$result) {
		$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
	}

	COpt::savesqlrequest(microtime(true) - $time_start, $query);
	return $result;
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
			if (!$result = mysql_query($query, $DB['DB'])) {
				error('Error in query ['.$query.'] ['.mysql_error().']');
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = (bool) pg_query($DB['DB'], $query)) {
				error('Error in query ['.$query.'] ['.pg_last_error().']');
			}
			break;
		case ZBX_DB_ORACLE:
			if (!$result = OCIParse($DB['DB'], $query)) {
				$e = @ocierror();
				error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
			}
			elseif (!@OCIExecute($result, ($DB['TRANSACTIONS'] ? OCI_DEFAULT : OCI_COMMIT_ON_SUCCESS))) {
				$e = ocierror($result);
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

	COpt::savesqlrequest(microtime(true) - $time_start, $query);
	return (bool) $result;
}

function DBfetch(&$cursor) {
	global $DB;
	$result = false;

	if (!isset($DB['DB']) || empty($DB['DB'])) {
		return $result;
	}

	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			if (!$result = mysql_fetch_assoc($cursor)) {
				mysql_free_result($cursor);
			}
			break;
		case ZBX_DB_POSTGRESQL:
			if (!$result = pg_fetch_assoc($cursor)) {
				pg_free_result($cursor);
			}
			break;
		case ZBX_DB_ORACLE:
			if (ocifetchinto($cursor, $row, (OCI_ASSOC+OCI_RETURN_NULLS))) {
				$result = array();
				foreach ($row as $key => $value) {
					$field_type = zbx_strtolower(oci_field_type($cursor, $key));
					$value = (str_in_array($field_type, array('varchar', 'varchar2', 'blob', 'clob')) && is_null($value)) ? '' : $value;

					if (is_object($value) && (zbx_stristr($field_type, 'lob') !== false)) {
						$value = $value->load();
					}
					$result[zbx_strtolower($key)] = $value;
				}
			}
			break;
		case ZBX_DB_DB2:
			if (!$result = db2_fetch_assoc($cursor)) {
				db2_free_result($cursor);
			}
			break;
		case ZBX_DB_SQLITE3:
			if ($DB['TRANSACTIONS'] == 0) {
				lock_sqlite3_access();
			}
			if (!$result = $cursor->fetchArray(SQLITE3_ASSOC)) {
				unset($cursor);
			}
			if ($DB['TRANSACTIONS'] == 0) {
				unlock_sqlite3_access();
			}
			break;
	}

	if ($result) {
		foreach ($result as $key => $val) {
			if (is_null($val)) {
				$result[$key] = '0';
			}
		}
	}
	return $result;
}

// string value prepearing
if (isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_MYSQL) {
	function zbx_dbstr($var) {
		if (is_array($var)) {
			foreach ($var as $vnum => $value) {
				$var[$vnum] = "'".mysql_real_escape_string($value)."'";
			}
			return $var;
		}
		return "'".mysql_real_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field) {
		return ' CAST('.$field.' AS UNSIGNED) ';
	}
}
elseif (isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
	function zbx_dbstr($var) {
		if (is_array($var)) {
			foreach ($var as $vnum => $value) {
				$var[$vnum] = "'".pg_escape_string($value)."'";
			}
			return $var;
		}
		return "'".pg_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field) {
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
elseif (isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_ORACLE) {
	function zbx_dbstr($var) {
		if (is_array($var)) {
			foreach ($var as $vnum => $value) {
				$var[$vnum] = "'".preg_replace('/\'/', '\'\'', $value)."'";
			}
			return $var;
		}
		return "'".preg_replace('/\'/','\'\'',$var)."'";
	}

	function zbx_dbcast_2bigint($field) {
		return ' CAST('.$field.' AS NUMBER(20)) ';
	}
}
elseif (isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_DB2) {
	function zbx_dbstr($var) {
		if (is_array($var)) {
			foreach ($var as $vnum => $value) {
				$var[$vnum] = "'".db2_escape_string($value)."'";
			}
			return $var;
		}
		return "'".db2_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field) {
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
elseif (isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_SQLITE3) {
	function zbx_dbstr($var) {
		global $DB;

		if (is_array($var)) {
			foreach ($var as $vnum => $value) {
				$var[$vnum] = "'".$DB['DB']->escapeString($value)."'";
			}
			return $var;
		}
		return "'".$DB['DB']->escapeString($var)."'";
	}

	function zbx_dbcast_2bigint($field) {
		return ' CAST('.$field.' AS BIGINT) ';
	}
}

function zbx_dbconcat($params) {
	global $DB;
	switch ($DB['TYPE']) {
		case ZBX_DB_SQLITE3:
			return implode(' || ', $params);
		default:
			return 'CONCAT('.implode(',', $params).')';
	}
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

function DBid2nodeid($id_name) {
	global $DB;
	switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			$result = '('.$id_name.' div 100000000000000)';
			break;
		case ZBX_DB_ORACLE:
			$result = 'round('.$id_name.'/100000000000000)';
			break;
		default:
			$result = '('.$id_name.'/100000000000000)';
	}
	return $result;
}

function id2nodeid($id_var) {
	return (int)bcdiv("$id_var", '100000000000000');
}

function DBin_node($id_name, $nodes = null) {
	if (is_null($nodes)) {
		$nodes = get_current_nodeid();
	}
	elseif (is_bool($nodes)) {
		$nodes = get_current_nodeid($nodes);
	}

	if (empty($nodes)) {
		$nodes = array(0);
	}
	elseif (!is_array($nodes)) {
		if (is_string($nodes)) {
			if (!preg_match('/^([0-9,]+)$/', $nodes)) {
				fatal_error('Incorrect "nodes" for "DBin_node". Passed ['.$nodes.']');
			}
		}
		elseif (!zbx_ctype_digit($nodes)) {
			fatal_error('Incorrect type of "nodes" for "DBin_node". Passed ['.gettype($nodes).']');
		}
		$nodes = zbx_toArray($nodes);
	}

	if (count($nodes) == 1) {
		$nodeid = reset($nodes);
		$sql = $id_name.' BETWEEN '.$nodeid.'00000000000000 AND '.$nodeid.'99999999999999';
	}
	else {
		$sql = '';
		foreach ($nodes as $nodeid) {
			$sql .= '('.$id_name.' BETWEEN '.$nodeid.'00000000000000 AND '.$nodeid.'99999999999999) OR ';
		}
		$sql = '('.rtrim($sql, ' OR ').')';
	}
	return $sql;
}

function in_node($id_var, $nodes = null) {
	if (is_null($nodes)) {
		$nodes = get_current_nodeid();
	}

	if (empty($nodes)) {
		$nodes = 0;
	}

	if (zbx_ctype_digit($nodes)) {
		$nodes = array($nodes);
	}
	elseif (is_string($nodes)) {
		if (!preg_match('/^([0-9,]+)$/', $nodes)) {
			fatal_error('Incorrect "nodes" for "in_node". Passed ['.$nodes.']');
		}
		$nodes = explode(',', $nodes);
	}
	elseif (!is_array($nodes)) {
		fatal_error('Incorrect type of "nodes" for "in_node". Passed ['.gettype($nodes).']');
	}

	return uint_in_array(id2nodeid($id_var), $nodes);
}

function get_dbid($table, $field) {
	// PGSQL on transaction failure on all queries returns false..
	global $DB, $ZBX_LOCALNODEID;

	if ($DB['TYPE'] == ZBX_DB_POSTGRESQL && $DB['TRANSACTIONS'] && !$DB['TRANSACTION_NO_FAILED_SQLS']) {
		return 0;
	}

	$nodeid = get_current_nodeid(false);
	$found = false;

	do {
		$min = bcadd(bcmul($nodeid, '100000000000000', 0), bcmul($ZBX_LOCALNODEID, '100000000000', 0), 0);
		$max = bcadd(bcadd(bcmul($nodeid, '100000000000000', 0), bcmul($ZBX_LOCALNODEID, '100000000000', 0), 0), '99999999999', 0);

		$dbSelect = DBselect('SELECT i.nextid FROM ids i WHERE i.nodeid='.$nodeid.' AND i.table_name='.zbx_dbstr($table).' AND i.field_name='.zbx_dbstr($field));
		if (!$dbSelect) {
			return false;
		}

		$row = DBfetch($dbSelect);
		if (!$row) {
			$row = DBfetch(DBselect('SELECT MAX('.$field.') AS id FROM '.$table.' WHERE '.$field.'>='.$min.' AND '.$field.'<='.$max));
			if (!$row || ($row['id'] == 0)) {
				DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',$min)");
			}
			else {
				DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',".$row['id'].')');
			}
			continue;
		}
		else {
			$ret1 = $row['nextid'];
			if (bccomp($ret1, $min) < 0 || !bccomp($ret1, $max) < 0) {
				DBexecute('DELETE FROM ids WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field));
				continue;
			}

			$sql = 'UPDATE ids SET nextid=nextid+1 WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field);
			DBexecute($sql);

			$row = DBfetch(DBselect('SELECT i.nextid FROM ids i WHERE i.nodeid='.$nodeid.' AND i.table_name='.zbx_dbstr($table).' AND i.field_name='.zbx_dbstr($field)));
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

function create_id_by_nodeid($id, $nodeid = 0) {
	global $ZBX_LOCALNODEID;

	if ($id == 0) {
		return 0;
	}
	$nodeid = ($nodeid == 0) ? get_current_nodeid(false) : $nodeid;

	$id = remove_nodes_from_id($id);
	$id = bcadd($id, bcadd(bcmul($nodeid, '100000000000000'), bcmul($ZBX_LOCALNODEID, '100000000000')), 0);
	return $id;
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
		info('Error in search request for table ['.$table.']');
	}

	$start = is_null($options['startSearch']) ? '%' : '';
	$exclude = is_null($options['excludeSearch']) ? '' : ' NOT ';

	$search = array();
	foreach ($options['search'] as $field => $pattern) {
		if (!isset($tableSchema['fields'][$field]) || zbx_empty($pattern)) {
			continue;
		}
		if ($tableSchema['fields'][$field]['type'] != DB::FIELD_TYPE_CHAR
			&& $tableSchema['fields'][$field]['type'] != DB::FIELD_TYPE_TEXT) {
			continue;
		}

		// escaping parameter that is about to be used in LIKE statement
		$pattern = str_replace("!", "!!", $pattern);
		$pattern = str_replace("%", "!%", $pattern);
		$pattern = str_replace("_", "!_", $pattern);

		if (empty($options['searchWildcardsEnabled'])) {
			$search[$field] =
				' UPPER('.$tableShort.'.'.$field.') '.
				$exclude.' LIKE '.
				zbx_dbstr($start.zbx_strtoupper($pattern).'%').
				" ESCAPE '!'";
		}
		else {
			$pattern = str_replace("*", "%", $pattern);
			$search[$field] =
				' UPPER('.$tableShort.'.'.$field.') '.
				$exclude.' LIKE '.
				zbx_dbstr(zbx_strtoupper($pattern)).
				" ESCAPE '!'";
		}
	}

	if (!empty($search)) {
		if (isset($sql_parts['where']['search'])) {
			$search[] = $sql_parts['where']['search'];
		}

		$glue = (is_null($options['searchByAny']) || $options['searchByAny'] === false) ? ' AND ' : ' OR ';
		$sql_parts['where']['search'] = '( '.implode($glue, $search).' )';
		return true;
	}

	return false;
}

function zbx_db_filter($table, $options, &$sql_parts) {
	list($table, $tableShort) = explode(' ', $table);

	$tableSchema = DB::getSchema($table);
	if (!$tableSchema) {
		info('Error in search request for table ['.$table.']');
	}

	$filter = array();
	foreach ($options['filter'] as $field => $value) {
		if (!isset($tableSchema['fields'][$field]) || zbx_empty($value)) {
			continue;
		}
		zbx_value2array($value);
		$filter[$field] = DBcondition($tableShort.'.'.$field, $value);
	}

	if (!empty($filter)) {
		if (isset($sql_parts['where']['filter'])) {
			$filter[] = $sql_parts['where']['filter'];
		}

		$glue = (is_null($options['searchByAny']) || $options['searchByAny'] === false) ? ' AND ' : ' OR ';
		$sql_parts['where']['filter'] = '( '.implode($glue, $filter).' )';
		return true;
	}
	return false;
}

function zbx_db_sorting(&$sql_parts, $options, $sort_columns, $alias) {
	if (!zbx_empty($options['sortfield'])) {
		if (!is_array($options['sortfield'])) {
			$options['sortfield'] = array($options['sortfield']);
		}
		else {
			$options['sortfield'] = array_unique($options['sortfield']);
		}

		foreach ($options['sortfield'] as $i => $sortfield) {
			// validate sortfield
			if (!str_in_array($sortfield, $sort_columns)) {
				throw new APIException(ZBX_API_ERROR_INTERNAL, _s('Sorting by field "%s" not allowed.', $sortfield));
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
			$sql_parts['order'][] = $alias.'.'.$sortfield.$sortorder;

			// add sort field to select if distinct is used
			if (count($sql_parts['from']) > 1) {
				if (!str_in_array($alias.'.'.$sortfield, $sql_parts['select']) && !str_in_array($alias.'.*', $sql_parts['select'])) {
					$sql_parts['select'][$sortfield] = $alias.'.'.$sortfield;
				}
			}
		}
	}
}

function remove_nodes_from_id($id) {
	return bcmod($id, '100000000000');
}

function check_db_fields($db_fields, &$args) {
	if (!is_array($args)) {
		return false;
	}

	foreach ($db_fields as $field => $def) {
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

function DBcondition($fieldname, $array, $notin = false) {
	$condition = '';

	if (!is_array($array)) {
		throw new APIException(1, 'DBcondition Error: ['.$fieldname.'] = '.$array);
		return ' 1=0 ';
	}

	$in = $notin ? ' NOT IN ':' IN ';
	$concat = $notin ? ' AND ':' OR ';

	$items = array_chunk($array, 950);
	foreach ($items as $id => $values) {
		$condition .= !empty($condition) ? ')'.$concat.$fieldname.$in.'(' : '';
		$condition .= implode(',', zbx_dbstr($values));
	}
	if (zbx_empty($condition)) {
		return ' 1=0 ';
	}
	return ' ('.$fieldname.$in.'('.$condition.')) ';
}

function zero2null($val) {
	return ($val == '0') ? 'NULL' : $val; // string 0 because ('any string' == 0) = true
}

/**
 * Transform DB cursor to array
 *
 * @return array
 */
function DBfetchArray($cursor) {
	$result = array();
	while ($row = DBfetch($cursor)) {
		$result[] = $row;
	}
	return $result;
}

/**
 * Initialize access to SQLite3 database
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
 * Get exclusive lock on SQLite3 database
 *
 * @return bool
 */
function lock_sqlite3_access() {
	global $DB;
	sem_acquire($DB['SEM_ID']);
}

/**
 * Release exclusive lock on SQLite3 database
 *
 * @return bool
 */
function unlock_sqlite3_access() {
	global $DB;
	sem_release($DB['SEM_ID']);
}

class DB {
	const SCHEMA_FILE = 'schema.inc.php';
	const DBEXECUTE_ERROR = 1;
	const RESERVEIDS_ERROR = 2;
	const SCHEMA_ERROR = 3;
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

	private static function exception($code, $errors = array()) {
		throw new APIException($code, $errors);
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

		$nextid = (!$row || is_null($row['id'])) ? self::$minNodeId : $row['id'];

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
			self::$schema = include(self::SCHEMA_FILE);
		}

		if (is_null($table)) {
			return self::$schema;
		}
		elseif (isset(self::$schema[$table])) {
			return self::$schema[$table];
		}
		else {
			self::exception(self::SCHEMA_ERROR, _s('Table "%s" does not exist.', $table));
		}
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
					self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "NULL" for NOT NULL field "%s".', $field));
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
	 * Insert data into DB
	 *
	 * @param string $table
	 * @param array $values pair of fieldname => fieldvalue
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

		foreach ($values as $key => $row) {
			self::checkValueTypes($table, $row);

			if ($getids) {
				$resultIds[$key] = $id;
				$row[$tableSchema['key']] = $id;
				$id = bcadd($id, 1, 0);
			}

			$sql = 'INSERT INTO '.$table.' ('.implode(',', array_keys($row)).')'.
					' VALUES ('.implode(',', array_values($row)).')';
			if (!DBexecute($sql)) {
				self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%s"', $sql));
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
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%s" without values.', $table));
			}

			// set creation
			$sqlSet = '';
			foreach ($row['values'] as $field => $value) {
				$sqlSet .= ' '.$field.'='.$value.',';
			}
			$sqlSet = rtrim($sqlSet, ',');

			if (!isset($row['where']) || empty($row['where']) || !is_array($row['where'])) {
				self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform update statement on table "%s" without where condition.', $table));
			}

			// where condition proccess
			$sqlWhere = array();
			foreach ($row['where'] as $field => $values) {
				if (!isset($tableSchema['fields'][$field]) || is_null($values)) {
					self::exception(self::DBEXECUTE_ERROR, _s('Incorrect field "%1$s" name or value in where statement for table "%2$s".', $field, $table));
				}
				$sqlWhere[] = DBcondition($field, zbx_toArray($values));
			}

			// sql execution
			$sql = 'UPDATE '.$table.' SET '.$sqlSet.' WHERE '.implode(' AND ', $sqlWhere);
			if (!DBexecute($sql)) {
				self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%s"', $sql));
			}
		}
		return true;
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
	 * @param array $where pair of fieldname => fieldvalues
	 * @return bool
	 */
	public static function delete($table, $wheres, $use_or = false) {
		if (empty($wheres) || !is_array($wheres)) {
			self::exception(self::DBEXECUTE_ERROR, _s('Cannot perform delete statement on table "%s" without where condition.', $table));
		}
		$table_schema = self::getSchema($table);

		$sqlWhere = array();
		foreach ($wheres as $field => $values) {
			if (!isset($table_schema['fields'][$field]) || is_null($values)) {
				self::exception(self::DBEXECUTE_ERROR, _s('Incorrect field "%1$s" name or value in where statement for table "%2$s".', $field, $table));
			}
			$sqlWhere[] = DBcondition($field, zbx_toArray($values));
		}

		$sql = 'DELETE FROM '.$table.' WHERE '.implode(($use_or ? ' OR ' : ' AND '), $sqlWhere);
		if (!DBexecute($sql)) {
			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%s"', $sql));
		}
		return true;
	}
}
?>
