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
 * Test suite for history value storage.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_history_value
 * @backup history,history_uint,history_str,history_text,history_log,items
 */
class testHistoryValue extends CIntegrationTest {
	const HOSTNAME = 'test_history_value';

	private static $hostid;
	private static $items = [];

	public function prepareData() {
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$item_defs = [
			['key_' => 'trapper_float', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['key_' => 'trapper_float_second', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['key_' => 'trapper_uint', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['key_' => 'trapper_uint_second', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['key_' => 'trapper_str', 'value_type' => ITEM_VALUE_TYPE_STR],
			['key_' => 'trapper_str_second', 'value_type' => ITEM_VALUE_TYPE_STR],
			['key_' => 'trapper_text', 'value_type' => ITEM_VALUE_TYPE_TEXT],
			['key_' => 'trapper_text_second', 'value_type' => ITEM_VALUE_TYPE_TEXT],
			['key_' => 'trapper_log', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['key_' => 'trapper_log_second', 'value_type' => ITEM_VALUE_TYPE_LOG],
			['key_' => 'trapper_float_range', 'value_type' => ITEM_VALUE_TYPE_FLOAT]
		];

		foreach ($item_defs as $def) {
			$response = $this->call('item.create', [
				'hostid' => self::$hostid,
				'name' => $def['key_'],
				'key_' => $def['key_'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $def['value_type'],
				'preprocessing' => [
					[
						'type' => ZBX_PREPROC_TRIM,
						'params' => ' ',
						'error_handler' => ZBX_PREPROC_FAIL_DEFAULT,
						'error_handler_params' => ''
					]
				]
			]);
			$this->assertArrayHasKey('itemids', $response['result']);
			self::$items[$def['key_']] = [
				'itemid' => $response['result']['itemids'][0],
				'value_type' => $def['value_type']
			];
		}

		return true;
	}

	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'LogFileSize' => 20,
				'StartTrappers' => 1,
				'StartDBSyncers' => 1
			]
		];
	}

	public function testHistoryValue_sendAndRetrieve() {
		$tm = time();

		$cases = [
			'trapper_float' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 1.5, 'clock' => $tm, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 2.5, 'clock' => $tm + 1, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 3.5, 'clock' => $tm + 2, 'ns' => 1]
			],
			'trapper_uint' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 10, 'clock' => $tm, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 20, 'clock' => $tm + 1, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 30, 'clock' => $tm + 2, 'ns' => 1]
			],
			'trapper_str' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'alpha', 'clock' => $tm, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'beta', 'clock' => $tm + 1, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'gamma', 'clock' => $tm + 2, 'ns' => 1]
			],
			'trapper_text' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_a', 'clock' => $tm, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_b', 'clock' => $tm + 1, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_c', 'clock' => $tm + 2, 'ns' => 1]
			],
			'trapper_log' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_1', 'clock' => $tm, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_2', 'clock' => $tm + 1, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_3', 'clock' => $tm + 2, 'ns' => 1]
			]
		];

		foreach ($cases as $key => $values) {
			$this->sendDataValues('sender', $values, self::COMPONENT_SERVER);

			$item = self::$items[$key];
			$response = $this->callUntilDataIsPresent('history.get', [
				'history' => $item['value_type'],
				'itemids' => [$item['itemid']],
				'filter' => [
					'clock' => array_column($values, 'clock'),
					'ns' => array_column($values, 'ns')
				]
			], 5, 5);

			$this->assertEquals(count($values), count($response['result']));
		}

		return true;
	}

	public function testHistoryValue_sendBulkAndRetrieve() {
		$tm = time();

		$tm_val_first = $tm + 10;
		$tm_val_second = $tm + 11;

		$cases = [
			'trapper_float' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 4.0, 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 5.0, 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_float_second' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_float_second', 'value' => 6.0, 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_float_second', 'value' => 7.0, 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_uint' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 40, 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 50, 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_uint_second' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_uint_second', 'value' => 60, 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint_second', 'value' => 70, 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_str' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'delta', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'epsilon', 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_str_second' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_str_second', 'value' => 'zeta', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_str_second', 'value' => 'eta', 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_text' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_d', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_e', 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_text_second' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_text_second', 'value' => 'text_f', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_text_second', 'value' => 'text_g', 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_log' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_4', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_5', 'clock' => $tm_val_second, 'ns' => 1]
			],
			'trapper_log_second' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_log_second', 'value' => 'log_6', 'clock' => $tm_val_first, 'ns' => 1],
				['host' => self::HOSTNAME, 'key' => 'trapper_log_second', 'value' => 'log_7', 'clock' => $tm_val_second, 'ns' => 1]
			]
		];

		$this->sendDataValues('sender', array_merge(...array_values($cases)), self::COMPONENT_SERVER);

		$by_type = [];
		foreach ($cases as $key => $values) {
			$item = self::$items[$key];
			$type = $item['value_type'];
			$by_type[$type]['itemids'][] = $item['itemid'];

			if (!isset($by_type[$type]['count'])) {
				$by_type[$type]['count'] = 0;
			}
			$by_type[$type]['count'] += count($values);
		}

		foreach ($by_type as $value_type => $data) {
			$expected = $data['count'];
			$this->callUntilDataIsPresent('history.get', [
				'history' => $value_type,
				'itemids' => $data['itemids'],
				'time_from' => $tm_val_first,
				'time_till' => $tm_val_second
			], 5, 5, function($response) use ($expected) {
				return count($response['result']) === $expected;
			});
		}

		return true;
	}

	public function testHistoryValue_timeRangeCountOutput() {
		$tm = time();

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 3.45, 'clock' => $tm, 'ns' => 834726191],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 7.82, 'clock' => $tm + 60, 'ns' => 129483557],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 1.09, 'clock' => $tm + 120, 'ns' => 907315224],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 9.67, 'clock' => $tm + 180, 'ns' => 456192873],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 4.23, 'clock' => $tm + 240, 'ns' => 781034662],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 0.58, 'clock' => $tm + 300, 'ns' => 215908347],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 6.71, 'clock' => $tm + 360, 'ns' => 663920115],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 2.36, 'clock' => $tm + 420, 'ns' => 398471256],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 8.90, 'clock' => $tm + 480, 'ns' => 742159803],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_range', 'value' => 5.14, 'clock' => $tm + 540, 'ns' => 580237419]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER);

		$itemid = self::$items['trapper_float_range']['itemid'];
		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm + 60,
			'time_till' => $tm + 480
		], 5, 5, function($response) {
			return count($response['result']) === 8;
		});

		return true;
	}
}
