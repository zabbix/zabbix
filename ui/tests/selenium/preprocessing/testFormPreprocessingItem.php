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
 * @backup items,users
 * @onBefore setRowsPerPage
 */
class testFormPreprocessingItem extends testFormPreprocessing {

	public $link = 'zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.self::HOSTID;
	public $button = 'Create item';
	public $success_message = 'Item added';
	public $fail_message = 'Cannot add item';

	const HOSTID					= 40001;	// 'Simple form test host'
	const INHERITANCE_TEMPLATEID	= 15000;	// 'Inheritance test template'
	const INHERITANCE_HOSTID		= 15001;	// 'Template inheritance test host'

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
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
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
						[
							'type' => 'Prometheus pattern',
							'parameter_1' => 'cpu_usage_system',
							'parameter_2' => 'label',
							'parameter_3' => '{#LABELNAME}'
						]
					],
					'error' => 'Invalid parameter "/1/preprocessing/1/params/3": invalid Prometheus label.'
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
					'error' => 'Invalid parameter "/1/preprocessing/1/params/1": invalid Prometheus pattern.'
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
	public function testFormPreprocessingItem_CreateAllSteps($data) {
		$this->checkCreate($data);
	}

	/**
	 * @dataProvider getItemPreprocessingTrailingSpacesData
	 */
	public function testFormPreprocessingItem_TrailingSpaces($data) {
		$this->checkTrailingSpaces($data);
	}

	/**
	 * Test copies templated item from one host to another.
	 */
	public function testFormPreprocessingItem_CopyItem() {
		$item_key = 'test-inheritance-item-preprocessing';	// testInheritanceItemPreprocessing
		$item_name = 'testInheritanceItemPreprocessing';
		$original_hostid = 15001;							// "Template inheritance test host"
		$target_hostname = 'Simple form test host';

		$this->page->login()->open('zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.$original_hostid);
		$table = $this->query('xpath://form[@name="item_list"]/table')->asTable()->one();
		$table->findRow('Key', $item_key)->select();
		$this->query('button:Copy')->one()->click();
		$mass_update_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$mass_update_form->fill([
			'Target type'	=> 'Hosts',
			'Target' => $target_hostname
		]);
		$mass_update_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Item copied');

		// Open original item form and get steps text.
		$this->page->open('zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.$original_hostid);
		$this->query('link', $item_name)->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$form->selectTab('Preprocessing');
		$original_steps = $this->listPreprocessingSteps();
		$dialog->close();
		// Open copied item form, get steps text and compare to original.
		$this->page->open('zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.self::HOSTID);
		$this->query('link', $item_name)->one()->click();
		$form->invalidate();
		$this->assertEquals($item_name, $form->getField('Name')->getValue());
		$this->assertEquals($item_key, $form->getField('Key')->getValue());
		$form->selectTab('Preprocessing');
		$copied_steps = $this->listPreprocessingSteps();
		$this->assertEquals($original_steps, $copied_steps);

		// Get steps inputs and check if they are not disabled.
		foreach (array_keys($copied_steps) as $i) {
			$step = $this->query('id:preprocessing_'.$i.'_type')->one();
			$this->assertNull($step->getAttribute('readonly'));
		}

		$dialog->close();
	}

	/**
	 * Add preprocessing steps to item for cloning.
	 */
	public function prepareCloneItemPreprocessing() {
		CDataHelper::call('item.update', [
			'itemid' => self::CLONE_ITEMID,
			'preprocessing' => self::CLONE_PREPROCESSING
		]);
	}

	/**
	 * @onBefore prepareCloneItemPreprocessing
	 *
	 * @backup profiles
	 */
	public function testFormPreprocessingItem_CloneItem() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.self::HOSTID;
		$this->checkCloneItem($link, 'Item');
	}

	public function testFormPreprocessingItem_CloneTemplatedItem() {
		$link = 'zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.self::INHERITANCE_HOSTID;
		$this->checkCloneItem($link, 'Item', $templated = true);
	}

	/**
	 * @dataProvider getItemCustomOnFailData
	 */
	public function testFormPreprocessingItem_CustomOnFail($data) {
		$this->checkCustomOnFail($data);
	}

	/**
	 * @dataProvider getItemInheritancePreprocessing
	 */
	public function testFormPreprocessingItem_PreprocessingInheritanceFromTemplate($data) {
		$this->link = 'zabbix.php?action=item.list&filter_set=1&context=template&filter_hostids[0]='.self::INHERITANCE_TEMPLATEID;
		$host_link = 'zabbix.php?action=item.list&filter_set=1&context=host&filter_hostids[0]='.self::INHERITANCE_HOSTID;

		$this->checkPreprocessingInheritance($data, $host_link);
	}

	/**
	 * Check Prometheus 3rd parameter editability depending on formula field.
	 */
	public function testFormPreprocessingItem_PrometheusParameters() {
		$this->page->login()->open($this->link);
		$this->query('button:'.$this->button)->waitUntilPresent()->one()->click();

		// Open preprocessing form and add prometheus step.
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps([['type' => 'Prometheus pattern', 'parameter_1' => 'pattern']]);

		// Check default values.
		$fields = [
			'dropdown' => 'name:preprocessing[0][params][1]',
			'value' => 'id:preprocessing_0_params_2'
		];

		$this->assertEquals('value', $form->getField($fields['dropdown'])->getValue());
		$this->assertEquals('', $form->getField($fields['value'])->getValue());

		// Fill value with text.
		$form->getField($fields['dropdown'])->fill('label');
		$form->getField($fields['value'])->fill('test');

		$values = [
			'value' => false,
			'label' => true,
			'sum' => false,
			'min' => false,
			'max' => false,
			'avg' => false,
			'count' => false
		];

		// Change dropdown values and check label field value and editability.
		foreach ($values as $value => $enabled) {
			$form->getField($fields['dropdown'])->fill($value);
			$this->assertTrue($form->getField($fields['value'])->isEnabled($enabled));

			// Check that entered value did not disappear.
			$this->assertEquals('test', $form->getField($fields['value'])->getValue());
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * @dataProvider getItemsParametersData
	 */
	public function testFormPreprocessingItem_CheckStepsLayout($data) {
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
