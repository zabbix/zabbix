<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
require_once dirname(__FILE__).'/traits/TableTrait.php';
require_once dirname(__FILE__).'/traits/FilterTrait.php';

class testPageMonitoringHosts extends CWebTest {

	use FilterTrait;
	use TableTrait;

	public function testPageMonitoringHosts_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=host.view');
		$headers = ['Name', 'Interface', 'Availability', 'Tags', 'Problems', 'Status', 'Latest data', 'Problems',
				'Graphs', 'Screens', 'Web'];
		$buttons = ['Apply', 'Reset'];
		$tags_buttons = ['Add', 'Remove'];
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$fields = ['Name', 'Host groups', 'IP', 'DNS', 'Port', 'Severity', 'Status', 'Tags',
				'Show hosts in maintenance'];
		$severity_elements = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		$status_elements = ['Any', 'Enabled', 'Disabled'];
		$tags_elements = ['And/Or', 'Or', 'Contains', 'Equals'];

		// Checking Title, Header and Column names.
		$this->assertPageTitle('Hosts');
		$this->assertPageHeader('Hosts');
		$this->assertSame($headers, ($this->query('class:list-table')->asTable()->one())->getHeadersText());

		// Check filter buttons.
		foreach ($buttons as $button) {
			$this->assertTrue($form->query('button', $button)->exists());
		}

		// Check all field names.
		$this->assertEquals($fields, $form->getLabels()->asText());

		// Check elements for existing fields.
		foreach ($fields as $field) {
			switch ($field) {
				case 'Severity':
					$this->assertEquals($form->getField($field)->getLabels()->asText(), $severity_elements);
					break;
				case 'Status':
					$this->assertEquals($form->getField($field)->getLabels()->asText(), $status_elements);
					break;
				case 'Tags':
					$tags_container = $form->getFieldContainer($field);
					foreach ($tags_elements as $tag) {
						$this->assertTrue(($tags_container->query('xpath://li/label[text() = "'.$tag.'"]'))->
						exists());
					}
					foreach ($tags_buttons as $button) {
						$this->assertTrue($tags_container->query('button', $button)->exists());
					}
					break;
				case 'Show hosts in maintenance':
					$this->assertTrue($form->getField($field)->getLabel('Show suppressed problems')->isPresent());
					$this->assertTrue($form->query('class:second-column-label')->exists());
					break;
			}
		}

