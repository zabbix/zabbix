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

class testFormUserProfile extends CWebTest {

	public function testFormProfile_SimpleUpdate() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->login('profile.php');

		$this->checkTitle('User profile');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_Cancel() {
		$this->login('profile.php');

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');
	}

	public function testFormProfile_PasswordChange() {
		$pwd="'\'$\"\"!$@$#^%$+-=~`\`\\";

		$sqlHashUsers = 'select * from users where alias<>'.zbx_dbstr(PHPUNIT_LOGIN_NAME).' order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1', $pwd);
		$this->input_type('password2', $pwd);

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row = DBfetch(DBselect('select passwd from users where alias='.zbx_dbstr(PHPUNIT_LOGIN_NAME)));
		$this->assertEquals(md5($pwd), $row['passwd']);

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));

		/* set default password */
		$this->open('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1', PHPUNIT_LOGIN_PWD);
		$this->input_type('password2', PHPUNIT_LOGIN_PWD);

		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_EmptyPasswords() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1', '');
		$this->input_type('password2', '');

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Password should not be empty');
		$this->checkTitle('User profile');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_DifferentPasswords() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->login('profile.php');

		$this->button_click('change_password');
		$this->wait();
		$this->input_type('password1', 'abc');
		$this->input_type('password2', 'def');

		$this->button_click('save');
		$this->wait();
		$this->ok('ERROR: Cannot update user. Both passwords must be equal.');
		$this->checkTitle('User profile');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_ThemeChange() {
		$sqlHashUsers = 'select * from users where alias<>'.zbx_dbstr(PHPUNIT_LOGIN_NAME).' order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->login('profile.php');

		$this->dropdown_select('theme', 'Original blue');
		$this->button_click('save');
		$this->wait();
		$this->ok('Copyright');

		$row = DBfetch(DBselect('select theme from users where alias='.zbx_dbstr(PHPUNIT_LOGIN_NAME)));
		$this->assertEquals('originalblue', $row['theme']);

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_GlobalMessagingEnable() {
// TODO
		$this->markTestIncomplete();
/*
		$this->login('profile.php');
		$this->click('messages[enabled]');
		// we wait for the first element after messaging checkbox to appear
		$this->waitForVisible('timeout_row');
		// and then check that the remaining elements are there as well
		$this->ok('Play sound');
		$this->ok('Trigger severity');
		$this->ok('Recovery');
		$this->ok('Not classified');
		$this->ok('Information');
		$this->ok('Warning');
		$this->ok('Average');
		$this->ok('High');
		$this->ok('Disaster');
		// should also save profile & revisit to check that it's still the same
		// probably also should check in the db saved data
		// and maybe individually toggle priority checkboxes and check in the db...
*/
	}
}
?>
