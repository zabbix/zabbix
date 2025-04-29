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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CPreprocessingBehavior.php';

/**
 * Base class for Preprocessing tests.
 */
abstract class testFormPreprocessing extends CWebTest {

	/**
	 * Attach MessageBehavior and PreprocessingBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CPreprocessingBehavior::class
		];
	}

	public $link;
	public $ready_link;
	public $success_message;
	public $button;
	public $fail_message;

	const INHERITED_ITEMID			= 15094;	// 'testInheritanceItemPreprocessing'
	const CLONE_ITEMID				= 99102;	// 'Simple form test host' -> 'testFormItem'
	const INHERITED_ITEM_PROTOTYPE	= 15096;	// 'testInheritanceDiscoveryRule' -> 'testInheritanceItemPrototypePreprocessing'
	const CLONE_ITEM_PROTOTYPEID	= 23804;	// 'Discovery rule for triggers filtering' -> 'Discovered item {#TEST}'
	const CLONE_PREPROCESSING = [
		[
			'type' => '1',
			'params' => '123',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '2',
			'params' => 'abc',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '3',
			'params' => 'def',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '4',
			'params' => '1a2b3c',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '5',
			'params' => "regular expression pattern \noutput template",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '6',
			'params' => '',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '7',
			'params' => '',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '8',
			'params' => '',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '9',
			'params' => '',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '11',
			'params' => '/document/item/value/text()',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '12',
			'params' => '$.document.item.value parameter.',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '13',
			'params' => "-5\n3",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '14',
			'params' => 'regular expression pattern for matching',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '15',
			'params' => 'regular expression pattern for not matching',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '16',
			'params' => '/json/path',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '17',
			'params' => '/xml/path',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '18',
			'params' => "regular expression pattern for error matching \ntest output",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '20',
			'params' => '7',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
						[
			'type' => '21',
			'params' => 'test script',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '23',
			'params' => 'metric',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '24',
			'params' => ".\n/\n1",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '25',
			'params' => "1\n2",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '26',
			'params' => "1\ntest",
			'error_handler' => 1,
			'error_handler_params' => ''
		],
		[
			'type' => '27',
			'params' => '',
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '28',
			'params' => "OID\n1",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '29',
			'params' => "test\nOID\n1",
			'error_handler' => 0,
			'error_handler_params' => ''
		],
		[
			'type' => '30',
			'params' => '1',
			'error_handler' => 0,
			'error_handler_params' => ''
		]
	];

	/**
	 * Sets rows per page to 200 so that all items are always shown on screen.
	 */
	protected function setRowsPerPage() {
		DB::update('users', [
			'values' => ['rows_per_page' => 200],
			'where' => ['userid' => 1] // Admin (super admin role)
		]);
	}