		// Check filter collapse/expand.
		$filter_expanded = ['true', 'false'];
		foreach ($filter_expanded as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'filter' => [
						'Name' => 'Empty host'
					],
					'expected' => [
						'Empty host'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => [
							'Group to copy all graph'
						]
					],
					'expected' => [
						'Host with item to copy all graphs 1',
						'Host with item to copy all graphs 2'
					]
				]
			],
			[
				[
					'filter' => [
						'IP' => '127.0.0.3'
					],
					'expected' => [
						'Template inheritance test host',
						'Test item host'
					]
				]
			],
			[
				[
					'filter' => [
						'DNS' => 'zabbixzabbixzabbix.com'
					],
					'expected' => [
						'Available host',
						'Not available host',
						'Not available host in maintenance',
						'Unknown host',
						'Unknown host in maintenance'
					]
				]
			],
			[
				[
					'filter' => [
						'Port' => '161'
					],
					'expected' => [
						'Test item host',
						'Visible host for template linkage'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'Not classified'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview',
						'Host for tag permissions'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'Warning'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview',
						'ЗАББИКС Сервер'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'High'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'Information'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'Average'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview',
						'3_Host_to_check_Monitoring_Overview',
						'4_Host_to_check_Monitoring_Overview',
						'Host for triggers filtering',
						'ЗАББИКС Сервер'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => 'Disaster'
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview'
					]
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'available',
						'Host groups' => [
							'Group for Host availability widget'
						]
					],
					'expected' => [
						'Available host',
						'Not available host'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'for',
						'Host groups' => [
							'Zabbix servers'
						],
						'IP' => '127.0.5.1'
					],
					'expected' => [
						'Simple form test host'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Unknown',
						'Host groups' => [
							'Group for Host availability widget'],
						'IP' => '127.0.0.1',
						'DNS' => 'zabbix.com'
					],
					'expected' => [
						'Unknown host'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'maintenance',
						'Host groups' => [
							'Group in maintenance for Host availability widget'
						],
						'IP' => '127.0.0.1',
						'DNS' => 'zab',
						'Port' => '10050'
					],
					'expected' => [
						'Not available host in maintenance',
						'Unknown host in maintenance'
					]
				]
			],
			[
				[
					'filter' => [
						'Severity' => [
							'Not classified',
							'Warning',
							'High',
							'Information',
							'Average',
							'Disaster'
						]
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview',
						'3_Host_to_check_Monitoring_Overview',
						'4_Host_to_check_Monitoring_Overview',
						'Host for tag permissions',
						'Host for triggers filtering',
						'ЗАББИКС Сервер'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Tommy'
					],
					'expected' => []
				]
			],
			// With name 'maintenance', exists 3 hosts in maintenance status. Unchecking 'Show hosts in maintenance'.
			[
				[
					'filter' => [
						'Name' => 'maintenance',
						'Show hosts in maintenance' => ''
					],
					'expected' => []
				]
			],
			[
				[
					'filter' => [
						'Name' => 'maintenance'
					],
					'expected' => [
						'Available host in maintenance',
						'Not available host in maintenance',
						'Unknown host in maintenance'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testPageMonitoringHosts_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn($data['expected']);
	}

	public static function getTagsFilterData() {
		return [
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'test', 'value' => 'test_tag', 'operator' => 'Equals']
						]
					],
					'result' => [
						'Simple form test host'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'test', 'value' => '', 'operator' => 'Contains']
						]
					],
					'result' => [
						'Simple form test host'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'test', 'value' => 'test_tag', 'operator' => 'Equals'],
							['name' => 'action', 'value' => 'clone', 'operator' => 'Contains']
						]
					],
					'result' => [
						'Host with tags for cloning',
						'Simple form test host'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'action', 'value' => 'clone', 'operator' => 'Equals'],
							['name' => 'tag', 'value' => 'host', 'operator' => 'Equals']
						]
					],
					'result' => [
						'Host with tags for cloning',
						'Host with tags for updating'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'action', 'value' => 'clone', 'operator' => 'Contains'],
							['name' => 'tag', 'value' => 'host', 'operator' => 'Contains']
						]
					],
					'result' => [
						'Host with tags for cloning'
					]
				]
			],
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'action', 'value' => 'clone', 'operator' => 'Equals'],
							['name' => 'action', 'value' => 'update', 'operator' => 'Equals'],
							['name' => 'tag', 'value' => 'TEMPLATE', 'operator' => 'Equals']
						]
					],
					'result' => [
						'Host with tags for cloning',
						'Host with tags for updating',
						'Simple form test host'
					]
				]
			],
			// wrote 'template' in lowercase.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'tag', 'value' => 'template', 'operator' => 'Equals']
						]
					],
					'result' => []
				]
			],
			// non existing tag
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'Tommy', 'value' => 'train', 'operator' => 'Contains']
						]
					],
					'result' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getTagsFilterData
	 */
	public function testPageMonitoringHosts_TagsFilter($data) {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$form->fill(['id:filter_evaltype' => $data['tag_options']['type']]);
		$this->setTags($data['tag_options']['tags']);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'result', []));
	}

	public function testPageMonitoringHosts_ResetButtonCheck() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_name=&filter_ip=&filter_dns=&filter_port=' .
				'&filter_status=-1&filter_evaltype=0&filter_tags%5B0%5D%5Btag%5D=&filter_tags%5B0%5D%5Boperator%5D='.
				'0&filter_tags%5B0%5D%5Bvalue%5D=&filter_maintenance_status=1&filter_show_suppressed=0&filter_set=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertRowCount($start_rows_count);
		$start_contents = $this->getTableResult($start_rows_count);

		// Filter hosts
		$form->fill(['Name' => 'Empty host']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check that filtered count matches expected.
		$this->assertEquals(count(['Empty host']), $table->getRows()->count());
		$this->assertRowCount(count(['Empty host']));

		// After pressing reset button, check that previous hosts are displayed again.
		$form->query('button:Reset')->one()->click();
		$reset_rows_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_rows_count);
		$this->assertRowCount($reset_rows_count);
		$this->assertEquals($start_contents, $this->getTableResult($reset_rows_count));
	}

	/**
	 * Checking that Show suppressed problems filter works.
	 */
	public function testPageMonitoringHosts_ShowSuppresed() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$form->fill(['Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']]);
		$form->submit();
		$this->page->waitUntilReady();
		$before_show_count = $table->getRows()->count();
		$form->query('id:filter_show_suppressed')->asCheckbox()->one()->check();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertEquals($table->getRows()->count(), $before_show_count + 1);
	}

	/**
	 * @param integer $rows_count	Rows amount whom host name should be checked
	 */
	private function getTableResult($rows_count) {
		$table = $this->query('class:list-table')->asTable()->one();
		$result = [];
		for ($i = 0; $i < $rows_count; $i ++) {
			$result[] = $table->getRow($i)->getColumn('Name')->getText();
		}
	}
}
