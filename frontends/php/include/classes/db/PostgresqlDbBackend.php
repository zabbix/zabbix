<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
		$tableExists = DBfetch(DBselect('SELECT 1 FROM information_schema.tables'.
			' WHERE table_catalog='.zbx_dbstr($this->dbname).
				' AND table_schema='.zbx_dbstr($this->schema).
				' AND table_name='.zbx_dbstr('dbversion')
		));

		if (!$tableExists) {
			$this->setError(_('The frontend does not match Zabbix database.'));
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
		$row = DBfetch(DBselect('SELECT datname, usename, ssl, client_addr, cipher FROM pg_stat_ssl'.
			' JOIN pg_stat_activity ON pg_stat_ssl.pid=pg_stat_activity.pid'.
				' AND pg_stat_activity.usename='.zbx_dbstr($this->user)));

		$pattern = '/'. str_replace('*', '.+', $this->tls_cipher_list).'/';

		if (!$row || ($this->tls_cipher_list !== '' && !preg_match($pattern, $row['cipher']))) {
			$this->setError('Error connecting to database. Invalid cipher.');

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
	 * @param
	 * @return resource|null
	 */
	public function connect($host, $port, $user, $password, $dbname, $schema) {
		$this->user = $user;
		$this->dbname = $dbname;
		$this->schema = ($schema) ? $schema : 'public';
		$params = compact(['host', 'port', 'user', 'password', 'dbname']);

		if ($this->tls_encryption === ZBX_DB_TLS_VERIFY_HOST
				|| ($this->tls_encryption === ZBX_DB_TLS_ENABLED && ((bool) $this->tls_ca_file))) {
			$params += [
				'sslmode' => ($this->tls_encryption === ZBX_DB_TLS_VERIFY_HOST) ? 'verify-full' : 'verify-ca',
				'sslkey' => $this->tls_key_file,
				'sslcert' => $this->tls_cert_file,
				'sslrootcert' => $this->tls_ca_file
			];
		}

		$conn_string = '';

		foreach ($params as $key => $param) {
			$conn_string .= ((bool) $param) ? $key.'=\''.pg_connect_escape($param).'\' ' : '';
		}

		$resource = pg_connect($conn_string);

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
		$schema_set = DBexecute('SET search_path='.zbx_dbstr($this->schema), true);

		if(!$schema_set) {
			$this->setError(pg_last_error());
			return false;
		}

		$pgsql_version = pg_parameter_status('server_version');

		if ($pgsql_version !== false && (int) $pgsql_version >= 9) {
			// change the output format for values of type bytea from hex (the default) to escape
			DBexecute('SET bytea_output=escape');
		}

		return true;
	}
}
