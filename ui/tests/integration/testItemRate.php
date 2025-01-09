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
 * Test suite for items state change verification.
 *
 * @required-components server
 * @hosts test_host
 * @backup history
 */
class testItemRate extends CIntegrationTest {

	const HOST_NAME = 'test_host';
	const WAIT_TIME = 3;

	private static $hostid;
	private static $interfaceid;
	private static $values;

	private static $items = [
		[
			'key' => 'kuber_metric[0.1]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 1,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[0.3]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 2,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[0.5]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 4,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[0.7]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 8,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[0.9]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 16,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[+Inf]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 32,
			'count' => 120
		],
		[
			'key' => 'kuber_metric[Inf2]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 32,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,0.1]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 1,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,0.3]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 2,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,0.5]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 4,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,0.7]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 8,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,0.9]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 16,
			'count' => 120
		],
		[
			'key' => 'kuber_metric2[promparam,+Inf]',
			'value_type' => ITEM_VALUE_TYPE_FLOAT,
			'start' => 0,
			'step' => 32,
			'count' => 120
		]
	];

	private static $scenarios = [
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '1'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'rate[0.1]',
				'params' => 'rate(/'.'/kuber_metric[0.1],60)',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '2'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'rate[0.3]',
				'params' => 'rate(/'.'/kuber_metric[0.3],60)',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '32'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'rate[Inf]',
				'params' => 'rate(/'.'/kuber_metric[+Inf],60)',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '0.9'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'histogram_quantile',
				'params' => 'histogram_quantile(0.8, bucket_rate_foreach(/'.'/kuber_metric[*],60,1))',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '0.9'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'histogram_quantile2',
				'params' => 'histogram_quantile(0.8, bucket_rate_foreach(/'.'/kuber_metric2[promparam,*],60,2))',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '0.1'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'histogram_quantile-2rates',
				'params' => 'histogram_quantile(0.8,0.1,last(/'.'/rate[0.1]),"Inf",last(/'.'/rate[Inf]))',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '0.9'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'bucket_percentile',
				'params' => 'bucket_percentile(/'.'/kuber_metric[*],60,80)',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => [
				'output' => ['value']
			],
			'expected_result' => [
				[
					'value' => '32'
				]
			],
			'expected_error' => false,
			'item' => [
				'name' => 'rate[Inf2]',
				'params' => 'rate(/'.'/kuber_metric[Inf2],60)',
				'delay' => 1,
				'item_num' => 0
				]
		],
		[
			'api_request' => false,
			'expected_result' => false,
			'expected_error' => 'Cannot evaluate expression: invalid string values of bucket for function at "histogram_quantile(0.8,0.1,last(//rate[0.1]),"Inf2",last(//rate[Inf2]))"',
			'item' => [
				'name' => 'histogram_quantile-fail-Inf2',
				'params' => 'histogram_quantile(0.8,0.1,last(/'.'/rate[0.1]),"Inf2",last(/'.'/rate[Inf2]))',
				'delay' => 1,
				'item_num' => 0
			]
		],
		[
			'api_request' => false,
			'expected_result' => false,
			'expected_error' => 'Cannot evaluate expression: invalid last infinity rate buckets for function at "histogram_quantile(0.8,0.1,last(//rate[0.1]),0.3,last(//rate[0.3]))"',
			'item' => [
				'name' => 'histogram_quantile-fail-withoutInf',
				'params' => 'histogram_quantile(0.8,0.1,last(/'.'/rate[0.1]),0.3,last(/'.'/rate[0.3]))',
				'delay' => 1,
				'item_num' => 0
			]
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_host"
		$response = $this->call('host.create', [
			[
				'host' => self::HOST_NAME,
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

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create trapper item
		foreach (self::$items as &$item) {
			$items[] = [
				'name' => $item['key'],
				'key_' => $item['key'],
				'value_type' => $item['value_type'],
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$hostid
			];
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));
		$itemids = $response['result']['itemids'];
		$id = 0;
		$time_till = time() + 60;

		foreach (self::$items as &$item) {
			$item['itemid'] = $itemids[$id++];
			$item['time_till'] = $time_till;
		}

		self::$values = $this->createItemsData(self::HOST_NAME, self::$items);

		// Create calculated items
		foreach (self::$scenarios as &$scenario) {
			$response = $this->call('item.create', [
				[
					'name' => $scenario['item']['name'],
					'key_' => $scenario['item']['name'],
					'type' => ITEM_TYPE_CALCULATED,
					'params' => $scenario['item']['params'],
					'hostid' => self::$hostid,
					'delay' => $scenario['item']['delay'],
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				]
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			$this->assertEquals(1, count($response['result']['itemids']));

			if ($scenario['api_request'] === false) {
				$scenario['api_request'] = ['itemids' => []];
			}
			$scenario['api_request']['itemids'][] = $response['result']['itemids'][0];
			$scenario['api_request']['time_from'] = self::$items[$scenario['item']['item_num']]['time_from'];
			$scenario['api_request']['time_till'] = self::$items[$scenario['item']['item_num']]['time_till'];
		}
		return true;
	}

	/**
	 * Create array of Histogram counters values for single item with timestamp
	 *
	 * @param string  $host_name       host name
	 * @param array   $item            item
	 *
	 * @return array
	 */
	protected function createItemData($host_name, &$item) {
		$data = [];
		$value['host'] = $host_name;
		$time_from = $item['time_till'];
		for ($v = ($item['step'] * $item['count'] + $item['start']); $v > $item['start']; $v -= $item['step']) {
			$value['key'] = $item['key'];
			$value['value'] = $v;
			$value['clock'] = $time_from--;
			$value['ns'] = 0;
			$data[] = $value;
		}
		$item['time_from'] = $time_from;
		return $data;
	}


	/**
	 * Create array of Histogram counters values for all items with timestamp
	 *
	 * @param string  $host_name       host name
	 * @param array   $items           list of items
	 *
	 * @return array
	 */
	protected function createItemsData($host_name, &$items) {

		$data = [];
		foreach ($items as &$item) {
			$data = array_merge($data, $this->createItemData($host_name, $item));
		}
		return $data;
	}

	public static function history_get_data() {
		return self::$scenarios;
	}

	/**
	 * Send all values
	 */
	public function testItemRate_Send() {
		$this->sendSenderValues(self::$values);
		sleep(self::WAIT_TIME);
	}

	/**
	 * @dataProvider history_get_data
	 * @depends testItemRate_Send
	 */
	public function testItemRate_Get($api_request, $expected_result, $expected_error, $item) {

		$req = [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'sortorder' => 'DESC',
			'sortfield' => 'clock',
			'limit' => 1
		];

		foreach (self::$scenarios as $scenario) {
			if ($scenario['item'] != $item)
				continue;
			$api_request = array_merge($scenario['api_request'], $req);
			break;
		}

		$this->reloadConfigurationCache();

		if ($expected_error === false) {
			$result = $this->call('history.get', $api_request, $expected_error);
		} else {
			$result = $this->call('item.get',[
				'itemids' => $api_request['itemids'],
				'output' => ['error']
				]
			);
		}

		if ($expected_error === false) {
			$this->assertSame($result['result'], $expected_result);
		}
		else {
			$this->assertSame($result['result'][0]['error'], $expected_error);
		}
	}
}
