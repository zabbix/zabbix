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

/**
 * @backup items
 */
class testPageLowLevelDiscovery extends CWebTest {

	const HOST_ID = 90001;
	private $discovery_rule_names = ['Discovery rule 1', 'Discovery rule 2', 'Discovery rule 3'];

	public function testPageLowLevelDiscovery_CheckLayout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check all field names.
		$fields = ['Host groups', 'Hosts', 'Name', 'Key', 'Type', 'Update interval',
			'Keep lost resources period', 'SNMP OID', 'State', 'Status'];
		$labels = $form->getLabels()->asText();
		$this->assertEquals($fields, $labels);

		// Check filter collapse/expand.
		$filter_expanded = ['true', 'false'];
		foreach ($filter_expanded as $status){
			$filter_tab = $this->query('xpath://a[contains(@class, "filter-trigger")]')->one();
			$filter_tab->parents('xpath:/li[@aria-expanded="'.$status.'"]')->one()->click();
		}

		// Check all dropdowns.
		$dropdowns = [
			'Type' => ['all','Zabbix agent', 'Zabbix agent (active)', 'Simple check', 'SNMP agent', 'Zabbix internal', 'Zabbix trapper', 'External check',
					'Database monitor', 'HTTP agent', 'IPMI agent', 'SSH agent', 'TELNET agent', 'JMX agent', 'Dependent item'],
			'State' => ['Normal', 'Not supported', 'all'],
			'Status' => ['all', 'Enabled', 'Disabled']
		];

		foreach ($dropdowns as $name => $values) {
			foreach ($values as $value) {
				$form->fill([$name => $value]);
				$form->submit();
				$form->invalidate();
				$this->assertEquals($form->getField($name)->getValue(), $value);
			}
		}

		// Check filter buttons.
		$buttons = ['Apply', 'Reset'];
		foreach ($buttons as $button){
			$this->assertTrue($form->query('button:'.$button)->one()->isPresent());
		}

		// Checking Title, Header and Column names.
		$this->assertPageTitle('Configuration of discovery rules');
		$title = $this->query('xpath://h1[@id="page-title-general"]')->one()->getText();
		$this->assertEquals('Discovery rules', $title);

		$table = $this->query('class:list-table')->asTable()->one();
		$headers = ['', 'Host', 'Name', 'Items', 'Triggers', 'Graphs', 'Hosts', 'Key', 'Interval', 'Type', 'Status', 'Info'];
		$this->assertSame($headers, $table->getHeadersText());

