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


/**
 * Database backend class for Oracle.
 */
class OracleDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect("SELECT table_name FROM user_tables WHERE table_name='DBVERSION'"));

		if (!$table_exists) {
			$this->setError(_s('Unable to determine current Zabbix database version: %1$s.',
				_s('the table "%1$s" was not found', 'dbversion')
			));

			return false;
		}

		return true;
	}

	/**
	 * Check is current connection contain requested cipher list.
	 *
	 * @return bool
	 */
	public function isConnectionSecure() {
		$this->setError('Secure connection for Oracle is not supported.');
		return false;
	}

	/**
	 * Create connection to database server.
	 *
	 * @param string $host         Host name.
	 * @param string $port         Port.
	 * @param string $user         User name.
	 * @param string $password     Password.
	 * @param string $dbname       Database name.
	 * @param string $schema       DB schema.
	 *
	 * @param
	 * @return resource|null
	 */
	public function connect($host, $port, $user, $password, $dbname, $schema) {
		$connect = '';

		if ($host) {
			$connect = '//'.$host.(($port) ? ':'.$port : '').(($dbname) ? '/'.$dbname : '');
		}
		elseif ($dbname) {
			$connect = $dbname;
		}

		$resource = @oci_connect($user, $password, $connect, 'UTF8');

		if (!$resource) {
			$ociError = oci_error();
			$this->setError('Error connecting to database: '.$ociError['message']);
			return null;
		}

		return $resource;
	}

	/**
	 * Initialize connection.
	 *
	 * @return bool
	 */
	public function init() {
		DBexecute('ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.zbx_dbstr('. '));
	}

	/**
	 * Create INSERT SQL query.
	 * Creation example:
	 *	BEGIN
	 *	INSERT INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('20', 'admins', '1', '0', '1');
	 *	INSERT INTO usrgrp (usrgrpid, name, gui_access, users_status, debug_mode)
	 *		VALUES ('21', 'users', '0', '0', '0');
	 *  END;
	 */
	public function createInsertQuery($table, array $fields, array $values) {
		$sql = 'BEGIN';
		$fields = '('.implode(',', $fields).')';
		foreach ($values as $row) {
			$sql .= ' INSERT INTO '.$table.' '.$fields.' VALUES ('.implode(',', array_values($row)).');';
		}
		$sql .= ' END;';

		return $sql;
	}

	/**
	 * Check database and table fields encoding.
	 *
	 * @return bool
	 */
	public function checkEncoding() {
		return $this->checkDatabaseEncoding();
	}

	/**
	 * Check database schema encoding. On error will set warning message.
	 *
	 * @return bool
	 */
	protected function checkDatabaseEncoding() {
		$row = DBfetch(DBselect('SELECT value,parameter FROM NLS_DATABASE_PARAMETERS'.
			' WHERE '.dbConditionString('parameter', ['NLS_CHARACTERSET', 'NLS_NCHAR_CHARACTERSET']).
				' AND '.dbConditionString('value', [ORACLE_UTF8_CHARSET, ORACLE_CESU8_CHARSET], true)
		));

		if ($row) {
			$this->setWarning(_s('Incorrect parameter "%1$s" value: %2$s.', $row['parameter'],
				_s('"%1$s" instead "%2$s"', $row['value'], ORACLE_UTF8_CHARSET.', '.ORACLE_CESU8_CHARSET)
			));
		}

		return !$row;
	}

	/**
	* Check if database is using IEEE754 compatible double precision columns.
	*
	* @return bool
	*/
	public function isDoubleIEEE754() {
		global $DB;

		$conditions_or = [
			'(table_name=\'HISTORY\' AND column_name=\'VALUE\')',
			'(table_name=\'TRENDS\' AND column_name IN (\'VALUE_MIN\', \'VALUE_AVG\', \'VALUE_MAX\'))'
		];

		$sql =
			'SELECT COUNT(*) cnt FROM user_tab_columns'.
				' WHERE data_type=\'BINARY_DOUBLE\' AND ('.implode(' OR ', $conditions_or).')';

		$result = DBfetch(DBselect($sql));

		return (is_array($result) && array_key_exists('cnt', $result) && $result['cnt'] == 4);
	}
}
