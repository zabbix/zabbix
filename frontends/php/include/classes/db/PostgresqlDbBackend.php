<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Database backend class for PostgreSQL.
 */
class PostgresqlDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		global $DB;

		$schema = zbx_dbstr($DB['SCHEMA'] ? $DB['SCHEMA'] : 'public');

		$tableExists = DBfetch(DBselect('SELECT 1 FROM information_schema.tables'.
			' WHERE table_catalog='.zbx_dbstr($DB['DATABASE']).
				' AND table_schema='.$schema.
				" AND table_name='dbversion'"
		));

		if (!$tableExists) {
			$this->setError(_('The frontend does not match Zabbix database.'));
			return false;
		}

		return true;
	}

	/**
	 * Check database and table fields encoding.
	 *
	 * @return bool
	 */
	public function checkEncoding() {
		global $DB;

		return $this->checkDatabaseEncoding($DB) && $this->checkTablesEncoding($DB);
	}

	/**
	 * Check database schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkDatabaseEncoding(array $DB) {
		$row = DBfetch(DBselect('SELECT pg_encoding_to_char(encoding) db_charset FROM pg_database'.
			' WHERE datname='.zbx_dbstr($DB['DATABASE'])
		));

		if ($row && $row['db_charset'] != ZBX_DB_DEFAULT_CHARSET) {
			$this->setWarning(_s('Incorrect default charset for Zabbix database: %1$s.',
				_s('"%1$s" instead "%2$s"', $row['db_charset'], ZBX_DB_DEFAULT_CHARSET)
			));
			return false;
		}

		return true;
	}

	/**
	 * Check tables schema encoding. On error will set warning message.
	 *
	 * @param array $DB  Array of database settings, same as global $DB.
	 *
	 * @return bool
	 */
	protected function checkTablesEncoding(array $DB) {
		$schema = $DB['SCHEMA'] ? $DB['SCHEMA'] : 'public';
		$row = DBfetch(DBselect('SELECT oid FROM pg_namespace WHERE nspname='.zbx_dbstr($schema)));

		/**
		 * Getting all fields in all Zabbix tables to check for collation.
		 * If collation is not default (its mean collation is the same that was during db creation),
		 * than we consider it as error.
		 */
		$tables = DBfetchColumn(DBSelect('SELECT c.relname AS table_name FROM pg_attribute AS a'.
			' LEFT JOIN pg_class AS c ON c.relfilenode=a.attrelid'.
			' LEFT JOIN pg_collation AS l ON l.oid=a.attcollation'.
			' WHERE '.dbConditionInt('atttypid', [25, 1043]).
				' AND '.dbConditionInt('c.relnamespace', [$row['oid']]).
				' AND c.relam=0 AND '.dbConditionString('c.relname', array_keys(DB::getSchema())).
				' AND l.collname!='.zbx_dbstr('default')
		), 'table_name');

		if ($tables) {
			$tables = array_unique($tables);
			$this->setWarning(_n('Unsupported charset or collation for table: %1$s.',
				'Unsupported charset or collation for tables: %1$s.',
				implode(', ', $tables), implode(', ', $tables), count($tables)
			));
			return false;
		}

		return true;
	}
}