		// Check table buttons.
		$buttons_name = ['Enable', 'Disable', 'Execute now', 'Delete'];
		foreach ($buttons_name as $button) {
			$this->assertTrue($this->query('button:'.$button)->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_ResetButton() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check table contents before filtering.
		$start_rows_count = $table->getRows()->count();
		$this->compareDisplayingText($start_rows_count);
		$start_contents = $this->getTableResult($start_rows_count);

		// Filling fields with needed discovery rule info.
		$form->fill(['Name' => 'Discovery rule 3']);
		$form->submit();

		$filtered_count = 1;
		$filtered_contents = ['Discovery rule 3'];

		// Check that filtered count mathces expected.
		$this->assertEquals($filtered_count, $table->getRows()->count());
		$this->compareDisplayingText($filtered_count);
		// Checking that filtered discovery rule matches expected.
		$this->assertEquals($filtered_contents, $this->getTableResult($filtered_count));

		// After pressing reset button, check that previous discovery rules are displayed again.
		$form->query('button:Reset')->one()->click();
		$reset_rows_count = $table->getRows()->count();
		$this->assertEquals($start_rows_count, $reset_rows_count);
		$this->compareDisplayingText($reset_rows_count);
		$this->assertEquals($start_contents, $this->getTableResult($reset_rows_count));
	}

	public function testPageLowLevelDiscovery_EnableDisableSingle() {
		$this->page->login()->open('host_discovery.php?&hostid='.self::HOST_ID);
		$table = $this->query('class:list-table')->asTable()->one();
		$name = 'Discovery rule 2';
		$row = $table->findRow('Name', $name);
		$row->select();
		// Clicking Enabled/Disabled link
		$discovery_status = ['Enabled', 'Disabled'];
		foreach ($discovery_status as $action) {
			$row->query('link:'.$action)->one()->click();
			$expected_status = $action === 'Enabled' ? 1 : 0;
			$status = CDBHelper::getValue('SELECT status FROM items WHERE name ='.zbx_dbstr($name));
			$this->assertEquals($expected_status, $status);
			$message_action = $action === 'Enabled' ? 'disabled' : 'enabled';
			$this->assertEquals('Discovery rule '.$message_action, CMessageElement::find()->one()->getTitle());
			$link_color = $action === 'Enabled' ? 'red' : 'green';
			$this->assertTrue($row->query('xpath://td/a[@class="link-action '.$link_color.'"]')->one()->isPresent());
		}
	}

	public function testPageLowLevelDiscovery_EnableDisableAll() {
		$this->page->login()->open('host_discovery.php?&hostid='.self::HOST_ID);
		// Press Enable or Disable buttons and check the result.
		$actions = ['Disable', 'Enable'];
		foreach ($actions as $action) {
			$this->massChangeStatus($action);
			$expected_status = $action === 'Disable' ? 1 : 0;
			foreach ($this->discovery_rule_names as $name) {
				$status = CDBHelper::getValue('SELECT status FROM items WHERE name ='.zbx_dbstr($name));
				$this->assertEquals($expected_status, $status);
			}
		}
	}

	public static function getCheckNowData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'names' => ['Discovery rule 1', 'Discovery rule 2', 'Discovery rule 3'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'names' => ['Discovery rule 2'],
					'message' => 'Request sent successfully'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'names' => ['Discovery rule 2'],
					'disabled' => true,
					'message' => 'Cannot send request',
					'error_details' => 'Cannot send request: discovery rule is disabled.'
				]
			]
		];
	}

	/**
	* @dataProvider getCheckNowData
	*/
	public function testPageLowLevelDiscovery_CheckNow($data) {
		$this->page->login()->open('host_discovery.php?&hostid='.self::HOST_ID);
		// Enabe all LLDs, so Check now can be send successfully.
		$this->massChangeStatus('Enable');

		$table = $this->query('class:list-table')->asTable()->one();
		foreach($data['names'] as $name){
			$row = $table->findRow('Name', $name);
			$row->select();
			if (CTestArrayHelper::get($data, 'disabled')) {
				$this->query('button:Disable')->one()->click();
				$this->page->acceptAlert();
				$row->select();
			}
		}
		$this->query('button:Execute now')->one()->click();

		$message = CMessageElement::find()->one();
		$this->assertEquals($data['message'], $message->getTitle());
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertTrue($message->hasLine($data['error_details']));
				break;
		}
	}

	public function testPageLowLevelDiscovery_DeleteAllButton() {
		$this->page->login()->open('host_discovery.php?&hostid='.self::HOST_ID);
		// Delete all discovery rules.
		$table = $this->query('class:list-table')->asTable()->one();
		foreach ($this->discovery_rule_names as $rule_name) {
			$table->findRow('Name', $rule_name)->select();
		}
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertEquals('Discovery rules deleted', CMessageElement::find()->one()->getTitle());
		foreach ($this->discovery_rule_names as $rule_name) {
			$count = CDBHelper::getCount('SELECT null FROM items WHERE name ='.zbx_dbstr($rule_name));
			$this->assertEquals(0, $count);
		}
	}

	private function getTableResult($rows_count){
		$table = $this->query('class:list-table')->asTable()->one();
		$result = [];
		for ($i = 0; $i < $rows_count; $i ++) {
			$result[] = $table->getRow($i)->getColumn('Name')->getText();
		}

		return $result;
	}

	private function compareDisplayingText($count){
		$this->assertEquals('Displaying '.$count.' of '.$count.' found',
			$this->query('xpath://div[@class="table-stats"]')->one()->getText());
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Virtualization',
					],
					'expected' => [
						'count' => 8
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'values' => ['Simple form test host'],
							'context' => 'Zabbix servers'
						]
					],
					'expected' => [
						'count' => 5,
						'names' => ['testFormDiscoveryRule',
							'testFormDiscoveryRule1',
							'testFormDiscoveryRule2',
							'testFormDiscoveryRule3',
							'testFormDiscoveryRule4']
					]
				]
			],
			[
				[
					'filter' => [
						'Name' => 'testFormDiscoveryRule2',
					],
					'expected' => [
						'count' => 1,
						'names' => ['testFormDiscoveryRule2']
					]
				]
			],
			[
				[
					'filter' => [
						'Update interval' => '0',
					],
					'expected' => [
						'count' => 2,
						'names' => ['Test discovery rule', 'Test discovery rule']
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'values' => ['Visible host for template linkage', 'Test item host'],
							'context' => 'Zabbix servers'
						]
					],
					'expected' => [
						'count' => 2,
						'names' => ['delete Discovery Rule', 'Test discovery rule']
					]
				]
			],
			[
				[
					'filter' => [
						'Hosts' => [
							'values' => ['Visible host for template linkage', 'Test item host'],
							'context' => 'Zabbix servers'
						],
						'Key' => 'key'
					],
					'expected' => [
						'count' => 1,
						'names' => ['delete Discovery Rule']
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => [
							'values' => 'Inheritance test'
						],
						'Hosts' => [
							'values' => ['Inheritance test template with host prototype'],
							'context' => 'Inheritance test'
						],
					],
					'expected' => [
						'count' => 1,
						'names' => ['Discovery rule for host prototype test']
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => [
							'values' => 'Zabbix servers'
						],
						'Name' => 'DiscoveryRule ZBX6663'
					],
					'expected' => [
						'count' => 2
					]
				]
			],
			[
				[
					'filter' => [
						'Key' => 'array.cache.discovery'
					],
					'expected' => [
						'count' => 3,
						'names' => ['Array Controller Cache Discovery',
							'Array Controller Cache Discovery',
							'Array Controller Cache Discovery']
					]
				]
			],
			[
				[
					'filter' => [
						'State' => 'Normal'
					],
					'expected' => [
						'count' => 3,
						'names' => ['Template Module Linux block devices by Zabbix agent: Get /proc/diskstats: Block devices discovery',
							'Template Module Linux filesystems by Zabbix agent: Mounted filesystem discovery',
							'Template Module Linux network interfaces by Zabbix agent: Network interface discovery']
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
						'count' => 1,
						'names' => ['Template Module Linux block devices by Zabbix agent: Get /proc/diskstats: Block devices discovery']
					]
				]
			],
			[
				[
					'filter' => [
						'Host groups' => 'Templates/Operating systems',
						'Type' => 'Dependent item'
					],
					'expected' => [
						'count' => 10
					]
				]
			],
			[
				[
					'filter' => [
						'Type' => 'Dependent item'
					],
					'expected' => [
						'count' => 47
					]
				]
			],
			[
				[
					'filter' => [
						'Type' => 'Database monitor',
						'Update interval' => '1h'
					],
					'expected' => [
						'count' => 2,
						'names' => ['Databases discovery', 'Replication discovery']
					]
				]
			],
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'count' => 1,
						'names' => ['Discovery-rule-layout-test-001']
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
						'count' => 1,
						'names' => ['Discovery-rule-layout-test-002']
					]
				]
			],
			[
				[
					'filter' => [
						'Keep lost resources period' => '50d'
					],
					'expected' => [
						'count' => 1,
						'names' => ['Discovery-rule-layout-test-001']
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
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertEquals($data['expected']['count'], $table->getRows()->count());

		if (array_key_exists('names', $data['expected'])) {
			foreach ($data['expected']['names'] as $i => $name){
				$this->assertEquals($name, $table->getRow($i)->getColumn('Name')->getText());
			}
		}
	}

	private function massChangeStatus($action) {
		$this->query('id:all_items')->asCheckbox()->one()->check();
		$this->query('button:'.$action)->one()->click();
		$this->page->acceptAlert();
		$this->assertEquals('Discovery rules '.lcfirst($action).'d', CMessageElement::find()->one()->getTitle());
	}
}

