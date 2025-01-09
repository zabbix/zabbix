<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup users
 */
class testFormUserProfile extends CLegacyWebTest {

	protected static $old_password = 'zabbix';

	public function testFormUserProfile_SimpleUpdate() {
		$sqlHashUsers = 'select userid,username,name,surname,passwd,url,autologin,lang,refresh,roleid,theme,attempt_failed,attempt_clock,rows_per_page'
				. ' from users order by userid';
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');

		$this->zbxTestCheckTitle('User profile');

		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Global view');

		$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
	}

	public function testFormUserProfile_Cancel() {
		$sqlHashUsers = 'select userid,username,name,surname,passwd,url,autologin,lang,refresh,roleid,theme,attempt_failed,attempt_clock,rows_per_page'
				. ' from users order by userid';
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');
		$this->zbxTestCheckHeader('User profile: Zabbix Administrator');
		$this->zbxTestInputTypeOverwrite('refresh', '60');

		$this->zbxTestClickWait('cancel');
		$this->zbxTestCheckHeader('Global view');

		$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
	}

	public static function passwords() {
		return [
			[[
				'expected' => TEST_BAD,
				'password1' => '',
				'password2' => '',
				'error_msg' => 'Incorrect value for field "Password": cannot be empty.'
			]],
			[[
				'expected' => TEST_BAD,
				'password1' => 'abcd1234',
				'password2' => 'abCD1235',
				'error_msg' => 'Both passwords must be equal.'
			]],
			[[
				'expected' => TEST_BAD,
				'password1' => '12345',
				'password2' => '123456',
				'error_msg' => 'Both passwords must be equal.'
			]],
			[[
				'expected' => TEST_BAD,
				'old_password' => 'test_123',
				'password1' => "test_123",
				'password2' => "test_123",
				'error_msg' => 'Incorrect current password.'
			]],
			[[
				'expected' => TEST_BAD,
				'old_password' => '',
				'password1' => "'\'$\"\"!$@$#^%$+-=~`\`\\",
				'password2' => "'\'$\"\"!$@$#^%$+-=~`\`\\",
				'error_msg' => 'Incorrect value for field "Current password": cannot be empty.'
			]],
			[[
				'expected' => TEST_GOOD,
				'password1' => "'\'$\"\"!$@$#^%$+-=~`\`\\",
				'password2' => "'\'$\"\"!$@$#^%$+-=~`\`\\"
			]],
			[[
				'expected' => TEST_GOOD,
				'password1' => 'New_PASS_123',
				'password2' => 'New_PASS_123'
			]]
		];
	}

