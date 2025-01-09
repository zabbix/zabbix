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

/**
 * @backup usrgrp
 *
 * @dataSource LoginUsers
 */
class testFormUserGroups extends CLegacyWebTest {
	private $userGroup = 'Selenium user group';

	public function testFormUserGroups_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestClickButtonText('Create user group');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestTextPresent(['Group name', 'Users', 'Frontend access', 'Enabled', 'Debug mode']);
		$this->zbxTestAssertAttribute('//input[@id="name"]', 'maxlength', '64');

		$this->zbxTestDropdownHasOptions('gui_access', ['System default', 'Internal', 'LDAP', 'Disabled']);
		$this->zbxTestDropdownAssertSelected('gui_access', 'System default');
		$this->zbxTestCheckboxSelected('users_status');
	}

	public static function create() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => '',
					'error_msg' => 'Cannot add user group',
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error_msg' => 'Cannot add user group',
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
					'error_msg' => 'Cannot add user group',
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium test add admin in group with disabled GUI access',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'frontend_access' => 'Disabled',
					'error_msg' => 'Cannot add user group',
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
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
	public function testFormUserGroups_Create($data) {
		$this->zbxTestLogin('zabbix.php?action=usergroup.edit');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestInputTypeOverwrite('name', $data['name']);
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
		$this->page->waitUntilReady();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot add user group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User group added');
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, CDBHelper::getCount($sql));

				if (array_key_exists('user_group', $data)) {
					$groupid = DBfetch(DBselect($sql));
					$users = "SELECT id FROM users_groups WHERE usrgrpid=".$groupid['usrgrpid'];
					$this->assertEquals(1, CDBHelper::getCount($users));
				}
				break;

			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestTextPresent($data['error']);
				break;
		}
	}

	public static function update() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => ' ',
					'error_msg' => 'Cannot update user group',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error_msg' => 'Cannot update user group',
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
					'error_msg' => 'Cannot update user group',
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Selenium group update, admin in group with disabled GUI access',
					'user_group' => 'Zabbix administrators',
					'user' => 'Admin',
					'frontend_access' => 'Disabled',
					'error_msg' => 'Cannot update user group',
					'error' => 'User cannot add oneself to a disabled group or a group with disabled GUI access.'
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
	public function testFormUserGroups_Update($data) {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestClickLinkTextWait($this->userGroup);
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestInputTypeOverwrite('name', $data['name']);
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

		$this->zbxTestClickButton('usergroup.update');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot update user group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User group updated');
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, CDBHelper::getCount($sql));

				if (array_key_exists('user_group', $data)) {
					$groupid = DBfetch(DBselect($sql));
					$users = "SELECT id FROM users_groups WHERE usrgrpid=".$groupid['usrgrpid'];
					$this->assertEquals(1, CDBHelper::getCount($users));
				}
				break;

			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error_msg']);
				$this->zbxTestTextPresent($data['error']);
				break;
		}
	}

	public static function delete() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Disabled'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Zabbix administrators',
					'error' => 'User group "Zabbix administrators" is used in'
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
	public function testFormUserGroups_Delete($data) {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestClickLinkTextWait($data['name']);
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestClickXpath("//button[@id='cancel']/../button[@id='delete']");
		$this->zbxTestAcceptAlert();

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of user groups');
				$this->zbxTestCheckHeader('User groups');
				$this->zbxTestTextNotPresent(['Page received incorrect data', 'Cannot delete user group']);
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User group deleted');
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(0, CDBHelper::getCount($sql));
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete user group');
				$this->zbxTestTextPresent($data['error']);
				$sql = "SELECT usrgrpid FROM usrgrp WHERE name='".$data['name']."'";
				$this->assertEquals(1, CDBHelper::getCount($sql));
				break;
		}
	}
}
