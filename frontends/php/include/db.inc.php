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
	global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

	function	DBconnect(&$error)
	{
		$result = true;
		
		global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

		$DB = null;

//SDI('type: '.$DB_TYPE.'; server: '.$DB_SERVER.'; port: '.$DB_PORT.'; db: '.$DB_DATABASE.'; usr: '.$DB_USER.'; pass: '.$DB_PASSWORD);

		if(!isset($DB_TYPE))
		{
			$error = "Unknown database type.";
			$result = false;
		}
		else
		{
			switch($DB_TYPE)
			{
				case "MYSQL":
					$mysql_server = $DB_SERVER.( !empty($DB_PORT) ? ':'.$DB_PORT : '');

					if ( !($DB = mysql_pconnect($mysql_server,$DB_USER,$DB_PASSWORD)))
					{
						$error = "Error connecting to database [".mysql_error()."]";
						$result = false;
					}
					else
					{
						if ( !mysql_select_db($DB_DATABASE) )
						{
							$error = 'Error database selection ['.mysql_error().']';
							$result = false;
						}
					}
					break;
				case "POSTGRESQL":
					$pg_connection_string = 
						( !empty($DB_SERVER) ? 'host=\''.$DB_SERVER.'\' ' : '').
						'dbname=\''.$DB_DATABASE.'\' '.
						( !empty($DB_USER) ? 'user=\''.$DB_USER.'\' ' : '').
						( !empty($DB_PASSWORD) ? 'password=\''.$DB_PASSWORD.'\' ' : '').
						( !empty($DB_PORT) ? 'port='.$DB_PORT : '');

					$DB=pg_connect($pg_connection_string);
					if(!$DB)
					{
						$error = 'Error connecting to database';
						$result = false;
					}
					break;
				case "ORACLE":
					$DB = ocilogon($DB_USER, $DB_PASSWORD, $DB_DATABASE);
					//$DB = ocilogon($DB_USER, $DB_PASSWORD, "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$DB_SERVER)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=$DB_DATABASE)))");
					if(!$DB)
					{
						$error = "Error connecting to database";
						$result = false;
					}
					break;
				case "SQLITE3":
					if(!function_exists('init_db_access'))
					{
						function init_db_access()
						{
							global $DB_DATABASE, $ZBX_SEM_ID;

							$ZBX_SEM_ID = false;
							if(function_exists('ftok') && function_exists('sem_get') &&
								file_exists($DB_DATABASE))
							{
								$ZBX_SEM_ID = sem_get(ftok($DB_DATABASE, 'z'), 1);
							}
						}
					}

					if(!function_exists('lock_db_access'))
					{
						function lock_db_access()
						{
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_acquire'))
							{
								sem_acquire($ZBX_SEM_ID);
							}
						}
					}

					if(!function_exists('unlock_db_access'))
					{
						function unlock_db_access()
						{
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_release'))
								sem_release($ZBX_SEM_ID);
						}
					}

					if(!function_exists('free_db_access'))
					{
						function free_db_access()
						{
							global $ZBX_SEM_ID;

							if($ZBX_SEM_ID && function_exists('sem_remove'))
								sem_remove($ZBX_SEM_ID);

							$ZBX_SEM_ID = false;
						}
					}


					if(file_exists($DB_DATABASE))
					{
						$DB = sqlite3_open($DB_DATABASE);
						if(!$DB)
						{
							$error = "Error connecting to database";
							$result = false;
						}
					}
					else
					{
						$error = "Missed database";
						$result = false;
					}

					init_db_access();
					break;
				default:
					$error = "Unsupported database";
					$result = false;
			}
		}
		if( false == $result )
			$DB = null;

		return $result;
	}

	function	DBclose()
	{
		global $DB, $DB_TYPE, $DB_SERVER, $DB_PORT, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

		$result = false;

		if( isset($DB) && !empty($DB) )
		{
			switch($DB_TYPE)
			{
				case "MYSQL":		$result = mysql_close($DB);	break;
				case "POSTGRESQL":	$result = pg_close($DB);	break;
				case "ORACLE":		$result = ocilogoff($DB);	break;
				case "SQLITE3":		
					$result = true; 
					sqlite3_close($DB);	
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
			$GLOBALS['DB_PASSWORD']
			);
		
		return $result;
	}

	function	DBloadfile($file, &$error)
	{
		global $DB_TYPE;

		if(!file_exists($file))
		{
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

	function	DBstart()
	{
		/* TODO *//* start transaction */
		// lock_db_access(); /* check DBselect & DBexecute */
	}
	
	function	DBend($result)
	{
		/* end transaction *//* TODO */

		if($result)
		{ // OK
			/* commit TODO */
		}
		else
		{ // FAIL
			/* rollback  TODO */
		}
		// unlock_db_access(); /* check DBselect & DBexecute */
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

	function	&DBselect($query, $limit='NO')
	{
		global $DB, $DB_TYPE;

COpt::savesqlrequest($query);

		$result = false;
		if( isset($DB) && !empty($DB) )
		switch($DB_TYPE)
		{
			case "MYSQL":
				if(is_numeric($limit))
				{
					$query .= ' limit '.intval($limit);
				}
				$result=mysql_query($query,$DB);
				if(!$result)
				{
					error("Error in query [$query] [".mysql_error()."]");
				}
				break;
			case "POSTGRESQL":
				if(is_numeric($limit))
				{
					$query .= ' limit '.intval($limit);
				}
				if(!($result = pg_query($DB,$query)))
				{
					error("Error in query [$query] [".pg_last_error()."]");
				}
				break;
			case "ORACLE":
				if(is_numeric($limit))
				{
					$query = 'select * from ('.$query.') where rownum<'.intval($limit);
				}

				$stid=OCIParse($DB,$query);
				if(!$stid)
				{
					$e=@ocierror();
					error("SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
				}
				$result=@OCIExecute($stid);
				if(!$result)
				{
					$e=ocierror($stid);
					error("SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
				}
				else
				{
					$result = $stid;
				}
				break;
			case "SQLITE3":
				lock_db_access();
				if(!($result = sqlite3_query($DB,$query)))
                                {
                                        error("Error in query [$query] [".sqlite3_error($DB)."]");
                                }
				else
				{
					$data = array();

					while($row = sqlite3_fetch_array($result))
					{
						foreach($row as $id => $name)
						{
							if(!strstr($id,'.')) continue;
							$ids = explode('.',$id);
							$row[array_pop($ids)] = $row[$id];
							unset($row[$id]);
						}
						$data[] = $row;
					}

					sqlite3_query_close($result);

					$result = &$data;
				}
				unlock_db_access();
				break;
		}
		
		return $result;
	}

	function	DBexecute($query, $skip_error_messages=0)
	{
		global $DB,$DB_TYPE;

COpt::savesqlrequest($query);

		$result = false;

		if( isset($DB) && !empty($DB) )
		switch($DB_TYPE)
		{
			case "MYSQL":
				$result=mysql_query($query,$DB);

				if(!$result)
				{
					error("Error in query [$query] [".mysql_error()."]");
				}
				break;
			case "POSTGRESQL":
				if(!($result = pg_query($DB,$query)))
				{
					error("Error in query [$query] [".pg_last_error()."]");
				}
				break;
			case "ORACLE":
				$result = DBselect($query);
				if(!$result)
				{
					$e = ocierror($stid);
					error("SQL error [".$e["message"]."] in [".$e["sqltext"]."]");
				}
				break;
			case "SQLITE3":
				lock_db_access();
				$result = sqlite3_exec($DB, $query);
				if(!$result)
				{
					error("Error in query [$query] [".sqlite3_error($DB)."]");
				}
				unlock_db_access();
				break;
		}

		return $result;
	}

	function	DBfetch(&$cursor)
	{
		global $DB, $DB_TYPE;
	
		$result = false;
		
		if( isset($DB) && !empty($DB) )
		switch($DB_TYPE)
		{
			case "MYSQL":
				$result = mysql_fetch_array($cursor);
				break;
			case "POSTGRESQL":
				$result = pg_fetch_array($cursor);
				break;
			case "ORACLE":
				if(ocifetchinto($cursor, $row, OCI_ASSOC+OCI_NUM+OCI_RETURN_NULLS))
				{
					$result = array();
					$keys = (array_keys($row));
					foreach ($keys as $k)		$result[strtolower($k)] = $row[$k];
				} 
				break;
			case "SQLITE3":
				if($cursor)
				{
					$result = array_shift($cursor);

					if(is_null($result)) $result = false;

				}
				break;
		}

		return $result;
	}
	

/* string value prepearing */
if(isset($DB_TYPE) && $DB_TYPE == "ORACLE") {	
	function	zbx_dbstr($var)	{
		return "'".ereg_replace('\'','\'\'',$var)."'";	
	}
} else {			
	function	zbx_dbstr($var)	{
		return "'".addslashes($var)."'";
	}
}

	function	zbx_dbconcat($params)
	{
		global $DB_TYPE;

		switch($DB_TYPE)
		{
			case "SQLITE3":
				return implode(' || ',$params);
			default:
				return 'CONCAT('.implode(',',$params).')';
		}
	}

	function DBid2nodeid($id_name)
	{
		global $DB_TYPE;

		switch($DB_TYPE)
		{
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

	function id2nodeid($id_var)
	{
		return (int)bcdiv("$id_var",'100000000000000');
	}

	function DBin_node( $id_name, $nodes = null )
	{
		if ( is_null($nodes) )	$nodes = get_current_nodeid();

		if ( empty($nodes) )	$nodes = 0;

		if ( is_array($nodes) )
		{
			$nodes = implode(',', $nodes);
		}
		else if ( is_string($nodes) )
		{
			if ( !eregi('([0-9\,]+)', $nodes ) )
				fatal_error('Incorrect "nodes" for "DBin_node". Passed ['.$nodes.']');
		}
		else if ( !is_numeric($nodes) )
		{
			fatal_error('Incorrect type of "nodes" for "DBin_node". Passed ['.gettype($nodes).']');
		}

		return (' '.DBid2nodeid($id_name).' in ('.$nodes.') ');
	}

	function in_node( $id_var, $nodes = null )
	{
		if ( is_null($nodes) )	$nodes = get_current_nodeid();

		if ( empty($nodes) )	$nodes = 0;

		if ( is_numeric($nodes) )
		{
			$nodes = array($nodes);
		}
		else if ( is_string($nodes) )
		{
			if ( !eregi('([0-9\,]+)', $nodes ) )
				fatal_error('Incorrect "nodes" for "in_node". Passed ['.$nodes.']');

			$nodes = explode(',', $nodes);
		}
		else if ( !is_array($nodes) )
		{
			fatal_error('Incorrect type of "nodes" for "in_node". Passed ['.gettype($nodes).']');
		}

		return in_array(id2nodeid($id_var), $nodes);
	}

	function	get_dbid($table,$field)
	{
		$nodeid = get_current_nodeid(false);

		$found = false;
		do
		{
			global $ZBX_LOCALNODEID;

			$min=bcadd(bcmul($nodeid,"100000000000000"),bcmul($ZBX_LOCALNODEID,"100000000000"));
			$max=bcadd(bcadd(bcmul($nodeid,"100000000000000"),bcmul($ZBX_LOCALNODEID,"100000000000")),"99999999999");
			$row = DBfetch(DBselect("select nextid from ids where nodeid=$nodeid and table_name='$table' and field_name='$field'"));
			if(!$row)
			{
				$row=DBfetch(DBselect("select max($field) as id from $table where $field>=$min and $field<=$max"));
				if(!$row || is_null($row["id"]))
				{
					DBexecute("insert into ids (nodeid,table_name,field_name,nextid) ".
						" values ($nodeid,'$table','$field',$min)");
				}
				else
				{
					/*
					$ret1 = $row["id"];
					if($ret1 >= $max) {
						"Maximum number of id's was exceeded"
					}
					*/

					DBexecute("insert into ids (nodeid,table_name,field_name,nextid) values ($nodeid,'$table','$field',".$row["id"].")");
				}
				continue;
			}
			else
			{
				$ret1 = $row["nextid"];
				if(($ret1 < $min) || ($ret1 >= $max)) {
					DBexecute("delete from ids where nodeid=$nodeid and table_name='$table' and field_name='$field'");
					continue;
				}
	
				DBexecute("update ids set nextid=nextid+1 where nodeid=$nodeid and table_name='$table' and field_name='$field'");
	
				$row = DBfetch(DBselect("select nextid from ids where nodeid=$nodeid and table_name='$table' and field_name='$field'"));
				if(!$row || is_null($row["nextid"]))
				{
					/* Should never be here */
					continue;
				}
				else
				{
					$ret2 = $row["nextid"];
					if(bccomp(bcadd($ret1,1),$ret2) ==0)
					{
						$found = true;
					}
				}
			}
		}
		while(false == $found);

		return $ret2;
	}
?>
