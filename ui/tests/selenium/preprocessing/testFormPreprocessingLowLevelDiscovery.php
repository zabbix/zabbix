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

require_once dirname(__FILE__).'/../common/testFormPreprocessing.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup items
 */
class testFormPreprocessingLowLevelDiscovery extends testFormPreprocessing {

	public $link = 'host_discovery.php?context=host&filter_set=1&filter_hostids%5B0%5D='.self::HOSTID;
	public $ready_link = 'host_discovery.php?form=update&context=host&itemid=';
	public $button = 'Create discovery rule';
	public $success_message = 'Discovery rule created';
	public $fail_message = 'Cannot add discovery rule';

	const IS_LLD = true;
	const HOSTID = 40001;
	const INHERITANCE_TEMPLATEID	= 15000;	// 'Inheritance test template'
	const INHERITANCE_HOSTID		= 15001;	// 'Template inheritance test host'
	const INHERITANCE_LLDID			= 15016;	// 'Template inheritance test host' -> 'testInheritanceDiscoveryRule'
	const CLONE_LLDID				= 133800;	// 'Simple form test host' -> 'testFormDiscoveryRule1'
	const CLONE_PREPROCESSING = [
		[
			'type' => '5',
			'params' => "regular expression pattern \noutput template",
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
			'type' => '20',
			'params' => '7',
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

	/*
	 * GOOD create scenario data for LLD ONLY.
	 */
	public function getLLDPreprocessingCreateData() {
		return array_merge($this->getCommonPreprocessingCreateData(), [
			// All steps at once.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'All steps at once',
						'Key' => 'all-steps-at-once[{#KEY}]'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Replace', 'parameter_1' => 'text', 'parameter_2' => 'REPLACEMENT'],
						['type' => 'XML XPath', 'parameter_1' => 'def'],
						['type' => 'JSONPath', 'parameter_1' => 'def'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'XML to JSON'],
						['type' => 'SNMP walk value', 'parameter_1' => '1.2.3', 'parameter_2' => 'UTF-8 from Hex-STRING'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => 'abc', 'parameter_table_1_2' => '123'],
						['type' => 'SNMP get value', 'parameter_1' => 'MAC from Hex-STRING'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5']
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
						['type' => 'XML XPath', 'parameter_1' => '08_<!@"\'&nbsp;🙂🙃'],
						['type' => 'JSONPath', 'parameter_1' => '09_<!@"\'&nbsp;🙂🙃'],
						['type' => 'CSV to JSON','parameter_1' => '<', 'parameter_2' => '<'],
						['type' => 'SNMP walk value', 'parameter_1' => '10_<!@"\'&nbsp;🙂🙃'],
						['type' => 'SNMP walk to JSON', 'parameter_table_1_1' => '11_<!@"\'&nbsp;🙂🙃', 'parameter_table_1_2' => '12_<!@"\'&nbsp;🙂🙃'],
						['type' => 'JavaScript', 'parameter_1' => '13_5d!@#$%^&*()-='],
						['type' => 'Matches regular expression', 'parameter_1' => '14_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Does not match regular expression', 'parameter_1' => '15_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Check for error in JSON', 'parameter_1' => '16_<!@"\'&nbsp;🙂🙃'],
						['type' => 'Check for error in XML', 'parameter_1' => '17_<!@"\'&nbsp;🙂🙃']
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
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in JSON', 'parameter_1' => 'test'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc'],
						['type' => 'Check for error in XML', 'parameter_1' => 'abc']
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
						['type' => 'XML XPath', 'parameter_1' => 'number(/values/Item/value[../key=\'{$DELIM}\'])'],
						['type' => 'JSONPath', 'parameter_1' => '$.data[\'{$KEY}\']'],
						['type' => 'Matches regular expression', 'parameter_1' => '{$EXPRESSION}(.*)'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$REGEXP}(.+)'],
						['type' => 'JavaScript', 'parameter_1' => '{$JAVASCRIPT}'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$USERMACRO}'],
						['type' => 'Check for error in XML', 'parameter_1' => '/tmp/{$PATH}'],
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
						['type' => 'XML XPath', 'parameter_1' => '   number(/values/Item)    '],
						['type' => 'JSONPath', 'parameter_1' => '    $.data.key    '],
						['type' => 'Matches regular expression', 'parameter_1' => '  expression    '],
						['type' => 'Does not match regular expression', 'parameter_1' => '   not_expression   '],
						['type' => 'JavaScript', 'parameter_1' => "   Test line 1  \n   Test line 2 \n   Test line  3   \n   \n "],
						['type' => 'Check for error in JSON', 'parameter_1' => '   $.error     '],
						['type' => 'Check for error in XML', 'parameter_1' => '   /tmp/path/   ']
					]
				]
			]
		]);
	}

	public function getLLDPrometheusData() {
		return array_merge($this->getPrometheustoJSONData(), [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometheus to JSON wrong first parameter - LLD macro',
						'Key' => 'json-wrong-first-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{#METRICNAME}==1']

					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.',
					'lld_error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			]
		]);
	}

	/**
	 * @dataProvider getCommonPreprocessingValidationData
	 * @dataProvider getLLDPreprocessingCreateData
	 * @dataProvider getLLDPrometheusData
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormPreprocessingLowLevelDiscovery_CreateAllSteps($data) {
		$this->checkCreate($data, self::IS_LLD);
	}

	/**
	 * @dataProvider getCommonPreprocessingTrailingSpacesData
	 */
	public function testFormPreprocessingLowLevelDiscovery_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data, self::IS_LLD);
	}

	/**
	 * Add preprocessing steps to templated LLD for cloning.
	 */
	public function prepareCloneTemplatedLLDPreprocessing() {
		CDataHelper::call('discoveryrule.update', [
			'itemid' => '15011',
			'preprocessing' => self::CLONE_PREPROCESSING
		]);
	}

	/**
	 * @onBefore prepareCloneTemplatedLLDPreprocessing
	 */
	public function testFormPreprocessingLowLevelDiscovery_CloneTemplatedLLD() {
		$link = 'host_discovery.php?form=update&context=host&itemid='.self::INHERITANCE_LLDID;
		$this->checkCloneItem($link, 'Discovery rule', $templated = true);
	}

	/**
	 * Add preprocessing steps to LLD for cloning.
	 */
	public function prepareCloneLLDPreprocessing() {
		CDataHelper::call('discoveryrule.update', [
			'itemid' => self::CLONE_LLDID,
			'preprocessing' => self::CLONE_PREPROCESSING
		]);
	}

	/**
	 * @onBefore prepareCloneLLDPreprocessing
	 */
	public function testFormPreprocessingLowLevelDiscovery_CloneLLD() {
		$link = 'host_discovery.php?form=update&context=host&itemid='.self::CLONE_LLDID;
		$this->checkCloneItem($link, 'Discovery rule');
	}

	/**
	 * @dataProvider getCommonCustomOnFailData
	 */
	public function testFormPreprocessingLowLevelDiscovery_CustomOnFail($data) {
		$this->checkCustomOnFail($data, self::IS_LLD);
	}

	/**
	 * @dataProvider getCommonInheritancePreprocessing
	 */
	public function testFormPreprocessingLowLevelDiscovery_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'host_discovery.php?filter_set=1&&context=template&filter_hostids%5B0%5D='.self::INHERITANCE_TEMPLATEID;
		$host_link = 'host_discovery.php?filter_set=1&context=host&filter_hostids%5B0%5D='.self::INHERITANCE_HOSTID;

		$this->checkPreprocessingInheritance($data, $host_link, self::IS_LLD);
	}

	/**
	 * Please note that this data provider consists only from ONE test case with an array of steps.
	 */
	public static function getLLDParametersData() {
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
						'type' => 'Discard unchanged with heartbeat',
						'parameters' => [
							['placeholder' => 'seconds', 'maxlength' => 255]
						],
						'on_fail_enabled' => false
					],
					[
						'type' => 'Prometheus to JSON',
						'parameters' => [
							[
								'placeholder' => '<metric name>{<label name>="<label value>", ...} == <value>',
								'maxlength' => 255
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLLDParametersData
	 */
	public function testFormPreprocessingLowLevelDiscovery_CheckStepsLayout($data) {
		$steps = ['Regular expression', 'Replace', 'XML XPath', 'JSONPath', 'CSV to JSON', 'XML to JSON', 'SNMP walk value',
			'SNMP walk to JSON', 'SNMP get value', 'JavaScript', 'Matches regular expression', 'Does not match regular expression',
			'Check for error in JSON', 'Check for error in XML', 'Discard unchanged with heartbeat', 'Prometheus to JSON'
		];
		$this->checkLayout($data, $steps, true);
	}
}
