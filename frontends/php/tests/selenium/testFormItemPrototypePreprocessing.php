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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/common/testFormPreprocessing.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testFormItemPrototypePreprocessing extends testFormPreprocessing {

	const DISCOVERY_RULE_ID = 33800;			// 'Simple form test host' => 'testFormDiscoveryRule'
	const TEMPL_DISCOVERY_RULE_ID = 15011;	// 'testInheritanceDiscoveryRule'
	const HOST_DISCOVERY_RULE_ID	 = 15016;	// 'Template inheritance test host -> testInheritanceDiscoveryRule'

	public $link = 'disc_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID;
	public $ready_link = 'disc_prototypes.php?form=update&parent_discoveryid='.self::DISCOVERY_RULE_ID.'&itemid=';
	public $button = 'Create item prototype';
	public $success_message = 'Item prototype added';
	public $fail_message = 'Cannot add item prototype';

	public function getItemPrototypePrometheusData() {
		return array_merge($this->getPrometheusData(), [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometeus LLD macro in parameters 1',
						'Key' => 'parameters-macro-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{#METRICNAME}==1', 'parameter_2' => '{#LABELNAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometeus LLD macro in parameters 2',
						'Key' => 'parameters-macro-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{__name__="{#METRICNAME}"}', 'parameter_2' => '{#LABELNAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometheus to JSON LLD macro in parameter 1 ',
						'Key' => 'json-parameter-macro-1'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{#METRICNAME}==1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometeus to Json LLD macro in parameter 2',
						'Key' => 'json-parameters-macro-2'
					],
					'preprocessing' => [
						['type' => 'Prometheus to JSON', 'parameter_1' => '{label_name="{#LABELVALUE}"}']
					]
				]
			]
		]);
	}

	/**
	 * @dataProvider getItemPreprocessingValidationData
	 * @dataProvider getItemPreprocessingCreateData
	 * @dataProvider getItemPrototypePrometheusData
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormItemPrototypePreprocessing_CreateAllSteps($data) {
		$this->checkCreate($data);
	}

	/**
	 * @dataProvider getItemCustomOnFailData
	 */
	public function testFormItemPrototypePreprocessing_CustomOnFail($data) {
		$this->checkCustomOnFail($data);
	}

	/**
	 * @dataProvider getItemPreprocessingTrailingSpacesData
	 */
	public function testFormItemPrototypePreprocessing_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data);
	}

	/**
	 * @dataProvider getItemInheritancePreprocessing
	 */
	public function testFormItemPrototypePreprocessing_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'disc_prototypes.php?parent_discoveryid='.self::TEMPL_DISCOVERY_RULE_ID;
		$host_link = 'disc_prototypes.php?parent_discoveryid='.self::HOST_DISCOVERY_RULE_ID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}
}
