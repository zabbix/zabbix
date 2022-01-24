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

require_once dirname(__FILE__).'/../../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../../include/func.inc.php';
require_once dirname(__FILE__).'/../../../include/classes/api/CApiService.php';
require_once dirname(__FILE__).'/../../../include/db.inc.php';
require_once dirname(__FILE__).'/../../../include/classes/db/DB.php';
require_once dirname(__FILE__).'/../../../include/classes/user/CWebUser.php';
require_once dirname(__FILE__).'/../../../include/classes/debug/CProfiler.php';
require_once dirname(__FILE__).'/../../../include/classes/db/DbBackend.php';
require_once dirname(__FILE__).'/../../../include/classes/db/MysqlDbBackend.php';
require_once dirname(__FILE__).'/../../../include/classes/db/PostgresqlDbBackend.php';
require_once dirname(__FILE__).'/CTestArrayHelper.php';

/**
 * Database helper.
 */
class CDBHelper {

	/**
	 * Backup stack.
	 *
	 * @var array
	 */
	static $backups = [];

	/**
	 * Perform select query and check the result.
	 *
	 * @param string  $sql       query to be executed
	 * @param integer $limit     data limit
	 * @param integer $offset    data offset
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	protected static function select($sql, $limit = null, $offset = 0) {
		if (($result = DBselect($sql, $limit, $offset)) === false) {
			throw new Exception('Failed to execute query: "'.$sql.'".');
		}

		return $result;
	}

	/**
	 * Get database data suitable for PHPUnit data provider functions.
	 *
	 * @param string $sql    query to be executed
	 *
	 * @return array
	 */
	public static function getDataProvider($sql) {
		DBconnect($error);

		$data = [];
		$result = static::select($sql);
		while ($row = DBfetch($result)) {
			$data[] = [$row];
		}

		DBclose();
		return $data;
	}

	/**
	 * Get database data.
	 *
	 * @param string  $sql       query to be executed
	 * @param integer $limit     data limit
	 * @param integer $offset    data offset
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function getAll($sql, $limit = null, $offset = 0) {
		return DBfetchArray(static::select($sql, $limit, $offset));
	}

	/**
	 * Get random database data set (limited set of random records).
	 *
	 * @param string  $sql       query to be executed
	 * @param integer $count     data set size (or null for all data set)
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function getRandom($sql, $count = null) {
		$data = self::getAll($sql);
		shuffle($data);

		if ($count !== null) {
			$data = array_slice($data, 0, $count);
		}

		return $data;
	}

	/**
	 * Get random database data suitable for PHPUnit data provider functions (limited set of random records).
	 *
	 * @param string  $sql       query to be executed
	 * @param integer $count     data set size (or null for all data set)
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	public static function getRandomizedDataProvider($sql, $count = null) {
		DBconnect($error);

		$data = [];
		foreach (CDBHelper::getRandom($sql, $count) as $row) {
			$data[] = [$row];
		}

		DBclose();
		return $data;
	}

	/**
	 * Get database data row.
	 *
	 * @param string  $sql       query to be executed
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public static function getRow($sql) {
		return DBfetch(static::select($sql, 1));
	}

	/**
	 * Get single value from database.
	 *
	 * @param string  $sql       query to be executed
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public static function getValue($sql) {
		$row = static::getRow($sql);

		if ($row === false) {
			throw new Exception('Failed to retrieve data row from query: "'.$sql.'".');
		}

		return reset($row);
	}

	/**
	 * Get all values of database column.
	 *
	 * @param type $sql			 query to be executed
	 * @param type $column		 column name
	 *
	 * @return array
	 */
	public static function getColumn($sql, $column) {
		$data = [];

		foreach (CDBHelper::getAll($sql) as $row) {
			$data[] = $row[$column];
		}

		return $data;
	}

	/**
	 * Get list of all referenced tables sorted by dependency level.
	 *
	 * For example: getTables($tables, 'users')
	 * Result: [users,alerts,acknowledges,auditlog,auditlog_details,opmessage_usr,media,profiles,sessions,...]
	 */
	public static function getTables(&$tables, $top_table) {
		if (is_array($top_table)) {
			foreach ($top_table as $table) {
				self::getTables($tables, $table);
			}

			return;
		}

		if (in_array($top_table, $tables)) {
			return;
		}

		if (substr($top_table, 0, 1) === '!') {
			$tables[] = substr($top_table, 1);
			return;
		}

		$schema = DB::getSchema();

		foreach ($schema[$top_table]['fields'] as $field => $field_data) {
			if (!array_key_exists('ref_table', $field_data)) {
				continue;
			}

			$ref_table = $field_data['ref_table'];
			if ($ref_table != $top_table) {
				static::getTables($tables, $ref_table);
			}
		}

		if (!in_array($top_table, $tables)) {
			$tables[] = $top_table;
		}

		foreach (array_keys($schema) as $table) {
			foreach ($schema[$table]['fields'] as $field => $field_data) {
				if (!array_key_exists('ref_table', $field_data)) {
					continue;
				}

				$ref_table = $field_data['ref_table'];
				if ($ref_table == $top_table && $top_table !== $table) {
					static::getTables($tables, $table);
				}
			}
		}
	}

