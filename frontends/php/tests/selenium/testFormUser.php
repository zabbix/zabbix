<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CLegacyWebTest.php';

class testFormUser extends CLegacyWebTest {

	public function getCreateData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Alias' => 'Mandatory_user',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Admin',
						'Groups' => 'Zabbix administrators',
						'Password' => '123',
						'Password (once again)' => '123'
					],
					'error_details' => 'User with alias "Admin" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Negative_Test',
						'Groups' => 'Zabbix administrators'
					],
					'error_details' => 'Incorrect value for field "passwd": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Negative_Test',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_details' => 'Invalid parameter "/1/usrgrps": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Negative_Test',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix'
					],
					'error_details' => 'Cannot add user. Both passwords must be equal.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Negative_Test',
						'Groups' => 'Zabbix administrators',
						'Password (once again)' => 'Zabbix'
					],
					'error_details' => 'Cannot add user. Both passwords must be equal.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => '',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_details' => 'Incorrect value for field "Alias": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => 'Empty_Refresh',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_details' => 'Invalid parameter "/1/refresh": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => '0RowsPerPage',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_details' => 'Incorrect value "0" for "Rows per page" field: must be between 1 and 999999.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Alias' => '89_seconds_logout',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 * @backup users
	 */
	public function testFormUser_Create($data) {
		$this->page->login()->open('users.php?form=create');
		$form = $this->query('name:userForm')->asForm()->one();
		$form->fill($data['fields']);

		if ($data['fields']['Alias'] === 'Empty_Refresh') {
			$form->getField('Refresh')->clear();
		}
		if ($data['fields']['Alias'] === '0RowsPerPage') {
			$form->getField('Rows per page')->fill('0');
		}
		if ($data['fields']['Alias'] === '89_seconds_logout') {
			$auto_logout = $form->getFieldElements('Auto-logout');
			$auto_logout->get(0)->asCheckbox()->check();
			$auto_logout->get(3)->overwrite('89s');
		}
		$form->submit();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();

		if ($data['expected'] === TEST_GOOD) {
			$this->assertTrue($message->isGood());
			$this->assertEquals('User added', $message->getTitle());
			$sql = "SELECT * FROM users WHERE alias = " . zbx_dbstr($data['fields']['Alias']);
			$this->assertEquals(1, CDBHelper::getCount($sql), 'The correctly created user was not added');
		}
		else {
			$this->assertTrue($message->isBad());
			if (sizeof($message->getLines()->asText()) == 0) {
				$this->assertContains($data['error_details'], $message->getTitle());
			}
			else {
				$this->assertTrue($message->hasLine($data['error_details']));
			}
		}
	}

	public function getCreateToUpdateData() {
		return [
			[
				[
					'fields' => [
						'Alias' => 'Detailed_user',
						'Name' => 'Bugs',
						'Surname' => 'Bunny',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix',
						'Theme' => 'Dark',
						'Auto-login' => false, // change to "true," when ZBX-16104 is fixed
						'Rows per page' => '5',
						'URL (after login)' => 'screenconf.php'
					],
					'new_fields' => [
						'Name' => 'Road',
						'Surname' => 'Runner',
						'Groups' => 'Selenium user group',
						'Language' => 'English (en_US)',
						'Theme' => 'High-contrast light',
						'Auto-login' => true,
						'Refresh' => '60s',
						'Rows per page' => '2',
						'URL (after login)' => 'sysmaps.php'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateToUpdateData
	 * @backup users
	 */
	public function testFormUser_CreateWithOptionalFields($data) {
		$this->page->login()->open('users.php?form=create');
		$form = $this->query('name:userForm')->asForm()->one();
		$form->fill($data['fields']);

		// Set Auto-logout to 10m
		$auto_logout = $form->getFieldElements('Auto-logout');
		$auto_logout->get(0)->asCheckbox()->check();
		$auto_logout->get(3)->overwrite('10m');

		//verify that Auto-login is unchecked after setting Auto-logout
		$checkbox = $form->getField('Auto-login');
		$this->assertTrue($checkbox->isChecked(false));
		$form->submit();

		// Log in with the created user
		$this->page->query('class:top-nav-signout')->one()->click();
		$this->webDriver->manage()->deleteAllCookies();
		$this->page->query('id:enter')->waitUntilTextPresent('Sign in');
		$this->page->query('id:name')->asElement()->one()->fill($data['fields']['Alias']);
		$this->page->query('id:password')->asElement()->one()->fill($data['fields']['Password']);
		$this->page->query('button:Sign in')->one()->click();

		// verification of URL after login
		$login_url = $this->page->getCurrentURL();
		$this->assertContains($data['fields']['URL (after login)'], $login_url);

		// verification of the number of rows per page parameter
		$screen_table = $this->query('name:screenForm')->asTable()->one();
		$rows = $screen_table->getRows();
		$this->assertEquals($data['fields']['Rows per page'], $rows->count());

		// verification of default theme
		$sql_theme = "SELECT theme from users where alias ='" . $data['fields']['Alias'] . "'";
		$theme = CDBHelper::getValue($sql_theme);
		$this->assertEquals('dark-theme', $theme);

		// verification of auto-logout
		$sql_autologout = "SELECT autologout FROM users WHERE alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->assertEquals('10m', CDBHelper::getValue($sql_autologout), 'Unexpected outcome: The Auto-logout option was not set');

		// verify that autologin is not set
		$sql_autologin = "SELECT autologin FROM users WHERE alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->assertEquals(0, CDBHelper::getValue($sql_autologin), 'Unexpected outcome: The Auto-login option was set');
	}

	/**
	 * @dataProvider getCreateToUpdateData
	 * @backup users
	 */
	public function testFormUser_Update($data) {

		$this->page->login()->open('users.php?form=create');
		$form_create = $this->query('name:userForm')->asForm()->one();
		$form_create->fill($data['fields']);
		$auto_logout = $form_create->getFieldElements('Auto-logout');
		$auto_logout->get(0)->asCheckbox()->check();
		$auto_logout->get(3)->overwrite('10m');
		$form_create->submit();

		// Get userid and open the user for update
		$sql_userid = "SELECT userid FROM users where alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->page->open('users.php?form=update&userid=' . CDBHelper::getValue($sql_userid));

		// update user parameters
		$form_update = $this->query('name:userForm')->asForm()->one();
		$form_update->fill($data['new_fields']);
		$form_update->submit();
		$this->page->waitUntilReady();
		$ok_message = CMessageElement::find()->one();
		$this->assertEquals('User updated', $ok_message->getTitle());

		// Log in as the updated user
		$this->page->query('class:top-nav-signout')->one()->click();
		$this->webDriver->manage()->deleteAllCookies();
		$this->page->query('id:enter')->waitUntilTextPresent('Sign in');
		$this->page->query('id:name')->asElement()->one()->fill($data['fields']['Alias']);
		$this->page->query('id:password')->asElement()->one()->fill($data['fields']['Password']);
		$this->page->query('button:Sign in')->one()->click();

		// verification of URL after login
		$login_url = $this->page->getCurrentURL();
		$this->assertContains($data['new_fields']['URL (after login)'], $login_url);

		// verification of the number of rows per page parameter
		$this->page->open('screenconf.php');
		$screen_table = $this->query('name:screenForm')->asTable()->one();
		$rows = $screen_table->getRows();
		$this->assertEquals($data['new_fields']['Rows per page'], $rows->count());

		// verification of usergroup after update
		$sql_group = "SELECT name FROM usrgrp WHERE usrgrpid IN (SELECT usrgrpid FROM users_groups WHERE userid IN"
				. "(SELECT userid FROM users WHERE alias = '" . $data['fields']['Alias'] . "'))";
		$this->assertEquals(CDBHelper::getValue($sql_group), $data['new_fields']['Groups']);

		// verification of other modifier parameters
		$sql_params = "SELECT name,surname,theme,autologin,autologout,lang,refresh from users where alias ='" . $data['fields']['Alias'] . "'";
		$params_arr = CDBHelper::getAll($sql_params);
		$etalon_arr = [
			[
				'name' => 'Road',
				'surname' => 'Runner',
				'theme' => 'hc-light',
				'autologin' => "1",
				'autologout' => "0",
				'lang' => 'en_US',
				'refresh' => '60s'
			]
		];
		$this->assertEquals($params_arr, $etalon_arr);
	}

	/**
	 * @dataProvider getCreateToUpdateData
	 * @backup users
	 */
	public function testFormUser_PasswordUpdate($data) {

		$this->page->login()->open('users.php?form=create');
		$form_create = $this->query('name:userForm')->asForm()->one();
		$form_create->fill($data['fields']);
		$form_create->submit();

		// Get userid and open the user for update
		$sql_userid = "SELECT userid FROM users where alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->page->open('users.php?form=update&userid=' . CDBHelper::getValue($sql_userid));
		$this->page->query('button:Change password')->one()->click();

		// Change user password and log out
		$form_update = $this->query('name:userForm')->asForm()->one();
		$form_update->getField('Password')->fill('new_password');
		$form_update->getField('Password (once again)')->fill('new_password');
		$form_update->submit();
		$this->page->query('class:top-nav-signout')->one()->click();
		$this->webDriver->manage()->deleteAllCookies();

		// Atempt to sign in with old password
		$this->page->query('id:enter')->waitUntilTextPresent('Sign in');
		$this->page->query('id:name')->asElement()->one()->fill($data['fields']['Alias']);
		$this->page->query('id:password')->asElement()->one()->fill($data['fields']['Password']);
		$this->page->query('button:Sign in')->one()->click();
		$message = $this->page->query('class:red')->one()->getText();
		$this->assertEquals($message, 'Login name or password is incorrect.');

		// sign in with new password
		$this->page->query('id:name')->asElement()->one()->fill($data['fields']['Alias']);
		$this->page->query('id:password')->asElement()->one()->fill('new_password');
		$this->page->query('button:Sign in')->one()->click();
		$attempt_message = CMessageElement::find()->one();
		$this->assertTrue($attempt_message->hasLine('1 failed login attempt logged. Last failed attempt was from'));
	}

	/**
	 * @dataProvider getCreateToUpdateData
	 * @backup users
	 */
	public function testFormUser_Delete($data) {

		$this->page->login()->open('users.php?form=create');
		$form = $this->query('name:userForm')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();

		$sql_userid = "SELECT userid FROM users where alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->page->open('users.php?form=update&userid=' . CDBHelper::getValue($sql_userid));
		$this->page->query('button:Delete')->one()->click();
		$this->webDriver->switchTo()->alert()->accept();

		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertEquals('User deleted', $message->getTitle());
		$sql = "SELECT * FROM users WHERE alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->assertEquals(0, CDBHelper::getCount($sql), 'The user was not deleted');
	}

	/**
	 * @dataProvider getCreateToUpdateData
	 * @backup users
	 */
	public function testFormUser_Cancel($data) {

		$this->page->login()->open('users.php?form=create');

		// Check cancellation when creating users
		$form_create = $this->query('name:userForm')->asForm()->one();
		$form_create->fill($data['fields']);
		$this->page->query('button:Cancel')->one()->click();

		$cancel_url = $this->page->getCurrentURL();
		$this->assertContains('users.php?cancel=1', $cancel_url);
		$sql = "SELECT * FROM users WHERE alias = " . zbx_dbstr($data['fields']['Alias']);
		$this->assertEquals(0, CDBHelper::getCount($sql));

		//Check Cancellation when updating users
		$this->page->open('users.php?form=update&userid=1');
		$this->page->query('id:name')->asElement()->one()->fill('Boris');
		$this->page->query('button:Cancel')->one()->click();

		$sql_name = "SELECT name FROM users WHERE alias = 'Admin'";
		$this->assertEquals("Zabbix", CDBHelper::getValue($sql_name));
	}
}
