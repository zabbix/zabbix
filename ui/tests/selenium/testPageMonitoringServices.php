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

/**
 * @backup services
 *
 * @onBefore prepareServicesData
 */
class testPageMonitoringServices extends CWebTest {

	use FilterTrait;
	use TableTrait;

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
			]
		]);
	}

	public function testPageMonitoringServices_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$this->page->assertTitle('Services');
		$this->page->assertHeader('Services');

		// Check layout view mode
		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check filter fields.
		$filter_form = $this->query('name:zbx_filter')->asFluidForm()->one();
		$this->assertEquals(['Name', 'Status', 'Tags'], $filter_form->getLabels()->asText());

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

		// Check layout Edit mode
		// Click on Edit mode button
		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		// Check that "Create service" button is displayed
		$this->assertTrue($this->query('button', 'Create service')->one()->isVisible());

		// Check filter fields.
		$filter_form = $this->query('name:zbx_filter')->asFluidForm()->one();
		$this->assertEquals(['Name', 'Status', 'Only services without children', 'Only services without problem tags', 'Tags'], $filter_form->getLabels()->asText());

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
	}

	public static function getFilterByTagsData() {
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
				],
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
				],
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Exits',
						],
					],
					'result' => [
						'Name' => 'Server 3 1'
					]
				],
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
				],
				[
					'evaluation_type' => 'And/Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not exit',
						],
					],
					'result' => [
						'Name' => 'Server 4',
						'Name' => 'Server 5',
					]
				],
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
				],
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
				],
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
				],
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
				],
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
				[
					'evaluation_type' => 'Or',
					'tags' => [
						[
							'name' => 'test',
							'operator' => 'Does not exit',
						],
					],
					'result' => [
						'Name' => 'Server 4',
						'Name' => 'Server 5',
					]
				],
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
			]
		];
	}

	/**
	 * Test filtering services by tags.
	 *
	 * @dataProvider getFilterByTagsData
	 */
	public function testPageMonitoringServices_CheckFiltringByTags($data) {
		$this->page->login()->open('zabbix.php?action=service.list');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:filter_evaltype' => $data['evaluation_type']]);
		$this->setTags($data['tags']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check filtered result.
		foreach ($data['result'] as $result) {
			$filtering = $this->getTableResult('Name');
			$this->assertTableDataColumn($filtering, 'Name');
		}

		// Reset filter due to not influence further tests.
		$form->query('button:Reset')->one()->click();
	}

	/**
	 * Test filtering services by checkboxes
	 */
	public function testPageMonitoringServices_CheckFiltringByCheckboxes() {
		$this->page->login()->open('zabbix.php?action=service.list');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();

		$this->query('xpath://label[@for="list_mode_1"]')->waitUntilClickable()->one()->click();

		$this->query('id:filter_without_children')->asCheckbox()->one()->check();
		$form->submit();
		$this->page->waitUntilReady();

		$data = [
			'Name' => 'Server 1',
			'Name' => 'Server 4',
			'Name' => 'Server 5'
		];

		// Check filtered result.
		foreach ($data as $result) {
			$filtering = $this->getTableResult('Name');
			$this->assertTableDataColumn($filtering, 'Name');
		}

		$form->query('button:Reset')->one()->click();

		$this->query('id:filter_without_problem_tags')->asCheckbox()->one()->check();
		$form->submit();
		$this->page->waitUntilReady();

		$data = [
			'Name' => 'Server 1',
			'Name' => 'Server 2',
			'Name' => 'Server 3',
			'Name' => 'Server 4',
		];

		// Check filtered result.
		foreach ($data as $result) {
			$filtering = $this->getTableResult('Name');
			$this->assertTableDataColumn($filtering, 'Name');
		}
	}

	public function testPageMonitoringServices_CheckBreadcrumbs() {
		$this->page->login()->open('zabbix.php?action=service.list');

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
}
