<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../../include/validate.inc.php';
require_once dirname(__FILE__).'/../../../include/func.inc.php';
require_once dirname(__FILE__).'/../../../include/gettextwrapper.inc.php';
require_once dirname(__FILE__).'/../../../include/nodes.inc.php';
require_once dirname(__FILE__).'/../../../conf/zabbix.conf.php';
require_once dirname(__FILE__).'/../../../include/db.inc.php';
require_once dirname(__FILE__).'/../../../include/classes/db/DB.php';
require_once dirname(__FILE__).'/../../../include/items.inc.php';
require_once dirname(__FILE__).'/../../../include/triggers.inc.php';
require_once dirname(__FILE__).'/../../../include/classes/api/APIException.php';
require_once dirname(__FILE__).'/../../../include/classes/core/Z.php';


if (!function_exists('error')) {
	function error($error) {
		echo "\nError reported: $error\n";
		return true;
	}
}
if (!function_exists('get_accessible_nodes_by_user')) {
	function get_accessible_nodes_by_user() {
		return true;
	}
}

class CDRuleTest extends PHPUnit_Framework_TestCase {

	private static $drule;
	private static $createdRules = array();

	public static function setUpBeforeClass() {
		$a = new Z;
		$a->run();

		self::$drule = new CDRule();

		// some variable defines not to include config.inc.php
		global $ZBX_CURRENT_NODEID;
		$ZBX_CURRENT_NODEID = 0;
		define('ZBX_DISTRIBUTED', false);

		// set api to return objects, to pass user session verification
		API::setReturnAPI();

		CZBXAPI::$userData['type'] = USER_TYPE_SUPER_ADMIN;
	}

	public static function tearDownAfterClass() {
		API::setReturnRPC();
	}

	public function setUp() {
		DBConnect($error);
	}

	public function tearDown() {
		if (!empty(self::$createdRules)) {
			self::$drule->delete(self::$createdRules);
			self::$createdRules = array();
		}
		DBclose();
	}

