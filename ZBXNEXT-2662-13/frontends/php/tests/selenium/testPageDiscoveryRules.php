<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
				' AND h.host LIKE '.zbx_dbstr('%-layout-test%').
				' AND i.flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
	}

	/**
	* @dataProvider data
	*/

	public function testPageDiscoveryRules_CheckLayout($data) {
		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		// We are in the list of drules
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
		$this->zbxTestTextPresent('Discovery rules');
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('Host list');
			// Header
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
					'Error'
				]
			);
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('Template list');
			// Header
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
			$this->zbxTestTextNotPresent('Error');
		}
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestDropdownHasOptions('action', [
				'Enable selected',
				'Disable selected',
				'Delete selected'
		]);
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageDiscoveryRules_Setup() {
		DBsave_tables('triggers');
	}

	/**
	* @dataProvider data
	*/
	public function testPageDiscoveryRules_SimpleDelete($data) {
		$itemid = $data['itemid'];
		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('host_discovery.php?&hostid='.$data['hostid']);
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_hostdruleid_'.$itemid);
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');

		$sql = "SELECT null FROM items WHERE itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageDiscoveryRules_Teardown() {
		DBrestore_tables('triggers');
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageDiscoveryRules_SetupMass() {
		DBsave_tables('triggers');
	}

	// Returns all discovery rules
	public static function rule() {
		return DBdata(
			'SELECT distinct h.hostid, h.host from hosts h, items i'.
			' WHERE h.host LIKE ' .zbx_dbstr('%-layout-test-%').
				' AND h.hostid = i.hostid'.
				' AND i.flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
	}


	/**
	* @dataProvider rule
	*/
	public function testPageDiscoveryRules_MassDelete($rule) {
		$this->chooseOkOnNextConfirmation();

		$hostids = DBdata(
			'SELECT hostid'.
			' FROM items'.
			' WHERE hostid='.$rule['hostid'].
				' AND flags = '.ZBX_FLAG_DISCOVERY_RULE
		);
		$hostids = zbx_objectValues($hostids, 'hostids');

		$this->zbxTestLogin('host_discovery.php?&hostid='.$rule['hostid']);
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestDropdownSelect('action', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');

		$sql = 'SELECT null FROM items WHERE '.dbConditionInt('hostids', $hostids);
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageDiscoveryRules_TeardownMass() {
		DBrestore_tables('triggers');
	}
}
