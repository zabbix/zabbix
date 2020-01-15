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
	 * @param string $cipher_list  List of allowable ciphers to use for SSL encryption.
	 *
	 * @return resource|bool
	 */
	public function connect($host, $user, $password, $database, $port, $key_file, $cert_file, $ca_file, $cipher_list) {
		$this->connect = mysqli_init();

		if ($key_file.$cert_file.$ca_file !== '') {
			$this->ssl = true;
			$this->connect->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
			$cipher_suit = preg_match('/^[\w\-]$/', $cipher_list) ? $cipher_list : null;
			$this->connect->ssl_set($key_file, $cert_file, $ca_file, NULL, $cipher_suit);
		}

		if ($this->connect->real_connect($host, $user, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL)) {
			return $this->connect;
		}
		else {
			$this->setError('Error connecting to database: '.trim($this->connect->error));
			return false;
		}
	}

	/**
	 * Initialize database connection.
	 *
	 * @param string $cipher_list  A list of allowable ciphers to use for SSL encryption.
	 *
	 * @return bool
	 */
	public function init($cipher_list) {
		if ($this->connect->autocommit(true) === false) {
			$this->setError('Error setting auto commit.');
			return false;
		}
		else {
			DBexecute('SET NAMES utf8');
		}

		if (!$this->ssl) {
			return true;
		}

		$row = DBfetch(DBselect("SHOW STATUS LIKE 'ssl_cipher'"));

		if ($row) {
			$pattern = '/'. str_replace('*', '.*', $cipher_list).'/';

			if ($cipher_list === '' || preg_match($pattern, $row['Value'])) {
				return true;
			}
		}

		$DB['DB'] = false;
		$this->setError('Error connecting to database. Invalid cipher.');

		return false;
	}
}
