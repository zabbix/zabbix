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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once 'PHPUnit/Framework.php';

require_once(dirname(__FILE__).'/../../../include/defines.inc.php');
require_once(dirname(__FILE__).'/../../../include/validate.inc.php');
require_once(dirname(__FILE__).'/../../../include/func.inc.php');
require_once(dirname(__FILE__).'/../../../include/nodes.inc.php');
require_once(dirname(__FILE__).'/../../../conf/zabbix.conf.php');
require_once(dirname(__FILE__).'/../../../include/db.inc.php');
require_once(dirname(__FILE__).'/../../../include/copt.lib.php');
require_once(dirname(__FILE__).'/../../../include/items.inc.php');
require_once(dirname(__FILE__).'/../../../include/triggers.inc.php');


function error($error){
	echo "\nError reported: $error\n";
	return true;
}

class CDRuleTest extends PHPUnit_Framework_TestCase{
	private static $drule;

	public static function autoloadRegister($name){
		if(is_file(dirname(__FILE__).'/../../../api/classes/class.'.strtolower($name).'.php'))
			require_once(dirname(__FILE__).'/../../../api/classes/class.'.strtolower($name).'.php');
		else
			require_once(dirname(__FILE__).'/../../../include/classes/class.'.strtolower($name).'.php');
	}

	public static function setUpBeforeClass(){
		global $ZBX_CURRENT_NODEID;
		$ZBX_CURRENT_NODEID = 0;
		define('ZBX_DISTRIBUTED', false);
		spl_autoload_register('self::autoloadRegister');
		self::$drule = new CDRule();
	}

	public static function tearDownAfterClass(){
		spl_autoload_unregister('self::autoloadRegister');
	}

	public function setUp(){
		DBConnect($error);
	}

	public function tearDown(){
		DBclose();
	}


	public static function providerCreateValidRules(){
		return array(
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '3600',
					'proxy_hostid' => '0',
					'status' => '1',
					'dchecks' => array(
						array(
							'type' => '9',
							'key_' => 'system.uname',
							'snmp_community' => '0',
							'ports' => '10050',
							'snmpv3_securityname' => '',
							'snmpv3_securitylevel' => '0',
							'snmpv3_authpassphrase' => '',
							'snmpv3_privpassphrase' => '',
							'uniq' => '0',
						),
						array(
							'type' => '8',
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
			array(
				array(
					'name' => 'api create',
					'iprange' => '192.168.1.1-255',
					'delay' => '3600',
					'proxy_hostid' => '0',
					'status' => '1',
					'dchecks' => array(
						array(
							'type' => '9',
							'key_' => 'system.uname',
							'snmp_community' => '0',
							'ports' => '10050',
							'snmpv3_securityname' => '',
							'snmpv3_securitylevel' => '0',
							'snmpv3_authpassphrase' => '',
							'snmpv3_privpassphrase' => '',
							'uniq' => '0',
						),
						array(
							'type' => '8',
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

	public static function providerCreateInvalidRules(){
		return array(
			array(array(
				'name' => 'api create',
				'iprange' => '192.168.1.1-255',
				'delay' => '3600',
				'proxy_hostid' => '0',
				'status' => '1',
				'dchecks' => array(array(
					'type' => '8',
					'key_' => 'system.uname',
					'snmp_community' => '0',
					'ports' => '10050',
					'snmpv3_securityname' => '',
					'snmpv3_securitylevel' => '0',
					'snmpv3_authpassphrase' => '',
					'snmpv3_privpassphrase' => '',
					'uniq' => '0',
				),)
			),
			)
		);
	}

	public static function providerUpdateValid(){
		return array();
	}

	public static function providerUpdateInvalid(){
		return array();
	}


	/**
	 * @dataProvider providerCreateValidRules
	 */
	public function testCreateValid($rule){
		try{
			$result = self::$drule->create($rule);
			$this->assertArrayHasKey('druleids', $result, 'CDRule->create() result does not contain "druleids"');

			self::$drule->delete($result['druleids']);
		}
		catch(APIException $e){
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * @dataProvider providerCreateInvalidRules
	 * @expectedException APIException
	 */
	public function testCreateInvalid($rule){
		$this->markTestIncomplete();
		try{
		$result = self::$drule->create($rule);
		}
		catch(APIException $e){
//			print_r($e->getTrace());
		}
//		echo 5;
//		self::$drule->delete($result['druleids']);
	}

	/**
	 * @dataProvider providerUpdateValid
	 */
	public function testUpdateValid($rule){
		$this->markTestIncomplete();
		try{
			// create rules
			$rulesCreate = self::providerCreateValidRules();
			$createdRules = self::$drule->create($rulesCreate);

			// update each created rule
			foreach($createdRules['ruleids'] as $ruleid){
				$rule['ruleid'] = $ruleid;
				$result = self::$drule->update($rule);
				$this->assertArrayHasKey('druleids', $result, 'CDRule->update() result does not contain "druleids"');
			}

			// delete rules
			self::$drule->delete($createdRules['druleids']);
		}
		catch(APIException $e){
			// any exception should fail
			$this->assertTrue(false, $e->getMessage());
		}
	}

	/**
	 * @dataProvider providerUpdateInvalid
	 * @expectedException APIException
	 */
	public function testUpdateInvalid($rule){
		$this->markTestIncomplete();

		// create rules, fail if can't
		try{
			$rulesCreate = self::providerCreateValidRules();
			$createdRules = self::$drule->create($rulesCreate);
		}
		catch(APIException $e){
			$this->asssertTrue(false, $e->getMessage());
		}

		// here exception should be thrown
		foreach($createdRules['ruleids'] as $ruleid){
			$rule['ruleid'] = $ruleid;
			$result = self::$drule->update($rule);
			$this->assertArrayHasKey('druleids', $result, 'CDRule->update() result does not contain "druleids"');
		}

		// delete rules, fail if can't
		try{
			self::$drule->delete($createdRules['druleids']);
		}
		catch(APIException $e){
			$this->asssertTrue(false, $e->getMessage());
		}
	}

}
?>
