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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup items
 *
 * @dataSource ExecuteNowAction, DiscoveredHosts, HostTemplateGroups, AllItemValueTypes, DynamicItemWidgets
 *
 * @onBefore prepareLLDData
 */
class testPageLowLevelDiscovery extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const HOST_ID = 90001;
	const SELECTOR = 'xpath://form[@name="discovery"]/table[contains(@class, "list-table")]';

	public static function prepareLLDData() {
		CDataHelper::createHosts([
			[
				'host' => 'Host with LLD',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'discoveryrules' => [
					[
						'name' => 'Trapper LLD for filter',
						'key_' => 'key_lld_trapper',
						'type' => ITEM_TYPE_TRAPPER
					]
				]
			]
		]);
	}

	public function testPageLowLevelDiscovery_CheckLayout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID.'&context=host');
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check all field names.
		$fields = ['Host groups', 'Hosts', 'Name', 'Key', 'Type', 'Update interval', 'Delete lost resources',
				'Disable lost resources', 'SNMP OID', 'State', 'Status'
		];
		$this->assertEquals($fields, $form->getLabels()->asText());

		// Check filter collapse/expand.
		foreach (['true', 'false'] as $status) {
			$filter_space = $this->query('xpath://div['.CXPathHelper::fromClass('filter-space').']')->one();
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();

			if ($status === 'true') {
				$filter_space->query('id:tab_0')->one()->waitUntilNotVisible();
			}
			else {
				$filter_space->query('id:tab_0')->one()->waitUntilVisible();
			}
		}

		// Check all dropdowns.
		$dropdowns = [
				'Type' => ['Zabbix agent', 'Zabbix agent (active)', 'Simple check',
						'SNMP agent', 'Zabbix internal','Zabbix trapper', 'External check',
						'Database monitor', 'HTTP agent', 'IPMI agent', 'SSH agent',
						'TELNET agent', 'JMX agent', 'Dependent item', 'All'],
				'State' => ['Normal', 'Not supported', 'All'],
				'Status' => ['All', 'Enabled', 'Disabled']
		];
		foreach ($dropdowns as $name => $values) {
			foreach ($values as $value) {
				$form->fill([$name => $value]);
				$this->assertEquals($form->getField($name)->getValue(), $value);
				switch ($value) {
					case 'SNMP agent':
						$this->assertTrue($form->getField('SNMP OID')->isVisible());
						break;
					case 'Zabbix trapper':
						$this->assertFalse($form->getField('Update interval')->isVisible());
						break;
					case 'Normal':
					case 'Not supported':
						$this->assertFalse($form->getField('Status')->isEnabled());
						$this->assertEquals('All', $form->getField('Status')->getText());
						break;
				}
			}
		}

		// Check filter buttons.
		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isPresent());
		}

		// Checking Title, Header and Column names.
		$headers = ['', 'Host', 'Name', 'Items', 'Triggers', 'Graphs', 'Hosts',
				'Key', 'Interval', 'Type', 'Status', 'Info'];
		$this->page->assertTitle('Configuration of discovery rules');
		$this->page->assertHeader('Discovery rules');
		$table = $this->query(self::SELECTOR)->asTable()->one();
		$this->assertSame($headers, $table->getHeadersText());

		// Check table buttons.
		foreach (['Enable', 'Disable', 'Execute now', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_ResetButton() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID.'&context=host');
		$table = $this->query(self::SELECTOR)->asTable()->one();
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertTableStats($start_rows_count);
		$start_contents = $this->getTableData();

		// Filling fields with needed discovery rule info.
		$form->fill(['Name' => 'Discovery rule 3']);
		$form->submit();

		// Check that filtered count matches expected.
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertTableStats(1);

		// Checking that filtered discovery rule matches expected.
		$this->assertEquals(['Discovery rule 3'], $this->getTableData());

		// After pressing reset button, check that previous discovery rules are displayed again.
		$form->query('button:Reset')->one()->click();
		$this->assertEquals($start_rows_count, $table->getRows()->count());
		$this->assertTableStats($table->getRows()->count());
		$this->assertEquals($start_contents, $this->getTableData());
	}

	/**
	 * @backup items
	 */
	public function testPageLowLevelDiscovery_EnableDisableSingle() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID.'&context=host');
		$table = $this->query(self::SELECTOR)->asTable()->one();
		$row = $table->findRow('Name', 'Discovery rule 2');

		// Clicking Enabled/Disabled link
		$discovery_status = ['Enabled' => 1, 'Disabled' => 0];
		foreach ($discovery_status as $action => $expected_status) {
			$row->query('link', $action)->one()->click();
			$status = CDBHelper::getValue('SELECT status FROM items WHERE name='.zbx_dbstr('Discovery rule 2').' and hostid='
				.self::HOST_ID);
			$this->assertEquals($expected_status, $status);
			$message_action = ($action === 'Enabled') ? 'disabled' : 'enabled';
			$this->assertEquals('Discovery rule '.$message_action, CMessageElement::find()->one()->getTitle());
			$link_color = ($action === 'Enabled') ? 'red' : 'green';
			$this->assertTrue($row->query('xpath://td/a[@class="link-action '.$link_color.'"]')->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_EnableDisableAll() {
		$lld_names = ['Discovery rule 1', 'Discovery rule 2', 'Discovery rule 3'];
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID.'&context=host');

		// Press Enable or Disable buttons and check the result.
		foreach (['Disable', 'Enable'] as $action) {
			$this->massChangeStatus($action);
			$expected_status = ($action === 'Disable') ? 1 : 0;
			foreach ($lld_names as $name) {
				$status = CDBHelper::getValue('SELECT status FROM items WHERE name='.zbx_dbstr($name).
					' and hostid='.self::HOST_ID);
				$this->assertEquals($expected_status, $status);
			}
		}
	}

	public static function getCheckNowData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'names' => [
						'Discovery rule 1',
						'Discovery rule 2',
						'Discovery rule 3'
					],
					'message' => 'Request sent successfully',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'names' => [
						'Discovery rule 2'
					],
					'message' => 'Request sent successfully',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Discovery rule 2'
					],
					'type' => 'disabled',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Discovery rule for triggers filtering'
					],
					'type' => 'trapper',
					'hostid' => 99062
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Temp Status Discovery'
					],
					'type' => 'template',
					'hostid' => 10250
				]
			]
		];
	}

	/**
	 * @backupOnce items
	 *
	 * @dataProvider getCheckNowData
	 */
	public function testPageLowLevelDiscovery_CheckNow($data) {
		$context = CTestArrayHelper::get($data, 'type') === 'template' ? '&context=template' : '&context=host';
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid'].$context);
		// Enable all LLDs, so Execute now can be sent successfully.
		$this->massChangeStatus('Enable');
		$this->selectTableRows($data['names'], 'Name', self::SELECTOR);

		switch (CTestArrayHelper::get($data, 'type')) {
			case 'disabled':
				$this->query('button:Disable')->one()->click();
				$this->page->acceptAlert();
				$this->selectTableRows($data['names'], 'Name', self::SELECTOR);
				$this->assertFalse($this->query('button:Execute now')->one()->isEnabled());
				break;
			case 'template';
				$this->assertFalse($this->query('button:Execute now')->one(false)->isValid());
				break;
			case 'trapper';
				$this->assertFalse($this->query('button:Execute now')->one()->isEnabled());
				break;
			default:
				$this->query('button:Execute now')->one()->click();
				$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
		}

		$this->page->logout();
	}

	/**
	 * Return table data by Name
	 *
	 * @return array
	 */
	private function getTableData() {
		$result = [];

		foreach ($this->query(self::SELECTOR)->asTable()->one()->getRows() as $row) {
			$result[] = $row->getColumn('Name')->getText();
		}

		return $result;
	}

	public static function getFilterData() {
		return [
			// #0.
			[
				[
					'filter' => [
						'Template groups' => 'Templates/Databases'
					],
					'context' => 'template',
					'rows' => 100
				]
			],
			// #1.
			[
				[
					'filter' => [
						'Type' => 'Zabbix agent',
						'Name' => 'Containers discovery'
					],
					'context' => 'template',
					'expected' => [
						'Containers discovery'
					]
				]
			],
			// #2.
			[
				[
					'filter' => [
						'Hosts' => [
							'Simple form test host'
						]
					],
					'expected' => [
						'testFormDiscoveryRule',
						'testFormDiscoveryRule1',
						'testFormDiscoveryRule2',
						'testFormDiscoveryRule3',
						'testFormDiscoveryRule4'
					]
				]
			],
			// #3.
			[
				[
					'filter' => [
						'Name' => 'testFormDiscoveryRule2'
					],
					'expected' => [
						'testFormDiscoveryRule2'
					]
				]
			],
			// #4.
			[
				[
					'filter' => [
						'Update interval' => '0'
					],
					'expected' => [
						'Test discovery rule'
					]
				]
			],
			// #5.
			[
				[
					'filter' => [
						'Hosts' => [
							'Host with LLD',
							'Test item host'
						]
					],
					'expected' => [
						'Test discovery rule',
						'Trapper LLD for filter'
					]
				]
			],
			// #6.
			[
				[
					'filter' => [
						'Hosts' => [
							'Host with LLD',
							'Test item host'
						],
						'Key' => 'key'
					],
					'expected' => [
						'Trapper LLD for filter'
					]
				]
			],
			// #7.
			[
				[
					'filter' => [
						'Template groups' => [
							'Inheritance test'
						],
						'Templates' => [
							'Inheritance test template with host prototype'
						]
					],
					'context' => 'template',
					'expected' => [
						'Discovery rule for host prototype test'
					]
				]
			],
			// #8.
			[
				[
					'filter' => [
						'Host groups' => [
							'Zabbix servers'
						],
						'Name' => 'DiscoveryRule ZBX6663'
					],
					'expected' => [
						'DiscoveryRule ZBX6663',
						'Template ZBX6663 Second: DiscoveryRule ZBX6663 Second'
					]
				]
			],
			// #9.
			[
				[
					'filter' => [
						'Key' => 'array.cache.discovery',
						'Type' => 'HTTP agent'
					],
					'context' => 'template',
					'expected' => [
						'Array controller cache discovery',
						'Array controller cache discovery',
						'Array controller cache discovery',
						'Array controller cache discovery'
					]
				]
			],
			// #10.
			[
				[
					'filter' => [
						'State' => 'Normal'
					],
					'expected' => [
						'1st LLD',
						'2nd LLD',
						'3rd LLD',
						'12th LLD',
						'15th LLD 🙃^天!',
						'16th LLD',
						'17th LLD',
						'Linux by Zabbix agent: Block devices discovery',
						'DR1-agent',
						'DR2-trap',
						'I1-lvl1-agent-num: DR3-I1-dep-agent',
						'I2-lvl1-trap-num: DR4-I2-dep-trap',
						'Last error message of scenario "Web scenario for execute now".: DR5-web-dep',
						'Dynamic widgets H1D1',
						'Dynamic widgets H2D1',
						'Eleventh LLD',
						'fifth LLD',
						'forth LLD',
						'Zabbix server health: Zabbix stats cluster: High availability cluster node discovery',
						'LLD for Discovered host tests',
						'LLD for host group test',
						'LLD number 8',
						'LLD rule for item types',
						'LLD 🙂🙃 !@#$%^&*()_+ 祝你今天过得愉快',
						'Linux by Zabbix agent: Get filesystems: Mounted filesystem discovery',
						'Mūsu desmitais LLD',
						'Linux by Zabbix agent: Network interface discovery',
						'sevenths LLD',
						'sixth LLD',
						'Test of discovered host 1 template for unlink: Template1 discovery rule',
						'Test of discovered host 2 template for clear: Template2 discovery rule',
						'Test of discovered host Template: Template discovery rule',
						'Trapper LLD for filter',
						'Trīspadsmitais LLD',
						'Zabbix server health: Zabbix proxies stats: Zabbix proxy discovery',
						'Zabbix server health: Zabbix proxy groups stats: Zabbix proxy groups discovery',
						'Četrpadsmitais LLD'
					]
				]
			],
			// #11.
			[
				[
					'filter' => [
						'Name' => 'Block',
						'State' => 'Normal'
					],
					'expected' => [
						'Linux by Zabbix agent: Block devices discovery'
					]
				]
			],
			// #12.
			[
				[
					'filter' => [
						'Template groups' => 'Templates/Operating systems',
						'Type' => 'Dependent item'
					],
					'context' => 'template',
					'rows' => 23
				]
			],
			// #13.
			[
				[
					'filter' => [
						'Type' => 'Database monitor',
						'Update interval' => '1h',
						'Name'=> 'Database'
					],
					'context' => 'template',
					'expected' => [
						'Database discovery'
					]
				]
			],
			// #14.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'context' => 'template',
					'expected' => [
						'Discovery-rule-layout-test-001'
					]
				]
			],
			// #15.
			[
				[
					'filter' => [
						'Status' => 'Enabled',
						'Name' => 'Discovery-rule'
					],
					'expected' => [
						'Discovery-rule-layout-test-002'
					]
				]
			],
			// #16.
			[
				[
					'filter' => [
						'Name' => 'empty try'
					],
					'expected' => []
				]
			],
			// #17.
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Test item host',
						'Name' => 'Test discovery rule',
						'Key' => 'test',
						'Type' => 'Zabbix agent',
						'Update interval' => '0',
						'State' => 'All',
						'Status' => 'Enabled'
					],
					'expected' => [
						'Test discovery rule'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageLowLevelDiscovery_Filter($data) {
		$context = CTestArrayHelper::get($data, 'context', 'host');
		$this->page->login()->open('host_discovery.php?filter_name=&sortorder=ASC&filter_key='.
				'&filter_type=-1&filter_delay=&filter_lifetime=&filter_snmp_oid='.
				'&filter_state=-1&filter_status=-1&filter_set=1&context='.$context);
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$table = $this->query(self::SELECTOR)->asTable()->one();

		if (array_key_exists('expected', $data)) {
			$this->assertTableDataColumn($data['expected'], 'Name', self::SELECTOR);
		}

		if (array_key_exists('rows', $data)) {
			$this->assertEquals($data['rows'], $table->getRows()->count());
		}
	}

	private function massChangeStatus($action) {
		$table = $this->query(self::SELECTOR)->asTable()->one();
		$this->query('id:all_items')->asCheckbox()->one()->check();
		$this->query('button', $action)->one()->click();
		$this->page->acceptAlert();
		$string = ($table->getRows()->count() == 1) ? 'Discovery rule ' : 'Discovery rules ';
		$this->assertEquals($string.lcfirst($action).'d', CMessageElement::find()->one()->getTitle());
		CMessageElement::find()->one()->close();
	}

	public static function getDeleteAllButtonData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'hostid' => self::HOST_ID,
					'filter' => [
						'Hosts' => 'Host for host prototype tests'
					],
					'keys' => [
						'key1',
						'key2',
						'key3'
					],
					'message' => 'Discovery rules deleted',
					'db_count' => 0
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'hostid' => 50001,
					'filter' => [
						'Hosts' => 'Host ZBX6663',
						'Key' => 'drule-ZBX6663-second'
					],
					'keys' => [
						'drule-ZBX6663-second'
					],
					'message' => 'Cannot delete discovery rule',
					'details' => 'Invalid parameter "/1": cannot delete inherited LLD rule.',
					'db_count' => 1
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteAllButtonData
	 */
	public function testPageLowLevelDiscovery_DeleteAllButton($data) {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid'].'&context=host');
		// Delete all discovery rules.
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter']);
		$form->submit();
		$this->selectTableRows($data['keys'], 'Key', self::SELECTOR);
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));

		foreach ($data['keys'] as $key) {
			$count = CDBHelper::getCount('SELECT status FROM items WHERE key_='
					.zbx_dbstr($key).' and hostid='.zbx_dbstr($data['hostid']));
			$this->assertEquals($data['db_count'], $count);
		}
	}
}
