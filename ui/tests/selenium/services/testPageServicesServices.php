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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @dataSource EntitiesTags, Services, Actions
 *
 * @backup services
 */
class testPageServicesServices extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const EDIT = true;

	const SERVICE_COUNT = 20;

	const LAYOUT_PARENT = 'Parent for 2 levels of child services';
	const LAYOUT_CHILD = 'Child service with child service';
	const LAYOUT_CHILD2 = 'Child service of child service';

	const ROOTCAUSE_PARENT = 'Test order';
	const ROOTCAUSE_CHILD1 = '1';
	const ROOTCAUSE_CHILD2 = '2';
	const ROOTCAUSE_CHILD3 = '3';

	const BREADCRUMB_SELECTOR = 'xpath://ul[@class="breadcrumbs"]';
	const TABLE_SELECTOR = 'id:service-list';

	/**
	 * Set parent and child services to Problem status and link the child services to the corresponding problem events.
	 */
	public static function prepareServiceProblemsData() {
		// Statuses: Warning = 2, Average = 3, High = 4.
		$service_statuses = [
			self::ROOTCAUSE_PARENT => 4,
			self::ROOTCAUSE_CHILD1 => 2,
			self::ROOTCAUSE_CHILD2 => 3,
			self::ROOTCAUSE_CHILD3 => 4
		];

		$service_ids = [];
		$i = 1;

		foreach ($service_statuses as $service => $status) {
			$service_ids[$service] = CDBHelper::getValue('SELECT serviceid FROM services WHERE name='.zbx_dbstr($service));

			// Change service status to Problem with the corresponding severity.
			DBexecute('UPDATE services SET status='.zbx_dbstr($status).' WHERE serviceid='.zbx_dbstr($service_ids[$service]));

			// Link child services to the corresponding problem events.
			if ($service !== self::ROOTCAUSE_PARENT) {
				// Corresponding trigger problem events have ids starting from 9001 in data_test.sql, so "9000 + $i" is used.
				DBexecute('INSERT into service_problem (service_problemid, eventid, serviceid, severity) '.
						'VALUES ('.$i.', '.(9000 + $i).', '.$service_ids[$service].', '.$status.')'
				);

				$i++;
			}
		}
	}

	public function testPageServicesServices_LayoutView() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		// Labels on columns at services list.
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		$this->assertSame(['Name', 'Status', 'Root cause', 'Created at', 'Tags'], $table->getHeadersText());
		$this->assertTableStats(self::SERVICE_COUNT);

		// Check that service buttons are not present in the table row.
		// Row count starts with 0, so here real service count - 1.
		$this->checkServiceButtons($table->getRow(rand(0, self::SERVICE_COUNT - 1)), false);

		// Check that "Edit elements" are not present in View mode.
		$elements = [
			'button:Create service',
			'id:filter_without_children',       // "Only services without children" checkbox in Filter.
			'id:filter_without_problem_tags',   // "Only services without problem tags" checkbox in Filter.
			'id:filter_tag_source_1',           // "Tag source" radiobutton in Filter.
			'id:all_services',                  // "Select all" checkbox in table.
			'button:Mass update',
			'button:Delete'
		];
		foreach ($elements as $element) {
			$this->assertFalse($table->query($element)->exists());
		}

		// Check parent-child service layout.
		$this->checkParentChildLayout($table, self::LAYOUT_PARENT, self::LAYOUT_CHILD);

		// Check Kiosk mode.
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();

		// Check that Header and Filter disappeared.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->assertFalse($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertTrue($this->query('id:service-list')->exists());

		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
		$this->page->waitUntilReady();

		// Check that Header and Filter are visible again.
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
		$this->assertTrue($this->query('xpath://div[@aria-label="Filter"]')->exists());
		$this->assertTrue($this->query('id:service-list')->exists());
	}

	public function testPageServicesServices_LayoutEdit() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->query('id:list_mode')->asSegmentedRadio()->one()->waitUntilClickable()->select('Edit');
		$this->page->waitUntilReady();

		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		$this->assertSame(['', 'Name', 'Status', 'Root cause', 'Created at', 'Tags', ''], $table->getHeadersText());
		$this->assertTableStats(self::SERVICE_COUNT);

		// Check that action buttons below the table are not enabled .
		$this->checkActionButtons(false);

		// Row count starts with 0, so here real service count - 1.
		$this->checkServiceButtons($table->getRow(rand(0, self::SERVICE_COUNT - 1)), true);
		$this->checkParentChildLayout($table, self::LAYOUT_PARENT, self::LAYOUT_CHILD, self::EDIT);
		$this->checkServiceButtons($table->findRow('Name', self::LAYOUT_CHILD2, true), true);

		// Check there is no Kiosk mode button.
		$this->assertFalse($this->query('xpath://button[@title="Kiosk mode"]')->exists());

		// Check Info/Filter tabs switching.
		foreach (['Filter' => 'tab_1', 'Info' => 'tab_info'] as $tab => $id) {
			$this->query("xpath://li[@role='tab']//a[text()=".CXpathHelper::escapeQuotes($tab)."]")
					->waitUntilClickable()->one()->click();
			$this->assertTrue($this->query('id', $id)->waitUntilReady()->one()->isVisible());
		}
	}

	/**
	 * Function for checking layout of Parent-Child chains layout.
	 *
	 * @param CTableElement    $table    table where to select particular service
	 * @param string           $parent   name of parent service
	 * @param string           $child    name of child service
	 * @param boolean          $edit     true if it is edit scenario, false otherwise
	 */
	private function checkParentChildLayout($table, $parent, $child, $edit = false) {
		$this->checkSeviceInfoLayout($table, $parent, $edit);
		$this->assertTableDataColumn([$child.' 1'], 'Name', 'xpath://form[@name="service_list"]//table');
		$this->checkSeviceInfoLayout($table, $child, $edit);
		$this->assertTableDataColumn([self::LAYOUT_CHILD2], 'Name', 'xpath://form[@name="service_list"]//table');

		$breadcrumbs = $this->query(self::BREADCRUMB_SELECTOR)->one();
		// Check breadcrumbs on last child page.
		foreach (['All services', $parent, $child] as $breadcrumb) {
			$this->assertTrue($breadcrumbs->query('link', $breadcrumb)->one()->isClickable());
		}

		// Check selected breadcrumb.
		$this->assertTrue($breadcrumbs->query("xpath:.//span[@class='selected']//a[text()=".
				CXPathHelper::escapeQuotes($child)."]")->one()->isValid()
		);
	}

	/**
	 * Function for checking layout of Service info card.
	 *
	 * @param CTableElement    $table    table where to select particular service
	 * @param string           $service  name of service to be checked
	 * @param boolean          $edit     true if it is edit scenario, false otherwise
	 */
	private function checkSeviceInfoLayout($table, $service, $edit = false) {
		$table->findRow('Name', $service, true)->query('link', $service)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		foreach (['Info', 'Filter'] as $tab) {
			$this->assertTrue($this->query("xpath://li[@role='tab']//a[text()=".CXPathHelper::escapeQuotes($tab).
					"]")->one()->isClickable());
		}

		$info_card = $this->query('id:tab_info')->waitUntilReady()->one();
		foreach ([$service, 'Parent services', 'Status', 'Tags'] as $text) {
			$this->assertTrue($info_card->query("xpath:.//div[@class='service-info-grid']//div[text()=".
					CXPathHelper::escapeQuotes($text)."]")->one()->isVisible()
			);
		}

		$edit_button = $info_card->query('xpath://button['.CXPathHelper::fromClass('js-edit-service').']');
		$this->assertEquals($edit, $edit_button->one(false)->isClickable());
		$table->invalidate();
		$this->assertTableStats(1);
	}

	/**
	 * Function for checking service action buttons in a table row.
	 *
	 * @param CTableRowElement    $row       row where buttons to be found
	 * @param boolean             $exists    true if buttons should exist, false otherwise
	 */
	private function checkServiceButtons($row, $exists) {
		foreach (['Add child service', 'Edit', 'Delete'] as $button) {
			// Here checking only visible, not clickable, because in some cases button can be disabled.
			$this->assertEquals($exists, $row->query("xpath:.//button[@title=".CXPathHelper::escapeQuotes($button).
					"]")->one(false)->isVisible()
			);
		}
	}

	/**
	 * Function for checking service action buttons in a table row.
	 *
	 * @param boolean    $enabled    true if buttons should be enabled, false otherwise
	 */
	private function checkActionButtons($enabled) {
		foreach (['Mass update', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}
	}

	public static function getFilterEditData() {
		return [
			// Tags source: Service.
			[
				[
					'Tags' => [
						'Source' => 'Service',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Contains',
								'value' => 'true'
							]
						]
					],
					'result' => [
						'Service with multiple service tags'
					]
				]
			],
			// Tag problem Equals false.
			[
				[
					'Tags' => [
						'Source' => 'Service',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Equals',
								'value' => 'false'
							]
						]
					],
					'result' => [
						'Simple actions service'
					]
				]
			],
			// Tags source: Service and Evaluation: Or.
			[
				[
					'Tags' => [
						'Source' => 'Service',
						'Evaluation' => 'Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Equals',
								'value' => 'false'
							],
							[
								'tag' => 'test',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Service with tags for updating',
						'Parent for 2 levels of child services 1',
						'Service with multiple service tags',
						'Simple actions service'
					]
				]
			],
			// Only services without children checkbox.
			[
				[
					'filter' => [
						'Name' => 'parent',
						'Only services without children' => true
					],
					'result' => [
						'Parent for child creation'
					]
				]
			],
			// Only services without problem tags checkbox.
			[
				[
					'filter' => [
						'Name' => 'parent',
						'Only services without problem tags' => true
					],
					'result' => [
						'Parent for 2 levels of child services 1',
						'Parent for deletion from row 1',
						'Parent for child deletion from row 1',
						'Clone parent 3',
						'Parent for child creation'
					]
				]
			]
		];
	}

	public static function getFilterCommonData() {
		return [
			// Only Name filtering.
			[
				[
					'filter' => [
						'Name' => 'parent'
					],
					'result' => [
						'Parent for 2 levels of child services 1',
						'Parent for deletion from row 1',
						'Parent for child deletion from row 1',
						'Clone parent 3',
						'Parent for child creation'
					],
					'check_breadcrumbs' => true
				]
			],
			// Name and Status: Any.
			[
				[
					'filter' => [
						'Name' => 'with problem',
						'Status' => 'Any'
					],
					'result' => [
						'Service with problem tags',
						'Service with problem'
					]
				]
			],
			// Name and Status: OK.
			[
				[
					'filter' => [
						'Name' => 'with problem',
						'Status' => 'OK'
					],
					'result' => [
						'Service with problem tags'
					]
				]
			],
			// Name and Status: Problem fields.
			[
				[
					'filter' => [
						'Name' => 'with problem',
						'Status' => 'Problem'
					],
					'result' => [
						'Service with problem'
					]
				]
			],
			// Evaluation: And/Or.
			[
				[
					'Tags' => [
						'Evaluation' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Contains',
								'value' => 'true'
							],
							[
								'tag' => 'test',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Service with multiple service tags'
					]
				]
			],
			// Evaluation: Or, Operators: Contains, Exists.
			[
				[
					'Tags' => [
						'Evaluation' => 'Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Contains',
								'value' => 'true'
							],
							[
								'tag' => 'test',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Service with tags for updating',
						'Parent for 2 levels of child services 1',
						'Service with multiple service tags',
						'Simple actions service'
					]
				]
			],
			// Operator: Equals.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Equals',
								'value' => 'false'
							]
						]
					],
					'result' => [
						'Simple actions service'
					]
				]
			],
			// Operator: Does not exist and Contains.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'test',
								'operator' => 'Contains',
								'value' => 'test'
							],
							[
								'tag' => 'problem',
								'operator' => 'Does not exist'
							]
						]
					],
					'result' => [
						'Parent for 2 levels of child services 1'
					]
				]
			],
			// Operator: Does not equal and Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Does not equal',
								'value' => 'false'
							],
							[
								'tag' => 'test',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Service with tags for updating',
						'Parent for 2 levels of child services 1',
						'Service with multiple service tags'
					]
				]
			],
			// Operator: Does not contain and Exists.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'problem',
								'operator' => 'Exists'
							],
							[
								'tag' => 'test',
								'operator' => 'Does not contain',
								'value' => '456'
							]
						]
					],
					'result' => [
						'Simple actions service'
					]
				]
			],
			// Empty table.
			[
				[
					'Tags' => [
						'evaluation_type' => 'And/Or',
						'tags' => [
							[
								'index' => 0,
								'action' => USER_ACTION_UPDATE,
								'tag' => 'test',
								'operator' => 'Contains',
								'value' => 'test9'
							]
						]
					],
					'result' => [],
					'check_breadcrumbs' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterCommonData
	 */
	public function testPageServicesServices_FilterView($data) {
		$this->checkFiltering($data);
	}

	/**
	 * @dataProvider getFilterEditData
	 * @dataProvider getFilterCommonData
	 */
	public function testPageServicesServices_FilterEdit($data) {
		$this->checkFiltering($data, self::EDIT);
	}

	/**
	 * Function for checking filtering on Services->Service page.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $edit      true if is edit scenario, false otherwise
	 */
	private function checkFiltering($data, $edit = false) {
		$this->page->login()->open(($edit === false) ? 'zabbix.php?action=service.list' :
				'zabbix.php?action=service.list.edit'
		);
		$filter_form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$filter_form->query('button:Reset')->one()->click();

		// Fill filter form with data.
		$filter_form->fill(CTestArrayHelper::get($data, 'filter'));

		// If data contains Tags fill them separately, because tags form is more complicated.
		if (CTestArrayHelper::get($data, 'Tags')) {
			if (CTestArrayHelper::get($data['Tags'], 'Source')) {
				$filter_form->getField('id:filter_tag_source')->asSegmentedRadio()->fill($data['Tags']['Source']);
			}

			if (CTestArrayHelper::get($data['Tags'], 'Evaluation')) {
				$filter_form->getField('id:filter_evaltype')->asSegmentedRadio()->fill($data['Tags']['Evaluation']);
			}

			$filter_form->getField('id:filter-tags')->asMultifieldTable()->fill(CTestArrayHelper::get($data, 'Tags.tags'));
		}

		$filter_form->submit();
		$this->page->waitUntilReady();

		// Check filtered result.
		$this->assertTableDataColumn($data['result'], 'Name');

		// Check breadcrumbs and table headers.
		$selector = $this->query(self::BREADCRUMB_SELECTOR);
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		if (CTestArrayHelper::get($data, 'check_breadcrumbs')) {
			$this->assertTrue($selector->one()->query('link:All services')->one()->isClickable());
			$this->assertTrue($selector->query('xpath:.//span[@class="selected" and text()="Filter results"]')->exists());

			$headers = ($edit)
					? ['', 'Parent services', 'Name', 'Status', 'Root cause', 'Created at', 'Tags', '']
					: ['Parent services', 'Name', 'Status', 'Root cause', 'Created at', 'Tags'];
			$this->assertSame($headers, $table->getHeadersText());
		}

		// Reset filter not to impact the results of next tests.
		$filter_form->query('button:Reset')->one()->click();

		// Check breadcrumbs and "Parent services" headers disappeared.
		if (CTestArrayHelper::get($data, 'check_breadcrumbs')) {
			$this->assertFalse($selector->query('link:All services')->exists());
			$this->assertFalse($selector->query('xpath:.//span[@class="selected" and text()="Filter results"]')->exists());
			$table->invalidate();

			$headers = ($edit)
					? ['', 'Name', 'Status', 'Root cause', 'Created at', 'Tags', '']
					: ['Name', 'Status', 'Root cause', 'Created at', 'Tags'];
			$this->assertSame($headers, $table->getHeadersText());
		}
	}

	public function testPageServicesServices_ResetButton() {
		$this->page->login()->open('zabbix.php?action=service.list');

		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableColumnData('Name');

		// Filling fields with needed services info.
		$form->fill(['id:filter_name' => 'Parent for 2 levels of child services']);
		$form->submit();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered service matches expected.
		$this->assertTableDataColumn(['Parent for 2 levels of child services 1']);

		// After pressing reset button, check that previous services are displayed again.
		$form->query('button:Reset')->one()->click();

		$reset_count =  $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_count);
		$this->assertTableStats($reset_count);
		$this->assertEquals($start_contents, $this->getTableColumnData('Name'));
	}

	public function testPageServicesServices_AddChild() {
		$parent = 'Service with problem';
		$child_name = 'Added child for Server with problem';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->asTable()->waitUntilVisible()->one();

		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);

		$table->findRow('Name', $parent)->query('xpath:.//button[@title="Add child service"]')->waitUntilClickable()
				->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one()->waitUntilReady();
		$form->fill(['Name' => $child_name]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service created');

		// Check that row count is not changed.
		$this->assertTableStats($before_rows_count);

		// Check DB.
		$childid = CDBHelper::getValue('SELECT serviceid FROM services WHERE name='.zbx_dbstr($child_name));
		$parentid = CDBHelper::getValue('SELECT serviceid FROM services WHERE name='.zbx_dbstr($parent));

		// Check parent-child linking in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services_links WHERE serviceupid='.
				zbx_dbstr($parentid).' AND servicedownid ='.zbx_dbstr($childid))
		);
	}

	public function testPageServicesServices_CancelDeleteFromRow() {
		$this->cancelDelete();
	}

	public function testPageServicesServices_CancelMassDelete() {
		$this->cancelDelete(true);
	}

	/**
	 * Function for checking cancelling of Delete action.
	 *
	 * @param boolean    $mass    true if is mass delete scenario, false otherwise
	 */
	private function cancelDelete($mass = false) {
		$name = 'Service for delete by checkbox';
		$sql = 'SELECT * FROM services ORDER BY serviceid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

		if ($mass) {
			$this->selectTableRows([$name], 'Name');
			$this->query('button:Delete')->one()->click();
		}
		else {
			$table->findRow('Name', $name)->query("xpath:.//button[".CXPathHelper::fromClass('js-delete-service')."]")
					->one()->waitUntilClickable()->click();
		}

		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		// Check service not disappeared from frontend.
			$this->assertTrue($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($name)."]")->exists()
		);

		// Check database.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	public function testPageServicesServices_SimpleServiceDeleteFromRow() {
		$name = 'Service for delete';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);

		// Delete service pressing cross button.
		$table->findRow('Name', $name)->query("xpath:.//button[".CXPathHelper::fromClass('js-delete-service')."]")
				->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service deleted');

		// Check service disappeared from frontend.
		$this->assertTableStats($before_rows_count-1);
		$this->assertFalse($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($name)."]")->exists());

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($name)));
	}

	public function testPageServicesServices_DeleteChildFromRow() {
		$parent = 'Parent for child deletion from row';
		$name = 'Child 1';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

		// Open parent service info.
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Delete child service pressing cross button.
		$table->invalidate();
		$table->findRow('Name', $name)->query("xpath:.//button[".CXPathHelper::fromClass('js-delete-service')."]")
				->one()->waitUntilClickable()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service deleted');

		// Check service disappeared from frontend.
		$this->assertTableData();
		$this->assertFalse($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($name)."]")->exists());

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($name)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($parent)));

	}

	public function testPageServicesServices_DeleteParentFromRow() {
		$name = 'Parent for deletion from row';
		$child = 'Child 2';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);

		// Check that parent link exists.
		$this->assertTrue($table->query('link', $name)->exists());

		// Check that child service is not present in global service table.
		$this->assertFalse($table->query("xpath://td/a[text()=".CXPathHelper::escapeQuotes($child)."]")->exists());

		// Delete parent service.
		$table->findRow('Name', $name, true)->query("xpath:.//button[".CXPathHelper::fromClass('js-delete-service')."]")
				->one()->waitUntilClickable()->click();

		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Service deleted');

		// Rows count remains unchanged because child takes parent's place.
		$this->assertTableStats($before_rows_count);

		// Parent disappeared from table.
		$this->assertFalse($table->query('link', $name)->exists());

		// Child now presents in table.
		$this->assertTrue($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($child)."]")->exists());

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($name)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name='.zbx_dbstr($child)));
	}

	/**
	 * @depends testPageServicesServices_SimpleServiceDeleteFromRow
	 */
	public function testPageServicesServices_SimpleServicesMassDelete() {
		$names = [
			'Service for delete by checkbox',
			'Service for delete 2'
		];

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);

		$this->selectTableRows($names);
		$this->assertEquals(count($names).' selected',
				$this->query('id:selected_count')->waitUntilVisible()->one()->getText()
		);

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Services deleted');

		$this->assertTableStats($before_rows_count - count($names));

		// Services disappeared from frontend.
		foreach ($names as $name) {
			$this->assertFalse($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($name)."]")->exists());
		}

		// Check database.
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name LIKE '.zbx_dbstr('%delete%')));
	}

	public function testPageServicesServices_ChildrenMassDelete() {
		$parent = 'Clone parent';
		$names = [
			'Clone child 1',
			'Clone child 2'
		];
		$remained = 'Clone child 3';

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

		// Open parent service info.
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$children_count = $table->getRows()->count();

		$this->selectTableRows($names, 'Name');
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Services deleted');

		// Services disappeared from frontend.
		$this->assertEquals($children_count - count($names), $table->getRows()->count());
		foreach ($names as $name) {
			$this->assertFalse($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($name)."]")->exists());
		}

		// Last child is not deleted.
		$this->assertTrue($table->query("xpath:.//td/a[text()=".CXPathHelper::escapeQuotes($remained)."]")->exists());

		// Check database.
		foreach ($names as $name) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name = '.zbx_dbstr($name)));
		}

		//  Last child remained in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM services WHERE name = '.zbx_dbstr($remained)));
	}

	/**
	 * Test for checking services ordering.
	 */
	public function testPageServicesServices_TestOrder() {
		$parent = 'Test order';
		$children = ['1' => 2, '2' => 3, '3' => 1];

		$this->page->login()->open('zabbix.php?action=service.list.edit');
		$table = $this->query('class:list-table')->waitUntilVisible()->asTable()->one();
		$table->findRow('Name', $parent, true)->query('link', $parent)->waitUntilClickable()->one()->click();
		$this->assertTableDataColumn(['1', '2', '3']);

		foreach ($children as $child => $order) {
			$table->findRow('Name', $child)->query('xpath:.//button[@title="Edit"]')->waitUntilClickable()->one()->click();
			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
			$form->fill(['Sort order (0->999)' => $order]);
			$form->submit();
			$table->waitUntilReloaded();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Service updated');
			CMessageElement::find()->one()->close();
		}

		$this->assertTableDataColumn(['3', '1', '2']);
	}

	public static function getRootCauseData() {
		return [
			// All children have problems without any advanced configuration on parent or on child services.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Most critical if all children have problems'
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average, 1_trigger_Warning'
				]
			],
			// Most critical of child services without any advanced configuration on parent or on child services.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Most critical of child services'
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average, 1_trigger_Warning'
				]
			],
			// Set status to OK any advanced configuration on parent or on child services.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK'
					]
				]
			],
			// Warning - If at least 1 child service has High status or above.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If at least N child services have Status status or above',
								'name:limit_value' => 1,
								'Status' => 'High'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High'
				]
			],
			// Warning - If at least 2 child service has High status or above.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If at least N child services have Status status or above',
								'name:limit_value' => 2,
								'Status' => 'High'
							]
						]
					]
				]
			],
			// Warning - If at least 50% of child services have Average status or above.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If at least N% of child services have Status status or above',
								'name:limit_value' => 50,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average'
				]
			],
			// Warning - If at least 90% of child services have Average status or above.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If at least N% of child services have Status status or above',
								'name:limit_value' => 90,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Warning - If less than 3 child services have Average status or below.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If less than N child services have Status status or below',
								'name:limit_value' => 3,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High'
				]
			],
			// Warning - If less than 2 child services have Average status or below.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If less than N child services have Status status or below',
								'name:limit_value' => 2,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Warning - If less than 35% of child services have Warning status or below.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If less than N% of child services have Status status or below',
								'name:limit_value' => 35,
								'Status' => 'Warning'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average'
				]
			],
			// Warning - If less than 30% of child services have Warning status or below.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If less than N% of child services have Status status or below',
								'name:limit_value' => 30,
								'Status' => 'Warning'
							]
						]
					]
				]
			],
			// Warning - If weight of child services with Average status or above is at least 30.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or above is at least W',
								'name:limit_value' => 20,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average'
				]
			],
			// Warning - If weight of child services with Average status or above is at least 25.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or above is at least W',
								'name:limit_value' => 25,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Warning - If weight of child services with Average status or above is at least 66%.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or above is at least N%',
								'name:limit_value' => 66,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average'
				]
			],
			// Warning - If weight of child services with Average status or above is at least 67%.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or above is at least N%',
								'name:limit_value' => 67,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Warning - If weight of child services with Average status or below is less than 21.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or below is less than W',
								'name:limit_value' => 21,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High'
				]
			],
			// Warning - If weight of child services with Average status or below is less than 20.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or below is less than W',
								'name:limit_value' => 20,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Warning - If weight of child services with Average status or below is less than 67%.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or below is less than N%',
								'name:limit_value' => 67,
								'Status' => 'Average'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High'
				]
			],
			// Warning - If weight of child services with Average status or below is less than 66%.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or below is less than N%',
								'name:limit_value' => 66,
								'Status' => 'Average'
							]
						]
					]
				]
			],
			// Multiple additional rules.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Information',
								'Condition' => 'If weight of child services with Status status or below is less than N%',
								'name:limit_value' => 67,
								'Status' => 'Average'
							],
							[
								'Set status to' => 'Warning',
								'Condition' => 'If weight of child services with Status status or above is at least N%',
								'name:limit_value' => 66,
								'Status' => 'Average'
							],
							[
								'Set status to' => 'High',
								'Condition' => 'If less than N% of child services have Status status or below',
								'name:limit_value' => 30,
								'Status' => 'Warning'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average'
				]
			],
			// Child status propagation rule - Increase by.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Average',
								'Condition' => 'If at least N child services have Status status or above',
								'name:limit_value' => 2,
								'Status' => 'High'
							]
						]
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD1,
							'fields' => [
								'Status propagation rule' => 'Increase by',
								'id:propagation_value_number' => 3
							]
						]
					],
					'child_rootcause' => [
						'1' => '1_trigger_Warning',
						'2' => '1_trigger_Average',
						'3' => '1_trigger_High'
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Warning'
				]
			],
			// Child status propagation rule - Decrease by.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Average',
								'Condition' => 'If at least N child services have Status status or above',
								'name:limit_value' => 2,
								'Status' => 'Average'
							]
						]
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD1,
							'fields' => [
								'Status propagation rule' => 'As is'
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD3,
							'fields' => [
								'Status propagation rule' => 'Decrease by',
								'id:propagation_value_number' => 2
							]
						]
					]
				]
			],
			// Child status propagation rule - Ignore this service.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'Average',
								'Condition' => 'If less than N% of child services have Status status or below',
								'name:limit_value' => 51,
								'Status' => 'Average'
							]
						]
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD3,
							'fields' => [
								'Status propagation rule' => 'As is'
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD2,
							'fields' => [
								'Status propagation rule' => 'Ignore this service'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High'
				]
			],
			// Child status propagation rule - Fixed status.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Set status to OK',
						'Additional rules' => [
							[
								'Set status to' => 'High',
								'Condition' => 'If weight of child services with Status status or above is at least W',
								'name:limit_value' => 20,
								'Status' => 'High'
							]
						]
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD2,
							'fields' => [
								'Status propagation rule' => 'As is'
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD1,
							'fields' => [
								'Status propagation rule' => 'Fixed status',
								'id:propagation_value_status' => 'Disaster'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Warning'
				]
			],
			// All three child services use status propagation modifications. Should not be possible to decrease to OK.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Most critical of child services'
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD1,
							'fields' => [
								'Status propagation rule' => 'Decrease by',
								'id:propagation_value_number' => 5
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD2,
							'fields' => [
								'Status propagation rule' => 'Fixed status',
								'id:propagation_value_status' => 'OK'
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD3,
							'fields' => [
								'Status propagation rule' => 'Ignore this service'
							]
						]
					],
					'parent_rootcause' => '1_trigger_Warning'
				]
			],
			// All three child services use status propagation modifications. Increase all to High and above.
			[
				[
					'parent' => [
						'Status calculation rule' => 'Most critical if all children have problems',
						'Additional rules' => [
							[
								'Set status to' => 'High',
								'Condition' => 'If weight of child services with Status status or below is less than W',
								'name:limit_value' => 1,
								'Status' => 'Average'
							]
						]
					],
					'child' => [
						[
							'name' => self::ROOTCAUSE_CHILD1,
							'fields' => [
								'Status propagation rule' => 'Increase by',
								'id:propagation_value_number' => 5
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD2,
							'fields' => [
								'Status propagation rule' => 'Fixed status',
								'id:propagation_value_status' => 'Disaster'
							]
						],
						[
							'name' => self::ROOTCAUSE_CHILD3,
							'fields' => [
								'Status propagation rule' => 'As is'
							]
						]
					],
					'parent_rootcause' => '1_trigger_High, 1_trigger_Average, 1_trigger_Warning'
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareServiceProblemsData
	 *
	 * @dataProvider getRootCauseData
	 */
	public function testPageServicesServices_RootCause($data) {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$table = $this->query(self::TABLE_SELECTOR)->asTable()->one();
		$row = $table->findRow('Name', self::ROOTCAUSE_PARENT, true);
		$row->query('xpath:.//button[@title="Edit"]')->waitUntilClickable()->one()->click();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:service-form')->asForm()->one();
		$form->getField('Status calculation rule')->fill($data['parent']['Status calculation rule']);

		if (array_key_exists('Additional rules', $data['parent'])) {
			$form->fill(['Advanced configuration' => true]);

			// Remove the additional rules from previous test cases.
			$form->getFieldContainer('Additional rules')->query('button:Remove')->all(false)->click();

			// Fill in configuration of each Additional rule separately.
			foreach ($data['parent']['Additional rules'] as $rule_fields) {
				$form->getFieldContainer('Additional rules')->query('button:Add')->waitUntilClickable()->one()->click();
				$rules_form = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();
				$rules_form->fill($rule_fields);
				$rules_form->submit();
				$rules_form->waitUntilNotVisible();
			}
		}

		$form->submit();
		$table->waitUntilReloaded();

		// Fill in the Advanced configuration fields for child services, that impact the root cause on the parent service.
		if (array_key_exists('child', $data)) {
			$this->query('link', self::ROOTCAUSE_PARENT)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();

			$child_table = $this->query(self::TABLE_SELECTOR)->asTable()->one();

			// Check the root causes for the child services if corresponding data exists in the data provider.
			if (array_key_exists('child_rootcause', $data)) {
				foreach ($data['child_rootcause'] as $service_name => $child_rootcause) {
					$this->assertEquals($data['child_rootcause'][$service_name], $child_table->findRow('Name', $service_name)
							->getColumn('Root cause')->getText()
					);
				}
			}

			// Fill in configuration of each child service separately.
			foreach ($data['child'] as $child_service) {
				$child_table->findRow('Name', $child_service['name'])->query('xpath:.//button[@title="Edit"]')->one()->click();

				COverlayDialogElement::find()->one()->waitUntilReady();
				$form = $this->query('id:service-form')->asForm()->one();
				$form->fill(['Advanced configuration' => true]);

				$form->fill($child_service['fields']);
				$form->submit();

				$child_table->waitUntilReloaded();
			}

			// Exit to the default services view, where parent root cause can be seen.
			$this->query('link:All services')->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
		}

		// Check root cause of the parent service.
		$row->invalidate();
		$this->assertEquals(CTestArrayHelper::get($data, 'parent_rootcause', ''), $row->getColumn('Root cause')->getText());
	}
}
