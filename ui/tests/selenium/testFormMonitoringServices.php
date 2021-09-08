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
class testFormMonitoringServices extends CWebTest
{

	public static function prepareServicesData() {
		$response = CDataHelper::call('service.create', [
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
				'tags' =>
				[
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
				'tags' =>
				[
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
				'problem_tags' =>
				[
					[
						'tag' => 'problem',
						'operator' => 0,
						'value' => 'true'
					]
				]
			]
		]);

		$serviceid_first = (int)$response['serviceids'][0];
		$serviceid_second = (int)$response['serviceids'][1];
		$serviceid_third = (int)$response['serviceids'][2];

		CDataHelper::call('service.update', [
			[
				'serviceid' =>  $serviceid_first ,
				'parents' => [
					[
						'serviceid' => $serviceid_second
					]
				]
			],
			[
				'serviceid' => $serviceid_second,
				'parents' => [
					[
						'serviceid' => $serviceid_third
					]
				]
			]
		]);
	}

	/**
	 * Check Service create form layout
	 */
	public function testFormMonitoringServices_CheckFormLayout()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();

		$serviceTabLabels = [
			'Name',
			'Parent services',
			'Problem tags',
			'Sort order (0->999)',
			'Status calculation rule',
			'Advanced configuration'
		];

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		// Check tabs available in the form
		$tabs = ['Service', 'SLA', 'Tags', 'Child services'];
		$this->assertEquals(count($tabs), $form->query('xpath:./' . '/li[@role="tab"]')->all()->count());

		foreach ($tabs as $tab) {
			$this->assertTrue($form->query('xpath:./' . '/li[@role="tab"]/' . '/a[text()='.zbx_dbstr($tab).
					']')->one()->isValid());
		}

		// Check layout at Service tab
		$service_tab = $form->query('id:service-tab')->one();
		foreach ($serviceTabLabels as $label) {
			$this->assertTrue($service_tab->query('xpath:./' . '/label[text()='.zbx_dbstr($label).']')->one(false)->isValid());
		}

		// Check Problem tags table data
		// Checks Problem tags table headers
		$problem_tags_table_headers_data = ['Name', 'Operation', 'Value', 'Action'];
		$problem_tags_table_headers = $form->query('id:problem_tags')->asTable()->one()->getHeadersText();

		foreach($problem_tags_table_headers_data as $key => $header)
		{
			$this->assertEquals($header, $problem_tags_table_headers[$key]);
		}

		// Check Problem tags table fields
		$problem_tags_table_values_data = ['tag' => '', 'operator' => 'Equals', 'value' => ''];
		$problem_tags_table_values = $form->query('id:problem_tags')->asMultifieldTable()->one()->getRowValue(0);

		foreach($problem_tags_table_values_data as $key => $value)
		{
			$this->assertEquals($value, $problem_tags_table_values[$key]);
		}

		$serviceTabFiledsMaxLength = [
			'Name' => 128,
			'Sort order (0->999)' => 3
		];

		// Service tab fields maxlength attribute.
		foreach ($serviceTabFiledsMaxLength as $field => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check layout at SLA tab
		$form->selectTab('SLA');
		$sla_tab = $form->query('id:sla-tab')->one();

		// Check SLA tab lables
		$sla_tab_lables = ['SLA', 'Service times'];
		foreach ($sla_tab_lables as $label) {
			$this->assertTrue($sla_tab->query('xpath:.//label[text()='.zbx_dbstr($label).']')->one(false)->isValid());
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
		$service_times_lables_data = ['Type', 'Interval', 'Note', 'Action'];
		$service_times_lables = $sla_tab->query('id:times')->asTable()->one()->getHeadersText();
		foreach($service_times_lables_data as $key => $value)
		{
			$this->assertEquals($value, $service_times_lables[$key]);
		}

		// Check layout at Tags tab
		$form->selectTab('Tags');
		$tags_tab = $form->query('id:tags-tab')->one();

		// Check Tags tab lables
		$this->assertTrue($tags_tab->query('xpath:.//label[text()="Tags"]')->one(false)->isValid());

		// Check Tags tab Tags table header labels
		$tags_tab_tags_table_headers_labels_data = ['Name', 'Value', 'Action'];
		$tags_tab_tags_table_headers_labels = $tags_tab->query('id:tags-table')->asTable()->one()->getHeadersText();

		foreach($tags_tab_tags_table_headers_labels_data as $key => $label)
		{
			$this->assertEquals($label, $tags_tab_tags_table_headers_labels_data[$key]);
		}

		// Check layout at Child services tab
		$form->selectTab('Child services');
		$child_services_tab = $form->query('id:child-services-tab')->one();

		// Check Tags tab lables
		$this->assertTrue($child_services_tab->query('xpath:.//label[text()="Child services"]')->one(false)->isValid());

		// Check Tags tab Tags table header labels
		$child_services_child_services_tab_table_headers_labels_data = ['Service', 'Status calculation rule', 'Problem tags', 'Action'];
		$child_services_child_services_tab_table_headers_labels = $child_services_tab->query('id:children')->asTable()->one()->getHeadersText();

		foreach($child_services_child_services_tab_table_headers_labels_data as $key => $label)
		{
			$this->assertEquals($label, $child_services_child_services_tab_table_headers_labels[$key]);
		}
	}

	public function getFormValidationData() {
		return [
			[
				[
					'fields' => [
						'Name' => ''
					],
					'error_message' => 'Incorrect value for field "name": cannot be empty.'
				],
			],
			[
				[
					'fields' => [
						'Sort order (0->999)' => 'zab'
					],
					'error_message' => 'Incorrect value "zab" for "sortorder" field.'
				]
			],
			[
				[
					'fields' => [
						'Sort order (0->999)' => '-1'
					],
					'error_message' => 'Incorrect value for field "sortorder": value must be no less than "0".'
				]
			],
			[
				[
					'SLA' =>
					[
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
					'SLA' =>
					[
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
					'SLA' =>
					[
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
			]
		];
	}

	/**
	 * @dataProvider getFormValidationData
	 */
	public function testFormMonitoringServices_ServiceFormValidation($data)
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		if(array_key_exists('SLA', $data)){
			$sla = CTestArrayHelper::get($data, 'SLA', []);
			$form->fill($sla['fields']);

			$form->selectTab('SLA');

			$this->setSLA($sla['sla'], $form);

		}else{
			$form->fill($data['fields']);
		}

		$form->submit();
		$this->page->waitUntilReady();
	}

	public function testFormMonitoringServices_Create()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Open service create form
		$this->query('xpath://button[@class="js-create-service"]')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		$data = [
			'fields' => [
				'Name' => 'Server 1',
				'Sort order (0->999)' => '1'
			]
		];

		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();
		sleep(1);

		$table = $this->query('class:list-table')->asTable()->one();

		// Validate Service list

		$this->assertTrue($table->query('xpath:.//td[2][text()="Server 1"]')->one()->isValid());
	}

	public function testFormMonitoringServices_Update()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->query('class:js-edit-service')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		$form->fill(['Name' => 'Server 2']);
		$form->submit();
		$this->page->waitUntilReady();
		sleep(2);

		$this->assertTrue($table->query('xpath:.//td[2][text()="Server 2"]')->waitUntilVisible()->one()->isValid());
	}

	public function testFormMonitoringServices_AddChild()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one()->getRow(0);
		$table->query('class:js-add-child-service')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		$form->fill(['Name' => 'Server 3']);
		$form->submit();
		$this->page->waitUntilReady();
		sleep(1);

		$this->assertEquals('Server 2 1', $table->getColumn('Name')->getText());

	}

	public function testFormMonitoringServices_DeleteParentService()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->query('class:js-remove-service')->waitUntilClickable()->one()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		sleep(2);

		$this->assertTrue($table->query('xpath:.//td[2][text()="Server 3"]')->waitUntilVisible()->one()->isValid());
	}

	public function testFormMonitoringServices_DeleteChildService()
	{
		$this->page->login()->open('zabbix.php?action=service.list');

		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->query('xpath://tbody/tr/td[text()="Server 3"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$table->getRow(0)->query('class:js-remove-service')->waitUntilClickable()->one()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		sleep(2);

		$this->assertTrue($table->query('xpath:.//tr[@class="nothing-to-show"]')->waitUntilVisible()->one()->isValid());
	}

	private function setSLA($data, $form)
	{
		$sla = $form->getFieldContainer('SLA');
		$sla->query('id:showsla')->asCheckbox()->one()->set($data['checked']);
		if (array_key_exists('value', $data)) {
			$sla->query('id:goodsla')->one()->overwrite($data['value']);
		}
	}
}
