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


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

class testPageItems extends CLegacyWebTest {

	public static function data() {
		return CDBHelper::getDataProvider(
						'SELECT hostid,status'.
						' FROM hosts'.
						' WHERE host LIKE \'%-layout-test%\''
		);
	}

	/**
	 * @dataProvider data
	 */
	public function testPageItems_CheckLayout($data) {
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
			$this->zbxTestTextPresent(
				[
					'Name',
					'Triggers',
					'Key',
					'Interval',
					'History',
					'Trends',
					'Type',
					'Status',
					'Info'
				]
			);
		}
		elseif ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			$this->zbxTestTextPresent(
				[
					'Name',
					'Triggers',
					'Key',
					'Interval',
					'History',
					'Trends',
					'Type',
					'Status',
					'Info'
				]
			);
		}

		$this->zbxTestAssertElementPresentXpath("//button[text()='Execute now'][@disabled]");

		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Clear history and trends', 'Delete');
	}

	/**
	 * @dataProvider data
	 */
	public function testPageItems_CheckNowAll($data) {
		$this->zbxTestLogin('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]='.$data['hostid']);
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestClick('all_items');

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->assertFalse($this->query('button:Execute now')->one()->isEnabled());
			$this->assertFalse($this->query('button:Clear history and trends')->one()->isEnabled());
		}
		else {
			$this->zbxTestClickButtonText('Execute now');
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Request sent successfully');
		}
	}

	public static function getHostAndGroupData() {
		return [
			// One host group without host.
			[
				[
					'filter_options' => [
						'Host groups' => 'Group to check triggers filtering'
					],
					'result' => [
						['Host for triggers filtering' => 'Discovered item one'],
						['Host for triggers filtering' => 'Inheritance item for triggers filtering'],
						['Host for triggers filtering' => 'Item for triggers filtering']
					]
				]
			],
			// Two host group without host.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Key' => 'trap'
					],
					'result' => [
						['Host for triggers filtering' => 'Inheritance item for triggers filtering'],
						['Host for triggers filtering' => 'Item for triggers filtering'],
						['Host for trigger tags filtering' => 'Trapper'],
						['ЗАББИКС Сервер' => 'Utilization of snmp trapper data collector processes, in %'],
						['ЗАББИКС Сервер' => 'Utilization of trapper data collector processes, in %']
					],
					'not_displayed' => [
						'Host' => 'Test Item Template',
						'Name' => 'Macro value: Value 2 B resolved'
					]
				]
			],
			// Two hosts without host group.
			[
				[
					'filter_options' => [
						'Key' => 'trap',
						'Hosts' => [
							[
								'values' => ['Host for trigger tags filtering'],
								'context' => 'Zabbix servers'
							],
							[
								'values' => ['Host for triggers filtering'],
								'context' => 'Group to check triggers filtering'
							]
						]
					],
					'result' => [
						['Host for triggers filtering' => 'Inheritance item for triggers filtering'],
						['Host for triggers filtering' => 'Item for triggers filtering'],
						['Host for trigger tags filtering' => 'Trapper']
					]
				]
			],
			// Two hosts and two their host groups.
			[
				[
					'filter_options' => [
						'Host groups' => ['Group to check triggers filtering', 'Zabbix servers'],
						'Hosts' => [
							[
								'values' => ['Host for trigger tags filtering'],
								'context' => 'Zabbix servers'
							],
							[
								'values' => ['Host for triggers filtering'],
								'context' => 'Group to check triggers filtering'
							]
						]
					],
					'result' => [
						['Host for triggers filtering' => 'Discovered item one'],
						['Host for triggers filtering' => 'Inheritance item for triggers filtering'],
						['Host for triggers filtering' => 'Item for triggers filtering'],
						['Host for trigger tags filtering' => 'Trapper']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getHostAndGroupData
	 */
	public function testPageItems_FilterHostAndGroupsFilter($data) {
		$this->page->login()->open('zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids[0]=99062');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		// Item create button enabled and breadcrumbs exist.
		$this->assertTrue($this->query('button:Create item')->one()->isEnabled());
		$this->assertFalse($this->query('class:breadcrumbs')->all()->isEmpty());

		// Clear hosts in filter fields.
		if (!array_key_exists('Hosts', $data['filter_options'])) {
			$form->getField('Hosts')->asMultiselect()->clear();
		}

		$form->fill($data['filter_options']);
		$form->submit();
		$this->page->waitUntilReady();

		// Item create button disabled and breadcrumbs not exist.
		$this->assertFalse($this->query('button:Create item (select host first)')->one()->isEnabled());
		$this->assertTrue($this->query('class:filter-breadcrumb')->all()->isEmpty());

		// Check results in table.
		$table = $this->query('name:item_list')->one()->query('class:list-table')->asTable()->one();
		foreach ($table->getRows() as $i => $row) {
			foreach ($data['result'][$i] as $group => $host) {
				$get_host = $row->getColumn('Name')->query('link:'.$host)->one()->getText();
				$get_group = $row->getColumn('Host')->getText();
				$this->assertEquals($host, $get_host);
				$this->assertEquals($group, $get_group);
			}
		}

		if (array_key_exists('not_displayed', $data)) {
			foreach ($data['not_displayed'] as $column => $value) {
				$this->assertNotContains($value, $table->getCells($column));
			}
		}

		$this->assertEquals(count($data['result']), $table->getRows()->count());
	}
}
