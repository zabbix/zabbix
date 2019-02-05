<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageLowLevelDiscovery extends CLegacyWebTest {

	// Returns all Discovery Rules
	public static function data() {
		return CDBHelper::getDataProvider(
			'SELECT h.hostid, i.itemid, i.name, h.host, h.status'.
			' FROM hosts h, items i'.
			' WHERE i.hostid=h.hostid'.
				' AND h.host LIKE \'%-layout-test%\''.
				' AND i.flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageLowLevelDiscovery_CheckLayout($data) {
		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckHeader('Discovery rules');
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
			$this->zbxTestTextPresent(
				[
					'Name',
					'Items',
					'Triggers',
					'Graphs',
					'Key',
					'Interval',
					'Type',
					'Status',
					'Info'
				]
			);
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
			$this->zbxTestTextPresent(
				[
					'Name',
					'Items',
					'Triggers',
					'Graphs',
					'Key',
					'Interval',
					'Type',
					'Status'
				]
			);
			$this->zbxTestTextNotPresent('Info');
		}

		$this->zbxTestAssertElementText("//button[@value='discoveryrule.masscheck_now'][@disabled]", 'Check now');

		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent('Enable', 'Disable', 'Delete');
		$this->zbxTestTextPresent('0 selected');
	}

	/**
	 * @dataProvider data
	 */
	public function testPageLowLevelDiscovery_CheckNowAll($data) {
		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		$this->zbxTestCheckHeader('Discovery rules');

		$this->zbxTestClick('all_items');
		$this->zbxTestClickButtonText('Check now');
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot send request');
			$this->zbxTestTextPresentInMessageDetails('Cannot send request: host is not monitored.');
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Request sent successfully');
		}
	}

	/**
	 * @dataProvider data
	 * @backup-once triggers
	 */
	public function testPageLowLevelDiscovery_SimpleDelete($data) {
		$itemid = $data['itemid'];

		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_hostdruleid_'.$itemid);
		$this->zbxTestClickButton('discoveryrule.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');
		$this->zbxTestCheckHeader('Discovery rules');

		$sql = "SELECT null FROM items WHERE itemid=$itemid";
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	// Returns all discovery rules
	public static function rule() {
		return CDBHelper::getDataProvider(
			'SELECT distinct h.hostid, h.host from hosts h, items i'.
			' WHERE h.host LIKE \'%-layout-test%\'' .
				' AND h.hostid = i.hostid'.
				' AND i.flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
	}


	/**
	 * @dataProvider rule
	 * @backup-once triggers
	 */
	public function testPageLowLevelDiscovery_MassDelete($rule) {
		$hostids = CDBHelper::getAll(
			'SELECT hostid'.
			' FROM items'.
			' WHERE hostid='.$rule['hostid'].
				' AND flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
		$hostids = zbx_objectValues($hostids, 'hostids');

		$this->zbxTestLogin('host_discovery.php?&hostid='.$rule['hostid']);
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('discoveryrule.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');
		$this->zbxTestCheckHeader('Discovery rules');

		$sql = 'SELECT null FROM items WHERE '.dbConditionInt('hostids', $hostids);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}
}
