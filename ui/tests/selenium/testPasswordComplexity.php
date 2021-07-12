<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @backup config, users
 */
class testPasswordComplexity extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Check authentication form fields layout.
	 */
	public function testPasswordComplexity_Layout() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->page->assertTitle('Configuration of authentication');
		$form = $this->query('name:form_auth')->asForm()->one();

		// Summon first hint-box.
		$form->query('xpath://label[text()="Password must contain"]//span')->one()->click();
		$contain_hint = $form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();

		// Assert text in the first hint-box.
		$expected_contain_text = "Password requirements:".
				"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
				"\nmust contain at least one digit (0-9)".
				"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)";
		$this->assertEquals($expected_contain_text, $contain_hint->one()->getText());

		// Close the first hint-box.
		$contain_hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$contain_hint->waitUntilNotPresent();

		// Summon second hint-box.
		$form->query('xpath://label[text()="Avoid easy-to-guess passwords"]//span')->one()->click();
		$easy_password_hint = $form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();

		// Assert text in the second hint-box.
		$expected_easy_password_text = "Password requirements:".
				"\nmust not contain user's name, surname or username".
				"\nmust not be one of common or context-specific passwords";
		$this->assertEquals($expected_easy_password_text, $easy_password_hint->one()->getText());

		// Close the second hit-box.
		$easy_password_hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$easy_password_hint->waitUntilNotPresent();
	}

	public function getFormValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '0'],
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '71']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '-ab']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '!@']
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '']
				]
			],
			[
				[
					'fields' => ['Minimum password length' => '1'],
					'db_passwd_min_length' => 1
				]
			],
			[
				[
					'fields' => ['Minimum password length' => '50'],
					'db_passwd_min_length' => 50
				]
			],
			[
				[
					'fields' => ['Minimum password length' => '70'],
					'db_passwd_min_length' => 70
				]
			],
			// Negative number will be converted to positive when focus-out.
			[
				[
					'fields' => ['Minimum password length' => '-7'],
					'db_passwd_min_length' => 7
				]
			]
		];
	}

	/**
	 * @dataProvider getFormValidationData
	 *
	 * Check authentication form fields validation.
	 */
	public function testPasswordComplexity_FormValidation($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM config');
		}

		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('name:form_auth')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update authentication',
				'Invalid parameter "/passwd_min_length": value must be one of 1-70.'
			);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			// Check length fields saved in db, other fields remained default.
			$db_expected = ['passwd_min_length' => $data['db_passwd_min_length'], 'passwd_check_rules' => 8];
			$this->assertEquals([$db_expected],
					CDBHelper::getAll('SELECT passwd_min_length, passwd_check_rules FROM config')
			);
		}
	}

	public function getUserPasswordData() {
		return [
			// Check default password complexity settings.
			[
				[
					'db_passwd_check_rules' => 8,
					'Password' => 'iamrobot',
					'hint' => "Password requirements:".
						"\nmust be at least 8 characters long".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords",
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '1',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 0,
					'Password' => 'a'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '1',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 15,
					'Password' => 'aA1!',
					'hint' => "Password requirements:".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)".
						"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords",
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '1',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 15,
					'Password' => '',
					'hint' => "Password requirements:".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)".
						"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "Password": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '1',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 15,
					'Password' => 'a',
					'hint' => "Password requirements:".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)".
						"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one lowercase and one uppercase Latin letter.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '1',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 0,
					'Password' => '',
					'error' => 'Incorrect value for field "Password": cannot be empty.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '3',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 1,
					'Password' => 'Tes',
					'hint' => "Password requirements:".
						"\nmust be at least 3 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '3',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 1,
					'Password' => 'Te',
					'hint' => "Password requirements:".
						"\nmust be at least 3 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)",
					'error' => 'Incorrect value for field "/1/passwd": must be at least 3 characters long.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '2',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 1,
					'Password' => 'tes',
					'hint' => "Password requirements:".
						"\nmust be at least 2 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one lowercase and one uppercase Latin letter.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '70',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 0,
					'Password' => str_repeat('a', 70),
					'hint' => "Password requirements:".
						"\nmust be at least 70 characters long"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '70',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 0,
					'Password' => str_repeat('a', 69),
					'hint' => "Password requirements:".
						"\nmust be at least 70 characters long",
					'error' => 'Incorrect value for field "/1/passwd": must be at least 70 characters long.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '70',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 0,
					'Password' => str_repeat('a', 71),
					'hint' => "Password requirements:".
						"\nmust be at least 70 characters long"
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '70',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 1,
					'Password' => str_repeat('a', 35).str_repeat('A', 36),
					'hint' => "Password requirements:".
						"\nmust be at least 70 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)",
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '70',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 1,
					'Password' => str_repeat('a', 80),
					'hint' => "Password requirements:".
						"\nmust be at least 70 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one lowercase and one uppercase Latin letter.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 2,
					'Password' => 'secure_password',
					'hint' => "Password requirements:".
						"\nmust be at least 8 characters long".
						"\nmust contain at least one digit (0-9)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one digit.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 3,
					'Password' => 'Secure_Password',
					'hint' => "Password requirements:".
						"\nmust be at least 8 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one digit.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 3,
					'Password' => 'Secure_Password1',
					'hint' => "Password requirements:".
						"\nmust be at least 8 characters long".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)"
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 2,
					'Password' => 'secure_password1',
					'hint' => "Password requirements:".
						"\nmust be at least 8 characters long".
						"\nmust contain at least one digit (0-9)"
				]
			],
		];
	}

	/**
	 * @dataProvider getUserPasswordData
	 *
	 * Check user creation with password complexity rules.
	 */
	public function testPasswordComplexity_CreateUserPassword($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM users');
		}

		if (array_key_exists('auth_fields', $data)) {
			$this->page->login()->open('zabbix.php?action=authentication.edit');
			$auth_form = $this->query('name:form_auth')->asForm()->waitUntilPresent()->one();
			$auth_form->fill($data['auth_fields']);
			$auth_form->submit();
			$this->page->waitUntilReady();
			// Uncomment this when ZBXNEXT-4029 (12) subissue is fixed.
//			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
			$this->assertEquals($data['db_passwd_check_rules'],
				CDBHelper::getValue('SELECT passwd_check_rules FROM config'));
		}
		else {
			$this->assertEquals([['passwd_min_length' => 8, 'passwd_check_rules' => 8]],
					CDBHelper::getAll('SELECT passwd_min_length, passwd_check_rules FROM config')
			);
		}

		// Check user password creation accordingly to complexity settings.
		$this->page->login()->open('zabbix.php?action=user.edit');
		$user_form = $this->query('name:user_form')->asForm()->waitUntilPresent()->one();
		$username = 'username'.time();
		$user_form->fill([
			'Username' => $username,
			'Groups' => ['Zabbix administrators'],
			'Password' => $data['Password'],
			'Password (once again)' => $data['Password']
		]);

		if (array_key_exists('hint', $data)) {
			// Summon hint-box.
			$user_form->query('xpath://label[text()="Password"]//span')->one()->click();
			$hint = $user_form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();

			// Assert text in hint-box.
			$this->assertEquals($data['hint'], $hint->one()->getText());

			// Close hint-box.
			$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
			$hint->waitUntilNotPresent();
		}
		else {
			$this->assertFalse($user_form->query('xpath://div[@class="overlay-dialogue"]')->exists());
		}

		$user_form->selectTab('Permissions');
		$user_form->fill(['Role' => 'User role']);
		$user_form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot add user', $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM users'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'User added');

			// Check user saved in db.
			$this->assertEquals(1,CDBHelper::getCount('SELECT * FROM users WHERE username ='.zbx_dbstr($username)));
		}
	}
}
