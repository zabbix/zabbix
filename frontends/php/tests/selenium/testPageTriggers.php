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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/traits/FilterTrait.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

class testPageTriggers extends CLegacyWebTest {

	public $hostid = 99050;

	use FilterTrait;
	use TableTrait;

	public static function data() {
		return CDBHelper::getDataProvider(
			'SELECT hostid,status'.
			' FROM hosts'.
			' WHERE host LIKE \'%-layout-test%\''
		);
	}

	/**
	 * @dataProvider data
	 */
	public function testPageTriggers_CheckLayout($data) {
		$this->zbxTestLogin('triggers.php?filter_set=1&filter_hostids[0]='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of triggers');
		$this->zbxTestCheckHeader('Triggers');

		$this->zbxTestTextPresent('Displaying');
		// Get table headers.
		$result = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath("//thead/tr/th"));
		foreach ($elements as $element) {
			$result[] = $element->getText();
		}

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
			// Check table headers.
			$this->assertEquals(['', 'Severity', 'Value', 'Name', 'Operational data', 'Expression', 'Status', 'Info', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status', 'Value', 'Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
		}

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			// Check table headers.
			$this->assertEquals(['', 'Severity', 'Name', 'Operational data', 'Expression', 'Status', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status','Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Delete');
	}

	public static function getTagsFilterData() {
		return [
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'a', 'operator' => 'Equals'],
							['name' => 'TagK', 'value' => 'K', 'operator' => 'Contains']
						]
					],
					'result' => [
						'Third trigger for tag filtering'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'A', 'operator' => 'Contains'],
							['name' => 'TagK', 'value' => 'K', 'operator' => 'Contains']
						]
					],
					'result' => [
						'Third trigger for tag filtering'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'A', 'operator' => 'Contains'],
							['name' => 'TagK', 'value' => 'K', 'operator' => 'Contains']
						]
					],
					'result' => [
						'First trigger for tag filtering',
						'Third trigger for tag filtering'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'TagZ', 'value' => 'Z', 'operator' => 'Equals'],
							['name' => 'TagI', 'value' => 'I', 'operator' => 'Equals']
						]
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'TagZ', 'value' => 'z', 'operator' => 'Equals'],
							['name' => 'TagI', 'value' => 'i', 'operator' => 'Equals']
						]
					],
					'result' => [
						'Second trigger for tag filtering',
						'Third trigger for tag filtering'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'operator' => 'Equals']
						]
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'operator' => 'Contains']
						]
					],
					'result' => [
						'First trigger for tag filtering',
						'Third trigger for tag filtering'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getTagsFilterData
	 */
	public function testPageTriggers_TagsFilter($data) {
		$this->page->login()->open('triggers.php?filter_set=1&filter_hostids[0]='.$this->hostid);
		$form = $this->setTags($data['tag_options']['type'], $data['tag_options']['tags']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	public function testPageTriggers_ResetTagsFilter() {
		$result = [
			'Fifth trigger for tag filtering (no tags)',
			'First trigger for tag filtering',
			'Fourth trigger for tag filtering',
			'Second trigger for tag filtering',
			'Third trigger for tag filtering'
		];

		$this->zbxTestLogin('triggers.php?filter_set=1&filter_hostids[0]='.$this->hostid);
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->getField('Tags')->query('id:filter_tags_0_tag')->one()->fill('Tag1234');
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn();

		$form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn($result);
	}

	public static function getFilterData() {
		return [
			// With all severity options. All triggers
			[
				[
					'filter_options' => [
						'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]'],
						'Trigger disabled with tags'
					]
				]
			],
			// With two severity options.
			[
				[
					'filter_options' => [
						'Severity' => ['Average', 'Disaster'],
					],
					'result' => [
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
						'Trigger disabled with tags'
					]
				]
			],
			// Not inherited.
			[
				[
					'filter_options' => [
						'Inherited' =>'No'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
						'Trigger disabled with tags'
					]
				]
			],
			// Discovered.
			[
				[
					'filter_options' => [
						'Discovered' => 'Yes'
					],
					'result' => [
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// With dependencies.
			[
				[
					'filter_options' => [
						'With dependencies' => 'Yes'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// Filter by name (not case sensetive).
			[
				[
					'filter_options' => [
						'Name' => 'One'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// Normal state.
			[
				[
					'filter_options' => [
						'State' => 'Normal'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// Status enabled/disabled.
			[
				[
					'filter_options' => [
						'Status' => 'Enabled'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			[
				[
					'filter_options' => [
						'Status' => 'Disabled'
					],
					'result' => [
						'Trigger disabled with tags',
					]
				]
			],
			// Value Ok/Problem.
			[
				[
					'filter_options' => [
						'Value' => 'Ok'
					],
					'result' => [
						['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]'],
						'Trigger disabled with tags'
					]
				]
			],
			[
				[
					'filter_options' => [
						'Value' => 'Problem'
					],
					'result' => [
						['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// All filter options.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Name' =>'Inheritance trigger',
						'Severity' => 'Not classified',
						'State' =>'Unknown',
						'Value' =>'Problem',
						'Inherited' =>'Yes',
						'Discovered' =>'No',
						'With dependencies' =>'Yes',
					],
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'server', 'value' => 'selenium', 'operator' => 'Equals'],
							['name' => 'Street', 'value' => 'dzelzavas', 'operator' => 'Contains']
						]
					],
					'result' => [
						['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]'],
					]
				]
			],
			// No results.
			[
				[
					'filter_options' => [
						'Host groups' => 'Zabbix servers',
					]
				]
			],
			[
				[
					'filter_options' => [
						'State' => 'Normal',
						'Inherited' => 'Yes'
					]
				]
			],
			[
				[
					'filter_options' => [
						'State' => 'Unknown',
						'With dependencies' => 'No'
					]
				]
			],
			[
				[
					'filter_options' => [
						'Inherited' => 'Yes',
						'With dependencies' => 'No'
					]
				]
			],
			[
				[
					'filter_options' => [
						'Inherited' => 'Yes',
						'Discovered' => 'Yes',
						'With dependencies' => 'Yes'
					]
				]
			],
			[
				[
					'filter_options' => [
						'Status' => 'Disabled',
						'Value' => 'Problem'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageTriggers_Filter($data) {
		$this->page->login()->open('triggers.php?filter_set=1&filter_hostids[0]=99062');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$form->fill($data['filter_options']);

		if (array_key_exists('tag_options', $data)) {
			$this->setTags($data['tag_options']['type'], $data['tag_options']['tags']);
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	public static function getHostAndGroupData() {
		return [
			// One host group without host.
			[
				[
					'filter_options' => [
						'Host groups' => 'Group to check triggers filtering'
					],
					'result' => [
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]']
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]']
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]']
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => 'Trigger disabled with tags'
						]
					]
				]
			],
			// Two host group without host.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Severity' => 'Average',
						'Name' => 'tag'
					],
					'result' => [
						[
							'Host' => 'ЗАББИКС Сервер',
							'Name' => 'Test trigger to check tag filter on problem page'
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Third trigger for tag filtering'
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => 'Trigger disabled with tags'
						]
					]
				]
			],
			// Two hosts without host group.
			[
				[
					'filter_options' => [
						'Severity' => 'Average',
						'Hosts' => [
							[
								'values' => ['Host for trigger tags filtering'],
								'context' => 'Zabbix servers'
							],
							[
								'values' => ['Host for triggers filtering'],
								'context' => 'Group to check triggers filtering'
							]
						]
					],
					'result' => [
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Third trigger for tag filtering'
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => 'Trigger disabled with tags'
						]
					]
				]
			],
			// Two hosts and two their host groups.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Hosts' => [
							[
								'values' => ['Host for trigger tags filtering'],
								'context' => 'Zabbix servers'
							],
							[
								'values' => ['Host for triggers filtering'],
								'context' => 'Group to check triggers filtering'
							]
						]
					],
					'result' => [
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Dependent trigger ONE', 'selector' => 'xpath:./a[not(@class)]'],
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Discovered trigger one', 'selector' => 'xpath:./a[not(@class)]']
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Fifth trigger for tag filtering (no tags)'
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'First trigger for tag filtering'
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Fourth trigger for tag filtering'
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => ['text' => 'Inheritance trigger with tags', 'selector' => 'xpath:./a[not(@class)]']
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Second trigger for tag filtering'
						],
						[
							'Host' => 'Host for trigger tags filtering',
							'Name' => 'Third trigger for tag filtering'
						],
						[
							'Host' => 'Host for triggers filtering',
							'Name' => 'Trigger disabled with tags'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getHostAndGroupData
	 */
	public function testPageTriggers_FilterHostAndGroups($data) {
		$this->page->login()->open('triggers.php?filter_set=1&filter_hostids[0]=99062');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Trigger create button enabled and breadcrumbs exist.
		$this->assertTrue($this->query('button:Create trigger')->one()->isEnabled());
		$this->assertFalse($this->query('class:filter-breadcrumb')->all()->isEmpty());
		// Clear hosts in filter fields.
		if (!array_key_exists('Hosts', $data['filter_options'])) {
			$form->getField('Hosts')->asMultiselect()->clear();
		}

		$form->fill($data['filter_options']);
		$form->submit();
		$this->page->waitUntilReady();

		// Trigger create button disabled and breadcrumbs not exist.
		$this->assertFalse($this->query('button:Create trigger (select host first)')->one()->isEnabled());
		$this->assertTrue($this->query('class:filter-breadcrumb')->all()->isEmpty());
		// Check results in table.
		$this->assertTableData(CTestArrayHelper::get($data, 'result', []));
	}
}
