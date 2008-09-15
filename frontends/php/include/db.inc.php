<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	if(isset($DB_TYPE))		$DB['TYPE'] 	= $DB_TYPE;
	if(isset($DB_SERVER)) 	$DB['SERVER'] 	= $DB_SERVER;
	if(isset($DB_PORT))		$DB['PORT'] 	= $DB_PORT;
	if(isset($DB_DATABASE))	$DB['DATABASE'] = $DB_DATABASE;
	if(isset($DB_USER))		$DB['USER'] 	= $DB_USER;
	if(isset($DB_PASSWORD))	$DB['PASSWORD'] = $DB_PASSWORD;
}

	function DBconnect(&$error){
		$result = true;
		
		global $DB;

		$DB['DB'] = null;
		$DB['TRANSACTIONS'] = 0;
		
//Stats
		$DB['SELECT_COUNT'] = 0;
		$DB['EXECUTE_COUNT'] = 0;

//SDI('type: '.$DB['TYPE'].'; server: '.$DB['SERVER'].'; port: '.$DB['PORT'].'; db: '.$DB['DATABASE'].'; usr: '.$DB['USER'].'; pass: '.$DB['PASSWORD']);

		if(!isset($DB['TYPE'])){
			$error = "Unknown database type.";
			$result = false;
		}
		else{
			switch($DB['TYPE']){
				case 'MYSQL':
					$mysql_server = $DB['SERVER'].( !empty($DB['PORT']) ? ':'.$DB['PORT'] : '');

					if (!$DB['DB']= mysql_pconnect($mysql_server,$DB['USER'],$DB['PASSWORD'])){
						$error = 'Error connecting to database ['.mysql_error().']';
						$result = false;
					}
					else{
						if (!mysql_select_db($DB['DATABASE'])){
							$error = 'Error database selection ['.mysql_error().']';
							$result = false;
						}
					}
					break;
				case 'POSTGRESQL':
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
				case 'ORACLE':
					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], $DB['DATABASE']);
//					$DB['DB']= ociplogon($DB['USER'], $DB['PASSWORD'], '(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST='.$DB['SERVER'].')(PORT=1521))(CONNECT_DATA=(SERVICE_NAME='.$DB['DATABASE'].')))');
					if(!$DB['DB']){
						$error = 'Error connecting to database';
						$result = false;
					}
					break;
				case 'SQLITE3':
					$DB['TRANSACTIONS'] = 0;
					if(!function_exists('init_db_access')){
						function init_db_access(){
							global $DB, $ZBX_SEM_ID;

							$ZBX_SEM_ID = false;
							if(function_exists('ftok') && function_exists('sem_get') && file_exists($DB['DATABASE'])){
								$ZBX_SEM_ID = sem_get(ftok($DB['DATABASE'], 'z'), 1);
							}
						}
					}

					if(!function_exists('lock_db_access')){
						function lock_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_acquire')){
								sem_acquire($ZBX_SEM_ID);
							}
						}
					}

					if(!function_exists('unlock_db_access')){
						function unlock_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_release')) 
								sem_release($ZBX_SEM_ID);
						}
					}

					if(!function_exists('free_db_access')){
						function free_db_access(){
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_remove'))
								sem_remove($ZBX_SEM_ID);

							$ZBX_SEM_ID = false;
						}
					}


					if(file_exists($DB['DATABASE'])){
						$DB['DB']= sqlite3_open($DB['DATABASE']);
						if(!$DB['DB']){
							$error = 'Error connecting to database';
							$result = false;
						}
					}
					else{
						$error = 'Missed database';
						$result = false;
					}

					init_db_access();
					break;
				default:
					$error = 'Unsupported database';
					$result = false;
			}
		}
		if( false == $result )
			$DB['DB']= null;

		return $result;
	}

	function DBclose(){
		global $DB;
		$result = false;

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			switch($DB['TYPE']){
				case 'MYSQL':		
					$result = mysql_close($DB['DB']);	
					break;
				case 'POSTGRESQL':
					$result = pg_close($DB['DB']);
					break;
				case 'ORACLE':
					$result = ocilogoff($DB['DB']);
					break;
				case 'SQLITE3':		
					$result = true; 
					sqlite3_close($DB['DB']);	
					free_db_access();
					break;
				default:		break;
			}
		}

		unset(
			$GLOBALS['DB'],
			$GLOBALS['DB_TYPE'],
			$GLOBALS['DB_SERVER'],
			$GLOBALS['DB_PORT'],
			$GLOBALS['DB_DATABASE'],
			$GLOBALS['DB_USER'],
			$GLOBALS['DB_PASSWORD'],
			$GLOBALS['SQLITE_TRANSACTION']
			);
		
		return $result;
	}

	function DBloadfile($file, &$error){
		global $DB;

		if(!file_exists($file)){
			$error = 'DBloadfile. Missing file['.$file.']';
			return false;
		}
		
		$fl = file($file);
		
		foreach($fl as $n => $l) if(substr($l,0,2)=='--') unset($fl[$n]);
		
		$fl = explode(";\n", implode("\n",$fl));
		unset($fl[count($fl)-1]);
		
		foreach($fl as $sql)
		{
			if(empty($sql)) continue;

			if(!DBexecute($sql,0))
			{
				$error = '';
				return false;
			}
		}
		return true;
	}
	
	function DBstart($comments=false){
		global $DB;
//SDI('DBStart(): '.$DB['TRANSACTIONS']);
		$DB['COMMENTS'] = $comments;
		if($DB['COMMENTS']) info(S_TRANSACTION.': '.S_STARTED_BIG);
		
		$DB['TRANSACTIONS']++;

		if($DB['TRANSACTIONS']>1){
			info('POSSIBLE ERROR: Used incorect logic in database processing, started subtransaction!');
		return $DB['TRANSACTION_STATE'];
		}
		
		$DB['TRANSACTION_STATE'] = true;
		
		$result = false;
		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('begin');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('begin');
				break;
			case 'ORACLE':
				$result = true;
// TODO			OCI_DEFAULT
				break;
			case 'SQLITE3':				
				if(1 == $DB['TRANSACTIONS']){
					lock_db_access();
					$result = DBexecute('begin');
				}
				break;
		}
	return $result;
	}
	
	
	function DBend($result=null){
		global $DB;
//SDI('DBend(): '.$DB['TRANSACTIONS']);
		if($DB['TRANSACTIONS'] != 1){
			$DB['TRANSACTIONS']--;
			
			if($DB['TRANSACTIONS'] < 1){
				$DB['TRANSACTIONS'] = 0;
				$DB['TRANSACTION_STATE'] = false;
				info('POSSIBLE ERROR: Used incorect logic in database processing, transaction not started!');
			}
		return $DB['TRANSACTION_STATE'];
		}

		$DB['TRANSACTIONS'] = 0;
		
		if(is_null($result)){
			$DBresult = $DB['TRANSACTION_STATE'];
		}
		else{
			$DBresult = $result && $DB['TRANSACTION_STATE'];
		}
			
//SDI('Result: '.$result);

		if($DBresult){ // OK
			$DBresult = DBcommit();
		}
		
		$msg = S_TRANSACTION.': '.S_COMMITED_BIG;
		if(!$DBresult){ // FAIL
			DBrollback();
			$msg = S_TRANSACTION.': '.S_ROLLBACKED_BIG;
		}
		if($DB['COMMENTS']) info($msg);
		
		$result = (!is_null($result) && $DBresult)?$result:$DBresult;
		
	return $result;
	}
	
	function DBcommit(){
		global $DB;
		
		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('commit');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('commit');
				break;
			case 'ORACLE':
				$result = ocicommit($DB['DB']);

				break;
			case 'SQLITE3':
				$result = DBexecute('commit');
				unlock_db_access();
				break;
		}
				
	return $result;
	}
	
	function DBrollback(){
		global $DB;

		$result = false;
		if( isset($DB['DB']) && !empty($DB['DB']) )
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = DBexecute('rollback');
				break;
			case 'POSTGRESQL':
				$result = DBexecute('rollback');
				break;
			case 'ORACLE':
				$result = ocirollback($DB['DB']);
				break;
			case 'SQLITE3':				
				$result = DBexecute('rollback');
				unlock_db_access();
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
	*/

	function &DBselect($query, $limit='NO'){
		global $DB;
//COpt::savesqlrequest($query);
		$result = false;
		
		if( isset($DB['DB']) && !empty($DB['DB']) ){
//SDI('SQL: '.$query);
			$DB['SELECT_COUNT']++;
			
			switch($DB['TYPE']){
				case 'MYSQL':
					if(zbx_numeric($limit)){
						$query .= ' limit '.intval($limit);
					}
					$result=mysql_query($query,$DB['DB']);
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
					break;
				case 'POSTGRESQL':
					if(zbx_numeric($limit)){
						$query .= ' limit '.intval($limit);
					}
					$result = pg_query($DB['DB'],$query);
					if(!$result){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
					break;
				case 'ORACLE':
					if(zbx_numeric($limit)){
						$query = 'select * from ('.$query.') where rownum<='.intval($limit);
					}
					$result = DBexecute($query);
					if(!$result){
						$e = ocierror($stid);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
	
					break;
				case 'SQLITE3':
					if(!$DB['TRANSACTIONS']){
						lock_db_access();
					}
					
					if(!($result = sqlite3_query($DB['DB'],$query))){
						error('Error in query ['.$query.'] ['.sqlite3_error($DB['DB']).']');
					}
					else{
						$data = array();
	
						while($row = sqlite3_fetch_array($result)){
							foreach($row as $id => $name){
								if(!zbx_strstr($id,'.')) continue;
								$ids = explode('.',$id);
								$row[array_pop($ids)] = $row[$id];
								unset($row[$id]);
							}
							$data[] = $row;
						}
	
						sqlite3_query_close($result);
	
						$result = &$data;
					}
					if(!$DB['TRANSACTIONS']){
						unlock_db_access();
					}
					break;
			}
			
			if($DB['TRANSACTIONS'] && !$result){
				$DB['TRANSACTION_STATE'] &= $result;
	//			SDI($query);
	//			SDI($DB['TRANSACTION_STATE']);
			}
		}		
		return $result;
	}

	function DBexecute($query, $skip_error_messages=0){
		global $DB;
//COpt::savesqlrequest($query);
		$result = false;

		if( isset($DB['DB']) && !empty($DB['DB']) ){
			$DB['EXECUTE_COUNT']++;	// WRONG FOR ORACLE!!
//SDI('SQL Exec: '.$query);
			switch($DB['TYPE']){
				case 'MYSQL':
					$result=mysql_query($query,$DB['DB']);
	
					if(!$result){
						error('Error in query ['.$query.'] ['.mysql_error().']');
					}
					break;
				case 'POSTGRESQL':
					if(!($result = pg_query($DB['DB'],$query))){
						error('Error in query ['.$query.'] ['.pg_last_error().']');
					}
					break;
				case 'ORACLE':
					$stid=OCIParse($DB['DB'],$query);
					if(!$stid){
						$e=@ocierror();
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					
					$result=@OCIExecute($stid,($DB['TRANSACTIONS']?OCI_DEFAULT:OCI_COMMIT_ON_SUCCESS));
					if(!$result){
						$e=ocierror($stid);
						error('SQL error ['.$e['message'].'] in ['.$e['sqltext'].']');
					}
					else{
						$result = $stid;
					}
	
					break;
				case 'SQLITE3':
					if(!$DB['TRANSACTIONS']){
						lock_db_access();
					}
	
					$result = sqlite3_exec($DB['DB'], $query);
					if(!$result){
						error('Error in query ['.$query.'] ['.sqlite3_error($DB['DB']).']');
					}
					
					if(!$DB['TRANSACTIONS']){
						unlock_db_access();
					}
					break;
			}
			
			if($DB['TRANSACTIONS'] && !$result){
				$DB['TRANSACTION_STATE'] &= $result;
	//			SDI($query);
	//			SDI($DB['TRANSACTION_STATE']);
			}
		}
	return $result;
	}

	function DBfetch(&$cursor){
		global $DB;
	
		$result = false;
		
		if(isset($DB['DB']) && !empty($DB['DB']))
		switch($DB['TYPE']){
			case 'MYSQL':
				$result = mysql_fetch_assoc($cursor);
				break;
			case 'POSTGRESQL':
				$result = pg_fetch_assoc($cursor);
				break;
			case 'ORACLE':
				if(ocifetchinto($cursor, $row, (OCI_ASSOC+OCI_RETURN_NULLS))){
					$result = array();
					foreach($row as $key => $value){
						$result[strtolower($key)] = (str_in_array(strtolower(ocicolumntype($cursor,$key)),array('varchar','varchar2','blob','clob')) && is_null($value))?'':$value;
					}
				}
				break;
			case 'SQLITE3':
				if($cursor){
					$result = array_shift($cursor);
					if(is_null($result)) $result = false;
				}
				break;
		}

	return $result;
	}
	
if(!isset($DB)){
	if(isset($DB_TYPE))	$DB['TYPE'] = $DB_TYPE;
	if(isset($DB_SERVER)) $DB['SERVER'] = $DB_SERVER;
	if(isset($DB_PORT))	$DB['PORT'] = $DB_PORT;
	if(isset($DB_DATABASE))	$DB['DATABASE'] = $DB_DATABASE;
	if(isset($DB_USER))	$DB['USER'] = $DB_USER;
	if(isset($DB_PASSWORD))	$DB['PASSWORD'] = $DB_PASSWORD;
}

/* string value prepearing */
if(isset($DB['TYPE']) && $DB['TYPE'] == 'ORACLE') {
	function zbx_dbstr($var)	{
		return "'".ereg_replace('\'','\'\'',$var)."'";	
	}
	
	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS NUMBER(20)) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == "MYSQL") {
	function zbx_dbstr($var)	{
		return "'".mysql_real_escape_string($var)."'";
	}
	
	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS UNSIGNED) ';
	}
}
else if(isset($DB['TYPE']) && $DB['TYPE'] == "POSTGRESQL") {
	function zbx_dbstr($var)	{
		return "'".pg_escape_string($var)."'";
	}
	
	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
}
else {			
	function zbx_dbstr($var)	{
		return "'".addslashes($var)."'";
	}
	
	function zbx_dbcast_2bigint($field){
		return ' CAST('.$field.' AS BIGINT) ';
	}
} 

	function zbx_dbconcat($params){
		global $DB;

		switch($DB['TYPE']){
			case "SQLITE3":
				return implode(' || ',$params);
			default:
				return 'CONCAT('.implode(',',$params).')';
		}
	}
	
	function zbx_sql_mod($x,$y){
		global $DB;

		switch($DB['TYPE']){
			case "SQLITE3":
				return ' ('.$x.' %% '.$y.')';
			default:
				return ' MOD('.$x.','.$y.')';
		}
	}

	function DBid2nodeid($id_name){
		global $DB;

		switch($DB['TYPE']){
			case "MYSQL":
				$result = '('.$id_name.' div 100000000000000)';
				break;
			case "ORACLE":
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

	function DBin_node( $id_name, $nodes = null ){
		if(is_null($nodes))	$nodes = get_current_nodeid();

		if(empty($nodes))	$nodes = 0;

		if(is_array($nodes)){
			$nodes = implode(',', $nodes);
		}
		else if(is_string($nodes)){
			if ( !eregi('([0-9\,]+)', $nodes ) )
				fatal_error('Incorrect "nodes" for "DBin_node". Passed ['.$nodes.']');
		}
		else if(!zbx_numeric($nodes)){
			fatal_error('Incorrect type of "nodes" for "DBin_node". Passed ['.gettype($nodes).']');
		}
	return (' '.DBid2nodeid($id_name).' in ('.$nodes.') ');
	}

	function in_node( $id_var, $nodes = null ){
		if(is_null($nodes))	
			$nodes = get_current_nodeid();

		if(empty($nodes))
			$nodes = 0;

		if(zbx_numeric($nodes)){
			$nodes = array($nodes);
		}
		else if(is_string($nodes)){
			if(!eregi('([0-9\,]+)',$nodes))
				fatal_error('Incorrect "nodes" for "in_node". Passed ['.$nodes.']');
				
			$nodes = explode(',', $nodes);
		}
		else if(!is_array($nodes)){
			fatal_error('Incorrect type of "nodes" for "in_node". Passed ['.gettype($nodes).']');
		}

	return uint_in_array(id2nodeid($id_var), $nodes);
	}

	function get_dbid($table,$field){
		$nodeid = get_current_nodeid(false);

		$found = false;
		do{
			global $ZBX_LOCALNODEID;

			$min=bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000'));
			$max=bcadd(bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')),'99999999999');
			$row = DBfetch(DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid ." AND table_name='$table' AND field_name='$field'"));
			if(!$row){
				$row=DBfetch(DBselect('SELECT max('.$field.') AS id FROM '.$table.' WHERE '.$field.'>='.$min.' AND '.$field.'<='.$max));
				if(!$row || is_null($row["id"])){
				
					DBexecute('INSERT INTO ids (nodeid,table_name,field_name,nextid) '.
						" VALUES ($nodeid,'$table','$field',$min)");
				}
				else{
					/*
					$ret1 = $row["id"];
					if($ret1 >= $max) {
						"Maximum number of id's was exceeded"
					}
					*/

					DBexecute("INSERT INTO ids (nodeid,table_name,field_name,nextid) VALUES ($nodeid,'$table','$field',".$row["id"].')');
				}
				continue;
			}
			else{
				$ret1 = $row["nextid"];
				if((bccomp($ret1,$min) < 0) || !(bccomp($ret1,$max) < 0)) {
					DBexecute("DELETE FROM ids WHERE nodeid=$nodeid AND table_name='$table' AND field_name='$field'");
					continue;
				}
	
				DBexecute("UPDATE ids SET nextid=nextid+1 WHERE nodeid=$nodeid AND table_name='$table' AND field_name='$field'");
	
				$row = DBfetch(DBselect('SELECT nextid FROM ids WHERE nodeid='.$nodeid." AND table_name='$table' AND field_name='$field'"));
				if(!$row || is_null($row["nextid"])){
					/* Should never be here */
					continue;
				}
				else{
					$ret2 = $row["nextid"];
					if(bccomp(bcadd($ret1,1),$ret2) ==0){
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
		$nodeid = ($nodeid == 0)?get_current_nodeid(false):$nodeid;
		
		$id=remove_nodes_from_id($id);
		$id=bcadd($id,bcadd(bcmul($nodeid,'100000000000000'),bcmul($ZBX_LOCALNODEID,'100000000000')));
	return $id;
	}
	
	function remove_nodes_from_id($id){
		return bcmod($id,'100000000000');
	}

	function DBcondition($fieldname, &$array, $notin=false){
		global $DB;
		$condition = '';
		
		if(!is_array($array)){
			info('DBcondition Error: ['.$fieldname.'] = '.$array);
			$array = explode(',',$array);
			if(empty($array))
				return ' 1=1 ';
		}

		$in = 		$notin?' NOT IN ':' IN ';
		$concat = 	$notin?' AND ':' OR ';

		switch($DB['TYPE']) {
			case 'MYSQL':
			case 'ORACLE':
				$items = array_chunk($array, 950);
				foreach($items as $id => $value){
					$condition.=!empty($condition)?')'.$concat.$fieldname.$in.'(':'';
					$condition.= implode(',',$value);
				}
				break;
			default:
				$condition = implode(',',$array);
		}
		
		if(zbx_empty($condition)) $condition = '0';

	return ' ('.$fieldname.$in.'('.$condition.')) ';
	}
	
?>
