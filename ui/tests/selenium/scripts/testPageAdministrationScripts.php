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
class testPageAdministrationScripts extends CWebTest {

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
	private static $custom_script = 'Custom script with linked action';
	private static $script_for_filter = 'Script для фильтра - $¢Řĩ₱₮';
	private static $script_scope_event = 'Manual event action for filter check';
	private static $custom_action = 'Trigger action for Scripts page testing';

	public function prepareScriptData()
	{
		$response = CDataHelper::call('script.create', [
			[
				'name' => self::$script_scope_event,
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_EVENT,
				'command' => 'test'
			],
			[
				'name' => self::$script_for_filter,
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_EVENT,
				'command' => '/sbin/run'
			],
			[
				'name' => self::$custom_script,
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'command' => '/sbin/zabbix_server --runtime-control config_cache_reload',
				'groupid' => '4',
				'description' => 'This command reload cache.'
			]
		]);
		$scriptids = CDataHelper::getIds('name');

		// Create trigger action
		CDataHelper::call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'name' =>  self::$custom_action,
			'operations' => [
				[
					'esc_period' => '0',
					'esc_step_from' => '1',
					'esc_step_to' => '1',
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'scriptid' => $scriptids[self::$custom_script]
					],
					'opcommand_hst' => [
						[
							'hostid'=> '0'
						]
					]
				]
			]
		]);
	}

	public function getScriptsData() {
		return [
			[
				[
					'fields' => [
						[
							'Name' => self::$custom_script,
							'Scope' => 'Action operation',
							'Used in actions' => self::$custom_action,
							'Type' => 'Script',
							'Execute on' => 'Server (proxy)',
							'Commands' => '/sbin/zabbix_server --runtime-control config_cache_reload',
							'User group' => 'All',
							'Host group' => 'Zabbix servers',
							'Host access' => 'Read'
						],
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
							'Name' => self::$script_scope_event,
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
							'Name' => self::$script_for_filter,
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
	public function testPageAdministrationScripts_Layout($data) {
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

		// Check Script table content.
		$this->assertTableData($data['fields']);
	}

	public function getFilterData() {
		return [
			// Name with special symbols.
			[
				[
					'filter' => [
						'Name' => '$¢Řĩ₱₮'
					],
					'expected' => [
						self::$script_for_filter
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
						self::$custom_script,
						'Detect operating system',
						self::$script_scope_event,
						self::$script_for_filter,
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
						self::$custom_script,
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
						self::$custom_script,
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
						self::$script_scope_event,
						self::$script_for_filter
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
						self::$script_scope_event
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
						self::$custom_script,
						'Detect operating system',
						self::$script_scope_event,
						'Ping',
						'Reboot',
						self::$script_for_filter,
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
	public function testPageAdministrationScripts_Filter($data) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		if (array_key_exists('expected', $data)) {
			// Using column Name check that only the expected Scripts are returned in the list.
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
						self::$script_for_filter,
						'Reboot',
						'Ping',
						self::$script_scope_event,
						'Detect operating system',
						self::$custom_script
					]
				]
			],
			[
				[
					'sort_field' => 'Commands',
					'expected' => [
						'/sbin/run',
						'/sbin/shutdown -r',
						'/sbin/zabbix_server --runtime-control config_cache_reload',
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
	public function testPageAdministrationScripts_Sort($data) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$table = $this->query('class:list-table')->asTable()->one();
		$header = $table->query('xpath:.//a[text()="'.$data['sort_field'].'"]')->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}

	public function testPageAdministrationScripts_Delete() {
		$this->page->login()->open('zabbix.php?action=script.list');

		foreach ([self::$script_scope_event, self::$custom_script, self::$script_for_filter] as $scripts) {
			$this->selectTableRows($scripts);
			$this->query('button:Delete')->one()->waitUntilClickable()->click();
			$this->page->acceptAlert();
			$this->page->waitUntilReady();

			if ($scripts === self::$custom_script) {
				// Verify that selected script which is linked to an action can't be deleted.
				$count = CDBHelper::getCount('SELECT scriptid FROM scripts');
				$this->assertMessage(TEST_BAD, 'Cannot delete script', 'Cannot delete scripts. Script "'
					.$scripts.'" is used in action operation "'.self::$custom_action);
				$this->assertEquals(1, CDBHelper::getCount('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr($scripts)));

				// Verify that there is no possibility to delete all selected scripts if at least one of them contains linked action.
				$this->query('id:all_scripts')->asCheckbox()->one()->set(true);
				$this->query('button:Delete')->one()->click();
				$this->page->acceptAlert();
				$this->assertMessage(TEST_BAD, 'Cannot delete scripts');
				$this->assertEquals($count, CDBHelper::getCount('SELECT scriptid FROM scripts'));

				// Uncheck selected scripts due to not influence further tests.
				$this->query('button:Reset')->one()->click();
			}
			else {
				$this->assertMessage(TEST_GOOD, 'Script deleted');
				$this->assertEquals(0, CDBHelper::getCount('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr($scripts)));
			}
		}
	}

	/**
	 * Verify that there is possibility to open 'action' modal popup via link located in 'Used in actions' tab.
	 */
	public function testPageAdministrationScripts_ActionLinks() {
		$this->page->login()->open('zabbix.php?action=script.list');
		$this->query('link:'.self::$custom_action)->one()->waitUntilClickable()->click();
		$form = $this->query('id:action-form')->asForm()->waitUntilVisible()->one();
		$this->assertEquals(self::$custom_action, $form->getField('Name')->getValue());
		$form->query('button:Cancel')->one()->click();
		$this->page->assertHeader('Trigger actions');
	}
}
