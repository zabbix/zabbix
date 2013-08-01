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

class testPageItemPrototypes extends CWebTest {

	// Returns all item protos
	public static function data() {
		$sql = 'SELECT h.status, i.name, d.parent_itemid, di.name AS d_name FROM items i, item_discovery d, items di, hosts h'.
					' WHERE i.itemid=d.itemid'.
					' AND h.hostid=i.hostid'.
					' AND d.parent_itemid=di.itemid'.
					' AND i.key_ LIKE "%zbx6275%"';
		return DBdata($sql);
	}

	/**
	* @dataProvider data
	*/

	public function testPageItemPrototypes_CheckLayout($data) {
		$druleid = $data['parent_itemid'];
		$drule = $data['d_name'];
		$hostid = $data['hostid'];

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {

			$this->zbxTestLogin('hosts.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');

			$this->checkTitle('Configuration of hosts');
			$this->zbxTestTextPresent('HOSTS');
			// Go to the list of protos
			$this->href_click("disc_prototypes.php?hostid=$hostid&parent_discoveryid=$druleid");
			$this->wait();
			// We are in the list of protos
			$this->checkTitle('Configuration of item prototypes');
			$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
			$this->zbxTestTextPresent('Item prototypes of'.$drule);
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
		if ($data['status'] == HOST_STATUS_TEMPLATE) {

			$this->zbxTestLogin('templates.php');
			$this->zbxTestDropdownSelectWait('groupid', 'all');

			$this->checkTitle('Configuration of templates');
			$this->zbxTestTextPresent('TEMPLATES');
			// Go to the list of protos
			$this->href_click("disc_prototypes.php?hostid=$hostid&parent_discoveryid=$druleid");
			$this->wait();
			// We are in the list of protos
			$this->checkTitle('Configuration of item prototypes');
			$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
			$this->zbxTestTextPresent('Item prototypes of'.$drule);
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
	public function testPageItemPrototypes_Setup() {
		DBsave_tables('triggers');
	}

	/**
	* @dataProvider data
	*/
	public function testPageItemPrototypes_SimpleDelete($rule) {
		$itemid = $rule['itemid'];
		$druleid = $rule['parent_itemid'];
		$drule = $rule['d_name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin("disc_prototypes.php?hostid=$hostid&parent_discoveryid=$druleid");
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('group_itemid_'.$itemid);
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
		$this->zbxTestTextPresent('Item prototypes of'.$drule);

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
	public function testPageItemPrototypes_Teardown() {
		DBrestore_tables('triggers');
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageItemPrototypes_SetupMass() {
		DBsave_tables('triggers');
	}

	/**
	* @dataProvider data
	*/
	public function testPageItemPrototypes_MassDelete($rule) {
		$itemid = $rule['itemid'];
		$druleid = $rule['parent_itemid'];
		$drule = $rule['d_name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin("disc_prototypes.php?hostid=$hostid&parent_discoveryid=$druleid");
		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
		$this->zbxTestTextPresent('Item prototypes of'.$drule);

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
	public function testPageItemPrototypes_TeardownMass() {
		DBrestore_tables('triggers');
	}
}
