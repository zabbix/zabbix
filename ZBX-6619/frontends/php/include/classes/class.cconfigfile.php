<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


class CConfigFile {

	const CONFIG_NOT_FOUND = 1;
	const CONFIG_ERROR = 2;

	const CONFIG_FILE_NAME = 'zabbix.conf.php';
	const CONFIG_FILE_PATH = '/conf/zabbix.conf.php';

	public $configFile = null;
	public $config = array();
	public $error = '';

	private static function exception($error, $code = self::CONFIG_ERROR) {
		throw new ConfigFileException($error, $code);
	}

	public function __construct($file = null) {
		$this->setDefaults();

		if (!is_null($file)) {
			$this->setFile($file);
		}
	}

	public function setFile($file) {
		$this->configFile = $file;
	}

	public function load() {
		if (!file_exists($this->configFile)) {
			self::exception('Config file does not exist.', self::CONFIG_NOT_FOUND);
		}

		ob_start();
		include($this->configFile);
		ob_end_clean();

		// config file in plain php is bad
		$dbs = array(ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL, ZBX_DB_ORACLE, ZBX_DB_DB2, ZBX_DB_SQLITE3);
		if (!isset($DB['TYPE'])) {
			self::exception('DB type is not set.');
		}
		elseif (isset($DB['TYPE']) && !in_array($DB['TYPE'], $dbs)) {
			self::exception('DB type has wrong value. Possible values '.implode(', ', $dbs));
		}
		elseif (!isset($DB['DATABASE'])) {
			self::exception('DB database is not set.');
		}

		$this->setDefaults();

		if (isset($DB['TYPE'])) {
			$this->config['DB']['TYPE'] = $DB['TYPE'];
		}

		if (isset($DB['DATABASE'])) {
			$this->config['DB']['DATABASE'] = $DB['DATABASE'];
		}

		if (isset($DB['SERVER'])) {
			$this->config['DB']['SERVER'] = $DB['SERVER'];
		}

		if (isset($DB['PORT'])) {
			$this->config['DB']['PORT'] = $DB['PORT'];
		}

		if (isset($DB['USER'])) {
			$this->config['DB']['USER'] = $DB['USER'];
		}

		if (isset($DB['PASSWORD'])) {
			$this->config['DB']['PASSWORD'] = $DB['PASSWORD'];
		}

		if (isset($DB['SCHEMA'])) {
			$this->config['DB']['SCHEMA'] = $DB['SCHEMA'];
		}

		if (isset($ZBX_SERVER)) {
			$this->config['ZBX_SERVER'] = $ZBX_SERVER;
		}
		if (isset($ZBX_SERVER_PORT)) {
			$this->config['ZBX_SERVER_PORT'] = $ZBX_SERVER_PORT;
		}
		if (isset($ZBX_SERVER_NAME)) {
			$this->config['ZBX_SERVER_NAME'] = $ZBX_SERVER_NAME;
		}

		$this->makeGlobal();

		return $this->config;
	}

	public function makeGlobal() {
		global $DB, $ZBX_SERVER, $ZBX_SERVER_PORT, $ZBX_SERVER_NAME;

		$DB = $this->config['DB'];
		$ZBX_SERVER = $this->config['ZBX_SERVER'];
		$ZBX_SERVER_PORT = $this->config['ZBX_SERVER_PORT'];
		$ZBX_SERVER_NAME = $this->config['ZBX_SERVER_NAME'];
	}

	public function save() {
		try {
			if (is_null($this->configFile)) {
				self::exception('Cannot save, config file is not set.');
			}

			$this->check();

			if (!file_put_contents($this->configFile, $this->getString())) {
				self::exception('Cannot write config file.');
			}
		}
		catch (Exception $e) {
			$this->error = $e->getMessage();
			return false;
		}
	}

	public function getString() {
		return
'<?php
// Zabbix GUI configuration file
global $DB;

$DB[\'TYPE\']     = \''.addcslashes($this->config['DB']['TYPE'], "'\\").'\';
$DB[\'SERVER\']   = \''.addcslashes($this->config['DB']['SERVER'], "'\\").'\';
$DB[\'PORT\']     = \''.addcslashes($this->config['DB']['PORT'], "'\\").'\';
$DB[\'DATABASE\'] = \''.addcslashes($this->config['DB']['DATABASE'], "'\\").'\';
$DB[\'USER\']     = \''.addcslashes($this->config['DB']['USER'], "'\\").'\';
$DB[\'PASSWORD\'] = \''.addcslashes($this->config['DB']['PASSWORD'], "'\\").'\';

// SCHEMA is relevant only for IBM_DB2 database
$DB[\'SCHEMA\'] = \''.addcslashes($this->config['DB']['SCHEMA'], "'\\").'\';

$ZBX_SERVER      = \''.addcslashes($this->config['ZBX_SERVER'], "'\\").'\';
$ZBX_SERVER_PORT = \''.addcslashes($this->config['ZBX_SERVER_PORT'], "'\\").'\';
$ZBX_SERVER_NAME = \''.addcslashes($this->config['ZBX_SERVER_NAME'], "'\\").'\';

$IMAGE_FORMAT_DEFAULT = IMAGE_FORMAT_PNG;
?>
';
	}

	protected function setDefaults() {
		$this->config['DB'] = array(
			'TYPE' => null,
			'SERVER' => 'localhost',
			'PORT' => '0',
			'DATABASE' => null,
			'USER' => '',
			'PASSWORD' => '',
			'SCHEMA' => ''
		);
		$this->config['ZBX_SERVER'] = 'localhost';
		$this->config['ZBX_SERVER_PORT'] = '10051';
		$this->config['ZBX_SERVER_NAME'] = '';
	}

	protected function check() {
		$dbs = array(ZBX_DB_MYSQL, ZBX_DB_POSTGRESQL, ZBX_DB_ORACLE, ZBX_DB_DB2, ZBX_DB_SQLITE3);

		if (!isset($this->config['DB']['TYPE'])) {
			self::exception('DB type is not set.');
		}
		elseif (!in_array($this->config['DB']['TYPE'], $dbs)) {
			self::exception('DB type has wrong value. Possible values '.implode(', ', $dbs));
		}
		elseif (!isset($this->config['DB']['DATABASE'])) {
			self::exception('DB database is not set.');
		}
	}
}
