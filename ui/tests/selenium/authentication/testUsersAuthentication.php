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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testUsersAuthentication extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Check saving default authentication page.
	 */
	public function testUsersAuthentication_Layout() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->page->assertTitle('Configuration of authentication');
		$this->page->assertHeader('Authentication');
		$form = $this->query('id:authentication-form')->asForm()->one();

		// Check switcher options and default values.
		$auth_radio = $form->getField('Default authentication')->asSegmentedRadio();
		$this->assertEquals(['Internal', 'LDAP'], $auth_radio->getLabels()->asText());

		$form->checkValue(['Deprovisioned users group' => '']);
		$form->query('button:Select')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('User groups', $dialog->getTitle());
		$footer = $dialog->getFooter();
		$this->assertEquals(1, $footer->query('xpath:.//button')->all()->count());
		$this->assertTrue($footer->query('button:Cancel')->one()->isClickable());

		$this->assertEquals(['Disabled'], $dialog->query('class:list-table')->asTable()->waitUntilVisible()->one()
				->getRows()->asText()
		);
		$dialog->close();

		// Check that 'Password policy' header presents.
		$this->assertTrue($form->query('xpath:.//h4[text()="Password policy"]')->exists());

		$this->assertEquals(2, $form->getField('Minimum password length')->getAttribute('maxlength'));

		// Check default texts in hint-boxes.
		$hintboxes = [
				'Deprovisioned users group' => 'Only disabled group can be set for deprovisioned users.',
				'Password must contain' => "Password requirements:".
						"\nmust contain at least one lowercase and one uppercase Latin letter (A-Z, a-z)".
						"\nmust contain at least one digit (0-9)".
						"\nmust contain at least one special character ( !\"#$%&'()*+,-./:;<=>?@[\]^_`{|}~)",
				'Avoid easy-to-guess passwords' => "Password requirements:".
						"\nmust not contain user's name, surname or username".
						"\nmust not be one of common or context-specific passwords"
		];

		foreach ($hintboxes as $field => $text) {
			// Summon the hint-box, assert text and close.
			$form->getLabel($field)->query('xpath:./button[@data-hintbox]')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilPresent()->one();
			$this->assertEquals($text, $hint->getText());
			$hint->close();
		}

		// Assert default values in form.
		$default_values = [
			'Default authentication' => 'Internal',
			'Deprovisioned users group' => '',
			'Minimum password length' => 8,
			'id:passwd_check_rules_case' => false,
			'id:passwd_check_rules_digits' => false,
			'id:passwd_check_rules_special' => false,
			'id:passwd_check_rules_simple' => true
		];

		foreach ($default_values as $field => $value) {
			$this->assertEquals($value, $form->getField($field)->getValue());
		}

		// Check default values in DB.
		$db_values = [
			[
				'authentication_type' => 0,
				'disabled_usrgrpid' => 0,
				'passwd_min_length' => 8,
				'passwd_check_rules' => 8
			]
		];

		$this->assertEquals($db_values, CDBHelper::getAll('SELECT authentication_type, disabled_usrgrpid,'.
				' passwd_min_length, passwd_check_rules FROM config')
		);
	}

	public function getFormData() {
		return [
			// Save default config without changes.
			[
				[
					'db_check' => [
						[
							'authentication_type' => 0,
							'disabled_usrgrpid' => 0,
							'passwd_min_length' => 8,
							'passwd_check_rules' => 8
						]
					]
				]
			],
			// Add Deprovisioned user group.
			[
				[
					'fields' => ['Deprovisioned users group' => 'Disabled'],
					'db_check' => [
						[
							'authentication_type' => 0,
							'disabled_usrgrpid' => 9,
							'passwd_min_length' => 8,
							'passwd_check_rules' => 8
						]
					]
				]
			],
			// Remove Deprovisioned user group.
			[
				[
					'fields' => ['Deprovisioned users group' => ''],
					'db_check' => [
						[
							'authentication_type' => 0,
							'disabled_usrgrpid' => 0,
							'passwd_min_length' => 8,
							'passwd_check_rules' => 8
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => ['Default authentication' => 'LDAP'],
					'check_alert' => true,
					'error_message' => 'Incorrect value for field "authentication_type": LDAP is not configured.'
				]
			]
		];
	}

	/**
	 * Check authentication form.
	 *
	 * @dataProvider getFormData
	 */
	public function testUsersAuthentication_Form($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM config');
		}

		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();

		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}

		$form->submit();

		if (CTestArrayHelper::get($data, 'check_alert')) {
			$this->assertEquals('Switching authentication method will reset all except this session! Continue?',
					$this->page->getAlertText()
			);
		}

		if ($this->page->isAlertPresent()) {
			$this->page->acceptAlert();
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update authentication', $data['error_message']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Authentication settings updated');

			// Check length fields saved in db.
			$this->assertEquals($data['db_check'], CDBHelper::getAll('SELECT authentication_type, disabled_usrgrpid, passwd_min_length,'.
					'passwd_check_rules FROM config')
			);
		}

		/*
		 * !!! All password related checks are performed in testUsersPasswordComplexity test !!!
		 */

	}
}
