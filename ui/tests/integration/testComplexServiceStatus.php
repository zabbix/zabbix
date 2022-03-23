<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for alerting for services.
 *
 * @required-components server
 * @hosts test_complex_services
 * @backup history
 */
class testComplexServiceStatus extends CIntegrationTest {
	const HOSTNAME = 'test_complex_services';
	const ITEM_KEY = 'test_trapper';
	const SERVICENAME = 'Service 1';

	private static $hostid;
	private static $parent_serviceid;
	private static $itemid;
	private static $triggerid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_services_alerting"
		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			'groups' => ['groupid' => 4],
			'status' => HOST_STATUS_MONITORED
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Create trapper item
		$response = $this->call('item.create', [
			'name' => self::ITEM_KEY,
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_CALCULATED,
			'params' => '1',
			'hostid' => self::$hostid,
			'delay' => '1s',
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemid = $response['result']['itemids'][0];

		// Create service
		$response = $this->call('service.create', [
			'name' => 'Parent',
			'algorithm' => 1,
			'weight' => 0,
			'sortorder' => 0
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);
		self::$parent_serviceid = $response['result']['serviceids'][0];

		// Create triggers and corresponding services
		$triggers_services = [
			'average1' => TRIGGER_SEVERITY_AVERAGE,
			'average2' => TRIGGER_SEVERITY_AVERAGE,
			'high1' => TRIGGER_SEVERITY_HIGH,
			'high2' => TRIGGER_SEVERITY_HIGH,
			'high3' => TRIGGER_SEVERITY_HIGH,
			'warning1' => TRIGGER_SEVERITY_WARNING,
			'ok1' => TRIGGER_SEVERITY_WARNING,
			'ok2' => TRIGGER_SEVERITY_WARNING
		];

		foreach ($triggers_services as $desc => $severity) {
			$op = (substr($desc, 0, 2) == 'ok' ? '<>' : '=');
			$expr = 'last(/'.self::HOSTNAME.'/'.self::ITEM_KEY.')'.$op.'1';
			$trigger_desc = 'trigger_'.$desc;
			$service_desc = 'service_'.$desc;

			$response = $this->call('trigger.create', [
				'description' => $trigger_desc,
				'priority' => $severity,
				'status' => TRIGGER_STATUS_ENABLED,
				'type' => 0,
				'recovery_mode' => 0,
				'manual_close' => ZBX_TRIGGER_MANUAL_CLOSE_NOT_ALLOWED,
				'expression' => $expr
			]);

			$this->assertArrayHasKey('triggerids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['triggerids']);
			$triggerid = $response['result']['triggerids'][0];

			$response = $this->call('trigger.update', [
				'triggerid' => $triggerid,
				'tags' => [
					[
						'tag' => 'ServiceLink',
						'value' => $triggerid.':'.$trigger_desc
					]
				]
			]);

			$response = $this->call('service.create', [
				'name' => $service_desc,
				'algorithm' => 1,
				'weight' => 100,
				'sortorder' => 0,
				'parents' => [
					['serviceid' => self::$parent_serviceid]
				],
				'problem_tags' => [
					[
						'tag' => 'ServiceLink',
						'operator' => 0,
						'value' => $triggerid.':'.$trigger_desc
					]
				]
			]);
			$this->assertArrayHasKey('serviceids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['serviceids']);
		}

		return true;
	}

	/**
	 * Inherit the status from the most critical of child nodes
	 *
	 * @backup services
	 */
	public function testComplexServiceStatus_case1() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(TRIGGER_SEVERITY_HIGH, $response['result'][0]['status']);

		return true;
	}

	/**
	 * Additional rule with update of a parent status
	 *
	 * @backup services
	 */
	public function testComplexServiceStatus_case2() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE,
			'status_rules' => [
				[
					'type' => 0,
					'limit_value' => 2,
					'limit_status' => TRIGGER_SEVERITY_WARNING,
					'new_status' => TRIGGER_SEVERITY_DISASTER
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals(TRIGGER_SEVERITY_DISASTER, $response['result'][0]['status']);

		return true;
	}

	/**
	 * Most critical if all children have problems
	 */
	public function testComplexServiceStatus_case3() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($response['result'][0]['status'], -1);

		return true;
	}

	/**
	 * Set status to ok
	 */
	public function testComplexServiceStatus_case4() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($response['result'][0]['status'], -1);

		return true;
	}

	/**
	 * Set status of a parent service to OK, unless additional rules are met
	 *
	 * @backup services
	 */
	public function testComplexServiceStatus_case5() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_SET_OK
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'status_rules' => [
				[
					'type' => 0,
					'limit_value' => 25,
					'limit_status' => TRIGGER_SEVERITY_WARNING,
					'new_status' => TRIGGER_SEVERITY_AVERAGE
				],
				[
					'type' => 1,
					'limit_value' => 2,
					'limit_status' => TRIGGER_SEVERITY_WARNING,
					'new_status' => TRIGGER_SEVERITY_DISASTER
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($response['result'][0]['status'], TRIGGER_SEVERITY_DISASTER);

		return true;
	}

	/**
	 * Test propagations
	 *
	 * @backup services
	 */
	public function testComplexServiceStatus_case6() {
		$response = $this->callUntilDataIsPresent('service.get', [
			'search' => [
				'name' => ['service_warning1']
			]
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('serviceid', $response['result'][0]);
		$propagated_serviceid = $response['result'][0]['serviceid'];

		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'algorithm' => ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE
		]);

		$response = $this->call('service.update', [
			'serviceid' => $propagated_serviceid,
			'propagation_rule' => 1,
			'propagation_value' => TRIGGER_SEVERITY_DISASTER
		]);
		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($response['result'][0]['status'], TRIGGER_SEVERITY_DISASTER);

		return true;
	}

	/**
	 * Test weights
	 *
	 * @backup service_status_rule
	 *
	 */
	public function testComplexServiceStatus_case7() {
		$response = $this->call('service.update', [
			'serviceid' => self::$parent_serviceid,
			'status_rules' => [
				[
					'type' => 4,
					'limit_value' => 200,
					'limit_status' => -1,
					'new_status' => TRIGGER_SEVERITY_INFORMATION
				]
			]
		]);
		$this->assertArrayHasKey('serviceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['serviceids']);

		$this->reloadConfigurationCache();

		$response = $this->callUntilDataIsPresent('service.get', [
			'serviceids' => self::$parent_serviceid
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals($response['result'][0]['status'], TRIGGER_SEVERITY_INFORMATION);

		return true;
	}
}
