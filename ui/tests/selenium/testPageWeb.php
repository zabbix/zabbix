<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../include/helpers/CDataHelper.php';

/**
 * @backup hosts, httptest
 *
 * @onBefore prepareHostWebData
 */
class testPageWeb extends CWebTest {

	public function prepareHostWebData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'WebData HostGroup'
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

		CDataHelper::call('host.create', [
			'host' => 'WebData Host',
			'groups' => [
				[
					'groupid' => $hostgrpid['WebData HostGroup']
				]
			],
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '192.168.3.217',
				'dns' => '',
				'port' => '10050'
			]
		]);
		$hostid = CDataHelper::getIds('host');

		CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario 1 step',
				'hostid' => $hostid['WebData Host'],
				'steps' => [
					[
						'name' => 'Homepage',
						'url' => 'http://zabbix.com',
						'no' => 1
					]
				]
			],
			[
				'name' => 'Web scenario 2 step',
				'hostid' => $hostid['WebData Host'],
				'steps' => [
					[
						'name' => 'Homepage1',
						'url' => 'http://example.com',
						'no' => 1
					],
					[
						'name' => 'Homepage2',
						'url' => 'http://example.com',
						'no' => 2
					]
				]
			],
			[
				'name' => 'Web scenario 3 step',
				'hostid' => $hostid['WebData Host'],
				'steps' => [
					[
						'name' => 'Homepage1',
						'url' => 'http://example.com',
						'no' => 1
					],
					[
						'name' => 'Homepage2',
						'url' => 'http://example.com',
						'no' => 2
					],
					[
						'name' => 'Homepage3',
						'url' => 'http://example.com',
						'no' => 3
					]
				]
			]
		]);
	}

	use TableTrait;

	/**
	 * Function checks the layout of Web page.
	 */
	public function testPageWeb_CheckLayout() {
		// Logins directly into required page.
		$this->page->login()->open('zabbix.php?action=web.view');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Checks Title, Header, and column names, and filter labels.
		$this->page->assertTitle('Web monitoring');
		$this->page->assertHeader('Web monitoring');
		$this->assertEquals(['Host', 'Name', 'Number of steps', 'Last check', 'Status'], $table->getHeadersText());
		$this->assertEquals(['Host groups', 'Hosts'], $form->getLabels()->asText());

		// Check if Apply and Reset button are clickable.
		foreach(['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isClickable());
		}

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$this->assertTrue($this->query('xpath://li[@aria-expanded='.CXPathHelper::escapeQuotes($status).']')
					->one()->isPresent()
			);
			$this->query('xpath://a[@class="filter-trigger ui-tabs-anchor"]')->one()->click();
		}

		// Check if links to Hosts and to Web scenarios are clickable.
		foreach (['Host', 'Name'] as $field) {
			$this->assertTrue($table->getRow(0)->getColumn($field)->query('xpath:.//a')->one()->isClickable());
		}

		// Check if the correct amount of rows is displayed.
		$this->assertTableStats($table->getRows()->count());
	}

	public static function getHostContextMenuData() {
		return [
			[
				[
					'name' => 'Template inheritance test host',
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
						'Script for Clone',
						'Script for Delete',
						'Script for Update',
						'Selenium script',
						'Traceroute'
					]
				]
			],
			[
				[
					'name' => 'Simple form test host',
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
						'Script for Clone',
						'Script for Delete',
						'Script for Update',
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
						'Script for Clone',
						'Script for Delete',
						'Script for Update',
						'Selenium script',
						'Traceroute'
					]
				]
			]
		];
	}

	/**
	 * Function which checks Hosts context menu.
	 *
	 * @dataProvider getHostContextMenuData
	 */
	public function testPageWeb_CheckHostContextMenu($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=hostname&sortorder=DESC');
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Host', $data['name']);
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

	/**
	 * Function which resets data provided to filter.
	 */
	private function resetFilter() {
		$filter = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$filter->query('button:Reset')->one()->click();
	}

	public static function getDisabledServiceData() {
		return [
			[
				[
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'Web scenario 3 step',
						'Web scenario 2 step',
						'Web scenario 1 step',
						'testInheritanceWeb4',
						'testInheritanceWeb3',
						'testInheritanceWeb2',
						'testInheritanceWeb1'
					]
				]
			]
		];
	}

	/**
	 * Function which checks if disabled web services aren't displayed.
	 *
	 * @dataProvider getDisabledServiceData
	 */
	public function testPageWeb_CheckDisabledWebServices($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Host', 'Simple form test host');
		$row->query('link', 'Simple form test host')->one()->click();
		$this->page->waitUntilReady();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$popup->query('xpath://a[@aria-label="Host, Configuration"]')->one()->click();

		// Check if Host is opened.
		$this->page->assertTitle('Configuration of hosts');
		$this->page->assertHeader('Hosts');
		$this->query('xpath://a[normalize-space()="Web scenarios"]')->one()->click();
		$this->query('xpath://input[@id="all_httptests"]')->one()->click();

		// Turn off web services
		$this->query('xpath://button[normalize-space()="Disable"]')->one()->click();
		$this->page->acceptAlert();
		$this->page->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$this->assertTableDataColumn($data['expected']);

		// Turn back on disbabled web services.
		$this->page->open('httpconf.php?filter_set=1&filter_hostids%5B0%5D=40001');
		$this->query('xpath://input[@id="all_httptests"]')->one()->click();
		$this->query('xpath://button[normalize-space()="Enable"]')->one()->click();
		$this->page->acceptAlert();
	}

	/**
	 * Function which checks number of steps for web services displayed.
	 */
	public function testPageWeb_CheckWebServiceNumberOfSteps() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one()->fill(['Hosts' => 'WebData Host'])->submit();
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Host', 'WebData Host');
		$count_before = $row->getColumn('Number of steps')->getText();
		$this->assertEquals('3', $count_before);
		$row->query('link', 'WebData Host')->one()->click();
		$this->page->waitUntilReady();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$popup->query('xpath://a[@aria-label="Host, Configuration"]')->one()->click();

		// Check if Host is opened.
		$this->page->assertTitle('Configuration of hosts');
		$this->page->assertHeader('Hosts');

		// Open hosts Web scenarios steps.
		$this->query('xpath://a[normalize-space()="Web scenarios"]')->one()->click();
		$this->query('xpath://a[normalize-space()="Web scenario 3 step"]')->one()->click();
		$this->query('xpath://a[@id="tab_stepTab"]')->one()->click();

		// Add one more Web Scenarios step.
		$this->query('xpath://button[@class="element-table-add btn-link"]')->one()->click();
		$this->page->waitUntilReady();
		$form = $this->query('id:http_step')->asForm()->one();
		//var_dump($form->getLabels()->asText());
		$form->fill(['Name' => 'Step number 4']);
		sleep(2);
		$form->fill(['URL' => 'test.com']); // xpath//div[@class="overlay-dialogue-body"]//input[@id="url"]

		$form->submit();
		$this->query('xpath://button[@id="update"]')->one()->click();

		// Check that successfully step was added without unexpected errors.
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Web scenario updated', $message->getTitle());

		// Return to the "Web monitoring" and check if the "Number of steps" is correctly displayed.
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one()->fill(['Hosts' => 'WebData Host'])->submit();
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Host', 'WebData Host');
		$count_after = $row->getColumn('Number of steps')->getValue();
		$this->assertEquals('4', $count_after);
		$this->resetFilter();
	}

	/**
	 * Function that checks sorting by Name column.
	 */
	public function testPageWeb_CheckSorting() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=hostname&sortorder=ASC');
		$table = $this->query('class:list-table')->asTable()->one();
		foreach (['Host', 'Name'] as $column_name) {
			if ($column_name === 'Name') {
				$table->query('xpath:.//a[text()="'.$column_name.'"]')->one()->click();
			}
			$column_values = $this->getTableResult($column_name);

			foreach(['asc', 'desc'] as $sorting) {
				$expected = ($sorting === 'asc') ? $column_values : array_reverse($column_values);
				$this->assertEquals($expected, $this->getTableResult($column_name));
				$table->query('xpath:.//a[text()="'.$column_name.'"]')->one()->click();
			}
		}
	}

	/**
	 * Test that title field disappears while Kioskmode is active.
	 */
	public function testPageWeb_CheckKioskMode() {
		$this->page->login()->open('zabbix.php?action=web.view');
		$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
		$this->page->waitUntilReady();
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilNotVisible();
		$this->query('xpath://button[@title="Normal view"]')->waitUntilPresent()->one()->click(true);
		$this->page->waitUntilReady();
		$this->query('xpath://h1[@id="page-title-general"]')->waitUntilVisible();
	}

	/**
	 * Test that links to web service names are working properly and directed to needed form.
	 */
	public function testPageWeb_CheckLinks() {
		$this->page->login()->open('zabbix.php?action=web.view');
		$this->query('class:list-table')->asTable()->one()->findRow('Name', 'testFormWeb1')
				->query('link', 'testFormWeb1')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Details of web scenario: testFormWeb1');
	}

	public static function getCheckFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'testInheritanceWeb4',
						'testInheritanceWeb3',
						'testInheritanceWeb2',
						'testInheritanceWeb1',
						'testFormWeb4',
						'testFormWeb3',
						'testFormWeb2',
						'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => 'Simple form test host'
					],
					'expected' => [
						'testFormWeb4',
						'testFormWeb3',
						'testFormWeb2',
						'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host ZBX6663'
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => [
							'Host ZBX6663',
							'Simple form test host'
						]
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'testFormWeb4',
						'testFormWeb3',
						'testFormWeb2',
						'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'Host ZBX6663',
							'Simple form test host',
							'Template inheritance test host'
						]
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'testInheritanceWeb4',
						'testInheritanceWeb3',
						'testInheritanceWeb2',
						'testInheritanceWeb1',
						'testFormWeb4',
						'testFormWeb3',
						'testFormWeb2',
						'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'WebData HostGroup',
						'Hosts' => ['WebData Host']
					],
					'expected' => [
						'Web scenario 3 step',
						'Web scenario 2 step',
						'Web scenario 1 step'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'Host ZBX6663',
							'Simple form test host',
							'Template inheritance test host',
							'WebData Host'
						]
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'Web scenario 3 step',
						'Web scenario 2 step',
						'Web scenario 1 step',
						'testInheritanceWeb4',
						'testInheritanceWeb3',
						'testInheritanceWeb2',
						'testInheritanceWeb1',
						'testFormWeb4',
						'testFormWeb3',
						'testFormWeb2',
						'testFormWeb1'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => [
							'WebData HostGroup',
							'Zabbix servers'
						],
						'Hosts' => [
							'Host ZBX6663',
							'WebData Host'
						]
					],
					'expected' => [
						'Web ZBX6663 Second',
						'Web ZBX6663',
						'Web scenario 3 step',
						'Web scenario 2 step',
						'Web scenario 1 step'
					]
				]
			],
		];
	}

	/**
	 * @dataProvider getCheckFilterData
	 */
	public function testPageWeb_CheckFilter($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC');
		$this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one()->fill($data['filter'])->submit();
		$this->page->waitUntilReady();
		$this->assertTableDataColumn($data['expected']);
		$this->resetFilter();
	}
}