	/*
	 * GOOD scenario data common for all - items, item prototypes and LLD.
	 *
	 * Comments are formatted like this:
	 * {Preprocessing step category} - {Preprocessing step name}.
	 * The category is shown in UI.
	 */
	public static function getCommonPreprocessingCreateData() {
		return [
			// Text - Regular expression.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Regular expression simple',
						'Key' => 'regular-expression-simple[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'test', 'parameter_2' => 'TEST' ]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Regular expression complex',
						'Key' => 'regular-expression-complex[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Regular expression',
							'parameter_1' => '^(\d{4}-\d{1,2}-[0123]{1}\d) test ([💚💙])',
							'parameter_2' => 'date \1 emoji \2'
						]
					]
				]
			],
			// Text - Replace.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Replace',
						'Key' => 'replace[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => 'old string ', 'parameter_2' => 'NEW👍string ']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Replace - empty replacement',
						'Key' => 'replace-empty-replacement[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => 'replacement']
					]
				]
			],
			// Structured data - XML XPath.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'XML XPath',
						'Key' => 'xml-xpath[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'XML XPath', 'parameter_1' => '//div[contains(@class, "test")]']
					]
				]
			],
			// Structured data - JSONPath.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'JSONPath',
						'Key' => 'jsonpath[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'JSONPath', 'parameter_1' => '$.test[:1].type']
					]
				]
			],
			// Structured data - CSV to JSON.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'CSV to JSON empty parameters',
						'Key' => 'csv-to-json-empty-parameters[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'CSV to JSON']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'CSV to JSON with default parameters',
						'Key' => 'csv-to-json-with-default-parameters[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'CSV to JSON', 'parameter_1' => ',', 'parameter_2' => '"', 'parameter_3' => true]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'CSV to JSON custom parameters',
						'Key' => 'csv-to-json-custom-parameters[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'CSV to JSON', 'parameter_1' => ' ', 'parameter_2' => "'", 'parameter_3' => false]
					]
				]
			],
			// Structured data - XML to JSON.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'XML to JSON',
						'Key' => 'xml-to-json[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'XML to JSON']
					]
				]
			],
			// SNMP - SNMP walk value.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk value - Unchanged',
						'Key' => 'snmp-walk-value-unchanged[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk value',
							'parameter_1' => '1.3.6.1.2.1.1.1',
							'parameter_2' => 'Unchanged'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk value - UTF-8 from Hex-STRING',
						'Key' => 'snmp-walk-value-utf8-hex[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk value',
							'parameter_1' => 'SNMPv2-MIB::sysName.0',
							'parameter_2' => 'UTF-8 from Hex-STRING'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk value - MAC from Hex-STRING',
						'Key' => 'snmp-walk-value-mac-hex[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk value',
							'parameter_1' => 'iso.org.dod.internet.mgmt.mib-2.system.sysDescr',
							'parameter_2' => 'MAC from Hex-STRING'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk value - Integer from BITS',
						'Key' => 'snmp-walk-value-int-bits[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk value',
							'parameter_1' => '1.2.3.4.5.6.7.8.9',
							'parameter_2' => 'Integer from BITS'
						]
					]
				]
			],
			// SNMP - SNMP walk to JSON.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - Unchanged',
						'Key' => 'snmp-walk-to-json-unchanged[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk to JSON',
							'parameter_table_1_1' => 'test',
							'parameter_table_1_2' => '1.3.6.1.2.1.1.1',
							'parameter_table_1_3' => 'Unchanged'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - UTF-8 from Hex-STRING',
						'Key' => 'snmp-walk-to-json-uft8-hex[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk to JSON',
							'parameter_table_1_1' => 'fieldName',
							'parameter_table_1_2' => 'SNMPv2-MIB::sysName.0',
							'parameter_table_1_3' => 'UTF-8 from Hex-STRING'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - MAC from Hex-STRING',
						'Key' => 'snmp-walk-to-json-mac-hex[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk to JSON',
							'parameter_table_1_1' => '1234',
							'parameter_table_1_2' => 'iso.org.dod.internet.mgmt.mib-2.system.sysDescr',
							'parameter_table_1_3' => 'MAC from Hex-STRING'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - Integer from BITS',
						'Key' => 'snmp-walk-to-json-int-bits[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'SNMP walk to JSON',
							'parameter_table_1_1' => 'test.test',
							'parameter_table_1_2' => '1.2.3.4.5.6.7.8.9',
							'parameter_table_1_3' => 'Integer from BITS'
						]
					]
				]
			],
			// SNMP - SNMP get value.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP get value - UTF-8 from Hex-STRING',
						'Key' => 'snmp-get-value-utf8-hex[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP get value', 'parameter_1' => 'UTF-8 from Hex-STRING']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP get value - MAC from Hex-STRING',
						'Key' => 'snmp-get-value-mac-hex[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP get value', 'parameter_1' => 'MAC from Hex-STRING']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'SNMP get value - Integer from BITS',
						'Key' => 'snmp-get-value-int-bits[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP get value',	'parameter_1' => 'Integer from BITS']
					]
				]
			],
			// Custom scripts - JavaScript.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'JavaScript',
						'Key' => 'javascript[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'JavaScript', 'parameter_1' => 'alert("hi!");']
					]
				]
			],
			// Validation - Matches regular expression.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Matches regular expression',
						'Key' => 'matches-regular-expression[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Matches regular expression', 'parameter_1' => '^test[123].*\d{4}-\d{1,2}-[0123]{1}\d$']
					]
				]
			],
			// Validation - Does not match regular expression.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Does not match regular expression',
						'Key' => 'does-not-match-regular-expression[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Does not match regular expression', 'parameter_1' => '^test[123].*\d{4}-\d{1,2}-[0123]{1}\d$']
					]
				]
			],
			// Validation - Check for error in JSON.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for error in JSON',
						'Key' => 'check-for-error-in-json[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error in JSON', 'parameter_1' => '$.path.to.node']
					]
				]
			],
			// Validation - Check for error in XML.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for error in XML',
						'Key' => 'check-for-error-in-xml[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error in XML', 'parameter_1' => '//div[contains(@class, "test")]']
					]
				]
			],
			// Throttling - Discard unchanged with heartbeat.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat',
						'Key' => 'discard-unchanged-with-heartbeat[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '2']
					]
				]
			]
		];
	}

	/*
	 * GOOD scenario data for items and item prototypes ONLY (not LLD).
	 *
	 * Comments are formatted like this:
	 * {Preprocessing step category} - {Preprocessing step name}.
	 * The category is shown in UI.
	 */
	public function getItemPreprocessingCreateData() {
		return array_merge($this->getCommonPreprocessingCreateData(), [
			// Text - Trim.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Trim',
						'Key' => 'trim[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Trim', 'parameter_1' => ' \t\\\\']
					]
				]
			],
			// Text - Right trim.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Right trim',
						'Key' => 'right-trim[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => '\n']
					]
				]
			],
			// Text - Left trim.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Left trim',
						'Key' => 'left-trim[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Left trim', 'parameter_1' => ' ']
					]
				]
			],
			// Arithmetic - Custom multiplier.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Custom multiplier',
						'Key' => 'custom-multiplier[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '1.23']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Custom multiplier - scientific notation',
						'Key' => 'custom-multiplier-scientific[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '1.5617497E+20']
					]
				]
			],
			// Change - Simple change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Simple change',
						'Key' => 'simple-change[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Simple change']
					]
				]
			],
			// Change - Change per second.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Change per second',
						'Key' => 'change-per-second[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Change per second']
					]
				]
			],
			// Numeral systems - Boolean to decimal.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Boolean to decimal',
						'Key' => 'boolean-to-decimal[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Boolean to decimal']
					]
				]
			],
			// Numeral systems - Octal to decimal.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Octal to decimal',
						'Key' => 'octal-to-decimal[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Octal to decimal']
					]
				]
			],
			// Numeral systems - Hexadecimal to decimal.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Hexadecimal to decimal',
						'Key' => 'hexadecimal-to-decimal[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Hexadecimal to decimal']
					]
				]
			],
			// Validation - In range.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'In range negative float',
						'Key' => 'in-range-negative-float[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '-3.5', 'parameter_2' => '-1.5']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'In range zero',
						'Key' => 'in-range-zero[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '0', 'parameter_2' => '1']
					]
				]
			],
			// Validation - Check for error using regular expression.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for error using regular expression',
						'Key' => 'check-for-error-using-regular-expression[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Check for error using regular expression',
							'parameter_1' => '^test[123].*(\d{4}-\d{1,2}-[0123]{1})\d$',
							'parameter_2' => 'output \1'
						]
					]
				]
			],
			// Validation - Check for not supported value.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - any error',
						'Key' => 'check-for-not-supported-value-any-error[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'any error']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error matches',
						'Key' => 'check-for-not-supported-value-error-matches[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => '^test.*$']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error does not match',
						'Key' => 'check-for-not-supported-value-error-does-not-match[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => '^test.*$']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error matches + any error',
						'Key' => 'check-for-not-supported-value-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => '^test.*$'],
						['type' => 'Check for not supported value', 'parameter_1' => 'any error']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error matches + error matches',
						'Key' => 'check-for-not-supported-value-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => '^test.*$'],
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => 'different']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error matches + error matches (equal parameters)',
						'Key' => 'check-for-not-supported-value-3[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => 'equal'],
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => 'equal']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error does not match + any error',
						'Key' => 'check-for-not-supported-value-4[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => '^test.*$'],
						['type' => 'Check for not supported value', 'parameter_1' => 'any error']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error does not match + error does not match',
						'Key' => 'check-for-not-supported-value-5[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => '^test.*$'],
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => 'different']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - error does not match + error does not match (equal parameters)',
						'Key' => 'check-for-not-supported-value-6[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => 'equal'],
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => 'equal']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check for not supported value - all types',
						'Key' => 'check-for-not-supported-value-all-types[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value', 'parameter_1' => 'error does not match', 'parameter_2' => 'aa'],
						['type' => 'Check for not supported value', 'parameter_1' => 'error matches', 'parameter_2' => '^test.*$'],
						['type' => 'Check for not supported value', 'parameter_1' => 'any error']
					]
				]
			],
			// Throttling - Discard unchanged.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Discard unchanged',
						'Key' => 'discard-unchanged[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged']
					]
				]
			],
			// All steps at once.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'All steps at once',
						'Key' => 'all-steps-at-once[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'XML XPath', 'parameter_1' => 'def'],
						['type' => 'JSONPath', 'parameter_1' => 'def'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'XML to JSON'],
						['type' => 'SNMP walk value', 'parameter_1' => '1.2.3', 'parameter_2' => 'UTF-8 from Hex-STRING'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => 'abc', 'parameter_table_1_2' => '123'],
						['type' => 'SNMP get value', 'parameter_1' => 'MAC from Hex-STRING'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Simple change'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test', 'parameter_2' => 'abc'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label',
								'parameter_3' => 'label_name'
						]
					]
				]
			],
			// Many steps for screenshot.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Many steps for screenshot',
						'Key' => 'many-steps-for-screenshot[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'XML to JSON'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Simple change'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => 'label',
								'parameter_3' => 'label_name'
						]
					],
					'screenshot' => true
				]
			],
			// Unicode symbols.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Unicode symbols',
						'Key' => 'unicode-symbols[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '01_<!@"\'&nbsp;🙂🙃', 'parameter_2' => '02_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Replace', 'parameter_1' => '03_<!@"\'&nbsp;🙂🙃', 'parameter_2' => '04_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Trim', 'parameter_1' => '05_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Right trim', 'parameter_1' => '06_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Left trim', 'parameter_1' => '07_<!@"\'&nbsp;🙂🙃'],
						['type' => 'XML XPath', 'parameter_1' => '08_<!@"\'&nbsp;🙂🙃'],
						['type' => 'JSONPath', 'parameter_1' => '09_<!@"\'&nbsp;🙂🙃'],
						['type' => 'CSV to JSON','parameter_1' => '<', 'parameter_2' => '<'],
						['type' => 'SNMP walk value', 'parameter_1' => '10_<!@"\'&nbsp;🙂🙃'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => '11_<!@"\'&nbsp;🙂🙃', 'parameter_table_1_2' => '12_<!@"\'&nbsp;🙂🙃'],
						['type' => 'JavaScript', 'parameter_1' => '13_5d!@#$%^&*()-='],
						['type' => 'Matches regular expression', 'parameter_1' => '14_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Does not match regular expression', 'parameter_1' => '15_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Check for error in JSON', 'parameter_1' => '16_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Check for error in XML', 'parameter_1' => '17_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Check for error using regular expression', 'parameter_1' => '18_<!@"\'&nbsp;🙂🙃', 'parameter_2' => '19_<!@"\'&nbsp;🙂🙃']
					]
				]
			],
			// Duplicate steps.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Duplicate steps',
						'Key' => 'duplicate-steps[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'XML XPath', 'parameter_1' => 'def'],
						['type' => 'XML XPath', 'parameter_1' => 'def'],
						['type' => 'JSONPath', 'parameter_1' => 'def'],
						['type' => 'JSONPath', 'parameter_1' => 'def'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'XML to JSON'],
						['type' => 'XML to JSON'],
						['type' => 'SNMP walk value', 'parameter_1' => '1.2.3', 'parameter_2' => 'UTF-8 from Hex-STRING'],
						['type' => 'SNMP walk value', 'parameter_1' => '1.2.3', 'parameter_2' => 'UTF-8 from Hex-STRING'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => 'abc', 'parameter_table_1_2' => '123'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => 'abc', 'parameter_table_1_2' => '123'],
						['type' => 'SNMP get value', 'parameter_1' => 'MAC from Hex-STRING'],
						['type' => 'SNMP get value', 'parameter_1' => 'MAC from Hex-STRING'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Boolean to decimal'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test', 'parameter_2' => 'abc'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test', 'parameter_2' => 'abc']
					]
				]
			],
			// User macros.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item with preprocessing rule with user macro',
						'Key' => 'item-user-macro[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => '{$TEST1}', 'parameter_2' => '{$MACRO2}'],
						['type' => 'Regular expression', 'parameter_1' => '{$DELIM}(.*)', 'parameter_2' => '\1'],
						['type' => 'Trim', 'parameter_1' => '{$DELIM}'],
						['type' => 'Right trim', 'parameter_1' => '{$MACRO}'],
						['type' => 'Left trim', 'parameter_1' => '{$USER}'],
						['type' => 'XML XPath', 'parameter_1' => 'number(/values/Item/value[../key=\'{$DELIM}\'])'],
						['type' => 'JSONPath', 'parameter_1' => '$.data[\'{$KEY}\']'],
						['type' => 'Custom multiplier', 'parameter_1' => '{$VALUE}'],
						['type' => 'In range', 'parameter_1' => '{$FROM}', 'parameter_2' => '{$TO}'],
						['type' => 'Matches regular expression', 'parameter_1' => '{$EXPRESSION}(.*)'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$REGEXP}(.+)'],
						['type' => 'JavaScript', 'parameter_1' => '{$JAVASCRIPT}'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$USERMACRO}'],
						['type' => 'Check for error in XML', 'parameter_1' => '/tmp/{$PATH}'],
						['type' => 'Check for error using regular expression', 'parameter_1' => '^{$REGEXP}(.+)', 'parameter_2' => '\0'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '{$SECONDS}'],
						['type' => 'Prometheus to JSON', 'parameter_1' => '{$PATTERN}']
					]
				]
			],
			// Whitespace.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item with spaces in preprocessing',
						'Key' => 'item-spaces-preprocessing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => '   test text  ', 'parameter_2' => '   replacement 1  '],
						['type' => 'Regular expression', 'parameter_1' => '  pattern    ', 'parameter_2' => '   \1   '],
						['type' => 'Right trim', 'parameter_1' => '    22   '],
						['type' => 'Left trim', 'parameter_1' => '   33  '],
						['type' => 'Trim', 'parameter_1' => '   0    '],
						['type' => 'XML XPath', 'parameter_1' => '   number(/values/Item)    '],
						['type' => 'JSONPath', 'parameter_1' => '    $.data.key    '],
						['type' => 'Matches regular expression', 'parameter_1' => '  expression    '],
						['type' => 'Does not match regular expression', 'parameter_1' => '   not_expression   '],
						['type' => 'JavaScript', 'parameter_1' => "   Test line 1  \n   Test line 2 \n   Test line  3   \n   \n "],
						['type' => 'Check for error in JSON', 'parameter_1' => '   $.error     '],
						['type' => 'Check for error in XML', 'parameter_1' => '   /tmp/path/   '],
						['type' => 'Check for error using regular expression', 'parameter_1' => '   expression    ', 'parameter_2' => '    0      ']
					]
				]
			]
		]);
	}

	/*
	 * BAD scenario data, common for all - items, item prototypes and LLD.
	 *
	 * Comments are formatted like this:
	 * {Preprocessing step category} - {Preprocessing step name}.
	 * The category is shown in UI.
	 */
	public static function getCommonPreprocessingValidationData() {
		return [
			// Text - Regular expression.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Regular expression - missing both parameters',
						'Key' => 'regex-empty[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Regular expression - missing pattern',
						'Key' => 'regex-missing-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_2' => 'output']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Regular expression - missing output',
						'Key' => 'regex-missing-output[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
				]
			],
			// Text - Replace.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Replace - missing search string',
						'Key' => 'replace-missing-search-string[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_2' => 'replacement']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Structured data - XML XPath.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'XML XPath missing',
						'Key' => 'xpath-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'XML XPath']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Structured data - JSONPath.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'JSONPath missing',
						'Key' => 'jsonpath-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'JSONPath']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// SNMP - SNMP walk value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SNMP walk value - missing OID',
						'Key' => 'snmp-walk-value-missing-oid[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP walk value']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// SNMP - SNMP walk to JSON.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - missing all fields',
						'Key' => 'snmp-walk-to-json-missing-all[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP walk to JSON']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - missing Field name',
						'Key' => 'snmp-walk-to-json-missing-name[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP walk to JSON', 'parameter_table_1_2' => '1.3.6.1.2.1.1.1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SNMP walk to JSON - missing OID',
						'Key' => 'snmp-walk-to-json-missing-oid[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => 'test']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
				]
			],
			// Custom scripts - JavaScript.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'JavaScript missing',
						'Key' => 'javascript-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'JavaScript']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Validation - Does not match regular expression.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Does not match regular expression - missing parameter',
						'Key' => 'does-not-match-regular-expression-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Does not match regular expression']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Validation - Check for error in JSON.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for error in JSON - missing parameter',
						'Key' => 'check-for-error-in-json-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error in JSON']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Throttling - Discard unchanged with heartbeat.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - conflict with different values',
						'Key' => 'discard-unchanged-with-heartbeat-conflict-different[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '2']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist '.
						'within the combinations of (type)=((19, 20)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - missing seconds',
						'Key' => 'discard-unchanged-with-heartbeat-missing-seconds[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - string',
						'Key' => 'discard-unchanged-with-heartbeat-string[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => 'abc']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - string with symbols',
						'Key' => 'discard-unchanged-with-heartbeat-string-symbols[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '3g!@#$%^&*()-=✨']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - decimal seconds',
						'Key' => 'discard-unchanged-with-heartbeat-decimal[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1.5']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - comma as decimal point',
						'Key' => 'discard-unchanged-with-heartbeat-comma[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1,5']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - negative value',
						'Key' => 'discard-unchanged-with-heartbeat-negative[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '-3']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - value zero',
						'Key' => 'discard-unchanged-with-heartbeat-zero[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '0']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - value over maximum',
						'Key' => 'discard-unchanged-with-heartbeat-max[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '788400001']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": value must be one of 1-788400000.'
				]
			]
		];
	}

	/*
	 * BAD scenario data for items and item prototypes ONLY (not LLD).
	 *
	 * Comments are formatted like this:
	 * {Preprocessing step category} - {Preprocessing step name}.
	 * The category is shown in UI.
	 */
	public function getItemPreprocessingValidationData() {
		return array_merge($this->getCommonPreprocessingValidationData(), [
			// Text - Trim.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Trim - missing list of characters',
						'Key' => 'trim-empty[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Trim']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Text - Right trim.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Right trim - missing list of characters',
						'Key' => 'right-trim-empty[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Right trim']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Text - Left trim.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Left trim - missing list of characters',
						'Key' => 'left-trim-empty[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Left trim']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Arithmetic - Custom multiplier.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Custom multiplier - missing multiplier',
						'Key' => 'custom-multiplier-missing-multiplier[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Custom multiplier - string multiplier',
						'Key' => 'custom-multiplier-string-multiplier[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => 'abc']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Custom multiplier - comma as decimal point',
						'Key' => 'custom-multiplier-comma[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '0,0']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Custom multiplier - invalid scientific notation',
						'Key' => 'symbols-multiplier[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '3.2e+12<$@✨']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			// Change - Simple change.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Simple change - duplicate',
						'Key' => 'simple-change-duplicate[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Simple change']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((9, 10)).'
				]
			],
			// Change - Change per second.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Change per second - duplicate',
						'Key' => 'change-per-second-duplicate[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Change per second']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((9, 10)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Change per second - conflict with simple change',
						'Key' => 'change-per-second-conflict[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Change per second']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((9, 10)).'
				]
			],
			// Validation - In range.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range - missing all',
						'Key' => 'in-range-missing-all[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range - strings',
						'Key' => 'in-range-strings[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => 'abc', 'parameter_2' => 'def']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range - symbols',
						'Key' => 'in-range-symbols[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1a!@#$%^&*()-=✨', 'parameter_2' => '2b!@#$%^&*()-=✨']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range - comma for decimal point',
						'Key' => 'in-range-comma[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1,5', 'parameter_2' => '-3,5']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'In range - invalid interval',
						'Key' => 'in-range-invalid-interval[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '8', 'parameter_2' => '-8']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be less than or equal '.
							'to the value of parameter "/1/preprocessing/1/params/1".'
				]
			],
			// Validation - Matches regular expression.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Matches regular expression - missing parameter',
						'Key' => 'matches-regular-expression-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Matches regular expression']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Validation - Check for error in XML.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for error in XML - missing parameter',
						'Key' => 'check-for-error-in-xml-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error in XML']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			// Validation - Check for error using regular expression.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for error using regular expression - missing all',
						'Key' => 'check-for-error-using-regex-missing[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for error using regular expression - missing pattern',
						'Key' => 'check-for-error-using-regex-missing-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_2' => 'test']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for error using regular expression - missing output',
						'Key' => 'check-for-error-using-regex-missing-output[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/2": cannot be empty.'
				]
			],
			// Validation - Check for not supported value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Check for not supported value - duplicate',
						'Key' => 'check-for-not-supported-duplicate[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Check for not supported value'],
						['type' => 'Check for not supported value']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": value (type, params)=(26, -1) already exists.'
				]
			],
			// Throttling - Discard unchanged.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged - duplicate',
						'Key' => 'discard-unchanged-duplicate[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((19, 20)).'
				]
			],
			// Throttling - Discard unchanged with heartbeat.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - duplicate',
						'Key' => 'discard-unchanged-with-heartbeat-duplicate[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((19, 20)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Discard unchanged with heartbeat - conflict',
						'Key' => 'discard-unchanged-with-heartbeat-conflict[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((19, 20)).'
				]
			]
		]);
	}

	/*
	 * "Prometheus to JSON" data for item, item prototype and LLD.
	 */
	public static function getPrometheustoJSONData() {
		return [
			// Prometheus to JSON validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter starts with digits',
						'Key' => 'json-prometeus-digits-first-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '1name_of_metric']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong equals operator',
						'Key' => 'json-prometeus-wrong-equals-operator[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}=1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator >',
						'Key' => 'json-prometeus-unsupported-operator-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}>1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator <',
						'Key' => 'json-prometeus-unsupported-operator-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}<1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator !==',
						'Key' => 'json-prometeus-unsupported-operator-3[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}!==1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator >=',
						'Key' => 'json-prometeus-unsupported-operator-4[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}>=1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported operator =<',
						'Key' => 'json-prometeus-unsupported-operator-5[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}=<1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON duplicate metric condition',
						'Key' => 'json-duplicate-metric-condition[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_system{__name__="metric_name"}']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong parameter - space',
						'Key' => 'json-wrong-parameter-space[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu usage_system']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON wrong parameter - slash',
						'Key' => 'json-wrong-parameter-slash[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu\\']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong parameter - digits',
						'Key' => 'json-wrong-parameter-digits[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '123']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - pipe',
						'Key' => 'json-wrong-parameter-pipe[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'metric==1e|5']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - slash',
						'Key' => 'json-wrong-parameter-slash[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label="value\"}']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item duplicate Prometeus to JSON steps',
						'Key' => 'duplicate-prometheus-to-json-steps[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system_1'],
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system_1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((22, 23)).'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON empty first parameter',
						'Key' => 'json-prometeus-empty-first-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter +inf',
						'Key' => 'json-prometeus-plus-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system==+inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter inf',
						'Key' => 'json-prometeus-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter -inf',
						'Key' => 'json-prometeus-negative-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__=~"<regex>"}==-inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter nan',
						'Key' => 'json-prometeus-nan[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==nan']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter exp',
						'Key' => 'json-prometeus-exp[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => 'cpu_usage_system==3.5180e+11']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameter ==1',
						'Key' => 'json-prometeus-neutral-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameters ==+1',
						'Key' => 'json-prometeus-positive-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==+1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON first parameters ==-1',
						'Key' => 'json-prometeus-negative-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="metric_name"}==-1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON label operator =',
						'Key' => 'json-prometeus-label-operator-equal-strong[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name="name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON label operator =~',
						'Key' => 'json-prometeus-label-operator-contains[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name=~"name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON slashes in pattern',
						'Key' => 'json-prometeus-slashes-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label="value\\\\"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameter 1',
						'Key' => 'json-prometeus-macros-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{$METRIC_NAME}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameter 2',
						'Key' => 'json-prometeus-macros-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{__name__="{$METRIC_NAME}"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameters 3',
						'Key' => 'json-prometeus-macros-3[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{{$LABEL_NAME}="<label value>"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON user macros in parameters 4',
						'Key' => 'json-prometeus-macros-4[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name="{$LABEL_VALUE}"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported label operator !=',
						'Key' => 'json-prometeus-unsupported-label-operator-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name!="regex_pattern"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus to JSON unsupported label operator !~',
						'Key' => 'json-prometeus-unsupported-label-operator-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name!~"<regex>"}']
					]
				]
			]
		];
	}

	/*
	 * Prometheus data for item and item prototype (included preprocessing step "Prometheus pattern").
	 */
	public function getPrometheusData() {
		return array_merge($this->getPrometheustoJSONData(), [
			// Prometheus pattern validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus empty first parameter',
						'Key' => 'prometeus-empty-first-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Prometheus space in pattern',
						'Key' => 'prometheus-space-in-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu usage_metric']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Prometheus only digits in pattern',
						'Key' => 'prometheus-digits-in-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '1223']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter starts with digits',
						'Key' => 'prometeus-digits-first-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '1name_of_metric']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong equals operator',
						'Key' => 'prometeus-wrong-equals-operator[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}=1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator >',
						'Key' => 'prometeus-unsupported-operator-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}>1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator <',
						'Key' => 'prometeus-unsupported-operator-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}<1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator !==',
						'Key' => 'prometeus-unsupported-operator-3[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}!==1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator >=',
						'Key' => 'prometeus-unsupported-operator-4[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}>=1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported operator =<',
						'Key' => 'prometeus-unsupported-operator-5[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}=<1']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus duplicate metric condition',
						'Key' => 'duplicate-metric-condition[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_system{__name__="metric_name"}']
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item duplicate Prometeus steps',
						'Key' => 'duplicate-prometheus-steps[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system'],
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system']
					],
					'error' => 'Invalid parameter "/1/preprocessing/2": only one object can exist within '.
							'the combinations of (type)=((22, 23)).'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - space',
						'Key' => 'wrong-second-parameter-space[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => 'label name'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - quotes',
						'Key' => 'wrong-second-parameter-quotes[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => '"label_name"'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - triangle quotes',
						'Key' => 'wrong-second-parameter-triangle-quotes[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => '<label_name>'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - slash',
						'Key' => 'wrong-second-parameter-slash[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => '\0'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - digits',
						'Key' => 'wrong-second-parameter-digits[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => '123'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - pipe',
						'Key' => 'wrong-second-parameter-pipe[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'metric==1e|5']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - slash',
						'Key' => 'wrong-second-parameter-slash[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label="value\"}']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter =!',
						'Key' => 'wrong-second-parameter-equals-exclamation[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name=!"name"}']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter ~!',
						'Key' => 'wrong-second-parameter-tilda-exclamation[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name~!"name"}']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
				]
			],
			// Successful Prometheus pattern creation.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus empty second parameter',
						'Key' => 'prometeus-empty-second-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus both parameters present',
						'Key' => 'prometeus-both-parameters-present[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => 'label_name'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter +inf',
						'Key' => 'prometeus-plus-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system==+inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter inf',
						'Key' => 'prometeus-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter -inf',
						'Key' => 'prometeus-negative-inf[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__=~"<regex>"}==-inf']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter nan',
						'Key' => 'prometeus-nan[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==nan']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter exp',
						'Key' => 'prometeus-exp[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system==3.5180e+11']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameter ==1',
						'Key' => 'prometeus-neutral-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameters ==+1',
						'Key' => 'prometeus-positive-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==+1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus first parameters ==-1',
						'Key' => 'prometeus-negative-digit[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="metric_name"}==-1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator =',
						'Key' => 'prometeus-label-operator-equal-strong[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name="name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator =~',
						'Key' => 'prometeus-label-operator-contains[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name=~"name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator !=',
						'Key' => 'prometeus-label-operator-exclamation-equals[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!="name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus label operator !~',
						'Key' => 'prometeus-label-operator-exclamation-tilda[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!~"name"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus slashes in pattern',
						'Key' => 'prometeus-slashes-pattern[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label="value\\\\"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters 1',
						'Key' => 'prometeus-macros-1[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{$METRIC_NAME}==1',
							'parameter_2' => 'label',
							'parameter_3' => '{$LABEL_NAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters 2',
						'Key' => 'prometeus-macros-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="{$METRIC_NAME}"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters 3',
						'Key' => 'prometeus-macros-3[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{{$LABEL_NAME}="<label value>"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus user macros in parameters 4',
						'Key' => 'prometeus-macros-4[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name="{$LABEL_VALUE}"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported label operator !=',
						'Key' => 'prometeus-unsupported-label-operator-1[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!="regex_pattern"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus unsupported label operator !~',
						'Key' => 'prometeus-unsupported-label-operator-2[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{label_name!~"<regex>"}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus function sum 1',
						'Key' => 'prometeus-function-sum[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{$METRIC_NAME}==1',
							'parameter_2' => 'sum'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus function min 2',
						'Key' => 'prometeus-function-min[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{label_name!~"<regex>"}',
							'parameter_2' => 'min'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus function max',
						'Key' => 'prometeus-function-max[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{{$LABEL_NAME}="<label value>"}',
							'parameter_2' => 'max'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus function avg',
						'Key' => 'prometeus-function-avg[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{label="value\\\\"}',
							'parameter_2' => 'avg'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Prometeus function count',
						'Key' => 'prometeus-function-count[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{label_name!~"name"}',
							'parameter_2' => 'count'
						]
					]
				]
			]
		]);
	}

	/**
	 * Add item and preprocessing steps.
	 *
	 * @param array   $data    data provider
	 * @param boolean $lld     true if item is lld, false if item or item prototype
	 *
	 * @return CFormElement|CGridFormElement
	 *
	 */
	protected function addItemWithPreprocessing($data, $lld = false) {
		$this->page->login()->open($this->link);
		$this->query('button:'.$this->button)->waitUntilPresent()->one()->click();

		$form = $lld
			? $this->query('name:itemForm')->waitUntilPresent()->asForm()->one()
			: COverlayDialogElement::find()->one()->waitUntilready()->asForm();

		$form->fill($data['fields']);
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($data['preprocessing']);

		return $form;
	}

	/**
	 * Check creating items, item prototypes or LLD rules with preprocessing steps.
	 */
	protected function checkCreate($data, $lld = false) {
		if ($data['expected'] === TEST_BAD) {
			$sql_items = 'SELECT * FROM items ORDER BY itemid';
			$old_hash = CDBHelper::getHash($sql_items);
		}

		$form = $this->addItemWithPreprocessing($data, $lld);

		// Take a screenshot to test draggable object position of preprocessing steps.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			// TODO: Added updateViewport due to not centered screenshot for an item's preprocessing
			// which makes unclear if result is correct.
			$this->page->updateViewport();
			$this->assertScreenshot($this->query('id:preprocessing')->one(), 'Preprocessing'.$this->link);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, $this->success_message);

			// Check result in frontend form.
			$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));

			if ($lld == true) {
				$this->page->open($this->ready_link.$id);
			}
			else {
				$this->page->open($this->link)->waitUntilReady();
				$this->query('link:'.$data['fields']['Name'])->one()->click();
			}

			$form->selectTab('Preprocessing')->waitUntilReady();
			$this->assertPreprocessingSteps($data['preprocessing']);
		}
		else {
			$this->assertMessage(TEST_BAD, $this->fail_message, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql_items));
		}

		if (!$lld) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/*
	 * Preprocessing steps with spaces in fields for item, item prototype and LLD.
	 */
	public static function getCommonPreprocessingTrailingSpacesData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Prometheus to JSON trailing spaces',
						'Key' => 'json-prometeus-space-in-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '  metric  ']
					]
				]
			]
		];
	}

	/*
	 * Additional preprocessing data with spaces in fields for item and item prototype.
	 */
	public function getItemPreprocessingTrailingSpacesData() {
		return array_merge($this->getCommonPreprocessingTrailingSpacesData(), [
			[
				[
					'fields' => [
						'Name' => 'Prometheus pattern trailing spaces',
						'Key' => 'prometeus-space-in-parameters[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '  metric  ',
							'parameter_2' => 'label',
							'parameter_3' => '  output  '
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Custom multiplier trailing spaces',
						'Key' => 'Custom-multiplier-spaces-in-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '  2  ']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'In range trailing spaces',
						'Key' => 'in-range-spaces-in-parameter[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '  5  ', 'parameter_2' => '  10  ']
					]
				]
			]
		]);
	}

	/**
	 * Check spaces in preprocessing steps.
	 */
	protected function checkTrailingSpaces($data, $lld = false) {
		$form = $this->addItemWithPreprocessing($data, $lld);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $this->success_message);

		// Remove spaces.
		foreach ($data['preprocessing'] as $i => &$options) {

		$parameters = CTestArrayHelper::get($options, 'type') === 'Prometheus pattern'
			? ['parameter_1', 'parameter_3']
			: ['parameter_1', 'parameter_2'];

			foreach ($parameters as $parameter) {
				if (array_key_exists($parameter, $options)) {
					$options[$parameter] = trim($options[$parameter]);
				}
			}
		}
		unset($options);

		// Check result in frontend form.
		$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));

		if ($lld == true) {
			$this->page->open($this->ready_link.$id);
		}
		else {
			$this->page->open($this->link)->waitUntilReady();
			$this->query('link:'.$data['fields']['Name'])->one()->click();
		}

		$form->selectTab('Preprocessing');
		$this->assertPreprocessingSteps($data['preprocessing']);

		if (!$lld) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/*
	 * Preprocessing steps with Custom on fail checks for item, item prototype and LLD.
	 */
	public static function getCommonCustomOnFailData() {
		$options = [
			ZBX_PREPROC_FAIL_DISCARD_VALUE	=> 'Discard value',
			ZBX_PREPROC_FAIL_SET_VALUE		=> 'Set value to',
			ZBX_PREPROC_FAIL_SET_ERROR		=> 'Set error to'
		];

		$data = [];
		foreach ($options as $value => $label) {
			$case = [
				'fields' => [
					'Name' => 'Preprocessing '.$label,
					'Key' => 'preprocessing-steps-discard-on-fail'.$value.'[{#KEY}]'
				],
				'preprocessing' => [
					[
						'type' => 'Regular expression',
						'parameter_1' => 'expression',
						'parameter_2' => '\1',
						'on_fail' => true
					],
					[
						'type' => 'XML XPath',
						'parameter_1' => 'path',
						'on_fail' => true
					],
					[
						'type' => 'JSONPath',
						'parameter_1' => '$.data.test',
						'on_fail' => true
					],
					[
						'type' => 'CSV to JSON',
						'on_fail' => true
					],
					[
						'type' => 'JavaScript',
						'parameter_1' => 'Test Java Script'
					],
					[
						'type' => 'Does not match regular expression',
						'parameter_1' => 'Pattern',
						'on_fail' => true
					],
					[
						'type' => 'Check for error in JSON',
						'parameter_1' => '$.new.path',
						'on_fail' => true
					],
					[
						'type' => 'Check for error in XML',
						'parameter_1' => 'XML',
						'on_fail' => true
					],
					[
						'type' => 'Discard unchanged with heartbeat',
						'parameter_1' => '30'
					],
					[
						'type' => 'Prometheus to JSON',
						'parameter_1' => 'metric',
						'on_fail' => true
					],
					[
						'type' => 'XML to JSON',
						'on_fail' => true
					]
				],
				'label' => $label,
				'value' => $value
			];

			$data[] = [self::appendErrorHandler($case)];
		}

		return $data;
	}

	/*
	 * Preprocessing steps with Custom on fail checks for item and item prototype.
	 */
	public function getItemCustomOnFailData() {
		$data = [];

		foreach($this->getCommonCustomOnFailData() as $packed) {
			$case = $packed[0];
			$case['preprocessing'] = array_merge([
				[
					'type' => 'Check for not supported value',
					'parameter_1' => 'any error',
					'on_fail' => true
				],
				[
					'type' => 'Trim',
					'parameter_1' => '111'
				],
				[
					'type' => 'Right trim',
					'parameter_1' => '333'
				],
				[
					'type' => 'Left trim',
					'parameter_1' => '555'
				],
				[
					'type' => 'Custom multiplier',
					'parameter_1' => '2',
					'on_fail' => true
				],
				[
					'type' => 'Change per second',
					'on_fail' => true
				],
				[
					'type' => 'Boolean to decimal',
					'on_fail' => true
				],
				[
					'type' => 'Matches regular expression',
					'parameter_1' => 'regular expression',
					'on_fail' => true
				],
				[
					'type' => 'Check for error using regular expression',
					'parameter_1' => 'expression',
					'parameter_2' => 'output',
					'on_fail' => true
				]
			], $case['preprocessing']);

			$data[] = [self::appendErrorHandler($case)];
		}

		return $data;
	}

	/**
	 * Function for adding handler parameter to case.
	 */
	public static function appendErrorHandler($case) {
		foreach ($case['preprocessing'] as &$preprocessing) {
			if (!array_key_exists('on_fail', $preprocessing)
				|| !$preprocessing['on_fail']) {
				continue;
			}

			$preprocessing['error_handler'] = $case['label'];

			if ($case['value'] !== ZBX_PREPROC_FAIL_DISCARD_VALUE) {
				$preprocessing['error_handler_params'] = 'handler parameter'.
					microtime();
			}
		}
		unset($preprocessing);

		return $case;
	}

	/**
	 * Check "Custom on fail" fields and checkbox state.
	 */
	public function checkCustomOnFail($data, $lld = null) {
		$form = $this->addItemWithPreprocessing($data, $lld);
		$steps = $this->getPreprocessingSteps();

		foreach ($data['preprocessing'] as $i => $options) {
			$this->assertNotEquals(in_array($options['type'], [
				'Trim',
				'Right trim',
				'Left trim',
				'JavaScript',
				'Discard unchanged with heartbeat',
				'Check for not supported value'
			]), $steps[$i]['on_fail']->isEnabled());
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $this->success_message);

		// Check saved preprocessing.
		$itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));
		$item_name = CDBHelper::getValue('SELECT name FROM items WHERE key_='.zbx_dbstr($data['fields']['Key']));

		if ($lld == true) {
			$this->page->open($this->ready_link.$itemid);
		}
		else {
			$this->page->open($this->link)->waitUntilReady();
			$this->query('link:'.$item_name)->one()->click()->waitUntilReady();
		}

		$form->selectTab('Preprocessing');
		$steps = $this->assertPreprocessingSteps($data['preprocessing']);

		$rows = [];
		foreach (CDBHelper::getAll('SELECT step, error_handler FROM item_preproc WHERE itemid='.$itemid) as $row) {
			$rows[$row['step']] = $row['error_handler'];
		}

		foreach ($data['preprocessing'] as $i => $options) {
			// Check "Custom on fail" value in DB.
			$expected = CTestArrayHelper::get($options, 'on_fail', false) === false
				? (($options['type'] === 'Check for not supported value') ? 1 : ZBX_PREPROC_FAIL_DEFAULT)
				: $data['value'];
			$this->assertEquals($expected, $rows[$i+1]);

			if (in_array($options['type'], [
				'Trim',
				'Right trim',
				'Left trim',
				'JavaScript',
				'Discard unchanged with heartbeat'
			])) {
				$this->assertFalse($steps[$i]['on_fail']->isEnabled());
				$this->assertFalse($steps[$i]['on_fail']->isSelected());
				$this->assertTrue($steps[$i]['error_handler'] === null || !$steps[$i]['error_handler']->isVisible());
				$this->assertTrue($steps[$i]['error_handler_params'] === null
					|| !$steps[$i]['error_handler_params']->isVisible()
				);
			}
			elseif (in_array($options['type'], ['Check for not supported value'])) {
				$this->assertFalse($steps[$i]['on_fail']->isEnabled());
				$this->assertTrue($steps[$i]['on_fail']->isSelected());
				$this->assertTrue($steps[$i]['error_handler']->isVisible());
			}
			else {
				$this->assertTrue($steps[$i]['on_fail']->isSelected());
				$this->assertTrue($steps[$i]['on_fail']->isEnabled());
			}
		}

		if (!$lld) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	public static function getCustomOnFailValidationData() {
		$cases = [
			// Custom on fail - Set value to.
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value empty',
					'Key' => 'set-value-empty[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => ''
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value number',
					'Key' => 'set-value-number[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '500'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value string',
					'Key' => 'set-value-string[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'String'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set value special-symbols',
					'Key' => 'set-value-special-symbols[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set value to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			],
			// Custom on fail - Set error to.
			[
				'expected' => TEST_BAD,
				'fields' => [
					'Name' => 'Set error empty',
					'Key' => 'set-error-empty[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => ''
				],
				'error' => 'Invalid parameter "/1/preprocessing/1/error_handler_params": cannot be empty.'
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error string',
					'Key' => 'set-error-string[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => 'Test error'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error number',
					'Key' => 'set-error-number[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '999'
				]
			],
			[
				'expected' => TEST_GOOD,
				'fields' => [
					'Name' => 'Set error special symbols',
					'Key' => 'set-error-special-symbols[{#KEY}]'
				],
				'custom_on_fail' => [
					'error_handler' => 'Set error to',
					'error_handler_params' => '!@#$%^&*()_+<>,.\/'
				]
			]
		];

		$data = [];
		$preprocessing = [
			[
				'type' => 'Regular expression',
				'parameter_1' => 'expression',
				'parameter_2' => '\1',
				'on_fail' => true
			],
			[
				'type' => 'JSONPath',
				'parameter_1' => '$.data.test',
				'on_fail' => true
			],
			[
				'type' => 'Does not match regular expression',
				'parameter_1' => 'Pattern',
				'on_fail' => true
			]
		];

		foreach ($cases as $case) {
			$case['preprocessing'] = [];
			foreach ($preprocessing as $step) {
				$case['preprocessing'][] = array_merge($step, $case['custom_on_fail']);
			}

			$data[] = [$case];
		}

		return $data;
	}

	/*
	 * Inheritance of preprocessing steps for Item, Item prototype and LLD.
	 */
	public static function getCommonInheritancePreprocessing() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Templated Preprocessing steps',
						'Key' => 'templated-preprocessing-steps[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Regular expression',
							'parameter_1' => 'expression',
							'parameter_2' => '\1',
							'on_fail' => true,
							'error_handler' => 'Discard value'
						],
						[
							'type' => 'XML XPath',
							'parameter_1' => 'path',
							'on_fail' => true,
							'error_handler' => 'Set value to',
							'error_handler_params' => 'Custom_text'
						],
						[
							'type' => 'XML to JSON',
							'on_fail' => true,
							'error_handler' => 'Set error to',
							'error_handler_params' => 'Custom_text'
						],
						[
							'type' => 'JSONPath',
							'parameter_1' => '$.data.test',
							'on_fail' => true,
							'error_handler' => 'Set value to',
							'error_handler_params' => 'Custom_text'
						],
						[
							'type' => 'CSV to JSON',
							'parameter_1' => '.',
							'parameter_2' => '/',
							'parameter_3' => false,
							'on_fail' => true,
							'error_handler' => 'Discard value'
						],
						[
							'type' => 'Does not match regular expression',
							'parameter_1' => 'Pattern',
							'on_fail' => true,
							'error_handler' => 'Set error to',
							'error_handler_params' => 'Custom_text'
						],
						[
							'type' => 'Check for error in JSON',
							'parameter_1' => '$.new.path',
							'on_fail' => false
						],
						[
							'type' => 'Check for error in XML',
							'parameter_1' => 'path',
							'on_fail' => false
						],
						[
							'type' => 'Discard unchanged with heartbeat',
							'parameter_1' => '30'
						],
						[
							'type' => 'JavaScript',
							'parameter_1' => "  Test line 1\n  Test line 2\nTest line 3  "
						]
					]
				]
			]
		];
	}

	/*
	 * Inheritance of preprocessing steps for item and item prototype.
	 */
	public function getItemInheritancePreprocessing() {
		$data = $this->getCommonInheritancePreprocessing();
		$data[0][0]['preprocessing'] = array_merge([
					[
						'type' => 'Check for not supported value'
					],
					[
						'type' => 'Right trim',
						'parameter_1' => '5'
					],
					[
						'type' => 'Custom multiplier',
						'parameter_1' => '10',
						'on_fail' => false
					],
					[
						'type' => 'Simple change',
						'on_fail' => false
					],
					[
						'type' => 'Octal to decimal',
						'on_fail' => true,
						'error_handler' => 'Set error to',
						'error_handler_params' => 'Custom_text'
					],
					[
						'type' => 'Check for error using regular expression',
						'parameter_1' => 'expression',
						'parameter_2' => '\0',
						'on_fail' => true,
						'error_handler' => 'Set error to',
						'error_handler_params' => 'Custom_text'
					],
					[
						'type' => 'Prometheus pattern',
						'parameter_1' => 'cpu_usage_system',
						'parameter_2' => 'label',
						'parameter_3' => 'label_name',
						'on_fail' => true,
						'error_handler' => 'Set error to',
						'error_handler_params' => 'Custom_text'
					]
				], $data[0][0]['preprocessing']);

		return $data;
	}

	/**
	 * Check inheritance of preprocessing steps in items or LLD rules.
	 *
	 * @param array		$data		data provider
	 * @param string	$host_link	URL of host configuration
	 */
	protected function checkPreprocessingInheritance($data, $host_link, $lld = false) {
		// Create item on template.
		$form = $this->addItemWithPreprocessing($data, $lld);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, $this->success_message);

		// Check preprocessing steps on host.
		$this->page->open($host_link);
		$this->query('link', $data['fields']['Name'])->waitUntilPresent()->one()->click();
		$form->selectTab('Preprocessing');
		$steps = $this->assertPreprocessingSteps($data['preprocessing']);

		foreach ($data['preprocessing'] as $i => $options) {
			$step = $steps[$i];
			$this->assertNotNull($step['type']->getAttribute('readonly'));

			foreach (['parameter_1', 'parameter_2', 'parameter_3'] as $param) {
				if (array_key_exists($param, $options)) {
					$this->assertFalse($step[$param]->detect()->isEnabled());
				}
			}

			$this->assertFalse($step['on_fail']->isEnabled());

			switch ($options['type']) {
				case 'Regular expression':
				case 'CSV to JSON':
				case 'XML XPath':
				case 'JSONPath':
				case 'Does not match regular expression':
				case 'Octal to decimal':
				case 'Prometheus pattern':
				case 'Check for error using regular expression':
				case 'Check for not supported value':
					$this->assertTrue($step['on_fail']->isSelected());
					$this->assertFalse($step['error_handler']->isEnabled());
					break;
				case 'Custom multiplier':
				case 'Simple change':
				case 'Right trim':
				case 'JavaScript':
				case 'Check for error in JSON':
				case 'Check for error in XML':
				case 'Discard unchanged with heartbeat':
					$this->assertFalse($step['on_fail']->isSelected());
					break;
			}
		}

		if (!$lld) {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Check cloning of inherited preprocessing steps in items, prototypes or LLD rules.
	 *
	 * @param string    $link         cloned item, prototype or LLD URL
	 * @param string    $item         what is being cloned: item, prototype or LLD rule
	 * @param string    $templated    is it templated item or not
	 */
	protected function checkCloneItem($link, $item, $templated = false) {
		$cloned_values = [
			'Name'	=> 'Cloned_testInheritancePreprocessingSteps'.time(),
			'Key' => 'cloned-preprocessing'.time().'[{#KEY}]'
		];

		// Open original item on host and get its preprocessing steps.
		$this->page->login()->open($link)->waitUntilReady();

		if ($item === 'Discovery rule') {
			$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		}
		else {
			$item_name = ($item === 'Item')
				? CDBHelper::getValue('SELECT name FROM items WHERE itemid='.($templated ? self::INHERITED_ITEMID : self::CLONE_ITEMID))
				: CDBHelper::getValue('SELECT name FROM items WHERE itemid='.($templated ? self::INHERITED_ITEM_PROTOTYPE : self::CLONE_ITEM_PROTOTYPEID));

			$this->query('link', $item_name)->one()->waitUntilClickable()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $dialog->asForm();
		}

		if ($templated) {
			// Check that right templated item is opened.
			$label = ($item === 'Discovery rule') ? 'Parent discovery rules' : 'Parent items';
			$this->assertEquals('Inheritance test template', $form->getField($label)->getText());
		}

		$form->selectTab('Preprocessing');
		$original_steps = $this->listPreprocessingSteps();
		$form->selectTab($item);

		// Clone item.
		if ($item === 'Item' || $item === 'Item prototype') {
			$dialog->getFooter()->query('button:Clone')->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();
		}
		else {
			$form->query('button:Clone')->waitUntilPresent()->one()->click();
		}

		$form->invalidate();
		$form->fill($cloned_values);

		$this->checkPreprocessingSteps($form, $original_steps);
		$form->submit();
		$message = ($item === 'Discovery rule') ? $item.' created' : $item.' added';
		$this->assertMessage(TEST_GOOD, $message);

		// Open cloned item and check preprocessing steps in saved form.
		$id = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.zbx_dbstr($cloned_values['Key']));

		if ($item === 'Discovery rule') {
			$this->page->open($this->ready_link.$id);
		}
		else {
			$this->page->open($link);
			$this->query('link', $cloned_values['Name'])->one()->click();
		}

		$form->invalidate();
		$this->assertEquals($cloned_values['Name'], $form->getField('Name')->getValue());
		$this->checkPreprocessingSteps($form, $original_steps);

		if ($item === 'Item' || $item === 'Item prototype') {
			COverlayDialogElement::find()->one()->close();
		}
	}

	/**
	 * Select Preprocessing tab in cloned item, prototype or LLD form and assert
	 * that steps are the same as in original item.
	 *
	 * @param CFormElement	$form				item, prototype or LLD configuration form
	 * @param array			$original_steps		preprocessing steps of original item
	 */
	protected function checkPreprocessingSteps($form, $original_steps) {
		$form->selectTab('Preprocessing');
		$this->assertEquals($original_steps, $this->listPreprocessingSteps());

		// Check that preprocessing steps in cloned form are editable.
		foreach (array_keys($this->listPreprocessingSteps()) as $i) {
			$step = $this->query('id:preprocessing_'.$i.'_type')->one();
			$this->assertNull($step->getAttribute('readonly'));
		}
	}

	/**
	 * Please note that this data provider consists only from ONE test case with an array of steps.
	 */
	public static function getItemsParametersData() {
		return [
			[
				[
					[
						'type' => 'Regular expression',
						'parameters' => [
							['placeholder' => 'pattern', 'maxlength' => 255],
							['placeholder' => 'output', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Replace',
						'parameters' => [
							['placeholder' => 'search string', 'maxlength' => 255],
							['placeholder' => 'replacement', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'Trim',
						'parameters' => [
							['placeholder' => 'list of characters', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'Right trim',
						'parameters' => [
							['placeholder' => 'list of characters', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'Left trim',
						'parameters' => [
							['placeholder' => 'list of characters', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'XML XPath',
						'parameters' => [
							['placeholder' => 'XPath', 'maxlength' => 255]
						]
					],
					[
						'type' => 'JSONPath',
						'parameters' => [
							['placeholder' => '$.path.to.node', 'maxlength' => 255]
						]
					],
					[
						'type' => 'CSV to JSON',
						'parameters' => [
							['placeholder' => 'delimiter', 'value' => ',', 'maxlength' => 1],
							['placeholder' => 'qualifier', 'value' => '"', 'maxlength' => 1],
							['value' => true]
						]
					],
					[
						'type' => 'XML to JSON'
					],
					[
						'type' => 'SNMP walk value',
						'parameters' => [
							['placeholder' => 'OID', 'value' => '', 'maxlength' => 255],
							[
								'selector' => 'xpath:.//z-select[@name="preprocessing[0][params][1]"]',
								'options' => ['Unchanged', 'UTF-8 from Hex-STRING', 'MAC from Hex-STRING', 'Integer from BITS'],
								'value' => 'Unchanged'
							]
						]
					],
					[
						'type' => 'SNMP walk to JSON',
						'parameters' => [
							[
								'selector' => 'xpath:(.//input[@name="preprocessing[0][params][]"])[1]',
								'placeholder' => 'Field name',
								'maxlength' => 255
							],
							[
								'selector' => 'xpath:(.//input[@name="preprocessing[0][params][]"])[2]',
								'placeholder' => 'OID prefix',
								'maxlength' => 255
							],
							[
								'selector' => 'xpath:.//z-select[@name="preprocessing[0][params][]"]',
								'options' => ['Unchanged', 'UTF-8 from Hex-STRING', 'MAC from Hex-STRING', 'Integer from BITS'],
								'value' => 'Unchanged'
							]
						]
					],
					[
						'type' => 'SNMP get value',
						'parameters' => [
							[
								'selector' => 'xpath:.//z-select[@name="preprocessing[0][params][0]"]',
								'options' => ['UTF-8 from Hex-STRING', 'MAC from Hex-STRING', 'Integer from BITS'],
								'value' => 'UTF-8 from Hex-STRING'
							]
						]
					],
					[
						'type' => 'Custom multiplier',
						'parameters' => [
							['placeholder' => 'number', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Simple change'
					],
					[
						'type' => 'Change per second'
					],
					[
						'type' => 'Boolean to decimal'
					],
					[
						'type' => 'Octal to decimal'
					],
					[
						'type' => 'Hexadecimal to decimal'
					],
					[
						'type' => 'JavaScript',
						'parameters' => [
							[
								'selector' => 'xpath:.//div[@class="multilineinput-control"]/input[@type="text"]',
								'placeholder' => 'script'
							]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'In range',
						'parameters' => [
							['placeholder' => 'min', 'maxlength' => 255],
							['placeholder' => 'max', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Matches regular expression',
						'parameters' => [
							['placeholder' => 'pattern', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Does not match regular expression',
						'parameters' => [
							['placeholder' => 'pattern', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Check for error in JSON',
						'parameters' => [
							['placeholder' => '$.path.to.node', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Check for error in XML',
						'parameters' => [
							['placeholder' => 'XPath', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Check for error using regular expression',
						'parameters' => [
							['placeholder' => 'pattern', 'maxlength' => 255],
							['placeholder' => 'output', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Check for not supported value',
						'parameters' => [
							[
								'selector' => 'xpath:.//z-select[@name="preprocessing[0][params][0]"]',
								'options' => ['any error', 'error matches', 'error does not match'],
								'value' => 'any error'
							]
						],
						'on_fail_value' => true,
						'on_fail_enabled' => false
					],
					[
						'type' => 'Discard unchanged',
						'on_fail_enabled' => false
					],
					[
						'type' => 'Discard unchanged with heartbeat',
						'parameters' => [
							['placeholder' => 'seconds', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'Prometheus pattern',
						'parameters' => [
							['placeholder' => '<metric name>{<label name>="<label value>", ...} == <value>', 'maxlength' => 255],
							['selector' => 'xpath:.//z-select[contains(@class, "preproc-param")]', 'value' => 'value'],
							['placeholder' => '<label name>', 'maxlength' => 255]
						]
					],
					[
						'type' => 'Prometheus to JSON',
						'parameters' => [
							['placeholder' => '<metric name>{<label name>="<label value>", ...} == <value>', 'maxlength' => 255]
						]
					]
				]
			]
		];
	}

	/**
	 * Check layout of preprocessing tab and fields.
	 *
	 * @param array $data     given preprocessing steps
	 * @param array $steps    list of steps options
	 * @param boolean $lld    true if LLD form is checked, false if item or item prototype
	 */
	protected function checkLayout($data, $steps, $lld = false) {
		$this->page->login()->open($this->link);
		$this->query('button:'.$this->button)->waitUntilPresent()->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill(
			[
				'Name' => 'Item for preprocessing layout check',
				'Key' => 'preproc-layout-check'
			]
		);
		$form->selectTab('Preprocessing');

		// Check initial layout.
		$preprocessing_container = $form->getFieldContainer('Preprocessing steps');
		$list_step = $preprocessing_container->query('xpath:.//li['.CXPathHelper::fromClass('preprocessing-list-item').']');

		// No any step presents at the beginning.
		$this->assertFalse($list_step->exists());

		// Check that 'Type of information' field is not visible before first step is added.
		$this->assertFalse($form->query('xpath:.//div[@id="item_preproc_list"]/label[text()="Type of information"]')
				->one(false)->isVisible()
		);

		$add_button = $this->query('id:param_add')->one();
		$add_button->click();

		// Check 1st step's visibility and buttons.
		$this->assertTrue($list_step->one()->isVisible());
		$this->assertEquals(4, $preprocessing_container->query('button', ['Add', 'Test', 'Remove', 'Test all steps'])
				->all()->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Check that 'Type of information' field is visible after step is added, but not for LLD.
		$this->assertTrue($form->query('xpath:.//div[@id="item_preproc_list"]/label[text()="Type of information"]')
				->one(false)->isVisible(!$lld)
		);

		// Hint is present only for Items and Item prototypes.
		if (!$lld) {
			$form->getLabel('Preprocessing steps')->query('xpath:./button[@data-hintbox]')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilReady();
			$this->assertEquals("Preprocessing is a transformation before saving the value to the database.".
					" It is possible to define a sequence of preprocessing steps, and those are executed in the order they are set.".
					"\n\nHowever, if \"Check for not supported value\" steps are configured, they are always placed and".
					" executed first (with \"any error\" being the last of them).", $hint->getText()
			);
			$hint->query('xpath:.//button[@title="Close"]')->waitUntilClickable()->one()->click();

			// Check 'Type of information' options.
			$this->assertEquals(['Numeric (unsigned)', 'Numeric (float)', 'Character', 'Log', 'Text'],
					$form->query('name:value_type_steps')->asDropdown()->one()->getOptions()->asText()
			);
		}

		// Assert preprocessing table headers (table is actually list in HTML).
		$this->assertEquals("Name\nParameters\nCustom on fail\nActions",
				$this->query('xpath://li[contains(@class, "preprocessing-list-head")]')->one()->getText()
		);

		// Check that one alone list item is not draggable.
		$disabled_sortable_element = $this->query('xpath://ul[@id="preprocessing" and contains(@class, "sortable-disabled")]');
		$this->assertTrue($disabled_sortable_element->exists());

		// Check steps' types in dropdown.
		$step_type_field = $preprocessing_container->query('xpath:.//z-select[contains(@id, "_type")]')->asDropdown()->one();
		$this->assertEquals($steps, $step_type_field->getOptions()->asText());

		foreach ($data as $step) {
			$step_type_field->fill($step['type']);

			/*
			 * Check Custom on fail checkbox. Note that error handler and parameters are checked by
			 * separate scenario _CustomOnFail and function checkCustomOnFail().
			 */
			$on_fail_field = $preprocessing_container->query('xpath:.//input[@name="preprocessing[0][on_fail]"]')
					->waitUntilPresent()->one();
			$this->assertTrue($on_fail_field->isEnabled(CTestArrayHelper::get($step, 'on_fail_enabled', true)));
			$this->assertTrue($on_fail_field->asCheckbox()->isChecked(CTestArrayHelper::get($step, 'on_fail_value', false)));

			if (array_key_exists('parameters', $step)) {
				foreach ($step['parameters'] as $i => $parameter) {
					$parameter['selector'] = CTestArrayHelper::get($parameter, 'selector',
							'xpath:.//input[@id="preprocessing_0_params_'.$i.'"]'
					);
					$field = $preprocessing_container->query($parameter['selector'])->waitUntilPresent()->one();

					foreach (['maxlength', 'placeholder'] as $attribute) {
						if (array_key_exists($attribute, $parameter)) {
							$this->assertEquals($parameter[$attribute], $field->getAttribute($attribute));
						}
					}

					if (array_key_exists('options', $parameter)) {
						$field = $field->asDropdown();
						$this->assertEquals($parameter['options'], $field->getOptions()->asText());
					}

					$this->assertEquals(CTestArrayHelper::get($parameter, 'value', ''), $field->getValue());
				}
			}
			else {
				$this->assertFalse($preprocessing_container->query('xpath:.//input[contains(@id, "preprocessing_0_params")]')->exists());
			}
		}

		// Add one more step and check sortable class.
		$add_button->click();
		$this->assertFalse($disabled_sortable_element->exists());
	}
}
