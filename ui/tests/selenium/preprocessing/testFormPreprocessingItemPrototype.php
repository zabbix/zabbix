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


require_once __DIR__.'/../common/testFormPreprocessing.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items,users
 * @onBefore setRowsPerPage
 *
 * TODO: remove ignoreBrowserErrors after DEV-4233
 * @ignoreBrowserErrors
 */
class testFormPreprocessingItemPrototype extends testFormPreprocessing {

	public $link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::DISCOVERY_RULEID;
	public $button = 'Create item prototype';
	public $success_message = 'Item prototype added';
	public $fail_message = 'Cannot add item prototype';

	const DISCOVERY_RULEID			= 133800;	// 'Simple form test host' => 'testFormDiscoveryRule'
	const TEMPL_INHERITANCE_RULEID	= 15011;	//'testInheritanceDiscoveryRule'
	const HOST_INHERITANCE_RULEID	= 15016;	// 'Template inheritance test host' -> 'testInheritanceDiscoveryRule'
	const CLONE_RULEID				= 133800;	// 'Host for triggers filtering' -> 'Discovery rule for triggers filtering'

	public function getItemPrototypePrometheusData() {
		return array_merge($this->getPrometheusData(), [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometeus LLD macro in parameters 1',
						'Key' => 'parameters-macro-1[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{#METRICNAME}==1',
							'parameter_2' => 'label',
							'parameter_3' => '{#LABELNAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometeus LLD macro in parameters 2',
						'Key' => 'parameters-macro-2[{#KEY}]'
					],
					'preprocessing' => [
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => '{__name__="{#METRICNAME}"}',
							'parameter_2' => 'label',
							'parameter_3' => '{#LABELNAME}']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Prometheus to JSON LLD macro in parameter 1',
						'Key' => 'json-parameter-macro-1[{#KEY}]'
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
						'Key' => 'json-parameters-macro-2[{#KEY}]'
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
	public function testFormPreprocessingItemPrototype_CreateAllSteps($data) {
		$this->checkCreate($data);
	}

	/**
	 * @dataProvider getItemPreprocessingTrailingSpacesData
	 */
	public function testFormPreprocessingItemPrototype_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data);
	}

	/**
	 * Add preprocessing steps to item prototype for cloning.
	 */
	public function prepareCloneItemPrototypePreprocessing() {
		CDataHelper::call('itemprototype.update', [
			'itemid' => self::CLONE_ITEM_PROTOTYPEID,
			'preprocessing' => self::CLONE_PREPROCESSING
		]);
	}

	/**
	 * @onBefore prepareCloneItemPrototypePreprocessing
	 */
	public function testFormPreprocessingItemPrototype_CloneItemPrototype() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::CLONE_RULEID;
		$this->checkCloneItem($link, 'Item prototype');
	}

	public function testFormPreprocessingItemPrototype_CloneTemplatedItemPrototype() {
		$link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::HOST_INHERITANCE_RULEID;
		$this->checkCloneItem($link, 'Item prototype', $templated = true);
	}

	/**
	 * @dataProvider getItemCustomOnFailData
	 */
	public function testFormPreprocessingItemPrototype_CustomOnFail($data) {
		$this->checkCustomOnFail($data);
	}

	/**
	 * @dataProvider getItemInheritancePreprocessing
	 */
	public function testFormPreprocessingItemPrototype_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'zabbix.php?action=item.prototype.list&context=template&parent_discoveryid='.self::TEMPL_INHERITANCE_RULEID;
		$host_link = 'zabbix.php?action=item.prototype.list&context=host&parent_discoveryid='.self::HOST_INHERITANCE_RULEID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}

	/**
	 * @dataProvider getItemsParametersData
	 */
	public function testFormPreprocessingItemPrototype_CheckStepsLayout($data) {
		$steps = ['Regular expression', 'Replace', 'Trim', 'Right trim', 'Left trim', 'XML XPath', 'JSONPath', 'CSV to JSON',
				'XML to JSON', 'SNMP walk value', 'SNMP walk to JSON', 'SNMP get value', 'Custom multiplier',
				'Simple change', 'Change per second', 'Boolean to decimal', 'Octal to decimal', 'Hexadecimal to decimal',
				'JavaScript', 'In range', 'Matches regular expression', 'Does not match regular expression',
				'Check for error in JSON', 'Check for error in XML', 'Check for error using regular expression',
				'Check for not supported value', 'Discard unchanged', 'Discard unchanged with heartbeat',
				'Prometheus pattern', 'Prometheus to JSON'
		];
		$this->checkLayout($data, $steps);
	}
}
