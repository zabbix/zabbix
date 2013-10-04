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

class testPageTriggerPrototypes extends CWebTest {

	// Returns all trigger protos
	public static function data() {
		return DBdata(
				'SELECT i.hostid, i.name AS i_name, i.itemid, h.status, t.triggerid, t.description, d.parent_itemid, di.name AS d_name'.
				' FROM items i, triggers t, functions f, item_discovery d, items di, hosts h'.
					' WHERE f.itemid = i.itemid'.
					' AND t.triggerid = f.triggerid'.
					' AND d.parent_itemid=di.itemid'.
					' AND i.itemid=d.itemid'.
					' AND h.hostid=i.hostid'.
					' AND i.name LIKE '.zbx_dbstr('%-layout-test%')
		);
	}

	/**
	* @dataProvider data
	*/

	public function testPageTriggerPrototypes_CheckLayout($data) {
		$drule = $data['d_name'];
		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);
		// We are in the list of protos
		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGER PROTOTYPES');
		$this->zbxTestTextPresent('Trigger prototypes of '.$drule);
		$this->zbxTestTextPresent('Displaying');

		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
			$this->zbxTestTextPresent('Host list');
		}
		if ($data['status'] == HOST_STATUS_TEMPLATE) {
			$this->zbxTestTextPresent('Template list');
		}
		// Header
		$this->zbxTestTextPresent(
			array(
				'Severity',
				'Name',
				'Expression',
				'Status'
			)
		);
		$this->zbxTestTextNotPresent('Error');
		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
		$this->zbxTestDropdownHasOptions('go', array(
				'Enable selected',
				'Disable selected',
				'Mass update',
				'Delete selected'
		));
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageTriggerPrototypes_Setup() {
		DBsave_tables('triggers');
	}

	/**
	* @dataProvider data
	*/
	public function testPageTriggerPrototypes_SimpleDelete($data) {
		$triggerid = $data['triggerid'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$data['hostid'].'&parent_discoveryid='.$data['parent_itemid']);

		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckboxSelect('g_triggerid_'.$triggerid);
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGER PROTOTYPES');
		$this->zbxTestTextPresent('Trigger prototypes of '.$data['d_name']);

		$sql = 'SELECT null FROM triggers WHERE triggerid='.$triggerid;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageTriggerPrototypes_Teardown() {
		DBrestore_tables('triggers');
	}

	/**
	 * Backup the tables that will be modified during the tests.
	 */
	public function testPageTriggerPrototypes_SetupMass() {
		DBsave_tables('triggers');
	}

	// Returns all discovery rules
	public static function rule() {
		return DBdata(
				'SELECT i.hostid, i.name AS i_name, i.itemid, h.status, t.triggerid, t.description, d.parent_itemid, di.name AS d_name'.
				' FROM items i, triggers t, functions f, item_discovery d, items di, hosts h'.
					' WHERE f.itemid = i.itemid'.
					' AND t.triggerid = f.triggerid'.
					' AND d.parent_itemid=di.itemid'.
					' AND i.itemid=d.itemid'.
					' AND h.hostid=i.hostid'.
					' AND h.host LIKE '.zbx_dbstr('%-layout-test-%')
		);
	}

	/**
	* @dataProvider rule
	*/
	public function testPageTriggerPrototypes_MassDelete($rule) {
		$druleid = $rule['parent_itemid'];
		$triggerids = DBdata('select i.itemid from item_discovery id, items i where parent_itemid='.$druleid.' and i.itemid = id.itemid');
		$triggerids = zbx_objectValues($triggerids, 'itemid');

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('trigger_prototypes.php?hostid='.$rule['hostid'].'&parent_discoveryid='.$druleid);
		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestCheckboxSelect('all_triggers');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		sleep(1);
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of trigger prototypes');
		$this->zbxTestTextPresent('CONFIGURATION OF TRIGGER PROTOTYPES');
		$this->zbxTestTextPresent('Trigger prototypes of '.$rule['d_name']);

		$sql = 'SELECT null FROM triggers WHERE '.dbConditionInt('triggerids', $triggerids);
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Restore the original tables.
	 */
	public function testPageTriggerPrototypes_TeardownMass() {
		DBrestore_tables('triggers');
	}
}
