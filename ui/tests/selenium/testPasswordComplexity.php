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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @onBefore prepareUserData
 *
 * @backup config, users
 */
class testPasswordComplexity extends CWebTest {

	const ACTION_UPDATE = true;
	const OWN_PASSWORD  = true;
	const ADMIN_USERID  = 1;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Id of user that created for future updating.
	 *
	 * @var integer
	 */
	protected static $userid;

	/**
	 * Password for user which is being changed in scenarios.
	 *
	 * @var string
	 */
	protected static $user_password = 'Iamrobot1!';

	/**
	 * Password for Admin which is being changed in scenarios.
	 *
	 * @var string
	 */
	protected static $admin_password = 'zabbix';

	/**
	 * Add user for updating.
	 */
	public function prepareUserData() {
		$response = CDataHelper::call('user.create', [
			[
				'username' => 'update-user',
				'passwd' => 'Iamrobot1!',
				'autologin' => 1,
				'autologout' => 0,
				'roleid' => 1,
				'usrgrps' => [
					[
						'usrgrpid' => '7'
					]
				]
			]
		]);

		$this->assertArrayHasKey('userids', $response);
		self::$userid = $response['userids'][0];
	}

	/**
	 * Check authentication form fields layout.
	 */
	public function testPasswordComplexity_Layout() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->page->assertTitle('Configuration of authentication');
		$form = $this->query('name:form_auth')->asForm()->one();

		// Check authentication swithcher options and default value.
		$auth_radio = $form->getField('Default authentication')->asSegmentedRadio();
		$this->assertEquals(['Internal', 'LDAP'], $auth_radio->getLabels()->asText());
		$this->assertEquals('Internal', $auth_radio->getSelected());

		// Check that 'Password policy' header presents.
		$this->assertTrue($form->query('xpath://h4[text()="Password policy"]')->exists());

