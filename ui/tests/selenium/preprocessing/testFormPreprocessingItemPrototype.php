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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../common/testFormPreprocessing.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testFormPreprocessingItemPrototype extends testFormPreprocessing {

	public $link = 'disc_prototypes.php?context=host&parent_discoveryid='.self::DISCOVERY_RULEID;
	public $ready_link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.self::DISCOVERY_RULEID.'&itemid=';
	public $button = 'Create item prototype';
	public $success_message = 'Item prototype added';
	public $fail_message = 'Cannot add item prototype';

	const DISCOVERY_RULEID			= 133800;	// 'Simple form test host' => 'testFormDiscoveryRule'
	const TEMPL_INHERITANCE_RULEID	= 15011;	//'testInheritanceDiscoveryRule'
	const HOST_INHERITANCE_RULEID	= 15016;	// 'Template inheritance test host' -> 'testInheritanceDiscoveryRule'
	const INHERITED_ITEM_PROTOTYPE	= 15096;	// 'testInheritanceDiscoveryRule' -> 'testInheritanceItemPrototypePreprocessing'
	const CLONE_RULEID				= 133800;	// 'Host for triggers filtering' -> 'Discovery rule for triggers filtering'
	const CLONE_ITEM_PROTOTYPEID	= 23804;	// 'Discovery rule for triggers filtering' -> 'Discovered item {#TEST}'

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
						'Key' => 'parameters-macro-2'
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
		$link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.self::CLONE_RULEID.
				'&itemid='.self::CLONE_ITEM_PROTOTYPEID;
		$this->checkCloneItem($link, 'Item prototype');
	}

	public function testFormPreprocessingItemPrototype_CloneTemplatedItemPrototype() {
		$link = 'disc_prototypes.php?form=update&context=host&parent_discoveryid='.self::HOST_INHERITANCE_RULEID.
				'&itemid='.self::INHERITED_ITEM_PROTOTYPE;
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
		$this->link = 'disc_prototypes.php?context=template&parent_discoveryid='.self::TEMPL_INHERITANCE_RULEID;
		$host_link = 'disc_prototypes.php?context=host&parent_discoveryid='.self::HOST_INHERITANCE_RULEID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}
}
