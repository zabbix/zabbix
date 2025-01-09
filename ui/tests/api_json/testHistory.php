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


require_once dirname(__FILE__).'/../include/CAPITest.php';


/**
 * @backup history_bin
 */
class testHistory extends CAPITest {

	public static function history_get_data() {
		$binary_itemid = 58740;

		return [
			// Test item history of value_type == ITEM_VALUE_TYPE_STR ('history' => 1).
			[
				'api_request' => [
					'output' => 'extend',
					'history' => 1,
					'itemids' => ['133760']
				],
				'expected_result' => [
					[
						'itemid' => '133760',
						'clock' => '1549350960',
						'value' => '1',
						'ns' => '754460948'
					],
					[
						'itemid' => '133760',
						'clock' => '1549350962',
						'value' => '1',
						'ns' => '919404393'
					],
					[
						'itemid' => '133760',
						'clock' => '1549350965',
						'value' => '1',
						'ns' => '512878374'
					]
				],
				'expected_error' => false
			],
			// Test item history of value_type == ITEM_VALUE_TYPE_LOG ('history' => 2).
			[
				'api_request' => [
					'output' => ['value', 'severity'],
					'history' => 2,
					'itemids' => ['133761']
				],
				'expected_result' => [
					[
						'value' => '1',
						'severity' =>  '0'
					],
					[
						'value' => '2',
						'severity' => '0'
					],
					[
						'value' => '3',
						'severity' => '0'
					],
					[
						'value' => '4',
						'severity' => '0'
					],
					[
						'value' => '5',
						'severity' => '0'
					]
				],
				'expected_error' => false
			],
			// Get last 5 values of item of value_type == ITEM_VALUE_TYPE_FLOAT ('history' => 0).
			[
				'api_request' => [
					'output' => ['value', 'clock'],
					'history' => 0,
					'itemids' => ['133759'],
					'sortorder' => 'DESC',
					'sortfield' => 'clock',
					'limit' => 5
				],
				'expected_result' => [
					[
						'value' => '-1.5',
						'clock' => '1549350962'
					],
					[
						'value' => '-1',
						'clock' => '1549350961'
					],
					[
						'value' => '1.5',
						'clock' => '1549350960'
					],
					[
						'value' => '1.0001',
						'clock' => '1549350959'
					],
					[
						'value' => '1.5',
						'clock' => '1549350958'
					]
				],
				'expected_error' => false
			],
			// Get values of item of value_type == ITEM_VALUE_TYPE_UINT64 ('history' => 3) using time range selector.
			[
				'api_request' => [
					'output' => ['value', 'clock'],
					'history' => 3,
					'itemids' => ['133758'],
					'time_from' => 1549350908,
					'time_till' => 1549350909
				],
				'expected_result' => [
					[
						'value' => '3',
						'clock' => '1549350908'
					],
					[
						'value' => '4',
						'clock' => '1549350909'
					]
				],
				'expected_error' => false
			],
			// Get count of values of item of value_type == ITEM_VALUE_TYPE_TEXT ('history' => 4).
			[
				'api_request' => [
					'countOutput' => true,
					'history' => 4,
					'itemids' => ['133762']
				],
				'expected_result' => '3',
				'expected_error' => false
			],
			// Get number of history records filtering records by itemid.
			[
				'api_request' => [
					'countOutput' => true,
					'filter' => [
						'itemid' => ['133758']
					]
				],
				'expected_result' => '14',
				'expected_error' => false
			],
			// Get number of history records of filtering by particular value.
			[
				'api_request' => [
					'countOutput' => true,
					'history' => 3,
					'filter' => [
						'value' => '5'
					]
				],
				'expected_result' => '5',
				'expected_error' => false
			],
			// Get number of history records of searching by particular value.
			[
				'api_request' => [
					'countOutput' => true,
					'history' => 1,
					'search' => [
						'value' => '1'
					]
				],
				'expected_result' => '3',
				'expected_error' => false
			],
			// Get floating point number values using differently casted filter values.
			[
				'api_request' => [
					'output' => ['value', 'clock'],
					'history' => 0,
					'filter' => [
						'value' => ['1.5', 1.0001, -1, -1.5, 'abc']
					]
				],
				'expected_result' => [
					[
						'value' => '1.5',
						'clock' => '1549350958'
					],
					[
						'value' => '1.0001',
						'clock' => '1549350959'
					],
					[
						'value' => '1.5',
						'clock' => '1549350960'
					],
					[
						'value' => '-1',
						'clock' => '1549350961'
					],
					[
						'value' => '-1.5',
						'clock' => '1549350962'
					]
				],
				'expected_error' => false
			],
			// Unsupported type of value in filter request.
			[
				'api_request' => [
					'output' => [],
					'history' => 0,
					'filter' => [
						'value' => [[]]
					]
				],
				'expected_result' => null,
				'expected_error' => 'Invalid parameter "/filter/value/1": a character string, integer or floating point value is expected.'
			],
			'Verify binary type NOT returned with wrong history/value type' => [
				'api_request' => [
					'output' => ['value'],
					'history' => ITEM_VALUE_TYPE_FLOAT,
					'itemids' => [$binary_itemid],
					'limit' => 1
				],
				'expected_result' => [],
				'expected_error' => false
			],
			'Verify binary type returned as base64' => [
				'api_request' => [
					'output' => ['value'],
					'history' => ITEM_VALUE_TYPE_BINARY,
					'itemids' => [$binary_itemid],
					'sortorder' => 'DESC',
					'sortfield' => 'clock',
					'limit' => 1
				],
				'expected_result' => [
					[
						'value' => base64_encode('This should be binary')
					]
				],
				'expected_error' => false
			]
		];
	}