	/*
	 * Saves data of the specified table and all dependent tables in temporary storage.
	 * For example: backupTables(['users'])
	 */
	public static function backupTables(array $top_tables) {
		global $DB;

		$tables = [];
		static::getTables($tables, $top_tables);
		self::$backups[] = $tables;

		$suffix = '_tmp'.count(self::$backups);

		if ($DB['TYPE'] === ZBX_DB_POSTGRESQL) {
			if ($DB['PASSWORD'] !== '') {
				putenv('PGPASSWORD='.$DB['PASSWORD']);
			}
			$server = $DB['SERVER'] !== '' ? ' -h'.$DB['SERVER'] : '';
			$db_name = $DB['DATABASE'];
			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump';

			exec('pg_dump'.$server.' -U'.$DB['USER'].' -Fd -j5 -t'.implode(' -t', $tables).' '.$db_name.' -f'.$file,
				$output, $result_code
			);

			if ($result_code != 0) {
				throw new Exception('Failed to backup "'.implode('", "', $top_tables).'".');
			}
		}
		else {
			foreach ($tables as $table) {
				DBexecute('DROP TABLE IF EXISTS '.$table.$suffix);
				DBexecute('CREATE TABLE '.$table.$suffix.' AS SELECT * FROM '.$table);
			}
		}
	}

	/**
	 * Restores data from temporary storage. backupTables() must be called first.
	 * For example: restoreTables()
	 */
	public static function restoreTables() {
		global $DB;

		if (!self::$backups) {
			return;
		}

		$suffix = '_tmp'.count(self::$backups);
		$tables = array_pop(self::$backups);

		if ($DB['TYPE'] === ZBX_DB_POSTGRESQL) {
			if ($DB['PASSWORD'] !== '') {
				putenv('PGPASSWORD='.$DB['PASSWORD']);
			}
			$server = $DB['SERVER'] !== '' ? ' -h'.$DB['SERVER'] : '';
			$db_name = $DB['DATABASE'];
			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump';

			exec('pg_restore'.$server.' -U'.$DB['USER'].' -Fd -j5 --clean -d '.$db_name.' '.$file, $output,
				$result_code
			);

			if ($result_code != 0) {
				throw new Exception('Failed to restore "'.$file.'".');
			}

			exec('rm -rf '.$file);

			if ($result_code != 0) {
				throw new Exception('Failed to remove "'.$file.'".');
			}
		}
		else {
			$result = DBselect('SELECT @@unique_checks,@@foreign_key_checks');
			$row = DBfetch($result);
			DBexecute('SET unique_checks=0,foreign_key_checks=0');

			foreach (array_reverse($tables) as $table) {
				DBexecute('DELETE FROM '.$table);
			}

			foreach ($tables as $table) {
				DBexecute('INSERT INTO '.$table.' SELECT * FROM '.$table.$suffix);
				DBexecute('DROP TABLE '.$table.$suffix);
			}

			DBexecute('SET foreign_key_checks='.$row['@@foreign_key_checks'].',unique_checks='.$row['@@unique_checks']);
		}
	}

	/**
	 * Get md5 hash sum of database result.
	 *
	 * @param string  $sql       query to be executed
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	public static function getHash($sql) {
		$hash = '<empty hash>';
		$result = static::select($sql);

		while ($row = DBfetch($result)) {
			$hash = md5($hash.json_encode($row));
		}

		return $hash;
	}

	/**
	 * Get number of records in database result.
	 *
	 * @param string  $sql       query to be executed
	 * @param integer $limit     data limit
	 * @param integer $offset    data offset
	 *
	 * @return integer
	 *
	 * @throws Exception
	 */
	public static function getCount($sql, $limit = null, $offset = 0) {
		$result = static::select($sql, $limit, $offset);
		$count = 0;
		while (DBfetch($result)) {
			$count++;
		}

		return $count;
	}

	/**
	 * Returns comma-delimited list of the fields.
	 *
	 * @param string $table_name
	 * @param array  $exlude_fields
	 */
	public static function getTableFields($table_name, array $exlude_fields = []) {
		$field_names = [];

		foreach (DB::getSchema($table_name)['fields'] as $field_name => $field) {
			if (!in_array($field_name, $exlude_fields, true)) {
				$field_names[] = $field_name;
			}
		}

		return implode(', ', $field_names);
	}

