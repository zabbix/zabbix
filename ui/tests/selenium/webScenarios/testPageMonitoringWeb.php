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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';

/**
 * @backup hosts, httptest
 *
 * @dataSource WebScenarios, DiscoveredHosts, EntitiesTags, ExecuteNowAction, WidgetCommunication
 *
 * @onBefore getContextData
 */
class testPageMonitoringWeb extends CWebTest {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			CTagBehavior::class
		];
	}

	const HOST = 'Simple form test host';
	const SCENARIO = 'Scenario for Delete';

	/**
	 * Host id created for web service.
	 *
	 * @var integer
	 */
	private static $hostid;

	/**
	 * Web service id.
	 *
	 * @var integer
	 */
	private static $httptestid;

	/**
	 * Get the necessary properties of entities used within this test.
	 */
	public static function getContextData() {
		self::$hostid = CDataHelper::get('WebScenarios.hostid');
		self::$httptestid = CDataHelper::get('WebScenarios.httptestids.'.self::SCENARIO);
	}

	/**
	 * Function which checks the layout of Monitoring Web scenarios page.
	 */
	public function testPageMonitoringWeb_CheckLayout() {
		// Logins directly into required page.
		$this->page->login()->open('zabbix.php?action=web.view');
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$table = $this->query('class:list-table')->asTable()->one();

		// Checks Title, Header, and column names, and filter labels.
		$this->page->assertTitle('Web monitoring');
		$this->page->assertHeader('Web monitoring');
		$this->assertEquals(['Host', 'Name', 'Number of steps', 'Last check', 'Status', 'Tags'], $table->getHeadersText());
		$this->assertEquals(['Host groups', 'Hosts', 'Tags'], $form->getLabels()->asText());

		// Check if Apply and Reset button are clickable.
		foreach(['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isClickable());
		}

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$this->assertTrue($this->query('xpath://li[@aria-expanded='.CXPathHelper::escapeQuotes($status).']')
					->one()->isPresent()
			);
			//$this->query('xpath://a[@class="filter-trigger ui-tabs-anchor"]')->one()->click();
			$this->query('xpath://a[@id="ui-id-1"]')->one()->click();
		}

		// Check fields maximum length.
		foreach(['filter_tags[0][tag]', 'filter_tags[0][value]'] as $field) {
			$this->assertEquals(255, $form->query('xpath:.//input[@name="'.$field.'"]')
					->one()->getAttribute('maxlength')
			);
		}

		// Check if links to Hosts and to Web scenarios are clickable.
		$row = $table->getRow(0);

		foreach (['Host', 'Name'] as $field) {
			$this->assertTrue($row->getColumn($field)->query('xpath:.//a')->one()->isClickable());
		}

		// Check that the correct details of scenario are opened.
		$column = $row->getColumn('Name');
		$first_row_name = $column->getText();
		$column->query('tag:a')->one()->click();

		$this->page->waitUntilReady();
		$this->page->assertHeader('Details of web scenario: '.$first_row_name);
		$this->page->assertTitle('Details of web scenario');
	}

	/**
	 * Function which checks if button "Reset" works properly.
	 */
	public function testPageMonitoringWeb_ResetButtonCheck() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableColumnData('Name');

		// Filter hosts.
		$form->fill(['Hosts' => self::HOST]);
		$this->query('button:Apply')->one()->waitUntilClickable()->click();
		$table->waitUntilReloaded();

		// Check that filtered count matches expected.
		$this->assertEquals(3, $table->getRows()->count());
		$this->assertTableStats(3);

		// After pressing reset button, check that previous hosts are displayed again.
		$this->query('button:Reset')->one()->click();
		$table->waitUntilReloaded();
		$reset_rows_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_rows_count);
		$this->assertTableStats($reset_rows_count);
		$this->assertEquals($start_contents, $this->getTableColumnData('Name'));
	}

	/**
	 * Function which checks Hosts context menu.
	 */
	public function testPageMonitoringWeb_CheckHostContextMenu() {
		$popupitems = [
			'Dashboards', 'Problems', 'Latest data', 'Graphs', 'Web', 'Inventory', 'Host', 'Items', 'Triggers', 'Graphs',
			'Discovery', 'Web', 'Detect operating system', 'Ping', 'Traceroute'
		];

		$this->checkHostContextMenu($popupitems, 'Host for tags testing', 'Graphs');
		$this->checkHostContextMenu($popupitems, self::HOST, 'Dashboards');
		$this->checkHostContextMenu($popupitems, 'Template inheritance test host', 'Dashboards');
	}

	/**
	 * Function for checking the context menu of the selected host.
	 *
	 * @param array		$popupitems		items of the popup window.
	 * @param string	$hostname		name of the host.
	 * @param string	$disabled		disabled host elements.
	 *
	 */
	private function checkHostContextMenu($popupitems, $hostname, $disabled) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=hostname&sortorder=DESC');
		$this->query('class:list-table')->asTable()->one()->findRow('Host', $hostname)->query('link', $hostname)->one()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertEquals(['VIEW', 'CONFIGURATION', 'SCRIPTS'], $popup->getTitles()->asText());
		$this->assertTrue($popup->hasItems($popupitems));
		$this->assertTrue($popup->query('xpath://a[@aria-label="View, ' .
				$disabled . '" and @class="menu-popup-item disabled"]')->one()->isPresent()
		);
		$popup->close();
	}

	public static function getFilterData() {
		return [
			// #0.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FirstTag', 'operator' => 'Exists']
						]
					],
					'expected' => [
						'Scenario for Update'
					]
				]
			],
			// #1.
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'FirstTag', 'operator' => 'Exists'],
							['name' => 'SecondTag', 'operator' => 'Exists'],
							['name' => 'FourthTag', 'operator' => 'Exists']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template_Web_scenario'
					]
				]
			],
			// #2.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FirstTag', 'value' => 'value 1', 'operator' => 'Equals']
						]
					],
					'expected' => [
						'Scenario for Update'
					]
				]
			],
			// #3.
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'FirstTag', 'value' => 'value 1', 'operator' => 'Equals']
						]
					],
					'expected' => [
						'Scenario for Update'
					]
				]
			],
			// #4.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'SecondTag', 'value' => 'value 2', 'operator' => 'Contains'],
							['name' => 'ThirdTag', 'value' => 'value 3', 'operator' => 'Contains']
						]
					],
					'expected' => [
						'Scenario for Delete'
					]
				]
			],
			// #5.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'SecondTag', 'value' => '2', 'operator' => 'Contains'],
							['name' => 'SixthTag', 'value' => 'value 6', 'operator' => 'Equals']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Template_Web_scenario'
					]
				]
			],
			// #6.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FourthTag', 'operator' => 'Does not exist']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template web scenario',
						'Template web scenario 1',
						'Template web scenario 2',
						'Template web scenario with tags for cloning',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web scenario 1st host for widget communication',
						'Web scenario 2nd host for widget communication',
						'Web scenario 3rd host for widget communication',
						'Web scenario for execute now',
						'Web scenario for removing tags',
						'Web scenario with tags for cloning',
						'Web scenario with tags for updating',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #7.
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'FourthTag', 'operator' => 'Does not exist']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template web scenario',
						'Template web scenario 1',
						'Template web scenario 2',
						'Template web scenario with tags for cloning',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web scenario 1st host for widget communication',
						'Web scenario 2nd host for widget communication',
						'Web scenario 3rd host for widget communication',
						'Web scenario for execute now',
						'Web scenario for removing tags',
						'Web scenario with tags for cloning',
						'Web scenario with tags for updating',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #8.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FourthTag', 'value' => 'value 4', 'operator' => 'Does not equal'],
							['name' => 'FifthTag', 'value' => 'value 5', 'operator' => 'Does not equal']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template web scenario',
						'Template web scenario 1',
						'Template web scenario 2',
						'Template web scenario with tags for cloning',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web scenario 1st host for widget communication',
						'Web scenario 2nd host for widget communication',
						'Web scenario 3rd host for widget communication',
						'Web scenario for execute now',
						'Web scenario for removing tags',
						'Web scenario with tags for cloning',
						'Web scenario with tags for updating',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #9.
			[
				[
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'FourthTag', 'value' => 'value 4', 'operator' => 'Does not equal'],
							['name' => 'FifthTag', 'value' => 'value 5', 'operator' => 'Does not equal']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template web scenario',
						'Template web scenario 1',
						'Template web scenario 2',
						'Template web scenario with tags for cloning',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web scenario 1st host for widget communication',
						'Web scenario 2nd host for widget communication',
						'Web scenario 3rd host for widget communication',
						'Web scenario for execute now',
						'Web scenario for removing tags',
						'Web scenario with tags for cloning',
						'Web scenario with tags for updating',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #10.
			[
				[
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FirstTag', 'value' => 'value', 'operator' => 'Does not contain']
						]
					],
					'expected' => [
						'Scenario for Delete',
						'Template web scenario',
						'Template web scenario 1',
						'Template web scenario 2',
						'Template web scenario with tags for cloning',
						'Template_Web_scenario',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web scenario 1st host for widget communication',
						'Web scenario 2nd host for widget communication',
						'Web scenario 3rd host for widget communication',
						'Web scenario for execute now',
						'Web scenario for removing tags',
						'Web scenario with tags for cloning',
						'Web scenario with tags for updating',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #11.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'tag_options' => [
						'type' => 'Or',
						'tags' => [
							['name' => 'FirstTag', 'value' => 'value 6', 'operator' => 'Does not contain'],
							['name' => 'FirstTag', 'value' => '1', 'operator' => 'Does not contain'],
							['name' => 'FirstTag', 'operator' => 'Exists'],
							['name' => 'FirstTag', 'operator' => 'Exists']
						]
					],
					'expected' => [
						'Scenario for Update'
					]
				]
			],
			// #12.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template_Web_scenario',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #13.
			[
				[
					'filter' => [
						'Hosts' => 'Simple form test host'
					],
					'expected' => [
						'Scenario for Delete',
						'Scenario for Update',
						'Template_Web_scenario'
					]
				]
			],
			// #14.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host ZBX6663'
					],
					'expected' => [
						'Web ZBX6663',
						'Web ZBX6663 Second'

					]
				]
			],
			// #15.
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
						'Scenario for Delete',
						'Scenario for Update',
						'Template_Web_scenario',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #16.
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
						'Scenario for Delete',
						'Scenario for Update',
						'Template_Web_scenario',
						'testInheritanceWeb1',
						'testInheritanceWeb2',
						'testInheritanceWeb3',
						'testInheritanceWeb4',
						'Web ZBX6663',
						'Web ZBX6663 Second'
					]
				]
			],
			// #17.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => [
							'Simple form test host'
						]
					],
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FifthTag', 'operator' => 'Does not exist'],
							['name' => 'SecondTag', 'operator' => 'Does not exist']
						]
					],
					'expected' => [
						'Scenario for Update'
					]
				]
			],
			// #18.
			[
				[
					'filter' => [
						'Host groups' => [
							'HostTags'
						],
						'Hosts' => [
							'Host ZBX6663'
						]
					]
				]
			],
			// #19.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers'
					],
					'tag_options' => [
						'type' => 'And/Or',
						'tags' => [
							['name' => 'FirstTag', 'value' => 'value 6', 'operator' => 'Contains']
						]
					]
				]
			]
		];
	}

	/**
	 * Function which checks filtering of Web scenarios.
	 *
	 * @dataProvider getFilterData
	 */
	public function testPageMonitoringWeb_Filter($data) {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=ASC');
		$form = $this->query('name:zbx_filter')->waitUntilPresent()->asForm()->one();
		$table = $this->query('class:list-table')->waitUntilPresent()->one();

		if (CTestArrayHelper::get($data, 'tag_options')) {
			$form->fill(['id:filter_evaltype' => $data['tag_options']['type']]);
			$this->setTags($data['tag_options']['tags']);
		}

		if (CTestArrayHelper::get($data, 'filter')) {
			$form->fill($data['filter']);
		}

		$form->submit();
		$table->waitUntilReloaded();

		if (array_key_exists('expected', $data)) {
			$this->assertTableDataColumn($data['expected']);
		}
		else {
			$this->assertTableData();
		}
	}

	/**
	 * Function which checks number of steps for web services displayed.
	 */
	public function testPageMonitoringWeb_CheckWebServiceNumberOfSteps() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC')->waitUntilReady();
		$row = $this->query('class:list-table')->asTable()->one()->findRow('Name', self::SCENARIO);
		$this->assertEquals('2', $row->getColumn('Number of steps')->getText());

		// Directly open API created Web scenario and add one more step.
		$this->page->open('httpconf.php?context=host&form=update&hostid='.self::$hostid.'&httptestid='.self::$httptestid)
				->waitUntilReady();
		$scenario_form = $this->query('id:webscenario-form')->asForm()->one();
		$scenario_form->selectTab('Steps');
		$scenario_form->getField('Steps')->query('button:Add')->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $this->query('id:webscenario-step-form')->asForm()->one();
		$form->fill(['Name' => 'Step number 3', 'id:url' => 'test.com']);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Web scenario updated');

		// Return to the "Web monitoring" and check if the "Number of steps" is correctly displayed.
		$this->page->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC')->waitUntilReady();
		$this->assertEquals('3', $row->getColumn('Number of steps')->getText());
	}

	/**
	 * Function which checks sorting by Name column.
	 */
	public function testPageMonitoringWeb_CheckSorting() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=hostname&sortorder=ASC');
		$table = $this->query('class:list-table')->asTable()->one();

		foreach (['Host', 'Name'] as $column_name) {
			if ($column_name === 'Name') {
				$table->query('xpath:.//a[text()="'.$column_name.'"]')->one()->click();
			}
			$column_values = $this->getTableColumnData($column_name);

			foreach (['asc', 'desc'] as $sorting) {
				$expected = ($sorting === 'asc') ? $column_values : array_reverse($column_values);
				$this->assertEquals($expected, $this->getTableColumnData($column_name));
				$table->query('xpath:.//a[text()="'.$column_name.'"]')->one()->click();
			}
		}
	}

	/**
	 * Function which checks that title field disappears while Kiosk mode is active.
	 */
	public function testPageMonitoringWeb_CheckKioskMode() {
		$this->page->login()->open('zabbix.php?action=web.view')->waitUntilReady();

		// Check title, filter and table display after pressing Kiosk mode/Normal view.
		foreach (['Kiosk mode', 'Normal view'] as $status) {
			$this->query('xpath://button[@title="'.$status.'"]')->one()->click();
			$this->page->waitUntilReady();

			$header = $this->query('xpath://h1[@id="page-title-general"]');
			$status === 'Kiosk mode'
				? $header->waitUntilNotVisible()
				: $header->waitUntilVisible();

			$this->assertTrue($this->query('xpath://div[@aria-label="Filter"]')->exists());
			$this->assertTrue($this->query('id:flickerfreescreen_httptest')->exists());
		}

		$this->query('xpath://button[@title="Kiosk mode"]')->waitUntilVisible();
	}

	/**
	 * Function which checks if disabled web services aren't displayed.
	 */
	public function testPageMonitoringWeb_CheckDisabledWebServices() {
		$this->page->login()->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC')->waitUntilReady();
		$values = $this->getTableColumnData('Name');

		// Turn off/on web services and check table results.
		foreach (['Disable', 'Enable'] as $status) {
			$this->page->open('httpconf.php?context=host&filter_set=1&filter_hostids%5B0%5D='.self::$hostid)->waitUntilReady();
			$this->query('xpath://input[@id="all_httptests"]')->one()->click();
			$this->query('button', $status)->one()->click();
			$this->page->acceptAlert();

			$this->assertMessage(TEST_GOOD, ($status === 'Disable' ? 'Web scenarios disabled' : 'Web scenarios enabled'));

			$this->page->open('zabbix.php?action=web.view&filter_rst=1&sort=name&sortorder=DESC')->waitUntilReady();
			$changed = ($status === 'Disable')
				? array_diff($values, ['Template_Web_scenario', 'Scenario for Update', 'Scenario for Delete'])
				: array_merge($values, ['Scenario for Clone']);
			$this->assertTableDataColumn($changed);
		}
	}
}
