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
require_once dirname(__FILE__).'/behaviors/MessageBehavior.php';

/**
 * @backup items
 */
class testPageLowLevelDiscovery extends CWebTest {

	use TableTrait;

	const HOST_ID = 90001;

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

	public function testPageLowLevelDiscovery_CheckLayout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$headers = ['', 'Host', 'Name', 'Items', 'Triggers', 'Graphs', 'Hosts',
			'Key', 'Interval', 'Type', 'Status', 'Info'];
		$fields = ['Host groups', 'Hosts', 'Name', 'Key', 'Type', 'Update interval',
			'Keep lost resources period', 'SNMP OID', 'State', 'Status'];
		$dropdowns = [
			'Type' => ['Zabbix agent', 'Zabbix agent (active)', 'Simple check', 'SNMP agent', 'Zabbix internal',
					'Zabbix trapper', 'External check','Database monitor', 'HTTP agent', 'IPMI agent', 'SSH agent',
					'TELNET agent', 'JMX agent', 'Dependent item', 'all'],
			'State' => ['Normal', 'Not supported', 'all'],
			'Status' => ['all', 'Enabled', 'Disabled']
		];
		$buttons = ['Apply', 'Reset'];
		$buttons_name = ['Enable', 'Disable', 'Execute now', 'Delete'];

		// Check all field names.
		$labels = $form->getLabels()->asText();
		$this->assertEquals($fields, $labels);

		// Check filter collapse/expand.
		$filter_expanded = ['true', 'false'];
		foreach ($filter_expanded as $status) {
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check all dropdowns.
		foreach ($dropdowns as $name => $values) {
			foreach ($values as $value) {
				$form->fill([$name => $value]);
				$this->assertEquals($form->getField($name)->getValue(), $value);
				switch ($value):
					case 'SNMP agent':
						$this->assertTrue($form->query('id:filter_snmp_oid')->one()->isVisible());
						break;
					case 'Zabbix trapper':
						$this->assertFalse($form->query('id:filter_delay_row')->one()->isVisible());
						break;
					case 'Normal':
					case 'Not supported':
						$this->assertFalse($form->query('id:filter_status')->one()->isEnabled());
						break;
				endswitch;
			}
		}

		// Check filter buttons.
		foreach ($buttons as $button) {
			$this->assertTrue($form->query('button', $button)->one()->isPresent());
		}

		// Checking Title, Header and Column names.
		$this->assertPageTitle('Configuration of discovery rules');
		$this->assertPageHeader('Discovery rules');
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertSame($headers, $table->getHeadersText());

		// Check table buttons.
		foreach ($buttons_name as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_ResetButton() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->one()->asForm();
		$filtered_contents = ['Discovery rule 3'];

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->assertDisplayingText($start_rows_count);
		$start_contents = $this->getTableResult($start_rows_count);

		// Filling fields with needed discovery rule info.
		$form->fill(['Name' => 'Discovery rule 3']);
		$form->submit();

		// Check that filtered count mathces expected.
		$this->assertEquals(count($filtered_contents), $table->getRows()->count());
		$this->assertDisplayingText(count($filtered_contents));

		// Checking that filtered discovery rule matches expected.
		$this->assertEquals($filtered_contents, $this->getTableResult(count($filtered_contents)));

		// After pressing reset button, check that previous discovery rules are displayed again.
		$form->query('button:Reset')->one()->click();
		$reset_rows_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_rows_count);
		$this->assertDisplayingText($reset_rows_count);
		$this->assertEquals($start_contents, $this->getTableResult($reset_rows_count));
	}

	/**
	 * @backup items
	 */
	public function testPageLowLevelDiscovery_EnableDisableSingle() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
		$name = 'Discovery rule 2';
		$row = $table->findRow('Name', $name);

