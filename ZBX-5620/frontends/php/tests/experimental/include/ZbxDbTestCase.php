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


require_once __DIR__.'/../../../include/gettextwrapper.inc.php';
require_once __DIR__.'/../../../include/defines.inc.php';
require_once __DIR__.'/../../../include/func.inc.php';
require_once __DIR__.'/../../../include/db.inc.php';
require_once __DIR__.'/../../../include/nodes.inc.php';
require_once __DIR__.'/../../../conf/zabbix.conf.php';

require_once 'ZbxDbOperationTruncate.php';


spl_autoload_register(function($className) {
	$foundFile = false;

	$includePaths = array(
		'/include/classes',
		'/include/classes/core',
		'/include/classes/api',
		'/include/classes/db',
		'/include/classes/debug',
		'/include/classes/validators',
		'/include/classes/export',
		'/include/classes/export/writers',
		'/include/classes/export/elements',
		'/include/classes/import',
		'/include/classes/import/importers',
		'/include/classes/import/readers',
		'/include/classes/import/formatters',
		'/include/classes/screens',
		'/include/classes/sysmaps',
		'/include/classes/helpers',
		'/include/classes/helpers/trigger',
		'/include/classes/macros',
		'/include/classes/tree',
		'/include/classes/html',
		'/api/classes',
		'/api/classes/managers',
		'/api/rpc'
	);

	foreach ($includePaths as $includePath) {
		$filePath = __DIR__.'/../../..'.$includePath.'/'.$className.'.php';

		if (is_file($filePath)) {
			$foundFile = $filePath;
			break;
		}
		else {
			// fallback to old class names
			$filePath = __DIR__.'/../../..'.$includePath.'/class.'.strtolower($className).'.php';
			if (is_file($filePath)) {
				$foundFile = $filePath;
				break;
			}
		}
	}

	if ($foundFile) {
		require $foundFile;
	}
});

define('ZBX_DISTRIBUTED', false);
CZBXAPI::$userData['userid'] = 1;
CZBXAPI::$userData['type'] = USER_TYPE_SUPER_ADMIN;
API::setReturnAPI();


abstract class ZbxDbTestCase extends PHPUnit_Extensions_Database_TestCase {

	private $dbhost = 'sql';
	private $dbuser = 'root';
	private $dbname = 'alexey_test';
	static private $pdo = null;
	private $conn = null;

	/**
	 * Get test specific dataset.
	 *
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	abstract protected function getTestInitialDataSet();

	/**
	 * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
	 */
	final public function getConnection() {
		if ($this->conn === null) {
			if (self::$pdo == null) {
				self::$pdo = new PDO('mysql:host='.$this->dbhost.';dbname='.$this->dbname, $this->dbuser);
			}
			$this->conn = $this->createDefaultDBConnection(self::$pdo);
		}

		return $this->conn;
	}

	/**
	 * Zabbix cennoection to db.
	 */
	public function setUp() {
		global $DB;

		parent::setUp();

		DBConnect($error);
	}

	/**
	 * Override method to use ZbxDbOperationTruncate operation class.
	 *
	 * @return PHPUnit_Extensions_Database_Operation_Composite
	 */
	public function getSetUpOperation() {
		$cascadeTruncates = true;

		return new PHPUnit_Extensions_Database_Operation_Composite(array(
			new ZbxDbOperationTruncate($cascadeTruncates),
			PHPUnit_Extensions_Database_Operation_Factory::INSERT()
		));
	}

	/**
	 * @return PHPUnit_Extensions_Database_DataSet_IDataSet
	 */
	protected function getDataSet() {
		$ds = $this->createMySQLXMLDataSet(__DIR__.'/fixtures/initial_users.xml');
		$testDs = $this->getTestInitialDataSet();

		return new PHPUnit_Extensions_Database_DataSet_CompositeDataSet(array($ds, $testDs));
	}

	/**
	 * Load initial.xml for test case.
	 *
	 * @param string $dir
	 *
	 * @return PHPUnit_Extensions_Database_DataSet_MysqlXmlDataSet
	 */
	protected function loadInitialDataSet($dir) {
		return $this->createMySQLXMLDataSet($dir.'/fixtures/initial.xml');
	}

	/**
	 * Load fixture with expected data set for test method.
	 *
	 * @param string $dir
	 * @param string $method __METHOD__ in child class
	 *
	 * @return PHPUnit_Extensions_Database_DataSet_MysqlXmlDataSet
	 */
	protected function getExpectedDataSet($dir, $method) {
		list(, $method) = explode('::', $method);
		return $this->createMySQLXMLDataSet($dir.'/fixtures/'.$method.'.xml');
	}
}
