<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageActionsTriggers extends CWebTest {
	// Returns all trigger actions
	public static function allActions() {
		return DBdata("SELECT * FROM actions WHERE eventsource=".EVENT_SOURCE_TRIGGERS." ORDER BY actionid");
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_CheckLayout($action) {
		$name = $action['name'];

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');

// eventsource is used for a hidden field, so it does not work. See above: ?eventsource=0 is used instead
//		$this->dropdown_select('eventsource', 'Triggers');

		$this->ok('Event source');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name', 'Conditions', 'Operations', 'Status'));
		// Data
		$this->ok(array($action['name']));
		$this->dropdown_select('go', 'Enable selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_SimpleUpdate($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$sql1 = "SELECT * FROM actions WHERE actionid=$actionid ORDER BY actionid";
		$oldHashAction = DBhash($sql1);
		$sql2 = "SELECT * FROM operations WHERE actionid=$actionid ORDER BY operationid";
		$oldHashOperations = DBhash($sql2);
		$sql3 = "SELECT * FROM conditions WHERE actionid=$actionid ORDER BY conditionid";
		$oldHashConditions = DBhash($sql3);

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('Configuration of actions');
		$this->ok('Action updated');
		$this->ok("$name");

		$this->assertEquals($oldHashAction, DBhash($sql1), "Chuck Norris: Action update changed data in table 'actions'.");
		$this->assertEquals($oldHashOperations, DBhash($sql2), "Chuck Norris: Action update changed data in table 'operations'");
		$this->assertEquals($oldHashConditions, DBhash($sql3), "Chuck Norris: Action update changed data in table 'conditions'");
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_SingleEnableDisable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');
		switch ($action['status']) {
			case ACTION_STATUS_ENABLED:
				$this->href_click("actionconf.php?go=disable&g_actionid%5B%5D=$actionid&");
				break;
			case ACTION_STATUS_DISABLED:
				$this->href_click("actionconf.php?go=activate&g_actionid%5B%5D=$actionid&");
				break;
		}
		$this->wait();

		$this->assertTitle('Configuration of actions');
		$this->ok('Status updated');

		switch ($action['status']) {
			case ACTION_STATUS_ENABLED:
				$sql = "SELECT * FROM actions WHERE actionid=$actionid AND status=".ACTION_STATUS_DISABLED;
				break;
			case ACTION_STATUS_DISABLED:
				$sql = "SELECT * FROM actions WHERE actionid=$actionid AND status=".ACTION_STATUS_ENABLED;
				break;
		}
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsTriggers_Create() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageActionsTriggers_MassDisableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_MassDisable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');
		$this->checkbox_select("g_actionid[$actionid]");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->assertTitle('Configuration of actions');
		$this->ok('Status updated');
		$this->ok('Disabled');

		$sql = "SELECT * FROM actions WHERE actionid=$actionid AND status=1";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsTriggers_MassEnableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_MassEnable($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');
		$this->checkbox_select("g_actionid[$actionid]");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->assertTitle('Configuration of actions');
		$this->ok('Status updated');
		$this->ok('Enabled');

		$sql = "SELECT * FROM actions WHERE actionid=$actionid AND status=0";
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageActionsTriggers_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActionsTriggers_MassDelete($action) {
		$actionid = $action['actionid'];
		$name = $action['name'];

		$this->chooseOkOnNextConfirmation();

		DBsave_tables('actions');

		$this->login('actionconf.php?eventsource='.EVENT_SOURCE_TRIGGERS);
		$this->assertTitle('Configuration of actions');
		$this->checkbox_select("g_actionid[$actionid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();

		$this->assertTitle('Configuration of actions');
		$this->ok('Selected actions deleted');

		$sql = "SELECT * FROM actions WHERE actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT * FROM operations WHERE actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "SELECT * FROM conditions WHERE actionid=$actionid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('actions');
	}

	public function testPageActionsTriggers_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
