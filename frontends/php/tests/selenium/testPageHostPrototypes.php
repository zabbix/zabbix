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

	private function selectHostPrototype($data) {
		$discoveryid = DBfetch(DBselect("SELECT itemid FROM items WHERE name='" . $data['item'] . "'"));
		$this->zbxTestLogin("host_prototypes.php?parent_discoveryid=" . $discoveryid['itemid']);
		foreach ($data['host_list'] as $host) {
			$host_name = $host['host'];
			if ($host_name === 'all') {
				$this->zbxTestCheckboxSelect('all_hosts');
			} else {
				$result = DBselect('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host_name));
				while ($row = DBfetch($result)) {
					$this->zbxTestCheckboxSelect('group_hostid_' . $row['hostid']);
				}
			}
		}
	}

	public static function getSelectedData() {
		return [
			[
				[
					'item' => 'Discovery rule 1',
					'host_list' => [
						['host' => 'Host prototype {#1}']
					]
				]
			],
			[
				[
					'item' => 'Discovery rule 2',
					'host_list' => [
						['host' => 'all']
					]
				]
			],
			[
				[
					'item' => 'Discovery rule 3',
					'host_list' => [
						['host' => 'Host prototype {#7}'],
						['host' => 'Host prototype {#9}'],
						['host' => 'Host prototype {#10}']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DisableSelected($data) {
		$this->selectHostPrototype($data);
		$this->zbxTestClickButtonText('Create disabled');
		$this->zbxTestAcceptAlert();
		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckHeader('Host prototypes');

		// Check the results in DB, that selected host prototype disabled.
		foreach ($data['host_list'] as $host) {
			$host_name = $host['host'];
			if ($host_name === 'all') {
				$result = DBdata('SELECT hostid FROM host_discovery WHERE parent_itemid IN (SELECT itemid FROM items WHERE name='.zbx_dbstr($data['item']).')', false);
				foreach ($result as $hostid) {
					$hostid = $hostid[0];
					$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE status='.HOST_STATUS_MONITORED.' AND hostid='.zbx_dbstr($hostid['hostid'])));
				}
			}
			else {
				$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE status='.HOST_STATUS_MONITORED.' AND host='.zbx_dbstr($host_name)));
			}
		}
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_EnableSelected($data) {
		$this->selectHostPrototype($data);
		$this->zbxTestClickButtonText('Create enabled');
		$this->zbxTestAcceptAlert();
		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that selected host prototype enabled.
		foreach ($data['host_list'] as $host) {
			$host_name = $host['host'];
			if ($host_name === 'all') {
				$result = DBdata('SELECT hostid FROM host_discovery WHERE parent_itemid IN (SELECT itemid FROM items WHERE name='.zbx_dbstr($data['item']).')', false);
				foreach ($result as $hostid) {
					$hostid = $hostid[0];
					$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE status='.HOST_STATUS_NOT_MONITORED.' AND hostid='.zbx_dbstr($hostid['hostid'])));
				}
			}
			else {
				$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE status='.HOST_STATUS_NOT_MONITORED.' AND host='.zbx_dbstr($host_name)));
			}
		}
	}

	/**
	 * @dataProvider getSelectedData
	 */
	public function testPageHostPrototypes_DeleteSelected($data) {
		$this->selectHostPrototype($data);
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestIsElementPresent('//*[@class="msg-good"]');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that selected host prototype deleted.
		foreach ($data['host_list'] as $host) {
			$host_name = $host['host'];
			if ($host_name === 'all') {
				$result = DBdata('SELECT hostid FROM host_discovery WHERE parent_itemid IN (SELECT itemid FROM items WHERE name='.zbx_dbstr($data['item']).')', false);
				foreach ($result as $hostid) {
					$hostid = $hostid[0];
					$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE hostid='.zbx_dbstr($hostid['hostid'])));
				}
			}
			else {
				$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($host_name)));
			}
		}
	}
}
