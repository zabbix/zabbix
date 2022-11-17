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
 * Database backend class for PostgreSQL.
 */
class PostgresqlDbBackend extends DbBackend {
	/**
	 * Database name.
	 *
	 * @var string
	 */
	protected $dbname = '';

	/**
	 * DB schema.
	 *
	 * @var string
	 */
	protected $schema = '';

	/**
	 * User name.
	 *
	 * @var string
	 */
	protected $user = '';

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		$table_exists = DBfetch(DBselect(
			'SELECT 1 FROM information_schema.tables'.
			' WHERE table_catalog='.zbx_dbstr($this->dbname).
				' AND table_schema='.zbx_dbstr($this->schema).
				' AND table_name='.zbx_dbstr('dbversion')
		));

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
		$row = DBfetch(DBselect('SELECT pg_encoding_to_char(encoding) db_charset FROM pg_database'.
			' WHERE datname='.zbx_dbstr($DB['DATABASE'])
		));

		if ($row && $row['db_charset'] != ZBX_DB_POSTGRESQL_ALLOWED_CHARSET) {
			$this->setWarning(_s('Incorrect default charset for Zabbix database: %1$s.',
				_s('"%1$s" instead "%2$s"', $row['db_charset'], ZBX_DB_POSTGRESQL_ALLOWED_CHARSET)
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

	/**
	* Check if database is using IEEE754 compatible double precision columns.
	*
	* @return bool
	*/
	public function isDoubleIEEE754() {
		global $DB;

		$conditions_or = [
			'(table_name=\'history\' AND column_name=\'value\')',
			'(table_name=\'trends\' AND column_name IN (\'value_min\', \'value_avg\', \'value_max\'))'
		];

		$sql =
			'SELECT COUNT(*) cnt FROM information_schema.columns'.
				' WHERE table_catalog='.zbx_dbstr($DB['DATABASE']).
					' AND table_schema='.zbx_dbstr($DB['SCHEMA'] ? $DB['SCHEMA'] : 'public').
					' AND data_type=\'double precision\''.
					' AND ('.implode(' OR ', $conditions_or).')';

		$result = DBfetch(DBselect($sql));

		return (is_array($result) && array_key_exists('cnt', $result) && $result['cnt'] == 4);
	}

	/**
	 * Check is current connection contain requested cipher list.
	 *
	 * @return bool
	 */
	public function isConnectionSecure() {
		$row = DBfetch(DBselect('SHOW server_version'));
		$is_secure = false;

		if (version_compare($row['server_version'], '9.5', '<')) {
			$row = DBfetch(DBselect('SHOW ssl'));
			$is_secure = ($row && $row['ssl'] === 'on');
		}
		else {
			$is_secure = (bool) DBfetch(DBselect('SELECT datname, usename, ssl, client_addr, cipher FROM pg_stat_ssl'.
				' JOIN pg_stat_activity ON pg_stat_ssl.pid=pg_stat_activity.pid'.
					' AND pg_stat_activity.usename='.zbx_dbstr($this->user)));
		}

		if (!$is_secure) {
			$this->setError('Error connecting to database. Connection is not secure.');
		}

		return $is_secure;
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
		$this->user = $user;
		$this->dbname = $dbname;
		$this->schema = ($schema) ? $schema : 'public';
		$params = compact(['host', 'port', 'user', 'password', 'dbname']);

		if ($this->tls_encryption && (bool) $this->tls_ca_file) {
			$params += [
				'sslmode' => $this->tls_verify_host ? 'verify-full' : 'verify-ca',
				'sslkey' => $this->tls_key_file,
				'sslcert' => $this->tls_cert_file,
				'sslrootcert' => $this->tls_ca_file
			];
		}

		$conn_string = '';

		foreach ($params as $key => $param) {
			$conn_string .= ((bool) $param) ? $key.'=\''.pg_connect_escape($param).'\' ' : '';
		}

		$resource = @pg_connect($conn_string);

		if (!$resource) {
			$this->setError('Error connecting to database.');
			return null;
		}

		return $resource;
	}

	/**
	 * Initialize database connection.
	 *
	 * @return bool
	 */
	public function init() {
		global $DB;

		$schema_set = DBexecute('SET search_path='.zbx_dbstr($this->schema));

		if (!$schema_set) {
			$this->setError(pg_last_error($DB['DB']));
			return false;
		}

		$pgsql_version = pg_parameter_status($DB['DB'], 'server_version');

		if ($pgsql_version !== false && (int) $pgsql_version >= 9) {
			// change the output format for values of type bytea from hex (the default) to escape
			DBexecute('SET bytea_output=escape');
		}

		return true;
	}

	/**
	 * Check if tables have compressed data.
	 *
	 * @static
	 *
	 * @param array $tables  Tables list.
	 *
	 * @return bool
	 */
	public static function isCompressed(array $tables): bool {
		if (CHousekeepingHelper::get(CHousekeepingHelper::DB_EXTENSION) != ZBX_DB_EXTENSION_TIMESCALEDB) {
			return false;
		}

		$timescale_v1 = DBfetch(DBselect(
			'SELECT NULL FROM pg_catalog.pg_class c'.
			' JOIN pg_catalog.pg_namespace n'.
			' ON n.oid=c.relnamespace'.
			' WHERE n.nspname='.zbx_dbstr('timescaledb_information').
			' AND c.relname='.zbx_dbstr('compressed_hypertable_stats')
		));

		if ($timescale_v1) {
			$result = DBfetch(DBselect('SELECT coalesce(sum(number_compressed_chunks),0) chunks'.
				' FROM timescaledb_information.compressed_hypertable_stats'.
				' WHERE number_compressed_chunks != 0 AND '.dbConditionString('hypertable_name::text', $tables)
			));

			return (bool) $result['chunks'];
		}

		$query = implode(' UNION ', array_map(function ($table) {
			return 'SELECT number_compressed_chunks chunks'.
				' FROM hypertable_compression_stats('.zbx_dbstr($table).')'.
				' WHERE number_compressed_chunks != 0';
		}, $tables));

		$result = DBfetch(DBselect($query));

		return (bool) $result['chunks'];
	}

	/**
	 * @inheritdoc
	 */
	public function dbFieldExists(string $table_name, string $field_name): bool {
		global $DB;

		$schema = $DB['SCHEMA'] ? $DB['SCHEMA'] : 'public';

		return (bool) DBFetch(DBselect(
			'SELECT 1'.
			' FROM information_schema.columns'.
			' WHERE table_name='.zbx_dbstr($table_name).
				' AND column_name='.zbx_dbstr($field_name).
				' AND table_schema='.zbx_dbstr($schema)
		));
	}
}
