<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
		$sql = 'SELECT h.hostid, i.itemid, i.name, h.host, h.status'.
				' FROM hosts h, items i'.
				' WHERE i.hostid=h.hostid'.
					" AND h.host LIKE '%ZBX6275'";

		return DBdata($sql);
	}

	/**
	* @dataProvider data
	*/

	public function testPageDiscoveryRules_CheckLayout($data) {
var_dump($data['host']);
var_dump($data['name']);
		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$hostid = $data['hostid'];

			$this->zbxTestOpen('hosts.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');

			$this->checkTitle('Configuration of hosts');
			$this->zbxTestTextPresent('HOSTS');
			// Go to the list of drules
			$this->href_click("host_discovery.php?&hostid=$hostid");
			$this->wait();
			// We are in the list of drules
			$this->checkTitle('Configuration of discovery rules');
			$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
			$this->zbxTestTextPresent('Discovery rules');
			$this->zbxTestTextPresent('Displaying');
			$this->zbxTestTextPresent('Host list');
			// Header
			$this->zbxTestTextPresent(
				array(
					'Name',
					'Items',
					'Triggers',
					'Graphs',
					'Key',
					'Interval',
					'Type',
					'Status',
					'Error'
				)
			);
			// someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

			$this->zbxTestDropdownHasOptions('go', array(
					'Enable selected',
					'Disable selected',
					'Delete selected'
			));
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$templateid = $data['hostid'];

			$this->zbxTestOpen('templates.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');

			$this->checkTitle('Configuration of templates');
			$this->zbxTestTextPresent('TEMPLATES');
			// Go to the list of drules
			$this->href_click("host_discovery.php?&hostid=$templateid");
			$this->wait();
			// We are in the list of drules
			$this->checkTitle('Configuration of discovery rules');
			$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');
			$this->zbxTestTextPresent('Discovery rules');
			$this->zbxTestTextPresent('Displaying');
			$this->zbxTestTextPresent('Template list');
			// Header
			$this->zbxTestTextPresent(
				array(
					'Name',
					'Items',
					'Triggers',
					'Graphs',
					'Key',
					'Interval',
					'Type',
					'Status'
				)
			);
			$this->zbxTestTextNotPresent('Error');
			// someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

			$this->zbxTestDropdownHasOptions('go', array(
					'Enable selected',
					'Disable selected',
					'Delete selected'
			));
		}
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageActionsDiscovery_Setup() {
		DBsave_tables('triggers');
	}

	/**
	* @dataProvider data
	*/
	public function testPageActionsDiscovery_MassDelete($rule) {
		$itemid = $rule['itemid'];
		$this->chooseOkOnNextConfirmation();
var_dump($rule['host']);
var_dump($rule['name']);
		$this->zbxTestLogin('host_discovery.php?&hostid='.$rule['hostid']);
		$this->checkTitle('Configuration of discovery rules');
		$this->zbxTestCheckboxSelect('g_hostdruleid_'.$itemid);
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rules deleted');
		$this->zbxTestTextPresent('CONFIGURATION OF DISCOVERY RULES');

		$sql = "SELECT * FROM items WHERE itemid=$itemid AND flags=".ZBX_FLAG_DISCOVERY;
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT * FROM item_discovery WHERE parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT gi.gitemid FROM graphs_items gi, item_discovery id WHERE gi.itemid=id.itemid AND id.parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT f.functionid FROM functions f, item_discovery id WHERE f.itemid=id.itemid AND id.parent_itemid=$itemid";
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageActionsDiscovery_Teardown() {
		DBrestore_tables('triggers');
	}
}
