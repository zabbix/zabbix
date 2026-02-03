<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Test suite for testing dependent items
 *
 * @required-components server, agent
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_host
 * @backup history
 */
class testItemDependent extends CIntegrationTest {

	const ITEM_FILE_NAME = '/tmp/test.json';

	private static $hostid;
	private static $interfaceid;

	private static $master_itemid;
	private static $dep_itemid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create the host
		$response = $this->call('host.create', [
			[
				'host' => 'test_host',
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_NOT_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		// Check host interface ids
		$response = $this->call('host.get', [
			'output'           => ['host'],
			'hostids'          => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create master item
		$response = $this->call('item.create', [
			'hostid'     => self::$hostid,
			'name'       => 'Master item',
			'key_'       => 'vfs.file.contents['.self::ITEM_FILE_NAME.']',
			'type'       => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_STR,
			'delay'      => '1s',
			'status' => ITEM_STATUS_DISABLED
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$master_itemid = $response['result']['itemids'][0];

		// Create dependent item
		$response = $this->call('item.create', [
			'hostid'        => self::$hostid,
			'master_itemid' => self::$master_itemid,
			'name'          => 'Dependent item',
			'key_'          => 'dependent_item',
			'type'          => ITEM_TYPE_DEPENDENT,
			'value_type'    => ITEM_VALUE_TYPE_STR,
			'preprocessing' => [
				[
					'type'          => ZBX_PREPROC_ERROR_FIELD_JSON,
					'params'        => '$.b',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		self::$dep_itemid = $response['result']['itemids'][0];

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 20,
				'ListenPort' => self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051)
			],
			self::COMPONENT_AGENT => [
				'DebugLevel' => 5,
				'Hostname' => 'test_host',
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051),
				'BufferSend' => 1
			]
		];
	}

	protected function validateItems($itemid, $state, $lastvalue, $error) {
		$response = $this->call('item.get', [
			'itemids' => $itemid,
			'output' => ['error', 'state', 'lastvalue']
		]);

		$this->assertArrayHasKey(0, $response['result']);

		$this->assertArrayHasKey('state', $response['result'][0]);
		$this->assertEquals($state, $response['result'][0]['state']);

		$this->assertArrayHasKey('lastvalue', $response['result'][0]);
		$this->assertEquals($lastvalue, $response['result'][0]['lastvalue']);

		$this->assertArrayHasKey('error', $response['result'][0]);
		if (!is_bool($error)) {
			$this->assertEquals($error, $response['result'][0]['error']);
		} else {
			$this->assertTrue(!empty($response['result'][0]['error']));
		}
	}

	protected function updateDataAndWait($data) {
		if ($data === null) {
			$this->assertTrue(@unlink(self::ITEM_FILE_NAME) !== false);
		} else {
			$this->assertTrue(@file_put_contents(self::ITEM_FILE_NAME, $data) !== false);
		}
		$this->clearLog(self::COMPONENT_SERVER);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
				[',"data":[{"itemid":'.self::$master_itemid.',"value":"']
		);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER,
				['In preproc_flush_value_server() itemid:'.self::$dep_itemid]
		);
	}

	/**
	 * Test if error message are propagated to dependent item correctly
	 *
	 * @required-components server, agent
	 */
	public function testItemDependent_checkErrorPropagation() {
		if (file_exists(self::ITEM_FILE_NAME)) {
			$this->assertTrue(@unlink(self::ITEM_FILE_NAME) !== false);
		}

		$response = $this->call('item.update', [
			'itemid' => self::$master_itemid,
			'status' => ITEM_STATUS_ACTIVE,
			'preprocessing' => [
				[
					'type'                 => ZBX_PREPROC_JSONPATH,
					'params'               => '$.a',
					'error_handler'        => ZBX_PREPROC_FAIL_SET_ERROR,
					'error_handler_params' => 'ERROR'
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('item.update', [
			'itemid' => self::$dep_itemid,
			'status' => ITEM_STATUS_ACTIVE,
			'preprocessing' => [
				[
					'type'          => ZBX_PREPROC_JSONPATH,
					'params'        => '$.sub',
					'error_handler' => ZBX_PREPROC_FAIL_DISCARD_VALUE
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "finished forced reloading of the configuration cache", true, 60, 1);

		// Error in master preprocessing should be propagated to dependent item
		$this->updateDataAndWait('{"b":{}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NOTSUPPORTED, '', 'ERROR');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NOTSUPPORTED, '', 'ERROR');

		// Dependent drops the value
		$this->updateDataAndWait('{"a":{}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NORMAL, '{}', '');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NORMAL, '', '');

		// Both items are supported and has value
		$this->updateDataAndWait('{"a":{"sub":"sub_value"}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NORMAL, '{"sub":"sub_value"}', '');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NORMAL, 'sub_value', '');

		// Error in master should propagate to dependent
		$this->updateDataAndWait('{"b":{}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NOTSUPPORTED, '{"sub":"sub_value"}', 'ERROR');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NOTSUPPORTED, 'sub_value', 'ERROR');

		// Dependent should drop the old error
		$this->updateDataAndWait('{"a":{}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NORMAL, '{}', '');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NORMAL, 'sub_value', '');
	}

	/**
	 * Test if dependent item behaves properly after master item becomes unsupported and dependent need to clear
	 * error
	 *
	 * @required-components server, agent
	 */
	public function testItemDependent_checkUnsupported() {
		if (file_exists(self::ITEM_FILE_NAME)) {
			$this->assertTrue(@unlink(self::ITEM_FILE_NAME) !== false);
		}

		$response = $this->call('item.update', [
			'itemid' => self::$master_itemid,
			'status' => ITEM_STATUS_ACTIVE,
			'preprocessing' => [
				[
					'type'                 => ZBX_PREPROC_VALIDATE_NOT_SUPPORTED,
					'params'               => '-1',
					'error_handler'        => ZBX_PREPROC_FAIL_DISCARD_VALUE
				],
				[
					'type'                 => ZBX_PREPROC_JSONPATH,
					'params'               => '$.a',
					'error_handler'        => ZBX_PREPROC_FAIL_SET_ERROR,
					'error_handler_params' => 'MASTER_PREPROC_ERROR'
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('item.update', [
			'itemid' => self::$dep_itemid,
			'status' => ITEM_STATUS_ACTIVE,
			'preprocessing' => [
				[
					'type'          => ZBX_PREPROC_ERROR_FIELD_JSON,
					'params'        => '$.b',
					'error_handler' => ZBX_PREPROC_FAIL_DEFAULT
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "finished forced reloading of the configuration cache", true, 60, 1);

		// Set both items to supported
		$this->updateDataAndWait('{"a":{"b":{}}}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NORMAL, '{"b":{}}', '');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NOTSUPPORTED, '', true);

		// Propagate error coming from master item preprocessing
		$this->updateDataAndWait('{}');

		$this->validateItems(self::$master_itemid, ITEM_STATE_NOTSUPPORTED, '{"b":{}}', 'MASTER_PREPROC_ERROR');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NOTSUPPORTED, '', 'MASTER_PREPROC_ERROR');

		$this->updateDataAndWait(null);

		$this->validateItems(self::$master_itemid, ITEM_STATE_NORMAL, '{"b":{}}', '');
		$this->validateItems(self::$dep_itemid, ITEM_STATE_NORMAL, '', '');

		// ZBX-27406: propagation of no data to clear error should not go through preprocessing steps
		$this->assertFalse($this->isLogLinePresent(self::COMPONENT_SERVER, '=== Backtrace: ===', false));
	}
}