	/**
	 * @dataProvider passwords
	 */
	public function testFormUserProfile_PasswordChange($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');

		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->query('button:Change password')->waitUntilClickable()->one()->click();
		foreach (['current_password', 'password1', 'password2'] as $id) {
			$form->query('id', $id)->waitUntilVisible()->one();
		}
		$form->fill([
				'Current password' => (array_key_exists('old_password', $data)) ? $data['old_password'] : self::$old_password,
				'Password' => $data['password1'],
				'Password (once again)' => $data['password2']
		]);
		$form->submit();

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->page->assertTitle('Zabbix');
				$this->assertTrue($this->query('button:Sign in')->one()->isClickable());
				$this->page->userLogin('Admin', $data['password1']);
				$this->assertTrue($this->query('xpath://a[@title="Admin (Zabbix Administrator)" and text()='.
						'"User settings"]')->exists()
				);
				self::$old_password = $data['password1'];
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , $data['error_msg']);
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
				break;
		}
	}

	public function testFormUserProfile_ThemeChange() {
		$sqlHashUsers = "select * from users where username<>'".PHPUNIT_LOGIN_NAME."' order by userid";
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->page->login();
		$this->page->open('zabbix.php?action=userprofile.edit')->waitUntilReady();

		$this->zbxTestDropdownSelect('theme', 'Blue');
		$this->zbxTestClickWait('update');
		$this->page->waitUntilReady();
		$this->zbxTestCheckHeader('Global view');

		$row = DBfetch(DBselect("select theme from users where username='".PHPUNIT_LOGIN_NAME."'"));
		$this->assertEquals('blue-theme', $row['theme']);

		$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
	}

	public static function refresh() {
		return [
			[[
				'expected' => TEST_BAD,
				'refresh' => ' ',
				'error_msg' => 'Incorrect value for field "refresh": cannot be empty.'
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
			]],
			[[
				'expected' => TEST_GOOD,
				'refresh' => '30s'
			]]
		];
	}

	/**
	 * @dataProvider refresh
	 */
	public function ttestFormUserProfile_RefreshTime($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');

		$this->zbxTestInputTypeOverwrite('refresh', $data['refresh']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Global view');
				$row = DBfetch(DBselect("select refresh from users where username='".PHPUNIT_LOGIN_NAME."'"));
				$this->assertEquals($data['refresh'] , $row['refresh']);
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update user');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
				break;
		}
	}

	public static function autologout() {
		return [
			[[
				'expected' => TEST_BAD,
				'autologout' => ' ',
				'error_msg' => 'Incorrect value for field "autologout": cannot be empty.'
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
				'autologout' => '25h',
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
	public function testFormUserProfile_AutologoutTime($data) {
		$sqlHashUsers = 'select * from users order by userid';
		$oldHashUsers = CDBHelper::getHash($sqlHashUsers);

		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');

		$this->zbxTestCheckboxSelect('autologout_visible', true);
		$this->zbxTestInputTypeOverwrite('autologout', $data['autologout']);
		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Global view');
				$row = DBfetch(DBselect("select autologout from users where username='".PHPUNIT_LOGIN_NAME."'"));
				$this->assertEquals($data['autologout'] , $row['autologout']);
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update user');
				$this->zbxTestTextPresent($data['error_msg']);
				$this->zbxTestCheckTitle('User profile');
				$this->assertEquals($oldHashUsers, CDBHelper::getHash($sqlHashUsers));
				break;
		}
	}
	public static function messaging() {
		return [
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => ' ',
				'error_msg' => 'Incorrect value for field "timeout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => 's',
				'error_msg' => 'Incorrect value for field "timeout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '1.5',
				'error_msg' => 'Incorrect value for field "timeout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '{$DEFAULT_DELAY}',
				'error_msg' => 'Incorrect value for field "timeout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '0',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '1s',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '29',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '25h',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '2d',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '86401',
				'error_msg' => 'Incorrect value for field "timeout": value must be one of 30-86400.'
			]],
			[[
				'expected' => TEST_BAD,
				'messages_disabled' => true,
				'timeout' => '1y',
				'error_msg' => 'Incorrect value for field "timeout": a time unit is expected.'
			]],
			[[
				'expected' => TEST_GOOD,
				'messages_disabled' => true,
				'timeout' => '30s'
			]],
			[[
				'expected' => TEST_GOOD,
				'timeout' => '1m',
				'suppressed' => true
			]],
			[[
				'expected' => TEST_GOOD,
				'timeout' => '1d',
				'suppressed' => false
			]],
			[[
				'expected' => TEST_GOOD
			]]
		];
	}

	/**
	 * @dataProvider messaging
	 */
	public function testFormUserProfile_MessagesTimeout($data) {
		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');
		$this->zbxTestCheckHeader('User profile: Zabbix Administrator');
		$this->zbxTestTabSwitch('Frontend notifications');

		if (array_key_exists('messages_disabled', $data)) {
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_timeout'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//z-select[@id='messages_sounds.repeat']/input[@type='hidden']");
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_triggers.recovery'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//z-select[@id='messages_sounds.recovery']/input[@type='hidden']");
			$this->zbxTestAssertElementPresentXpath("//button[@name='start'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//button[@name='stop'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_show_suppressed'][@disabled]");
		}

		if (array_key_exists('timeout', $data)) {
			$this->zbxTestCheckboxSelect('messages_enabled', true);
			$this->zbxTestInputTypeOverwrite('messages_timeout', $data['timeout']);
			$this->zbxTestAssertElementNotPresentXpath("//z-select[@id='messages_sounds.repeat'][@disabled]");
			$this->zbxTestAssertElementNotPresentXpath("//input[@id='messages_triggers.recovery'][@disabled]");
			$this->zbxTestAssertElementNotPresentXpath("//z-select[@id='messages_sounds.recovery'][@disabled]");
			$this->zbxTestAssertElementNotPresentXpath("//button[@name='start'][@disabled]");
			$this->zbxTestAssertElementNotPresentXpath("//button[@name='stop'][@disabled]");
			$this->zbxTestAssertElementNotPresentXpath("//input[@id='messages_show_suppressed'][@disabled]");
		}
		else {
			$this->zbxTestCheckboxSelect('messages_enabled', false);
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_timeout'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//z-select[@id='messages_sounds.repeat']/input[@type='hidden']");
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_triggers.recovery'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//z-select[@id='messages_sounds.recovery']/input[@type='hidden']");
			$this->zbxTestAssertElementPresentXpath("//button[@name='start'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//button[@name='stop'][@disabled]");
			$this->zbxTestAssertElementPresentXpath("//input[@id='messages_show_suppressed'][@disabled]");
		}

		if (array_key_exists('suppressed', $data)) {
			$this->zbxTestCheckboxSelect('messages_show_suppressed', $data['suppressed']);
		}

		$this->zbxTestClickWait('update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckHeader('Global view');
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' , 'Cannot update user');
				$this->zbxTestTextPresent($data['error_msg']);
				break;
		}
	}

	public static function media() {
		return [
			[[
				'expected' => TEST_BAD,
				'send_to' => '',
				'error_msg' => 'Incorrect value for field "sendto": cannot be empty.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test',
				'period' => ' ',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test@zabbix.com',
				'period' => '0-0,00:00-00:00',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test@zabbix.com',
				'period' => '1-11,00:00-24:00',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test@zabbix.com',
				'period' => '1-7,00:00-25:00',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test@zabbix.com',
				'period' => '1-7,24:00-00:00',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]],
			[[
				'expected' => TEST_BAD,
				'send_to' => 'test@zabbix.com',
				'period' => 'test',
				'error_msg' => 'Incorrect value for field "period": a time period is expected.'
			]]
		];
	}

	/**
	 * @dataProvider media
	 */
	public function testFormUserProfile_Media($data) {
		$this->zbxTestLogin('zabbix.php?action=userprofile.edit');
		$this->zbxTestCheckHeader('User profile: Zabbix Administrator');
		$this->zbxTestTabSwitch('Media');
		$this->zbxTestClickButtonText('Add');
		$this->zbxTestLaunchOverlayDialog('Media');

		if (array_key_exists('type', $data)) {
			$this->zbxTestDropdownSelect('mediatypeid', $data['type']);
		}

		$this->zbxTestInputTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="sendto"]', $data['send_to']);

		if (array_key_exists('period', $data)) {
			$this->zbxTestInputClearAndTypeByXpath('//div[@class="overlay-dialogue-body"]//input[@id="period"]', $data['period']);
		}

		$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]//button[text()="Add"]');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitForPageToLoad();
				COverlayDialogElement::ensureNotPresent();
				$this->zbxTestClickWait('update');
				$this->zbxTestCheckHeader('Global view');
				$sql = "SELECT * FROM media WHERE sendto = '".$data['send_to']."'";
				$this->assertEquals(1, CDBHelper::getCount($sql));
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//div[@class='overlay-dialogue-body']//div[@class='msg-details']"));
				$this->zbxTestTextPresent($data['error_msg']);
				break;
		}
	}
}
