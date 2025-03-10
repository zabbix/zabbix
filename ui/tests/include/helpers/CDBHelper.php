<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once __DIR__.'/../../../include/gettextwrapper.inc.php';
require_once __DIR__.'/../../../include/defines.inc.php';
require_once __DIR__.'/../../../conf/zabbix.conf.php';
require_once __DIR__.'/../../../include/func.inc.php';
require_once __DIR__.'/../../../include/classes/api/CApiService.php';
require_once __DIR__.'/../../../include/db.inc.php';
require_once __DIR__.'/../../../include/classes/db/DB.php';
require_once __DIR__.'/../../../include/classes/db/DBException.php';
require_once __DIR__.'/../../../include/classes/user/CWebUser.php';
require_once __DIR__.'/../../../include/classes/debug/CProfiler.php';
require_once __DIR__.'/../../../include/classes/db/DbBackend.php';
require_once __DIR__.'/../../../include/classes/db/MysqlDbBackend.php';
require_once __DIR__.'/../../../include/classes/db/PostgresqlDbBackend.php';
require_once __DIR__.'/CTestArrayHelper.php';

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

	static $db_extension;

	const STATE_DEFAULT = 0;
	const STATE_BROKEN = 1;

	/**
	 * DB state.
	 *
	 * @var integer
	 */
	static $state = self::STATE_DEFAULT;

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
			if (self::$db_extension == null) {
				$res = DBfetch(DBselect('SELECT db_extension FROM config'));

				if ($res) {
					self::$db_extension = $res['db_extension'];
				}
			}

			if ($DB['PASSWORD'] !== '') {
				putenv('PGPASSWORD='.$DB['PASSWORD']);
			}

			$cmd = 'pg_dump';

			if ($DB['SERVER'] !== 'v') {
				$cmd .= ' --host='.$DB['SERVER'];
			}

			if ($DB['PORT'] !== '' && $DB['PORT'] != 0) {
				$cmd .= ' --port='.$DB['PORT'];
			}

			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump';
			$cmd .= ' --username='.$DB['USER'].' --format=d --jobs=9 --dbname='.$DB['DATABASE'];
			$cmd .= ' --table='.implode(' --table=', $tables).' --file='.$file;

			if (self::$db_extension  == ZBX_DB_EXTENSION_TIMESCALEDB) {
				$cmd .= ' 2>/dev/null';
			}

			static::removeDumpFile($file);

			exec($cmd, $output, $result_code);

			if ($result_code != 0) {
				throw new Exception('Failed to backup "'.implode('", "', $top_tables).'".');
			}
		}
		else {
			if ($DB['PASSWORD'] !== '') {
				putenv('MYSQL_PWD='.$DB['PASSWORD']);
			}

			$cmd = 'mysqldump';

			if ($DB['SERVER'] !== 'v') {
				$cmd .= ' --host='.$DB['SERVER'];
			}

			if ($DB['PORT'] !== '' && $DB['PORT'] != 0) {
				$cmd .= ' --port='.$DB['PORT'];
			}

			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump.gz';
			$cmd .= ' --user='.$DB['USER'].' --add-drop-table '.$DB['DATABASE'];
			$cmd .= ' '.implode(' ', $tables).' | gzip -c > '.$file;

			static::removeDumpFile($file);

			exec($cmd, $output, $result_code);

			if ($result_code != 0) {
				throw new Exception('Failed to backup "'.implode('", "', $top_tables).'".');
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

			$cmd = 'pg_restore';

			if ($DB['SERVER'] !== 'v') {
				$server = ' --host='.$DB['SERVER'];
			}
			$cmd .= $server;

			$port = '';
			if ($DB['PORT'] !== '' && $DB['PORT'] != 0) {
				$port .= ' --port='.$DB['PORT'];
			}
			$cmd .= $port;

			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump';
			$cmd .= ' --username='.$DB['USER'].' --format=d --jobs=1 --clean --dbname='.$DB['DATABASE'];
			$cmd .= ' '.$file;

			if (self::$db_extension  == ZBX_DB_EXTENSION_TIMESCALEDB) {
				$cmd_tdb = 'psql --username='.$DB['USER'].$server.$port.' --dbname='.$DB['DATABASE'].' --command="SELECT timescaledb_pre_restore();"; ';
				$cmd_tdb .= $cmd .' 2>/dev/null; ';
				$cmd_tdb .= 'psql --username='.$DB['USER'].$server.$port.' --dbname='.$DB['DATABASE'].' --command="SELECT timescaledb_post_restore();" ';
				exec($cmd_tdb, $output, $result_code);
			}
			else {
				exec($cmd, $output, $result_code);
			}

			if ($result_code != 0) {
				self::$state = self::STATE_BROKEN;
				throw new Exception('Failed to restore "'.$file.'".');
			}

			static::removeDumpFile($file);
		}
		else {
			if ($DB['PASSWORD'] !== '') {
				putenv('MYSQL_PWD='.$DB['PASSWORD']);
			}

			$cmd = 'mysql';

			if ($DB['SERVER'] !== 'v') {
				$cmd .= ' --host='.$DB['SERVER'];
			}

			if ($DB['PORT'] !== '' && $DB['PORT'] != 0) {
				$cmd .= ' --port='.$DB['PORT'];
			}

			$file = PHPUNIT_COMPONENT_DIR.$DB['DATABASE'].$suffix.'.dump.gz';
			$cmd .= ' --user='.$DB['USER'].' '.$DB['DATABASE'];
			$cmd = 'gzip -cd '.$file.' | '.$cmd;

			exec($cmd, $output, $result_code);

			if ($result_code != 0) {
				self::$state = self::STATE_BROKEN;
				throw new Exception('Failed to restore "'.$file.'".');
			}

			static::removeDumpFile($file);
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
	 * @param array  $exclude_fields
	 */
	public static function getTableFields($table_name, array $exclude_fields = []) {
		$field_names = [];

		foreach (DB::getSchema($table_name)['fields'] as $field_name => $field) {
			if (!in_array($field_name, $exclude_fields, true)) {
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
	 * @param string  $usergroup_name    user group name
	 * @param string  $hostgroup_name    host group name
	 * @param integer $permission        PERM_READ_WRITE / PERM_READ / PERM_DENY / PERM_NONE
	 * @param boolean $subgroups         include host subgroups (true) or not (false)
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
	 * @param array|string  $trigger_name    trigger name
	 * @param integer       $value           TRIGGER_VALUE_FALSE for RESOLVED problem / TRIGGER_VALUE_TRUE for PROBLEM
	 * @param array         $event_fields    values for events table
	 */
	public static function setTriggerProblem($triggers_names, $value = TRIGGER_VALUE_TRUE, $event_fields = []) {
		if (!is_array($triggers_names)) {
			$triggers_names = [$triggers_names];
		}

		$eventids = [];
		foreach ($triggers_names as $trigger_name) {
			$trigger = DB::find('triggers', ['description' => $trigger_name]);

			if ($trigger) {
				$trigger = $trigger[0];

				$tags = DB::select('trigger_tag', [
					'output' => ['tag', 'value'],
					'filter' => ['triggerid' => $trigger['triggerid']],
					'preservekeys' => true
				]);

				$time = time();

				$fields = [
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectid' => $trigger['triggerid'],
					'value' => $value,
					'name' => $trigger['description'],
					'severity' => $trigger['priority'],
					'clock' => CTestArrayHelper::get($event_fields, 'clock', $time),
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
								'lastchange' => CTestArrayHelper::get($event_fields, 'clock', $time)
							],
							'where' => ['triggerid' => $trigger['triggerid']]
						]);
					} else {
						$problems = DBfetchArray(DBselect(
							'SELECT *' .
							' FROM problem' .
							' WHERE objectid = ' . $trigger['triggerid'] .
							' AND r_eventid IS NULL'
						));

						if ($problems) {
							DB::update('triggers', [
								'values' => [
									'value' => TRIGGER_VALUE_FALSE,
									'lastchange' => CTestArrayHelper::get($event_fields, 'clock', $time)
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

					$eventids[] = $fields['eventid'];
				}
			}
		}

		return $eventids;
	}

	/**
	 * Get state of the DB.
	 *
	 * @return boolean
	 */
	public static function isValid() {
		return (self::$state === self::STATE_DEFAULT);
	}

	/**
	 * Remove dump file.
	 *
	 * @param string $file     path of the dump file
	 *
	 * @throws Exception on failure to remove the file
	 */
	public static function removeDumpFile($file) {
		if (strstr(strtolower(PHP_OS), 'win') !== false) {
			$file = str_replace('/', '\\', $file);
			exec('del '.$file);
		}
		else {
			exec('rm -rf '.$file, $output, $result_code);
		}

		if ($result_code != 0) {
			throw new Exception('Failed to remove "'.$file.'".');
		}
	}
}
