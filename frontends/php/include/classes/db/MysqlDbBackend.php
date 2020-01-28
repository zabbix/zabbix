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
 * Database backend class for MySQL.
 */
class MysqlDbBackend extends DbBackend {

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return bool
	 */
	protected function checkDbVersionTable() {
		$tableExists = DBfetch(DBselect("SHOW TABLES LIKE 'dbversion'"));

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
		$row = DBfetch(DBselect("SHOW STATUS LIKE 'ssl_cipher'"));

		$pattern = '/'.str_replace('*', '.+', $this->tls_cipher_list).'/';

		if (!$row || !$row['Value']) {
			$this->setError('Error connecting to database. Empty cipher.');
			return false;
		}

		if ($row && !preg_match($pattern, $row['Value'])) {
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
		$resource = mysqli_init();
		$tls_mode = null;

		if ($this->tls_encryption !== ZBX_DB_TLS_DISABLED) {
			if ($this->tls_encryption === ZBX_DB_TLS_VERIFY_HOST) {
				$resource->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
			}

			$cipher_suit = ($this->tls_cipher_list && strpos($this->tls_cipher_list, '*') === false)
				? $this->tls_cipher_list
				: null;
			$resource->ssl_set($this->tls_key_file, $this->tls_cert_file, $this->tls_ca_file, null, $cipher_suit);

			$tls_mode = ($this->tls_encryption === ZBX_DB_TLS_VERIFY_HOST || !($this->tls_ca_file))
				? MYSQLI_CLIENT_SSL
				: MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;

		}

		$resource->real_connect($host, $user, $password, $dbname, $port, null, $tls_mode);

		if ($resource->error) {
			$this->setError($resource->error);
			return null;
		}

		if ($resource->errno) {
			$this->setError('Database error code '.$resource->errno);
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
		DBexecute('SET NAMES utf8');
	}
}
