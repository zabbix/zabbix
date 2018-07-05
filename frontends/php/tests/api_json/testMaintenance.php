<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

/**
 * @backup maintenances
 */
class testMaintenance extends CZabbixTest {
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
							'tag' => 'tag',
							'operator' => 0,
							'value' => 'value'
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
							'tag' => 'tag'
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
							'operator' => 1,
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
							'operator' => 1,
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
				'expected_error' => 'Duplicate tags are not allowed.'
			],
			// Fail. Possible values for "tags_evaltype" are 0 (And/Or) and 2 (Or).
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'tags_evaltype' => 1
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags_evaltype": value must be one of 0, 2.'
			],
			// Fail. Parameter "tags_evaltype" is not allowed for maintenance with no data collection.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'maintenance_type' => 1,
					'tags_evaltype' => 0
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags_evaltype": not allowed for maintenance with no data collection.'
			],
			// Fail. Parameter "tags" is not allowed for maintenance with no data collection.
			[
				'request_data' => [
					'name' => 'M'.++$n,
					'maintenance_type' => 1,
					'tags' => []
				] + $def_options,
				'expected_error' => 'Invalid parameter "/tags": not allowed for maintenance with no data collection.'
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
			]
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
