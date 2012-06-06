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

class testPageUserGroups extends CWebTest {
	// Returns all user groups
	public static function allGroups() {
		return DBdata("select * from usrgrp where name<>'Disabled' order by usrgrpid");
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_CheckLayout($group) {
		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');
		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name', '#', 'Members', 'Status', 'Frontend access', 'Debug mode'));
		// Data
		$this->ok(array($group['name']));
		$this->dropdown_select('go', 'Enable selected');
		$this->dropdown_select('go', 'Disable selected');
		$this->dropdown_select('go', 'Enable DEBUG');
		$this->dropdown_select('go', 'Disable DEBUG');
		$this->dropdown_select('go', 'Delete selected');
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_SimpleUpdate($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroup = "select * from usrgrp where name='$name' order by usrgrpid";
		$oldHashGroup = DBhash($sqlHashGroup);
		$sqlHashUsersGroups = "select * from users_groups where usrgrpid=$usrgrpid order by id";
		$oldHashUsersGroups = DBhash($sqlHashUsersGroups);

		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of user groups');
		$this->ok('Group updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');

		$this->assertEquals($oldHashGroup, DBhash($sqlHashGroup));
		$this->assertEquals($oldHashUsersGroups, DBhash($sqlHashUsersGroups));
	}

	public function testPageUserGroups_MassEnableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnable($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go', 'Enable selected');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of user groups');
		$this->ok('Users status updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and users_status=".GROUP_STATUS_ENABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	public function testPageUserGroups_MassDisableAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisable($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$cannotDisable = ('Zabbix administrators' == $name);

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go', 'Disable selected');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of user groups');
		if ($cannotDisable) {
			$this->ok('Cannot update users status');
		}
		else {
			$this->ok('Users status updated');
		}

		$sql = "select * from usrgrp where usrgrpid=$usrgrpid and users_status=".GROUP_STATUS_DISABLED;
		if ($cannotDisable) {
			$this->assertEquals(0, DBcount($sql));
		}
		else {
			$this->assertEquals(1, DBcount($sql));
		}

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	public function testPageUserGroups_MassEnableDEBUGAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go', 'Enable DEBUG');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of user groups');
		$this->ok('Debug mode updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_ENABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	public function testPageUserGroups_MassDisableDEBUGAll() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->login('usergrps.php');
		$this->checkTitle('Configuration of user groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go', 'Disable DEBUG');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->checkTitle('Configuration of user groups');
		$this->ok('Debug mode updated');

		$sql = "select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_DISABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	public function testPageUserGroups_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassDelete() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
