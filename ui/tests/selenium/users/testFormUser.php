<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup users
 */
class testFormUser extends CWebTest {

	public function getCreateData() {
		return [
			// Username is already taken by another user.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Admin',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'User with username "Admin" already exists.'
				]
			],
			// Empty 'Username' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => '',
						'Groups' => 'Zabbix administrators',
						'Password' => 'zabbix',
						'Password (once again)' => 'zabbix'
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "username": cannot be empty.'
				]
			],
			// Space as 'Username' field value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => '   ',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "username": cannot be empty.'
				]
			],
			// Empty 'Group' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test1',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Field "user_groups" is mandatory.'
				]
			],
			// 'Password' fields not specified.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test2',
						'Groups' => 'Zabbix administrators'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "Password": cannot be empty.'
				]
			],
			// Empty 'Password (once again)' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test3',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Password' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test4',
						'Groups' => 'Zabbix administrators',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// 'Password' and 'Password (once again)' do not match.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test5',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'tEST5678'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test6',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => ''
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "refresh": cannot be empty.'
				]
			],
			// Digits in value of the 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test7',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '123abc'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// Value of the 'Refresh' field too large.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test8',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '3601'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test_2h',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '2h'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test_61m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '61m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			// Non-time unit value in 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test9',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => '00000000000001'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// 'Rows per page' field equal to '0'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test10',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Rows per page' => '0'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// Non-numeric value of 'Rows per page' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test11',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Rows per page' => 'abc123'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// 'Autologout' below minimal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test12',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '89'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test12_1m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' above maximal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '86401'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13_1441m',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1441m'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test13_25h',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '25h'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' with a non-numeric value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test14',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => 'ninety'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/autologout": a time unit is expected.'
				]
			],
			// 'Autologout' with an empty value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test15',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => ''
					],
					'error_title' => 'Cannot add user',
					'error_details' => 'Incorrect value for field "autologout": cannot be empty.'
				]
			],
			// URL unacceptable.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test16',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'URL (after login)' => 'javascript:alert(123);'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Incorrect URL protocol.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Negative_Test19',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'URL (after login)' => 'snmp://zabbix.com'
					],
					'role' => 'Super admin role',
					'error_title' => 'Cannot add user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Creating user by specifying only mandatory parameters.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Mandatory_user',
						'Groups' => 'Guests',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'role' => 'Guest role'
				]
			],
			// Creating a user with optional parameters specified (including autologout) using Cyrillic charatcers.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Оверлорд',
						'Name' => 'Антон Антонович',
						'Last name' => 'Антонов',
						'Groups' => ['Zabbix administrators'],
						'Password' => 'абвгдеЁж',
						'Password (once again)' => 'абвгдеЁж',
						'Theme' => 'High-contrast dark',
						'Auto-login' => false,
						'Refresh' => '0',
						'Rows per page' => '999999',
						'URL (after login)' => 'https://zabbix.com'
					],
					'role' => 'Admin role',
					'check_form' => true
				]
			],
			// Creating a user with punctuation symbols in password and optional parameters specified.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Detailed user',
						'Name' => 'Bugs',
						'Last name' => 'Bunny',
						'Groups' => [
							'Selenium user group in configuration',
							'Zabbix administrators'
						],
						'Password' => '!@#$%^&*()_+',
						'Password (once again)' => '!@#$%^&*()_+',
						'Language' => 'English (en_US)',
						'Theme' => 'Dark',
						'Auto-login' => true,
						'Refresh' => '3600s',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '1d'
					],
					'role' => 'Admin role',
					'check_form' => true,
					'check_user' => true
				]
			],
			// Verification that field password is not mandatory for users with LDAP authentication.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'LDAP_user',
						'Groups' => 'LDAP user group'
					],
					'role' => 'Super admin role'
				]
			],
			// Verification that field password is not mandatory for users with no access to frontend.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'No_frontend_user',
						'Groups' => 'No access to the frontend'
					],
					'role' => 'User role'
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormUser_Create($data) {
		$sql = 'SELECT * FROM users';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=user.edit');
		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->fill($data['fields']);

		if (array_key_exists('auto_logout', $data)) {
			$this->setAutoLogout($data['auto_logout']);
		}

		if (array_key_exists('role', $data)) {
			$form->selectTab('Permissions');
			$form->fill(['Role' => $data['role']]);
		}

		$form->submit();
		$this->page->waitUntilReady();
		// Verify that the user was created.
		$this->assertUserMessage($data, 'User added', 'Cannot add user');

		if ($data['expected'] === TEST_BAD) {
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}

		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->assertFormFields($data);
		}

		if (CTestArrayHelper::get($data, 'check_user', false)) {
			$this->assertUserParameters($data);
		}
	}

	/*
	 * Check the field values after creating or updating user.
	 */
	private function assertFormFields($data) {
		$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username ='.zbx_dbstr($data['fields']['Username']));
		$this->page->open('zabbix.php?action=user.edit&userid='.$userid);
		$form_update = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();

		// Verify that fields are updated.
		$check_fields = ['Username', 'Name', 'Last name', 'Language', 'Theme', 'Refresh', 'Rows per page', 'URL (after login)'];
		foreach ($check_fields as $field_name) {
			if (array_key_exists($field_name, $data['fields'])) {
				$this->assertEquals($data['fields'][$field_name], $form_update->getField($field_name)->getValue());
			}
		}

		$this->assertEquals($data['fields']['Groups'], $form_update->getField('Groups')->getSelected());

		if (CTestArrayHelper::get($data, 'auto_logout.checked', false)) {
			$this->assertTrue($form_update->getField('Auto-login')->isChecked(false));
		}
		else {
			$this->assertTrue($form_update->getField('Auto-login')->isChecked($data['fields']['Auto-login']));
		}

		if (array_key_exists('role', $data)) {
			$form_update->selectTab('Permissions');
			$this->assertEquals([$data['role']], $form_update->getField('Role')->getSelected());
		}
	}

	/*
	 * Login as user and check user profile parameters in UI.
	 */
	private function assertUserParameters($data) {
		try {
			$this->page->logout();
			// Log in with the created or updated user.
			$password = CTestArrayHelper::get($data['fields'], 'Password', $data['fields']['Password'] = 'zabbix');
			$this->page->userLogin($data['fields']['Username'], $password);
			// Verification of URL after login.
			$this->assertStringContainsString($data['fields']['URL (after login)'], $this->page->getCurrentURL());
			// Verification of the number of rows per page parameter.
			$rows = $this->query('name:frm_maps')->asTable()->waitUntilVisible()->one()->getRows();
			$this->assertEquals($data['fields']['Rows per page'], $rows->count());

			// Verification of default theme.
			$db_theme = CDBHelper::getValue('SELECT theme FROM users WHERE username ='.zbx_dbstr($data['fields']['Username']));
			$color = $this->query('tag:body')->one()->getCSSValue('background-color');
			$stylesheet = $this->query('xpath://link[@rel="stylesheet"]')->one();
			$parts = explode('/', $stylesheet->getAttribute('href'));
			$file_time = explode('?', end($parts));
			$file = $file_time[0];

			if ($data['fields']['Theme'] === 'Dark') {
				$this->assertEquals('dark-theme', $db_theme);
				$this->assertEquals('dark-theme.css', $file);
				$this->assertEquals('rgba(14, 16, 18, 1)', $color);
			}
			else if ($data['fields']['Theme'] === 'High-contrast light') {
				$this->assertEquals('hc-light', $db_theme);
				$this->assertEquals('hc-light.css', $file);
				$this->assertEquals('rgba(255, 255, 255, 1)', $color);
			}

			$this->page->logout();
		}
		catch (Exception $e) {
			$this->page->logout();
			throw $e;
		}
	}

	public function getUpdateData() {
		return [
			// Username is already taken by another user.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Username' => 'Admin'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'User with username "Admin" already exists.'
				]
			],
			// Empty 'Group' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Groups' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Field "user_groups" is mandatory.'
				]
			],
			// Empty 'Password (once again)' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Password' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Password' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Password (once again)' => 'test5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// 'Password' and 'Password (once again)' do not match.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'teST5678'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Both passwords must be equal.'
				]
			],
			// Empty 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678',
						'Refresh' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "refresh": cannot be empty.'
				]
			],
			// Digits in value of the 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '123abc'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			// Value of the 'Refresh' field too large.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '3601'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '61m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
				]
			],
			// Non time unit value in 'Refresh' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh' => '00000000000001'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/refresh": a time unit is expected.'
				]
			],
			//	'Rows per page' field equal to '0'.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Rows per page' => '0'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			//	Non-numeric value of 'Rows per page' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Rows per page' => 'abc123'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
				]
			],
			// 'Autologout' below minimal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '89'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' above maximal value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '86401'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '1m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '1441m'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => '25h'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
				]
			],
			// 'Autologout' with a non-numeric value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => 'ninety'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/autologout": a time unit is expected.'
				]
			],
			// 'Autologout' with an empty value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'auto_logout' => [
						'checked' => true,
						'value' => ''
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Incorrect value for field "autologout": cannot be empty.'
				]
			],
			// URL unacceptable.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL (after login)' => 'javascript:alert(123);'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Incorrect URL protocol.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'URL (after login)' => 'snmp://zabbix.com'
					],
					'error_title' => 'Cannot update user',
					'error_details' => 'Invalid parameter "/1/url": unacceptable URL.'
				]
			],
			// Updating all fields (except password) of an existing user.
			[
				[
					'expected' => TEST_GOOD,
					'user_to_update' => 'disabled-user',
					'fields' => [
						'Username' => 'Updated_user_1',
						'Name' => 'Test_Name',
						'Last name' => 'Test_Surname',
						'Groups' => [
							'Selenium user group in configuration'
						],
						'Language' => 'English (en_US)',
						'Theme' => 'Dark',
						'Auto-login' => true,
						'Refresh' => '60m',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'auto_logout' => [
						'checked' => true,
						'value' => '24h'
					],
					'check_form' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'Updated_user',
						'Name' => 'Road',
						'Last name' => 'Runner',
						'Groups' => [
							'Selenium user group in configuration'
						],
						'Language' => 'English (en_US)',
						'Theme' => 'High-contrast light',
						'Auto-login' => true,
						'Refresh' => '1h',
						'Rows per page' => '1',
						'URL (after login)' => 'sysmaps.php'
					],
					'check_form' => true,
					'check_user' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormUser_Update($data) {
		$update_user = CTestArrayHelper::get($data, 'user_to_update', 'Tag-user');
		$sql = 'SELECT * FROM users';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $update_user)->waitUntilVisible()->one()->click();

		// Update user parameters.
		$form = $this->query('name:user_form')->asForm()->one();
		if (array_key_exists('Password', $data['fields']) || array_key_exists('Password (once again)', $data['fields'])) {
			$form->query('button:Change password')->one()->click();
		}
		$form->fill($data['fields']);
		if (array_key_exists('auto_logout', $data)) {
			$this->setAutoLogout($data['auto_logout']);
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Verify if the user was updated.
		$this->assertUserMessage($data, 'User updated', 'Cannot update user');
		if ($data['expected'] === TEST_BAD) {
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}

		if (CTestArrayHelper::get($data, 'check_form', false)) {
			$this->assertFormFields($data);
		}

		if (CTestArrayHelper::get($data, 'check_user', false)) {
			$this->assertUserParameters($data);
		}
	}

	/**
	 * Test update without any modification of user data.
	 */
	public function testFormUser_SimpleUpdate() {
		$sql_hash = 'SELECT * FROM users ORDER BY userid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', 'test-user')->waitUntilVisible()->one()->click();

		$form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form->submit();
		$this->page->waitUntilReady();
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('User updated', $message->getTitle());

		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));
	}

	/**
	 * Test user password change and sign in with new password.
	 */
	public function testFormUser_PasswordUpdate() {
		$data = [
			'username' => 'user-zabbix',
			'old_password' => 'test5678',
			'new_password' => 'test5678_new',
			'error_message' => 'Incorrect user name or password or account is temporarily blocked.',
			'attempt_message' => '1 failed login attempt logged. Last failed attempt was from'
		];
		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $data['username'])->waitUntilVisible()->one()->click();
		$form_update = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form_update->query('button:Change password')->one()->click();

		// Change user password and log out.
		$form_update->fill([
			'Password' => $data['new_password'],
			'Password (once again)' => $data['new_password']
		]);
		$form_update->submit();

		try {
			$this->page->logout();

			// Attempt to sign in with old password.
			$this->page->userLogin($data['username'], $data['old_password']);
			$message = $this->query('class:red')->one()->getText();
			$this->assertEquals($message, $data['error_message']);

			// Sign in with new password.
			$this->page->userLogin($data['username'], $data['new_password']);
			$attempt_message = CMessageElement::find()->one();
			$this->assertTrue($attempt_message->hasLine($data['attempt_message']));
			$this->page->logout();
		}
		catch (\Exception $e) {
			// Logout to execute remaining tests.
			$this->page->logout();
			throw $e;
		}
	}

	public function getDeleteData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Username' => 'no-access-to-the-frontend'
					]
				]
			],
			// Attempt to delete internal user guest.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'guest',
					'error_details' => 'Cannot delete Zabbix internal user "guest", try disabling that user.'
				]
			],
			// Attempt to delete a user that owns a map.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'user-zabbix',
					'parameters' => [
						'DB_table' => 'sysmaps',
						'column' => 'name',
						'value' => 'Local network'
					],
					'error_details' => 'User "user-zabbix" is map "Local network" owner.'
				]
			],
			// Attempt to delete a user that owns a dashboard.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'test-timezone',
					'error_details' => 'User "test-timezone" is dashboard "Testing share dashboard" owner.'
				]
			],
			// Attempt to delete a user that is mentioned in an action.
			[
				[
					'expected' => TEST_BAD,
					'username' => 'user-for-blocking',
					'parameters' => [
						'DB_table' => 'opmessage_usr',
						'column' => 'operationid',
						'value' => '19'
					],
					'User "user-zabbix" is used in "Trigger action 4" action.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormUser_Delete($data) {
		// Defined required variables.
		if (array_key_exists('username', $data)) {
			$username = $data['username'];
		}
		else {
			$username = $data['fields']['Username'];
		}

		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $username)->one()->click();
		$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username =' . zbx_dbstr($username));

		// Link user with map, action to validate user deletion.
		if (array_key_exists('parameters', $data)) {
			DBexecute(
					'UPDATE '.$data['parameters']['DB_table'].' SET userid ='.zbx_dbstr($userid).
					' WHERE '.$data['parameters']['column'].'='.zbx_dbstr($data['parameters']['value'])
			);
		}

		// Attempt to delete the user from user update view and verify result.
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		// Validate if the user was deleted.
		$this->assertUserMessage($data, 'User deleted', 'Cannot delete user');
		if ($data['expected'] === TEST_BAD) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT userid FROM users WHERE username =' . zbx_dbstr($username)));
		}
	}

	/**
	 * Check that user can't delete himself.
	 */
	public function testFormUser_SelfDeletion() {
		$this->page->login()->open('zabbix.php?action=user.edit&userid=1');
		$this->assertTrue($this->query('button:Delete')->waitUntilVisible()->one()->isEnabled(false));
	}

	public function testFormUser_Cancel() {
		$data = [
			'Username' => 'user-cancel',
			'Password' => 'zabbix',
			'Password (once again)' => 'zabbix',
			'Groups' => 'Guests'
		];
		$sql_users = 'SELECT * FROM users ORDER BY userid';
		$user_hash = CDBHelper::getHash($sql_users);
		$this->page->login()->open('zabbix.php?action=user.edit');

		// Check cancellation when creating users.
		$form_create = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$form_create->fill($data);
		$this->query('button:Cancel')->one()->click();
		$cancel_url = $this->page->getCurrentURL();
		$this->assertStringContainsString('zabbix.php?action=user.list', $cancel_url);
		$this->assertEquals($user_hash, CDBHelper::getHash($sql_users));

		// Check Cancellation when updating users.
		$this->page->open('zabbix.php?action=user.edit&userid=1');
		$this->query('id:name')->one()->fill('Boris');
		$this->query('button:Cancel')->one()->click();
		$this->assertEquals($user_hash, CDBHelper::getHash($sql_users));
	}

	private function assertUserMessage($data, $good_title, $bad_title) {
		$message = CMessageElement::find()->one();
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals($good_title, $message->getTitle());
				$user_count = CDBHelper::getCount('SELECT userid FROM users WHERE username ='.zbx_dbstr($data['fields']['Username']));
				if ($good_title === 'User deleted') {
					$this->assertTrue($user_count === 0);
				}
				else {
					$this->assertTrue($user_count === 1);
				}
				break;

			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals(CTestArrayHelper::get($data, 'error_title', $data['error_title'] = $bad_title), $message->getTitle());
				if (array_key_exists('error_details', $data)) {
					$this->assertTrue($message->hasLine($data['error_details']));
				}
				break;
		}
	}

	private function setAutoLogout($data) {
		$form = $this->query('name:user_form')->asForm()->one();
		$auto_logout = $form->getFieldContainer('Auto-logout');
		$auto_logout->query('id:autologout_visible')->asCheckbox()->one()->set($data['checked']);
		if (array_key_exists('value', $data)) {
			$auto_logout->query('id:autologout')->one()->overwrite($data['value']);
		}
		// Verify that Auto-login is unchecked after setting Auto-logout.
		$this->assertTrue($form->getField('Auto-login')->isChecked(false));
	}
}
