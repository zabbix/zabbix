<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
require_once(dirname(__FILE__).'/class.ctest.php');

class testPageUsers extends CTest
{
	public function testPageUsers_SimpleTest()
	{
		$this->login('users.php');
		$this->assertTitle('Users');

		$this->dropdown_select('filter_usrgrpid','All');

		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');
		$this->ok('Displaying');
		$this->ok('Alias');
		$this->ok('Name');
		$this->ok('Surname');
		$this->ok('User type');
		$this->ok('Groups');
		$this->ok('Is online?');
		$this->ok('Login');
		$this->ok('GUI access');
		$this->ok('API access');
		$this->ok('Debug mode');
		$this->ok('Status');
		$this->ok('Admin');
		$this->ok('Guest');
		$this->dropdown_select('go','Unblock selected');
		$this->dropdown_select('go','Delete selected');
	}

	public function testPageUsers_FilterByHostGroup()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUsers_Sorting()
	{
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUsers_SimpleUpdate()
	{
		$result=DBselect('select alias from users');

		while($user=DBfetch($result))
		{
			$alias=$user['alias'];
			$sql1="select * from users where alias='$alias'";
			$oldHashUser=$this->DBhash($sql1);
			$sql2="select * from users,users_groups where users.userid=users_groups.userid and users.alias='$alias'";
			$oldHashGroup=$this->DBhash($sql2);
			$sql3="select * from users,media where users.userid=media.userid and users.alias='$alias'";
			$oldHashMedia=$this->DBhash($sql3);

			$this->login('users.php');
			$this->assertTitle('Users');
			$this->dropdown_select('filter_usrgrpid','All');

			$this->click("link=$alias");
			$this->wait();
			$this->button_click('save');
			$this->wait();
			$this->assertTitle('Users');
			$this->ok('User updated');
			$this->ok("$alias");
			$this->ok('CONFIGURATION OF USERS AND USER GROUPS');

			$this->assertEquals($oldHashUser,$this->DBhash($sql1));
			$this->assertEquals($oldHashGroup,$this->DBhash($sql2),"Chuck Norris: User update changed data in table users_groups");
			$this->assertEquals($oldHashMedia,$this->DBhash($sql3),"Chuck Norris: User update changed data in table medias");
		}
	}

	public function testPageUsers_MassDelete()
	{
		DBexecute('select * into temp table users_tmp from users');
		DBexecute('select * into temp table users_groups_tmp from users_groups');
		DBexecute('select * into temp table media_tmp from media');

		$this->chooseOkOnNextConfirmation();

		$result=DBselect("select userid from users where alias not in ('guest','Admin')");

		while($user=DBfetch($result))
		{
			$id=$user['userid'];

			$this->login('users.php');
			$this->assertTitle('Users');
			$this->dropdown_select('filter_usrgrpid','All');

			$this->checkbox_select("group_userid[$id]");
			$this->dropdown_select('go','Delete selected');
			$this->button_click('goButton');
			$this->wait();

			$this->getConfirmation();
			$this->assertTitle('Users');
			$this->ok('User deleted');

			$sql="select * from users where userid=$id";
			$this->assertEquals(0,$this->DBcount($sql));
			$sql="select * from users_groups where userid=$id";
			$this->assertEquals(0,$this->DBcount($sql));
			$sql="select * from media where userid=$id";
			$this->assertEquals(0,$this->DBcount($sql));
		}

		DBexecute('delete from users');
		DBexecute('insert into users select * from users_tmp');
		DBexecute('drop table users_tmp');
		DBexecute('delete from users_groups');
		DBexecute('insert into users_groups select * from users_groups_tmp');
		DBexecute('drop table users_groups_tmp');
		DBexecute('delete from media');
		DBexecute('insert into media select * from media_tmp');
		DBexecute('drop table media_tmp');
	}

	public function testPageUsers_MassDeleteSpecialUsers()
	{
		DBexecute('select * into temp table users_tmp from users');
		DBexecute('select * into temp table users_groups_tmp from users_groups');
		DBexecute('select * into temp table media_tmp from media');

		$this->chooseOkOnNextConfirmation();

		$result=DBselect("select userid from users where alias in ('guest','Admin')");

		while($user=DBfetch($result))
		{
			$id=$user['userid'];

			$this->login('users.php');
			$this->assertTitle('Users');
			$this->dropdown_select('filter_usrgrpid','All');

			$this->checkbox_select("group_userid[$id]");
			$this->dropdown_select('go','Delete selected');
			$this->button_click('goButton');
			$this->wait();

			$this->getConfirmation();
			$this->assertTitle('Users');
			$this->ok('Cannot delete user');

			$sql="select * from users where userid=$id";
			$this->assertNotEquals(0,$this->DBcount($sql));
			$sql="select * from users_groups where userid=$id";
			$this->assertNotEquals(0,$this->DBcount($sql));
// No media types by default for guest and Admin
//			$sql="select * from media where userid=$id";
//			$this->assertNotEquals(0,$this->DBcount($sql));
		}

		DBexecute('delete from users');
		DBexecute('insert into users select * from users_tmp');
		DBexecute('drop table users_tmp');
		DBexecute('delete from users_groups');
		DBexecute('insert into users_groups select * from users_groups_tmp');
		DBexecute('drop table users_groups_tmp');
		DBexecute('delete from media');
		DBexecute('insert into media select * from media_tmp');
		DBexecute('drop table media_tmp');
	}

	public function testPageUsers_MassUnblock()
	{
// TODO
		$this->markTestIncomplete();
	}
}
?>
