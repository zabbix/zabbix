<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

class testPageUserGroups extends CWebTest {
	public static function allGroups() {
		return DBdata("select * from usrgrp where name<>'Disabled' order by usrgrpid");
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_CheckLayout($group) {
		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');
		$this->zbxTestTextPresent('Displaying');

		$this->zbxTestTextPresent(['Name', '#', 'Members', 'Status', 'Frontend access', 'Debug mode', 'Status']);

		$this->zbxTestTextPresent([$group['name']]);
		$this->zbxTestTextPresent('Enable', 'Disable', 'Enable debug mode', 'Disable debug mode', 'Delete');
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

		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('Group updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestCheckHeader('User groups');

		$this->assertEquals($oldHashGroup, DBhash($sqlHashGroup));
		$this->assertEquals($oldHashUsersGroups, DBhash($sqlHashUsersGroups));
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

		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('group_groupid_'.$usrgrpid);
		$this->zbxTestClickButton('usergroup.massdisable');

		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestCheckTitle('Configuration of user groups');
		if ($cannotDisable) {
			$this->zbxTestTextPresent('Cannot disable user group');
			$this->zbxTestTextPresent('User cannot change status of himself.');
		}
		else {
			$this->zbxTestTextPresent('User group disabled');
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

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnable($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('group_groupid_'.$usrgrpid);
		$this->zbxTestClickButton('usergroup.massenable');

		$this->webDriver->switchTo()->alert()->accept();
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('User group enabled');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and users_status=".GROUP_STATUS_ENABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('group_groupid_'.$usrgrpid);
		$this->zbxTestClickButton('usergroup.massenabledebug');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('Debug mode updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_ENABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = DBhash($sqlHashGroups);

		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('group_groupid_'.$usrgrpid);
		$this->zbxTestClickButton('usergroup.massdisabledebug');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('Debug mode updated');

		$sql = "select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_DISABLED;
		$this->assertEquals(1, DBcount($sql));

		$this->assertEquals($oldHashGroups, DBhash($sqlHashGroups));
	}

}
