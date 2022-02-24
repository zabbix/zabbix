<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


use PHPUnit\Framework\TestCase;

class CValueMapHelperTest extends TestCase {

	/**
	 * Data for testing single rule match.
	 */
	public function dataMatchMapping(): array {
		return [
			// VALUEMAP_MAPPING_TYPE_EQUAL
			[
				'.1', ITEM_VALUE_TYPE_FLOAT,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '0.1', 'newvalue' => 'ok'],
				true
			],
			[
				'0.2', ITEM_VALUE_TYPE_FLOAT,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '.2', 'newvalue' => 'ok'],
				true
			],
			[
				'2', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '2', 'newvalue' => 'ok'],
				true
			],
			[
				'2', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '2', 'newvalue' => 'ok'],
				true
			],
			[
				'match case sensitive', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => 'match case sensitive', 'newvalue' => 'ok'],
				true
			],
			[
				'1024', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '1K', 'newvalue' => 'ok'],
				false
			],
			[
				'.1', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '0.1', 'newvalue' => 'ok'],
				false
			],
			[
				'0.2', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '.2', 'newvalue' => 'ok'],
				false
			],
			[
				'match Case Sensitive', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => 'match case sensitive', 'newvalue' => 'ok'],
				false
			],
			[
				'11 ', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '11', 'newvalue' => 'ok'],
				false
			],
			[
				'2K', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '2000', 'newvalue' => 'ok'],
				false
			],
			// VALUEMAP_MAPPING_TYPE_GREATER_EQUAL
			[
				'.1', ITEM_VALUE_TYPE_FLOAT,
				['type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, 'value' => '0.1', 'newvalue' => 'ok'],
				true
			],
			[
				'3', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, 'value' => '0', 'newvalue' => 'ok'],
				true
			],
			[
				'2048', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, 'value' => '1K', 'newvalue' => 'ok'],
				false
			],
			[
				'3', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, 'value' => '0', 'newvalue' => 'ok'],
				false
			],
			// VALUEMAP_MAPPING_TYPE_LESS_EQUAL
			[
				'.1', ITEM_VALUE_TYPE_FLOAT,
				['type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL, 'value' => '10', 'newvalue' => 'ok'],
				true
			],
			[
				'4', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL, 'value' => '10', 'newvalue' => 'ok'],
				true
			],
			[
				'100', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_LESS_EQUAL, 'value' => '1K', 'newvalue' => 'ok'],
				false
			],
			// VALUEMAP_MAPPING_TYPE_IN_RANGE
			[
				'-5.2', ITEM_VALUE_TYPE_FLOAT,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '-5.5--5', 'newvalue' => 'ok'],
				true
			],
			[
				'5', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '1-10', 'newvalue' => 'ok'],
				true
			],
			[
				'5', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '4-5', 'newvalue' => 'ok'],
				true
			],
			[
				'5', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '0.5-5.5', 'newvalue' => 'ok'],
				true
			],
			[
				'5', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '10-30,5', 'newvalue' => 'ok'],
				true
			],
			[
				'2048', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '2K-2.5K', 'newvalue' => 'ok'],
				true
			],
			[
				'1K', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_IN_RANGE, 'value' => '1000-2000', 'newvalue' => 'ok'],
				false
			],
			// VALUEMAP_MAPPING_TYPE_REGEXP
			[
				'12', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_REGEXP, 'value' => '\d{2}', 'newvalue' => 'ok'],
				true
			],
			[
				'test two words', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_REGEXP, 'value' => 'test(\s\w+){2}', 'newvalue' => 'ok'],
				true
			],
			[
				'test/ slash escaped', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_REGEXP, 'value' => 'test/(\s\w+){2}', 'newvalue' => 'ok'],
				true
			],
			[
				'12', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_REGEXP, 'value' => '\d{2}', 'newvalue' => 'ok'],
				false
			],
			[
				'test no modifiers', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_REGEXP, 'value' => '/test(\s\w+){2}/i', 'newvalue' => 'ok'],
				false
			],
			// VALUEMAP_MAPPING_TYPE_DEFAULT
			[
				'12', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'value' => '', 'newvalue' => 'ok'],
				true
			],
			[
				'128K', ITEM_VALUE_TYPE_UINT64,
				['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'value' => '', 'newvalue' => 'ok'],
				true
			],
			[
				'any should match', ITEM_VALUE_TYPE_STR,
				['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'value' => '', 'newvalue' => 'ok'],
				true
			]
		];
	}

	/**
	 * @dataProvider dataMatchMapping
	 *
	 * @param string $value
	 * @param int    $value_type
	 * @param array  $mapping
	 * @param bool   $expected
	 */
	public function testMatchMapping(string $value, int $value_type, array $mapping, bool $expected) {
		$this->assertSame(CValueMapHelper::matchMapping($value_type, $value, $mapping), $expected);
	}

	/**
	 * Data for testing multiple mappings.
	 */
	public function dataMatchMappingOrder(): array {
		return [
			['1', [], false],
			[
				'1',
				[
					['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'newvalue' => 'newvalue-1'],
					['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '10', 'newvalue' => 'newvalue-2']
				],
				'newvalue-1'
			],
			[
				'10',
				[
					['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'newvalue' => 'newvalue-1'],
					['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '10', 'newvalue' => 'newvalue-2']
				],
				'newvalue-2'
			],
			[
				'10',
				[
					['type' => VALUEMAP_MAPPING_TYPE_DEFAULT, 'newvalue' => 'newvalue-1'],
					['type' => VALUEMAP_MAPPING_TYPE_GREATER_EQUAL, 'value' => '10', 'newvalue' => 'newvalue-2'],
					['type' => VALUEMAP_MAPPING_TYPE_EQUAL, 'value' => '10', 'newvalue' => 'newvalue-3']
				],
				'newvalue-2'
			]
		];
	}

	/**
	 * @dataProvider dataMatchMappingOrder
	 *
	 * @param string      $value
	 * @param array       $mapping
	 * @param string|bool $expected
	 */
	public function testMatchMappingOrder(string $value, array $mappings, $expected) {
		$this->assertSame(CValueMapHelper::getMappedValue(ITEM_VALUE_TYPE_UINT64, $value, ['mappings' => $mappings]), $expected);
	}
}
