<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php

require_once dirname(__FILE__).'/../../include/validate.inc.php';
require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../include/nodes.inc.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';
require_once dirname(__FILE__).'/dbfunc.php';

// locales
require_once dirname(__FILE__).'/../../include/locales.inc.php';

// classes
require_once dirname(__FILE__).'/../../include/classes/class.cwebuser.php';

// APIs
require_once dirname(__FILE__).'/../../include/classes/api/APIException.php';
require_once dirname(__FILE__).'/../../include/classes/api/CZBXAPI.php';
require_once dirname(__FILE__).'/../../api/classes/CItemGeneral.php';
require_once dirname(__FILE__).'/../../api/classes/CItemKey.php';
require_once dirname(__FILE__).'/../../api/classes/CItem.php';
require_once dirname(__FILE__).'/../../api/classes/CHost.php';
require_once dirname(__FILE__).'/../../api/classes/CHostGroup.php';
require_once dirname(__FILE__).'/../../api/classes/CTemplate.php';
require_once dirname(__FILE__).'/../../api/classes/CHostInterface.php';
require_once dirname(__FILE__).'/../../api/classes/CProxy.php';
require_once dirname(__FILE__).'/../../api/classes/CGraph.php';
require_once dirname(__FILE__).'/../../api/classes/CTrigger.php';

if (!function_exists('info')) {
	function info($data) {
	}
}

require_once 'PHPUnit/Autoload.php';

/**
 * A base class for creating API method tests.
 */
abstract class CApiTest extends PHPUnit_Framework_TestCase {

	/**
	 * API object.
	 *
	 * @var CZBXAPI
	 */
	protected $api;

	/**
	 * An array of private keys of the created during the tests.
	 *
	 * @var array
	 */
	protected $createdPks = array();


	/**
	 * A test host object.
	 *
	 * @var array
	 */
	protected $testHost;


	/**
	 * A method that provides valid objects for creating.
	 *
	 * @abstract
	 *
	 * @return array
	 */
	public abstract function providerCreateValid();


	/**
	 * Test case setup.
	 *
	 * @static
	 */
	public static function setUpBeforeClass() {

		// some variable defines not to include config.inc.php
		global $ZBX_CURRENT_NODEID;
		$ZBX_CURRENT_NODEID = 0;
		define('ZBX_DISTRIBUTED', false);

		// add some user data
		CZBXAPI::$userData = array(
			'userid' => null,
			'type' => USER_TYPE_SUPER_ADMIN
		);

		// skip the RPC calls
		API::setReturnAPI();
	}


	/**
	 * Test setup.
	 */
	public function setUp() {
		DBconnect($error);
	}


	/**
	 * A test for creating valid objects.
	 *
	 * @dataProvider providerCreateValid
	 *
	 * @param array $object
	 */
	public function testCreateValid(array $object) {
		$rs = $this->createTestObject($object);

		$this->assertArrayHasKey($this->api->pkOption(), $rs, get_class($this->api).'->create() result does not contain "'.$this->api->pkOption().'"');

		$this->createdPks = array_merge($this->createdPks, $rs[$this->api->pkOption()]);
	}

	/**
	 * A test for retrieving objects.
	 *
	 * @dataProvider providerCreateValid
	 *
	 * @param array $object
	 */
	public function testGet(array $object) {
		$this->createTestObject($object);

		$rs = $this->api->get(
			array(
				$this->api->pkOption() => $this->createdPks
			)
		);

		$this->assertCount(1, $rs, 'One of the objects has not been retrieved.');
	}


	/**
	 * A test for deleting object.
	 *
	 * @dataProvider providerCreateValid
	 *
	 * @param array $object
	 */
	public function testDelete(array $object) {
		$rs = $this->createTestObject($object);
		$rs = $this->api->delete($rs[$this->api->pkOption()]);

		$this->assertCount(1, $rs[$this->api->pkOption()], 'One of the objects has not been deleted.');
	}


	/**
	 * Reverts the database to the initial test.
	 */
	public function tearDown() {

		// delete the created objects
		if ($this->createdPks) {
			$this->tearDownTestObjects();
		}

		// delete test host
		if ($this->testHost) {
			$this->tearDownTestHost();
		}
	}


	/**
	 * Saves an object to the database and returns the result. The objects private key will be added to
	 * $this->createdPks.
	 *
	 * @param array $object
	 *
	 * @return mixed
	 */
	protected function createTestObject(array $object) {
		$rs = $this->api->create($object);

		$this->createdPks = array_merge($this->createdPks, $rs[$this->api->pkOption()]);

		return $rs;
	}


	/**
	 * Deletes the objects, that have been created during the test.
	 *
	 * TODO: delete related objects.
	 */
	protected function tearDownTestObjects() {
		DB::delete($this->api->tableName(), array(
			$this->api->pk() => $this->createdPks
		));
	}


	/**
	 * Sets up a test host group and a host. The created host is saved to $this->testHost.
	 */
	protected function setUpTestHost() {

		// create a test group
		$hostGroupApi = API::HostGroup();
		$hostGroupRs = $hostGroupApi->create(
			array(
				'name' => 'Test host group'
			)
		);

		// create a test host
		$hostApi = new CHost();
		$hostRs = $hostApi->create(array(
			'host' => 'Test host 1',
			'interfaces' => array(
				array(
					'type' => INTERFACE_TYPE_AGENT,
					'ip' => '127.0.0.1',
					'dns' => '',
					'useip' => 1,
					'port' => 10050,
					'main' => 1
				)
			),
			'groups' => array(
				array(
					'groupid' => $hostGroupRs['groupids'][0]
				)
			)
		));

		// fetch the new host
		$hosts = $hostApi->get(array(
			'output' => API_OUTPUT_EXTEND,
			'hostids' => $hostRs['hostids'][0],
			'selectGroups' => API_OUTPUT_EXTEND,
			'selectInterfaces' => API_OUTPUT_EXTEND
		));
		$this->testHost = reset($hosts);
	}


	/**
	 * Deletes the created test host group and host.
	 */
	protected function tearDownTestHost() {
		DB::delete('hosts', array(
			'hostid' => $this->testHost['hostid']
		));
		DB::delete('groups', array(
			'groupid' => zbx_objectValues($this->testHost['groups'], 'groupid')
		));
		$this->testHost = null;
	}
}
?>