	/**
	 * Escapes value to be used in SQL query.
	 *
	 * @param mixed $value    value to be escaped
	 *
	 * @return string
	 */
	public static function escape($value) {
		if (!is_array($value)) {
			return zbx_dbstr($value);
		}

		$result = [];
		foreach ($value as $part) {
			$result[] = zbx_dbstr($part);
		}

		return implode(',', $result);
	}

	/**
	 * Add host groups to user group with these rights.
	 *
	 * @param string $usergroup_name
	 * @param string $hostgroup_name
	 * @param int $permission
	 * @param bool $subgroups
	 */
	public static function setHostGroupPermissions($usergroup_name, $hostgroup_name, $permission, $subgroups = false) {
		$usergroup = DB::find('usrgrp', ['name' => $usergroup_name]);
		$hostgroups = DB::find('hstgrp', ['name' => $hostgroup_name]);

		if ($usergroup && $hostgroups) {
			$usergroup = $usergroup[0];

			if ($subgroups) {
				$hostgroups = array_merge($hostgroups, DBfetchArray(DBselect(
					'SELECT * FROM hstgrp WHERE name LIKE '.zbx_dbstr($hostgroups[0]['name'].'/%')
				)));
			}

			$rights_old = DB::find('rights', [
				'groupid' => $usergroup['usrgrpid'],
				'id' => array_column($hostgroups, 'groupid')
			]);

			$rights_new = [];
			foreach ($hostgroups as $hostgroup) {
				$rights_new[] = [
					'groupid' => $usergroup['usrgrpid'],
					'permission' => $permission,
					'id' => $hostgroup['groupid']
				];
			}
			DB::replace('rights', $rights_old, $rights_new);
		}
	}

	/**
	 * Create problem or resolved events of trigger.
	 *
	 * @param string $trigger_name
	 * @param int $value TRIGGER_VALUE_FALSE
	 * @param array $event_fields
	 */
	public static function setTriggerProblem($trigger_name, $value = TRIGGER_VALUE_TRUE, $event_fields = []) {
		$trigger = DB::find('triggers', ['description' => $trigger_name]);

		if ($trigger) {
			$trigger = $trigger[0];

			$tags = DB::select('trigger_tag', [
				'output' => ['tag', 'value'],
				'filter' => ['triggerid' => $trigger['triggerid']],
				'preservekeys' => true
			]);

			$fields = [
				'source' => EVENT_SOURCE_TRIGGERS,
				'object' => EVENT_OBJECT_TRIGGER,
				'objectid' => $trigger['triggerid'],
				'value' => $value,
				'name' => $trigger['description'],
				'severity' => $trigger['priority'],
				'clock' => CTestArrayHelper::get($event_fields, 'clock', time()),
				'ns' => CTestArrayHelper::get($event_fields, 'ns', 0),
				'acknowledged' => CTestArrayHelper::get($event_fields, 'acknowledged', EVENT_NOT_ACKNOWLEDGED)
			];

			$eventid = DB::insert('events', [$fields]);

			if ($eventid) {
				$fields['eventid'] = $eventid[0];

				if ($value == TRIGGER_VALUE_TRUE) {
					DB::insert('problem', [$fields], false);
					DB::update('triggers', [
						'values' => [
							'value' => TRIGGER_VALUE_TRUE,
							'lastchange' => CTestArrayHelper::get($event_fields, 'clock', time())
						],
						'where' => ['triggerid' => $trigger['triggerid']]
					]);
				}
				else {
					$problems = DBfetchArray(DBselect(
						'SELECT *'.
						' FROM problem'.
						' WHERE objectid = '.$trigger['triggerid'].
							' AND r_eventid IS NULL'
					));

					if ($problems) {
						DB::update('triggers', [
							'values' => [
								'value' => TRIGGER_VALUE_FALSE,
								'lastchange' => CTestArrayHelper::get($event_fields, 'clock', time())
							],
							'where' => ['triggerid' => $trigger['triggerid']]
						]);
						DB::update('problem', [
							'values' => [
								'r_eventid' => $fields['eventid'],
								'r_clock' => $fields['clock'],
								'r_ns' => $fields['ns']
							],
							'where' => ['eventid' => array_column($problems, 'eventid')]
						]);

						$recovery = [];
						foreach ($problems as $problem) {
							$recovery[] = [
								'eventid' => $problem['eventid'],
								'r_eventid' => $fields['eventid']
							];
						}
						DB::insert('event_recovery', $recovery, false);
					}
				}

				if ($tags) {
					foreach ($tags as &$tag) {
						$tag['eventid'] = $fields['eventid'];
					}
					unset($tag);

					DB::insertBatch('event_tag', $tags);

					if ($value == TRIGGER_VALUE_TRUE) {
						DB::insertBatch('problem_tag', $tags);
					}
				}
			}
		}
	}
}
