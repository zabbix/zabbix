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

class testPageDiscovery extends CWebTest {
	// Returns all discovery rules
	public static function allRules() {
		return DBdata('select * from drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_CheckLayout($rule) {
		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');

		$this->ok('DISCOVERY');
		$this->ok('Displaying');
		$this->ok(array('Name', 'IP range', 'Delay', 'Checks', 'Status'));
		$this->ok($rule['name']);
		$this->ok($rule['iprange']);
		$this->ok($rule['delay']);
		$this->dropdown_select('go', 'Enable selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_SimpleUpdate($rule) {
		$name = $rule['name'];
		$druleid = $rule['druleid'];

		$sqlRules = "select * from drules where name='$name' order by druleid";
		$oldHashRules = DBhash($sqlRules);
		$sqlChecks = "select * from dchecks where druleid=$druleid order by dcheckid";
		$oldHashChecks = DBhash($sqlChecks);

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rule updated');
		$this->ok("$name");
		$this->ok('DISCOVERY');

		$this->assertEquals($oldHashRules, DBhash($sqlRules));
		$this->assertEquals($oldHashChecks, DBhash($sqlChecks));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassDelete($rule) {
		$druleid=$rule['druleid'];

		DBsave_tables('drules');

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("g_druleid[$druleid]");
		$this->dropdown_select('go', 'Delete selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rules deleted');

		$sql = "select * from drules where druleid=$druleid";
		$this->assertEquals(0, DBcount($sql));
		$sql = "select * from dchecks where druleid=$druleid";
		$this->assertEquals(0, DBcount($sql));

		DBrestore_tables('drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_ChangeStatus($rule) {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageDiscovery_MassEnableAll() {
		DBexecute('update drules set status='.DRULE_STATUS_DISABLED);

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("all_drules");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rules updated');

		$sql = "select * from drules where status=".DRULE_STATUS_DISABLED;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassEnable($rule) {
		$druleid=$rule['druleid'];

		DBexecute('update drules set status='.DRULE_STATUS_DISABLED.' where druleid='.$druleid);

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("g_druleid[$druleid]");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rules updated');

		$sql = "select * from drules where druleid=$druleid and status=".DRULE_STATUS_ACTIVE;
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageDiscovery_MassDisableAll() {
		DBexecute('update drules set status='.DRULE_STATUS_ACTIVE);

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("all_drules");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rules updated');

		$sql = "select * from drules where status=".DRULE_STATUS_ACTIVE;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_MassDisable($rule) {
		$druleid = $rule['druleid'];

		DBexecute('update drules set status='.DRULE_STATUS_ACTIVE.' where druleid='.$druleid);

		$this->chooseOkOnNextConfirmation();

		$this->login('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->checkbox_select("g_druleid[$druleid]");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');
		$this->wait();

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->ok('Discovery rules updated');

		$sql = "select * from drules where druleid=$druleid and status=".DRULE_STATUS_DISABLED;
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageDiscovery_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
