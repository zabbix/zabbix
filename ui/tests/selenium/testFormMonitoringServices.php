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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testFormMonitoringServices extends CWebTest {

	use TableTrait;

	const UPDATE = true;

	// This is first service name for updating scenario.
	private static $update_service = 'Update service';
	// These ids are needed to check service linking in delete childs scenario.
	private static $parentid;
	private static $childid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function prepareServicesData() {
		CDataHelper::call('service.create', [
			[
				'name' => 'Update service',
				'algorithm' => 1,
				'showsla' => 0,
				'goodsla' => 99.99,
				'sortorder' => 4,
			],
			[
				'name' => 'Parent1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 5
			],
			[
				'name' => 'Parent2',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 50,
				'sortorder' => 5
			],
			[
				'name' => 'Parent3 with problem tags',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 49.5,
				'sortorder' => 5,
				'problem_tags' => [
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
				'name' => 'Parent4',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 5
			],
			[
				'name' => 'Child1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 20,
				'sortorder' => 6
			],
			[
				'name' => 'Child2 with tags',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 20,
				'sortorder' => 6,
				'problem_tags' => [
					[
						'tag' => 'test1',
						'value' => 'value1'
					],
					[
						'tag' => 'test2',
						'value' => 'value2'
					]
				]
			],
			[
				'name' => 'Child3',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 20,
				'sortorder' => 6
			],
			[
				'name' => 'Child4',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 20,
				'sortorder' => 6
			]
		]);

		$services = CDataHelper::getIds('name');

		CDataHelper::call('service.update', [
			[
				'serviceid' => $services['Child3'],
				'parents' => [
					[
						'serviceid' => $services['Parent2']
					]
				]
			],
			[
				'serviceid' => $services['Child4'],
				'parents' => [
					[
						'serviceid' => $services['Parent4']
					]
				]
			]
		]);

		self::$parentid = $services['Parent2'];
		self::$childid = $services['Child3'];
	}

	// Please check my comments about Layout scenario in PR.
	/**
	 * Check Service create form layout
	 */
	public function testFormMonitoringServices_Layout() {
		// Here we fistly open default view mode and then clikc Edit swith, to check switcher.
		// In next scenarios we open service.list.edit, because it is faster and no need to check switcher second time.
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->query('id:list_mode')->one()->asSegmentedRadio()->waitUntilClickable()->select('Edit');
		$this->query('button:Create service')->waitUntilClickable()->one()->click();
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

	public function getServiceData() {
		// This is what I meant, when asked to combine validation GOOD and BAD cases.
		// Please, based on my arrays, consider adding some more cases, for ex. Status calculation rule, Advanced configuration,
		// Additional rules, Status propagation rule, etc.
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				],
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Letters in sort order',
						'Sort order (0->999)' => 'zab'
					],
					'error' => 'Incorrect value "zab" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative sort order',
						'Sort order (0->999)' => '-1'
					],
					'error' => 'Incorrect value for field "sortorder": value must be no less than "0".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty sort order',
						'Sort order (0->999)' => ''
					],
					'error' => 'Incorrect value "" for "sortorder" field.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative SLA'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => '-1'
						]
					],
					'error' => 'Invalid parameter "/1/goodsla": value must be within the range of 0-100.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Letters in SLA'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => 'aaa'
						]
					],
					'error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Symbols in SLA'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => '!@#$%^'
						]
					],
					'error' => 'Invalid parameter "/1/goodsla": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Symbols in time'
					],
					'sla' => [
						'Service times' => [
							'Period type' => 'Downtime',
							'id:service-time-from-week' => 'Monday',
							'id:service-time-from-hour' => 'te',
							'id:service-time-from-minute' => 'st',
							'id:service-time-till-week' => 'Saturday',
							'id:service-time-till-hour' => 'ab',
							'id:service-time-till-minute' => 'cd'
						]
					],
					// Please pay attention, that we also can assert two or more errors in one array.
					'error' => [
						'Incorrect service start time.',
						'Incorrect service end time.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty time'
					],
					'sla' => [
						'Service times' => [
							'Period type' => 'Downtime',
							'id:service-time-from-week' => 'Tuesday',
							'id:service-time-from-hour' => '',
							'id:service-time-from-minute' => '',
							'id:service-time-till-week' => 'Thursday',
							'id:service-time-till-hour' => '',
							'id:service-time-till-minute' => ''
						]
					],
					'error' => [
						'Incorrect service start time.',
						'Incorrect service end time.'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong end time'
					],
					'sla' => [
						'Service times' => [
							'Period type' => 'Downtime',
							'id:service-time-from-week' => 'Wednesday',
							'id:service-time-from-hour' => '01',
							'id:service-time-from-minute' => '30',
							'id:service-time-till-week' => 'Friday',
							'id:service-time-till-hour' => '',
							'id:service-time-till-minute' => ''
						]
					],
					'error' => 'Incorrect service end time.'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Min sort order and SLA',
						'Sort order (0->999)' => '0'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => '0'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Max sort order, SLA, weight, etc',
						'Sort order (0->999)' => '999',
						'id:advanced_configuration' => true,
						'Status propagation rule' => 'Increase by',
						'id:propagation_value_number' => '5',
						'Weight' => '1000000'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => '100'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Intermediate values in sort order and SLA',
						'Sort order (0->999)' => '10',
						'id:advanced_configuration' => true,
						'Status propagation rule' => 'Fixed status',
						'id:propagation_value_status' => 'OK',
						'Weight' => '5'
					],
					'sla' => [
						'SLA' => [
							'checked' => true,
							'value' => '95.99'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Fixed status',
						'id:advanced_configuration' => true,
						'Status propagation rule' => 'Fixed status',
						'id:propagation_value_status' => 'Not classified',
						'Weight' => '0'
					]
				]
			],
