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

require_once dirname(__FILE__).'/common/testFormPreprocessing.php';

/**
 * @backup items
 */
class testFormLowLevelDiscoveryPreprocessing extends testFormPreprocessing {

	const HOST_ID = 40001;
	const INHERITANCE_TEMPLATE_ID	= 15000;		// 'Inheritance test template'
	const INHERITANCE_HOST_ID		= 15001;		// 'Template inheritance test host'

	public $link = 'host_discovery.php?hostid='.self::HOST_ID;
	public $ready_link = 'host_discovery.php?form=update&itemid=';
	public $button = 'Create discovery rule';
	public $success_message = 'Discovery rule created';
	public $fail_message = 'Cannot add discovery rule';

	/*
	 * Preprocessing data for LLD successful creation.
	 */
	public static function getLLDPreprocessingCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Add spaces preprocessing',
						'Key' => 'item.spaces.preprocessing'
					],
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '       expression       ', 'parameter_2' => '   \1     '],
						['type' => 'JSONPath', 'parameter_1' => '    $.data.test      '],
						['type' => 'JavaScript', 'parameter_1' => "  Test line 1\n   Test line   2\n   Test line 3   "],
						['type' => 'Does not match regular expression', 'parameter_1' => '    Pattern     '],
						['type' => 'Check for error in JSON', 'parameter_1' => '    $.new.path    '],
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
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
						['type' => 'JavaScript', 'parameter_1' => 'Test JavaScript'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
						['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '30']
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
						['type' => 'Regular expression', 'parameter_1' => 'expression1', 'parameter_2' => '\1'],
						['type' => 'Regular expression', 'parameter_1' => 'expression2', 'parameter_2' => '\2'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test1'],
						['type' => 'JSONPath', 'parameter_1' => '$.data.test2'],
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
	public function testFormLowLevelDiscoveryPreprocessing_CreateAllSteps($data) {
		$this->checkCreate($data);
	}

	/**
	 * @dataProvider getCommonPreprocessingTrailingSpacesData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data);
	}

	/**
	 * @dataProvider getCommonCustomOnFailData
	 */
	public function testFormLowLevelDiscoveryPreprocessing_CustomOnFail($data) {
		$this->checkCustomOnFail($data);
	}

	/**
	 * @dataProvider getCommonInheritancePreprocessing
	 */
	public function testFormLowLevelDiscoveryPreprocessing_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'host_discovery.php?hostid='.self::INHERITANCE_TEMPLATE_ID;
		$host_link = 'host_discovery.php?hostid='.self::INHERITANCE_HOST_ID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}
}
