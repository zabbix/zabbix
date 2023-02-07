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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup scripts
 *
 * @onBefore prepareScriptData
 */
class testPageAlertsScripts extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			'class' => CMessageBehavior::class
		];
	}

	private static $script_sql = 'SELECT * FROM scripts ORDER BY scriptid';

	public static function allScripts() {
		return CDBHelper::getDataProvider('SELECT scriptid,name FROM scripts');
	}

	public function prepareScriptData()
	{
		$response = CDataHelper::call('script.create', [
			[
				'name' => 'Manual event action for filter check',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_EVENT,
				'command' => 'test'
			],
			[
				'name' => 'Script для фильтра - $¢Řĩ₱₮',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_EVENT,
				'command' => '/sbin/run'
			]
		]);
	}

	public function getScriptsData() {
		return [
			[
				[
					'fields' => [
						[
							'Name' => 'Detect operating system',
							'Scope' => 'Manual host action',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => 'sudo /usr/bin/nmap -O {HOST.CONN}',
							'User group' => 'Zabbix administrators',
							'Host group' => 'All',
							'Host access' => 'Read'
						],
						[
							'Name' => 'Manual event action for filter check',
							'Scope' => 'Manual event action',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => 'test',
							'User group' => 'All',
							'Host group' => 'All',
							'Host access' => 'Read'
						],
						[
							'Name' => 'Ping',
							'Scope' => 'Manual host action',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => 'ping -c 3 {HOST.CONN}; case $? in [01]) true;; *) false;; esac',
							'User group' => 'All',
							'Host group' => 'All',
							'Host access' => 'Read'
						],
						[
							'Name' => 'Reboot',
							'Scope' => 'Action operation',
							'Used in actions' => 'Autoregistration action 1, Autoregistration action 2, Trigger action 4',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => '/sbin/shutdown -r',
							'User group' => 'Zabbix administrators',
							'Host group' => 'Zabbix servers',
							'Host access' => 'Write'
						],
						[
							'Name' => 'Script для фильтра - $¢Řĩ₱₮',
							'Scope' => 'Manual event action',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => '/sbin/run',
							'User group' => 'All',
							'Host group' => 'All',
							'Host access' => 'Read'
						],
						[
							'Name' => 'Selenium script',
							'Scope' => 'Action operation',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => 'test',
							'User group' => 'Selenium user group in scripts',
							'Host group' => 'All',
							'Host access' => 'Read'
						],
						[
							'Name' => 'Traceroute',
							'Scope' => 'Manual host action',
							'Used in actions' => '',
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => '/usr/bin/traceroute {HOST.CONN}',
							'User group' => 'All',
							'Host group' => 'All',
							'Host access' => 'Read'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getScriptsData
	 */
	public function testPageAlertsScripts_Layout($data) {
		$scripts_count = count($data['fields']);

		$this->page->login()->open('zabbix.php?action=script.list');
		$this->page->assertTitle('Configuration of scripts');
		$this->page->assertHeader('Scripts');

		// Check status of buttons on the Script page.
		$form_buttons = [
			'Create script' => true,
			'Apply' => true,
			'Reset' => true,
			'Delete' => false
		];
		foreach ($form_buttons as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		// Check displaying and hiding the filter.
		$filter_form = $this->query('name:zbx_filter')->asForm()->one();
		$filter_tab = $this->query('xpath://a[contains(text(), "Filter")]')->one();
		$filter = $filter_form->query('id:tab_0')->one();
		$this->assertTrue($filter->isDisplayed());
		$filter_tab->click();
		$this->assertFalse($filter->isDisplayed());
		$filter_tab->click();
		$this->assertTrue($filter->isDisplayed());

		// Check that all filter fields are present.
		$this->assertEquals(['Name', 'Scope'], $filter_form->getLabels()->asText());

		// Check the count of returned Scripts and the count of selected Scripts.
		$this->assertTableStats($scripts_count);
		$selected_count = $this->query('id:selected_count')->one();
		$this->assertEquals('0 selected', $selected_count->getText());
		$all_scripts = $this->query('id:all_scripts')->asCheckbox()->one();
		$all_scripts->set(true);
		$this->assertEquals($scripts_count.' selected', $selected_count->getText());

		// Check that button became enabled.
		$this->assertTrue($this->query('button:Delete')->one()->isClickable());

		$all_scripts->set(false);
		$this->assertEquals('0 selected', $selected_count->getText());

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers_text = $table->getHeadersText();
		array_shift($headers_text);

		$reference_headers = [
			'Name' => true,
			'Scope' => false,
			'Used in actions' => false,
			'Type' => false,
			'Execute on' => false,
			'Commands' => true,
			'User group' => false,
			'Host group' => false,
			'Host access' => false
		];
		$this->assertSame(array_keys($reference_headers), $headers_text);

		// Check which headers are sortable.
		foreach ($reference_headers as $header => $sortable) {
			$xpath = 'xpath:.//th/a[text()='.CXPathHelper::escapeQuotes($header).']';
			if ($sortable) {
				$this->assertTrue($table->query($xpath)->one()->isClickable());
			}
			else {
				$this->assertFalse($table->query($xpath)->one(false)->isValid());
			}
		}

		// Check Script table contents.
		$this->assertTableData($data['fields']);
	}

	/**
	 * @backup scripts
	 */
	public function testPageAlertsScripts_MassDeleteAll() {
		$this->page->login()->open('zabbix.php?action=script.list');
		$this->query('id:all_scripts')->asCheckbox()->one()->set(true);
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->assertTitle('Configuration of scripts');
		$this->assertMessage(TEST_BAD, 'Cannot delete scripts');
	}

	/**
	 * @dataProvider allScripts
	 * @backupOnce scripts
	 */
	public function testPageAlertsScripts_MassDelete($script) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$this->query('id:scriptids_'.$script['scriptid'])->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->assertTitle('Configuration of scripts');
		if ($script['scriptid'] === '4') {
			$this->assertMessage(TEST_BAD, 'Cannot delete script', 'Cannot delete scripts. Script "Reboot" is used in action operation "Trigger action 4".');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid'])));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Script deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.zbx_dbstr($script['scriptid'])));
		}
	}

	public function getFilterData()
	{
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '$¢Řĩ₱₮'
					],
					'expected' => [
						'Script для фильтра - $¢Řĩ₱₮'
					]
				]
			],
			// Exact match for field Name.
			[
				[
					'filter' => [
						'Name' => 'Traceroute'
					],
					'expected' => [
						'Traceroute'
					]
				]
			],
			// Partial match for field Name.
			[
				[
					'filter' => [
						'Name' => 'Detect'
					],
					'expected' => [
						'Detect operating system'
					]
				]
			],
			// Space in search field Name.
			[
				[
					'filter' => [
						'Name' => ' '
					],
					'expected' => [
						'Detect operating system',
						'Manual event action for filter check',
						'Script для фильтра - $¢Řĩ₱₮',
						'Selenium script'
					]
				]
			],
			// Partial name match with space between.
			[
				[
					'filter' => [
						'Name' => 'm s'
					],
					'expected' => [
						'Selenium script'
					]
				]
			],
			// Partial name match with spaces on the sides.
			[
				[
					'filter' => [
						'Name' => ' operating '
					],
					'expected' => [
						'Detect operating system'
					]
				]
			],
			// Search should not be case sensitive.
			[
				[
					'filter' => [
						'Name' => 'Selenium SCRIPT'
					],
					'expected' => [
						'Selenium script'
					]
				]
			],
			// Wrong name in filter field "Name".
			[
				[
					'filter' => [
						'Name' => 'No data should be returned'
					]
				]
			],
			// Search by Action operation.
			[
				[
					'filter' => [
						'Scope' => 'Action operation'
					],
					'expected' => [
						'Reboot',
						'Selenium script'
					]
				]
			],
			// Search by Action operation and Name.
			[
				[
					'filter' => [
						'Name' => 'Reboot',
						'Scope' => 'Action operation'
					],
					'expected' => [
						'Reboot'
					]
				]
			],
			// Search by Manual host action.
			[
				[
					'filter' => [
						'Scope' => 'Manual host action'
					],
					'expected' => [
						'Detect operating system',
						'Ping',
						'Traceroute'
					]
				]
			],
			// Search by Manual host action and Partial name match.
			[
				[
					'filter' => [
						'Name' => 'ing',
						'Scope' => 'Manual host action'
					],
					'expected' => [
						'Detect operating system',
						'Ping'
					]
				]
			],
			// Search by Manual event action.
			[
				[
					'filter' => [
						'Scope' => 'Manual event action'
					],
					'expected' => [
						'Manual event action for filter check',
						'Script для фильтра - $¢Řĩ₱₮'
					]
				]
			],
			// Search by Manual event action and Partial name match.
			[
				[
					'filter' => [
						'Name' => 'Manual event',
						'Scope' => 'Manual event action'
					],
					'expected' => [
						'Manual event action for filter check'
					]
				]
			],
			// Search Any scripts.
			[
				[
					'filter' => [
						'Scope' => 'Any'
					],
					'expected' => [
						'Detect operating system',
						'Manual event action for filter check',
						'Ping',
						'Reboot',
						'Script для фильтра - $¢Řĩ₱₮',
						'Selenium script',
						'Traceroute'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageAlertsScripts_Filter($data) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		if (array_key_exists('expected', $data)) {
			// Using column Name check that only the expected Hosts are returned in the list.
			$this->assertTableDataColumn($data['expected']);
		}
		else {
			// Check that 'No data found.' string is returned if no results are expected.
			$this->assertTableData();
		}

		// Reset filter due to not influence further tests.
		$this->query('button:Reset')->one()->click();
	}

	public function getSortData() {
		return [
			[
				[
					'sort_field' => 'Name',
					'expected' => [
						'Traceroute',
						'Selenium script',
						'Script для фильтра - $¢Řĩ₱₮',
						'Reboot',
						'Ping',
						'Manual event action for filter check',
						'Detect operating system'
					]
				]
			],
			[
				[
					'sort_field' => 'Commands',
					'expected' => [
						'/sbin/run',
						'/sbin/shutdown -r',
						'/usr/bin/traceroute {HOST.CONN}',
						'ping -c 3 {HOST.CONN}; case $? in [01]) true;; *) false;; esac',
						'sudo /usr/bin/nmap -O {HOST.CONN}',
						'test',
						'test'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSortData
	 */
	public function testPageAlertsScripts_Sort($data) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}
}
