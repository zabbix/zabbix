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

class testConfiguration extends CZabbixTest {

	public static function export_fail_data() {
		return [
			[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						],
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "format" is missing.'
			],
				[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						],
					],
					'format' => ''
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of xml, json.'
			],
			[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						],
					],
					'format' => 'test'
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of xml, json.'
			],
			[
				'export' => [
					'options' => [
						'groups' => [
							'50012'
						],
					],
					'format' => 'test',
					'hosts' => '50009'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "hosts".'
			],
			[
				'export' => [
					'options' => [
						'applications' => [
							'366'
						]
					],
					'format' => 'xml'
				],
				'expected_error' => 'Invalid parameter "/options": unexpected parameter "applications".'
			],
			[
				'export' => [
					'format' => 'xml'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "options" is missing.'
			]
		];
	}

	/**
	* @dataProvider export_fail_data
	*/
	public function testConfiguration_ExportFail($export, $expected_error) {
		$this->call('configuration.export', $export, $expected_error);
	}

	public static function export_string_ids() {
		return [
			['groups'],
			['hosts'],
			['images'],
			['maps'],
			['screens'],
			['templates'],
			['valueMaps']
		];
	}

	/**
	* @dataProvider export_string_ids
	*/
	public function testConfiguration_ExportIdsNotNumber($options) {
		$formats = ['xml', 'json'];

		foreach ($formats as $parameter){
			$this->call('configuration.export',
				[
					'options' => [
							$options => [
								$options
							],
					],
					'format' => $parameter
				],
				'Invalid parameter "/options/'.$options.'/1": a number is expected.'
			);
		}
	}

	public static function export_success_data() {
		return [
			[
				['groups' =>  ['50012']]
			],
			[
				['hosts' =>  ['50009']]
			],
			[
				['images' =>  ['1']]
			],
			[
				['maps' =>  ['1']]
			],
			[
				['screens' =>  ['3']]
			],
			[
				['templates' =>  ['10069']]
			],
			[
				['valueMaps' =>  ['1']]
			],
		];
	}

	/**
	* @dataProvider export_success_data
	*/
	public function testConfiguration_ExportSuccess($data) {
		$formats = ['xml', 'json'];

		foreach ($formats as $parameter){
			$this->call('configuration.export', [
				'options' => $data,
				'format' => $parameter
			]);
		}
	}

	public static function import_fail_data() {
		return [
			// Check format.
			[
				'import' => [
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-09T07:12:45Z</date>
								<groups>
									<group>
										<name>API import host group</name>
									</group>
								</groups>
								</zabbix_export>'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "format" is missing.'
			],
			[
				'import' => [
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "format" is missing.'
			],
			[
				'import' => [
					'format' => '',
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of xml, json.'
			],
			[
				'import' => [
					'format' => 'test',
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of xml, json.'
			],
			[
				'import' => [
					'format' => 'json',
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}',
					'hosts' => '50009'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "hosts".'
			],
			// Check rules.
			[
				'import' => [
					'format' => 'json',
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "rules" is missing.'
			],
			[
				'import' => [
					'format' => 'json',
					'rules' => [
						'users' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/rules": unexpected parameter "users".'
			],
			[
				'import' => [
					'format' => 'json',
					'rules' => [
						'groups' => [
							'createMissing' => true
						],
						'users' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'expected_error' => 'Invalid parameter "/rules": unexpected parameter "users".'
			],
			[
				'import' => [
					'format' => 'json',
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "source" is missing.'
			]
		];
	}

	/**
	* @dataProvider import_fail_data
	*/
	public function testConfiguration_ImportFail($import, $expected_error) {
		$this->call('configuration.import', $import, $expected_error);
	}

	public static function import_rules_parametrs() {
		return [
			[[
				'parametr' => 'applications',
				'expected' => ['createMissing', 'deleteMissing'],
				'unexpected' => ['updateExisting']
			]],
			[[
				'parametr' => 'discoveryRules',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'graphs',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'groups',
				'expected' => ['createMissing'],
				'unexpected' => ['deleteMissing', 'updateExisting']
			]],
			[[
				'parametr' => 'hosts',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parametr' => 'httptests',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'images',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parametr' => 'items',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'maps',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parametr' => 'screens',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parametr' => 'templateLinkage',
				'expected' => ['createMissing'],
				'unexpected' => ['deleteMissing', 'updateExisting']
			]],
			[[
				'parametr' => 'templates',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parametr' => 'templateScreens',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'triggers',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parametr' => 'valueMaps',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]]
		];
	}

	/**
	* @dataProvider import_rules_parametrs
	*/
	public function testConfiguration_ImportBooleanTypeAndUnexpectedParametrs($import) {
		foreach ($import['expected'] as $expected) {
			$this->call('configuration.import', [
					'format' => 'json',
					'rules' => [
						$import['parametr'] => [
							$expected => 'test'
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'Invalid parameter "/rules/'.$import['parametr'].'/'.$expected.'": a boolean is expected.'
			);
		}

		foreach ($import['unexpected'] as $unexpected) {
			$this->call('configuration.import', [
					'format' => 'json',
					'rules' => [
						$import['parametr'] => [
							$unexpected => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'Invalid parameter "/rules/'.$import['parametr'].'": unexpected parameter "'.$unexpected.'".'
			);
		}
	}

	public static function import_source() {
		return [
			[[
				'format' => 'xml',
				'source' => '' ,
				'error' => 'Cannot read XML: XML is empty.'
			]],
			[[
				'format' => 'xml',
				'source' => 'test' ,
				'error' => 'Cannot read XML: (4) Start tag expected, \'<\' not found [Line: 1 | Column: 1].'
			]],
			[[
				'format' => 'xml',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
							<zabbix_export><date>2016-12-09T07:12:45Z</date></zabbix_export>',
				'error' => 'Invalid tag "/zabbix_export": the tag "version" is missing.'
			]],
			[[
				'format' => 'xml',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
							<zabbix_export><version></version><date>2016-12-09T07:12:45Z</date></zabbix_export>' ,
				'error' => 'Invalid tag "/zabbix_export/version": unsupported version number.'
			]],
			[[
				'format' => 'xml',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
							<zabbix_export><version>3.2</version><date>2016-12-09T07:12:45Z</date>' ,
				// can be different error message text
				'error_contains' => 'Cannot read XML:'
			]],
			[[
				'format' => 'json',
				'source' => '' ,
				// can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: No error.'
				'error_contains' => 'Cannot read JSON: '
			]],
			[[
				'format' => 'json',
				'source' => 'test' ,
				// can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: boolean expected.'
				'error_contains' => 'Cannot read JSON: '
			]],
			[[
				'format' => 'json',
				'source' => '{"zabbix_export":{"date":"2016-12-09T07:29:55Z"}}' ,
				'error' => 'Invalid tag "/zabbix_export": the tag "version" is missing.'
			]],
			[[
				'format' => 'json',
				'source' => '{"zabbix_export":{"version":"","date":"2016-12-09T07:29:55Z"}}' ,
				'error' => 'Invalid tag "/zabbix_export/version": unsupported version number.'
			]],
			[[
				'format' => 'json',
				'source' => '{"export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}' ,
				'error' => 'Invalid tag "/": unexpected tag "export".'
			]],
			[[
				'format' => 'json',
				'source' => '{"export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}' ,
				// can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: unexpected end of data.'
				'error_contains' => 'Cannot read JSON: '
			]]
		];
	}

	/**
	* @dataProvider import_source
	*/
	public function testConfiguration_ImportInvalidSource($data) {
		$result = $this->call('configuration.import', [
				'format' => $data['format'],
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => $data['source']
			],
			true
		);

		// condition for different error message text
		if (array_key_exists('error_contains', $data)) {
			$this->assertContains($data['error_contains'], $result['error']['data']);
		}
		else {
			$this->assertSame($data['error'], $result['error']['data']);
		}
	}

	public static function import_create() {
		return [
			[
				'format' => 'xml',
				'parametr' => 'groups',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-09T07:12:45Z</date>
								<groups>
									<group>
										<name>API host group xml import</name>
									</group>
								</groups>
								</zabbix_export>',
				'sql' => 'select * from groups where name=\'API host group xml import\''
			],
			[
				'format' => 'json',
				'parametr' => 'groups',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T12:29:57Z","groups":[{"name":"API host group json import"}]}}',
				'sql' => 'select * from groups where name=\'API host group json import\''
			],
			[
				'format' => 'xml',
				'parametr' => 'screens',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-12T07:18:00Z</date>
								<screens>
									<screen>
										<name>API screen xml import</name>
										<hsize>1</hsize>
										<vsize>1</vsize>
									</screen>
								</screens>
								</zabbix_export>',
				'sql' => 'select * from screens where name=\'API screen xml import\''
			],
			[
				'format' => 'json',
				'parametr' => 'screens',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-12T07:18:00Z","screens":[{"name":"API screen json import",'
							. '"hsize":"1","vsize":"1"}]}}',
				'sql' => 'select * from screens where name=\'API screen json import\''
			],
			[
				'format' => 'xml',
				'parametr' => 'valueMaps',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-12T07:18:00Z</date>
								<value_maps>
									<value_map>
										<name>API valueMap xml import</name>
										<mappings>
											<mapping>
												<value>1</value>
												<newvalue>Up</newvalue>
											</mapping>
										</mappings>
									</value_map>
								</value_maps>
								</zabbix_export>',
				'sql' => 'select * from valuemaps where name=\'API valueMap xml import\''
			],
			[
				'format' => 'json',
				'parametr' => 'valueMaps',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-12T07:18:00Z","value_maps":[{"name":"API valueMap json import",'
							. '"mappings":[{"value":"1","newvalue":"Up"}]}]}}',
				'sql' => 'select * from valuemaps where name=\'API valueMap json import\''
			]
		];
	}

	/**
	* @dataProvider import_create
	*/
	public function testConfiguration_ImportCreate($format, $parametr, $source, $sql) {
		$result = $this->call('configuration.import', [
				'format' => $format,
				'rules' => [
					$parametr => [
						'createMissing' => true
					]
				],
				'source' => $source
			]
		);

		$this->assertSame(true, $result['result']);
		$this->assertEquals(1, DBcount($sql));
	}

	public static function import_users() {
		return [
			[
				'format' => 'xml',
				'parametr' => 'groups',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-09T07:12:45Z</date>
								<groups>
									<group>
										<name>API host group xml import as non Super Admin</name>
									</group>
								</groups>
								</zabbix_export>',
				'sql' => 'select * from groups where name=\'API host group xml import as non Super Admin\'',
				'expected_error' => 'Only Super Admins can create host groups.'
			],
			[
				'format' => 'json',
				'parametr' => 'groups',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T12:29:57Z","groups":[{"name":"API host group json import as non Super Admin"}]}}',
				'sql' => 'select * from groups where name=\'API host group json import as non Super Admin\'',
				'expected_error' => 'Only Super Admins can create host groups.'
			],
			[
				'format' => 'xml',
				'parametr' => 'valueMaps',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
								<zabbix_export>
								<version>3.2</version>
								<date>2016-12-12T07:18:00Z</date>
								<value_maps>
									<value_map>
										<name>API valueMap xml import as non Super Admin</name>
										<mappings>
											<mapping>
												<value>1</value>
												<newvalue>Up</newvalue>
											</mapping>
										</mappings>
									</value_map>
								</value_maps>
								</zabbix_export>',
				'sql' => 'select * from valuemaps where name=\'API valueMap xml import as non Super Admin\'',
				'expected_error' => 'Only super admins can create value maps.'
			],
			[
				'format' => 'json',
				'parametr' => 'valueMaps',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-12T07:18:00Z","value_maps":[{"name":"API valueMap json import as non Super Admin",'
							. '"mappings":[{"value":"1","newvalue":"Up"}]}]}}',
				'sql' => 'select * from valuemaps where name=\'API valueMap json import as non Super Admin\'',
				'expected_error' => 'Only super admins can create value maps.'
			]
		];
	}

	/**
	* @dataProvider import_users
	*/
	public function testConfiguration_UsersPermissionsToImportCreate($format, $parametr, $source, $sql, $expected_error) {
		$users = ['zabbix-admin', 'zabbix-user'];

		foreach ($users as $username) {
			$this->authorize($username, 'zabbix');
			$this->call('configuration.import', [
					'format' => $format,
					'rules' => [
						$parametr => [
							'createMissing' => true
						]
					],
					'source' => $source
				],
				$expected_error
			);

			$this->assertEquals(0, DBcount($sql));
		}
	}
}