		$this->assertEquals(2, $form->getField('Minimum password length')->getAttribute('maxlength'));
		// Check default texts in hint-boxes.
		$hintboxes = [
			[
				'field' => 'Password must contain',
				'text' => "Password requirements:".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)".
						"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)"
			],
			[
				'field' => 'Avoid easy-to-guess passwords',
				'text' => "Password requirements:".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords"
			]
		];

		foreach ($hintboxes as $hintbox) {
			// Summon the hint-box.
			$form->query('xpath://label[text()='.zbx_dbstr($hintbox['field']).']//a')->one()->click();
			$hint = $form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();

			// Assert text.
			$this->assertEquals($hintbox['text'], $hint->one()->getText());

			// Close the hint-box.
			$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
			$hint->waitUntilNotPresent();
		}

		// Assert default values in form.
		$default_values = [
			'Minimum password length' => '8',
			'id:passwd_check_rules_case' => false,
			'id:passwd_check_rules_digits' => false,
			'id:passwd_check_rules_special' => false,
			'id:passwd_check_rules_simple' => true
		];
		foreach ($default_values as $field => $value) {
			$this->assertEquals($value, $form->getField($field)->getValue());
		}

		// Check default values in DB.
		$this->assertEquals([['passwd_min_length' => 8, 'passwd_check_rules' => 8]],
				CDBHelper::getAll('SELECT passwd_min_length, passwd_check_rules FROM config')
		);
	}

	public function getFormValidationData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Minimum password length' => '0']
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
					'fields' => ['Minimum password length' => '-8'],
					'db_passwd_min_length' => 8
				]
			]
		];
	}

	/**
	 * Check authentication form fields validation.
	 *
	 * @dataProvider getFormValidationData
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

	public function getCommonPasswordData() {
		return [
			[
				// Check default password complexity settings.
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'iamrobot',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords"
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
							"\nmust not be one of common or context-specific passwords"
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
							"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)"
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
					'Password' => '99009900',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
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
					'Password' => '12345678',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one digit (0-9)"
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
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 10,
					'Password' => '12345678',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one digit (0-9)".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "/1/passwd": must not be one of common or context-specific passwords.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 6,
					'Password' => 'secure_password1#():}',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one digit (0-9)".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 6,
					'Password' => 'securepassword1',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one digit (0-9)".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one special character.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => true,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 6,
					'Password' => 'securepassword#',
					'hint' => "Password requirements:".
								"\nmust be at least 8 characters long".
								"\nmust contain at least one digit (0-9)".
								"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one digit.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 4,
					'Password' => 'securepassword#',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 4,
					'Password' => 'securepassword',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one special character.'
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => false
					],
					'db_passwd_check_rules' => 4,
					'Password' => "( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)",
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)"
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 12,
					'Password' => "zabbix",
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "/1/passwd": must be at least 8 characters long.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => true,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 12,
					'Password' => "zabbix",
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "/1/passwd": must contain at least one special character.'
				]
			]
		];
	}

	public function getUserPasswordData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => "zabbix",
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => 'Incorrect value for field "/1/passwd": must not be one of common or context-specific passwords.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => true,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 9,
					'Password' => 'Admin',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not be one of common or context-specific passwords."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'admin',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not be one of common or context-specific passwords."
				]
			]
		];
	}

	public function getAdminPasswordData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'Admin',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not contain user's name, surname or username."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'admin',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not contain user's name, surname or username."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'admin1',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not contain user's name, surname or username."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '8',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'admin',
					'hint' => "Password requirements:".
							"\nmust be at least 8 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must be at least 8 characters long."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'zabbix',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not contain user's name, surname or username."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'password',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not be one of common or context-specific passwords."
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'password',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords",
					'error' => "Incorrect value for field \"/1/passwd\": must not be one of common or context-specific passwords."
				]
			],
			[
				[
					'auth_fields' => [
						'Minimum password length' => '4',
						'id:passwd_check_rules_case' => false,
						'id:passwd_check_rules_digits' => false,
						'id:passwd_check_rules_special' => false,
						'id:passwd_check_rules_simple' => true
					],
					'db_passwd_check_rules' => 8,
					'Password' => 'securepassword',
					'hint' => "Password requirements:".
							"\nmust be at least 4 characters long".
							"\nmust not contain user's name, surname or username".
							"\nmust not be one of common or context-specific passwords"
				]
			]
		];
	}

	/**
	 * Check user creation with password complexity rules.
	 *
	 * @dataProvider getCommonPasswordData
	 * @dataProvider getUserPasswordData
	 */
	public function testPasswordComplexity_CreateUserPassword($data) {
		$this->checkPasswordComplexity($data, self::$admin_password);
	}

	/**
	 * Check user changes his own password accordingly to complexity rules.
	 *
	 * @dataProvider getCommonPasswordData
	 * @dataProvider getUserPasswordData
	 */
	public function testPasswordComplexity_ChangeOwnUserPassword($data) {
		$this->checkPasswordComplexity($data, self::$admin_password, self::$userid, self::ACTION_UPDATE,
				self::OWN_PASSWORD, self::$user_password
		);
	}

	/**
	 * Check Admin changes his own password accordingly to complexity rules.
	 *
	 * @dataProvider getCommonPasswordData
	 * @dataProvider getAdminPasswordData
	 */
	public function testPasswordComplexity_ChangeOwnAdminPassword($data) {
		$this->checkPasswordComplexity($data, self::$admin_password, self::ADMIN_USERID, self::ACTION_UPDATE, self::OWN_PASSWORD);
	}

	/**
	 * Check user update with password complexity rules.
	 *
	 * @dataProvider getCommonPasswordData
	 * @dataProvider getUserPasswordData
	 */
	public function testPasswordComplexity_UpdateUserPassword($data) {
		$this->checkPasswordComplexity($data, self::$admin_password, self::$userid, self::ACTION_UPDATE);
	}

	/**
	 * Check admin user password update accordingly to complexity rules.
	 *
	 * @dataProvider getCommonPasswordData
	 * @dataProvider getAdminPasswordData
	 */
	public function testPasswordComplexity_UpdateAdminPassword($data) {
		$this->checkPasswordComplexity($data, self::$admin_password, self::ADMIN_USERID, self::ACTION_UPDATE);
	}

	/**
	 * Check password complexity rules for user creation or update.
	 *
	 * @param array     $data       data provider
	 * @param string    $admin_password    password used for Admin user login
	 * @param int       $userid            id of the user whose password is changed
	 * @param boolean   $update            false if create, true if update
	 * @param $own      $own               true if user changes his password himself
	 */
	private function checkPasswordComplexity($data, $admin_password, $userid = null, $update = false, $own = false,
			$user_password = null) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM users ORDER BY userid');
		}

		$this->page->userLogin('Admin', $admin_password);
		$this->page->open('zabbix.php?action=authentication.edit');
		$auth_form = $this->query('name:form_auth')->asForm()->waitUntilPresent()->one();
		$auth_form->fill($data['auth_fields']);
		$auth_form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Authentication settings updated');
		$this->assertEquals($data['db_passwd_check_rules'],
				CDBHelper::getValue('SELECT passwd_check_rules FROM config')
		);

		if ($update) {
			if ($own) {
				if ($userid !== 1) {
					$this->page->userLogin('update-user', $user_password);
				}

				$this->page->open('zabbix.php?action=userprofile.edit');
				$this->clickChangePassword();
			}
			else {
				$this->page->open('zabbix.php?action=user.edit&userid='.$userid);
				$this->clickChangePassword();
			}
		}
		else {
			$this->page->open('zabbix.php?action=user.edit');
		}

		// Check user password creation accordingly to complexity settings.
		$user_form = $this->query('name:user_form')->asForm()->waitUntilPresent()->one();
		$username = ($userid === 1)
			? 'Admin'
			: ($update ? 'update-user' : 'username'.time());

		if ($update === false && $own === false) {
			$user_form->fill([
				'Username' => $username,
				'Groups' => ['Zabbix administrators']
			]);
		}

		if (array_key_exists('hint', $data)) {
			// Summon hint-box and assert text accordingly to password complexity settings, then close hint-box.
			$user_form->query('xpath://label[text()="Password"]//a')->one()->click();
			$hint = $user_form->query('xpath://div[@class="overlay-dialogue"]')->waitUntilPresent();
			$this->assertEquals($data['hint'], $hint->one()->getText());
			$hint->one()->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
			$hint->waitUntilNotPresent();
		}
		else {
			// If password can be 1 symbol long and doesn't have any complexity rules hint is not shown at all.
			$this->assertFalse($user_form->query('xpath://label[text()="Password"]//a')->exists());
		}

		$user_form->fill([
			'Password' => $data['Password'],
			'Password (once again)' => $data['Password']
		]);

		if ($update === false) {
			$user_form->selectTab('Permissions');
			$user_form->fill(['Role' => 'User role']);
		}

		$user_form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot '.($update ? 'update' : 'add').' user', $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM users ORDER BY userid'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'User '.($update ? 'updated' : 'added'));

			// Check user saved in db.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM users WHERE username ='.zbx_dbstr($username)));

			// Check success login with new password.
			$this->page->userLogin($username, $data['Password']);
			$this->assertTrue($this->query('xpath://a[@title='.zbx_dbstr(($userid === 1) ? 'Admin (Zabbix Administrator)'
					: $username).' and text()="User settings"]')->exists()
			);

			// Write new password for next case.
			if (($userid === 1) && ($own || $update)) {
				self::$admin_password = $data['Password'];
			}
			elseif ($own) {
				self::$user_password = $data['Password'];
			}
		}
	}

	/**
	 * Click button "Change password" and wait until both password fields are editable.
	 */
	private function clickChangePassword() {
		$this->query('button:Change password')->waitUntilClickable()->one()->click();
		$this->query('id:password1')->waitUntilPresent()->one();
		$this->query('id:password2')->waitUntilPresent()->one();
	}
}
