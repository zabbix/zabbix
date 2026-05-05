<?php declare(strict_types = 1);
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
 * Test suite for history.get on different backends
 *
 * @required-components server
 * @suite-components-reuse true
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_history_value
 * @onAfterOnce clearData
 */
class testHistoryGet extends CIntegrationTest {
	const HOSTNAME = 'test_history_value';

	private static $hostid;
	private static $items = [];

	public function prepareData() {
		$response = $this->call('hostgroup.get', [
			'filter' => ['name' => ['Zabbix servers']]
		]);
		$this->assertNotEmpty($response['result'], 'Host group "Zabbix servers" not found');
		$groupid = $response['result'][0]['groupid'];

		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [],
				'groups' => [['groupid' => $groupid]],
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
			['key_' => 'trapper_float_range', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['key_' => 'trapper_float_sort', 'value_type' => ITEM_VALUE_TYPE_FLOAT],
			['key_' => 'trapper_uint_count', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['key_' => 'trapper_str_search', 'value_type' => ITEM_VALUE_TYPE_STR],
			['key_' => 'trapper_str_startsearch', 'value_type' => ITEM_VALUE_TYPE_STR],
			['key_' => 'trapper_str_wildcard', 'value_type' => ITEM_VALUE_TYPE_STR],
			['key_' => 'trapper_uint_filter', 'value_type' => ITEM_VALUE_TYPE_UINT64],
			['key_' => 'trapper_text_search', 'value_type' => ITEM_VALUE_TYPE_TEXT],
			['key_' => 'trapper_float_ns_sort', 'value_type' => ITEM_VALUE_TYPE_FLOAT]
		];

		$item_params = [];
		foreach ($item_defs as $def) {
			$item_params[] = [
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
			];
		}

		$response = $this->call('item.create', $item_params);
		$this->assertArrayHasKey('itemids', $response['result']);

		foreach ($item_defs as $i => $def) {
			self::$items[$def['key_']] = [
				'itemid' => $response['result']['itemids'][$i],
				'value_type' => $def['value_type']
			];
		}

		return true;
	}

	public static function clearData(): void {
		if (self::$hostid !== null) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}

		self::$hostid = null;
	}

