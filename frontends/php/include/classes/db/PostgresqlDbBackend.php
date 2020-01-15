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
	 * Creates database connection.
	 *
	 * @param string $host         Host name.
	 * @param string $user         User name.
	 * @param string $password     Password.
	 * @param string $database     Database name.
	 * @param string $port         Port.
	 * @param string $key_file     Path name to the key file.
	 * @param string $cert_file    Path name to the certificate file.
	 * @param string $ca_file      Path name to the certificate authority file.
	 *
	 * @return resource|bool
	 */
	public function connect($host, $user, $password, $database, $port, $key_file, $cert_file, $ca_file) {
		$pg_connection_string =
			(($host !== '') ? 'host=\''.pg_connect_escape($host).'\' ' : '').
			'dbname=\''.pg_connect_escape($database).'\' '.
			(($user !== '') ? 'user=\''.pg_connect_escape($user).'\' ' : '').
			(($password !== '') ? 'password=\''.pg_connect_escape($password).'\' ' : '').
			(($port !== '') ? 'port='.pg_connect_escape($port) : '');

		if ($key_file.$cert_file.$ca_file !== '') {
			$this->ssl = true;
			$pg_connection_string .= ' sslmode=\'verify-ca\' sslkey=\''.pg_connect_escape($key_file).
				'\' sslcert=\''.pg_connect_escape($cert_file).'\''.
				(($ca_file === '') ? '' : ' sslrootcert=\''.pg_connect_escape($ca_file).'\'');
		}

		$this->connect = @pg_connect($pg_connection_string);

		if (!$this->connect) {
			$this->setError('Error connecting to database.');
		}

		return $this->connect;
	}

	/**
	 * Initialize database connection.
	 *
	 * @param string $cipher_list  A list of allowable ciphers to use for SSL encryption.
	 * @param string $user         User name.
	 * @param string $schema       DB schema.
	 *
	 * @return bool
	 */
	public function init($cipher_list, $user, $schema) {
		if ($this->ssl) {
			$query = sprintf('SELECT datname, usename, ssl, client_addr, cipher FROM pg_stat_ssl '.
				'JOIN pg_stat_activity ON pg_stat_ssl.pid = pg_stat_activity.pid and '.
				'pg_stat_activity.usename = \'%s\';', $user);

			$row = DBfetch(DBselect($query));

			$pattern = '/'. str_replace('*', '.*', $cipher_list).'/';

			if (!$row || ($cipher_list !== '' && !preg_match($pattern, $row['cipher']))) {
				$this->setError('Error connecting to database. Invalid cipher.');

				return false;
			}
		}

		$schemaSet = DBexecute('SET search_path = '.zbx_dbstr($schema ? $schema : 'public'), true);

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
