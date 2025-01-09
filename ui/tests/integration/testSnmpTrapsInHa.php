<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for High availability
 *
 * @backup ha_node, globalvars
 */
class testSnmpTrapsInHa extends CIntegrationTest {

	const NODE1_NAME = 'node1';
	const NODE2_NAME = 'node2';

	const TC1_TRAPFILE1 = 'ha1.trap';
	const TC1_TRAPFILE2 = 'ha2.trap';
	const TC2_TRAPFILE1 = 'ha3.trap';
	const TC2_TRAPFILE2 = 'ha4.trap';

	/**
	 * @required-components server, server_ha1
	 * @inheritdoc
	 */
	public function prepareData() {
		$socketDir = $this->getConfigurationValue(self::COMPONENT_SERVER_HANODE1, 'SocketDir');

		if (file_exists($socketDir) === false) {
			mkdir($socketDir);
		}

		foreach ([self::TC1_TRAPFILE1, self::TC1_TRAPFILE2, self::TC2_TRAPFILE1, self::TC2_TRAPFILE2] as $fn) {
			$this->assertTrue(copy('integration/data/snmptrap/'.$fn, '/tmp/'.$fn));
			$this->assertTrue(chmod('/tmp/'.$fn, 0644));
		}


		return true;
	}

	/**
	 * @return array
	 */
	public function serverConfigurationProvider_tc1() {
		return [
			self::COMPONENT_SERVER => [
				'HANodeName' => self::NODE1_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::SERVER_HANODE1_PORT_SUFFIX,
				'StartSNMPTrapper' => 1,
				'SNMPTrapperFile' => '/tmp/'.self::TC1_TRAPFILE1
			],
			self::COMPONENT_SERVER_HANODE1 => [
				'HANodeName' => self::NODE2_NAME,
				'NodeAddress' => 'localhost:'.
					self::getConfigurationValue(self::COMPONENT_SERVER_HANODE1, 'ListenPort'),
				'StartSNMPTrapper' => 1,
				'SNMPTrapperFile' => '/tmp/'.self::TC1_TRAPFILE2
			]
		];
	}

	/**
	 * @return array
	 */
	public function serverConfigurationProvider_tc2() {
		return [
			self::COMPONENT_SERVER => [
				'HANodeName' => self::NODE1_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::SERVER_HANODE1_PORT_SUFFIX,
				'StartSNMPTrapper' => 1,
				'SNMPTrapperFile' => '/tmp/'.self::TC2_TRAPFILE1
			],
			self::COMPONENT_SERVER_HANODE1 => [
				'HANodeName' => self::NODE2_NAME,
				'NodeAddress' => 'localhost:'.
					self::getConfigurationValue(self::COMPONENT_SERVER_HANODE1, 'ListenPort'),
				'StartSNMPTrapper' => 1,
				'SNMPTrapperFile' => '/tmp/'.self::TC2_TRAPFILE2
			]
		];
	}

	private function getTrapTs($filename) {
		$ha1_contents = file_get_contents($filename);
		$data = explode("\n", $ha1_contents);
		$this->assertNotFalse(preg_match_all('/([0-9]{4}-[0-9]{2}-[0-9T:\+]+).*PDU INFO/', $ha1_contents,
			$result));

		return $result[1];
	}

	/**
	 * Ensure that standby node correctly continues and reads trap with timestamp at 15:30:40
	 * and does not re-read records.
	 *
	 * @required-components server, server_ha1
	 * @backup globalvars
	 * @configurationDataProvider serverConfigurationProvider_tc1
	 */
	public function testSnmpTrapsInHa_tc1() {
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node started in "active" mode', true, 3, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node started in "standby" mode', true, 3, 3);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'PDU INFO', true, 30, 3);

		$ha1_timestamps = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER));
		$ha2_timestamps = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER_HANODE1));
		$this->assertCount(1, $ha1_timestamps);
		$this->assertEquals("2024-01-11T15:28:47+0200", $ha1_timestamps[0]);
		$this->assertCount(0, $ha2_timestamps);

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, 'PDU INFO', true, 30, 3);

		$ha2_ts = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER_HANODE1));
		$this->assertCount(1, $ha2_ts);
		$this->assertEquals("2024-01-11T15:30:40+0200", $ha2_ts[0]);

		return true;
	}

	/**
	 * Ensure that standby node correctly continues and reads trap with timestamp "2024-01-11T15:30:40+0200" and does not re-read records.
	 *
	 * @required-components server, server_ha1
	 * @configurationDataProvider serverConfigurationProvider_tc2
	 */
	public function testSnmpTrapsInHa_tc2() {
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '"'.self::NODE1_NAME.'" node started in "active" mode', true, 3, 3);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, '"'.self::NODE2_NAME.'" node started in "standby" mode', true, 3, 3);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '2024-01-11T15:28:47+0200 PDU INFO', true, 30, 3);

		$ha1_timestamps = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER));
		$ha2_timestamps = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER_HANODE1));
		$this->assertCount(3, $ha1_timestamps);
		$this->assertCount(0, $ha2_timestamps);

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER_HANODE1, 'PDU INFO', true, 30, 3);

		$ha2_ts = $this->getTrapTs(self::getLogPath(self::COMPONENT_SERVER_HANODE1));
		$this->assertCount(1, $ha2_ts);
		$this->assertEquals("2024-01-11T15:30:40+0200", $ha2_ts[0]);

		return true;
	}
}
