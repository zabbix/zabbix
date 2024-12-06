<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

/**
 * Database backend class for MySQL.
 */
class MysqlDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect("SHOW TABLES LIKE 'dbversion'"));

		if (!$table_exists) {
			$this->setError(_s('Unable to determine current Zabbix database version: %1$s.',
				_s('the table "%1$s" was not found', 'dbversion')
			));

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
		$row = DBfetch(DBselect('SELECT default_character_set_name db_charset FROM information_schema.schemata'.
			' WHERE schema_name='.zbx_dbstr($DB['DATABASE'])
		));

		if ($row && !in_array(strtoupper($row['db_charset']), ZBX_DB_MYSQL_ALLOWED_CHARSETS)) {
			$this->setWarning(_s('Incorrect default charset for Zabbix database: %1$s.',
				_s('"%1$s" instead "%2$s"', $row['db_charset'], implode(', ', ZBX_DB_MYSQL_ALLOWED_CHARSETS))
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
		// Aliasing table_name to ensure field name is lowercase.
		$tables = DBfetchColumn(DBSelect('SELECT table_name AS table_name FROM information_schema.columns'.
			' WHERE table_schema='.zbx_dbstr($DB['DATABASE']).
				' AND '.dbConditionString('table_name', array_keys(DB::getSchema())).
				' AND '.dbConditionString('data_type', ['text', 'varchar', 'longtext']).
				' AND ('.
					dbConditionString('UPPER(character_set_name)', ZBX_DB_MYSQL_ALLOWED_CHARSETS, true).
					' OR '.dbConditionString('collation_name', ZBX_DB_MYSQL_ALLOWED_COLLATIONS, true).
				')'
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

	/**
	 * Check is current connection contain requested cipher list.
	 *
	 * @return bool
	 */
	public function isConnectionSecure() {
		$row = DBfetch(DBselect("SHOW STATUS LIKE 'ssl_cipher'"));

		if (!$row || !$row['Value']) {
			$this->setError('Error connecting to database. Empty cipher.');
			return false;
		}

		return true;
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
	 * @return mysqli|null
	 */
	public function connect($host, $port, $user, $password, $dbname, $schema): ?mysqli {
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		$resource = mysqli_init();

		if ($resource === false) {
			return null;
		}

		if ($this->tls_encryption) {
			$cipher_suit = $this->tls_cipher_list !== '' ? $this->tls_cipher_list : null;
			$resource->ssl_set($this->tls_key_file, $this->tls_cert_file, $this->tls_ca_file, null, $cipher_suit);

			$tls_mode = MYSQLI_CLIENT_SSL;
		}
		else {
			$tls_mode = 0;
		}

		try {
			@$resource->real_connect($host, $user, $password, $dbname, $port, null, $tls_mode);
		}
		catch (mysqli_sql_exception $e) {
			$this->setError($e->getMessage());

			return null;
		}

		if ($resource->autocommit(true) === false) {
			$this->setError('Error setting auto commit.');

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
		$db_encoding = DBselect("SHOW VARIABLES LIKE 'character_set_database'");
		$charset = $db_encoding ? DBfetch($db_encoding) : false;
		if ($charset && strtoupper($charset['Value']) === 'UTF8MB4') {
			DBexecute('SET NAMES utf8mb4');
			return;
		}

		DBexecute('SET NAMES utf8');
	}
}
