<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup services
 */
class testFormMonitoringServices extends CWebTest{

	public static function prepareServicesData() {
		$services = CDataHelper::call('service.create', [
			[
				'name' => 'Server 1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 1
			],
			[
				'name' => 'Server 2',
				'algorithm' => 1,
				'showsla' => 0,
				'goodsla' => 99.99,
				'sortorder' => 2
			],
			[
				'name' => 'Server 3',
				'algorithm' => 1,
				'showsla' => 0,
				'goodsla' => 99.99,
				'sortorder' => 3,
				'tags' => [
					[
						'tag' => 'test',
						'value' => 'test123'
					]
				]
			],
			[
				'name' => 'Server 4',
				'algorithm' => 1,
				'showsla' => 0,
				'goodsla' => 99.99,
				'sortorder' => 4,
				'tags' => [
					[
						'tag' => 'test123',
						'value' => 'test456'
					],
					[
						'tag' => 'test',
						'value' => 'test789'
					]
				]
			],
			[
				'name' => 'Server 5',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 5,
				'problem_tags' => [
					[
						'tag' => 'problem',
						'operator' => 0,
						'value' => 'true'
					]
				]
			]
		]);

		CDataHelper::call('service.update', [
			[
				'serviceid' =>  $services['Server 1'],
				'parents' => [
					[
						'serviceid' => $services['Server 2']
					]
				]
			],
			[
				'serviceid' => $services['Server 2'],
				'parents' => [
					[
						'serviceid' => $services['Server 3']
					]
				]
			]
		]);
	}

