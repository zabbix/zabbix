<?php declare(strict_types = 0);
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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testHistoryIterativeResult extends CAPITest {

	private static $items = [
		':item:item1' => [
			'key_' => 'item1'
		],
		':item:item2' => [
			'key_' => 'item2',
			'type' => ITEM_TYPE_INTERNAL,
			'delay' => '1m;40/1-7,03:00-05:30'
		],
		':item:item3' => [
			'key_' => 'item3',
			'type' => ITEM_TYPE_INTERNAL,
			'delay' => '6h'
		]
	];

	public static function prepareTestData(): void {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'test.history.iterator']
			],
			'hosts' => [
				[
					'host' => 'for.history.iterator.items',
					'items' => array_values(self::$items)
				]
			]
		]);

		$itemids = CTestDataHelper::getConvertedValueReferences(array_keys(self::$items));
		$now = time();

		// Cover the sum of offsets in CHistory::getOffsetPeriods().
		$max_offset = $now - SEC_PER_MONTH - SEC_PER_WEEK - 2 * SEC_PER_DAY;

		foreach ($itemids as $itemid) {
			$rows = [];

			$clock = $max_offset;
			while ($clock < $now) {
				$rows[] = [
					'itemid' => $itemid,
					'clock' => $clock,
					'value' => rand(0, 999999999),
					'ns' => rand(0, 999999999)
				];

				$clock += 6 * SEC_PER_HOUR;
			}

			DB::insert('history_uint', $rows, false);
		}

		foreach ($itemids as $itemid) {
			$rows = [];

			$ns = 0;
			while ($ns++ < 100) {
				$rows[] = [
					'itemid' => $itemid,
					'clock' => $now - 21 * SEC_PER_MIN,
					'value' => rand(0, 999999999),
					'ns' => $ns
				];
			}

			DB::insert('history_uint', $rows, false);
		}
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function comparative_requests(): array {
		$now = time();
		// For time_from earlier than sum of offsets.
		$max_offset = SEC_PER_MONTH * 2;
		// ~ rows created by prepareTestData()
		$max_rows = 768;

		$cases = [
			'Small limit, big time_from' => [
				'request_base' => [
					'time_from' => $now - $max_offset
				],
				'limit' => intval($max_rows / 10)
			],
			'Medium limit, medium time_from' => [
				'request_base' => [
					'time_from' => $now - intval($max_offset / 2)
				],
				'limit' => intval($max_rows / 2)
			],
			'Small limit (incl. items with many ns per same second)' => [
				'request_base' => [
					'time_from' => $now - SEC_PER_HOUR
				],
				'limit' => intval($max_rows / 10)
			],
			'Big limit, big time_from (incl. items with many ns per same second)' => [
				'request_base' => [
					'time_from' => $now - SEC_PER_MONTH
				],
				'limit' => $max_rows
			],
			'Small limit, big time_from, small time_till' => [
				'request_base' => [
					'time_from' => $now - $max_offset,
					'time_till' => $now - SEC_PER_WEEK
				],
				'limit' => intval($max_rows / 10)
			],
			'Medium limit, medium time_till' => [
				'request_base' => [
					'time_till' => $now - intval($max_offset / 2)
				],
				'limit' => intval($max_rows / 2)
			],
			'Medium limit, medium time_from, small time_till' => [
				'request_base' => [
					'time_from' => $now - intval($max_offset / 2),
					'time_till' => $now - SEC_PER_WEEK
				],
				'limit' => intval($max_rows / 2)
			],
			'Small limit, very small time_till (incl. items with many ns per same second)' => [
				'request_base' => [
					'time_from' => $now - SEC_PER_HOUR,
					'time_till' => $now - 10 * SEC_PER_MIN
				],
				'limit' => intval($max_rows / 10)
			],
			'Big limit, very small time_till (incl. items with many ns per same second)' => [
				'request_base' => [
					'time_from' => $now - SEC_PER_HOUR,
					'time_till' => $now - 10 * SEC_PER_MIN
				],
				'limit' => $max_rows
			]
		];

		$sort_variants = [
			'clock DESC' => [
				'sortfield' => ['clock'],
				'sortorder' => [ZBX_SORT_DOWN]
			],
			'clock DESC, ns ASC' => [
				'sortfield' => ['clock', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_UP]
			],
			'clock DESC, ns DESC' => [
				'sortfield' => ['clock', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_DOWN]
			],
			'clock DESC, itemid ASC, ns ASC' => [
				'sortfield' => ['clock', 'itemid', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_UP, ZBX_SORT_UP]
			],
			'clock DESC, itemid ASC, ns DESC' => [
				'sortfield' => ['clock', 'itemid', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_UP, ZBX_SORT_DOWN]
			],
			'clock DESC, itemid DESC, ns ASC' => [
				'sortfield' => ['clock', 'itemid', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_DOWN, ZBX_SORT_UP]
			],
			'clock DESC, itemid DESC, ns DESC' => [
				'sortfield' => ['clock', 'itemid', 'ns'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_DOWN, ZBX_SORT_DOWN]
			]
		];

		$result = [];
		foreach ($cases as $label => $case) {
			foreach ($sort_variants as $description => $sort_parameters) {
				$case_sorted = $case;
				$case_sorted['request_base'] += $sort_parameters;

				$result[$label.'; sort by '.$description] = $case_sorted;
			}
		}

		return $result;
	}

	/**
	 * @dataProvider comparative_requests
	 */
	public function testHistoryIterativeResult_Matches(array $request_base, int $limit): void {
		global $DB;

		$request_base['itemids'] = CTestDataHelper::getConvertedValueReferences(array_keys(self::$items));

		$this->compareRequestResults($request_base, $limit);

		$resource = DBSelect(
			'SELECT itemid,clock,ns,value'.
			' FROM history_uint'.
			' ORDER BY '.($DB['TYPE'] === ZBX_DB_MYSQL ? 'RAND()' : 'RANDOM()').
			' LIMIT 50'
		);

		$filter = [];
		while ($row = DBfetch($resource)) {
			$filter[] = $row;
		}

		foreach (array_keys(reset($filter)) as $field) {
			$filter_request_base = $request_base + [
				'filter' => [
					$field => array_column($filter, $field)
				]
			];

			$this->compareRequestResults($filter_request_base, $limit);
		}
	}

	private function compareRequestResults(array $request_base, int $limit): void {
		$non_iterative_result = $this->call('history.get', $request_base);
		$this->assertArrayHasKey('result', $non_iterative_result);
		$non_iterative_result = $non_iterative_result['result'];

		$iterative_result = $this->call('history.get', $request_base + ['limit' => $limit]);
		$this->assertArrayHasKey('result', $iterative_result);
		$iterative_result = $iterative_result['result'];

		$non_iterative_result = array_slice($non_iterative_result, 0, $limit, true);

		$this->assertEquals(count($non_iterative_result), count($iterative_result),
			'Count of result entries (non-, iterative strategy) should match.'
		);

		// Order of rows is not guaranteed if a field is not sorted by, only compare by values in sortfield.
		$compare_fields = array_flip($request_base['sortfield']);

		foreach ($non_iterative_result as $i => $row) {
			$this->assertArrayHasKey($i, $iterative_result);
			$iterative_row = $iterative_result[$i];

			$this->assertEquals(
				array_intersect_key($row, $compare_fields),
				array_intersect_key($iterative_row, $compare_fields),
				'Fields included in sortfield should match (non-iterative, iterative).'
			);

			unset($iterative_result[$i]);
		}

		$this->assertEmpty($iterative_result, 'No more results should be present in iterative result.');
	}
}
