<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
require_once dirname(__FILE__).'/behaviors/CTagBehavior.php';
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @dataSource TagFilter
 */
class testPageMonitoringHosts extends CWebTest {

	/**
	 * Attach TagBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTagBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * Id of host that was updated.
	 *
	 * @var integer
	 */
	protected static $hostid;

	public function testPageMonitoringHosts_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Checking Title, Header and Column names.
		$this->page->assertTitle('Hosts');
		$this->page->assertHeader('Hosts');
		$headers = ['Name', 'Interface', 'Availability', 'Tags', 'Problems', 'Status', 'Latest data', 'Problems',
			'Graphs', 'Screens', 'Web'];
		$this->assertSame($headers, ($this->query('class:list-table')->asTable()->one())->getHeadersText());

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check fields maximum length.
		foreach(['name', 'ip', 'dns', 'port', 'tags_0_tag', 'tags_0_value'] as $field) {
			$this->assertEquals(255, $form->query('xpath:.//input[@id="filter_'.$field.'"]')
				->one()->getAttribute('maxlength'));
		}

		// Check disabled links.
		foreach (['Graphs', 'Screens', 'Web'] as $disabled) {
			$row = $table->findRow('Name', 'Available host');
			$this->assertTrue($row->query('xpath://following::td/span[@class="disabled" and text()="'.$disabled.'"]')->exists());
		}

		// Check tags on the specific host.
		$tags = $table->findRow('Name', 'Host for tags filtering - clone')->getColumn('Tags')->query('class:tag')->all();
		$this->assertEquals(['action: clone', 'tag: host'], $tags->asText());

