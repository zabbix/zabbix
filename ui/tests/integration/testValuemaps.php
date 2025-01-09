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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * Test suite for value mapping.
 *
 * @required-components server
 * @hosts test_valuemaps
 * @backup history
 */
class testValuemaps extends CIntegrationTest {
	const VALUEMAP_NAME = 'valuemap';
	const HOST_NAME = 'test_valuemaps';
	const ITEM_NAME = 'trap';

	private static $hostid;
	private static $itemid;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host HOST_NAME.
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];
		// create trapper
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_NAME,
			'key_' => self::ITEM_NAME,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_FLOAT
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemid = $response['result']['itemids'];

		return true;
	}

	/**
	 * Data provider (valuemaps).
	 *
	 * @return array
	 */
	public function getValuemaps() {
		$valuemap_patterns = [
			'exactMatch' => [
				'name' => self::VALUEMAP_NAME,
				'hostid' => null,
				'mappings' => [
					[
						'value' => '0',
						'newvalue' => 'Value 0'
					],
					[
						'value' => '1',
						'newvalue' => 'Value 1'
					]
				]
			],
			'rangeWithoutDefault' => [
				'name' => self::VALUEMAP_NAME,
				'hostid' => null,
				'mappings' => [
					[
						'value' => '0',
						'newvalue' => 'Value 0'
					],
					[
						'value' => '^1$',
						'newvalue' => 'Regexp 1',
						'type' => VALUEMAP_MAPPING_TYPE_REGEXP
					],
					[
						'value' => '3',
						'newvalue' => 'Value <= 3',
						'type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL
					],
					[
						'value' => '10',
						'newvalue' => 'Value >= 10',
						'type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL
					],
					[
						'value' => '5-7,8',
						'newvalue' => 'Range 5-7,8',
						'type' => VALUEMAP_MAPPING_TYPE_IN_RANGE
					]
				]
			],
			'rangeWithDefault' => [
				'name' => self::VALUEMAP_NAME,
				'hostid' => null,
				'mappings' => [
					[
						'value' => '-1.2e-1K--3e1, -10, -7--5, -1-1, 5-7.8K',
						'newvalue' => 'Range',
						'type' => VALUEMAP_MAPPING_TYPE_IN_RANGE
					],
					[
						'newvalue' => 'Default',
						'type' => VALUEMAP_MAPPING_TYPE_DEFAULT
					]
				]
			]
		];

		return [
			[
				'inputData' => '1',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['exactMatch'],
				'outputData' => 'Value 1 (1)'
			],
			[
				'inputData' => '0',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['exactMatch'],
				'outputData' => 'Value 0 (0)'
			],
			[
				'inputData' => '2',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['exactMatch'],
				'outputData' => '2'
			],
			[
				'inputData' => '0',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Value 0 (0)'
			],
			[
				'inputData' => '1',
				'inputType'=> ITEM_VALUE_TYPE_STR,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Regexp 1 (1)'
			],
			[
				'inputData' => '3',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Value <= 3 (3)'
			],
			[
				'inputData' => '5',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Range 5-7,8 (5)'
			],
			[
				'inputData' => '8',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Range 5-7,8 (8)'
			],
			[
				'inputData' => '9',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => '9'
			],
			[
				'inputData' => '10',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithoutDefault'],
				'outputData' => 'Value >= 10 (10)'
			],
			[
				'inputData' => '-123',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Default (-123)'
			],
			[
				'inputData' => '-122.88',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Range (-122.88)'
			],
			[
				'inputData' => '-30',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Range (-30)'
			],
			[
				'inputData' => '0',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Range (0)'
			],
			[
				'inputData' => '7987.2',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Range (7987.2)'
			],
			[
				'inputData' => '7988',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Default (7988)'
			],
			[
				'inputData' => '4',
				'inputType'=> ITEM_VALUE_TYPE_FLOAT,
				'valuemap' => $valuemap_patterns['rangeWithDefault'],
				'outputData' => 'Default (4)'
			]
		];
	}

	/**
	 * Test valuemaps cases.
	 *
	 * @dataProvider getValuemaps
	 */
	public function testValuemaps_checkProblemName($inputData, $inputType, $valuemap, $outputData) {
		$valuemap['hostid'] = self::$hostid;
		$response = $this->call('valuemap.create', $valuemap);
		$this->assertArrayHasKey('valuemapids', $response['result']);
		$this->assertEquals(1, count($response['result']['valuemapids']));
		$valuemapid = $response['result']['valuemapids'];

		$response = $this->call('item.update', [
				'itemid' => self::$itemid[0],
				'valuemapid' => $valuemapid[0],
				'value_type' => $inputType
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$this->assertEquals(self::$itemid, $response['result']['itemids']);

		$response = $this->call('trigger.create', [
			'description' => ' {ITEM.VALUE}',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::ITEM_NAME.')='.$inputData
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		$triggerid =  $response['result']['triggerids'];

		$this->reloadConfigurationCache();

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_NAME, $inputData);

		['result' => $result] = $this->call('problem.get', [
			'output' => ['name'],
			'objectids' => $triggerid
		]);

		$result = array_column($result, 'name');
		$this->assertEquals(' '.$outputData, $result[0]);

		$response = $this->call('trigger.delete', $triggerid);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals($triggerid, $response['result']['triggerids']);

		$response = $this->call('valuemap.delete', $valuemapid);
		$this->assertArrayHasKey('valuemapids', $response['result']);
		$this->assertEquals($valuemapid, $response['result']['valuemapids']);
	}
}
