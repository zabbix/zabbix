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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../traits/FilterTrait.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testPageMonitoringServices extends CWebTest {

	use FilterTrait;
	use TableTrait;

	const EDIT = true;
	const LAYOUT_PARENT = 'Server 3';
	const LAYOUT_CHILD = 'Server 2';
	const LAYOUT_CHILD2 = 'Server 1';
	const BREADCRUMB_SELECTOR = 'xpath://ul[@class="breadcrumbs"]';

	private $selector = 'xpath://form[@name="service_list"]/table[@class="list-table"]';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function prepareServicesData() {
		CDataHelper::call('service.create', [
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
						'tag' => 'test',
						'value' => 'test456'
					],
					[
						'tag' => 'problem',
						'value' => 'true'
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
				],
				'tags' => [
					[
						'tag' => 'problem',
						'value' => 'false'
					],
					[
						'tag' => 'test',
						'value' => 'test789'
					]
				]
			],
			[
				'name' => 'Server 6 for delete by checkbox',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 6
			],
			[
				'name' => 'Server 7 for delete by action button',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 7
			],
			[
				'name' => 'Server 8 parent with child for delete child',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 8
			],
			[
				'name' => 'Server 9 parent with child for delete parent',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 9
			],
			[
				'name' => 'Server 10 child for Server 8',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 10
			],
			[
				'name' => 'Server 11 child for Server 9',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 12
			],
			[
				'name' => 'Server for mass delete 1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 13
			],
			[
				'name' => 'Server for mass delete 2',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 14,
				'problem_tags' => [
					[
						'tag' => 'tag1',
						'operator' => 0,
						'value' => 'value1'
					]
				]
			],
			[
				'name' => 'Server for mass delete 3',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 15,
				'problem_tags' => [
					[
						'tag' => 'tag2',
						'operator' => 0,
						'value' => 'value2'
					]
				]
			],
			[
				'name' => 'Server for mass update 1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 16
			],
			[
				'name' => 'Server for mass update 2',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 17
			],
			[
				'name' => 'Server for mass update 3',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 18
			],
			[
				'name' => 'Server with problem',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 18
			]
		]);

		$services = CDataHelper::getIds('name');

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
			],
			[
				'serviceid' => $services['Server 10 child for Server 8'],
				'parents' => [
					[
						'serviceid' => $services['Server 8 parent with child for delete child']
					]
				]
			],
			[
				'serviceid' => $services['Server 11 child for Server 9'],
				'parents' => [
					[
						'serviceid' => $services['Server 9 parent with child for delete parent']
					]
				]
			]
		]);

		DBexecute('UPDATE services SET status = 5 WHERE name = "Server with problem"');
	}

	public function testPageMonitoringServices_LayoutView() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		// Labels on columns at services list.
		$table = $this->query('id:service-list')->asTable()->one();
		$this->assertSame(['Name', 'Status', 'Root cause', 'SLA', 'Tags'], $table->getHeadersText());

		// Check that row buttons are not present in the table.
		$this->assertFalse($table->query('id:all_services')->exists());
		$this->assertFalse($table->query('xpath://button[@title="Add child service"]')->exists());
		$this->assertFalse($table->query('xpath://button[@title="Edit"]')->exists());
		$this->assertFalse($table->query('xpath://button[@title="Delete"]')->exists());

		// Check that action buttons are not present below the table.
		$this->assertFalse($this->query('button:Mass update')->exists());
		$this->assertFalse($this->query('button:Delete')->exists());

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

	public function testPageMonitoringServices_LayoutEdit() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->query('id:list_mode')->asSegmentedRadio()->one()->waitUntilClickable()->select('Edit');
		$this->page->waitUntilReady();

		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		$this->assertTrue($this->query('button:Create service')->one()->isVisible());

		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame(['', 'Name', 'Status', 'Root cause', 'SLA', 'Tags', ''], $table->getHeadersText());

		$this->checkServiceButtons($table->getRow(rand(0,13)));

		// Check that action buttons are not present below the table.
		$this->assertFalse($this->query('button:Mass update')->one()->isEnabled());
		$this->assertFalse($this->query('button:Delete')->one()->isEnabled());

		$this->checkParentChildLayout($table, self::LAYOUT_PARENT, self::LAYOUT_CHILD, self::EDIT);
		$this->checkServiceButtons($table->findRow('Name', self::LAYOUT_CHILD2, true));

		// Check there is no Kiosk mode button.
		$this->assertFalse($this->query('xpath://button[@title="Kiosk mode"]')->exists());
	}

	private function checkParentChildLayout($table, $parent, $child) {
		$this->checkSeviceInfoLayout($table, $parent);
		$this->assertTableDataColumn([$child.' 1'], 'Name');
		$this->checkSeviceInfoLayout($table, $child);
		$this->assertTableDataColumn([self::LAYOUT_CHILD2], 'Name');

		foreach (['All services', $parent, $child] as $breadcrumb) {
			$this->assertTrue($this->query(self::BREADCRUMB_SELECTOR)->one()->query('link', $breadcrumb)
					->one()->isClickable()
			);
		}
		$this->assertTrue($this->query(self::BREADCRUMB_SELECTOR)->one()
				->query("xpath://span[@class='selected']//a[text()=".zbx_dbstr($child)."]")->one()->isValid()
		);
	}

	private function checkSeviceInfoLayout($table, $service, $edit = false) {
		$table->findRow('Name', $service, true)->query('link', $service)->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->assertTrue($this->query('xpath://li[@role="tab"]//a[text()="Info"]')->one()->isClickable());

		$info_card = $this->query('id:tab_info')->waitUntilReady()->one();
		foreach ([$service, 'Parent services', 'Status', 'SLA', 'Tags'] as $text) {
			$this->assertTrue($info_card->query("xpath://div[@class='service-info-grid']//div[text()=".zbx_dbstr($text)."]")
					->one()->isVisible()
			);
		}

		if ($edit) {
			$this->assertTrue($info_card->query('xpath://button['.CXPathHelper::fromClass('btn-edit').']')->one()
					->isClickable()
			);
		}

		$table->invalidate();
		$this->assertTableStats(1);
	}

	private function checkBreadcrumbs($breadcrumbs) {
		foreach ($breadcrumbs as $breadcrumb) {
			$this->assertTrue($this->query(self::BREADCRUMB_SELECTOR)->one()->query('link', $breadcrumb)
					->one()->isClickable()
			);
		}
	}

	private function checkServiceButtons($row) {
		$this->assertTrue($row->query('xpath://button[@title="Add child service"]')->one()->isClickable());
		$this->assertTrue($row->query('xpath://button[@title="Edit"]')->one()->isClickable());
		$this->assertTrue($row->query('xpath://button[@title="Delete"]')->one()->isClickable());
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
						'Server 4'
					]
				]
			],
			// Tags source: Problem.
			[
				[
					'Tags' => [
						'Source' => 'Problem',
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
						'Server 5'
					]
				]
			],
			// Tags source: Problem and Evaluation: Or.
			[
				[
					'Tags' => [
						'Source' => 'Problem',
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
						'Server 5'
					]
				]
			],
			// Only services without children checkbox.
			[
				[
					'filter' => [
						'Name' => 'delete',
						'Only services without children' => true,
					],
					'result' => [
						'Server 6 for delete by checkbox',
						'Server 7 for delete by action button',
						'Server for mass delete 1',
						'Server for mass delete 2',
						'Server for mass delete 3'
					]
				]
			],
			// Only services without problem tags checkbox.
			[
				[
					'filter' => [
						'Name' => 'Server for mass delete',
						'Only services without problem tags' => true,
					],
					'result' => [
						'Server for mass delete 1'
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
						'Name' => 'Server for mass delete',
					],
					'result' => [
						'Server for mass delete 1',
						'Server for mass delete 2',
						'Server for mass delete 3'
					],
					'check_breadcrumbs' => true
				]
			],
			// Name and Status: Any.
			[
				[
					'filter' => [
						'Name' => 'with',
						'Status' => 'Any'
					],
					'result' => [
						'Server 8 parent with child for delete child 1',
						'Server 9 parent with child for delete parent 1',
						'Server with problem'
					]
				]
			],
			// Name and Status: OK.
			[
				[
					'filter' => [
						'Name' => 'with',
						'Status' => 'OK'
					],
					'result' => [
						'Server 8 parent with child for delete child 1',
						'Server 9 parent with child for delete parent 1'
					]
				]
			],
			// Name and Status: Problem fields.
			[
				[
					'filter' => [
						'Name' => 'with',
						'Status' => 'Problem'
					],
					'result' => [
						'Server with problem'
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
						'Server 4'
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
						'Server 3 1',
						'Server 4',
						'Server 5'
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
						'Name' => 'Server 5'
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
						'Server 3 1'
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
						'Server 3 1',
						'Server 4'
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
						'Server 5'
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
	public function testPageMonitoringServices_FilterView($data) {
		$this->checkFiltering($data);
	}

	/**
	 * @dataProvider getFilterEditData
	 * @dataProvider getFilterCommonData
	 */
	public function testPageMonitoringServices_FilterEdit($data) {
		$this->checkFiltering($data, self::EDIT);
	}

	private function checkFiltering($data, $edit = false){
		$this->page->login()->open(($edit === false) ? 'zabbix.php?action=service.list' :
				'zabbix.php?action=service.list.edit'
		);
		$filter_form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Reset filter in case if some filtering remained before ongoing test case.
		$filter_form->query('button:Reset')->one()->click();

		// Fill filter form with data.
		$filter_form->fill(CTestArrayHelper::get($data, 'filter'));

		// If data contains Tags fill them separataly, because tags form is more complicated.
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

		// Check breadcrumbs.
		$selector = $this->query(self::BREADCRUMB_SELECTOR);
		if (CTestArrayHelper::get($data, 'check_breadcrumbs')) {
			$this->assertTrue($selector->one()->query('link:All services')>one()->isClickable());
			$this->assertTrue($selector->query('xpath://span[@class="selected" and text()="Filter results"]')->exists());
		}

		// Reset filter due to not interfere next tests.
		$filter_form->query('button:Reset')->one()->click();

		// Check breadcrumbs disappeared.
		if (CTestArrayHelper::get($data, 'check_breadcrumbs')) {
			$this->assertFalse($selector->query('link:All services')->exists());
			$this->assertFalse($selector->query('xpath://span[@class="selected" and text()="Filter results"]')->exists());
		}
	}

	public function testPageMonitoringServices_ResetButton() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$table = $this->query($this->selector)->asTable()->one();
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = array_values($this->getTableResult('Name'));
		// Filling fields with needed services info.
		$form->fill(['id:filter_name' => 'Server 3']);
		$form->submit();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered service matches expected.
		$this->assertEquals(['Server 3 1'], array_values($this->getTableResult('Name')));

		// After pressing reset button, check that previous services are displayed again.
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($start_rows_count, $table->getRows()->count());
		$this->assertTableStats($table->getRows()->count());
		$this->assertEquals($start_contents, array_values($this->getTableResult('Name')));
	}

	public function testPageMonitoringServices_MassUpdate() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$filter_form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		$filter_form->fill(['id:filter_name' => 'Server for mass update']);
		$filter_form->submit();

		$this->page->waitUntilReady();

		$this->selectTableRows([
			'Server for mass update 1',
			'Server for mass update 2',
			'Server for mass update 3'], 'Name'
		);

		$this->query('button:Mass update')->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$filter_form = $dialog->asForm();
		$filter_form->getLabel('Tags')->click();

		$this->query('id:tags-table')->asMultifieldTable()->one()->fill([
			'action' => USER_ACTION_UPDATE,
			'index' => 0,
			'tag' => 'added_tag_1',
			'value' => 'added_tag_1']);

		$filter_form->submit();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Services updated');

		$this->assertTableDataColumn(array_values($this->getTableResult('Name')), 'Name');
	}

	public function testPageMonitoringServices_DeleteByActionButton() {
		$byActionButton = 'Server 7 for delete by action button';

		// Single service delete by action button
		$this->deleteService($byActionButton, true, false, false);
	}

	public function testPageMonitoringServices_DeleteChild() {
		$child = 'Server 8 parent with child for delete child';

		// Delete child service from page
		$this->deleteService($child, false, true, false);
	}

	public function testPageMonitoringServices_DeleteParent() {
		$parent = 'Server 9 parent with child for delete parent';

		// Delete parent service from page
		$this->deleteService($parent, true, true, false);
	}

	public function testPageMonitoringServices_MassDelete() {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		$form->fill(['id:filter_name' => 'Server for mass delete']);
		$form->submit();

		$this->page->waitUntilReady();

		$this->selectTableRows([
			'Server for mass delete 1',
			'Server for mass delete 2',
			'Server for mass delete 3'], 'Name'
		);

		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();

		$this->assertMessage(TEST_GOOD, 'Services deleted');

		// Check database
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name LIKE '.
			CXPathHelper::escapeQuotes('%Server for mass delete%'))
		);
	}

	private function deleteService($serviceName, $parent = true, $child = false, $checkbox = true) {
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$table = $this->query('class:list-table')->asTable()->one();

		$before_rows_count = $table->getRows()->count();
		$this->assertTableStats($before_rows_count);

		if ($parent === true) {
			if ($checkbox) {
				$table->findRow('Name', $serviceName, true)->select();
				$this->query('button:Delete')->one()->click();
			}
			else {
				$table->findRow('Name', $serviceName, true)->query('xpath:.//button[contains(@class, "btn-remove")]')
						->one()->waitUntilClickable()->click();
			}

			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Service deleted');

			$count = ($child === true) ? $before_rows_count : $before_rows_count-1;
			$this->assertTableStats($count);

			// Check database.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.CXPathHelper::escapeQuotes($serviceName)));
		}

		if ($child === true && $parent === false) {
			$table->findRow('Name', $serviceName, true)->query('xpath://tbody/tr/td/a[text()="'.$serviceName.'"]')
					->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			$childs_rows_count = $table->getRows()->count();
			$this->assertTableStats($childs_rows_count);

			$table = $this->query('class:list-table')->asTable()->one();
			$table->findRow('Name', 'Server 10 child for Server 8', true)->query('xpath:.//button[contains(@class, "btn-remove")]')
					->one()->waitUntilClickable()->click();

			$this->page->acceptAlert();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Service deleted');
			$this->assertTableStats($childs_rows_count - 1);

			// Check database.
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM services WHERE name='.CXPathHelper::escapeQuotes('Server 10 child for Server 8')));
		}
	}
}
