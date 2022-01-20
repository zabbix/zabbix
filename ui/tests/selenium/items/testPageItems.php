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
		$this->zbxTestLogin('items.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid'].'&context=host');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');
		$this->zbxTestTextPresent('Displaying');

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

		$this->zbxTestAssertElementText("//button[@value='item.masscheck_now'][@disabled]", 'Execute now');

		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Mass update', 'Copy', 'Clear history', 'Delete');
	}

	/**
	 * @dataProvider data
	 */
	public function testPageItems_CheckNowAll($data) {
		$this->zbxTestLogin('items.php?filter_set=1&filter_hostids%5B0%5D='.$data['hostid'].'&context=host');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestClick('all_items');

		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->assertFalse($this->query('button:Execute now')->one()->isEnabled());
			$this->assertFalse($this->query('button:Clear history')->one()->isEnabled());
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
						['ЗАББИКС Сервер' => 'Zabbix server: Utilization of snmp trapper data collector processes, in %'],
						['ЗАББИКС Сервер' => 'Zabbix server: Utilization of trapper data collector processes, in %']
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
		$this->page->login()->open('items.php?filter_set=1&filter_hostids%5B0%5D=99062&context=host');
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
		$table = $this->query('name:items')->one()->query('class:list-table')->asTable()->one();
		foreach ($table->getRows() as $i => $row) {
			$get_host = $row->getColumn('Name')->query('xpath:./a[not(@class)]')->one()->getText();
			$get_group = $row->getColumn('Host')->getText();
			foreach ($data['result'][$i] as $group => $host) {
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
