<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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


/**
 * Abstract database backend class.
 */
abstract class DbBackend {

	protected $error;

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	abstract protected function checkDbVersionTable();

	/**
	 * Check if connected database version matches with frontend version.
	 *
	 * @return bool
	 */
	public function checkDbVersion() {
		if (!$this->checkDbVersionTable()) {
			return false;
		}

		$version = DBfetch(DBselect('SELECT dv.mandatory,dv.optional FROM dbversion dv'));
		if ($version['mandatory'] != ZABBIX_DB_VERSION) {
			$this->setError(_s('The frontend does not match Zabbix database. Current database version (mandatory/optional): %d/%d. Required mandatory version: %d. Contact your system administrator.',
				$version['mandatory'], $version['optional'], ZABBIX_DB_VERSION));
			return false;
		}

		return true;
	}

	/**
	 * Insert batch data into DB
	 *
	 * @param string $table
	 * @param array  $values pair of fieldname => fieldvalue
	 * @param bool   $getids
	 *
	 * @return array    an array of ids with the keys preserved
	 */
	public function insertBatch($table, $values, $getids = true) {
		if (empty($values)) {
			return true;
		}
		$resultIds = array();

		$tableSchema = DB::getSchema($table);
		$values = DB::addMissingFields($tableSchema, $values);
		$fields = array_keys($values[0]);

		if ($getids) {
			$id = DB::reserveIds($table, count($values));
			$fields[] = $tableSchema['key'];
		}

		foreach ($values as $key => $row) {
			if ($getids) {
				$resultIds[$key] = $id;
				$row[$tableSchema['key']] = $id;
				$values[$key][$tableSchema['key']] = $id;
				$id = bcadd($id, 1, 0);
			}

			DB::checkValueTypes($table, $row);
		}

		$sql = $this->insertGeneration($table, $fields, $values);

		var_dump($sql);
//		if (!DBexecute($sql)) {
//			self::exception(self::DBEXECUTE_ERROR, _s('SQL statement execution has failed "%1$s".', $sql));
//		}

		return $resultIds;
	}

	/**
	 * !!!
	 *
	 * @param !!!
	 *
	 * @return !!!
	 */
	public static function i() {
	global $DB;
		switch ($DB['TYPE']) {
			case ZBX_DB_MYSQL:
				$dbBackend = new MysqlDbBackend();
				break;
			case ZBX_DB_POSTGRESQL:
				$dbBackend = new PostgresqlDbBackend();
				break;
			case ZBX_DB_ORACLE:
				$dbBackend = new OracleDbBackend();
				break;
			case ZBX_DB_DB2:
				$dbBackend = new Db2DbBackend();
				break;
			case ZBX_DB_SQLITE3:
				$dbBackend = new SqliteDbBackend();
				break;
		}
		return $dbBackend;
	}

	/**
	 * !!!
	 *
	 * @return !!!
	 */
	public function insertGeneration($table, $fields, $values) {
		$sql = 'INSERT2 INTO '.$table.' ('.implode(',', $fields).') VALUES ';

		foreach ($values as $row) {
			$sql .= '('.implode(',', array_values($row)).'),';
		}

		$sql[strlen($sql) - 1] = ' ';

		return $sql;
	}

	/**
	 * Set error string.
	 *
	 * @param string $error
	 */
	public function setError($error) {
		$this->error = $error;
	}

	/**
	 * Return error or null if no error occured.
	 *
	 * @return mixed
	 */
	public function getError() {
		return $this->error;
	}
}
