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
	public function testBuildQueryFromParts(Closure $method, array $sql_parts, string $expected) {
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
	public function testAddQueryOutputOptions(Closure $method, array $sql_parts, array $options, array $expected) {
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
	public function testAddQuerySortOptions(Closure $method, array $sql_parts, array $options, array $expected) {
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

		yield 'Filter ignore fields for another value type' => [
			$closure,
			[],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'filter' => ['severity' => 7]
			] + $defaults,
			[]
		];

		yield 'Filter with searchByAny use OR and enclose filter in brackets' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_UINT64,
				'searchByAny' => true,
				'filter' => [
					'itemid' => [12345],
					'value' => [5]
				]
			] + $defaults,
			[
				'where' => [
					'filter' => '(itemid IN {filter_itemid:Array(UInt64)} OR value IN {filter_value:Array(UInt64)})'
				],
				'param' => [
					'UInt64' => [
						'filter_itemid' => [12345],
						'filter_value' => [5]
					]
				]
			]
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
	}

	/**
	 * @covers CClickHouseStorage::addQueryFilterOptions
	 * @dataProvider dataProviderAddQueryFilterOptions
	 */
	public function testAddQueryFilterOptions(Closure $method, array $sql_parts, array $options, array $expected) {
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

		yield 'Search startSearch' => [
			$closure,
			['where' => [], 'param' => []],
			[
				'history' => ITEM_VALUE_TYPE_LOG,
				'startSearch' => true,
				'search' => [
					'value' => ['str'],
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => 'arrayAll(p -> value ILIKE p, {search_value:Array(String)}) AND arrayAll(p -> source ILIKE p, {search_source:Array(String)})'
				],
				'param' => [
					'String' => [
						'search_value' => ['str%'],
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
					'value' => ['str'],
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => '(arrayExists(p -> value ILIKE p, {search_value:Array(String)}) OR arrayExists(p -> source ILIKE p, {search_source:Array(String)}))'
				],
				'param' => [
					'String' => [
						'search_value' => ['str%'],
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
					'value' => ['str'],
					'source' => ['*str1', '%str2']
				]
			] + $defaults,
			[
				'where' => [
					'search' => 'arrayAll(p -> value ILIKE p, {search_value:Array(String)}) AND arrayAll(p -> source ILIKE p, {search_source:Array(String)})'
				],
				'param' => [
					'String' => [
						'search_value' => ['str'],
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
	public function testAddQuerySearchOptions(Closure $method, array $sql_parts, array $options, array $expected) {
		$this->assertSame($expected, $method($sql_parts, $options));
	}
}
