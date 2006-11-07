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
	function	DBconnect(&$error)
	{
		$result = true;
		
		global $DB, $DB_TYPE, $DB_SERVER, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

		if(!isset($DB_TYPE))
		{
			$error = "Uncnown database type.";
			$result = false;
		}
		else
		{
			if($DB_TYPE == "MYSQL")
			{
				$DB = mysql_pconnect($DB_SERVER,$DB_USER,$DB_PASSWORD);
				if(!mysql_select_db($DB_DATABASE))
				{
					$error = "Error connecting to database [".mysql_error()."]";
					$result = false;
				}
				else
				{
					mysql_select_db($DB_DATABASE);
				}
			}
			if($DB_TYPE == "POSTGRESQL")
			{
				$DB=pg_pconnect("host='$DB_SERVER' dbname='$DB_DATABASE' user='$DB_USER' password='$DB_PASSWORD'");
				if(!$DB)
				{
					$error = "Error connecting to database";
					$result = false;
				}
			}

			if($DB_TYPE == "ORACLE")
			{
				$DB = ocilogon($DB_USER, $DB_PASSWORD, "");
		//		$DB = ocilogon($DB_USER, $DB_PASSWORD, "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$DB_SERVER)(PORT=1521))(CONNECT_DATA=(SERVICE_NAME=$DB_DATABASE)))");
				if(!$DB)
				{
					$error = "Error connecting to database";
					$result = false;
				}
			}
		}
		return $result;
	}

	function	DBclose()
	{
		global $DB, $DB_TYPE, $DB_SERVER, $DB_DATABASE, $DB_USER, $DB_PASSWORD;

		$result = false;

		if($DB)
		{
			if($DB_TYPE == "MYSQL")			$result = mysql_close($DB);
			elseif($DB_TYPE == "POSTGRESQL")	$result = pg_close($DB);
			elseif($DB_TYPE == "ORACLE")		$result = ociclose($DB);
		}
		unset(
			$GLOBALS['DB'],
			$GLOBALS['DB_TYPE'],
			$GLOBALS['DB_SERVER'],
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
		
		$result = true;

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

	function	DBselect($query, $limit='NO')
	{
		global $DB,$DB_TYPE;

COpt::savesqlrequest($query);

		if($DB_TYPE == "MYSQL")
		{
			if(is_numeric($limit))
			{
				$query .= ' limit '.intval($limit);
			}
			$result=mysql_query($query,$DB);
			if(!$result)
			{
				error("Error in query [$query] [".mysql_error()."]");
			}
			return $result;
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			if(is_numeric($limit))
			{
				$query .= ' limit '.intval($limit);
			}
			$result=pg_exec($DB,$query);
			return $result;
		}
		if($DB_TYPE == "ORACLE")
		{
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
			return $stid;
		}
	}

	function	DBexecute($query, $skip_error_messages=0)
	{
		global $DB,$DB_TYPE;

COpt::savesqlrequest($query);

		$result = FALSE;

		if($DB_TYPE == "MYSQL")
		{
			$result=mysql_query($query,$DB);

			if(!$result && $skip_error_messages==0)
			{
				error("SQL error: ".mysql_error());
				error("Query: $query");
			}
		}
		else if($DB_TYPE == "POSTGRESQL")
		{
			$result=pg_exec($DB,$query);
		}
		else if($DB_TYPE == "ORACLE")
		{

			return DBselect($query);
		}

//SDI("DBexecute($query) = '".$result."'");

		return $result;
	}

	function	DBfetch($cursor)
	{
		global $DB_TYPE;

		if($DB_TYPE == "MYSQL")
		{
			$row=mysql_fetch_array($cursor);
			return $row;
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			$row=pg_fetch_array($cursor);
			return $row;
		}
		if($DB_TYPE == "ORACLE")
		{
			if(!ocifetchinto($cursor, $row, OCI_ASSOC+OCI_NUM+OCI_RETURN_NULLS))
			{
				return FALSE;
			}
			else
			{
				$result=array();
				$keys = (array_keys($row));
           			foreach ($keys as $k)
				{
					$result[strtolower($k)]=$row[$k];
				}
			} 
			return $result;
		}
		return FALSE;
	}

	function	get_field($result,$rownum,$fieldnum)
	{
		global $DB_TYPE;

		if($DB_TYPE == "MYSQL")
		{
			mysql_data_seek($result,$rownum);
			$row=mysql_fetch_row($result);
			return $row[$fieldnum];
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			$row=pg_fetch_row($result,$rownum);
			if(!$row)
			{
				fatal_error("Error getting row");
			}
			return $row[$fieldnum];
		}
		if($DB_TYPE == "ORACLE")
		{
			$result=FALSE;
		}
	}

	function        DBinsert_id($result,$table,$field)
	{
		global  $DB,$DB_TYPE;

		if($DB_TYPE == "MYSQL")
		{
			return mysql_insert_id($DB);
		}
		
		if($DB_TYPE == "POSTGRESQL")
		{
			$oid=pg_getlastoid($result);
			$sql="select $field from $table where oid=$oid";
			$result=DBselect($sql);
			return get_field($result,0,0);
		}
		if($DB_TYPE == "ORACLE")
		{
/*                      $sql="select max($field) from $table";
			$parse=DBexecute($sql);
			while(OCIFetch($parse))
			{
				$colvalue = OCIResult($parse, 1);
				return $colvalue;
			}
*/
			$res = DBfetch(DBselect('select '.$table.'_'.$field.'.currval from dual'));
			return $res[0];
		}
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

	function DBid2nodeid($id_name)
	{
		return '('.$id_name.' div 100000000000000)';
	}

	function id2nodeid($id_var)
	{
		return (int)bcdiv("$id_var","100000000000000");
	}

	function	get_dbid($table,$field)
	{
		global	$ZBX_CURNODEID;

		$result=DBselect("select max($field) as id from $table where ".DBid2nodeid($field)." in (".$ZBX_CURNODEID.")");
		$row=DBfetch($result);
		if($row && !is_null($row["id"]))
		{
			return	bcadd($row["id"],1);
		}
		else
		{
			return bcadd(bcmul($ZBX_CURNODEID,"100000000000000"),1);
		}
	}
?>
