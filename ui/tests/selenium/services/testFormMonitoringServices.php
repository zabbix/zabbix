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

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testFormMonitoringServices extends CWebTest {

	use TableTrait;

	const UPDATE = true;

	private static $update_service = 'Update service';

	private static $parentid;
	private static $childid;
	private static $parentid_2;
	private static $childid_2;

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
		self::$parentid_2 = $services['Parent4'];
		self::$childid_2 = $services['Child4'];
	}

	/**
	 * Check Service create form layout.
	 */
	public function testFormMonitoringServices_Layout() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->query('id:list_mode')->one()->asSegmentedRadio()->waitUntilClickable()->select('Edit');
		$this->query('button:Create service')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();

		// Check tabs available in the form.
		$tabs = ['Service', 'SLA', 'Tags', 'Child services'];
		$this->assertEquals(count($tabs), $form->query("xpath:.//li[@role='tab']")->all()->count());

		foreach ($tabs as $tab) {
			$this->assertTrue($form->query("xpath:.//li[@role='tab']//a[text()=".CXPathHelper::escapeQuotes($tab).']')
					->one()->isValid());
		}

		$service_tabs_labels = [
			'Name',
			'Parent services',
			'Problem tags',
			'Sort order (0->999)',
			'Status calculation rule',
			'Advanced configuration'
		];

		// Check layout at Service tab.
		foreach ($service_tabs_labels as $label) {
			$this->assertTrue($form->query('id:service-tab')->one()->query("xpath:.//label[text()=".
					CXPathHelper::escapeQuotes($label).']')->one(false)->isValid());
		}

		// Check Problem tags table data.
		// Checks Problem tags table headers.
		$this->assertSame(['Name', 'Operation', 'Value', 'Action'],
				$form->query('id:problem_tags')->asTable()->one()->getHeadersText()
		);

		// Check Problem tags table fields.
		$form->query('id:problem_tags')->asMultifieldTable()->one()
				->checkValue([['tag' => '', 'operator' => 'Equals', 'value' => '']]
		);

		$service_tab_limits = [
			'Name' => 128,
			'Sort order (0->999)' => 3
		];

		// Service tab fields maxlength attribute.
		foreach ($service_tab_limits as $field => $max_length) {
			$this->assertEquals($max_length, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check status calculation rule default value.
		$this->assertEquals('Most critical of child services', $form->getField('Status calculation rule')->getText());

		// Check hint-box.
		$form->query('id:algorithm-not-applicable-warning')->one()->click();
		$hint = $form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();
		$hintbox = 'Status calculation rule and additional rules are only applicable if child services exist.';
		$this->assertEquals($hintbox, $hint->one()->getText());

		// Close the hint-box.
		$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();

		// Check advanced configuration default value.
		$this->assertFalse($form->query('id:advanced_configuration')->asCheckbox()->one()->isChecked());

		// Check layout at SLA tab.
		$form->selectTab('SLA');

		// Check SLA tab labels.
		$sla_tab_labels = ['SLA', 'Service times'];
		foreach ($sla_tab_labels as $label) {
			$this->assertTrue($form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).']')->one(false)->isValid());
		}

		// Check SLA checkbox.
		$sla_checkbox = $form->getField('SLA')->asCheckbox();
		$this->assertTrue($sla_checkbox->isEnabled());
		$this->assertFalse($sla_checkbox->isChecked());

		// Check SLA value field.
		$sla_input_field = $form->query('id:sla-tab')->one()->query('id:goodsla')->one();
		$this->assertFalse($sla_input_field->isEnabled());
		$this->assertEquals(99.9, $sla_input_field->getValue());

		// Check Service times table labels.
		$this->assertSame(['Type', 'Interval', 'Note', 'Action'],
				$form->query('id:sla-tab')->one()->query('id:times')->asTable()->one()->getHeadersText()
		);

		// Check layout at Tags tab.
		$form->selectTab('Tags');

		// Check Tags tab labels.
		$this->assertTrue($form->query('id:tags-tab')->one()->query('xpath:.//label[text()="Tags"]')
				->one()->isValid()
		);

		// Check Tags tab Tags table header labels.
		$this->assertSame(['Name', 'Value', 'Action'],
				$form->query('id:tags-tab')->one()->query('id:tags-table')->asTable()->one()->getHeadersText()
		);

		// Check layout at Child services tab.
		$form->selectTab('Child services');

		// Check Child services tab labels.
		$this->assertTrue($form->query('id:child-services-tab')->one()->query('xpath:.//label[text()="Child services"]')
				->one()->isValid()
		);

		// Check Tags tab Tags table header labels.
		$this->assertSame(['Service', 'Status calculation rule', 'Problem tags', 'Action'],
				$form->query('id:child-services-tab')->one()->query('id:children')->asTable()->one()->getHeadersText()
		);

		$form->getFieldContainer('Child services')->query('button:Add')->waitUntilClickable()->one()->click();
		$childs_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

		// Check popup title.
		$this->assertEquals('Add child services', $childs_dialog->getTitle());

		// Check input fields maxlength.
		$this->assertEquals(255, $childs_dialog->query('id:services-filter-name')->one()->getAttribute('maxlength'));

		// Check "select all" checkbox default value.
		$this->assertFalse($childs_dialog->query('id:serviceid_all')->asCheckbox()->one()->isChecked());

		// Enter and submit filtering data.
		$childs_dialog->query('id:services-filter-name')->one()->fill('Parent1');
		$childs_dialog->query('button:Filter')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();
		$childs_dialog->invalidate();

		// Check filtering result.
		$result = [
			[
				'Name' => 'Parent1',
				'Status calculation rule' => 'Most critical if all children have problems',
				'Problem tags' => ''
			]
		];
		$this->assertTableData($result, 'xpath://div[@data-dialogueid="services"]//table[@class="list-table"]');

		// Check filtering reset.
		$childs_dialog->query('button:Reset')->one()->waitUntilClickable()->click();
		$childs_dialog->invalidate();

		$this->assertEquals(9, count($childs_dialog->query('class:list-table')->asTable()->waitUntilReady()->one()
				->getRows()->asArray())
		);
	}

	public function getServicesData() {
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
			[
				[
					'fields' => [
						'Name' => 'Service with childs'
					],
					'childs' => [
						'Child services' => [
							'Service' => 'Child4',
							'Status calculation rule' => 'Most critical if all children have problems',
							'Problem tags' => '',
							'Action' => 'Remove'
						]
					]
				]
			],
			// Check that you can create service with already existing name.
			[
				[
					'fields' => [
						'Name' => 'Child1',
					],
					'dublicate' => true
				]
			],
			// This case should always be last, otherwise update sceanrio won't work.
			[
				[
					'fields' => [
						'Name' => 'With parent',
						'Parent services' => 'Parent1'
					],
					'update_dublicate' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getServicesData
	 *
	 * @backupOnce services
	 */
	public function testFormMonitoringServices_Create($data) {
		$this->checkForm($data);
	}

	/**
	 * @dataProvider getServicesData
	 */
	public function testFormMonitoringServices_Update($data) {
		$this->checkForm($data, self::UPDATE);
	}

	/**
	 * Function for checking Service create or update form, successful submitting and validation.
	 *
	 * @param array	    $data      data provider
	 * @param boolean	$update    true if it is update scenario, false if create(default)
	 */
	private function checkForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM services';
			$old_hash = CDBHelper::getHash('SELECT * FROM services');
		}

		// Open service form depending on create or update scenario.
		$this->page->login()->open('zabbix.php?action=service.list.edit');
		if ($update) {
			$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();
			$table->findRow('Name', self::$update_service, true)->query('xpath:.//button[@title="Edit"]')
					->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create service')->waitUntilClickable()->one()->click();
		}

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();

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

				$service_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$service_form = $service_dialog->query('id:service-time-form')->waitUntilReady()->one()->asForm();

				// Check default values in popup.
				$this->assertEquals('Uptime', $service_form->query('id:service-time-type-focusable')->one()->getText());
				$this->assertEquals('Sunday', $service_form->query('id:service-time-from-week-focusable')->one()->getText());
				$this->assertEquals('Sunday', $service_form->query('id:service-time-till-week-focusable')->one()->getText());

				$service_form_defaults = [
						'id:service-time-from-hour' => [255, 'hh'],
						'id:service-time-from-minute' => [255, 'mm'],
						'id:service-time-till-hour' => [255, 'hh'],
						'id:service-time-till-minute' => [255, 'mm']
				];

				// Check input fields maxlength.
				foreach ($service_form_defaults as $field => $max_length) {
					$this->assertEquals($max_length[0], $service_form->query($field)->one()->getAttribute('maxlength'));
				}

				// Check input fields placeholders.
				foreach ($service_form_defaults as $field => $placeholder) {
					$this->assertEquals($placeholder[1], $service_form->query($field)->one()->getAttribute('placeholder'));
				}

				$service_form->fill($data['sla']['Service times']);
				$service_form->submit();

				if ($expected === TEST_BAD) {
					$this->assertMessage(TEST_BAD, null, $data['error']);
					return;
				}
			}
		}
		elseif (array_key_exists('childs', $data)) {
			$form->selectTab('Child services');

			$service = $form->getFieldContainer('Child services');
			$service->query('button:Add')->waitUntilClickable()->one()->click();
			$childs_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$table = $childs_dialog->query('class:list-table')->asTable()->waitUntilVisible()->one();
			$table->findRow('Name', $data['childs']['Child services']['Service'])->select();
			$childs_dialog->query('button:Select')->waitUntilClickable()->one()->click();
			$this->assertTableData([$data['childs']['Child services']], 'id:children');
		}

		// Return to tab Service for filling it.
		$form->selectTab('Service');
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					CXPathHelper::escapeQuotes($data['fields']['Name'])));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($update ? 'Service updated' : 'Service created'));
			$count = (array_key_exists('dublicate', $data)) ? 2 : 1;
			$this->assertEquals($count, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					CXPathHelper::escapeQuotes($data['fields']['Name']))
			);

			if ($update) {
				// In update scenario check that old name actually changed.
				$expected_count = (array_key_exists('update_dublicate', $data)) ? 1 : 0;
				$this->assertEquals($expected_count, CDBHelper::getCount('SELECT * FROM services WHERE name='.
						CXPathHelper::escapeQuotes(self::$update_service))
				);

				//  Write new name to global variable for using it in next case.
				self::$update_service = $data['fields']['Name'];
			}

			// Open just created or updated Service and check that all fields present correctly in form.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

			// If it is child service, we need to open parent firstly.
			if (array_key_exists('Parent services', $data['fields'])) {
				$table->findRow('Name', $data['fields']['Parent services'], true)
						->query('link', $data['fields']['Parent services'])->waitUntilClickable()->one()->click();
			}

			if (array_key_exists('childs', $data)) {
				$row = $table->findRow('Name', $data['fields']['Name'], true);

				$this->assertEquals($data['fields']['Name'].' '.count($data['childs']),
						$row->getColumn('Name')->getText()
				);

				$row->query('xpath:.//button[@title="Edit"]')->waitUntilClickable()->one()->click();
				$form->selectTab('Child services');
				$this->assertTableData([$data['childs']['Child services']], 'css: form#service-form div#child-services-tab table#children');
			}
			else {
				$table->findRow('Name', $data['fields']['Name'])->query('xpath:.//button[@title="Edit"]')
						->waitUntilClickable()->one()->click();
			}

			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();

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
			elseif (array_key_exists('childs', $data)) {
				$form->selectTab('Child services');
				$this->assertTableData([$data['childs']['Child services']], 'css: form#service-form div#child-services-tab table#children');
			}

			if (array_key_exists('childs', $data)){
				$form->checkValue($data['fields']['Name'].' '.count($data['childs']));
			}
			else {
				$form->checkValue($data['fields']);
			}
		}
	}

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
			$sql = 'SELECT * FROM services ORDER BY serviceid';
			$old_hash = CDBHelper::getHash('SELECT * FROM services ORDER BY serviceid');
		}

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();

		if (CTestArrayHelper::get($data, 'circular', false)) {
			$table->findRow('Name', $data['Child services'], true)->query('link', $data['Child services'])
					->waitUntilClickable()->one()->click();
		}
		// Find necessary row and then find Add child button right in that row.
		$table->findRow('Name', $data['parent'], true)->query('xpath:.//button[@title="Add child service"]')
				->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();
		$form->fill($data['fields']);

		// Go to child services tab and add child there to create circular dependency.
		if (CTestArrayHelper::get($data, 'circular', false)) {
			$form->selectTab('Child services');
			$service = $form->getFieldContainer('Child services');
			$service->query('button:Add')->waitUntilClickable()->one()->click();
			$childs_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$childs_dialog->query('link', $data['Child services'])->waitUntilReady()->one()->click();
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					CXPathHelper::escapeQuotes($data['fields']['Name']))
			);
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Service created');
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
					CXPathHelper::escapeQuotes($data['fields']['Name']))
			);

			// Check that child is present in frontend.
			$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
			// Firstly click on parent.
			$table->findRow('Name', $data['parent'], true)->query('link', $data['parent'])
					->waitUntilClickable()->one()->click();
			$table->findRow('Name', $data['fields']['Name'])->query('xpath:.//button[@title="Edit"]')
					->waitUntilClickable()->one()->click();

			COverlayDialogElement::find()->one()->waitUntilReady();
			$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();

			// Check that all form fields were saved correctly.
			$form->checkValue($data['fields']);
			// Check parent field separately, because it was not present in data[fields] array.
			$this->assertEquals([$data['parent']], $form->getField('Parent services')->getValue());
		}
	}

	public function testFormMonitoringServices_DeleteChild() {
		$parent = 'Parent2';
		$child = 'Child3';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();

		$this->query('id:tab_info')->one()->waitUntilVisible()->query('xpath:.//button[contains(@class, "btn-edit")]')
				->one()->waitUntilClickable()->click();

		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->selectTab('Child services');

		// Go to "Childs" tab and find row by particular Service name in Childs table.
		$service_table = $form->getFieldContainer('Child services')->asTable();
		$service_table->findRow('Service', $child, true)->query('button:Remove')
				->waitUntilClickable()->one()->click();

		// Make sure that Name disappeared right after removing.
		$this->assertFalse($service_table->query("xpath:.//table[@id='children']//td[contains(text(),".
				CXPathHelper::escapeQuotes($child).')]')->exists());
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'Service updated');

		// Check "No data found." text in table under Parent.
		$this->assertTableData([]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
				CXPathHelper::escapeQuotes($parent)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
				CXPathHelper::escapeQuotes($child)));

		// Check that service linking is disappeared from DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
				self::$parentid.' AND servicedownid ='.self::$childid));
	}

	public function testFormMonitoringServices_DeleteParent() {
		$parent = 'Parent4';
		$child = 'Child4';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->one()->waitUntilReady();
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$table->findRow('Name', $child, true)->query('xpath:.//button[contains(@class, "btn-edit")]')
				->one()->waitUntilClickable()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();
		$form->getField('Parent services')->asMultiselect()->clear();
		$form->submit();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service updated');
		$this->assertTableData([]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
				CXPathHelper::escapeQuotes($parent)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.
				CXPathHelper::escapeQuotes($child)));
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
				self::$parentid_2.' AND servicedownid ='.self::$childid_2));
	}
}