		// Clicking Enabled/Disabled link
		$discovery_status = ['Enabled' => 1, 'Disabled' => 0];
		foreach ($discovery_status as $action => $expected_status) {
			$row->query('link', $action)->one()->click();
			$status = CDBHelper::getValue('SELECT status FROM items WHERE name ='.zbx_dbstr($name).' and hostid ='
				.self::HOST_ID);
			$this->assertEquals($expected_status, $status);
			$message_action = ($action === 'Enabled') ? 'disabled' : 'enabled';
			$this->assertEquals('Discovery rule '.$message_action, CMessageElement::find()->one()->getTitle());
			$link_color = ($action === 'Enabled') ? 'red' : 'green';
			$this->assertTrue($row->query('xpath://td/a[@class="link-action '.$link_color.'"]')->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_EnableDisableAll() {
		$discovery_rule_names = ['Discovery rule 1', 'Discovery rule 2', 'Discovery rule 3'];
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);

		// Press Enable or Disable buttons and check the result.
		$actions = ['Disable', 'Enable'];
		foreach ($actions as $action) {
			$this->massChangeStatus($action);
			$expected_status = ($action === 'Disable') ? 1 : 0;
			foreach ($discovery_rule_names as $name) {
				$status = CDBHelper::getValue('SELECT status FROM items WHERE name ='.zbx_dbstr($name).
					' and hostid ='.self::HOST_ID);
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
						['Name' => 'Discovery rule 1'],
						['Name' => 'Discovery rule 2'],
						['Name' => 'Discovery rule 3']
					],
					'message' => 'Request sent successfully',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'names' => [
						'Name' => 'Discovery rule 2'
					],
					'message' => 'Request sent successfully',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Name' => 'Discovery rule 2'
					],
					'disabled' => true,
					'message' => 'Cannot send request',
					'details' => 'Cannot send request: discovery rule is disabled.',
					'hostid' => self::HOST_ID
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => [
						'Name' => 'Discovery rule for triggers filtering'
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
						'Name' => 'Temp Status Discovery'
					],
					'message' => 'Cannot send request',
					'details' => 'Cannot send request: host is not monitored.',
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
			$this->selectTableRows($data['names']);
		}
		$this->query('button:Execute now')->one()->click();
		$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
	}

	private function getTableResult($rows_count) {
		$table = $this->query('class:list-table')->asTable()->one();
		$result = [];
		for ($i = 0; $i < $rows_count; $i ++) {
			$result[] = $table->getRow($i)->getColumn('Name')->getText();
		}
		return $result;
	}

	private function assertDisplayingText($count) {
		$this->assertEquals('Displaying '.$count.' of '.$count.' found',
			$this->query('xpath://div[@class="table-stats"]')->one()->getText());
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Virtualization'
					],
					'rows' => 8
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
						'Name' => 'testFormDiscoveryRule2',
					],
					'expected' => [
						'testFormDiscoveryRule2'
					]
				]
			],
			[
				[
					'filter' => [
						'Update interval' => '0',
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
						'Key' => 'array.cache.discovery'
					],
					'expected' => [
						'Array Controller Cache Discovery',
						'Array Controller Cache Discovery',
						'Array Controller Cache Discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'State' => 'Normal'
					],
					'expected' => [
						'Template Module Linux block devices by Zabbix agent: Get /proc/diskstats:'
						. ' Block devices discovery',
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
						'Template Module Linux block devices by Zabbix agent: Get /proc/diskstats:'
						. ' Block devices discovery'
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Operating systems',
						'Type' => 'Dependent item'
					],
					'rows' => 10
				]
			],
			[
				[
					'filter' => [
						'Type' => 'Database monitor',
						'Update interval' => '1h'
					],
					'expected' => [
						'Databases discovery',
						'Replication discovery'
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
						'Name' => 'try'
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
		$this->page->login()->open('host_discovery.php?filter_name=&filter_key='
			. '&filter_type=-1&filter_delay=&filter_lifetime=&filter_snmp_oid='
			. '&filter_state=-1&filter_status=-1&filter_set=1');
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
					'keys' =>[
						['Key' => 'key1'],
						['Key' => 'key2'],
						['Key' => 'key2']
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
						['Key' => 'drule-ZBX6663-second']
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
		$this->selectTableRows($data['keys']);
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage($data['expected'], $data['message'], CTestArrayHelper::get($data, 'details'));
		foreach ($data['keys'] as ['Key' => $key]) {
			$count = CDBHelper::getCount('SELECT status FROM items WHERE key_ ='
				.zbx_dbstr($key).' and hostid ='.zbx_dbstr($data['hostid']));
			$this->assertEquals($data['db_count'], $count);
		}
	}
}
