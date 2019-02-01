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

class testPageTriggers extends CLegacyWebTest {
	public $hostid = 99050;

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
			$this->assertEquals(['', 'Severity', 'Value', 'Name', 'Expression', 'Status', 'Info', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status', 'Value', 'Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
		}

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			// Check table headers.
			$this->assertEquals(['', 'Severity', 'Name', 'Expression', 'Status', 'Tags'], $result);

			// Check the filter options text.
			foreach (['Severity', 'State', 'Status','Tags'] as $label) {
				$this->zbxTestAssertElementPresentXpath('//label[text()="'.$label.'"]');
			}
			$this->zbxTestAssertElementNotPresentXpath('//label[text()="Value"]');
		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Delete');
	}

	public static function getTagsFilterData() {
		return [
			[
				[
					'tag_options' => [
						'operator' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'a', 'type' => 'Equals'],
							['name' => 'TagK', 'value' => 'K', 'type' => 'Contains']
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
						'operator' => 'And/Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'A', 'type' => 'Contains'],
							['name' => 'TagK', 'value' => 'K', 'type' => 'Contains']
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
						'operator' => 'Or',
						'tags' => [
							['name' => 'TagA', 'value' => 'A', 'type' => 'Contains'],
							['name' => 'TagK', 'value' => 'K', 'type' => 'Contains']
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
						'operator' => 'Or',
						'tags' => [
							['name' => 'TagZ', 'value' => 'Z', 'type' => 'Equals'],
							['name' => 'TagI', 'value' => 'I', 'type' => 'Equals']
						]
					]
				]
			],
			[
				[
					'tag_options' => [
						'operator' => 'Or',
						'tags' => [
							['name' => 'TagZ', 'value' => 'z', 'type' => 'Equals'],
							['name' => 'TagI', 'value' => 'i', 'type' => 'Equals']
						]
					],
					'result' => [
						'Second trigger for tag filtering',
						'Third trigger for tag filtering'
					]
				]
			]
		];
	}

	/**
	 *
	 * @dataProvider getTagsFilterData
	 */
	public function testPageTriggers_TagsFilter($data) {
		$this->page->login()->open('triggers.php?filter_set=1&filter_hostids[0]='.$this->hostid);
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$this->setTags($data['tag_options']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->checkFilterResults($data);
	}

	public function testPageTriggers_ResetTagsFilter() {
		$data = [
			'result' => [
				'Fifth trigger for tag filtering (no tags)',
				'First trigger for tag filtering',
				'Fourth trigger for tag filtering',
				'Second trigger for tag filtering',
				'Third trigger for tag filtering'
			]
		];

		$this->zbxTestLogin('triggers.php?filter_set=1&filter_hostids[0]='.$this->hostid);
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->getField('Tags')->query('id:filter_tags_0_tag')->one()->fill('Tag1234');
		$form->submit();
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['No data found.'], $table->getRows()->asText());

		$form->query('button:Reset')->one()->click();
		$this->page->waitUntilReady();
		$this->checkFilterResults($data);
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
						'Dependent trigger ONE',
						'Discovered trigger one',
						'Inheritance trigger with tags',
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
						'Discovered trigger one',
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
						'Dependent trigger ONE',
						'Discovered trigger one',
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
						'Discovered trigger one'
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
						'Dependent trigger ONE',
						'Inheritance trigger with tags'
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
						'Dependent trigger ONE',
						'Discovered trigger one'
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
						'Dependent trigger ONE',
						'Discovered trigger one'
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
						'Dependent trigger ONE',
						'Discovered trigger one',
						'Inheritance trigger with tags'
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
						'Dependent trigger ONE',
						'Discovered trigger one',
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
						'Inheritance trigger with tags'
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
						'operator' => 'Or',
						'tags' => [
							['name' => 'server', 'value' => 'selenium', 'type' => 'Equals'],
							['name' => 'Street', 'value' => 'dzelzavas', 'type' => 'Contains']
						]
					],
					'result' => [
						'Inheritance trigger with tags'
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
			$this->setTags($data['tag_options']);
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->checkFilterResults($data);
	}

	private function setTags($data) {
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$tag_table = $form->getField('Tags');

		$tag_table->getRow(0)->query('class:radio-segmented')->asSegmentedRadio()->one()->select($data['operator']);

		$button = $tag_table->query('button:Add')->one();
		$last = count($data['tags']) - 1;
		foreach ($data['tags'] as $i => $tag) {
			$tag_row = $tag_table->getRow($i+1);
			// TODO: after update to latest trunk, column will start from 0
			$tag_row->getColumn(1)->query('tag:input')->one()->fill($tag['name']);
			$tag_row->getColumn(2)->query('class:radio-segmented')->asSegmentedRadio()->one()->select($tag['type']);
			$tag_row->getColumn(3)->query('tag:input')->one()->fill($tag['value']);

			if ($i !== $last) {
				$button->click();
			}
		}
	}

