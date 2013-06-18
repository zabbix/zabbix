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

class testPageDiscovery extends CWebTest {
	// Returns all discovery rules
	public static function allRules() {
		return DBdata('select * from drules');
	}

	/**
	* @dataProvider allRules
	*/
	public function testPageDiscovery_CheckLayout($rule) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');

		$this->zbxTestTextPresent('DISCOVERY');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(array('Name', 'IP range', 'Delay', 'Checks', 'Status'));
		$this->zbxTestTextPresent($rule['name']);
		$this->zbxTestTextPresent($rule['iprange']);
		$this->zbxTestTextPresent($rule['delay']);
		$this->zbxTestDropdownHasOptions('go', array('Enable selected', 'Disable selected', 'Delete selected'));
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

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rule updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('DISCOVERY');

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

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestCheckboxSelect('g_druleid['.$druleid.']');
		$this->zbxTestDropdownSelect('go', 'Delete selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rules deleted');

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

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestDropdownSelect('go', 'Enable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rules updated');

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

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestCheckboxSelect('g_druleid['.$druleid.']');
		$this->zbxTestDropdownSelect('go', 'Enable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rules updated');

		$sql = "select * from drules where druleid=$druleid and status=".DRULE_STATUS_ACTIVE;
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageDiscovery_MassDisableAll() {
		DBexecute('update drules set status='.DRULE_STATUS_ACTIVE);

		$this->chooseOkOnNextConfirmation();

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestCheckboxSelect('all_drules');
		$this->zbxTestDropdownSelect('go', 'Disable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rules updated');

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

		$this->zbxTestLogin('discoveryconf.php');
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestCheckboxSelect('g_druleid['.$druleid.']');
		$this->zbxTestDropdownSelect('go', 'Disable selected');
		$this->zbxTestClickWait('goButton');

		$this->getConfirmation();
		$this->checkTitle('Configuration of discovery');
		$this->zbxTestTextPresent('Discovery rules updated');

		$sql = "select * from drules where druleid=$druleid and status=".DRULE_STATUS_DISABLED;
		$this->assertEquals(1, DBcount($sql));
	}

	public function testPageDiscovery_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
