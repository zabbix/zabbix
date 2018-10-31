<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../include/func.inc.php';
require_once dirname(__FILE__).'/../../include/db.inc.php';
require_once dirname(__FILE__).'/../../include/classes/db/DB.php';
require_once dirname(__FILE__).'/../../include/classes/user/CWebUser.php';
require_once dirname(__FILE__).'/../../include/classes/debug/CProfiler.php';
require_once dirname(__FILE__).'/../../include/classes/db/DbBackend.php';
require_once dirname(__FILE__).'/../../include/classes/db/MysqlDbBackend.php';
require_once dirname(__FILE__).'/../../include/classes/db/PostgresqlDbBackend.php';


/**
 * Returns database data suitable for PHPUnit data provider functions
 */
function DBdata($sql, $make_connection = true) {
	if ($make_connection) {
		DBconnect($error);
	}

	$data = [];

	$result = DBselect($sql);
	while ($row = DBfetch($result)) {
		$data[] = [$row];
	}

	if ($make_connection) {
		DBclose();
	}

	return $data;
}

/**
 * The function returns list of all referenced tables sorted by dependency level
 * For example: DBget_tables('users')
 * Result: array(users,alerts,acknowledges,auditlog,auditlog_details,opmessage_usr,media,profiles,sessions,users_groups)
 */
function DBget_tables(&$tables, $topTable) {
	if (in_array($topTable, $tables))
		return;

	$schema = include(dirname(__FILE__).'/../../include/schema.inc.php');

	$tableData = $schema[$topTable];

	$fields = $tableData['fields'];
	foreach ($fields as $field => $fieldData) {
		if (isset($fieldData['ref_table'])) {
			$refTable = $fieldData['ref_table'];
			if ($refTable != $topTable)
				DBget_tables($tables, $refTable);
		}
	}

	if (!in_array($topTable, $tables))
		$tables[] = $topTable;

	foreach ($schema as $table => $tableData) {
		$fields = $schema[$table]['fields'];
		$referenced = false;
		foreach ($fields as $field => $fieldData) {
			if (isset($fieldData['ref_table'])) {
				$refTable = $fieldData['ref_table'];
				if ($refTable == $topTable && $topTable != $table) {
					DBget_tables($tables, $table);
				}
			}
		}
	}
}

/*
 * Saves data of the specified table and all dependent tables in temporary storage.
 * For example: DBsave_tables('users')
 */
function DBsave_tables($topTable) {
	global $DB;

	$tables = [];

	DBget_tables($tables, $topTable);

	foreach ($tables as $table) {
		switch ($DB['TYPE']) {
		case ZBX_DB_MYSQL:
			DBexecute("drop table if exists ${table}_tmp");
			DBexecute("create table ${table}_tmp like $table");
			DBexecute("insert into ${table}_tmp select * from $table");
			break;
		default:
			DBexecute("drop table if exists ${table}_tmp");
			DBexecute("select * into table ${table}_tmp from $table");
		}
	}
}

/**
 * Restores data from temporary storage. DBsave_tables() must be called first.
 * For example: DBrestore_tables('users')
 */
function DBrestore_tables($topTable) {
	global $DB;

	$tables = [];

	if ($DB['TYPE'] == ZBX_DB_MYSQL) {
		$result = DBselect('select @@unique_checks,@@foreign_key_checks');
		$row = DBfetch($result);
		DBexecute('set unique_checks=0');
		DBexecute('set foreign_key_checks=0');
	}

	DBget_tables($tables, $topTable);

	$tablesReversed = array_reverse($tables);

	foreach ($tablesReversed as $table) {
		DBexecute("delete from $table");
	}

	foreach ($tables as $table) {
		DBexecute("insert into $table select * from ${table}_tmp");
		DBexecute("drop table ${table}_tmp");
	}

	if ($DB['TYPE'] == ZBX_DB_MYSQL) {
		DBexecute('set foreign_key_checks='.$row['@@foreign_key_checks']);
		DBexecute('set unique_checks='.$row['@@unique_checks']);
	}
}

/**
 * Returns md5 hash sum of database result.
 */
function DBhash($sql) {
	$hash = '<empty hash>';

	$result = DBselect($sql);
	while ($row = DBfetch($result)) {
		foreach ($row as $value) {
			$hash = md5($hash.$value);
		}
	}

	return $hash;
}

/**
 * Returns number of records in database result.
 */
function DBcount($sql, $limit = null, $offset = null) {
	$cnt = 0;

	if (isset($limit) && isset($offset)) {
		$result = DBselect($sql, $limit, $offset);
	}
	elseif (isset($limit)) {
		$result = DBselect($sql, $limit);
	}
	else {
		$result = DBselect($sql);
	}

	if ($result === false) {
		return -1;
	}

	while (DBfetch($result)) {
		$cnt++;
	}

	return $cnt;
}
