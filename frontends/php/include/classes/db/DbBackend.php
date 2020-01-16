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
 * Abstract database backend class.
 */
abstract class DbBackend {
	/**
	 * Error message.
	 *
	 * @var string
	 */
	protected $error;

	/**
	 * Path to SSL key file.
	 *
	 * @var string
	 */
	protected $ssl_key_file = '';

	/**
	 * Path to SSL cert file.
	 *
	 * @var string
	 */
	protected $ssl_cert_file = '';

	/**
	 * Path to SSL ca file.
	 *
	 * @var string
	 */
	protected $ssl_ca_file = '';

	/**
	 * Connection required cipher pattern.
	 *
	 * @var string
	 */
	protected $ssl_cipher_list = '';

	/**
	 * Set SSL specific options for db conection.
	 *
	 * @param string $key_file       Path to SSL key file.
	 * @param string $cert_file      Path to SSL cert file.
	 * @param string $ca_file        Path to SSL ca file.
	 * @param string $cipher_list    Connection required cipher pattern.
	 */
	public function setConnectionSecurity($key_file, $cert_file, $ca_file, $cipher_list) {
		$this->ssl_key_file = $key_file;
		$this->ssl_cert_file = $cert_file;
		$this->ssl_ca_file = $ca_file;
		$this->ssl_cipher_list = $cipher_list;
	}

	/**
	 * Check if 'dbversion' table exists.
	 *
	 * @return boolean
	 */
	abstract protected function checkDbVersionTable();

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
	 * @return resource|null
	 */
	abstract public function connect($host, $port, $user, $password, $dbname, $schema);

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
	 * Check the integrity of the table "config".
	 *
	 * @return bool
	 */
	public function checkConfig() {
		if (!DBfetch(DBselect('SELECT NULL FROM config c'))) {
			$this->setError(_('Unable to select configuration.'));
			return false;
		}

		return true;
	}

	/**
	 * Create INSERT SQL query for MySQL, PostgreSQL.
	 * Creation example:
	 *	INSERT INTO applications (name,hostid,templateid,applicationid)
	 *	VALUES ('CPU','10113','13','868'),('Filesystems','10113','5','869'),('General','10113','21','870');
	 *
	 * @param string $table
	 * @param array $fields
	 * @param array $values
	 *
	 * @return string
	 */
	public function createInsertQuery($table, array $fields, array $values) {
		$sql = 'INSERT INTO '.$table.' ('.implode(',', $fields).') VALUES ';

		foreach ($values as $row) {
			$sql .= '('.implode(',', array_values($row)).'),';
		}

		$sql = substr($sql, 0, -1);

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
	 * Return error or null if no error occurred.
	 *
	 * @return mixed
	 */
	public function getError() {
		return $this->error;
	}
}