//			 TODO: uncomment this when SLA task is ready.
//			[
//				[
//					'expected' => TEST_GOOD,
//					'fields' => [
//						'Name' => 'With SLA service intervals'
//					],
//					'sla' => [
//						'Service times' => [
//							'Period type' => 'Downtime',
//							'id:service-time-from-week' => 'Wednesday',
//							'id:service-time-from-hour' => '01',
//							'id:service-time-from-minute' => '30',
//							'id:service-time-till-week' => 'Friday',
//							'id:service-time-till-hour' => '17',
//							'id:service-time-till-minute' => '56'
//						]
//					]
//				]
//			],
			[
				[
					'fields' => [
						'Name' => 'With SLA service intervals 2'
					],
					'sla' => [
						'Service times' => [
							// This form is not standard, that's why here we need to find some fields by id instead of labels.
							'Period type' => 'Downtime',
							'id:service-time-from-week' => 'Monday',
							'id:service-time-from-hour' => '01',
							'id:service-time-from-minute' => '02',
							'id:service-time-till-week' => 'Tuesday',
							'id:service-time-till-hour' => '03',
							'id:service-time-till-minute' => '04'
						]
					]
				]
			],
			// This case should always be last, otherwise update sceanrio won't work.
			[
				[
					'fields' => [
						'Name' => 'With parent',
						'Parent services' => 'Parent1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getServiceData
	 *
	 * This annotation is necessary, in order to DB check in update scenario be working.
	 * @backupOnce services
	 */
	public function testFormMonitoringServices_Create($data) {
		$this->checkForm($data);
	}

	/**
	 * @dataProvider getServiceData
	 */
	public function testFormMonitoringServices_Update($data) {
		$this->checkForm($data, self::UPDATE);
	}

	// This is what I meant when asked to combine  create and update scenario.
	private function checkForm($data, $update = false) {
		// Default value is TEST_GOOD (3rd param) so we even may not write it in the data provider arrays.
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM services';
			$old_hash = CDBHelper::getHash('SELECT * FROM services');
		}

		// Open service form depending on create or update scenario.
		$this->page->login()->open('zabbix.php?action=service.list.edit');
		if ($update) {
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
			// Find in table previously defined service name.
			$table->findRow('Name', self::$update_service)->query('xpath:.//button[@title="Edit"]')
					->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create service')->waitUntilClickable()->one()->click();
		}

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

		if (array_key_exists('sla', $data)) {
			$form->selectTab('SLA');

			if (array_key_exists('SLA', $data['sla'])) {
				$sla = $form->getFieldContainer('SLA');
				$sla->query('id:showsla')->asCheckbox()->one()->set(CTestArrayHelper::get($data['sla'], 'SLA.checked'));
				$sla->query('id:goodsla')->one()->overwrite(CTestArrayHelper::get($data['sla'], 'SLA.value'));
			}

			if (array_key_exists('Service times', $data['sla'])) {
				$service = $form->getFieldContainer('Service times');
				$service->query('button:Add')->waitUntilClickable()->one()->click();
				// Find second dialog and work with it, because now there is multi-layer dialogs.
				$service_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$service_form = $service_dialog->query('id:service-time-form')->waitUntilReady()->one()->asFluidForm();
				$service_form->fill($data['sla']['Service times']);
				$service_form->submit();

				if ($expected === TEST_BAD) {
					$this->assertMessage(TEST_BAD, null, $data['error']);
					// When SLA error is caught, no need to continue executing test.
					return;
				}
			}
			// Return to tab Service for filling it.
			// In case if we have to fill other tab, switch to it in optimal order.
			$form->selectTab('Service');
		}

		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			// Check success message after service creation. I added message behavior in 22 and 45 lines.
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			// Check that Service with given name was not saved in DB.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name'])));
		}
		else {
			// Here Message text depends on Create or Update scenario.
			$this->assertMessage(TEST_GOOD, ($update ? 'Service updated' : 'Service created'));
			// Check that Service was actually created or updated in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name'])));

			if ($update) {
				// In update scenario check that old name actually changed.
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.
						zbx_dbstr(self::$update_service)));
				// In update scenario we need to rewrite name for updating service in order to update the same service in next case.
				// This also could be done using service id, it's up to you, but my variant seems to be easier.
				self::$update_service = $data['fields']['Name'];
			}

			// Open just created or updated Service and check that all fields present correctly in form.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

			// If it is child service, we need to open parent firstly.
			if (array_key_exists('Parent services', $data['fields'])) {
				$table->findRow('Name', $data['fields']['Parent services'], true)
						->query('link', $data['fields']['Parent services'])->waitUntilClickable()->one()->click();
			}
			// Find necessary Service name in table and localize its' row. Then in that row we can click Edit button.
			$table->findRow('Name', $data['fields']['Name'])->query('xpath:.//button[@title="Edit"]')
					->waitUntilClickable()->one()->click();

			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();

			// Check that fields in frontend are the same as in data provider.
			if (array_key_exists('sla', $data)) {
				$form->selectTab('SLA');
				if (array_key_exists('SLA', $data['sla'])) {
					$form->query('id:showsla')->asCheckbox()->one()->isChecked($data['sla']['SLA']['checked']);
					$form->query('id:goodsla')->one()->checkValue($data['sla']['SLA']['value']);
				}

				if (array_key_exists('Service times', $data['sla'])) {
					$interval = $data['sla']['Service times']['id:service-time-from-week'].' '.
							$data['sla']['Service times']['id:service-time-from-hour'].':'.
							$data['sla']['Service times']['id:service-time-from-minute'].' - '.
							$data['sla']['Service times']['id:service-time-till-week'].' '.
							$data['sla']['Service times']['id:service-time-till-hour'].':'.
							$data['sla']['Service times']['id:service-time-till-minute'];

					// To use this function I added Table Trait in 23 and 32 line.
					$this->assertTableData([
							[
								'Type' => $data['sla']['Service times']['Period type'],
								'Interval' => $interval,
								'Note' => ''
							]
						], 'id:times'
					);
				}
			}

			// This is easy framework function to check form values equal to data provider.
			$form->checkValue($data['fields']);
		}
	}

	// Please pay attention to data function naming and to it correlates with test function name.
	public function getCreateChildData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'parent' => 'Parent3 with problem tags',
					'fields' => [
						'Name' => 'With parent that has problem tags'
					],
					'error' => 'Service "Parent3 with problem tags" cannot have problem tags and children at the same time.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'circular' => true,
					'parent' => 'Child3',
					'fields' => [
						'Name' => 'Circular dependency',
					],
					'Child services' => 'Parent2',
					'error' => 'Services form a circular dependency.'
				]
			],
			[
				[
					'parent' => 'Parent1',
					'fields' => [
						'Name' => 'With parent without tags'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateChildData
	 */
	public function testFormMonitoringServices_CreateChild($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM services';
			$old_hash = CDBHelper::getHash('SELECT * FROM services');
		}

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

		if(CTestArrayHelper::get($data, 'circular', false)) {
			$table->findRow('Name', $data['Child services'], true)->query('link', $data['Child services'])
					->waitUntilClickable()->one()->click();
		}
		// Find necessary row and then find Add child button right in that row.
		$table->findRow('Name', $data['parent'], true)->query('xpath:.//button[@title="Add child service"]')
				->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();
		$form->fill($data['fields']);

		// Go to child services tab and add child there to create circular dependency.
		if(CTestArrayHelper::get($data, 'circular', false)) {
			$form->selectTab('Child services');
			$service = $form->getFieldContainer('Child services');
			$service->query('button:Add')->waitUntilClickable()->one()->click();
			$childs_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$childs_dialog->query('link', $data['Child services'])->waitUntilReady()->one()->click();
			$childs_dialog->invalidate();
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			// Check that faulty service was not created in DB.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name'])));
		}
		else {
			// Check success message after service creation. I added message behavior in 22 and 45 lines.
			$this->assertMessage(TEST_GOOD, 'Service created');
			// Check that child was actually created in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($data['fields']['Name'])));
			// Check that child is present in frontend.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
			// Firstly click on parent.
			$table->findRow('Name', $data['parent'], true)->query('link', $data['parent'])
					->waitUntilClickable()->one()->click();
			// Find Edit button in front of Parent name and click it.
			$table->findRow('Name', $data['fields']['Name'])->query('xpath:.//button[@title="Edit"]')
					->waitUntilClickable()->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();
			// Check that all form field were filled in correctly.
			$form->checkValue($data['fields']);
			// Check parent field separately, because it was not present in data[fields] array.
			$this->assertEquals([$data['parent']], $form->getField('Parent services')->getValue());
		}
	}

	public function testFormMonitoringServices_DeleteChild() {
		// This is our linked services parent and child names.
		// I added them before test, to easily find them and change them in case we want that in future.
		$parent = 'Parent2';
		$child = 'Child3';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		// Again, I find particular row by service name, it is the most stable and easy readable variant.
		$table->findRow('Name', $parent, true)->query('link', $parent)
					->waitUntilClickable()->one()->click();
		// Open Info tab in case it is not opened yet.
		$this->query('link:Info')->one()->waitUntilClickable()->click();
		// Find edit button for parent.
		$this->query('id:tab_info')->one()->waitUntilVisible()->query('xpath:.//button[contains(@class, "btn-edit")]')
				->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asFluidForm()->one()->waitUntilReady();
		$form->selectTab('Child services');
		// Go to Childs tab and find row by particular Service name in Childs table.
		$service_table = $form->getFieldContainer('Child services')->asTable();
		$service_table->findRow('Service', $child, true)->query('button:Remove')
				->waitUntilClickable()->one()->click();
		// Make sure that Name disappeared right after removing.
		// This is not idel implementation, maybe it was possible to work with it as with table row, but xpath was faster to write.
		$this->assertFalse($service_table->query('xpath:.//table[@id="children"]//td[contains(text(),'.$child.')]')->exists());
		$form->submit();
		// Again, checking success or fail message is mandatory every time when it appears.
		$this->assertMessage(TEST_GOOD, 'Service updated');
		// Check No data found. text in table under Parent. This function is also from Table trait.
		$this->assertTableData([]);
		// Check that both parent and child remained in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($parent)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					zbx_dbstr($child)));
		// Check that service linking is disappeared from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
					self::$parentid.'AND servicedownid ='.self::$childid));
	}

	// Please, after learning all my code and comments in testFormMonitoringServices_DeleteChild, rewrite this scenario in similar way.
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
}
