<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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

require_once(dirname(__FILE__).'/../../include/defines.inc.php');
require_once(dirname(__FILE__).'/../../conf/zabbix.conf.php');
require_once(dirname(__FILE__).'/../../include/copt.lib.php');
require_once(dirname(__FILE__).'/../../include/func.inc.php');
require_once(dirname(__FILE__).'/../../include/db.inc.php');

function error($error)
{
	return true;
}

/**
 * Returns database data suitable for PHPUnit data provider functions
 */
function DBdata($query)
{
	DBconnect($error);

	$objects=array();

	$result=DBselect($query);
	while($object=DBfetch($result))
	{
		$objects[]=array($object);
	}

	DBclose();
	return $objects;
}

/**
 * Saves data of the specified tables in temporary tables.
 */
function DBsave_tables($tables)
{
	global $DB;

	if(!is_array($tables))	$tables=array($tables);

	foreach($tables as $table)
	{
		switch($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			DBexecute("drop table if exists ${table}_tmp");
			DBexecute("create table ${table}_tmp like $table");
			DBexecute("insert into ${table}_tmp select * from $table");
			break;
		case ZBX_DB_SQLITE3:
			DBexecute("drop table if exists ${table}_tmp");
			DBexecute("create table if not exists ${table}_tmp as select * from ${table}");
			break;
		default:
			DBexecute("drop table if exists ${table}_tmp");
			DBexecute("select * into temp table ${table}_tmp from $table");
		}
	}
}

/**
 * Restores data from temporary tables. DBsave_tables() must be called first.
 */
function DBrestore_tables($tables)
{
	global $DB;

	if(!is_array($tables))	$tables=array($tables);

	foreach($tables as $table)
	{
		DBexecute("delete from $table");
	}

	foreach($tables as $table)
	{
		DBexecute("insert into $table select * from ${table}_tmp");
		DBexecute("drop table ${table}_tmp");
	}
}

/**
 * Returns md5 hash sum of database result.
 */
function DBhash($sql)
{
	global $DB;

	$hash = '<empty hash>';

	$result=DBselect($sql);
	while($row = DBfetch($result))
	{
		foreach($row as $key => $value)
		{
			$hash = md5($hash.$value);
		}
	}

	return $hash;
}

/**
 * Returns number of records in database result.
 */
function DBcount($sql,$limit = null,$offset = null)
{
	global $DB;
	$cnt=0;

	if(isset($limit) && isset($offset))
	{
		$result=DBselect($sql,$limit,$offset);
	}
	else if(isset($limit))
	{
		$result=DBselect($sql,$limit);
	}
	else
	{
		$result=DBselect($sql);
	}
	while($row = DBfetch($result))
	{
		$cnt++;
	}

	return $cnt;
}

?>