		foreach ($tags as $tag) {
			$tag->click();
			$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last();
			$this->assertEquals($tag->getText(), $hint->getText());
			$hint->close();
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
					'expected' => []
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
						'Show hosts in maintenance' => false
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
						'Host for tags filtering'
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
						'Host for tags filtering'
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
						'Host for tags filtering',
						'Host for tags filtering - clone'
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
						'Host for tags filtering - clone',
						'Host for tags filtering - update'
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
						'Host for tags filtering - clone'
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
						'Host for tags filtering',
						'Host for tags filtering - clone',
						'Host for tags filtering - update'
					]
				]
			],
			// Wrote 'template' in lowercase.
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
			// Non-existing tag.
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
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableColumnData('Name');

		// Filter hosts.
		$form->fill(['Name' => 'Empty host']);
		$form->submit();
		$this->page->waitUntilReady();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// After pressing reset button, check that previous hosts are displayed again.
		$form->query('button:Reset')->one()->click();
		$reset_rows_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_rows_count);
		$this->assertTableStats($reset_rows_count);
		$this->assertEquals($start_contents, $this->getTableColumnData('Name'));
	}

	// Checking that Show suppressed problems filter works.
	public function testPageMonitoringHosts_ShowSuppresed() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();
		$form->fill(['Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']]);
		$form->submit();
		$this->page->waitUntilReady();
		foreach ([true, false] as $show) {
			$form->query('id:filter_show_suppressed')->asCheckbox()->one()->fill($show);
			$form->submit();
			$this->page->waitUntilReady();
			$this->assertTrue($table->findRow('Name', 'Host for suppression')->isPresent($show));
		}
	}

	public static function getEnabledLinksData() {
		return [
			[
				[
					'name' => 'Dynamic widgets H1',
					'link_name' => 'Graphs',
					'page_header' => 'Graphs'
				]
			],
			[
				[
					'name' => 'Host ZBX6663',
					'link_name' => 'Web',
					'page_header' => 'Web monitoring'
				]
			],
			[
				[
					'name' => 'ЗАББИКС Сервер',
					'link_name' => 'Screens',
					'page_header' => 'Network interfaces on ЗАББИКС Сервер'
				]
			],
			[
				[
					'name' => 'Empty host',
					'link_name' => 'Problems',
					'page_header' => 'Problems'
				]
			],
			[
				[
					'name' => 'Available host',
					'link_name' => 'Latest data',
					'page_header' => 'Latest data'
				]
			]
		];
	}

	/**
	 * @dataProvider getEnabledLinksData
	 *
	 * Check enabled links and that correct host is displayed.
	 */
	public function testPageMonitoringHosts_EnabledLinks($data) {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		switch ($data['name']) {
			case 'Dynamic widgets H1':
			case 'Host ZBX6663':
			case 'Available host':
				$field = ($data['name'] == 'Dynamic widgets H1') ? 'Host' : 'Hosts';
				$this->selectLink($data['name'], $data['link_name'], $data['page_header']);
				$form->checkValue([$field => $data['name']]);
				$form->query('button:Reset')->one()->click();
				break;
			case 'ЗАББИКС Сервер':
				$this->selectLink($data['name'], $data['link_name'], $data['page_header']);
				break;
			case 'Empty host':
				$this->query('xpath://td/a[text()="'.$data['name'].'"]/following::td/a[text()="'.$data['link_name'].'"]')
					->one()->click();
				$this->page->waitUntilReady();
				$this->page->assertHeader($data['page_header']);
				$form->checkValue(['Hosts' => $data['name']]);
				$form->query('button:Reset')->one()->click();
				break;
		}
	}

	public static function getHostContextMenuData() {
		return [
			[
				[
					'name' => 'ЗАББИКС Сервер',
					'disabled' => ['Web'],
					'titles' => [
						'Inventory',
						'Latest data',
						'Problems',
						'Graphs',
						'Screens',
						'Web',
						'Configuration',
						'Detect operating system',
						'Ping',
						'Reboot',
						'Selenium script',
						'Traceroute'
					]
				]
			],
			[
				[
					'name' => 'Available host',
					'disabled' => ['Web', 'Graphs', 'Screens'],
					'titles' => [
						'Inventory',
						'Latest data',
						'Problems',
						'Graphs',
						'Screens',
						'Web',
						'Configuration',
						'Detect operating system',
						'Ping',
						'Selenium script',
						'Traceroute'
					]
				]
			],
			[
				[
					'name' => 'Dynamic widgets H1',
					'disabled' => ['Screens', 'Web'],
					'titles' => [
						'Inventory',
						'Latest data',
						'Problems',
						'Graphs',
						'Screens',
						'Web',
						'Configuration',
						'Detect operating system',
						'Ping',
						'Selenium script',
						'Traceroute'
					]
				]
			],
			[
				[
					'name' => 'Host ZBX6663',
					'disabled' => ['Screens'],
					'titles' => [
						'Inventory',
						'Latest data',
						'Problems',
						'Graphs',
						'Screens',
						'Web',
						'Configuration',
						'Detect operating system',
						'Ping',
						'Reboot',
						'Selenium script',
						'Traceroute'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getHostContextMenuData
	 *
	 * Click on host name from the table and check displayed popup context.
	 */
	public function testPageMonitoringHosts_HostContextMenu($data) {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', $data['name']);
		$row->query('link', $data['name'])->one()->click();
		$this->page->waitUntilReady();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertEquals(['HOST', 'SCRIPTS'], $popup->getTitles()->asText());
		$this->assertTrue($popup->hasItems($data['titles']));
		foreach ($data['disabled'] as $disabled) {
			$this->assertTrue($popup->query('xpath://a[@aria-label="Host, '.
					$disabled.'" and @class="menu-popup-item-disabled"]')->one()->isPresent());
		}
	}

	public function prepareUpdateData() {
		$response = CDataHelper::call('host.update', ['hostid' => '99013', 'status' => 1]);
		$this->assertArrayHasKey('hostids', $response);
		self::$hostid = $response['hostids'][0];
	}

	/**
	 * @backup hosts
	 *
	 * @onBeforeOnce prepareUpdateData
	 */
	public function testPageMonitoringHosts_TableSorting() {
		// Sort by name and status.
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		foreach (['Name', 'Status'] as $listing) {
			$query = $this->query('xpath://a[@href and text()="'.$listing.'"]');
			$query->one()->click();
			$this->page->waitUntilReady();
			$after_listing = $this->getTableColumnData($listing);
			$query->one()->click();
			$this->page->waitUntilReady();
			$this->assertEquals(array_reverse($after_listing), $this->getTableColumnData($listing));
		}
	}

	/**
	 * Сount problems amount from first column and compare with displayed problems from another Problems column.
	 */
	public function testPageMonitoringHosts_CountProblems() {
		$this->page->login()->open('zabbix.php?action=host.view&filter_rst=1');
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$hosts = [
			'1_Host_to_check_Monitoring_Overview',
			'3_Host_to_check_Monitoring_Overview',
			'4_Host_to_check_Monitoring_Overview',
			'Host for tag permissions',
			'Host for triggers filtering',
			'ЗАББИКС Сервер'
		];
		foreach ($hosts as $host) {
			$form->fill(['Name' => $host]);
			$form->submit();
			$this->page->waitUntilReady();

			$row = $table->findRow('Name', $host);
			$icons = $row->query('xpath://td/div[@class="problem-icon-list"]/span')->all();
			$result = 0;
			foreach ($icons as $icon) {
				$result += intval($icon->getText());
			}

			// Getting problems amount from second Problems column and then comparing with summarized first column.
			$problems = $row->query('xpath://td/a[text()="Problems"]/following::sup')->one()->getText();
			$this->assertEquals((int)$problems, $result);
		}
	}

	/**
	 * Clicking on link from the table and then checking page header
	 *
	 * @param string $host_name		Host name
	 * @param string $column		Column name
	 * @param string $page_header	Page header name
	 */
	private function selectLink($host_name, $column, $page_header) {
		$this->query('class:list-table')->asTable()->one()->findRow('Name', $host_name)->getColumn($column)->click();
		$this->page->waitUntilReady();
		if ($page_header !== null) {
			$this->page->assertHeader($page_header);
		}
		if ($host_name === 'Dynamic widgets H1' && $this->query('xpath://li[@aria-labelledby="ui-id-2"'.
				' and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}
	}
}
