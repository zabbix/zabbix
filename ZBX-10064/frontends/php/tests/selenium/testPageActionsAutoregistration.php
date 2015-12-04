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

class testPageActionsAutoregistration extends CWebTest {
	// Returns all trigger actions
	public static function allActions() {
		return DBdata("select * from actions where eventsource=".EVENT_SOURCE_AUTO_REGISTRATION." order by actionid");
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_CheckLayout($action) {
		$name = $action['name'];

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');

// eventsource is used for a hidden field, so it does not work. See above: ?eventsource=0 is used instead
//		$this->zbxTestDropdownSelect('eventsource', 'Auto registration');

		$this->zbxTestTextPresent('Event source');
		$this->zbxTestTextPresent('Displaying');
		// Header
		$this->zbxTestTextPresent(array('Name', 'Conditions', 'Operations', 'Status'));
		// Data
		$this->zbxTestTextPresent(array($action['name']));
		$this->zbxTestDropdownHasOptions('go', array('Enable selected', 'Disable selected', 'Delete selected'));
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_SimpleUpdate($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$sqlAction = "select * from actions where actionid=$actionid order by actionid";
		$oldHashAction = DBhash($sqlAction);
		$sqlOperations = "select * from operations where actionid=$actionid order by operationid";
		$oldHashOperations = DBhash($sqlOperations);
		$sqlConditions = "select * from conditions where actionid=$actionid order by conditionid";
		$oldHashConditions = DBhash($sqlConditions);

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent('Action updated');
		$this->zbxTestTextPresent("$name");

		$this->assertEquals($oldHashAction, DBhash($sqlAction), "Chuck Norris: Action update changed data in table 'actions'.");
		$this->assertEquals($oldHashOperations, DBhash($sqlOperations), "Chuck Norris: Action update changed data in table 'operations'");
		$this->assertEquals($oldHashConditions, DBhash($sqlConditions), "Chuck Norris: Action update changed data in table 'conditions'");
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_SingleEnableDisable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');
		switch ($action['status']) {
			case ACTION_STATUS_ENABLED:
				$this->href_click("actionconf.php?go=disable&g_actionid%5B%5D=$actionid&");
				break;
			case ACTION_STATUS_DISABLED:
				$this->href_click("actionconf.php?go=activate&g_actionid%5B%5D=$actionid&");
				break;
		}
		$this->wait();

		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent('Status updated');

		switch ($action['status']) {
			case ACTION_STATUS_ENABLED:
				$sql = "select * from actions where actionid=$actionid and status=".ACTION_STATUS_DISABLED;
				break;
			case ACTION_STATUS_DISABLED:
				$sql = "select * from actions where actionid=$actionid and status=".ACTION_STATUS_ENABLED;
				break;
		}
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsAutoregistration_Create() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageActionsAutoregistration_MassDisableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_MassDisable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');
		$this->zbxTestCheckboxSelect('g_actionid['.$actionid.']');
		$this->zbxTestDropdownSelect('go', 'Disable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent('Status updated');
		$this->zbxTestTextPresent('Disabled');

		$sql = "select * from actions where actionid=$actionid and status=1";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsAutoregistration_MassEnableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_MassEnable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');
		$this->zbxTestCheckboxSelect('g_actionid['.$actionid.']');
		$this->zbxTestDropdownSelect('go', 'Enable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent('Status updated');
		$this->zbxTestTextPresent('Enabled');

		$sql = "select * from actions where actionid=$actionid and status=0";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsAutoregistration_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsAutoregistration_MassDelete($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		DBsave_tables('actions');

		$this->zbxTestLogin('actionconf.php?eventsource='.EVENT_SOURCE_AUTO_REGISTRATION);
		$this->checkTitle('Configuration of actions');
		$this->zbxTestCheckboxSelect('g_actionid['.$actionid.']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();

		$this->checkTitle('Configuration of actions');
		$this->zbxTestTextPresent('Selected actions deleted');

		$sql = "select * from actions where actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from operations where actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from conditions where actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('actions');
	}

	public function testPageActionsAutoregistration_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
