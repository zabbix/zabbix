<?php
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
		]
	];

	/*
	 * Preprocessing data for LLD successful creation.
	 */
	public static function getLLDPreprocessingCreateData() {
		return [
			// Structured data. CSV to JSON.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'CSV to JSON empty parameters',
						'Key' => 'csv-to-json-empty-parameters'
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
						'Key' => 'csv-to-json-with-default-parameters'
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
						'Key' => 'csv-to-json-custom-parameters'
					],
					'preprocessing' => [
						['type' => 'CSV to JSON', 'parameter_1' => ' ', 'parameter_2' => "'", 'parameter_3' => false]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add spaces preprocessing',
						'Key' => 'item.spaces.preprocessing'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => '   test text  ', 'parameter_2' => '   replacement 1  '],
						['type' => 'Regular expression', 'parameter_1' => '       expression       ', 'parameter_2' => '   \1     '],
						['type' => 'JSONPath', 'parameter_1' => '    $.data.test      '],
						['type' => 'JavaScript', 'parameter_1' => "  Test line 1\n   Test line   2\n   Test line 3   "],
						['type' => 'Does not match regular expression', 'parameter_1' => '    Pattern     '],
						['type' => 'Check for error in JSON', 'parameter_1' => '    $.new.path    ']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'LLD all preprocessing steps',
						'Key' => 'lld-all-preprocessing-steps'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => 'test', 'parameter_2' => 'replacement'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
						['type' => 'CSV to JSON','parameter_1' => ' ', 'parameter_2' => '\\', 'parameter_3' => true],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '30'],
						['type' => 'XML to JSON']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'LLD double preprocessing steps',
						'Key' => 'lld-double-preprocessing-steps'
					],
					'preprocessing' => [
						['type' => 'Replace', 'parameter_1' => '123', 'parameter_2' => '345'],
						['type' => 'Replace', 'parameter_1' => '123', 'parameter_2' => '345'],
						['type' => 'Regular expression', 'parameter_1' => 'expression1', 'parameter_2' => '\1'],
						['type' => 'Regular expression', 'parameter_1' => 'expression2', 'parameter_2' => '\2'],
						['type' => 'CSV to JSON', 'parameter_1' => '.', 'parameter_2' => "'" ,'parameter_3' => false],
						['type' => 'CSV to JSON', 'parameter_1' => '.', 'parameter_2' => "'" ,'parameter_3' => false],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test2'],
						['type' => 'XML to JSON'],
						['type' => 'XML to JSON'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern1'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern2'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path1'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path2']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'LLD symbols preprocessing steps',
						'Key' => 'lld-symbols-preprocessing-steps'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '1a!@#$%^&*()-=', 'parameter_2' => '2b!@#$%^&*()-='],
						['type' => 'JSONPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'Does not match regular expression', 'parameter_1' => '4d!@#$%^&*()-='],
						['type' => 'JavaScript', 'parameter_1' => '5d!@#$%^&*()-='],
						['type' => 'Check for error in JSON', 'parameter_1' => '5e!@#$%^&*()-=']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'LLD user macros preprocessing steps',
						'Key' => 'lld-macros-preprocessing-steps'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$PATTERN}', 'parameter_2' => '{$OUTPUT}'],
						['type' => 'JSONPath', 'parameter_1' => '{$PATH}'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$PATTERN2}'],
						['type' => 'JavaScript', 'parameter_1' => '{$JAVASCRIPT}'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$PATH2}'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '{$HEARTBEAT}']
					]
				]
			]
		];
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
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
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
}
