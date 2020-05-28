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

class testPageLowLevelDiscovery extends CWebTest {

	public function testPageLowLevelDiscovery_CheckLayout() {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D=90001');
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Check all field names.
		$fields = ['Host groups', 'Hosts', 'Name', 'Key', 'Type', 'Update interval',
			'Keep lost resources period', 'SNMP OID', 'State', 'Status'];
		$labels = $form->getLabels()->asText();
		$this->assertEquals($fields, $labels);

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

		// Check that all buttons.
		$buttons_name = ['Apply', 'Reset'];
		foreach ($buttons_name as $button){
			$this->assertTrue($form->query('button:'.$button)->one()->isPresent());
		}

		// Check all headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = ['','Host', 'Name', 'Items', 'Triggers', 'Graphs', 'Hosts', 'Key', 'Interval', 'Type', 'Status', 'Info'];
		$this->assertSame($headers, $table->getHeadersText());
	}

	public static function getForResetButtonCheck() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Discovery rule 3',
						'Key' => 'key3',
						'Type' => 'Zabbix agent',
						'Update interval' => '30s',
						'Status' => 'Enabled'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getForResetButtonCheck
	 */
	public function testPageLowLevelDiscovery_ResetButton($data) {
		$this->page->login()->open('host_discovery.php?filter_set=1&filter_hostids%5B0%5D=90001');
		$form = $this->query('name:zbx_filter')->one()->asForm();

		// Filling fields with neede discovery rule info.
		$form->fill($data['fields']);
		$form->submit();

		// Checking that needed discovery displayed and he is only one.
		$table = $this->query('class:list-table')->asTable()->one();
		$this->assertTrue($table->findRow('Name', 'Discovery rule 3')->isPresent());
		$this->assertEquals(1, $table->getRows()->count());

		// After pressing reset button, check that there is 3 discovery rules displayed again.
		$form->query('button:Reset')->one()->click();
		$this->assertEquals(3, $table->getRows()->count());
	}

	public static function getFilterData() {
		return [
			[
				[
					'filter' => [
						'Host groups' => 'Zabbix servers',
					],
					'expected' => [
						'count' => 23
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
}
