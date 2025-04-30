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
						'host_groups' => [
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
						'host_groups' => [
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
			['host_groups'],
			['template_groups'],
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
						'host_groups' => []
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
						'host_groups' => ['11111111111111']
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
						'host_groups' => ['50012']
					],
					'prettyprint' => true
				]
			],
			[
				[
					'options' => [
						'template_groups' => ['52013']
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
						'host_groups' => ['50012'],
						'hosts' => ['50009']
					]
				]
			],
			[
				[
					'options' => [
						'template_groups' => ['52013'],
						'templates' => ['50010']
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
						'host_groups' => [
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
						'host_groups' => [
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
						'host_groups' => [
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
						'host_groups' => [
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
						'host_groups' => [
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
						'host_groups' => [
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
						'host_groups' => [
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
				'parameter' => 'host_groups',
				'expected' => ['createMissing', 'updateExisting'],
				'unexpected' => ['deleteMissing']
			]],
			[[
				'parameter' => 'template_groups',
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
			// Excessive indentation before "zabbix_export".
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
					'host_groups' => [
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
			// test for host groups
			[
				'format' => 'xml',
				'parameter' => 'host_groups',
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
				'parameter' => 'host_groups',
				'source' => '{"zabbix_export":{"version":"3.2","date":"2016-12-09T12:29:57Z","groups":[{"name":"API host group json import as non Super Admin"}]}}',
				'sql' => 'select * from hstgrp where name=\'API host group json import as non Super Admin\'',
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
			[
				'format' => 'yaml',
				'parameter' => 'host_groups',
				'source' => "---\nzabbix_export:\n  version: \"4.0\"\n  date: \"2020-08-03T12:41:17Z\"\n  groups:\n  - name: API host group yaml import as non Super Admin\n...\n",
				'sql' => 'select * from hstgrp where name=\'API host group yaml import as non Super Admin\'',
				'expected_error' => 'No permissions to call "hostgroup.create".'
			],
				// test for template groups
			[
			'format' => 'xml',
			'parameter' => 'template_groups',
			'source' => '<?xml version="1.0" encoding="UTF-8"?>
						<zabbix_export>
							<version>5.4</version>
							<date>2022-04-05T13:50:12Z</date>
							<groups>
								<group>
									<uuid>1b086ec667184dff8015c7f7bb5c5978</uuid>
									<name>API template group xml import as non Super Admin</name>
								</group>
							</groups>
							<templates>
								<template>
									<uuid>17e68f86419d40a18bb3b1d1a876d231</uuid>
									<template>API xml import template as non Super Admin</template>
									<name>API xml import template as non Super Admin</name>
									<groups>
										<group>
											<name>API template group xml import as non Super Admin</name>
										</group>
									</groups>
							</template>
							</templates>
						</zabbix_export>',
			'sql' => 'select * from hstgrp where name=\'API template group xml import as non Super Admin\'',
			'expected_error' => 'No permissions to call "templategroup.create".'
			],
			[
				'format' => 'json',
				'parameter' => 'template_groups',
				'source' => '{"zabbix_export": {"version": "5.4","date": "2022-04-05T13:57:36Z","groups": [{"uuid": "1b086ec667184dff8015c7f7bb5c5978","name": "API template group xml import as non Super Admin"}],"templates": [{"uuid": "17e68f86419d40a18bb3b1d1a876d231","template": "API xml import template as non Super Admin","name": "API xml import template as non Super Admin","groups": [{"name": "API template group xml import as non Super Admin"}]}]}}',
				'sql' => 'select * from hstgrp where name=\'API template group json import as non Super Admin\'',
				'expected_error' => 'No permissions to call "templategroup.create".'
			],
			[
				'format' => 'yaml',
				'parameter' => 'template_groups',
				'source' => "{zabbix_export: {version: '5.4', date: '2022-04-05T13:59:57Z', groups: [{uuid: 1b086ec667184dff8015c7f7bb5c5978, name: API template group xml import as non Super Admin}], templates: [{uuid: 17e68f86419d40a18bb3b1d1a876d231, template: API xml import template as non Super Admin, name: API xml import template as non Super Admin, groups: [{name: API template group xml import as non Super Admin}]}]}}",
				'sql' => 'select * from hstgrp where name=\'API template group yaml import as non Super Admin\'',
				'expected_error' => 'No permissions to call "templategroup.create".'
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
							'createMissing' => true,
							'updateExisting' => false
						]
					],
					'source' => $source
				],
				$expected_error
			);

			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}
}
