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
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/behaviors/CTableBehavior.php';

/**
 * @backup items
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

	public function testPageLowLevelDiscovery_CheckLayout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check all field names.
		$fields = ['Host groups', 'Hosts', 'Name', 'Key', 'Type', 'Update interval',
				'Keep lost resources period', 'SNMP OID', 'State', 'Status'];
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
						'TELNET agent', 'JMX agent', 'Dependent item', 'all'],
				'State' => ['Normal', 'Not supported', 'all'],
				'Status' => ['all', 'Enabled', 'Disabled']
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
						$this->assertEquals('all', $form->getField('Status')->getText());
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
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame($headers, $table->getHeadersText());

		// Check table buttons.
		foreach (['Enable', 'Disable', 'Execute now', 'Delete'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_ResetButton() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
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
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
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
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);

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
					'disabled' => true,
					'message' => 'Cannot send request',
					'details' => 'Cannot send request: discovery rule "Discovery rule 2" on host "Host for host prototype tests" is not monitored.',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Discovery rule for triggers filtering'
					],
					'message' => 'Cannot send request',
					'details' => 'Cannot send request: wrong discovery rule type.',
					'hostid' => 99062
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Temp Status Discovery'
					],
					'template' => true,
					'hostid' => 10250
				]
			]
		];
	}

	/**
	 * @backup items
	 *
	 * @dataProvider getCheckNowData
	 */
	public function testPageLowLevelDiscovery_CheckNow($data) {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid']);
		// Enabe all LLDs, so Check now can be send successfully.
		$this->massChangeStatus('Enable');
		$this->selectTableRows($data['names']);

		if (CTestArrayHelper::get($data, 'disabled')) {
			$this->query('button:Disable')->one()->click();
			$this->page->acceptAlert();
			$this->assertMessage(TEST_GOOD, 'Discovery rule disabled');
			$this->selectTableRows($data['names']);
		}

		if (CTestArrayHelper::get($data, 'template', false)) {
			$this->assertFalse($this->query('button:Execute now')->one()->isEnabled());
		}
		else {
			$this->query('button:Execute now')->one()->click();
			$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
		}
	}

	/**
	 * Return table data by Name
	 *
	 * @return array
	 */
	private function getTableData() {
		$result = [];

		foreach ($this->query('class:list-table')->asTable()->one()->getRows() as $row) {
			$result[] = $row->getColumn('Name')->getText();
		}

		return $result;
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Templates/SAN'
					],
					'rows' => 22
				]
			],
			[
				[
					'filter' => [
						'Type' => 'Zabbix agent',
						'Name' => 'Containers discovery'
					],
					'expected' => [
						'Containers discovery'
					]
				]
			],
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
			[
				[
					'filter' => [
						'Update interval' => '0'
					],
					'expected' => [
						'Test discovery rule',
						'Test discovery rule'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'Visible host for template linkage',
							'Test item host'
						]
					],
					'expected' => [
						'delete Discovery Rule',
						'Test discovery rule'
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'Visible host for template linkage',
							'Test item host'
						],
						'Key' => 'key'
					],
					'expected' => [
						'delete Discovery Rule'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => [
							'Inheritance test'
						],
						'Hosts' => [
							'Inheritance test template with host prototype'
						]
					],
					'expected' => [
						'Discovery rule for host prototype test'
					]
				]
			],
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
			[
				[
					'filter' => [
						'Key' => 'nodes.discovery'
					],
					'expected' => [
						'Cluster nodes discovery',
						'Nodes discovery',
						'Nodes performance discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'State' => 'Normal'
					],
					'expected' => [
						'Template Module Linux block devices by Zabbix agent: Block devices discovery',
						'Template Module Linux filesystems by Zabbix agent: Mounted filesystem discovery',
						'Template Module Linux network interfaces by Zabbix agent: Network interface discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'Block',
						'State' => 'Normal'
					],
					'expected' => [
						'Template Module Linux block devices by Zabbix agent: Block devices discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Operating systems',
						'Type' => 'Dependent item'
					],
					'rows' => 6
				]
			],
			[
				[
					'filter' => [
						'Type' => 'Zabbix agent',
						'Update interval' => '15m'
					],
					'expected' => [
						'Containers discovery',
						'Images discovery',
						'Replication Discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'Discovery-rule-layout-test-001'
					]
				]
			],
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
			[
				[
					'filter' => [
						'Keep lost resources period' => '50d'
					],
					'expected' => [
						'Discovery-rule-layout-test-001'
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'empty try'
					],
					'expected' => []
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Test item host',
						'Name' => 'Test discovery rule',
						'Key' => 'test',
						'Type' => 'Zabbix agent',
						'Update interval' => '0',
						'Keep lost resources period' => '30d',
						'State' => 'all',
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
		$this->page->login()->open('host_discovery.php?filter_name=&filter_key='.
				'&filter_type=-1&filter_delay=&filter_lifetime=&filter_snmp_oid='.
				'&filter_state=-1&filter_status=-1&filter_set=1');
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		if (array_key_exists('expected', $data)) {
			$this->assertTableDataColumn($data['expected']);
		}

		if (array_key_exists('rows', $data)) {
			$this->assertEquals($data['rows'], $table->getRows()->count());
		}
	}

	private function massChangeStatus($action) {
		$table = $this->query('class:list-table')->asTable()->one();
		$this->query('id:all_items')->asCheckbox()->one()->check();
		$this->query('button', $action)->one()->click();
		$this->page->acceptAlert();
		$string = ($table->getRows()->count() == 1) ? 'Discovery rule ' : 'Discovery rules ';
		$this->assertEquals($string.lcfirst($action).'d', CMessageElement::find()->one()->getTitle());
	}

	public static function getDeleteAllButtonData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'hostid' => self::HOST_ID,
					'filter' => [
						'Hosts' => 'Host for host prototype tests',
						'Keep lost resources period' => ''
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
					'message' => 'Cannot delete discovery rules',
					'details' => 'Cannot delete templated items.',
					'db_count' => 1
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteAllButtonData
	 */
	public function testPageLowLevelDiscovery_DeleteAllButton($data) {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid']);
		// Delete all discovery rules.
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$form->fill($data['filter']);
		$form->submit();
		$this->selectTableRows($data['keys'], 'Key');
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
