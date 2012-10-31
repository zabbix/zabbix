<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
require_once __DIR__.'/../../../include/classes/debug/CProfiler.php';
require_once __DIR__.'/../../../include/classes/class.cwebuser.php';
require_once __DIR__.'/../../../include/classes/db/DbBackend.php';
require_once __DIR__.'/../../../include/classes/db/MysqlDbBackend.php';
require_once __DIR__.'/../../../include/classes/db/DB.php';
require_once __DIR__.'/../../../include/classes/api/API.php';
require_once __DIR__.'/../../../include/classes/api/CAPIObject.php';
require_once __DIR__.'/../../../include/classes/api/APIException.php';
require_once __DIR__.'/../../../include/classes/api/CZBXAPI.php';
require_once __DIR__.'/../../../include/classes/db/DBException.php';
require_once __DIR__.'/../../../conf/zabbix.conf.php';

require_once 'ZbxDbOperationTruncate.php';


define('ZBX_DISTRIBUTED', false);
CZBXAPI::$userData['userid'] = 1;
CZBXAPI::$userData['type'] = USER_TYPE_SUPER_ADMIN;


abstract class ZbxDbTestCase extends PHPUnit_Extensions_Database_TestCase {

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
				// TODO: hardcoded db settimgs
				self::$pdo = new PDO('mysql:host=sql;dbname=alexey_test', 'root');
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
	 * @param string $class __CLASS__ in child class
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
