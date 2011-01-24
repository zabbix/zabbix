<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

if(!isset($DB)){
	$DB = array();
	if(isset($DB_TYPE))		$DB['TYPE'] 	= $DB_TYPE;
	if(isset($DB_SERVER)) 	$DB['SERVER'] 	= $DB_SERVER;
	if(isset($DB_PORT))		$DB['PORT'] 	= $DB_PORT;
	if(isset($DB_DATABASE))	$DB['DATABASE'] = $DB_DATABASE;
	if(isset($DB_USER))		$DB['USER'] 	= $DB_USER;
	if(isset($DB_PASSWORD))	$DB['PASSWORD'] = $DB_PASSWORD;
}


/**
 * Creates global database connection
 *
 * @param string $error returns a message in case of an error
 * @return bool
 */
	function DBconnect(&$error){
		global $DB;

		if(isset($DB['DB']))
		{
			$error=_('Cannot create another database connection.');
			return false;
		}

		$result = true;

// Global DB handler
		$DB['DB'] = null;
// Level of a nested transation
		$DB['TRANSACTIONS'] = 0;
// True - if no statements failed in transaction, False - there are failed statements
		$DB['TRANSACTION_NO_FAILED_SQLS'] = true;
//Stats
		$DB['SELECT_COUNT'] = 0;
		$DB['EXECUTE_COUNT'] = 0;

//SDI('type: '.$DB['TYPE'].'; server: '.$DB['SERVER'].'; port: '.$DB['PORT'].'; db: '.$DB['DATABASE'].'; usr: '.$DB['USER'].'; pass: '.$DB['PASSWORD']);

		if(!isset($DB['TYPE'])){
			$error = "Unknown database type.";
			$result = false;
		}
		else{
			$DB['TYPE'] = zbx_strtoupper($DB['TYPE']);

			switch($DB['TYPE']){
				case ZBX_DB_MYSQL:
					$mysql_server = $DB['SERVER'].( !empty($DB['PORT']) ? ':'.$DB['PORT'] : '');

					if (!$DB['DB']= mysql_connect($mysql_server,$DB['USER'],$DB['PASSWORD'])){
						$error = 'Error connecting to database ['.mysql_error().']';
						$result = false;
					}
					else{
						if(!mysql_select_db($DB['DATABASE'])){
							$error = 'Error database in selection ['.mysql_error().']';
							$result = false;
						}
						else{
							DBexecute('SET NAMES utf8');
							DBexecute('SET CHARACTER SET utf8');
						}
					}
				break;
				case ZBX_DB_POSTGRESQL:
					$pg_connection_string =
						( !empty($DB['SERVER']) ? 'host=\''.$DB['SERVER'].'\' ' : '').
						'dbname=\''.$DB['DATABASE'].'\' '.
						( !empty($DB['USER']) ? 'user=\''.$DB['USER'].'\' ' : '').
						( !empty($DB['PASSWORD']) ? 'password=\''.$DB['PASSWORD'].'\' ' : '').
						( !empty($DB['PORT']) ? 'port='.$DB['PORT'] : '');

					$DB['DB']= pg_connect($pg_connection_string);
					if(!$DB['DB']){
						$error = 'Error connecting to database';
						$result = false;
					}
				break;
				case ZBX_DB_ORACLE:
					$connect = '';
					if (!empty($DB['SERVER'])){
						$connect = '//'.$DB['SERVER'];

						if ($DB['PORT'] != '0')
							$connect .= ':'.$DB['PORT'];

						if ($DB['DATABASE'])
							$connect .= '/'.$DB['DATABASE'];
					}

					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], $connect);
//					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST='.$DB['SERVER'].')(PORT=1521))(CONNECT_DATA=(SERVICE_NAME='.$DB['DATABASE'].')))');
					if(!$DB['DB']){
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

					$DB['DB']= db2_connect($connect, $DB['USER'], $DB['PASSWORD']);
					if(!$DB['DB']){
						$error = 'Error connecting to database';
						$result = false;
					}
					else{
						$options = array(
							'db2_attr_case' => DB2_CASE_LOWER,
						);
						db2_set_option($DB['DB'], $options, 1);
						if(isset($DB['SCHEMA']) && ($DB['SCHEMA'] != '')){
							DBexecute("SET CURRENT SCHEMA='".$DB['SCHEMA']."'");
						}
					}

				break;
				case ZBX_DB_SQLITE3:
					if(file_exists($DB['DATABASE'])){
						try{
							$DB['DB']= new SQLite3($DB['DATABASE'],SQLITE3_OPEN_READWRITE);
						}
						catch(Exception $e){
							$error = 'Error connecting to database';
							$result = false;
						}
					}
					else{
						$error = 'Missing database';
						$result = false;
					}

					if($result){
						$result = init_sqlite3_access();
					}
				break;
				default:
					$error = 'Unsupported database';
					$result = false;
			}
		}
		if(false == $result)
			$DB['DB'] = null;

		return $result;
	}

	function DBclose(){
		global $DB;
		$result = false;

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			switch($DB['TYPE']){
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
					$DB['DB']->close();
					$result = true;
					break;
			}
		}

		unset($DB['DB']);

		return $result;
	}

	function DBloadfile($file, &$error){
		if(!file_exists($file)){
			$error = 'DBloadfile. Missing file['.$file.']';
			return false;
		}

		$fl = file($file);

		foreach($fl as $n => $l) if(substr($l,0,2)=='--') unset($fl[$n]);

		$fl = explode(";\n", implode("\n",$fl));
		unset($fl[count($fl)-1]);

		foreach($fl as $sql){
			if(empty($sql)) continue;

			if(!DBexecute($sql,0)){
				$error = '';
				return false;
			}
		}
		return true;
	}

	function DBstart($strict=true){
		global $DB;
//SDI('DBStart(): '.$DB['TRANSACTIONS']);
		$DB['STRICT'] = $strict;

		$DB['TRANSACTIONS']++;

		if($DB['TRANSACTIONS']>1){
			info('POSSIBLE ERROR: Used incorrect logic in database processing, started subtransaction!');
			return false;
		}

		$DB['TRANSACTION_NO_FAILED_SQLS'] = true;

		$result = false;
		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case ZBX_DB_MYSQL:
				$result = DBexecute('begin');
				break;
			case ZBX_DB_POSTGRESQL:
				$result = DBexecute('begin');
				break;
			case ZBX_DB_ORACLE:
				$result = true;
// TODO			OCI_DEFAULT
				break;
			case ZBX_DB_DB2:
				$result = db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_OFF);
				break;
			case ZBX_DB_SQLITE3:
				if(lock_sqlite3_access()){
					$result = DBexecute('begin');
				}
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
	function DBend($doCommit=true){
		global $DB;

		if($DB['TRANSACTIONS'] != 1){
			$DB['TRANSACTIONS']--;

			if($DB['TRANSACTIONS'] < 1){
				$DB['TRANSACTIONS'] = 0;
				$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
				info('POSSIBLE ERROR: Used incorrect logic in database processing, transaction not started!');
			}

			$DB['TRANSACTION_NO_FAILED_SQLS'] = $doCommit && $DB['TRANSACTION_NO_FAILED_SQLS'];

			$result =  $DB['TRANSACTION_NO_FAILED_SQLS'];
		}
		else{
			$DBresult = $doCommit && $DB['TRANSACTION_NO_FAILED_SQLS'];

			$DB['TRANSACTIONS'] = 0;

			if($DBresult){ // OK
				$DBresult = DBcommit();
			}

			if(!$DBresult){ // FAIL
				DBrollback();
			}

			$result = (!is_null($doCommit) && $DBresult)?$doCommit:$DBresult;
		}

	return $result;
	}

	function DBcommit(){
		global $DB;

		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
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
				if($result) db2_autocommit($DB['DB'], DB2_AUTOCOMMIT_ON);
				break;
			case ZBX_DB_SQLITE3:
				if(unlock_sqlite3_access()){
					$result = DBexecute('commit');
				}
				break;
		}

	return $result;
	}

	function DBrollback(){
		global $DB;

		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
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
				if(unlock_sqlite3_access()){
					$result = DBexecute('rollback');
				}
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
	function &DBselect($query, $limit=null, $offset=0){
		global $DB;
		$result = false;

		if(
			(isset($limit) && ($limit<0 || !zbx_ctype_digit($limit))) ||
			$offset<0 ||
			!zbx_ctype_digit($offset))
		{
			$moreDetails=isset($limit)?" Limit [$limit] Offset [$offset]":" Offset [$offset]";
			error("Incorrect parameters for limit and/or offset. Query [$query]".$moreDetails);
			return $result;
		}

		$time_start=microtime(true);

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			$DB['SELECT_COUNT']++;
//SDI('SQL['.$DB['SELECT_COUNT'].']: '.$query);
			// Process limit and offset
			switch($DB['TYPE']){
				case ZBX_DB_MYSQL:
				case ZBX_DB_POSTGRESQL:
				case ZBX_DB_SQLITE3:
					if(isset($limit)){
						$query .= ' LIMIT '.intval($limit).' OFFSET '.intval($offset);
					}
				break;
				case ZBX_DB_ORACLE:
				case ZBX_DB_DB2:
					if(isset($limit)){
						$till = $offset + $limit;
						$query = 'SELECT * FROM ('.$query.') WHERE rownum BETWEEN '.intval($offset).' AND '.intval($till);
					}
				break;
			}

			switch($DB['TYPE']){
				case ZBX_DB_MYSQL:
					$result = mysql_query($query,$DB['DB']);
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
					break;
				case ZBX_DB_POSTGRESQL:
					$result = pg_query($DB['DB'],$query);
					if(!$result){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
					break;
				case ZBX_DB_ORACLE:
					$result=OCIParse($DB['DB'],$query);
					if(!$result){
						$e=@ocierror();
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else if(!@OCIExecute($result,($DB['TRANSACTIONS']?OCI_DEFAULT:OCI_COMMIT_ON_SUCCESS))){
						$e=ocierror($result);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
				break;
				case ZBX_DB_DB2:
					$options = array();
					if($DB['TRANSACTIONS']) $options['autocommit'] = DB2_AUTOCOMMIT_OFF;

					if(!$result = db2_prepare($DB['DB'], $query)){
						$e = @db2_stmt_errormsg($result);
						error('SQL error ['.$query.'] in ['.$e.']');
					}
					else if(true !== @db2_execute($result, $options)){
						$e = @db2_stmt_errormsg($result);
						error('SQL error ['.$query.'] in ['.$e.']');
						$result = false;
					}
				break;
				case ZBX_DB_SQLITE3:
					$lock=true;
					if(0 == $DB['TRANSACTIONS']){
						$lock = lock_sqlite3_access();
					}

					if($lock && (!$result = $DB['DB']->query($query))){
						error('Error in query ['.$query.'] Error code ['.$DB['DB']->lastErrorCode().'] Message ['.$DB['DB']->lastErrorMsg().']');
					}

					if($lock && (0 == $DB['TRANSACTIONS'])){
						$lock = unlock_sqlite3_access();
					}

					if(!$lock){
						$result = false;
					}
				break;
			}
//SDI($result);

			// $result is false only if an error occured
			if($DB['TRANSACTION_NO_FAILED_SQLS'] && !$result){
				$DB['TRANSACTION_NO_FAILED_SQLS'] = false;
			}
		}
COpt::savesqlrequest(microtime(true)-$time_start,$query);

	return $result;
	}

	function DBexecute($query, $skip_error_messages=0){
		global $DB;
		$result = false;

		$time_start=microtime(true);
		if( isset($DB['DB']) && !empty($DB['DB']) ){
			$DB['EXECUTE_COUNT']++;
//SDI('SQL xec: '.$query);

			switch($DB['TYPE']){
				case ZBX_DB_MYSQL:
					$result = mysql_query($query,$DB['DB']);
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
				break;
				case ZBX_DB_POSTGRESQL:
					$result = (bool) pg_query($DB['DB'],$query);
					if(!$result){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
				break;
				case ZBX_DB_ORACLE:
					$result=OCIParse($DB['DB'],$query);
					if(!$result){
						$e=@ocierror();
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else if(!@OCIExecute($result,($DB['TRANSACTIONS']?OCI_DEFAULT:OCI_COMMIT_ON_SUCCESS))){
						$e=ocierror($result);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else{
						/* It should be here. The function must return boolean */
						$result = true;
					}
				break;
				case ZBX_DB_DB2:
					if(!$result = db2_prepare($DB['DB'], $query)){
						$e = @db2_stmt_errormsg($result);
						error('SQL error ['.$query.'] in ['.$e.']');
					}
					else if(true !== @db2_execute($result)){
						$e = @db2_stmt_errormsg($result);
						error('SQL error ['.$query.'] in ['.$e.']');
					}
					else{
						/* It should be here. The function must return boolean */
						$result = true;
					}
				break;
				case ZBX_DB_SQLITE3:
					$lock = true;
					if(0 == $DB['TRANSACTIONS']){
						$lock = lock_sqlite3_access();
					}

					if($lock && (!$result = $DB['DB']->exec($query))) {
						error('Error in query ['.$query.'] Error code ['.$DB['DB']->lastErrorCode().'] Message ['.$DB['DB']->lastErrorMsg().']');
					}

					if($lock && (0 == $DB['TRANSACTIONS'])){
						$lock = unlock_sqlite3_access();
					}

					if(!$lock) {
						$result = false;
					}
					break;
			}
			if($DB['TRANSACTIONS'] && !$result){
				$DB['TRANSACTION_NO_FAILED_SQLS']  = false;
			}
		}
COpt::savesqlrequest(microtime(true)-$time_start,$query);
	return (bool) $result;
	}

	function DBfetch(&$cursor){
		global $DB;

		$result = false;

		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case ZBX_DB_MYSQL:
				$result = mysql_fetch_assoc($cursor);
				if(!$result){
					mysql_free_result($cursor);
				}
				break;
			case ZBX_DB_POSTGRESQL:
				$result = pg_fetch_assoc($cursor);
				if(!$result){
					pg_free_result($cursor);
				}
				break;
			case ZBX_DB_ORACLE:
				if(ocifetchinto($cursor, $row, (OCI_ASSOC+OCI_RETURN_NULLS))){
					$result = array();
					foreach($row as $key => $value){
						$field_type = zbx_strtolower(oci_field_type($cursor,$key));
						$value = (str_in_array($field_type,array('varchar','varchar2','blob','clob')) && is_null($value))? '':$value;

						if(is_object($value) && (zbx_stristr($field_type, 'lob') !== false)){
							$value = $value->load();
						}

						$result[zbx_strtolower($key)] = $value;
					}
				}
				break;
			case ZBX_DB_DB2:
				$result = db2_fetch_assoc($cursor);
				if(!$result){
					db2_free_result($cursor);
				}
				break;
			case ZBX_DB_SQLITE3:
				$result = $cursor->fetchArray(SQLITE3_ASSOC);
				if(!$result){
					unset($cursor);
				}
				break;
		}

		if($result){
			foreach($result as $key => $val){
				if(is_null($val)){
					$result[$key] = 0;
				}
			}
		}

	return $result;
	}

// string value prepearing
if(isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_MYSQL) {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".mysql_real_escape_string($value)."'";
			return $var;
		}

	return "'".mysql_real_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS UNSIGNED) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_POSTGRESQL) {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".pg_escape_string($value)."'";
			return $var;
		}

	return "'".pg_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_ORACLE) {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".preg_replace('/\'/','\'\'',$value)."'";
			return $var;
		}

	return "'".preg_replace('/\'/','\'\'',$var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS NUMBER(20)) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == ZBX_DB_DB2) {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".db2_escape_string($value)."'";
			return $var;
		}

	return "'".db2_escape_string($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
else {
	function zbx_dbstr($var){
		if(is_array($var)){
			foreach($var as $vnum => $value) $var[$vnum] = "'".addslashes($value)."'";
			return $var;
		}

	return "'".addslashes($var)."'";
	}

	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}

	function zbx_dbconcat($params){
		global $DB;

		switch($DB['TYPE']){
			case ZBX_DB_SQLITE3:
				return implode(' || ',$params);
			default:
				return 'CONCAT('.implode(',',$params).')';
		}
	}

	function zbx_sql_mod($x,$y){
		global $DB;

		switch($DB['TYPE']){
			case ZBX_DB_SQLITE3:
				return ' ('.$x.' %% '.$y.')';
			default:
				return ' MOD('.$x.','.$y.')';
		}
	}

	function DBid2nodeid($id_name){
		global $DB;

		switch($DB['TYPE']){
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

	function id2nodeid($id_var){
		return (int)bcdiv("$id_var",'100000000000000');
	}

	function DBin_node($id_name, $nodes = null){
		if(is_null($nodes))	$nodes = get_current_nodeid();
		else if(is_bool($nodes)) $nodes = get_current_nodeid($nodes);

		if(empty($nodes)){
			$nodes = array(0);
		}
		else if(!is_array($nodes)){
			if(is_string($nodes)){
				if(!preg_match('/^([0-9,]+)$/', $nodes))
					fatal_error('Incorrect "nodes" for "DBin_node". Passed ['.$nodes.']');
			}
			else if(!zbx_ctype_digit($nodes)){
				fatal_error('Incorrect type of "nodes" for "DBin_node". Passed ['.gettype($nodes).']');
			}

			$nodes = zbx_toArray($nodes);
		}

		$sql = '';
		foreach($nodes as $nnum => $nodeid){
			$sql.= '('.$id_name.'  BETWEEN '.$nodeid.'00000000000000 AND '.$nodeid.'99999999999999)';
			$sql.= ' OR ';
		}

		$sql = '('.trim($sql, 'OR ').')';
	return $sql;
	}

	function in_node( $id_var, $nodes = null ){
		if(is_null($nodes))
			$nodes = get_current_nodeid();

		if(empty($nodes))
			$nodes = 0;

		if(zbx_ctype_digit($nodes)){
			$nodes = array($nodes);
		}
		else if(is_string($nodes)){
			if(!preg_match('/^([0-9,]+)$/', $nodes))
				fatal_error('Incorrect "nodes" for "in_node". Passed ['.$nodes.']');

			$nodes = explode(',', $nodes);
		}
		else if(!is_array($nodes)){
			fatal_error('Incorrect type of "nodes" for "in_node". Passed ['.gettype($nodes).']');
		}

	return uint_in_array(id2nodeid($id_var), $nodes);
	}

	function get_dbid($table,$field){
// PGSQL on transaction failure on all queries returns false..
		global $DB, $ZBX_LOCALNODEID;

		if(($DB['TYPE'] == ZBX_DB_POSTGRESQL) && $DB['TRANSACTIONS'] && !$DB['TRANSACTION_NO_FAILED_SQLS']) return 0;
//------
		$nodeid = get_current_nodeid(false);

		$found = false;
		do{
			$min=bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000'), 0);
			$max=bcadd(bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')),'99999999999', 0);
			$dbSelect = DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid .' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field));

			if(!$dbSelect) return false;

			$row = DBfetch($dbSelect);

			if(!$row){
				$row = DBfetch(DBselect('SELECT max('.$field.') AS id FROM '.$table.' WHERE '.$field.'>='.$min.' AND '.$field.'<='.$max));
				if(!$row || ($row['id'] == 0)){
					DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',$min)");
				}
				else{
/*					$ret1 = $row["id"];
					if($ret1 >= $max) {
						"Maximum number of id's was exceeded"
					}
//*/

					DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',".$row['id'].')');
				}
				continue;
			}
			else{
				$ret1 = $row['nextid'];
				if((bccomp($ret1,$min) < 0) || !(bccomp($ret1,$max) < 0)) {
					DBexecute('DELETE FROM ids WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field));
					continue;
				}

				$sql = 'UPDATE ids SET nextid=nextid+1 WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field);
				DBexecute($sql);

				$row = DBfetch(DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid.' AND table_name='.zbx_dbstr($table).' AND field_name='.zbx_dbstr($field)));
				if(!$row || is_null($row["nextid"])){
// Should never be here
					continue;
				}
				else{
					$ret2 = $row["nextid"];
					if(bccomp(bcadd($ret1,1),$ret2) == 0){
						$found = true;
					}
				}
			}
		}
		while(false == $found);

	return $ret2;
	}

	function create_id_by_nodeid($id,$nodeid=0){
		global $ZBX_LOCALNODEID;

		if($id == 0) return 0;
		$nodeid = ($nodeid == 0)?get_current_nodeid(false):$nodeid;

		$id=remove_nodes_from_id($id);
		$id=bcadd($id,bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')),0);
	return $id;
	}

	function zbx_db_distinct($sql_parts){
		if(count($sql_parts['from']) > 1) return ' DISTINCT ';
		else return ' ';
	}


	function zbx_db_search($table, $options, &$sql_parts){
		list($table, $tableShort) = explode(' ', $table);

		$tableSchema = DB::getSchema($table);
		if(!$tableSchema) info('Error in search request for table ['.$table.']');

		$start = is_null($options['startSearch'])?'%':'';
		$exclude = is_null($options['excludeSearch'])?'':' NOT ';

		$search = array();
		foreach($options['search'] as $field => $pattern){
			if(!isset($tableSchema['fields'][$field]) || zbx_empty($pattern)) continue;
			if($tableSchema['fields'][$field]['type'] != DB::FIELD_TYPE_CHAR) continue;

			// escaping parameter that is about to be used in LIKE statement
			$pattern = str_replace("!", "!!", $pattern);
			$pattern = str_replace("%", "!%", $pattern);
			$pattern = str_replace("_", "!_", $pattern);

			$search[$field] =
				' UPPER('.$tableShort.'.'.$field.') '.
				$exclude.' LIKE '.
				zbx_dbstr($start.zbx_strtoupper($pattern).'%').
				" ESCAPE '!'";
		}

		if(!empty($search)){
			if(isset($sql_parts['where']['search'])){
				$search[] = $sql_parts['where']['search'];
			}

			$glue = is_null($options['searchByAny']) || $options['searchByAny'] === false ? ' AND ' : ' OR ';
			$sql_parts['where']['search'] = '( '.implode($glue, $search).' )';
			return true;
		}

	return false;
	}


	function zbx_db_filter($table, $options, &$sql_parts){
		list($table, $tableShort) = explode(' ', $table);

		$tableSchema = DB::getSchema($table);
		if(!$tableSchema) info('Error in search request for table ['.$table.']');

		$filter = array();
		foreach($options['filter'] as $field => $value){
			if(!isset($tableSchema['fields'][$field]) || zbx_empty($value)) continue;

			zbx_value2array($value);
			switch($tableSchema['fields'][$field]['type']){
				case DB::FIELD_TYPE_CHAR:
					$filter[$field] = DBcondition($tableShort.'.'.$field, $value, false, true);
					break;
				case DB::FIELD_TYPE_INT:
				case DB::FIELD_TYPE_FLOAT:
				case DB::FIELD_TYPE_ID:
					$filter[$field] = DBcondition($tableShort.'.'.$field, $value);
					break;
				default:
					continue;
			}
		}

		if(!empty($filter)){
			if(isset($sql_parts['where']['filter'])){
				$filter[] = $sql_parts['where']['filter'];
			}

			$glue = is_null($options['searchByAny']) || $options['searchByAny'] === false ? ' AND ' : ' OR ';
			$sql_parts['where']['filter'] = '( '.implode($glue, $filter).' )';
			return true;
		}

	return false;
	}


	function remove_nodes_from_id($id){
		return bcmod($id,'100000000000');
	}

	function check_db_fields($db_fields, &$args){
		if(!is_array($args)) return false;

		foreach($db_fields as $field => $def){
			if(!isset($args[$field])){
				if(is_null($def)){
					return false;
				}
				else{
					$args[$field] = $def;
				}
			}
		}
	return true;
	}

	function DBcondition($fieldname, $array, $notin=false, $string=false){
		$condition = '';

		if(!is_array($array)){
			info('DBcondition Error: ['.$fieldname.'] = '.$array);
			return ' 1=0 ';
		}

		$in = 		$notin ? ' NOT IN ':' IN ';
		$concat = 	$notin ? ' AND ':' OR ';

		$items = array_chunk($array, 950);
		foreach($items as $id => $values){
			if($string) $values = zbx_dbstr($values);
			else foreach($values as $value){
				if(!is_numeric($value)){
					info('DBcondition Error: ['.$value.'] incorrect value for numeric field');
					return ' 1=0 ';
				}
			}

			$condition.=!empty($condition) ? ')'.$concat.$fieldname.$in.'(':'';
			$condition.= implode(',',$values);
		}

		if(zbx_empty($condition)) $condition = $string ? "'-1'":'-1';

	return ' ('.$fieldname.$in.'('.$condition.')) ';
	}

	function zero2null($val){
// string 0 beacause ('any string' == 0) = true
		if($val == '0'){
			return 'NULL';
		}
		else return $val;
	}

/**
 * Initialize access to SQLite3 database
 *
 * The function creates a semaphore for exclusive SQLite3 access. It is
 * shared between Zabbix front-end and Zabbix Server.
 *
 * @return bool
 */
if(isset($DB['TYPE']) && ZBX_DB_SQLITE3 == $DB['TYPE']){
	function init_sqlite3_access(){
		global $DB, $ZBX_SEM_ID;

		$ZBX_SEM_ID = sem_get(ftok($DB['DATABASE'], 'z'), 1, 0660);
		return $ZBX_SEM_ID;
	}
}

/**
 * Get exclusive lock on SQLite3 database
 *
 * @return bool
 */
if(isset($DB['TYPE']) && ZBX_DB_SQLITE3 == $DB['TYPE']){
	function lock_sqlite3_access(){
		global $ZBX_SEM_ID,$DB;

		return sem_acquire($ZBX_SEM_ID);
	}
}

/**
 * Release exclusive lock on SQLite3 database
 *
 * @return bool
 */
if(isset($DB['TYPE']) && ZBX_DB_SQLITE3 == $DB['TYPE']){
	function unlock_sqlite3_access(){
		global $ZBX_SEM_ID;

		return sem_release($ZBX_SEM_ID);
	}
}

	class DB{
		const SCHEMA_FILE = 'schema.inc.php';
		const DBEXECUTE_ERROR = 1;
		const RESERVEIDS_ERROR = 2;
		const SCHEMA_ERROR = 3;

		const FIELD_TYPE_INT = 'int';
		const FIELD_TYPE_CHAR = 'char';
		const FIELD_TYPE_ID = 'id';
		const FIELD_TYPE_FLOAT = 'float';
		const FIELD_TYPE_UINT = 'uint';
		const FIELD_TYPE_BLOB = 'blob';

		private static $schema = null;

		private static function exception($code, $errors=array()){
			throw new APIException($code, $errors);
		}

		protected static function reserveIds($table, $count){
			global $ZBX_LOCALNODEID,$DB;

			$nodeid = get_current_nodeid(false);
			$id_name = self::getSchema($table);
			$id_name = $id_name['key'];

			$min = bcadd(bcmul($nodeid,'100000000000000'), bcmul($ZBX_LOCALNODEID,'100000000000'), 0);
			$max = bcadd(bcadd(bcmul($nodeid,'100000000000000'), bcmul($ZBX_LOCALNODEID,'100000000000')),'99999999999', 0);

			$sql = 'SELECT nextid '.
				' FROM ids '.
				' WHERE nodeid='.$nodeid .
					' AND table_name='.zbx_dbstr($table).
					' AND field_name='.zbx_dbstr($id_name);

			// SQLite3 does not support this syntax. Since we are in transation, it can be ignored.
			if($DB['TYPE'] != ZBX_DB_SQLITE3)
			{
				$sql  = $sql.' FOR UPDATE';
			}

			$res = DBfetch(DBselect($sql));
			if($res){
				$nextid = bcadd($res['nextid'], 1, 0);

				if((bccomp($nextid, $max) == 1) || (bccomp($nextid, $min) == -1))
					self::exception(self::RESERVEIDS_ERROR, __METHOD__.' ID out of range for ['.$table.']');

				$sql = 'UPDATE ids '.
					' SET nextid=nextid+'.$count.
					' WHERE nodeid='.$nodeid.
						' AND table_name='.zbx_dbstr($table).
						' AND field_name='.zbx_dbstr($id_name);
				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}
			else{
				$sql = 'SELECT max('.$id_name.') AS id'.
						' FROM '.$table.
						' WHERE '.$id_name.'>='.$min.
							' AND '.$id_name.'<='.$max;
				$row = DBfetch(DBselect($sql));

				$nextid = (!$row || ($row['id'] == 0)) ? $min : $row['id'];

				$sql = 'INSERT INTO ids (nodeid,table_name,field_name,nextid) '.
					' VALUES ('.$nodeid.','.zbx_dbstr($table).','.zbx_dbstr($id_name).','.bcadd($nextid, $count, 0).')';

				$nextid = bcadd($nextid, 1, 0);

				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}

			return $nextid;
		}


		public static function getSchema($table=null){
			if(is_null(self::$schema)){
				self::$schema = include(self::SCHEMA_FILE);
			}

			if(is_null($table))
				return self::$schema;
			else if(isset(self::$schema[$table]))
				return self::$schema[$table];
			else
				self::exception(self::SCHEMA_ERROR, _s('Table "%s" does not exist.', $table));
		}

		public static function checkValueTypes($table, &$values){
			$table_schema = self::getSchema($table);

			foreach($values as $field => $value){
				if(!isset($table_schema['fields'][$field])){
					unset($values[$field]);
					continue;
				}

				if(is_null($values[$field])){
					if($table_schema['fields'][$field]['null'])
						$values[$field] = 'NULL';
					else if(isset($table_schema['fields'][$field]['default']))
						$values[$field] = $table_schema['fields'][$field]['default'];
					else
						self::exception(self::DBEXECUTE_ERROR, _s('Mandatory field "%1$s" is missing in table "%2$s".', $field, $table));

				}

				if(isset($table_schema['fields'][$field]['ref_table'])){
					if($table_schema['fields'][$field]['null'])
						$values[$field] = zero2null($values[$field]);
				}


				if($values[$field] === 'NULL'){
					if(!$table_schema['fields'][$field]['null'])
						self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "NULL" for NOT NULL field "%s".', $field));
				}
				else{
					switch($table_schema['fields'][$field]['type']){
						case self::FIELD_TYPE_CHAR:
							if(zbx_strlen($values[$field]) > $table_schema['fields'][$field]['length']){
								self::exception(self::SCHEMA_ERROR, _s('Value "%1$s" is too long for field "%2$s" - %3$d characters. Allowed length is %4$d characters.',
									$values[$field], $field, zbx_strlen($values[$field]), $table_schema['fields'][$field]['length']));
							}

							$values[$field] = zbx_dbstr($values[$field]);
							break;
						case self::FIELD_TYPE_ID:
						case self::FIELD_TYPE_UINT:
							if(!zbx_ctype_digit($values[$field]))
								self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for unsigned int field "%2$s".', $values[$field], $field));
							break;
						case self::FIELD_TYPE_INT:
							if(!zbx_is_int($values[$field]))
								self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for int field "%2$s".', $values[$field], $field));
							break;
						case self::FIELD_TYPE_FLOAT:
							if(!is_numeric($values[$field]))
								self::exception(self::DBEXECUTE_ERROR, _s('Incorrect value "%1$s" for float field "%2$s".', $values[$field], $field));
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
		public static function insert($table, $values, $getids=true){
			if(empty($values)) return true;
			$resultIds = array();

			if($getids)
				$id = self::reserveIds($table, count($values));

			$table_schema = self::getSchema($table);

			foreach($values as $key => $row){
				self::checkValueTypes($table, $row);

				if($getids){
					$resultIds[$key] = $id;
					$row[$table_schema['key']] = $id;
					$id = bcadd($id, 1, 0);
				}

				$sql = 'INSERT INTO '.$table.' ('.implode(',',array_keys($row)).')'.
					' VALUES ('.implode(',',array_values($row)).')';

				if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
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
		public static function update($table, $data){
			if(empty($data)) return true;

			$data = zbx_toArray($data);

			foreach($data as $dnum => $row){
				$sql_set = '';

				self::checkValueTypes($table, $row['values']);

				foreach($row['values'] as $field => $value){
					$sql_set .= $field.'='.$value.',';
				}

				$sql_set = rtrim($sql_set, ',');

				if(!empty($sql_set)){
					$sql = 'UPDATE '.$table.' SET '.$sql_set.' WHERE '.implode(' AND ', $row['where']);

					if(!DBexecute($sql)) self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
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
		public static function delete($table, $wheres, $use_or=false){
			if(empty($wheres) || !is_array($wheres)){
				return true;
			}

			$table_schema = self::getSchema($table);
			$sql_wheres = array();

//for every field
			foreach($wheres as $field => $values){
//if this field does not exist, just skip it
				if(!isset($table_schema['fields'][$field]) || is_null($values)){
					continue;
				}
				$values = zbx_toArray($values);
				$is_string = ($table_schema['fields'][$field]['type'] == self::FIELD_TYPE_CHAR);

//false = not NOT IN
				$sql_wheres[] = DBcondition($field, $values, false, $is_string);
			}

//we will not delete everything from a table just like this
			if(count($sql_wheres) == 0){
				return false;
			}

			$sql = 'DELETE FROM '.$table.' WHERE '.implode(($use_or ? ' OR ' : ' AND '), $sql_wheres);

			if(!DBexecute($sql)) {
				self::exception(self::DBEXECUTE_ERROR, 'DBEXECUTE_ERROR');
			}
			return true;
		}

	}

?>
