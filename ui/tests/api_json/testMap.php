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
 * @backup sysmaps
 */
class testMap extends CAPITest {
	/**
	 * Create map tests data provider.
	 *
	 * @return array
	 */
	public static function createMapDataProvider() {
		return [
			// Success. Map A1, map B1 with submap having sysmapid=1 created. Map with sysmapid=1 should exist.
			[
				'request_data' => [
					[
						'name' => 'A1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'show_suppressed' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => []
					],
					[
						'name' => 'B1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'show_suppressed' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'iconid_on' => '0',
								'label' => 'Y -> 1',
								'label_location' => '-1',
								'x' => '339',
								'y' => '227',
								'iconid_disabled' => '0',
								'iconid_maintenance' => '0',
								'elementsubtype' => '0',
								'areatype' => '0',
								'width' => '200',
								'height' => '200',
								'viewtype' => '0',
								'use_iconmap' => '1',
								'evaltype' => TAG_EVAL_TYPE_AND_OR,
								'tags' => [],
								'urls' => [],
								'elements' => [
									['sysmapid' => '1']
								],
								'permission' => 3
							]
						]
					]
				],
				'error' => null
			],
			// Success. Property itemid is not validated by circular reference when map is created.
			[
				'request_data' => [
					[
						'sysmapid' => '1',
						'name' => 'A3',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'show_suppressed' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'iconid_on' => '0',
								'label' => 'Test',
								'label_location' => '-1',
								'x' => '339',
								'y' => '227',
								'iconid_disabled' => '0',
								'iconid_maintenance' => '0',
								'elementsubtype' => '0',
								'areatype' => '0',
								'width' => '200',
								'height' => '200',
								'viewtype' => '0',
								'use_iconmap' => '1',
								'evaltype' => TAG_EVAL_TYPE_AND_OR,
								'tags' => [],
								'urls' => [],
								'elements' => [
									['sysmapid' => '1']
								],
								'permission' => 3
							]
						]
					]
				],
				'error' => null
			],
			// Fail. Map name is unique.
			[
				'request_data' => [
					[
						'name' => 'A1',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'show_suppressed' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => []
					]
				],
				'error' => 'Map "A1" already exists.'
			],
			// Fail. Cannot create map with submap with non existing sysmapid.
			[
				'request_data' => [
					[
						'name' => 'A4',
						'width' => '800',
						'height' => '600',
						'backgroundid' => '0',
						'label_type' => '0',
						'label_location' => '0',
						'highlight' => '0',
						'expandproblem' => '1',
						'markelements' => '0',
						'show_unack' => '0',
						'grid_size' => '50',
						'grid_show' => '1',
						'grid_align' => '1',
						'label_format' => '0',
						'label_type_host' => '2',
						'label_type_hostgroup' => '2',
						'label_type_trigger' => '2',
						'label_type_map' => '2',
						'label_type_image' => '2',
						'label_string_host' => '',
						'label_string_hostgroup' => '',
						'label_string_trigger' => '',
						'label_string_map' => '',
						'label_string_image' => '',
						'iconmapid' => '0',
						'expand_macros' => '0',
						'severity_min' => '0',
						'show_suppressed' => '0',
						'userid' => '1',
						'private' => '1',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'iconid_on' => '0',
								'label' => 'Test',
								'label_location' => '-1',
								'x' => '339',
								'y' => '227',
								'iconid_disabled' => '0',
								'iconid_maintenance' => '0',
								'elementsubtype' => '0',
								'areatype' => '0',
								'width' => '200',
								'height' => '200',
								'viewtype' => '0',
								'use_iconmap' => '1',
								'evaltype' => TAG_EVAL_TYPE_AND_OR,
								'tags' => [],
								'urls' => [],
								'elements' => [
									['sysmapid' => '10008']
								],
								'permission' => 3
							]
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Fail. Map element label is too long.
			[
				'request_data' => [
					[
						'name' => 'Map with invalid element label',
						'width' => '800',
						'height' => '600',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'label' => self::generateRandomString(2049),
								'x' => '0',
								'y' => '0',
								'elements' => [
									['sysmapid' => '1']
								]
							]
						]
					]
				],
				'expected_error' => 'Incorrect value for field "label": value is too long.'
			],
			// Fail. Map shape text is too long.
			[
				'request_data' => [
					[
						'name' => 'Map with invalid shape text',
						'width' => '400',
						'height' => '400',
						'shapes' => [
							[
								'type' => 0,
								'x' => 0,
								'y' => 0,
								'width' => 100,
								'height' => 100,
								'text' => self::generateRandomString(65536)
							]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/shape/1/text": value is too long.'
			]
		];
	}

	/**
	 * @dataProvider createMapDataProvider
	 */
	public function testMapCreate($request_data, $expected_error = null) {
		$this->call('map.create', $request_data, $expected_error);
	}

	/**
	 * Update map tests data provider.
	 *
	 * @return array
	 */
	public static function updateMapDataProvider() {
		return [
			// Fail. Can not add map as sub map for itself.
			[
				'request_data' => [
					'sysmapid' => '10001',
					'selements' => [
						[
							'elementtype' => '1',
							'elements' => [
								[
									'sysmapid' => '10001'
								]
							]
						]
					]
				],
				'expected_error' => 'Cannot add map element of the map "A" due to circular reference.'
			],
			// Fail. Can not add map with sub maps having circular reference.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'name' => 'A',
						'selements' => [
							[
								'elementtype' => '1',
								'label' => 'B map element',
								'elements' => [
									[
										'sysmapid' => '10002'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10002',
						'name' => 'B',
						'selements' => [
							[
								'elementtype' => '1',
								'elements' => [
									[
										'sysmapid' => '10003'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10003',
						'name' => 'C',
						'selements' => [
							[
								'elementtype' => '1',
								'elements' => [
									[
										'sysmapid' => '10001'
									]
								]
							]
						]
					]
				],
				'expected_error' => 'Cannot add map element of the map "A" due to circular reference.'
			],
			// Success. Can add existing map as sub map. A > B > C.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'name' => 'A',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10002'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10002',
						'name' => 'B',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10003'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10003',
						'name' => 'C',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10004'
									]
								]
							]
						]
					]
				],
				'expected_error' => null
			],
			// Fail. Can not update map and create circular reference.
			[
				'request_data' => [
					[
						'sysmapid' => '10004',
						'name' => 'D',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'label' => 'New element',
								'elements' => [
									[
										'sysmapid' => '10001'
									]
								]
							]
						]
					],
					[
						'sysmapid' => '10002',
						'name' => 'B',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'elements' => [
									[
										'sysmapid' => '10003'
									]
								]
							]
						]
					]
				],
				'expected_error' => 'Cannot add map element of the map "D" due to circular reference.'
			],
			// Fail. Circular validation message do not show private maps name.
			[
				'request_data' => [
					[
						'sysmapid' => '10003',
						'name' => 'C',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '154',
								'label' => 'ups!',
								'elements' => [
									[
										'sysmapid' => '10001'
									]
								]
							]
						]
					]
				],
				'expected_error' => 'Cannot add map element of the map "C" due to circular reference.',
				'user' => ['user' => 'zabbix-user', 'password' => 'zabbix']
			],
			// Success. Map element label is of valid length.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'selements' => [
							[
								'elementtype' => '1',
								'iconid_off' => '151',
								'label' => self::generateRandomString(2048),
								'x' => '0',
								'y' => '0',
								'elements' => [
									['sysmapid' => '1']
								]
							]
						]
					]
				],
				'expected_error' => null
			],
			// Success. Map shape text is of valid length.
			[
				'request_data' => [
					[
						'sysmapid' => '10001',
						'shapes' => [
							[
								'type' => 0,
								'x' => 0,
								'y' => 0,
								'width' => 100,
								'height' => 100,
								'text' => self::generateRandomString(65535)
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider updateMapDataProvider
	 */
	public function testMapUpdate($request_data, $expected_error = null, $user = null) {
		if ($user !== null) {
			$this->authorize($user['user'], $user['password']);
		}

		$this->call('map.update', $request_data, $expected_error);
	}

	private static function generateRandomString(int $length): string {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$char_length = strlen($chars);
		$string = '';

		for ($i = 0; $i < $length; $i++) {
			$string .= $chars[rand(0, $char_length - 1)];
		}

		return $string;
	}
}
