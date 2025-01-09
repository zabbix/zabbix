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
 * Test suite for alerting for services.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_history_value_duplicates
 * @backup history,items
 */
class testHistoryValueDuplicates extends CIntegrationTest {
	const HOSTNAME = 'test_history_value_duplicates';

	private static $hostid;
	private static $item_data;

	private function createTrapperItem($item, $type) {
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => $item,
			'key_' => $item,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => $type
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		return $response['result']['itemids'][0];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test_services_alerting"
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		self::$item_data = [
			[
				'item_name' => 'item_flt',
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'history_values' => [
					[
						['value' => 1.5, 'clock' => time(), 'ns' => 1],
						['value' => 1.7, 'clock' => time() + 1, 'ns' => 1],
						['value' => 1.5, 'clock' => time(), 'ns' => 1],
						['value' => 1.9, 'clock' => time() + 1, 'ns' => 2]
					],
					[
						['value' => 2.5, 'clock' => time(), 'ns' => 1],
						['value' => 2.7, 'clock' => time() + 1, 'ns' => 1],
						['value' => 2.5, 'clock' => time(), 'ns' => 1],
						['value' => 2.9, 'clock' => time() + 1, 'ns' => 2]
					]
				]
			],
			[
				'item_name' => 'item_uint',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'history_values' => [
					[
						['value' => 1, 'clock' => time(), 'ns' => 1],
						['value' => 2, 'clock' => time() + 1, 'ns' => 1],
						['value' => 3, 'clock' => time(), 'ns' => 1],
						['value' => 4, 'clock' => time() + 1, 'ns' => 2]
					],
					[
						['value' => 5, 'clock' => time(), 'ns' => 1],
						['value' => 6, 'clock' => time() + 1, 'ns' => 1],
						['value' => 7, 'clock' => time(), 'ns' => 1],
						['value' => 8, 'clock' => time() + 1, 'ns' => 2]
					]
				]
			],
			[
				'item_name' => 'item_str',
				'value_type' => ITEM_VALUE_TYPE_STR,
				'history_values' => [
					[
						['value' => 'b1', 'clock' => time(), 'ns' => 1],
						['value' => 'b2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'b3', 'clock' => time(), 'ns' => 1],
						['value' => 'b4', 'clock' => time() + 1, 'ns' => 2]
					],
					[
						['value' => 'a1', 'clock' => time(), 'ns' => 1],
						['value' => 'a2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'a3', 'clock' => time(), 'ns' => 1],
						['value' => 'a4', 'clock' => time() + 1, 'ns' => 2]
					]
				]
			],
			[
				'item_name' => 'item_text',
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'history_values' => [
					[
						['value' => 'b1', 'clock' => time(), 'ns' => 1],
						['value' => 'b2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'b3', 'clock' => time(), 'ns' => 1],
						['value' => 'b4', 'clock' => time() + 1, 'ns' => 2]
					],
					[
						['value' => 'a1', 'clock' => time(), 'ns' => 1],
						['value' => 'a2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'a3', 'clock' => time(), 'ns' => 1],
						['value' => 'a4', 'clock' => time() + 1, 'ns' => 2]
					]
				]
			],
			[
				'item_name' => 'item_log',
				'value_type' => ITEM_VALUE_TYPE_LOG,
				'history_values' => [
					[
						['value' => 'b1', 'clock' => time(), 'ns' => 1],
						['value' => 'b2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'b3', 'clock' => time(), 'ns' => 1],
						['value' => 'b4', 'clock' => time() + 1, 'ns' => 2]
					],
					[
						['value' => 'a1', 'clock' => time(), 'ns' => 1],
						['value' => 'a2', 'clock' => time() + 1, 'ns' => 1],
						['value' => 'a3', 'clock' => time(), 'ns' => 1],
						['value' => 'a4', 'clock' => time() + 1, 'ns' => 2]
					]
				]
			]
		];

		foreach (self::$item_data as &$i) {
			$itm1_key = $i['item_name'] . 1;
			$itm2_key = $i['item_name'] . 2;
			$itm1_id = $this->createTrapperItem($itm1_key, $i['value_type']);
			$itm2_id = $this->createTrapperItem($itm2_key, $i['value_type']);

			$i['items'] = [
				[
					'itemid' => $itm1_id,
					'key' => $itm1_key
				],
				[
					'itemid' => $itm2_id,
					'key' => $itm2_key
				]
			];
		}

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
				'LogFileSize' => 20,
				'StartTrappers' => 1,
				'StartDBSyncers' => 1
			]
		];
	}

	private function prepareSenderData(&$item_data, $data_idx) {
		$sender_data = $item_data['history_values'][$data_idx];

		foreach ($sender_data as &$v) {
			$v['host'] = self::HOSTNAME;
			$v['key'] = $item_data['items'][$data_idx]['key'];
		}

		return $sender_data;
	}

	public function testHistoryValueDuplicates_multipleValuesi() {
		foreach (self::$item_data as $d) {
			$sender_values1 = $this->prepareSenderData($d, 0);
			$sender_values2 = $this->prepareSenderData($d, 1);
			$this->sendDataValues('sender', $sender_values1, self::COMPONENT_SERVER);
			$this->sendDataValues('sender', $sender_values2, self::COMPONENT_SERVER);

			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, '[Z3008]', true, 5, 5);
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'skipped', true, 5, 5);

			$response = $this->callUntilDataIsPresent('history.get', [
				'history' => $d['value_type'],
				'itemids' => [
					$d['items'][0]['itemid'],
					$d['items'][1]['itemid']
				],
				'sortfield' => 'itemid'
			], 5, 5);
			$this->assertEquals(6, count($response['result']));
		}

		return true;
	}
}
