<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
		return DBdata(
			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
			' FROM items i,item_discovery d,items di,hosts h'.
			' WHERE i.itemid=d.itemid'.
				' AND h.hostid=i.hostid'.
				' AND d.parent_itemid=di.itemid'.
				' AND i.key_ LIKE '.zbx_dbstr('%-layout-test%')
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageItemPrototypes_CheckLayout($data) {
		$drule = $data['d_name'];
		$this->zbxTestLogin('disc_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);

		// We are in the list of protos
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
		$this->zbxTestTextPresent('Item prototypes of '.$drule);
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('Host list');
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('Template list');
		}

		// Header
		$this->zbxTestTextPresent(
			array('Name', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Applications', 'Status')
		);
		$this->zbxTestTextNotPresent('Error');
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

		$this->zbxTestDropdownHasOptions('go', array(
			'Enable selected',
			'Disable selected',
			'Delete selected'
		));
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
	public function testPageItemPrototypes_SimpleDelete($data) {
		$itemid = $data['itemid'];
		$drule = $data['d_name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('disc_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('group_itemid_'.$itemid);
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
		$this->zbxTestTextPresent('Item prototypes of '.$drule);

		$sql = 'SELECT null FROM items WHERE itemid='.$itemid;
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

	// Returns all discovery rules
	public static function rule() {
		return DBdata(
			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
			' FROM items i,item_discovery d,items di,hosts h'.
			' WHERE i.itemid=d.itemid'.
				' AND h.hostid=i.hostid'.
				' AND d.parent_itemid=di.itemid'.
				' AND h.host LIKE '.zbx_dbstr('%-layout-test-%')
		);
	}

	/**
	* @dataProvider rule
	*/
	public function testPageItemPrototypes_MassDelete($rule) {
		$itemid = $rule['itemid'];
		$druleid = $rule['parent_itemid'];
		$drule = $rule['d_name'];
		$hostid = $rule['hostid'];

		$itemids = DBdata('select itemid from item_discovery where parent_itemid='.$druleid);
		$itemids = zbx_objectValues($itemids, 'itemid');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('disc_prototypes.php?hostid='.$hostid.'&parent_discoveryid='.$druleid);
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF ITEM PROTOTYPES');
		$this->zbxTestTextPresent('Item prototypes of '.$drule);

		$sql = 'SELECT null FROM items WHERE '.dbConditionInt('itemid', $itemids);
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageItemPrototypes_TeardownMass() {
		DBrestore_tables('triggers');
	}
}
