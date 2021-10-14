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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/traits/FilterTrait.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testPageMonitoringServices extends CWebTest {

	use FilterTrait;
	use TableTrait;

	const EDIT = true;
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
			],
			[
				'name' => 'Server 6 for delete by checkbox',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 6,
			],
			[
				'name' => 'Server 7 for delete by action button',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 7,
			],
			[
				'name' => 'Server 8 parent with child for delete child',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 8,
			],
			[
				'name' => 'Server 9 parent with child for delete parent',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 9,
			],
			[
				'name' => 'Server 10 child for Server 8',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 10,
			],
			[
				'name' => 'Server 11 child for Server 9',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 12,
			],
			[
				'name' => 'Server for mass delete 1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 13,
			],
			[
				'name' => 'Server for mass delete 2',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 14,
			],
			[
				'name' => 'Server for mass delete 3',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 15,
			],
			[
				'name' => 'Server for mass update 1',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 16,
			],
			[
				'name' => 'Server for mass update 2',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 17,
			],
			[
				'name' => 'Server for mass update 3',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 18,
			],
			[
				'name' => 'Server with problem',
				'algorithm' => 1,
				'showsla' => 1,
				'goodsla' => 99.99,
				'sortorder' => 18,
			],

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

		DBexecute('UPDATE services SET status=5 WHERE name="Server with problem"');
	}

	public function testPageMonitoringServices_LayoutView()
	{
		// Check layout view mode
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		// Labels on columns at services list
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame(['Name', 'Status', 'Root cause', 'SLA', 'Tags'], $table->getHeadersText());

		$expectedViewMode = [
			'Server 3 1' => [
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '',
				'Tags' => 'test: test123'
			],
			'Server 4' => [
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '',
				'Tags' => 'test: test789test123: test456'
			],
			'Server 5' => [
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '99.9900',
				'Tags' => ''
			]
		];

		$data = $table->index('Name');

		foreach ($expectedViewMode as $name => $fields) {
			// Get table row by service name.
			$row = $data[$name];

			// Check the value in table.
			foreach ($fields as $column => $value) {
				$this->assertEquals($value, $row[$column]);
			}
		}

		$this->checkBreadcrumbs();
	}

	public function testPageMonitoringServices_LayoutEdit()
	{
		// Click on Edit mode button
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Labels on columns at services list
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame(['', 'Name', 'Status', 'Root cause', 'SLA', 'Tags', ''], $table->getHeadersText());

		$expectedEditMode = [
			'Server 3 1' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '',
				'Tags' => 'test: test123',
				'' => ''
			],
			'Server 4' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '',
				'Tags' => 'test: test789test123: test456',
				'' => ''
			],
			'Server 5' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '99.9900',
				'Tags' => '',
				'' => ''
			]
		];

		$data = $table->index('Name');


		foreach ($expectedEditMode as $name => $fields) {
			// Get table row by service name.
			$row = $data[$name];

			// Check the value in table.
			foreach ($fields as $column => $value) {
				$this->assertEquals($value, $row[$column]);
			}
		}
	}

	public static function getFilterData() {
		return [
			// "And/Or" and "Or" checks.
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Contains',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Contains',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Exists',
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Equals',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not exist',
						],
					],
					'result' => [
						'Name' => 'Server 1',
						'Name' => 'Server 2 1',
						'Name' => 'Server 6 for delete by checkbox',
						'Name' => 'Server 7 for delete by action button',
						'Name' => 'Server 8 parent with child for delete child 1',
						'Name' => 'Server 9 parent with child for delete parent 1',
						'Name' => 'Server 10 child for Server 8',
						'Name' => 'Server 11 child for Server 9',
						'Name' => 'Server for mass delete 1',
						'Name' => 'Server for mass delete 2',
						'Name' => 'Server for mass delete 3',
						'Name' => 'Server for mass update 1',
						'Name' => 'Server for mass update 2',
						'Name' => 'Server for mass update 3',
						'Name' => 'Server with problem'
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not equal',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 4',
						'Name' => 'Server 5',
					]
				]
			],
			[
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Contains',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Contains',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Exists',
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Equals',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				],
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not exist',
						],
					],
					'result' => [
						'Name' => 'Server 4',
						'Name' => 'Server 5',
					]
				]
			],
			[
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not equal',
							'value' => 'test123'
						],
					],
					'result' => [
						'Name' => 'Server 4',
						'Name' => 'Server 5',
					]
				]
			],
			[
				[
					'children' => [
						'id:filter_without_children'
					],
					'result' => [
						'Name' => 'Server 1',
						'Name' => 'Server 4',
						'Name' => 'Server 5'
					]
				]
			],
			[
				[
					'problem' => [
						'id:filter_without_problem_tags'
					],
					'result' => [
						'Name' => 'Server 1',
						'Name' => 'Server 2',
						'Name' => 'Server 3',
						'Name' => 'Server 4'
					]
				]
			],
			[
				[
					'status' => [
						'id:filter_status_2'
					],
					'result' => [
						'Name' => 'Server with problem',
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageMonitoringServices_FilterView($data)
	{
		$this->checkFiltering($data);
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageMonitoringServices_FilterEdit($data)
	{
		$this->checkFiltering($data, self::EDIT);
	}

	private function checkFiltering($data, $edit = false)
	{
		if ($edit === false) {
			$this->page->login()->open('zabbix.php?action=service.list');
		}
		else {
			$this->page->login()->open('zabbix.php?action=service.list.edit');
		}

		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		$filter_form = $this->query('name:zbx_filter')->asFluidForm()->one();
		if ($edit === false) {
			// Check filter fields.
			$this->assertEquals(['Name', 'Status', 'Tags'], $filter_form->getLabels()->asText());
		}
		else {
			$this->assertEquals(['Name', 'Status', 'Only services without children', 'Only services without problem tags', 'Tags'], $filter_form->getLabels()->asText());

			$filter_form->query('id:filter_without_children')->asCheckbox()->one()->isChecked(false);
			$filter_form->query('id:filter_without_problem_tags')->asCheckbox()->one()->isChecked(false);
		}

		foreach (['Any', 'OK', 'Problem'] as $show_tag) {
			$this->assertTrue($filter_form->query('xpath://ul[@id="filter_status"]/li/label[text()="'.$show_tag.'"]')->exists());
		};

		foreach (['And/Or', 'Or'] as $show_tag) {
			$this->assertTrue($filter_form->query('xpath://ul[@id="filter_evaltype"]/li/label[text()="'.$show_tag.'"]')->exists());
		};

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($filter_form->query('button', $button)->exists());
		}

		if (array_key_exists('evaluation_type', $data)) {
			$form->fill(['id:filter_evaltype' => $data['evaluation_type']]);
			$this->setTags($data['tags']);
			$form->submit();
			$this->page->waitUntilReady();

			// Check filtered result.
			$filtering = $this->getTableResult('Name');
			$filtering = array_values($filtering);
			$this->assertTableDataColumn($filtering, 'Name');

			// Reset filter due to not influence further tests.
			$form->query('button:Reset')->one()->click();
		}

		// Filter by name
		$form->fill(['id:filter_name' => 'Server 3']);
		$form->submit();
		$this->assertEquals(['Server 3 1'], $this->getTableData());

		// Reset filter due to not influence further tests.
		$form->query('button:Reset')->one()->click();

		if (array_key_exists('status', $data)) {
			$form->fill(['id:filter_status' => 'Problem']);
			$form->submit();

			$filtering = $this->getTableResult('Name');
			$filtering = array_values($filtering);
			$this->assertTableDataColumn($filtering, 'Name');
		}

		if($edit) {

			if (array_key_exists('children', $data)) {
				$this->query($data['children'])->asCheckbox()->one()->check();
			}

			if (array_key_exists('problem', $data)) {
				$this->query($data['problem'])->asCheckbox()->one()->check();
			}

			$form->submit();
			$this->page->waitUntilReady();

			// Check filtered result.

			if (array_key_exists('children', $data)) {
				$filtering = $this->getTableResult('Name');
				$filtering = array_values($filtering);
				$this->assertTableDataColumn($filtering, 'Name');
			}

			if (array_key_exists('problem', $data)) {
				$filtering = $this->getTableResult('Name');
				$filtering = array_values($filtering);
				$this->assertTableDataColumn($filtering, 'Name');
			}


			// Reset filter due to not influence further tests.
			$form->query('button:Reset')->one()->click();
		}

	}

	public function testPageMonitoringServices_ResetButton() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$table = $this->query($this->selector)->asTable()->one();
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableData();

		// Filling fields with needed services info.
		$form->fill(['id:filter_name' => 'Server 3']);
		$form->submit();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered service matches expected.
		$this->assertEquals(['Server 3 1'], $this->getTableData());

		// After pressing reset button, check that previous services are displayed again.
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($start_rows_count, $table->getRows()->count());
		$this->assertTableStats($table->getRows()->count());
		$this->assertEquals($start_contents, $this->getTableData());
	}

	public function testPageMonitoringServices_MassUpdate()
	{
		$this->page->login()->open('zabbix.php?action=service.list.edit');

		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		$form->fill(['id:filter_name' => 'Server for mass update']);
		$form->submit();

		$this->page->waitUntilReady();

		$this->selectTableRows([
			'Server for mass update 1',
			'Server for mass update 2',
			'Server for mass update 3'], 'Name'
		);

		$this->query('button:Mass update')->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		$form = $dialog->asFluidForm();
		$form->getLabel('Tags')->click();

		$this->query('id:tags-table')->asMultifieldTable()->one()->fill([
			'action' => USER_ACTION_UPDATE,
			'index' => 0,
			'tag' => 'added_tag_1',
			'value' => 'added_tag_1']);

		$dialog->query('button:Update')->one()->waitUntilClickable()->click();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Services updated');

		$expectedData = [
			'Server for mass update 1' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '99.9900',
				'Tags' => 'added_tag_1: added_tag_1',
				'' => ''
			],
			'Server for mass update 2' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '99.9900',
				'Tags' => 'added_tag_1: added_tag_1',
				'' => ''
			],
			'Server for mass update 3' => [
				'' => '',
				'Status' => 'OK',
				'Root cause' => '',
				'SLA' => '99.9900',
				'Tags' => 'added_tag_1: added_tag_1',
				'' => ''
			]
		];

		$table = $this->query('class:list-table')->asTable()->one();
		$data = $table->index('Name');

		foreach ($expectedData as $name => $fields) {
			// Get table row by service name.
			$row = $data[$name];

			// Check the value in table.
			foreach ($fields as $column => $value) {
				$this->assertEquals($value, $row[$column]);
			}
		}
	}

	public function testPageMonitoringServices_DeleteByCheckbox()
	{
		$byCheckbox = 'Server 6 for delete by checkbox';

		// Single service delete by checkbox
		$this->deleteService($byCheckbox, true, false, true);
	}

	public function testPageMonitoringServices_DeleteByActionButton()
	{
		$byActionButton = 'Server 7 for delete by action button';

		// Single service delete by action button
		$this->deleteService($byActionButton, true, false, false);
	}

	public function testPageMonitoringServices_DeleteChild()
	{
		$child = 'Server 8 parent with child for delete child';

		// Delete child service from page
		$this->deleteService($child, false, true, false);
	}

	public function testPageMonitoringServices_DeleteParent()
	{
		$parent = 'Server 9 parent with child for delete parent';

		// Delete parent service from page
		$this->deleteService($parent, true, true, false);
	}

	public function testPageMonitoringServices_MassDelete()
	{
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


	private function checkBreadcrumbs() {
		// Check by switching childs and parents
		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->query('xpath://tbody/tr/td/a[text()="Server 3"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$this->assertEquals('Server 3', $this->query('xpath://ul[@class="breadcrumbs"]/li[2]')->one()->getText());

		$table = $this->query('class:list-table')->asTable()->one();
		$table->getRow(0)->query('xpath://tbody/tr/td/a[text()="Server 2"]')->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();

		$this->assertEquals('Server 2', $this->query('xpath://ul[@class="breadcrumbs"]/li[3]')->one()->getText());

		// Return to services list
		$this->query('xpath://ul[@class="breadcrumbs"]/li[1]')->one()->click();

		// Check by filtered data
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:filter_evaltype' => 'And/Or']);
		$this->setTags([['name' => 'test', 'operator' => 'Exists']]);
		$form->submit();
		$this->page->waitUntilReady();

		$this->assertEquals('Filter results', $this->query('xpath://ul[@class="breadcrumbs"]/li[2]')->one()->getText());
	}

	/**
	 * Return table data by Name
	 *
	 * @return array
	 */
	private function getTableData() {
		$result = [];

		foreach ($this->query($this->selector)->asTable()->one()->getRows() as $row) {
			$result[] = $row->getColumn('Name')->getText();
		}

		return $result;
	}

	private function deleteService($serviceName, $parent = true, $child = false, $checkbox = true)
	{
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
			if ($child === true) {
				$this->assertTableStats($before_rows_count);
			}
			else {
				$this->assertTableStats($before_rows_count-1);
			}

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
