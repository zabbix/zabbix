<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * @backup hosts, hstgrp
 */
class testConfiguration extends CAPITest {

	public static function export_fail_data() {
		return [
			// Check format parameter.
			[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/": the parameter "format" is missing.'
			],
				[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						]
					],
					'format' => ''
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of "yaml", "xml", "json", "raw".'
			],
			[
				'export' => [
					'options' => [
						'hosts' => [
							'50009'
						]
					],
					'format' => 'æų'
				],
				'expected_error' => 'Invalid parameter "/format": value must be one of "yaml", "xml", "json", "raw".'
			],
			// Check unexpected parameter.
			[
				'export' => [
					'options' => [
						'groups' => [
							'50012'
						]
					],
					'format' => 'test',
					'hosts' => '50009'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "hosts".'
			],
			[
				'export' => [
					'options' => [
						'groups' => [
							'50009'
						],
						'group' => [
							'50009'
						]
					],
					'format' => 'xml'
				],
				'expected_error' => 'Invalid parameter "/options": unexpected parameter "group".'
			],
			// Check missing options parameter.
			[
				'export' => [
					'format' => 'xml'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "options" is missing.'
			],
			// Check prettyprint parameter.
			[
				'export' => [
					'options' => [
						'groups' => [
							'50012'
						]
					],
					'format' => 'yaml',
					'prettyprint' => 'test'
				],
				'expected_error' => 'Invalid parameter "/prettyprint": a boolean is expected.'
			],
			[
				'export' => [
					'options' => [
						'groups' => [
							'50012'
						]
					],
					'format' => 'json',
					'prettyprint' => ''
				],
				'expected_error' => 'Invalid parameter "/prettyprint": a boolean is expected.'
			],
			[
				'export' => [
					'options' => [
						'groups' => [
							'50012'
						]
					],
					'format' => 'yaml',
					'prettyprint' => 'æų'
				],
				'expected_error' => 'Invalid parameter "/prettyprint": a boolean is expected.'
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
			['templates']
		];
	}

	/**
	 * @dataProvider export_string_ids
	 */
	public function testConfiguration_ExportIdsNotNumber($options) {
		$formats = ['xml', 'json', 'yaml'];

		foreach ($formats as $parameter){
			$this->call('configuration.export',
				[
					'options' => [
							$options => [
								$options
							]
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
				[
					'options' => [
							'groups' => []
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
							'groups' => ['11111111111111']
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
							'groups' => ['50012']
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
						'hosts' => ['50009']
					],
					'prettyprint' => false
				]
			],
			[
				[
					'options' => [
						'groups' => ['50012'],
						'hosts' => ['50009']
					]
				]
			],
			[
				[
					'options' => [
						'images' => ['1']
					]
				]
			],
			[
				[
					'options' => [
						'maps' => ['1']
					]
				]
			],
			[
				[
					'options' => [
						'templates' => ['10069']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider export_success_data
	 */
	public function testConfiguration_ExportSuccess($data) {
		$formats = ['xml', 'json', 'yaml'];

		foreach ($formats as $parameter) {
			$this->call('configuration.export', array_merge($data, ['format' => $parameter]));
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
				'expected_error' => 'Invalid parameter "/format": value must be one of "yaml", "xml", "json".'
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
				'expected_error' => 'Invalid parameter "/format": value must be one of "yaml", "xml", "json".'
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
			[
				'import' => [
					'format' => 'json',
					'rules' => [
						'groups' => [
							'createMissing' => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}',
					'prettyprint' => true
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "prettyprint".'
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

	public static function import_rules_parameters() {
		return [
			[[
				'parameter' => 'discoveryRules',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'graphs',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'groups',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'hosts',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'httptests',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'images',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'items',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'maps',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'templateLinkage',
				'expected' => ['createMissing', 'deleteMissing'],
				'unexpected' => ['updateExisting']
			]],
			[[
				'parameter' => 'templates',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'templateDashboards',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'triggers',
				'expected' => ['createMissing', 'deleteMissing', 'updateExisting'],
				'unexpected' => []
			]],
			[[
				'parameter' => 'valueMaps',
				'expected' => ['createMissing', 'updateExisting', 'deleteMissing'],
				'unexpected' => []
			]]
		];
	}

	/**
	 * @dataProvider import_rules_parameters
	 */
	public function testConfiguration_ImportBooleanTypeAndUnexpectedParameters($import) {
		foreach ($import['expected'] as $expected) {
			$this->call('configuration.import', [
					'format' => 'json',
					'rules' => [
						$import['parameter'] => [
							$expected => 'test'
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'Invalid parameter "/rules/'.$import['parameter'].'/'.$expected.'": a boolean is expected.'
			);
		}

		foreach ($import['unexpected'] as $unexpected) {
			$this->call('configuration.import', [
					'format' => 'json',
					'rules' => [
						$import['parameter'] => [
							$unexpected => true
						]
					],
					'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}'
				],
				'Invalid parameter "/rules/'.$import['parameter'].'": unexpected parameter "'.$unexpected.'".'
			);
		}
	}

	public static function import_source() {
		return [
			[[
				'format' => 'xml',
				'source' => '',
				'error' => 'Cannot read XML: XML is empty.'
			]],
			[[
				'format' => 'xml',
				'source' => 'test',
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
							<zabbix_export><version></version><date>2016-12-09T07:12:45Z</date></zabbix_export>',
				'error' => 'Invalid tag "/zabbix_export/version": unsupported version number.'
			]],
			[[
				'format' => 'xml',
				'source' => '<?xml version="1.0" encoding="UTF-8"?>
							<zabbix_export><version>3.2</version><date>2016-12-09T07:12:45Z</date>' ,
				// Can be different error message text.
				'error_contains' => 'Cannot read XML:'
			]],
			// JSON format.
			[[
				'format' => 'json',
				'source' => '',
				// Can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: No error.'
				'error_contains' => 'Cannot read JSON: '
			]],
			[[
				'format' => 'json',
				'source' => 'test',
				// Can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: boolean expected.'
				'error_contains' => 'Cannot read JSON: '
			]],
			[[
				'format' => 'json',
				'source' => '{"zabbix_export":{"date":"2016-12-09T07:29:55Z"}}',
				'error' => 'Invalid tag "/zabbix_export": the tag "version" is missing.'
			]],
			[[
				'format' => 'json',
				'source' => '{"zabbix_export":{"version":"","date":"2016-12-09T07:29:55Z"}}',
				'error' => 'Invalid tag "/zabbix_export/version": unsupported version number.'
			]],
			[[
				'format' => 'json',
				'source' => '{"export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}}',
				'error' => 'Invalid tag "/": unexpected tag "export".'
			]],
			[[
				'format' => 'json',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T07:29:55Z"}',
				// Can be different error message text 'Cannot read JSON: Syntax error.' or 'Cannot read JSON: unexpected end of data.'
				'error_contains' => 'Cannot read JSON: '
			]],
			// YAML format.
			// Empty YAML.
			[[
				'format' => 'yaml',
				'source' => '',
				'error' => 'Cannot read YAML: File is empty.'
			]],
			// Empty YAML.
			[[
				'format' => 'yaml',
				'source' => '---\r\n...',
				'error' => 'Cannot read YAML: Invalid YAML file contents.'
			]],
			// Non UTF-8.
			[[
				'format' => 'yaml',
				'source' => 'æų',
				'error' => 'Cannot read YAML: Invalid YAML file contents.'
			]],
			// No "version" tag.
			[[
				'format' => 'yaml',
				'source' => "---\nzabbix_export:\n  date: \"2020-07-27T12:58:01Z\"\n",
				'error' => 'Invalid tag "/zabbix_export": the tag "version" is missing.'
			]],
			// No indentation before tags.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export: \r\nversion: \"4.0\"\r\ndate: \"2020-08-03T11:38:33Z\"\r\ngroups:\r\nname: \"API host group yaml import\"\r\n...",
				'error' => 'Invalid tag "/": unexpected tag "version".'
			]],
			// Empty "version" value.
			[[
				'format' => 'yaml',
				'source' => "---\nzabbix_export:\n  version: \"\"\n  date: \"2020-07-27T12:58:01Z\"\n",
				'error' => 'Invalid tag "/zabbix_export/version": unsupported version number.'
			]],
			// Invalid first tag.
			[[
				'format' => 'yaml',
				'source' => "---\nexport:\n  version: \"4.0\"\n  date: \"2020-08-03T11:38:33Z\"\n...\n",
				'error' => 'Invalid tag "/": unexpected tag "export".'
			]],
			// Invalid inner tag.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export:\r\n  version: \"5.2\"\r\n  date: \"2020-08-31T14:44:18Z\"\r\n  groups:\r\n  - tag: 'name'\r\n...",
				'error' => 'Invalid tag "/zabbix_export/groups/group(1)": unexpected tag "tag".'
			]],
			// Unclosed quotes after date value.
			[[
				'format' => 'yaml',
				'source' => '---\nzabbix_export:\n  version: \"4.0\"\n  date: \"2020-08-03T11:38:33Z',
				'error' => 'A colon cannot be used in an unquoted mapping value at line 1 (near "---\nzabbix_export:\n  version: \"4.0\"\n  date: \"2020-08-03T11:38:33Z").'
			]],
			// XML contents in YAML file.
			[[
				'format' => 'yaml',
				'source' => '<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<zabbix_export><version>5.0</version><date>2020-08-03T12:36:11Z</date></zabbix_export>\n',
				'error' => 'Cannot read YAML: Invalid YAML file contents.'
			]],
			// Unquoted version value.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export: \r\n  version: 4.0\r\n  date: 2020-08-03T11:38:33Z\r\n...",
				'error' => 'Invalid tag "/zabbix_export/version": a character string is expected.'
			]],
			// No space after colon.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export: \r\n  version:\"4.0\"\r\n  date:\"2020-08-03T11:38:33Z\"\r\n...",
				'error' => 'Invalid tag "/zabbix_export": an array is expected.'
			]],
			// Invalid time and date format.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export:\r\n  version: \"4.0\"\r\n  date: \"2020-08-03T11:38:33\"\r\n...",
				'error' => 'Invalid tag "/zabbix_export/date": "YYYY-MM-DDThh:mm:ssZ" is expected.'
			]],
			// YAML starts from ... instead of ---.
			[[
				'format' => 'yaml',
				'source' => "...\r\nzabbix_export:\r\n  version: \"5.0\"\r\n  date: \"2021-08-03T11:38:33Z\"\r\n...",
				'error' => 'Unable to parse at line 1 (near "...").'
			]],
			// No new line before date tag.
			[[
				'format' => 'yaml',
				'source' => "---\r\nzabbix_export:\r\n  version: \"5.2\",date: \"2020-08-31T14:44:18Z\"\r\n...",
				'error' => 'Unexpected characters near ",date: "2020-08-31T14:44:18Z"" at line 3 (near "version: "5.2",date: "2020-08-31T14:44:18Z"").'
			]],
			// Excessive intendation before "zabbix_export".
			[[
				'format' => 'yaml',
				'source' => "---\r\n  zabbix_export:\r\n  version: \"4.0\"\r\n  date: \"2020-08-03T12:41:17Z\"\r\n...",
				'error' => 'Mapping values are not allowed in multi-line blocks at line 2 (near "  zabbix_export:").'
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

		// Condition for different error message text.
		if (array_key_exists('error_contains', $data)) {
			$this->assertStringContainsString($data['error_contains'], $result['error']['data']);
		}
		else {
			$this->assertSame($data['error'], $result['error']['data']);
		}
	}

	public static function import_create() {
		return [
			[
				'format' => 'xml',
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
										<name>API host group xml import</name>
									</group>
								</groups>
								</zabbix_export>',
				'sql' => 'select * from hstgrp where name=\'API host group xml import\''
			],
			[
				'format' => 'json',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T12:29:57Z","groups":[{"name":"API host group json import"}]}}',
				'sql' => 'select * from hstgrp where name=\'API host group json import\''
			],
			// Full YAML tags without quotes.
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "---\nzabbix_export:\n  version: \"4.0\"\n  date: \"2020-08-03T12:41:17Z\"\n  groups:\n  - name: API host group yaml import\n...\n",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// Full YAML tags with double quotes.
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "---\n\"zabbix_export\":\n  \"version\": \"4.0\"\n  \"date\": \"2020-08-03T12:41:17Z\"\n  \"groups\":\n  - \"name\": \"API host group yaml import\"\n...\n",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// Pretty YAML (without --- and ...).
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "zabbix_export:\r\n  version: \"4.0\"\r\n  date: \"2020-08-03T12:41:17Z\"\r\n  groups:\r\n  - name: API host group yaml import",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// Pretty YAML (without ... in the end).
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "---\nzabbix_export:\r\n  version: \"4.0\"\r\n  date: \"2020-08-03T12:41:17Z\"\r\n  groups:\r\n  - name: API host group yaml import",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// "Ugly" YAML (with new lines after -).
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "---\r\nzabbix_export:\r\n  version: \"4.0\"\r\n  date: \"2020-08-03T12:41:17Z\"\r\n  groups:\r\n  - \r\n    name: API host group yaml import\r\n...",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// JSON contents in YAML file (short, only date and version).
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "{\"zabbix_export\":{\"version\":\"5.0\",\"date\":\"2020-08-03T12:36:39Z\"}}",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			// JSON contents in YAML file (with zabbix tags).
			[
				'format' => 'yaml',
				'rules' => [
					'groups' => [
						'createMissing' => true
					]
				],
				'source' => "{\"zabbix_export\":{\"version\":\"4.0\",\"date\":\"2020-08-03T12:41:17Z\",\"groups\":[{\"name\":\"API host group yaml import\"}]}}",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import\''
			],
			[
				'format' => 'xml',
				'rules' => [
					'valueMaps' => [
						'createMissing' => true
					],
					'hosts' => [
						'createMissing' => true
					]
				],
				'source' => '<?xml version="1.0" encoding="UTF-8"?> <zabbix_export> <version>5.4</version> <date>2016-12-12T07:18:00Z</date> <hosts> <host> <host>API xml import host</host> <name>API xml import host</name> <groups> <group> <name>Linux servers</name> </group> </groups> <valuemaps> <valuemap> <name>API valueMap xml import</name> <mappings> <mapping> <value>1</value> <newvalue>Up</newvalue> </mapping> </mappings> </valuemap> </valuemaps> </host> </hosts> </zabbix_export>',
				'sql' => 'select * from valuemap where name=\'API valueMap xml import\''			],
			[
				'format' => 'json',
				'rules' => [
					'valueMaps' => [
						'createMissing' => true
					],
					'hosts' => [
						'createMissing' => true
					]
				],
				'source' => '{ "zabbix_export": { "version": "5.4", "date": "2016-12-12T07:18:00Z", "hosts": [ { "host": "API json import host", "name": "API json import host", "groups": [ { "name": "Linux servers" } ], "valuemaps": [ { "name": "API valueMap json import", "mappings": [ { "value": "1", "newvalue": "Up" } ] } ] } ] } }',
				'sql' => 'select * from valuemap where name=\'API valueMap json import\''
			],
			[
				'format' => 'yaml',
				'rules' => [
					'valueMaps' => [
						'createMissing' => true
					],
					'hosts' => [
						'createMissing' => true
					]
				],
				'source' => "zabbix_export:\n  version: '5.4'\n  date: '2016-12-12T07:18:00Z'\n  hosts:\n  -\n    host: 'API yaml import host'\n    name: 'API yaml import host'\n    groups:\n    -\n      name: 'Linux servers'\n    valuemaps:\n    -\n      name: 'API valueMap yaml import'\n      mappings:\n      -\n        value: '1'\n        newvalue: Up",
				'sql' => 'select * from valuemap where name=\'API valueMap yaml import\''
			]
		];
	}

	/**
	* @dataProvider import_create
	*/
	public function testConfiguration_ImportCreate($format, $rules, $source, $sql) {
		$result = $this->call('configuration.import', [
				'format' => $format,
				'rules' => $rules,
				'source' => $source
			]
		);

		$this->assertSame(true, $result['result']);
		$this->assertEquals(1, CDBHelper::getCount($sql));
	}

	public static function import_users() {
		return [
			[
				'format' => 'xml',
				'parameter' => 'groups',
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
				'sql' => 'select * from hstgrp where name=\'API host group xml import as non Super Admin\'',
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
			[
				'format' => 'json',
				'parameter' => 'groups',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T12:29:57Z","groups":[{"name":"API host group json import as non Super Admin"}]}}',
				'sql' => 'select * from hstgrp where name=\'API host group json import as non Super Admin\'',
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
			[
				'format' => 'yaml',
				'parameter' => 'groups',
				'source' => "---\nzabbix_export:\n  version: \"4.0\"\n  date: \"2020-08-03T12:41:17Z\"\n  groups:\n  - name: API host group yaml import as non Super Admin\n...\n",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import as non Super Admin\'',
				'expected_error' => 'No permissions to call "hostgroup.create".'
			]
		];
	}

	/**
	* @dataProvider import_users
	*/
	public function testConfiguration_UsersPermissionsToImportCreate($format, $parameter, $source, $sql, $expected_error) {
		$users = ['zabbix-admin', 'zabbix-user'];

		foreach ($users as $username) {
			$this->authorize($username, 'zabbix');
			$this->call('configuration.import', [
					'format' => $format,
					'rules' => [
						$parameter => [
							'createMissing' => true
						]
					],
					'source' => $source
				],
				$expected_error
			);

			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}

	public function testConfiguration_MapImportResetsElementIcons() {
		$mapname = "ZBX-24466";
		$mapid = 2000;
		$selementid = 2000;

		$sql = "INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, grid_size, grid_show, grid_align, label_format, label_type_host, label_type_hostgroup, label_type_trigger, label_type_map, label_type_image, label_string_host, label_string_hostgroup, label_string_trigger, label_string_map, label_string_image, iconmapid, expand_macros, severity_min, userid, private, show_suppressed) VALUES ($mapid, '$mapname', 800, 600, NULL, 0, 0, 0, 1, 0, 0, 50, 1, 1, 0, 2, 2, 2, 2, 2, '', '', '', '', '', NULL, 0, 0, 1, 1, 0)";
		DBexecute($sql);

		$sql = "INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance, elementsubtype, areatype, width, height, viewtype, use_iconmap, evaltype) VALUES ($selementid, $mapid, 10084, 0, 151, 2, 'New element', -1, 0, 0, 2, 2, 0, 0, 200, 200, 0, 0, 0)";
		DBexecute($sql);

		$json = <<<EOF
			{
					"zabbix_export": {
							"version": "6.0",
							"date": "2024-07-16T11:24:28Z",
							"images": [
									{
											"name": "Server_(96)",
											"imagetype": "1",
											"encodedImage": "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"
									}
							],
							"maps": [
									{
											"name": "$mapname",
											"width": "800",
											"height": "600",
											"label_type": "0",
											"label_location": "0",
											"highlight": "0",
											"expandproblem": "1",
											"markelements": "0",
											"show_unack": "0",
											"severity_min": "0",
											"show_suppressed": "0",
											"grid_size": "50",
											"grid_show": "1",
											"grid_align": "1",
											"label_format": "0",
											"label_type_host": "2",
											"label_type_hostgroup": "2",
											"label_type_trigger": "2",
											"label_type_map": "2",
											"label_type_image": "2",
											"label_string_host": "",
											"label_string_hostgroup": "",
											"label_string_trigger": "",
											"label_string_map": "",
											"label_string_image": "",
											"expand_macros": "0",
											"background": [],
											"iconmap": [],
											"urls": [],
											"selements": [
													{
															"elementtype": "0",
															"elements": [
																	{
																			"host": "Zabbix server"
																	}
															],
															"label": "New element",
															"label_location": "-1",
															"x": "0",
															"y": "0",
															"elementsubtype": "0",
															"areatype": "0",
															"width": "200",
															"height": "200",
															"viewtype": "0",
															"use_iconmap": "0",
															"selementid": "$selementid",
															"icon_off": {
																	"name": "Server_(96)"
															},
															"icon_on": [],
															"icon_disabled": [],
															"icon_maintenance": [],
															"urls": [],
															"evaltype": "0"
													}
											],
											"shapes": [],
											"lines": [],
											"links": []
									}
							]
					}
			}
		EOF;

		$result = $this->call('configuration.import', [
			'format' => 'json',
			'rules' => ['maps' => ['createMissing' => true, 'updateExisting' => true]],
			'source' => $json
		]);

		$sql = "SELECT iconid_on, iconid_disabled, iconid_maintenance from sysmaps_elements where sysmapid = $mapid";
		$effect = CDBHelper::getRow($sql);
		$this->assertEquals([
			'iconid_on' => '0',
			'iconid_disabled' => '0',
			'iconid_maintenance' => '0',
		], $effect);
	}
}
