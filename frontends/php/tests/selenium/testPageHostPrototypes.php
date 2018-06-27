<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

/**
 * @backup hosts
 */
class testPageHostPrototypes extends CWebTest {

	public static function getSelectedData() {
		return [
			[
				[
					'item' => 'Discovery rule 1',
					'hosts' => [
						'Host prototype {#1}'
					]
				]
			],
			[
				[
					'item' => 'Discovery rule 2',
					'hosts' => 'all'
				]
			],
			[
				[
					'item' => 'Discovery rule 3',
					'hosts' => [
						'Host prototype {#7}',
						'host' => 'Host prototype {#9}',
						'host' => 'Host prototype {#10}'
					]
				]
			]
		];
	}

	/**
	 * Select specified hosts from host prototype page.
	 *
	 * @param array $data	test case data from data provider
	 */
	private function selectHostPrototype($data) {
		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name=".zbx_dbstr($data['item'])));
		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=".$discoveryid['itemid']);

		if ($data['hosts'] === 'all') {
			$this->zbxTestCheckboxSelect('all_hosts');
			return;
		}

		foreach ($data['hosts'] as $host) {
			$result = DBselect('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host));
			while ($row = DBfetch($result)) {
				$this->zbxTestCheckboxSelect('group_hostid_'.$row['hostid']);
			}
		}
	}

	/**
	 * Check specific page action.
	 * Actions are defined by buttons pressed on page.
	 *
	 * @param array  $data		test case data from data provider
	 * @param string $action	button text (action to be executed)
	 * @param int    $status	host status to be checked in DB
	 */
	protected function checkPageAction($data, $action, $status = null) {
		$this->selectHostPrototype($data);

		// Click on button with required action.
		$this->zbxTestClickButtonText($action);
		$this->zbxTestAcceptAlert();
		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');

		// Create query part for status (if any).
		$status_criteria = ($status !== null) ? (' AND status='.$status) : '';

		// Check the results in DB.
		if ($data['hosts'] === 'all') {
			$sql = 'SELECT NULL'.
						' FROM hosts'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM host_discovery'.
							' WHERE parent_itemid IN ('.
								'SELECT itemid'.
								' FROM items'.
								' WHERE name='.zbx_dbstr($data['item']).
							')'.
						')';
		}
		else {
			$names = [];
			foreach ($data['hosts'] as $host) {
				$names[] = zbx_dbstr($host);
			}

			$sql = 'SELECT NULL'.
					' FROM hosts'.
					' WHERE host IN ('.implode(',', $names).')';
		}

		$this->assertEquals(0, DBcount($sql.$status_criteria));
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DisableSelected($data) {
		$this->checkPageAction($data, 'Create disabled', HOST_STATUS_MONITORED);
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_EnableSelected($data) {
		$this->checkPageAction($data, 'Create enabled', HOST_STATUS_NOT_MONITORED);
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DeleteSelected($data) {
		$this->checkPageAction($data, 'Delete');
	}
}
