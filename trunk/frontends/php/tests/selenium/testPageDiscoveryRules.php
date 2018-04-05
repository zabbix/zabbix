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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageDiscoveryRules extends CWebTest {

	// Returns all Discovery Rules
	public static function data() {
		return DBdata(
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
	public function testPageDiscoveryRules_CheckLayout($data) {
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
	public function testPageDiscoveryRules_CheckNowAll($data) {
		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		$this->zbxTestCheckHeader('Discovery rules');

		$this->zbxTestClick('all_items');
		$this->zbxTestClickButtonText('Check now');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Request sent successfully');
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * @dataProvider data
	 * @backup-once triggers
	 */
	public function testPageDiscoveryRules_SimpleDelete($data) {
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
		$this->assertEquals(0, DBcount($sql));
	}

	// Returns all discovery rules
	public static function rule() {
		return DBdata(
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
	public function testPageDiscoveryRules_MassDelete($rule) {
		$hostids = DBdata(
			'SELECT hostid'.
			' FROM items'.
			' WHERE hostid='.$rule['hostid'].
				' AND flags = '.ZBX_FLAG_DISCOVERY_RULE, false
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
		$this->assertEquals(0, DBcount($sql));
	}
}
