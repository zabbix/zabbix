<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

class testPageTriggerPrototypes extends CLegacyWebTest {

	// Returns all trigger protos
	public static function data() {
		return CDBHelper::getDataProvider(
				'SELECT i.hostid, i.name AS i_name, i.itemid, h.status, t.triggerid, t.description, d.parent_itemid, di.name AS d_name'.
				' FROM items i, triggers t, functions f, item_discovery d, items di, hosts h'.
					' WHERE f.itemid = i.itemid'.
					' AND t.triggerid = f.triggerid'.
					' AND d.parent_itemid=di.itemid'.
					' AND i.itemid=d.itemid'.
					' AND h.hostid=i.hostid'.
					' AND i.name LIKE \'%-layout-test%\''
		);
	}

	/**
	* @dataProvider data
	*/
	public function testPageTriggerPrototypes_CheckLayout($data) {
		$drule = $data['d_name'];
		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.
				$data['parent_itemid'].'&context=host');

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($drule);
		$this->zbxTestTextPresent($data['description']);
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('All hosts');
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('All templates');
		}
		$this->zbxTestTextPresent(
			[
				'Severity',
				'Name',
				'Expression',
				'Create enabled'
			]
		);
		$this->zbxTestTextNotPresent('Info');
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestTextPresent(['Create disabled', 'Mass update', 'Delete']);
	}

	/**
	 * @dataProvider data
	 * @backupOnce triggers
	 */
	public function testPageTriggerPrototypes_SimpleDelete($data) {
		$triggerid = $data['triggerid'];

		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.
				$data['parent_itemid'].'&context=host');

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckboxSelect('g_triggerid_'.$triggerid);
		$this->zbxTestClickButton('triggerprototype.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent($data['d_name']);
		$this->zbxTestTextPresent('Trigger prototypes deleted');

		$sql = 'SELECT null FROM triggers WHERE triggerid='.$triggerid;
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}

	// Returns all discovery rules
	public static function rule() {
		return CDBHelper::getDataProvider(
				'SELECT i.hostid, i.name AS i_name, i.itemid, h.status, t.triggerid, t.description, d.parent_itemid, di.name AS d_name'.
				' FROM items i, triggers t, functions f, item_discovery d, items di, hosts h'.
					' WHERE f.itemid = i.itemid'.
					' AND t.triggerid = f.triggerid'.
					' AND d.parent_itemid=di.itemid'.
					' AND i.itemid=d.itemid'.
					' AND h.hostid=i.hostid'.
					' AND h.host LIKE \'%-layout-test%\''
		);
	}

	/**
	 * @dataProvider rule
	 * @backupOnce triggers
	 */
	public function testPageTriggerPrototypes_MassDelete($rule) {
		$druleid = $rule['parent_itemid'];
		$triggerids = CDBHelper::getAll(
				'SELECT i.itemid'.
				' FROM item_discovery id, items i'.
				' WHERE parent_itemid='.$druleid.' AND i.itemid = id.itemid'
		);
		$triggerids = zbx_objectValues($triggerids, 'itemid');

		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$rule['hostid'].'&parent_discoveryid='.$druleid.'&context=host');
		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckboxSelect('all_triggers');
		$this->zbxTestClickButton('triggerprototype.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckHeader('Trigger prototypes');
		$this->zbxTestTextPresent('Trigger prototypes deleted');

		$sql = 'SELECT null FROM triggers WHERE '.dbConditionInt('triggerid', $triggerids);
		$this->assertEquals(0, CDBHelper::getCount($sql));
	}
}