	public static function providerCreateValidRules() {
		return array(
			array(
				// #0 minimal required fields
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
						),
					)
				)
			),
			// #1
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'proxy_hostid' => '0',
					'delay' => '3600',
					'status' => DRULE_STATUS_DISABLED,
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
							'key_' => 'system.uname',
							'snmp_community' => '0',
							'ports' => '10050',
							'snmpv3_securityname' => '',
							'snmpv3_securitylevel' => '0',
							'snmpv3_authpassphrase' => '',
							'snmpv3_privpassphrase' => '',
							'uniq' => '0',
						),
					)
				)
			),
			// #2
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'proxy_hostid' => '0',
					'delay' => '3600',
					'status' => DRULE_STATUS_DISABLED,
					'dchecks' => array(
						array(
							'type' => SVC_FTP,
							'ports' => '10050',
						),
					)
				)
			),
		);
	}

	public static function providerCreateInvalidRules() {
		return array(
			array(
				// #0 empty rule
				array()
			),
				// #1 without name
			array(
				array(
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #2 without iprange
				array(
					'name' => 'api create',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #3 with negative delay
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '-10',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #4 with nonexistent proxyid
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'proxy_hostid' => '9999999',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #5 with status out of range
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '3600',
					'status' => '5',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #6 with two unique checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
							'uniq' => '1',
						),
						array(
							'type' => SVC_FTP,
							'uniq' => '1',
						),
					)
				)
			),
			array(
				// #7 with incorrect ip range
				array(
					'name' => 'api create',
					'iprange' => 'iprange',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
							'uniq' => '1',
						),
					)
				)
			),
			array(
				// #8 without checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
				)
			),
			array(
				// #9 with agent check incorrect item
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_AGENT,
							'key_' => ' ',
						),
					)
				)
			),
			array(
				// #10 with snmp check incorrect OID
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_SNMPv1,
							'snmp_community' => 'community',
							'key_' => '',
						),
					)
				)
			),
			array(
				// #11 with snmp check incorrect community
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_SNMPv1,
							'snmp_community' => '',
							'key_' => 'oid',
						),
					)
				)
			),
			array(
				// #12 with two identical checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
						),
						array(
							'type' => SVC_ICMPPING,
						),
					)
				)
			),
			array(
				// #13 with two identical checks with different "uniq"
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_AGENT,
							'key_' => 'key',
							'uniq' => 0,
						),
						array(
							'type' => SVC_AGENT,
							'key_' => 'key',
							'uniq' => 1,
						),
					)
				)
			),
		);
	}

	public static function providerUpdateValid() {
		return array(
			array(
				// #0 minimal required fields
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
						),
					)
				)
			),
			// #1
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'proxy_hostid' => '0',
					'delay' => '3600',
					'status' => DRULE_STATUS_DISABLED,
					'dchecks' => array(
						array(
							'type' => SVC_TCP,
							'key_' => 'system.uname',
							'snmp_community' => '0',
							'ports' => '10050',
							'snmpv3_securityname' => '',
							'snmpv3_securitylevel' => '0',
							'snmpv3_authpassphrase' => '',
							'snmpv3_privpassphrase' => '',
							'uniq' => '0',
						),
					)
				)
			),
		);
	}

	public static function providerUpdateInvalid() {
		return array(
			array(
				// #0 empty rule
				array()
			),
			// #1 with snmp check incorrect community
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_SNMPv1,
							'snmp_community' => '',
							'key_' => 'oid',
						),
					)
				)
			),
			array(
				// #2 without iprange
				array(
					'name' => 'api create',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #3 with negative delay
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '-10',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #4 with nonexistent proxyid
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'proxy_hostid' => '9999999',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #5 with status out of range
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '3600',
					'status' => '5',
					'dchecks' => array(array(
						'type' => SVC_ICMPPING,
					))
				)
			),
			array(
				// #6 with two unique checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
							'uniq' => '1',
						),
						array(
							'type' => SVC_FTP,
							'uniq' => '1',
						),
					)
				)
			),
			array(
				// #7 with incorrect ip range
				array(
					'name' => 'api create',
					'iprange' => 'iprange',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
							'uniq' => '1',
						),
					)
				)
			),
			array(
				// #8 without checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
				)
			),
			array(
				// #9 with agent check incorrect item
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_AGENT,
							'key_' => ' ',
						),
					)
				)
			),
			array(
				// #10 with snmp check incorrect OID
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_SNMPv1,
							'snmp_community' => 'community',
							'key_' => '',
						),
					)
				)
			),
			array(
				// #11 with two identical checks
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'dchecks' => array(
						array(
							'type' => SVC_ICMPPING,
						),
						array(
							'type' => SVC_ICMPPING,
						),
					)
				)
			),
		);
	}


	protected function createValidRules() {
		// create rules
		$validRules = self::providerCreateValidRules();
		$rulesCreate = array();
		foreach ($validRules as $validRule) {
			$rulesCreate[] = reset($validRule);
		}
		$druleids = self::$drule->create($rulesCreate);
		self::$createdRules = $druleids['druleids'];
		return self::$createdRules;
	}


	/**
	 * @dataProvider providerCreateValidRules
	 */
	public function testCreateValid($rule) {
		try {
			$result = self::$drule->create($rule);
			$this->assertArrayHasKey('druleids', $result, 'CDRule->create() result does not contain "druleids"');

			self::$drule->delete($result['druleids']);
		}
		catch (APIException $e) {
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * @dataProvider providerCreateInvalidRules
	 * @expectedException APIException
	 */
	public function testCreateInvalid($rule) {
		$result = self::$drule->create($rule);
		self::$drule->delete($result['druleids']);
	}

	/**
	 * @dataProvider providerUpdateValid
	 */
	public function testUpdateValid($rule) {
		try {
			$createdRuleids = $this->createValidRules();
			// update each created rule
			foreach ($createdRuleids as $ruleid) {
				$rule['druleid'] = $ruleid;
				$result = self::$drule->update($rule);
				$this->assertArrayHasKey('druleids', $result, 'CDRule->update() result does not contain "druleids"');
			}
		}
		catch (APIException $e) {
			// any exception should fail
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * @dataProvider providerUpdateInvalid
	 * @expectedException APIException
	 */
	public function testUpdateInvalid($rule) {
		try {
			$createdRuleids = $this->createValidRules();
		}
		catch (APIException $e) {
			$this->assertTrue(false, $e->getMessage());
		}

		// here exception should be thrown
		foreach ($createdRuleids as $ruleid) {
			$rule['druleid'] = $ruleid;
			$result = self::$drule->update($rule);
			$this->assertArrayHasKey('druleids', $result, 'CDRule->update() result does not contain "druleids"');
		}
	}

}