	/**
	 * @dataProvider history_get_data
	 */
	public function testHistory_Get($api_request, $expected_result, $expected_error) {
		$result = $this->call('history.get', $api_request, $expected_error);

		if ($expected_error === false) {
			$this->assertSame($expected_result, $result['result']);
		}
		else {
			$this->assertSame($expected_error, $result['error']['data']);
		}
	}

	public static function history_clear_data() {
		$binary_itemid = 58740;

		return [
			[
				'api_request' => ['999999'], // Non-existing itemid.
				'expected_result' => null,
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'api_request' => [$binary_itemid],
				'expected_result' => [
					'itemids' => [$binary_itemid]
				],
				'expected_error' => false
			]
		];
	}

	/**
	 * @dataProvider history_clear_data
	 */
	public function testHistory_Clear($api_request, $expected_result, $expected_error) {
		$result = $this->call('history.clear', $api_request, $expected_error);

		if ($expected_error === false) {
			$this->assertSame($expected_result, $result['result']);
		}
		else {
			$this->assertSame($expected_error, $result['error']['data']);

			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT 1 FROM history_bin WHERE '.dbConditionId('itemid', $api_request), 1
			));
		}
	}

	/**
	 * Data provider for history.push.
	 *
	 * @return array
	 */
	public static function getHistoryPushData(): array {
		return [
			// Check an empty request.
			'Test history.push: empty request' => [
				'request' => [],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],

			// Check unexpected params.
			'Test history.push: unexpected parameter' => [
				'request' => [
					'abc' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "abc".'
			],

			// Check "itemid" field.
			'Test history.push: invalid "itemid" (boolean)' => [
				'request' => [
					'itemid' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/itemid": a number is expected.'
			],
			'Test history.push: invalid "itemid" (string)' => [
				'request' => [
					'itemid' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/itemid": a number is expected.'
			],

			// Check "host" field.
			'Test history.push: invalid "host" (boolean)' => [
				'request' => [
					'host' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/host": a character string is expected.'
			],

			// Check "key" field.
			'Test history.push: invalid "key" (boolean)' => [
				'request' => [
					'key' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/key": a character string is expected.'
			],

			// Check "value" field.
			'Test history.push: missing "value"' => [
				'request' => [
					[]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1": the parameter "value" is missing.'
			],
			'Test history.push: invalid "value" (boolean)' => [
				'request' => [
					'value' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/value": a character string, integer or floating point value is expected.'
			],
			'Test history.push: invalid "value" (array)' => [
				'request' => [
					'value' => []
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/value": a character string, integer or floating point value is expected.'
			],

			// Check "clock" field.
			'Test history.push: invalid "clock" (boolean)' => [
				'request' => [
					'value' => '25',
					'clock' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/clock": an unsigned integer is expected.'
			],
			'Test history.push: invalid "clock" (string)' => [
				'request' => [
					'value' => '25',
					'clock' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/clock": an unsigned integer is expected.'
			],
			'Test history.push: invalid "clock" (negative integer)' => [
				'request' => [
					'value' => '25',
					'clock' => -1
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/clock": an unsigned integer is expected.'
			],
			'Test history.push: invalid "clock" (too large)' => [
				'request' => [
					'value' => '25',
					'clock' => ZBX_MAX_DATE + 1
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/clock": a timestamp is too large.'
			],

			// Check "ns" field.
			'Test history.push: invalid "ns" (boolean)' => [
				'request' => [
					'value' => '25',
					'ns' => true
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/ns": an integer is expected.'
			],
			'Test history.push: invalid "ns" (string)' => [
				'request' => [
					'value' => '25',
					'ns' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/ns": an integer is expected.'
			],
			'Test history.push: invalid "ns" (too small)' => [
				'request' => [
					'value' => '25',
					'ns' => -1
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/ns": value must be one of 0-999999999.'
			],
			'Test history.push: invalid "ns" (too large)' => [
				'request' => [
					'value' => '25',
					'ns' => 1000000000
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/ns": value must be one of 0-999999999.'
			]
		];
	}

	/**
	 * Test history.push.
	 *
	 * @dataProvider getHistoryPushData
	 */
	public function testHistory_Push(array $request, array $expected_result, ?string $expected_error): void {
		$result = $this->call('history.push', $request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($expected_result, $result['result']);
		}
	}
}
