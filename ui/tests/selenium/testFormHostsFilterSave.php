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
require_once dirname(__FILE__).'/common/testFormFilterSave.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup profiles
 */
class testFormHostsFilterSave extends testFormFilterSave {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public static function getSaveFiltersData() {
		return [
			[
				[
					'hosts_filter' => [
						'Name' => 'Host-layout-test-001'
					],
					'filter' => [
						'Name' => '*;%№:?(',
						'Show number of records' => false
					],
					'tab_id' => '1'
				]
			],
			[
				[
					'hosts_filter' => [
						'Name' => 'imagination'
					],
					'filter' => [
						'Name' => 'simple name',
						'Show number of records' => true
					],
					'tab_id' => '2'
				]
			],
			[
				[
					'hosts_filter' => [
						'Name' => 'Simple form test host'
					],
					'filter' => [
						'Name' => 'Untitled',
						'Show number of records' => false
					],
					'tab_id' => '3'
				]
			],
			[
				[
					'hosts_filter' => [
						'Name' => 'Template inheritance test host'
					],
					'filter' => [
						'Name' => 'Untitled',
						'Show number of records' => false
					],
					'tab_id' => '4'
				]
			],
			[
				[
					'hosts_filter' => [
						'Name' => 'test'
					],
					'filter' => [
						'Name' => 'Several things',
						'Show number of records' => true
					],
					'tab_id' => '5'
				]
			]
		];
	}

	/**
	 * @dataProvider getSaveFiltersData
	 */
	public function testFormHostsFilterSave_Create($data) {
		$this->createFilter($data, 'zabbix.php?action=host.view');
	}

	// Updating filter form
	public function testFormHostsFilterSave_UpdateForm() {
		$this->updateFilterForm('zabbix.php?action=host.view&filter_rst=1', ['Name' => 'graph']);
	}

	public function testFormHostsFilterSave_UpdateProperties() {
		$this->updateFilterProperties('zabbix.php?action=host.view&filter_rst=1');
	}

	public function testFormHostsFilterSave_Delete() {
		$this->filterDelete('zabbix.php?action=host.view&filter_rst=1');
	}
}