	private function checkFilterResults($data) {
		$table = $this->query('class:list-table')->asTable()->one();
		if (array_key_exists('result', $data)) {
			foreach ($table->getRows() as $i => $row) {
				$host_name = $row->getColumn('Name')->query('xpath:./a[not(@class)]')->one()->getText();
				$this->assertEquals($data['result'][$i], $host_name);
			}
			$this->assertEquals(count($data['result']), $table->getRows()->count());
		}
		else {
			// Check that table contain one row with text "No data found."
			$this->assertEquals(['No data found.'], $table->getRows()->asText());
		}
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
						['Host for triggers filtering' => 'Dependent trigger ONE'],
						['Host for triggers filtering' => 'Discovered trigger one'],
						['Host for triggers filtering' => 'Inheritance trigger with tags'],
						['Host for triggers filtering' => 'Trigger disabled with tags']
					]
				]
			],
			// Two host group without host.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Severity' => 'Average',
						'Name' => 'tag',
					],
					'result' => [
						['ЗАББИКС Сервер' => 'Test trigger to check tag filter on problem page'],
						['Host for trigger tags filtering' => 'Third trigger for tag filtering'],
						['Host for triggers filtering' => 'Trigger disabled with tags']
					]
				]
			],
			// Two hosts without host group.
			[
				[
					'hosts' => [
						['group' => 'Zabbix servers', 'host' => 'Host for trigger tags filtering'],
						['group' => 'Group to check triggers filtering', 'host' => 'Host for triggers filtering']
					],
					'filter_options' => [
						'Severity' => 'Average'
					],
					'result' => [
						['Host for trigger tags filtering' => 'Third trigger for tag filtering'],
						['Host for triggers filtering' => 'Trigger disabled with tags']
					]
				]
			],
			// Two hosts and two their host groups.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
					],
					'hosts' => [
						['group' => 'Zabbix servers', 'host' => 'Host for trigger tags filtering'],
						['group' => 'Group to check triggers filtering', 'host' => 'Host for triggers filtering']
					],
					'result' => [
						['Host for triggers filtering' => 'Dependent trigger ONE'],
						['Host for triggers filtering' => 'Discovered trigger one'],
						['Host for trigger tags filtering' => 'Fifth trigger for tag filtering (no tags)'],
						['Host for trigger tags filtering' => 'First trigger for tag filtering'],
						['Host for trigger tags filtering' => 'Fourth trigger for tag filtering'],
						['Host for triggers filtering' => 'Inheritance trigger with tags'],
						['Host for trigger tags filtering' => 'Second trigger for tag filtering'],
						['Host for trigger tags filtering' => 'Third trigger for tag filtering'],
						['Host for triggers filtering' => 'Trigger disabled with tags']
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
		// Clear hosts and host groups in filter fields.
		$form->getField('Hosts')->asMultiselect()->clear();
		$form->getField('Host groups')->asMultiselect()->clear();

		$form->fill($data['filter_options']);

		if (array_key_exists('hosts', $data)) {
			foreach ($data['hosts'] as $host) {
				$overlay = $form->getField('Hosts')->asMultiselect()->edit();
				$overlay_form = $this->query('xpath://div[@id="overlay_dialogue"]//form')->waitUntilVisible()->asForm()->one();
				$group = $overlay_form->query('name:groupid')->asDropdown()->one();
				if ($group->getText() != $host['group']) {
					$group->fill($host['group']);
					$overlay_form->waitUntilReloaded();
				}
				$overlay->query('link:'.$host['host'])->one()->click();
				$overlay->waitUntilNotPresent();
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Trigger create button disabled and breadcrumbs not exist.
		$this->assertFalse($this->query('button:Create trigger (select host first)')->one()->isEnabled());
		$this->assertTrue($this->query('class:filter-breadcrumb')->all()->isEmpty());
		// Check results in table.
		$table = $this->query('class:list-table')->asTable()->one();
		foreach ($table->getRows() as $i => $row) {
			$get_host = $row->getColumn('Name')->query('xpath:./a[not(@class)]')->one()->getText();
			$get_group = $row->getColumn('Host')->getText();
			foreach ($data['result'][$i] as $group => $host) {
				$this->assertEquals($host, $get_host);
				$this->assertEquals($group, $get_group);
			}
		}
		$this->assertEquals(count($data['result']), $table->getRows()->count());
	}
}
