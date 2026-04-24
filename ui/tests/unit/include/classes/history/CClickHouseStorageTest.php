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
				'password' => ''
			], []),
			CClickHouseStorage::class
		);

		yield 'Unkown value types are ignored' => [
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
				'String' => ['A' => 'overwritten'],
			],
			[
				'A' => '\'overwritten\''
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
					'C' => 'd\'ef'
				]
			],
			[
				'empty' => '[]',
				'A' => '\'12345678901234567890123456789012345678901234567890123456789012345678901234567890\'',
				'B' => '\'abc\'',
				'C' => '\'d\\\'ef\''
			]
		];
	}

	/**
	 * @covers CClickHouseStorage::getEncodedParamMap
	 * @dataProvider dataProviderGetEncodedParamMap
	 */
	public function testGetEncodedParamMap(Closure $method, array $params, $expected) {
		$this->assertSame($expected, $method($params));
	}

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
				'password' => ''
			], []),
			CClickHouseStorage::class
		);

		yield 'Support <name> AS <alias> in SELECT when <name>!==<alias>' => [
			$closure,
			[
				'select' => ['itemid' => 'itemid', 'type' => 'i.type'],
				'from' => ['items i'],
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
	public function testBuildQueryFromParts(Closure $method, array $query_parts, $expected) {
		$this->assertSame($expected, $method($query_parts));
	}

	public static function dataProviderAddQueryOutputOptions() {
		$closure = Closure::bind(
			fn($sql_parts, $options) => $this->addQueryOutputOptions($sql_parts, $options),
			new CClickHouseStorage([
				'url' => '',
				'types' => [],
				'db' => '',
				'username' => '',
				'password' => ''
			], []),
			CClickHouseStorage::class
		);
		$defaults = [
			'output' => [],
			'history' => null,
			'maxValueSize' => null,
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
}
