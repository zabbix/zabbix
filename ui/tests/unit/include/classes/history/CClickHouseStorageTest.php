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


use PHPUnit\Framework\TestCase;

class CHistoryStorageClickHouseTest extends TestCase {

	public static function dataProviderGetEncodedParamMap() {
		$closure = Closure::bind(
			fn($sql_parts) => $this->getEncodedParamMap($sql_parts),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);

		yield 'Unknown value types are ignored' => [
			$closure,
			[
				'uint64' => ['A' => 123],
				'_' => ['B' => 5],
				['C' => 7]
			],
			[]
		];

		yield 'Different value type having same name will overwrite each other' => [
			$closure,
			[
				'Int32' => ['A' => 1],
				'Int64' => ['A' => 1],
				'UInt64' => ['A' => 1],
				'String' => ['A' => 'overwritten']
			],
			[
				'A' => 'overwritten'
			]
		];

		yield 'Int32 invalid values ignored' => [
			$closure,
			[
				'Int32' => [
					'false' => false,
					'null' => null,
					'A' => 1.1,
					'B' => -2.2,
					'C' => '3.3',
					'D' => '+4.4',
					'E' => 'test',
					'F' => '0test',
					'G' => '+5',
					'H' => '--6',
					'I' => '7-8',
					'J' => ' 10',
					'K' => '100_000_000'
				]
			],
			[]
		];

		yield 'Int32 value types resolving' => [
			$closure,
			[
				'Int32' => [
					'empty' => [],
					'A' => -5,
					'B' => '-5',
					'C' => [1],
					'D' => [1,2],
					'E' => [1,2,'-3']
				]
			],
			[
				'empty' => '[]',
				'A' => '-5',
				'B' => '-5',
				'C' => '[1]',
				'D' => '[1,2]',
				'E' => '[1,2,-3]'
			]
		];

		yield 'Int64 invalid values ignored' => [
			$closure,
			[
				'Int64' => [
					'false' => false,
					'null' => null,
					'A' => 1.1,
					'B' => -2.2,
					'C' => '3.3',
					'D' => '+4.4',
					'E' => 'test',
					'F' => '0test',
					'G' => '+5',
					'H' => '--6',
					'I' => '7-8',
					'J' => ' 10',
					'K' => '100_000_000'
				]
			],
			[]
		];

		yield 'Int64 value types resolving' => [
			$closure,
			[
				'Int64' => [
					'empty' => [],
					'A' => -5,
					'B' => '-5',
					'C' => [1],
					'D' => [1,2],
					'E' => [1,2,'-3']
				]
			],
			[
				'empty' => '[]',
				'A' => '-5',
				'B' => '-5',
				'C' => '[1]',
				'D' => '[1,2]',
				'E' => '[1,2,-3]'
			]
		];

		yield 'Float invalid values ignored' => [
			$closure,
			[
				'Float64' => [
					'false' => false,
					'null' => null,
					'A' => '+4.4',
					'B' => 'test',
					'C' => '0test',
					'D' => '+5',
					'E' => '--6',
					'F' => '7-8',
					'G' => ' 10',
					'H' => '100_000_000'
				]
			],
			[]
		];

		yield 'Float value types resolving' => [
			$closure,
			[
				'Float64' => [
					'empty' => [],
					'A' => -5,
					'B' => '-5',
					'C' => [1],
					'D' => [1,1.5],
					'E' => [1,2,'-3.3'],
					'F' => '1.2e3'
				]
			],
			[
				'empty' => '[]',
				'A' => '-5',
				'B' => '-5',
				'C' => '[1]',
				'D' => '[1,1.5]',
				'E' => '[1,2,-3.3]',
				'F' => '1.2e3'
			]
		];

		yield 'UInt64 invalid values ignored' => [
			$closure,
			[
				'UInt64' => [
					'false' => false,
					'null' => null,
					'A' => 1.1,
					'B' => -2.2,
					'C' => '3.3',
					'D' => '+4.4',
					'E' => 'test',
					'F' => '0test',
					'G' => '+5',
					'H' => '--6',
					'I' => '7-8',
					'J' => '-9',
					'K' => ' 10',
					'L' => '100_000_000'
				]
			],
			[]
		];

		yield 'UInt64 value types resolving' => [
			$closure,
			[
				'UInt64' => [
					'empty' => [],
					'A' => 4,
					'B' => '5',
					'C' => [1],
					'D' => [1,2],
					'E' => [1,2,'3', '12345678901234567890123456789012345678901234567890123456789012345678901234567890']
				]
			],
			[
				'empty' => '[]',
				'A' => '4',
				'B' => '5',
				'C' => '[1]',
				'D' => '[1,2]',
				'E' => '[1,2,3,12345678901234567890123456789012345678901234567890123456789012345678901234567890]'
			]
		];

		yield 'String invalid values ignored' => [
			$closure,
			[
				'String' => [
					'false' => false,
					'null' => null,
					'A' => 1.1,
					'B' => -2.2,
					'C' => 3,
					'D' => -4
				]
			],
			[]
		];

		yield 'String value types resolving' => [
			$closure,
			[
				'String' => [
					'empty' => [],
					'A' => '12345678901234567890123456789012345678901234567890123456789012345678901234567890',
					'B' => 'abc',
					'C' => 'd\'ef',
					'D' => ['abc', 'def'],
					'E' => ['abc', 'd\'ef']
				]
			],
			[
				'empty' => '[]',
				'A' => '12345678901234567890123456789012345678901234567890123456789012345678901234567890',
				'B' => 'abc',
				'C' => 'd\'ef',
				'D' => '[\'abc\',\'def\']',
				'E' => '[\'abc\',\'d\\\'ef\']'
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::getEncodedParamMap
	 * @ dataProvider dataProviderGetEncodedParamMap
	 */
	// public function testGetEncodedParamMap(Closure $method, array $params, $expected) {
	// 	$this->assertSame($expected, $method($params));
	// }

	public static function dataProviderBuildQueryFromParts() {
		$defaults = [
			'select' => [],
			'from' => [],
			'prewhere' => [],
			'where' => [],
			'group' => [],
			'order' => [],
			'limit' => [],
			'limit_by' => []
		];
		$closure = Closure::bind(
			fn($sql_parts) => $this->buildQueryFromParts($sql_parts),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);

		yield 'Support <value> AS <key> in SELECT when <key>!==<value>' => [
			$closure,
			[
				'select' => ['itemid' => 'itemid', 'type' => 'i.type'],
				'from' => ['items i']
			] + $defaults,
			'SELECT itemid,i.type AS type FROM items i'
		];

		yield 'PREWHERE condition use AND' => [
			$closure,
			[
				'prewhere' => ['itemid={pre_itemids:UInt64}', 'clock>{pre_time_gt:UInt64}']
			] + $defaults,
			'SELECT  FROM  PREWHERE itemid={pre_itemids:UInt64} AND clock>{pre_time_gt:UInt64}'
		];

		yield 'WHERE condition use AND' => [
			$closure,
			[
				'where' => ['itemid={pre_itemids:UInt64}', 'clock>{pre_time_gt:UInt64}']
			] + $defaults,
			'SELECT  FROM  WHERE itemid={pre_itemids:UInt64} AND clock>{pre_time_gt:UInt64}'
		];

		yield 'LIMIT BY resolved to LIMIT <first element> BY <other elements>' => [
			$closure,
			[
				'limit_by' => [2, 'itemid', 'clock']
			] + $defaults,
			'SELECT  FROM  LIMIT 2 BY itemid,clock'
		];
	}

	/**
	 * @covers CClickHouseStorage::buildQueryFromParts
	 * @dataProvider dataProviderBuildQueryFromParts
	 */
	public function testBuildQueryFromParts(Closure $method, array $sql_parts, $expected) {
		$this->assertSame($expected, $method($sql_parts));
	}

	public static function dataProviderAddQueryOutputOptions() {
		$closure = Closure::bind(
			fn($sql_parts, $options) => $this->addQueryOutputOptions($sql_parts, $options),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);
		$defaults = [
			'output' => [],
			'history' => null,
			'maxValueSize' => null
		];

		yield 'Ignore fields for another value type' => [
			$closure,
			[],
			[
				'output' => ['value', 'value_str'],
				'history' => ITEM_VALUE_TYPE_UINT64
			] + $defaults,
			[
				'select' => ['value' => 'value']
			]
		];

		yield 'maxValueSize for uint return value' => [
			$closure,
			[],
			[
				'output' => ['value'],
				'history' => ITEM_VALUE_TYPE_UINT64,
				'maxValueSize' => 128
			] + $defaults,
			[
				'select' => ['value' => 'value']
			]
		];

		yield 'maxValueSize for dbl return value' => [
			$closure,
			[],
			[
				'output' => ['value'],
				'history' => ITEM_VALUE_TYPE_FLOAT,
				'maxValueSize' => 128
			] + $defaults,
			[
				'select' => ['value' => 'value']
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::addQueryOutputOptions
	 * @dataProvider dataProviderAddQueryOutputOptions
	 */
	public function testAddQueryOutputOptions(Closure $method, array $sql_parts, array $options, $expected) {
		$this->assertSame($expected, $method($sql_parts, $options));
	}

	public static function dataProviderAddQuerySortOptions() {
		$closure = Closure::bind(
			fn($sql_parts, $options) => $this->addQuerySortOptions($sql_parts, $options),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);
		$defaults = [
			'history' => null,
			'sortfield' => [],
			'sortorder' => null
		];

		yield 'Unknown field is ignored' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'sortfield' => ['severity']
			] + $defaults,
			[]
		];

		yield 'Sorting by value_str or ns is not allowed' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'sortfield' => ['value_str', 'ns']
			] + $defaults,
			[]
		];

		yield 'Default sort is ZBX_SORT_UP' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['severity']
			] + $defaults,
			[
				'order' => ['severity' => 'severity']
			]
		];

		yield 'Uses ZBX_SORT_UP when sortorder index not set for sortfield index' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['severity', 'itemid'],
				'sortorder' => [ZBX_SORT_DOWN]
			] + $defaults,
			[
				'order' => [
					'severity' => 'severity '.ZBX_SORT_DOWN,
					'itemid' => 'itemid'
				]
			]
		];

		yield 'Field with it own sort' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['severity', 'itemid'],
				'sortorder' => [ZBX_SORT_DOWN, ZBX_SORT_DOWN]
			] + $defaults,
			[
				'order' => [
					'severity' => 'severity '.ZBX_SORT_DOWN,
					'itemid' => 'itemid '.ZBX_SORT_DOWN
				]
			]
		];

		yield 'Without own sort default ZBX_SORT_UP is set' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['severity', 'itemid'],
				'sortorder' => [ZBX_SORT_DOWN]
			] + $defaults,
			[
				'order' => [
					'severity' => 'severity '.ZBX_SORT_DOWN,
					'itemid' => 'itemid'
				]
			]
		];

		yield 'String value sortorder is applied to all fields' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['severity', 'itemid'],
				'sortorder' => ZBX_SORT_DOWN
			] + $defaults,
			[
				'order' => [
					'severity' => 'severity '.ZBX_SORT_DOWN,
					'itemid' => 'itemid '.ZBX_SORT_DOWN
				]
			]
		];

		yield 'Sorting by clock produces clock_ns sorting' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'sortfield' => ['clock'],
				'sortorder' => ZBX_SORT_DOWN
			] + $defaults,
			[
				'order' => ['clock_ns' => 'clock_ns '.ZBX_SORT_DOWN]
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::addQuerySortOptions
	 * @dataProvider dataProviderAddQuerySortOptions
	 */
	public function testAddQuerySortOptions(Closure $method, array $sql_parts, array $options, $expected) {
		$this->assertSame($expected, $method($sql_parts, $options));
	}

	public static function dataProviderAddQueryFilterOptions() {
		$closure = Closure::bind(
			fn($sql_parts, $options) => $this->addQueryFilterOptions($sql_parts, $options),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);
		$defaults = [
			'history' => null,
			'time_from' => null,
			'time_till' => null,
			'searchByAny' => null
		];

		yield 'Filter for clock produces condition for clock_ns' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'time_from' => 1234567,
				'filter' => ['clock' => [1234567]]
			] + $defaults,
			[
				'where' => [
					'filter' => 'toUnixTimestamp(clock_ns) IN {filter_clock:Array(UInt64)}'
				],
				'param' => [
					'UInt64' => ['filter_clock' => [1234567]]
				]
			]
		];

		yield 'Filter for ns produces condition for clock_ns' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'time_from' => 1234567,
				'filter' => ['ns' => [1234567]]
			] + $defaults,
			[
				'where' => [
					'filter' => 'toUnixTimestamp64Nano(clock_ns)%1000000000 IN {filter_ns:Array(Int32)}'
				],
				'param' => [
					'Int32' => ['filter_ns' => [1234567]]
				]
			]
		];

		yield 'Filter adds IN for array of values' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'filter' => ['itemid' => [1, 2, 3, 4]]
			] + $defaults,
			[
				'where' => [
					'filter' => 'itemid IN {filter_itemid:Array(UInt64)}'
				],
				'param' => [
					'UInt64' => ['filter_itemid' => [1, 2, 3, 4]]
				]
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::addQueryFilterOptions
	 * @dataProvider dataProviderAddQueryFilterOptions
	 */
	public function testAddQueryFilterOptions(Closure $method, array $sql_parts, array $options, $expected) {
		$this->assertSame($expected, $method($sql_parts, $options));
	}

	public static function dataProviderAddQuerySearchOptions() {
		$closure = Closure::bind(
			fn($sql_parts, $options) => $this->addQuerySearchOptions($sql_parts, $options),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => '',
				'value_type_ttl' => []
			]),
			CClickHouseStorage::class
		);
		$defaults = [
			'history' => null,
			'startSearch' => false,
			'excludeSearch' => false,
			'searchWildcardsEnabled' => false,
			'searchByAny' => false
		];

		yield 'Search ignore fields for another value type' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'search' => ['severity' => 7]
			] + $defaults,
			[]
		];

		yield 'Search ignore fields of not String type' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_JSON,
				'search' => ['itemid' => 7]
			] + $defaults,
			[]
		];

		yield 'Search ignore empty patterns' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'searchWildcardsEnabled' => true,
				'search' => [
					'value' => '',
					'source' => [' ', '']
				]
			] + $defaults,
			[
				'where' => [
					'search' => 'source ILIKE {search_source:String}'
				],
				'param' => [
					'String' => ['search_source' => ' ']
				]
			]
		];

		yield 'Search startSearch' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'startSearch' => true,
				'search' => [
					'value' => 'str',
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => 'value ILIKE {search_value:String} AND arrayAll(p -> source ILIKE p, {search_source:Array(String)})'
				],
				'param' => [
					'String' => [
						'search_value' => 'str%',
						'search_source' => ['*str1%', '\\%str2%']
					]
				]
			]
		];

		yield 'Search startSearch with searchByAny' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'startSearch' => true,
				'searchByAny' => true,
				'search' => [
					'value' => 'str',
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => '(value ILIKE {search_value:String} OR arrayExists(p -> source ILIKE p, {search_source:Array(String)}))'
				],
				'param' => [
					'String' => [
						'search_value' => 'str%',
						'search_source' => ['*str1%', '\\%str2%']
					]
				]
			]
		];

		yield 'Search searchWildcardsEnabled' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'searchWildcardsEnabled' => true,
				'search' => [
					'value' => 'str',
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => 'value ILIKE {search_value:String} AND arrayAll(p -> source ILIKE p, {search_source:Array(String)})'
				],
				'param' => [
					'String' => [
						'search_value' => 'str',
						'search_source' => ['%str1', '\\%str2']
					]
				]
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::addQuerySearchOptions
	 * @dataProvider dataProviderAddQuerySearchOptions
	 */
	public function testAddQuerySearchOptions(Closure $method, array $sql_parts, array $options, $expected) {
		$this->assertSame($expected, $method($sql_parts, $options));
	}
}
