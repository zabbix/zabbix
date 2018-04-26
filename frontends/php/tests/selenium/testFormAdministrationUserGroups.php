<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class testFormAdministrationUserGroups extends CWebTest {
	private $userGroup = 'Selenium user group';

	public function testFormAdministrationUserGroups_CheckLayout() {
		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestClickButtonText('Create user group');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestTextPresent(['Group name', 'Users', 'Frontend access', 'Enabled', 'Debug mode']);
		$this->zbxTestAssertAttribute('//input[@id=\'gname\']', 'maxlength', '64');

		$this->zbxTestDropdownHasOptions('gui_access', ['System default', 'Internal', 'Disabled']);
		$this->zbxTestDropdownAssertSelected('gui_access', 'System default');
		$this->zbxTestCheckboxSelected('users_status');
	}

	public static function create() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'error_msg' => 'Page received incorrect data',
					'error' => 'Incorrect value for field "Group name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error_msg' => 'Cannot add group',
					'error' => 'User group "Zabbix administrators" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium test add admin in disabled group',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'enabled' => false,
					'error_msg' => 'Cannot add group',
					'error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium test add admin in group with disabled GUI access',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'frontend_access' => 'Disabled',
					'error_msg' => 'Cannot add group',
					'error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Selenium test create user group'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Selenium test create user group with different properties',
					'user_group' => 'Guests',
					'user' => 'test-user',
					'frontend_access' => 'Disabled',
					'enabled' => false,
					'debug_mode' => true
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormAdministrationUserGroups_Create($data) {
		$this->zbxTestLogin('usergrps.php?form=Create+user+group');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestInputTypeOverwrite('gname', $data['name']);
		if (array_key_exists('user_group', $data)) {
			$this->zbxTestClickButtonMultiselect('userids_');
			$this->zbxTestLaunchOverlayDialog('Users');
			$this->zbxTestClickLinkTextWait($data['user']);
		}

		if (array_key_exists('frontend_access', $data)) {
			$this->zbxTestDropdownSelect('gui_access', $data['frontend_access']);
		}

		if (array_key_exists('enabled', $data)) {
			$this->zbxTestCheckboxSelect('users_status', $data['enabled']);
		}

		if (array_key_exists('debug_mode', $data)) {
			$this->zbxTestCheckboxSelect('debug_mode', $data['debug_mode']);
		}

		$this->zbxTestClickXpath("//button[@id='cancel']/../button[@id='add']");

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group added');
				$this->zbxTestCheckFatalErrors();
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, DBcount($sql));

				if (array_key_exists('user_group', $data)) {
					$groupid = DBfetch(DBselect($sql));
					$users = "SELECT id FROM users_groups WHERE usrgrpid=".$groupid['usrgrpid'];
					$this->assertEquals(1, DBcount($users));
				}
				break;

			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestTextPresent($data['error']);
				$this->zbxTestCheckFatalErrors();
				break;
		}
	}

	public static function update() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => ' ',
					'error_msg' => 'Page received incorrect data',
					'error' => 'Incorrect value for field "Group name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error_msg' => 'Cannot update group',
					'error' => 'User group "Zabbix administrators" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium test group update, admin in disabled group',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'enabled' => false,
					'error_msg' => 'Cannot update group',
					'error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium group update, admin in group with disabled GUI access',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'frontend_access' => 'Disabled',
					'error_msg' => 'Cannot update group',
					'error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Selenium test update user group with different properties',
					'user_group' => 'Guests',
					'user' => 'test-user',
					'frontend_access' => 'Disabled',
					'enabled' => false,
					'debug_mode' => true
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 */
	public function testFormAdministrationUserGroups_Update($data) {
		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestClickLinkTextWait($this->userGroup);
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestInputTypeOverwrite('gname', $data['name']);
		if (array_key_exists('user_group', $data)) {
			$this->zbxTestClickButtonMultiselect('userids_');
			$this->zbxTestLaunchOverlayDialog('Users');
			$this->zbxTestClickLinkTextWait($data['user']);
		}

		if (array_key_exists('frontend_access', $data)) {
			$this->zbxTestDropdownSelect('gui_access', $data['frontend_access']);
		}

		if (array_key_exists('enabled', $data)) {
			$this->zbxTestCheckboxSelect('users_status', $data['enabled']);
		}

		if (array_key_exists('debug_mode', $data)) {
			$this->zbxTestCheckboxSelect('debug_mode', $data['debug_mode']);
		}

		$this->zbxTestClickButton('Update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot update group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group updated');
				$this->zbxTestCheckFatalErrors();
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, DBcount($sql));

				if (array_key_exists('user_group', $data)) {
					$groupid = DBfetch(DBselect($sql));
					$users = "SELECT id FROM users_groups WHERE usrgrpid=".$groupid['usrgrpid'];
					$this->assertEquals(1, DBcount($users));
				}
				break;

			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestTextPresent($data['error']);
				$this->zbxTestCheckFatalErrors();
				break;
		}
	}

	public static function delete() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Disabled',
					'error' => 'User "disabled-user" cannot be without user group.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error' => 'User group "Zabbix administrators" is used in "Report problems to Zabbix administrators" action.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium user group in scripts',
					'error' => 'User group "Selenium user group in scripts" is used in script "Selenium script".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium user group in configuration',
					'error' => 'User group "Selenium user group in configuration" is used in configuration for database down messages.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Selenium test update user group with different properties'
				]
			]
		];
	}

	/**
	 * @dataProvider delete
	 */
	public function testFormAdministrationUserGroups_Delete($data) {
		$this->zbxTestLogin('usergrps.php');
		$this->zbxTestClickLinkTextWait($data['name']);
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestClickXpath("//button[@id='cancel']/../button[@id='delete']");
		$this->zbxTestAcceptAlert();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot delete group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Group deleted');
				$this->zbxTestCheckFatalErrors();
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(0, DBcount($sql));
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete group');
				$this->zbxTestTextPresent($data['error']);
				$this->zbxTestCheckFatalErrors();
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, DBcount($sql));
				break;
		}
	}
}
