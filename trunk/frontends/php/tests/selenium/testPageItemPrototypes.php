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

class testPageItemPrototypes extends CWebTest {

	// Returns all item protos
	public static function data() {
		return DBdata(
			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
			' FROM items i,item_discovery d,items di,hosts h'.
			' WHERE i.itemid=d.itemid'.
				' AND h.hostid=i.hostid'.
				' AND d.parent_itemid=di.itemid'.
				' AND i.key_ LIKE \'%-layout-test%\''
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageItemPrototypes_CheckLayout($data) {
		$drule = $data['d_name'];
		$this->zbxTestLogin('disc_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);

		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckHeader('Item prototypes');
		$this->zbxTestTextPresent($drule);
		$this->zbxTestTextPresent($data['name']);
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
		}

		$this->zbxTestTextPresent(
			['Name', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Applications', 'Status']
		);
		$this->zbxTestTextNotPresent('Info');
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc

		$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);
	}

	/**
	 * @dataProvider data
	 * @backup-once triggers
	 */
	public function testPageItemPrototypes_SimpleDelete($data) {
		$itemid = $data['itemid'];
		$drule = $data['d_name'];

		$this->zbxTestLogin('disc_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('group_itemid_'.$itemid);
		$this->zbxTestClickButton('itemprototype.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckHeader('Item prototypes');
		$this->zbxTestTextPresent('Item prototypes deleted');

		$sql = 'SELECT null FROM items WHERE itemid='.$itemid;
		$this->assertEquals(0, DBcount($sql));
	}

	// Returns all discovery rules
	public static function rule() {
		return DBdata(
			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
			' FROM items i,item_discovery d,items di,hosts h'.
			' WHERE i.itemid=d.itemid'.
				' AND h.hostid=i.hostid'.
				' AND d.parent_itemid=di.itemid'.
				' AND h.host LIKE \'%-layout-test%\''
		);
	}

	/**
	 * @dataProvider rule
	 * @backup-once triggers
	 */
	public function testPageItemPrototypes_MassDelete($rule) {
		$itemid = $rule['itemid'];
		$druleid = $rule['parent_itemid'];
		$drule = $rule['d_name'];
		$hostid = $rule['hostid'];

		$itemids = DBdata('select itemid from item_discovery where parent_itemid='.$druleid, false);
		$itemids = zbx_objectValues($itemids, 'itemid');

		$this->zbxTestLogin('disc_prototypes.php?hostid='.$hostid.'&parent_discoveryid='.$druleid);
		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckboxSelect('all_items');
		$this->zbxTestClickButton('itemprototype.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of item prototypes');
		$this->zbxTestCheckHeader('Item prototypes');
		$this->zbxTestTextPresent('Item prototypes deleted');

		$sql = 'SELECT null FROM items WHERE '.dbConditionInt('itemid', $itemids);
		$this->assertEquals(0, DBcount($sql));
	}
}
