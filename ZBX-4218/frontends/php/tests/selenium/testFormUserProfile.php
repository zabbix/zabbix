<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class testFormUserProfile extends CWebTest {

	public function testFormProfile_SimpleUpdate() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->checkTitle('User profile');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Copyright');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_Cancel() {
		$this->zbxTestLogin('profile.php');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Copyright');
	}

	public function testFormProfile_PasswordChange() {
		$pwd="'\'$\"\"!$@$#^%$+-=~`\`\\";

		$sqlHashUsers = 'select * from users where alias<>'.zbx_dbstr(PHPUNIT_LOGIN_NAME).' order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestClickWait('change_password');
		$this->input_type('password1', $pwd);
		$this->input_type('password2', $pwd);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Copyright');

		$row = DBfetch(DBselect('select passwd from users where alias='.zbx_dbstr(PHPUNIT_LOGIN_NAME)));
		$this->assertEquals(md5($pwd), $row['passwd']);

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));

		/* set default password */
		$this->zbxTestOpen('profile.php');

		$this->zbxTestClickWait('change_password');
		$this->input_type('password1', PHPUNIT_LOGIN_PWD);
		$this->input_type('password2', PHPUNIT_LOGIN_PWD);

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Copyright');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_EmptyPasswords() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestClickWait('change_password');
		$this->input_type('password1', '');
		$this->input_type('password2', '');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Password should not be empty');
		$this->checkTitle('User profile');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_DifferentPasswords() {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestClickWait('change_password');
		$this->input_type('password1', 'abc');
		$this->input_type('password2', 'def');

		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('ERROR: Cannot update user. Both passwords must be equal.');
		$this->checkTitle('User profile');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_ThemeChange() {
		$sqlHashUsers = 'select * from users where alias<>'.zbx_dbstr(PHPUNIT_LOGIN_NAME).' order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestDropdownSelect('theme', 'Original blue');
		$this->zbxTestClickWait('save');
		$this->zbxTestTextPresent('Copyright');

		$row = DBfetch(DBselect('select theme from users where alias='.zbx_dbstr(PHPUNIT_LOGIN_NAME)));
		$this->assertEquals('originalblue', $row['theme']);

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_GlobalMessagingEnable() {
// TODO
		$this->markTestIncomplete();
/*
		$this->zbxTestLogin('profile.php');
		$this->click('messages[enabled]');
		// we wait for the first element after messaging checkbox to appear
		$this->waitForVisible('timeout_row');
		// and then check that the remaining elements are there as well
		$this->zbxTestTextPresent('Play sound');
		$this->zbxTestTextPresent('Trigger severity');
		$this->zbxTestTextPresent('Recovery');
		$this->zbxTestTextPresent('Not classified');
		$this->zbxTestTextPresent('Information');
		$this->zbxTestTextPresent('Warning');
		$this->zbxTestTextPresent('Average');
		$this->zbxTestTextPresent('High');
		$this->zbxTestTextPresent('Disaster');
		// should also save profile & revisit to check that it's still the same
		// probably also should check in the db saved data
		// and maybe individually toggle priority checkboxes and check in the db...
*/
	}
}
