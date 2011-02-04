<?php
/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__).'/../include/class.cwebtest.php');

class testPageUserGroups extends CWebTest
{
	// Returns all user groups
	public static function allGroups()
	{
		return DBdata("select * from usrgrp where name<>'Disabled' order by usrgrpid");
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_SimpleTest($group)
	{
		$this->login('usergrps.php');
		$this->assertTitle('User groups');
		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');
		$this->ok('Displaying');
		// Header
		$this->ok(array('Name','#','Members','Users status','GUI access','API access','Debug mode'));
		// Data
		$this->ok(array($group['name']));
		$this->dropdown_select('go','Enable selected');
		$this->dropdown_select('go','Disable selected');
		$this->dropdown_select('go','Enable API');
		$this->dropdown_select('go','Disable API');
		$this->dropdown_select('go','Enable DEBUG');
		$this->dropdown_select('go','Disable DEBUG');
		$this->dropdown_select('go','Delete selected');
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_SimpleUpdate($group)
	{
		$usrgrpid=$group['usrgrpid'];
		$name=$group['name'];

		$sql1="select * from usrgrp where name='$name' order by usrgrpid";
		$oldHashGroup=DBhash($sql1);
		$sql2="select * from users_groups where usrgrpid=$usrgrpid order by id";
		$oldHashUsersGroups=DBhash($sql2);

		$this->login('usergrps.php');
		$this->assertTitle('User groups');
		$this->click("link=$name");
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->assertTitle('User groups');
		$this->ok('Group updated');
		$this->ok("$name");
		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');

		$this->assertEquals($oldHashGroup,DBhash($sql1));
		$this->assertEquals($oldHashUsersGroups,DBhash($sql2));
	}

	public function testPageUserGroups_MassEnableAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassEnable()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassDisableAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassDisable()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassEnableAPIAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnableAPI($group)
	{
		$usrgrpid=$group['usrgrpid'];
		$name=$group['name'];

		$sql1="select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups=DBhash($sql1);

		$this->login('usergrps.php');
		$this->assertTitle('User groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go','Enable API');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('User groups');
		$this->ok('API access updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and api_access=".GROUP_API_ACCESS_ENABLED;
		$this->assertEquals(1,DBcount($sql));

		$this->assertEquals($oldHashGroups,DBhash($sql1));
	}

	public function testPageUserGroups_MassDisableAPIAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisableAPI($group)
	{
		$usrgrpid=$group['usrgrpid'];
		$name=$group['name'];

		$sql1="select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups=DBhash($sql1);

		$this->login('usergrps.php');
		$this->assertTitle('User groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go','Disable API');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('User groups');
		$this->ok('API access updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and api_access=".GROUP_API_ACCESS_DISABLED;
		$this->assertEquals(1,DBcount($sql));

		$this->assertEquals($oldHashGroups,DBhash($sql1));
	}

	public function testPageUserGroups_MassEnableDEBUGAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnableDEBUG($group)
	{
		$usrgrpid=$group['usrgrpid'];
		$name=$group['name'];

		$sql1="select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups=DBhash($sql1);

		$this->login('usergrps.php');
		$this->assertTitle('User groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go','Enable DEBUG');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('User groups');
		$this->ok('Debug mode updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_ENABLED;
		$this->assertEquals(1,DBcount($sql));

		$this->assertEquals($oldHashGroups,DBhash($sql1));
	}

	public function testPageUserGroups_MassDisableDEBUGAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisableDEBUG($group)
	{
		$usrgrpid=$group['usrgrpid'];
		$name=$group['name'];

		$sql1="select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups=DBhash($sql1);

		$this->login('usergrps.php');
		$this->assertTitle('User groups');

		$this->checkbox_select("group_groupid[$usrgrpid]");
		$this->dropdown_select('go','Disable DEBUG');
		$this->button_click('goButton');

		$this->getConfirmation();
		$this->wait();
		$this->assertTitle('User groups');
		$this->ok('Debug mode updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_DISABLED;
		$this->assertEquals(1,DBcount($sql));

		$this->assertEquals($oldHashGroups,DBhash($sql1));
	}

	public function testPageUserGroups_MassDeleteAll()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_MassDelete()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUserGroups_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