	/**
	 * Check Service create form layout
	 */
	public function testFormMonitoringServices_Layout() {
		$this->openCreateServiceForm();

		COverlayDialogElement::find()->one()->waitUntilReady();

		$service_tabs_labels = [
			'Name',
			'Parent services',
			'Problem tags',
			'Sort order (0->999)',
			'Status calculation rule',
			'Advanced configuration'
		];

		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		// Check tabs available in the form
		$tabs = ['Service', 'SLA', 'Tags', 'Child services'];
		$this->assertEquals(count($tabs), $form->query('xpath:./' . '/li[@role="tab"]')->all()->count());

		foreach ($tabs as $tab) {
			$this->assertTrue($form->query('xpath:./' . '/li[@role="tab"]/' . '/a[text()='.CXPathHelper::escapeQuotes($tab).
					']')->one()->isValid());
		}

		// Check layout at Service tab
		$service_tab = $form->query('id:service-tab')->one();
		foreach ($service_tabs_labels as $label) {
			$this->assertTrue($service_tab->query('xpath:./' . '/label[text()='.CXPathHelper::escapeQuotes($label).']')->one(false)->isValid());
		}

		// Check Problem tags table data
		// Checks Problem tags table headers
		$problem_tags = ['Name', 'Operation', 'Value', 'Action'];
		$problem_tags_headers = $form->query('id:problem_tags')->asTable()->one()->getHeadersText();

		foreach($problem_tags as $key => $header) {
			$this->assertEquals($header, $problem_tags_headers[$key]);
		}

		// Check Problem tags table fields
		$problem_tags_values = ['tag' => '', 'operator' => 'Equals', 'value' => ''];
		$problem_tags_table = $form->query('id:problem_tags')->asMultifieldTable()->one()->getRowValue(0);

		foreach($problem_tags_values as $key => $value) {
			$this->assertEquals($value, $problem_tags_table[$key]);
		}

		$service_tab_limits = [
			'Name' => 128,
			'Sort order (0->999)' => 3
		];

		// Service tab fields maxlength attribute.
		foreach ($service_tab_limits as $field => $max_length) {
			$this->assertEquals($max_length, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check status calculation rule default value
		$this->assertEquals('Most critical of child services', $form->query('id:algorithm_focusable')->one()->getText());

		// Check advanced configuration default value
		$this->assertFalse($form->query('id:advanced_configuration')->asCheckbox()->one()->isChecked());

		// Check layout at SLA tab
		$form->selectTab('SLA');
		$sla_tab = $form->query('id:sla-tab')->one();

		// Check SLA tab lables
		$sla_tab_lables = ['SLA', 'Service times'];
		foreach ($sla_tab_lables as $label) {
			$this->assertTrue($sla_tab->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->one(false)->isValid());
		}

		// Check SLA checkbox
		$sla_checkbox = $sla_tab->query('id:showsla')->asCheckbox()->one();
		$this->assertTrue($sla_checkbox->isEnabled());
		$this->assertFalse($sla_checkbox->isChecked());

		// Check SLA value field
		$sla_input_field = $sla_tab->query('id:goodsla')->one();
		$this->assertFalse($sla_input_field->isEnabled());
		$this->assertEquals(99.9 ,$sla_input_field->asElement()->getValue());

		// Check Service times table labels
		$service_times_lables= ['Type', 'Interval', 'Note', 'Action'];
		$service_times_data = $sla_tab->query('id:times')->asTable()->one()->getHeadersText();
		foreach($service_times_lables as $key => $value) {
			$this->assertEquals($value, $service_times_data[$key]);
		}

		// Check layout at Tags tab
		$form->selectTab('Tags');
		$tags_tab = $form->query('id:tags-tab')->one();

		// Check Tags tab lables
		$this->assertTrue($tags_tab->query('xpath:.//label[text()="Tags"]')->one(false)->isValid());

		// Check Tags tab Tags table header labels
		$tags_tab_headers = ['Name', 'Value', 'Action'];
		$tags_tab_labels = $tags_tab->query('id:tags-table')->asTable()->one()->getHeadersText();

		foreach($tags_tab_headers as $key => $label) {
			$this->assertEquals($label, $tags_tab_labels[$key]);
		}

		// Check layout at Child services tab
		$form->selectTab('Child services');
		$child_services_tab = $form->query('id:child-services-tab')->one();

		// Check Tags tab lables
		$this->assertTrue($child_services_tab->query('xpath:.//label[text()="Child services"]')->one(false)->isValid());

		// Check Tags tab Tags table header labels
		$child_services = ['Service', 'Status calculation rule', 'Problem tags', 'Action'];
		$child_services_labels = $child_services_tab->query('id:children')->asTable()->one()->getHeadersText();

		foreach($child_services as $key => $label) {
			$this->assertEquals($label, $child_services_labels[$key]);
		}
	}

	public function getFormValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				],
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Sort order (0->999)' => 'zab'
					],
					'error_message' => 'Incorrect value "zab" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Sort order (0->999)' => '-1'
					],
					'error_message' => 'Incorrect value for field "sortorder": value must be no less than "0".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Sort order (0->999)' => ''
					],
					'error_message' => 'Incorrect value "" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'SLA' => [
						'fields' => [
							'Name' => 'Server 1'
						],
						'sla' => [
							'checked' => true,
							'value' => '-1'
						],
						'error_message' => 'Invalid parameter "/1/goodsla": value must be within the range of 0-100.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'SLA' => [
						'fields' => [
							'Name' => 'Server 1'
						],
						'sla' => [
							'checked' => true,
							'value' => 'aaa'
						],
						'error_message' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'SLA' => [
						'fields' => [
							'Name' => 'Server 1'
						],
						'sla' => [
							'checked' => true,
							'value' => '!@#$%^'
						],
						'error_message' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Server 1',
						'Sort order (0->999)' => '1'
					],
					'sla' => [
						'checked' => true,
						'value' => '1'
					],
					'success_message' => 'Service created'
				]
			]
		];
	}

	/**
	 * @dataProvider getFormValidationData
	 */
	public function testFormMonitoringServices_ServiceFormValidation($data) {

		$this->checkFormValidation($data);
	}


	public function getFormActionsData(){
		return [
			[
				[
					'action' => 'edit',
					'data' => [
						'query' => 'class:js-edit-service',
						'fields' => [
							'Name' => 'Server 2'
						],
						'expected' => [
							'Name' => 'Server 2'
						]
					]
				]
			],
			[
				[
					'action' => 'addChild',
					'data' => [
						'query' => 'class:js-add-child-service',
						'fields' => [
							'Name' => 'Server 3'
						],
						'expected' => [
							'Name' => 'Server 2 1'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFormActionsData
	 */
	public function testFormMonitoringServices_Actions($data) {
		$this->serviceActions($data);
	}

	private function serviceActions($data) {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		if ($data['action'] === 'edit') {
			$edit_data = CTestArrayHelper::get($data, 'data', []);

			$table = $this->query('class:list-table')->asTable()->one();
			$table->getRow(0)->query($edit_data['query'])->waitUntilClickable()->one()->click();

			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

			$form->fill($edit_data['fields']);
			$form->submit();
			$this->page->waitUntilReady();

			$this->assertTrue($table->query('xpath:.//td[2][text()="'.$edit_data['expected']['Name'].'"]')
				->waitUntilVisible()->one()->isValid());
		}
		elseif ($data['action'] === 'addChild') {
			$child_data = CTestArrayHelper::get($data, 'data', []);

			$table = $this->query('class:list-table')->asTable()->one();
			$table->getRow(0)->query($child_data['query'])->waitUntilClickable()->one()->click();

			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

			$form->fill($child_data['fields']);
			$form->submit();
			$this->page->waitUntilReady();

			$table->getRow(0)->waitUntilVisible();
			$this->assertEquals($child_data['expected']['Name'], $table->getRow(0)->getColumn('Name')->getText());
		}
	}

	public function testFormMonitoringServices_DeleteChildService() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->waitUntilVisible()->query('xpath://tbody/tr/td/a[text()="Server 2"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table->getRow(0)->waitUntilVisible()->query('class:js-remove-service')->waitUntilClickable()->one()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->query('xpath:.//ul/li[1]/span/a[text()="All services"]')->waitUntilClickable()->one()->click();

		$table->getRow(0)->waitUntilVisible();
		$this->assertTrue($table->getRow(0)->query('xpath://td[2][text()="Server 2"]')->one()->isValid());
	}

	public function testFormMonitoringServices_DeleteParentService() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->waitUntilClickable()->query('class:js-remove-service')->one()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$table->getRow(0)->waitUntilVisible();
		$this->assertTrue($table->getRow(0)->query('xpath://tr[@class="nothing-to-show"]')->waitUntilVisible()->one()->isValid());
	}

	private function setSLA($data, $form){
		$sla = $form->getFieldContainer('SLA');
		$sla->query('id:showsla')->asCheckbox()->one()->set($data['checked']);
		if (array_key_exists('value', $data)) {
			$sla->query('id:goodsla')->one()->overwrite($data['value']);
		}
	}

	private function checkFormValidation($data) {

		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM services';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->openCreateServiceForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		if (array_key_exists('SLA', $data)) {
			$sla = CTestArrayHelper::get($data, 'SLA', []);
			$form->fill($sla['fields']);

			$form->selectTab('SLA');

			$this->setSLA($sla['sla'], $form);

			$form->submit();

		}
		else {
			$form->fill($data['fields']);
			$form->submit();
		}

		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$table = $this->query('class:list-table')->asTable()->one();

			// Validate Service list
			$this->assertTrue($table->query('xpath:.//td[2][text()="'.$data['fields']['Name'].'"]')->one()->isValid());
		}
	}

	private function openCreateServiceForm() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();
	}
}
