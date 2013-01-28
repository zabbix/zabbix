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

class testPageUsers extends CWebTest {
	// Returns all users
	public static function allUsers() {
		return DBdata('select * from users');
	}

	/**
	* @dataProvider allUsers
	*/
	public function testPageUsers_CheckLayout($user) {
		$this->login('users.php');
		$this->checkTitle('Configuration of users');

		$this->dropdown_select_wait('filter_usrgrpid', 'All');

		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');
		$this->ok('Displaying');
		$this->ok(array('Alias', 'Name', 'Surname', 'User type', 'Groups', 'Is online?', 'Login', 'Frontend access', 'Debug mode', 'Status'));
		$this->ok(array($user['alias'], $user['name'], $user['surname']));
		$this->dropdown_select('go', 'Unblock selected');
		$this->dropdown_select('go', 'Delete selected');
	}

	public function testPageUsers_FilterByHostGroup() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUsers_Sorting() {
// TODO
		$this->markTestIncomplete();
	}

	/**
	* @dataProvider allUsers
	*/
	public function testPageUsers_SimpleUpdate($user) {
		$userid = $user['userid'];
		$alias = $user['alias'];

		$sqlHashUser = 'select * from users where userid='.$userid;
		$oldHashUser = DBhash($sqlHashUser);
		$sqlHashGroup = 'select * from users_groups where userid='.$userid.' order by id';
		$oldHashGroup = DBhash($sqlHashGroup);
		$sqlHashMedia = 'select * from media where userid='.$userid.' order by mediaid';
		$oldHashMedia = DBhash($sqlHashMedia);

		$this->login('users.php');
		$this->checkTitle('Configuration of users');
		$this->dropdown_select_wait('filter_usrgrpid', 'All');

		$this->click('link='.$alias);
		$this->wait();
		$this->button_click('save');
		$this->wait();
		$this->checkTitle('Configuration of users');
		$this->ok('User updated');
		$this->ok($alias);
		$this->ok('CONFIGURATION OF USERS AND USER GROUPS');

		$this->assertEquals($oldHashUser, DBhash($sqlHashUser));
		$this->assertEquals($oldHashGroup, DBhash($sqlHashGroup), 'Chuck Norris: User update changed data in table users_groups');
		$this->assertEquals($oldHashMedia, DBhash($sqlHashMedia), 'Chuck Norris: User update changed data in table medias');
	}

	public function testPageUsers_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUsers_MassDelete() {
		DBsave_tables('users');

		$this->chooseOkOnNextConfirmation();

		$result=DBselect("select userid from users where alias not in ('guest','Admin')");

		while ($user = DBfetch($result)) {
			$id = $user['userid'];

			$this->login('users.php');
			$this->checkTitle('Configuration of users');
			$this->dropdown_select_wait('filter_usrgrpid', 'All');

			$this->checkbox_select("group_userid[$id]");
			$this->dropdown_select('go', 'Delete selected');
			$this->button_click('goButton');
			$this->wait();

			$this->getConfirmation();
			$this->checkTitle('Configuration of users');
			$this->ok('User deleted');

			$sql = "select * from users where userid=$id";
			$this->assertEquals(0, DBcount($sql), "Chuck Norris: user $id deleted but still exists in table users");
			$sql = "select * from users_groups where userid=$id";
			$this->assertEquals(0, DBcount($sql), "Chuck Norris: user $id deleted but still exists in table users_groups");
			$sql = "select * from media where userid=$id";
			$this->assertEquals(0, DBcount($sql), "Chuck Norris: user $id deleted but still exists in table media");
		}

		DBrestore_tables('users');
	}

	public function testPageUsers_MassDeleteSpecialUsers() {
		DBsave_tables('users');

		$this->chooseOkOnNextConfirmation();

		$result = DBselect("select userid from users where alias in ('guest','Admin')");

		while ($user = DBfetch($result)) {
			$id = $user['userid'];

			$this->login('users.php');
			$this->checkTitle('Configuration of users');
			$this->dropdown_select_wait('filter_usrgrpid', 'All');

			$this->checkbox_select("group_userid[$id]");
			$this->dropdown_select('go', 'Delete selected');
			$this->button_click('goButton');
			$this->wait();

			$this->getConfirmation();
			$this->checkTitle('Configuration of users');
			$this->ok('Cannot delete user');

			$sql = "select * from users where userid=$id";
			$this->assertNotEquals(0, DBcount($sql));
			$sql = "select * from users_groups where userid=$id";
			$this->assertNotEquals(0, DBcount($sql));
// No media types by default for guest and Admin
//			$sql = "select * from media where userid=$id";
//			$this->assertNotEquals(0, DBcount($sql));
		}

		DBrestore_tables('users');
	}

	public function testPageUsers_MassUnblockAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageUsers_MassUnblock() {
// TODO
		$this->markTestIncomplete();
	}
}
?>
