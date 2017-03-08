<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
		$sqlHashUsers = 'select userid,alias,name,surname,passwd,url,autologin,lang,refresh,type,theme,attempt_failed,attempt_clock,rows_per_page'
				. ' from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestCheckTitle('User profile');

		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Dashboard');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public function testFormProfile_Cancel() {
		$sqlHashUsers = 'select userid,alias,name,surname,passwd,url,autologin,lang,refresh,type,theme,attempt_failed,attempt_clock,rows_per_page'
				. ' from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');
		$this->zbxTestCheckHeader('User profile: Zabbix Administrator');
		$this->zbxTestInputTypeOverwrite('refresh', '60');

		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckHeader('Dashboard');

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public static function passwords() {
		return [
			[[
				'expected' => TEST_BAD,
				'password1' => '',
				'password2' => '',
				'error_msg' => 'Password should not be empty'
			]],
			[[
				'expected' => TEST_BAD,
				'password1' => 'abc',
				'password2' => 'def',
				'error_msg' => 'Cannot update user. Both passwords must be equal.'
			]],
			[[
				'expected' => TEST_BAD,
				'password1' => '12345',
				'password2' => '123456',
				'error_msg' => 'Cannot update user. Both passwords must be equal.'
			]],
			[[
				'expected' => TEST_GOOD,
				'password1' => "'\'$\"\"!$@$#^%$+-=~`\`\\",
				'password2' => "'\'$\"\"!$@$#^%$+-=~`\`\\"
			]],
			[[
				'expected' => TEST_GOOD,
				'password1' => PHPUNIT_LOGIN_PWD,
				'password2' => PHPUNIT_LOGIN_PWD
			]],
		];
	}

	/**
	 * @dataProvider passwords
	 */
	public function testFormProfile_PasswordChange($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestClickXpathWait("//ul[@id='userFormList']//button[contains(@onclick, 'change_password')]");
		$this->zbxTestInputTypeWait('password1', $data['password1']);
		$this->zbxTestInputType('password2', $data['password2']);

		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Dashboard');
				$row = DBfetch(DBselect("select passwd from users where alias='".PHPUNIT_LOGIN_NAME."'"));
				$this->assertEquals(md5($data['password1']), $row['passwd']);
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , $data['error_msg']);
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
				break;
		}
	}

	public function testFormProfile_ThemeChange() {
		$sqlHashUsers = "select * from users where alias<>'".PHPUNIT_LOGIN_NAME."' order by userid";
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestDropdownSelect('theme', 'Blue');
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Dashboard');

		$row = DBfetch(DBselect("select theme from users where alias='".PHPUNIT_LOGIN_NAME."'"));
		$this->assertEquals('blue-theme', $row['theme']);

		$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
	}

	public static function refresh() {
		return [
			[[
				'expected' => TEST_BAD,
				'refresh' => ' ',
				'error_msg' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => 's',
				'error_msg' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => '1.5',
				'error_msg' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => '{$DEFAULT_DELAY}',
				'error_msg' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => '1d',
				'error_msg' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => '36001',
				'error_msg' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
			]],
			[[
				'expected' => TEST_BAD,
				'refresh' => '1y',
				'error_msg' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '0'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '1'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '3600'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '1s'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '1m'
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '1h'
			]]
		];
	}

	/**
	 * @dataProvider refresh
	 */
	public function testFormProfile_RefreshTime($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestInputTypeOverwrite('refresh', $data['refresh']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Dashboard');
				$row = DBfetch(DBselect("select refresh from users where alias='".PHPUNIT_LOGIN_NAME."'"));
				$this->assertEquals($data['refresh'] , $row['refresh']);
				$this->zbxTestCheckFatalErrors();
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update user');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckFatalErrors();
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
				break;
		}
	}

	public static function autologout() {
		return [
			[[
				'expected' => TEST_BAD,
				'autologout' => ' ',
				'error_msg' => 'Invalid parameter "/1/autologout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => 's',
				'error_msg' => 'Invalid parameter "/1/autologout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '1.5',
				'error_msg' => 'Invalid parameter "/1/autologout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '{$DEFAULT_DELAY}',
				'error_msg' => 'Invalid parameter "/1/autologout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '1s',
				'error_msg' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '1m',
				'error_msg' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '2d',
				'error_msg' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '89',
				'error_msg' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '86401',
				'error_msg' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'autologout' => '1y',
				'error_msg' => 'Invalid parameter "/1/autologout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_GOOD,
				'autologout' => '0'
			]],
			[[
				'expected' => TEST_GOOD,
				'autologout' => '90'
			]],
			[[
				'expected' => TEST_GOOD,
				'autologout' => '10000'
			]],
			[[
				'expected' => TEST_GOOD,
				'autologout' => '2m'
			]],
			[[
				'expected' => TEST_GOOD,
				'autologout' => '1d'
			]]
		];
	}

	/**
	 * @dataProvider autologout
	 */
	public function testFormProfile_AutologoutTime($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = DBhash($sqlHashUsers);

		$this->zbxTestLogin('profile.php');

		$this->zbxTestCheckboxSelect('autologout_visible', true);
		$this->zbxTestInputTypeOverwrite('autologout', $data['autologout']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Dashboard');
				$this->zbxTestCheckFatalErrors();
				$row = DBfetch(DBselect("select autologout from users where alias='".PHPUNIT_LOGIN_NAME."'"));
				$this->assertEquals($data['autologout'] , $row['autologout']);
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update user');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckFatalErrors();
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, DBhash($sqlHashUsers));
				break;
		}
	}
}
