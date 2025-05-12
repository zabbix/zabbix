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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup scripts
 *
 * @dataSource HostTemplateGroups, Actions
 *
 * @onBefore prepareScriptData
 */
class testPageAlertsScripts extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTableBehavior::class,
				'column_names' => ['', 'Name', 'Scope', 'Count', 'Used in actions', 'Type', 'Execute on', 'Commands',
					'User group', 'Host group', 'Host access'
				]
			]
		];
	}

	/**
	 * Script created in dataSource HostTemplateGroups.
	 */
	const HOST_GROUP_SCRIPT = 'Script for host group testing';

	private static $script_sql = 'SELECT * FROM scripts ORDER BY scriptid';
	private static $custom_script = 'Custom script with linked action';
	private static $script_for_filter = 'Script для фильтра - $¢Řĩ₱₮';
	private static $script_scope_event = 'Manual event action for filter check';
	private static $custom_action = 'Trigger action for Scripts page testing';

	public function prepareScriptData() {
		CDataHelper::call('script.create', [
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
					[
						'Name' => self::$custom_script,
						'Scope' => 'Action operation',
						'Count' => '1',
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
						'Count' => '',
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
						'Count' => '',
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
						'Count' => '',
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
						'Count' => '3',
						'Used in actions' => 'Autoregistration action 1, Autoregistration action 2, Minimal trigger action',
						'Type' => 'Script',
						'Execute on' => 'Server (proxy)',
						'Commands' => '/sbin/shutdown -r',
						'User group' => 'All',
						'Host group' => 'Zabbix servers',
						'Host access' => 'Read'
					],
					[
						'Name' => self::HOST_GROUP_SCRIPT,
						'Scope' => 'Action operation',
						'Count' => '',
						'Used in actions' => '',
						'Type' => 'Webhook',
						'Execute on' => '',
						'Commands' => '',
						'User group' => 'All',
						'Host group' => 'Group for Script',
						'Host access' => 'Read'
					],
					[
						'Name' => self::$script_for_filter,
						'Scope' => 'Manual event action',
						'Count' => '',
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
						'Count' => '',
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
						'Count' => '',
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
		];
	}

	/**
	 * @dataProvider getScriptsData
	 */
	public function testPageAlertsScripts_Layout($data) {
		$scripts_count = count($data);

		$this->page->login()->open('zabbix.php?action=script.list');
		$this->page->assertTitle('Configuration of scripts');
		$this->page->assertHeader('Scripts');

		// Check buttons on the Script page.
		$this->assertEquals(3, $this->query('button', ['Create script', 'Apply', 'Reset'])
				->all()->filter(CElementFilter::CLICKABLE)->count()
		);
		$this->assertFalse($this->query('button:Delete')->one()->isClickable());

		// Check displaying and hiding the filter.
		$filter = CFilterElement::find()->one();
		$filter_form = $filter->getForm();
		$this->assertEquals('Filter', $filter->getSelectedTabName());
		// Check that filter is expanded by default.
		$this->assertTrue($filter->isExpanded());
		// Check that filter is collapsing/expanding on click.
		foreach ([false, true] as $status) {
			$filter->expand($status);
			$this->assertTrue($filter->isExpanded($status));
		}

		// Check filter labels and default values.
		$this->assertEquals(['Name', 'Scope'], $filter_form->getLabels()->asText());
		$filter_form->checkValue(['Name' => '', 'Scope' => 'Any']);
		$this->assertEquals('255', $filter_form->getField('Name')->getAttribute('maxlength'));

		// Check the count of returned Scripts and the count of selected Scripts.
		$this->assertTableStats($scripts_count);
		$this->assertSelectedCount(0);
		$all_scripts = $this->query('id:all_scripts')->asCheckbox()->one();
		$all_scripts->check();
		$this->assertSelectedCount($scripts_count);

		// Check that button became enabled.
		$this->assertTrue($this->query('button:Delete')->one()->isClickable());

		$all_scripts->uncheck();
		$this->assertSelectedCount(0);

		// Check table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals(['', 'Name', 'Scope', 'Used in actions', 'Type', 'Execute on', 'Commands', 'User group',
			'Host group', 'Host access'], $table->getHeadersText()
		);

		// Check sortable headers.
		$this->assertEquals(['Name', 'Commands'], $table->getSortableHeaders()->asText());

		// Check Script table content.
		$this->assertTableHasData($data);

		// Check that the filter is still expanded after page refresh.
		$this->page->refresh()->waitUntilReady();
		$this->assertTrue($filter->isExpanded());
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
						self::HOST_GROUP_SCRIPT,
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
						self::HOST_GROUP_SCRIPT,
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
						self::HOST_GROUP_SCRIPT,
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
	public function testPageAlertsScripts_Filter($data) {
		$this->page->login()->open('zabbix.php?action=script.list');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();

		// Fill filter fields if such present in data provider.
		$form->fill(CTestArrayHelper::get($data, 'filter'));
		$form->submit();
		$this->page->waitUntilReady();

		// Check that expected Scripts are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected', []));

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
						self::HOST_GROUP_SCRIPT,
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
						// TODO: fix order after fix ZBX-22329
						'',
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
		$header = $table->query('link', $data['sort_field'])->one();

		foreach(['desc', 'asc'] as $sorting) {
			$expected = ($sorting === 'desc') ? $data['expected'] : array_reverse($data['expected']);
			$header->click();
			$this->assertTableDataColumn($expected, $data['sort_field']);
		}
	}

	public function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'error' => 'Cannot delete scripts. Script "Reboot" is used in action operation "Minimal trigger action".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => [
						self::$custom_script
					],
					'error' => 'Cannot delete scripts. Script "'.self::$custom_script.
							'" is used in action operation "'.self::$custom_action.'".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => [
						self::$custom_script,
						self::$script_for_filter
					],
					'error' => 'Cannot delete scripts. Script "'.self::$custom_script.
							'" is used in action operation "'.self::$custom_action.'".'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => [
						self::$script_scope_event
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => [
						self::$script_for_filter,
						'Ping'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testPageAlertsScripts_Delete($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::$script_sql);
		}

		$this->page->login()->open('zabbix.php?action=script.list');

		// Scripts count that will be selected before delete action.
		$scripts_count = (array_key_exists('name', $data))
				? count($data['name'])
				: CDBHelper::getCount(self::$script_sql);
		$this->selectTableRows(CTestArrayHelper::get($data, 'name'));
		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		// Verify that there is no possibility to delete selected script(s) if at least one of them contains linked action.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot delete script'.(($scripts_count > 1) ? 's' : ''), $data['error']);
			$this->assertSelectedCount($scripts_count);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$script_sql));

			// Uncheck selected scripts due to not influence further tests.
			$this->query('button:Reset')->one()->click();
		}
		else {
			$this->assertMessage(TEST_GOOD, ($scripts_count > 1) ? 'Scripts deleted' : 'Script deleted');
			$this->assertSelectedCount(0);
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name IN ('.
					CDBHelper::escape($data['name']).')')
			);
		}
	}

	public function testPageAlertsScripts_CancelDelete() {
		$this->cancelDelete([self::$custom_script]);
	}

	public function testPageAlertsScripts_CancelMassDelete() {
		$this->cancelDelete();
	}

	/**
	 * Function for checking cancelling of Delete action.
	 *
	 * @param array $scripts      script names, if empty delete will perform for all scripts
	 */
	private function cancelDelete($scripts = []) {
		$old_hash = CDBHelper::getHash(self::$script_sql);

		$this->page->login()->open('zabbix.php?action=script.list');
		$this->selectTableRows($scripts);

		// Scripts count that will be selected before delete action.
		$scripts_count = ($scripts === []) ? CDBHelper::getCount(self::$script_sql) : count($scripts);

		$this->query('button:Delete')->one()->waitUntilClickable()->click();
		$this->assertEquals(($scripts_count > 1) ? 'Delete selected scripts?' : 'Delete selected script?', $this->page->getAlertText());
		$this->page->dismissAlert();
		$this->page->waitUntilReady();

		$this->assertSelectedCount($scripts_count);
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$script_sql));
	}

	/**
	 * Verify that there is possibility to open 'action' modal popup via link located in 'Used in actions' tab.
	 */
	public function testPageAlertsScripts_ActionLinks() {
		$this->page->login()->open('zabbix.php?action=script.list');
		$this->query('link', self::$custom_action)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$dialog->checkValue(['Name' => self::$custom_action]);
		$dialog->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->page->assertHeader('Scripts');
		$this->assertMessage(TEST_GOOD, 'Action updated');
	}
}