	private function timeMonotonic(): int {
		static $last = 0;
		$now = time();
		if ($now <= $last) {
			$now = $last + 1;
		}
		$last = $now;
		return $now;
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
		$tm = $this->timeMonotonic();

		$cases = [
			'trapper_float' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 1.5, 'clock' => $tm, 'ns' => 0],
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 2.5, 'clock' => $tm + 1, 'ns' => 500000000],
				['host' => self::HOSTNAME, 'key' => 'trapper_float', 'value' => 3.5, 'clock' => $tm + 2, 'ns' => 999999999]
			],
			'trapper_uint' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 10, 'clock' => $tm, 'ns' => 0],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 20, 'clock' => $tm + 1, 'ns' => 500000000],
				['host' => self::HOSTNAME, 'key' => 'trapper_uint', 'value' => 30, 'clock' => $tm + 2, 'ns' => 999999999]
			],
			'trapper_str' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'alpha', 'clock' => $tm, 'ns' => 0],
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'beta', 'clock' => $tm + 1, 'ns' => 500000000],
				['host' => self::HOSTNAME, 'key' => 'trapper_str', 'value' => 'gamma', 'clock' => $tm + 2, 'ns' => 999999999]
			],
			'trapper_text' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_a', 'clock' => $tm, 'ns' => 0],
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_b', 'clock' => $tm + 1, 'ns' => 500000000],
				['host' => self::HOSTNAME, 'key' => 'trapper_text', 'value' => 'text_c', 'clock' => $tm + 2, 'ns' => 999999999]
			],
			'trapper_log' => [
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_1', 'clock' => $tm, 'ns' => 0],
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_2', 'clock' => $tm + 1, 'ns' => 500000000],
				['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_3', 'clock' => $tm + 2, 'ns' => 999999999]
			]
		];

		$this->sendDataValues('sender', array_merge(...array_values($cases)), self::COMPONENT_SERVER, 0);

		foreach ($cases as $key => $values) {
			$item = self::$items[$key];
			$this->callUntilDataIsPresent('history.get', [
				'history' => $item['value_type'],
				'itemids' => [$item['itemid']],
				'filter' => [
					'clock' => array_column($values, 'clock'),
					'ns' => array_column($values, 'ns')
				]
			], 5, 5, function($response) use ($values) {
				return count($response['result']) === count($values);
			});
		}

		return true;
	}

	public function testHistoryValue_sendBulkAndRetrieve() {
		$tm = $this->timeMonotonic();

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

		$this->sendDataValues('sender', array_merge(...array_values($cases)), self::COMPONENT_SERVER, 0);

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
		$tm = $this->timeMonotonic();

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

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

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

	public function testHistoryValue_sortAndLimit() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_float_sort']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 10.0, 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 20.0, 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 30.0, 'clock' => $tm + 2, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 40.0, 'clock' => $tm + 3, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 50.0, 'clock' => $tm + 4, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 4,
			'sortfield' => 'clock',
			'sortorder' => 'ASC'
		], 5, 5, function($response) {
			return count($response['result']) === 5;
		});

		$result = $response['result'];
		for ($i = 0; $i < count($result) - 1; $i++) {
			$this->assertLessThanOrEqual((int)$result[$i + 1]['clock'], (int)$result[$i]['clock']);
		}

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 4,
			'sortfield' => 'clock',
			'sortorder' => 'DESC'
		]);
		$result = $response['result'];
		for ($i = 0; $i < count($result) - 1; $i++) {
			$this->assertGreaterThanOrEqual((int)$result[$i + 1]['clock'], (int)$result[$i]['clock']);
		}

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 4,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 2
		]);
		$this->assertCount(2, $response['result']);
		$this->assertEquals($tm + 4, (int)$response['result'][0]['clock']);

		return true;
	}

	public function testHistoryValue_countOutput() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_uint_count']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_count', 'value' => 100, 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_count', 'value' => 200, 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_count', 'value' => 300, 'clock' => $tm + 2, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_count', 'value' => 400, 'clock' => $tm + 3, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 3
		], 5, 5, function($response) {
			return count($response['result']) === 4;
		});

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 3,
			'countOutput' => true
		]);
		$this->assertEquals('4', $response['result']);

		return true;
	}

	public function testHistoryValue_search() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_str_search']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'match_alpha', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'match_beta', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'other_gamma', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'match_']
		]);
		$this->assertCount(2, $response['result']);

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'match_'],
			'excludeSearch' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('other_gamma', $response['result'][0]['value']);

		return true;
	}

	public function testHistoryValue_caseInsensitiveSearch() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_str_search']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'CaseAlpha', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'CASEBETA', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'other', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'output' => ['itemid']
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// Uppercase search term matches both values containing 'case' regardless of stored case
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'CASE'],
			'output' => ['itemid']
		]);
		$this->assertCount(2, $response['result']);

		// Lowercase search term matches the all-uppercase stored value
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'casebeta'],
			'output' => ['itemid']
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('CASEBETA', $response['result'][0]['value']);

		// startSearch is also case-insensitive
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'case'],
			'startSearch' => true,
			'output' => ['itemid']
		]);
		$this->assertCount(2, $response['result']);

		return true;
	}

	public function testHistoryValue_startSearch() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_str_startsearch']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_str_startsearch', 'value' => 'alpha_start', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_startsearch', 'value' => 'end_alpha', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_startsearch', 'value' => 'other', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// Default substring match: both 'alpha_start' and 'end_alpha' contain 'alpha'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'alpha']
		]);
		$this->assertCount(2, $response['result']);

		// startSearch: only 'alpha_start' has 'alpha' as a prefix
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'alpha'],
			'startSearch' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('alpha_start', $response['result'][0]['value']);

		// startSearch + excludeSearch: everything except values starting with 'alpha'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'alpha'],
			'startSearch' => true,
			'excludeSearch' => true
		]);
		$this->assertCount(2, $response['result']);

		return true;
	}

	public function testHistoryValue_searchWildcardsEnabled() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_str_wildcard']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_str_wildcard', 'value' => 'abc123', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_wildcard', 'value' => 'abc456', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_wildcard', 'value' => 'xyz123', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// No wildcard support: literal '*' is not present in any value
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'abc*']
		]);
		$this->assertCount(0, $response['result']);

		// 'abc*' matches 'abc123' and 'abc456'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'abc*'],
			'searchWildcardsEnabled' => true
		]);
		$this->assertCount(2, $response['result']);

		// '*123' matches 'abc123' and 'xyz123'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => '*123'],
			'searchWildcardsEnabled' => true
		]);
		$this->assertCount(2, $response['result']);

		// 'abc*' + excludeSearch: only 'xyz123'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'abc*'],
			'searchWildcardsEnabled' => true,
			'excludeSearch' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('xyz123', $response['result'][0]['value']);

		return true;
	}

	public function testHistoryValue_filterValue() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_uint_filter']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_filter', 'value' => 100, 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_filter', 'value' => 200, 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_uint_filter', 'value' => 300, 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// Exact match on a single value
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'filter' => ['value' => '200']
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('200', $response['result'][0]['value']);

		// Exact match on multiple values
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'filter' => ['value' => ['100', '300']]
		]);
		$this->assertCount(2, $response['result']);

		// filter + countOutput
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'filter' => ['value' => ['100', '300']],
			'countOutput' => true
		]);
		$this->assertEquals('2', $response['result']);

		return true;
	}

	public function testHistoryValue_textSearch() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_text_search']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_text_search', 'value' => 'contains alpha keyword', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_text_search', 'value' => 'contains beta keyword', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_text_search', 'value' => 'no match here', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// Substring match on text value
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'keyword']
		]);
		$this->assertCount(2, $response['result']);

		// excludeSearch: only the non-matching record
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'keyword'],
			'excludeSearch' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('no match here', $response['result'][0]['value']);

		// startSearch on text: only 'contains alpha keyword' starts with 'contains alpha'
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'contains alpha'],
			'startSearch' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertEquals('contains alpha keyword', $response['result'][0]['value']);

		// countOutput + search on text
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_TEXT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'keyword'],
			'countOutput' => true
		]);
		$this->assertEquals('2', $response['result']);

		return true;
	}

	public function testHistoryValue_countOutputWithSearch() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_str_search']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'cnt_match_one', 'clock' => $tm, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'cnt_match_two', 'clock' => $tm + 1, 'ns' => 0],
			['host' => self::HOSTNAME, 'key' => 'trapper_str_search', 'value' => 'cnt_other', 'clock' => $tm + 2, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		// countOutput combined with search
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'cnt_match'],
			'countOutput' => true
		]);
		$this->assertEquals('2', $response['result']);

		// countOutput + excludeSearch
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'cnt_match'],
			'excludeSearch' => true,
			'countOutput' => true
		]);
		$this->assertEquals('1', $response['result']);

		// countOutput + startSearch
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_STR,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm + 2,
			'search' => ['value' => 'cnt_match'],
			'startSearch' => true,
			'countOutput' => true
		]);
		$this->assertEquals('2', $response['result']);

		return true;
	}

	public function testHistoryValue_sortByNs() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_float_ns_sort']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_float_ns_sort', 'value' => 1.0, 'clock' => $tm, 'ns' => 300],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_ns_sort', 'value' => 2.0, 'clock' => $tm, 'ns' => 100],
			['host' => self::HOSTNAME, 'key' => 'trapper_float_ns_sort', 'value' => 3.0, 'clock' => $tm, 'ns' => 200]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm,
			'sortfield' => 'ns',
			'sortorder' => 'ASC'
		], 5, 5, function($response) {
			return count($response['result']) === 3;
		});

		$result = $response['result'];
		$this->assertEquals(100, (int)$result[0]['ns']);
		$this->assertEquals(200, (int)$result[1]['ns']);
		$this->assertEquals(300, (int)$result[2]['ns']);

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm,
			'sortfield' => 'ns',
			'sortorder' => 'DESC'
		]);
		$result = $response['result'];
		$this->assertEquals(300, (int)$result[0]['ns']);
		$this->assertEquals(200, (int)$result[1]['ns']);
		$this->assertEquals(100, (int)$result[2]['ns']);

		// sort by ns ASC with limit
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm,
			'time_till' => $tm,
			'sortfield' => 'ns',
			'sortorder' => 'ASC',
			'limit' => 2
		]);
		$this->assertCount(2, $response['result']);
		$this->assertEquals(100, (int)$response['result'][0]['ns']);
		$this->assertEquals(200, (int)$response['result'][1]['ns']);

		return true;
	}

	public function testHistoryValue_logOutputFields() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_log']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_log', 'value' => 'log_field_test', 'clock' => $tm + 600, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		// Default output includes all log-specific fields
		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_LOG,
			'itemids' => [$itemid],
			'time_from' => $tm + 600,
			'time_till' => $tm + 600
		], 5, 5, function($response) {
			return count($response['result']) === 1;
		});

		$record = $response['result'][0];
		$this->assertArrayHasKey('itemid', $record);
		$this->assertArrayHasKey('clock', $record);
		$this->assertArrayHasKey('timestamp', $record);
		$this->assertArrayHasKey('source', $record);
		$this->assertArrayHasKey('severity', $record);
		$this->assertArrayHasKey('value', $record);
		$this->assertArrayHasKey('logeventid', $record);
		$this->assertArrayHasKey('ns', $record);
		$this->assertEquals('log_field_test', $record['value']);

		// Selective output for log-specific fields
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_LOG,
			'itemids' => [$itemid],
			'time_from' => $tm + 600,
			'time_till' => $tm + 600,
			'output' => ['itemid', 'value', 'source', 'severity']
		]);
		$this->assertCount(1, $response['result']);
		$record = $response['result'][0];
		$this->assertArrayHasKey('itemid', $record);
		$this->assertArrayHasKey('value', $record);
		$this->assertArrayHasKey('source', $record);
		$this->assertArrayHasKey('severity', $record);
		$this->assertArrayNotHasKey('clock', $record);
		$this->assertArrayNotHasKey('ns', $record);
		$this->assertArrayNotHasKey('timestamp', $record);
		$this->assertArrayNotHasKey('logeventid', $record);

		// search on log value
		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_LOG,
			'itemids' => [$itemid],
			'time_from' => $tm + 600,
			'time_till' => $tm + 600,
			'search' => ['value' => 'log_field']
		]);
		$this->assertCount(1, $response['result']);

		$response = $this->call('history.get', [
			'history' => ITEM_VALUE_TYPE_LOG,
			'itemids' => [$itemid],
			'time_from' => $tm + 600,
			'time_till' => $tm + 600,
			'search' => ['value' => 'log_field'],
			'excludeSearch' => true
		]);
		$this->assertCount(0, $response['result']);

		return true;
	}

	public function testHistoryValue_outputFields() {
		$tm = $this->timeMonotonic();
		$itemid = self::$items['trapper_float_sort']['itemid'];

		$values = [
			['host' => self::HOSTNAME, 'key' => 'trapper_float_sort', 'value' => 99.9, 'clock' => $tm + 100, 'ns' => 0]
		];

		$this->sendDataValues('sender', $values, self::COMPONENT_SERVER, 0);

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_FLOAT,
			'itemids' => [$itemid],
			'time_from' => $tm + 100,
			'time_till' => $tm + 100,
			'output' => ['itemid', 'value']
		], 5, 5, function($response) {
			return count($response['result']) === 1;
		});

		$record = $response['result'][0];
		$this->assertArrayHasKey('itemid', $record);
		$this->assertArrayHasKey('value', $record);
		$this->assertArrayNotHasKey('clock', $record);
		$this->assertArrayNotHasKey('ns', $record);

		return true;
	}
}
