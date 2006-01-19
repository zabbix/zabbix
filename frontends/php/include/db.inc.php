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

// DATABASE CONFIGURATION

//	$DB_TYPE	="POSTGRESQL";
	$DB_TYPE	="MYSQL";
	$DB_SERVER	="localhost";
	$DB_DATABASE	="zabbix";
	$DB_USER	="root";
	$DB_PASSWORD	="";
// END OF DATABASE CONFIGURATION

//	$USER_DETAILS	="";
	global $USER_DETAILS;

	if($DB_TYPE == "MYSQL")
	{
		$DB=mysql_pconnect($DB_SERVER,$DB_USER,$DB_PASSWORD);
		if(!mysql_select_db($DB_DATABASE))
		{
			echo "Error connecting to database [".mysql_error()."]";
			exit;
		}
		mysql_select_db($DB_DATABASE);
	}
	if($DB_TYPE == "POSTGRESQL")
	{
		$DB=pg_pconnect("host='$DB_SERVER' dbname='$DB_DATABASE' user='$DB_USER' password='$DB_PASSWORD'");
		if(!$DB)
		{
			echo "Error connecting to database";
			exit;
		}
	}

	function	DBselect($query)
	{
		global $DB,$DB_TYPE;

#		echo $query,"<br>";

		if($DB_TYPE == "MYSQL")
		{
			$result=mysql_query($query,$DB);
			if(!$result)
			{
				echo "Error in query [$query] [".mysql_error()."]";
			}
			return $result;
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			$result=pg_exec($DB,$query);
			return $result;
		}
	}

	function	DBexecute($query, $skip_error_messages=0)
	{
		global $DB,$DB_TYPE;

#		echo $query."<br>";

		if($DB_TYPE == "MYSQL")
		{
			$result=mysql_query($query,$DB);

			if(!$result && $skip_error_messages==0)
			{
				error("SQL error: ".mysql_error());
				error("Query: $query");
			}
			return $result;
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			$result=pg_exec($DB,$query);
			return $result;
		}
		return FALSE;
	}

	function	DBfetch($result)
	{
		global $DB_TYPE;

		if($DB_TYPE == "MYSQL")
		{
			$row=mysql_fetch_array($result);
			return $row;
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			$row=pg_fetch_array($result);
			return $row;
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
				echo "Error getting row";
				exit;
			}
			return $row[$fieldnum];
		}
	}

	function	DBnum_rows($result)
	{
		global $DB_TYPE;
	
		if($DB_TYPE == "MYSQL")
		{
			return mysql_num_rows($result);
		}
		if($DB_TYPE == "POSTGRESQL")
		{
			return pg_numrows($result);
		}
		return 0;
	}

	function	DBinsert_id($result,$table,$field)
	{
		global	$DB,$DB_TYPE;

		if($DB_TYPE == "MYSQL")
		{
			return mysql_insert_id($DB);
		}

		if($DB_TYPE == "POSTGRESQL")
		{
			$oid=pg_getlastoid($result);
//			echo "OID:$oid<br>";
			$sql="select $field from $table where oid=$oid";
			$result=DBselect($sql);
			return get_field($result,0,0);
		}
	}

?>
