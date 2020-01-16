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
		$schema = zbx_dbstr($this->schema ? $this->schema : 'public');

		$tableExists = DBfetch(DBselect('SELECT 1 FROM information_schema.tables'.
			' WHERE table_catalog='.zbx_dbstr($this->dbname).
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
	 * Check is current connection contain requested cipher list.
	 *
	 * @return bool
	 */
	public function isConnectionSecure() {
		$query = sprintf('SELECT datname, usename, ssl, client_addr, cipher FROM pg_stat_ssl '.
			'JOIN pg_stat_activity ON pg_stat_ssl.pid = pg_stat_activity.pid and '.
			'pg_stat_activity.usename = \'%s\';', $this->user);

		$row = DBfetch(DBselect($query));

		$pattern = '/'. str_replace('*', '.*', $this->ssl_cipher_list).'/';

		if (!$row || ($this->ssl_cipher_list !== '' && !preg_match($pattern, $row['cipher']))) {
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
		$conn_string = '';

		foreach (compact(['host', 'port', 'user', 'password', 'dbname']) as $key => $param) {
			$conn_string .= ((bool) $param) ? $key.'=\''.pg_connect_escape($param).'\' ' : '';
		}

		foreach (compact(['user', 'dbname', 'schema']) as $key => $property) {
			$this->{$key} = $property;
		}

		if ($this->ssl_key_file || $this->ssl_cert_file || $this->ssl_ca_file) {
			$conn_string .= ' sslmode=\'verify-ca\' sslkey=\''.pg_connect_escape($this->ssl_key_file).
				'\' sslcert=\''.pg_connect_escape($this->ssl_cert_file).'\''.
				(($this->ssl_ca_file === '') ? '' : ' sslrootcert=\''.pg_connect_escape($this->ssl_ca_file).'\'');
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
		$schemaSet = DBexecute('SET search_path = '.zbx_dbstr($this->schema ? $this->schema : 'public'), true);

		if(!$schemaSet) {
			clear_messages();
			$this->setError(pg_last_error());
			return false;
		}
		else {
			if (false !== ($pgsql_version = pg_parameter_status('server_version'))) {
				if ((int) $pgsql_version >= 9) {
					// change the output format for values of type bytea from hex (the default) to escape
					DBexecute('SET bytea_output = escape');
				}
			}
		}

		return true;
	}
}
