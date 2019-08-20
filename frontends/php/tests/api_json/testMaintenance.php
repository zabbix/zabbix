<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup maintenances
 */
class testMaintenance extends CAPITest {
	public static function getMaintenanceCreateData() {
		$n = 0;
		$def_options = [
			'active_since' => '1514757600',
			'active_till' => '1546207200',
			'groupids' => ['2'],
			'timeperiods' => [
				[
					'every' => 1,
					'dayofweek' => 64,
					'start_time' => 3600,
					'period' => 7200
				]
			]
		];

		return [
			// Success. Created maintenance without tags.
			[
				'request_data' => [
					'name' => 'M'.++$n
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance without tags.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => []
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with one tag.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple tags.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag1',
							'operator' => 0,
							'value' => 'value1'
						],
						[
							'tag' => 'tag2',
							'operator' => 2,
							'value' => 'value2'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Success. Created maintenance with multiple tags having the same tag name.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
						],
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => 2,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => null
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag'
						],
						[
							'tag' => 'tag'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 2, ) already exists.'
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 2, value) already exists.'
			],
			// Fail. Duplicate tags are not allowed.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						],
						[
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/2": value (tag, operator, value)=(tag, 0, value) already exists.'
			],
			// Fail. Possible values for "tags_evaltype" are 0 (And/Or) and 2 (Or).
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags_evaltype' => 1
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags_evaltype": value must be one of 0, 2.'
			],
			// Fail. Parameter "tag" is mandatory.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1": the parameter "tag" is missing.'
			],
			// Fail. Parameter "tag" cannot be empty.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => ''
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/tag": cannot be empty.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/tag": a character string is expected.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/tag": a character string is expected.'
			],
			// Fail. Parameter "tag" must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/tag": a character string is expected.'
			],
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							999 => 'aaa'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1": unexpected parameter "999".'
			],
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'aaa' => 'bbb'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1": unexpected parameter "aaa".'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/operator": an integer is expected.'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/operator": an integer is expected.'
			],
			// Fail. Possible values for "operator" are 0 (Equals) and 2 (Contains).
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/operator": value must be one of 0, 2.'
			],
			// Fail. Condition operator must be of type integer.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'operator' => 'aaa'
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/operator": an integer is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => null
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/value": a character string is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => true
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/value": a character string is expected.'
			],
			// Fail. Tag value must be of type string.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags' => [
						[
							'tag' => 'tag',
							'value' => 999
						]
					]
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags/1/value": a character string is expected.'
			],
		];
	}

	/**
	 * @dataProvider getMaintenanceCreateData
	 */
	public function testMaintenance_Create($request_data, $expected_error = null) {
		$this->call('maintenance.create', $request_data, $expected_error);
	}

	public static function getMaintenanceUpdateData() {
		return [
			// Fail. Unexpected parameter.
			[
				'request_data' => [
					'maintenanceid' => 99999,
					'tags' => [
						[
							'aaa' => 'bbb'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/tags/1": unexpected parameter "aaa".'
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceUpdateData
	 */
	public function testMaintenance_Update($request_data, $expected_error = null) {
		$this->call('maintenance.update', $request_data, $expected_error);
	}

	public static function getMaintenanceGetData() {
		return [
			[
				'request_data' => [
					'output' => ['tags_evaltype'],
					'selectTags' => 'extend'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider getMaintenanceGetData
	 */
	public function testMaintenance_Get($request_data, $expected_error = null) {
		$result = $this->call('maintenance.get', $request_data, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result'] as $maintenance) {
				$this->assertArrayHasKey('tags_evaltype', $maintenance);
				$this->assertArrayHasKey('tags', $maintenance);
			}
		}
	}
}
