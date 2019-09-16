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
class testFormItemPreprocessing extends testFormPreprocessing {
	const HOST_ID = 40001;							// 'Simple form test host'
	const INHERITANCE_TEMPLATE_ID	= 15000;		// 'Inheritance test template'
	const INHERITANCE_HOST_ID		= 15001;		// 'Template inheritance test host'

	public $link = 'items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID;
	public $ready_link = 'items.php?form=update&hostid='.self::HOST_ID.'&itemid=';
	public $button = 'Create item';
	public $success_message = 'Item added';
	public $fail_message = 'Cannot add item';

	use PreprocessingTrait;

	public function getItemPreprocessingPrometheusData() {
		return array_merge($this->getPrometheusData(), [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong first parameter - LLD macro',
						'Key' => 'wrong-first-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => '{#METRICNAME}==1']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus pattern.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Item Prometeus wrong second parameter - LLD macro',
						'Key' => 'wrong-second-parameter-macro'
					],
					'preprocessing' => [
						['type' => 'Prometheus pattern', 'parameter_1' => 'cpu_usage_system', 'parameter_2' => '{#LABELNAME}']

					],
					'error' => 'Incorrect value for field "params": invalid Prometheus output.'
				]
			],
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
	 * @dataProvider getItemPreprocessingValidationData
	 * @dataProvider getItemPreprocessingCreateData
	 * @dataProvider getItemPreprocessingPrometheusData
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormItemPreprocessing_CreateAllSteps($data) {
		$this->checkCreate($data);
	}

	/**
	 * @dataProvider getItemPreprocessingTrailingSpacesData
	 */
	public function testFormItemPreprocessing_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data);
	}

	/**
	 * Test copies templated item from one host to another.
	 */
	public function testFormItemPreprocessing_CopyItem() {
		$preprocessing_item_key = 'test-inheritance-item-preprocessing';	// testInheritanceItemPreprocessing.
		$preprocessing_item_name = 'testInheritanceItemPreprocessing';
		$preprocessing_item_id = 15094;
		$original_host_id = 15001;											// Template inheritance test host
		$target_hostname = 'Simple form test host';

		$this->page->login()->open('items.php?filter_set=1&filter_hostids[0]='.$original_host_id);
		$table = $this->query('xpath://form[@name="items"]/table')->asTable()->one();
		$table->findRow('Key', $preprocessing_item_key)->select();
		$this->query('button:Copy')->one()->click();
		$form = $this->query('name:elements_form')->waitUntilPresent()->asForm()->one();

		$form->fill([
			'Target type'	=> 'Hosts',
			'Target' => ['values' => [$target_hostname], 'context' => 'Zabbix servers']
		]);
		$form->submit();

		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Item copied', $message->getTitle());

		// Open original item form and get steps text.
		$this->page->open('items.php?form=update&hostid='.$original_host_id.'&itemid='.$preprocessing_item_id);
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Preprocessing');
		$original_steps = $this->listPreprocessingSteps();
		// Open copied item form, get steps text and compare to original.
		$this->page->open('items.php?filter_set=1&filter_hostids[0]='.self::HOST_ID);
		$this->query('link', $preprocessing_item_name)->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$this->assertEquals($preprocessing_item_name, $form->getField('Name')->getValue());
		$this->assertEquals($preprocessing_item_key, $form->getField('Key')->getValue());
		$form->selectTab('Preprocessing');
		$copied_steps = $this->listPreprocessingSteps();
		$this->assertEquals($original_steps, $copied_steps);
		// Get steps inputs and check if they are not disabled.
		foreach (array_keys($copied_steps) as $i) {
			$step = $this->query('id:preprocessing_'.$i.'_type')->one();
			$this->assertNull($step->getAttribute('readonly'));
		}
	}

	/**
	 * @dataProvider getItemCustomOnFailData
	 */
	public function testFormItemPreprocessing_CustomOnFail($data) {
		$this->checkCustomOnFail($data);
	}

	/**
	 * @dataProvider getItemInheritancePreprocessing
	 */
	public function testFormItemPreprocessing_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'items.php?filter_set=1&filter_hostids[0]='.self::INHERITANCE_TEMPLATE_ID;
		$host_link = 'items.php?filter_set=1&filter_hostids[0]='.self::INHERITANCE_HOST_ID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}
}
