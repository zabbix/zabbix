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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormUserAuthentication extends CWebTest {

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
	public function testFormUserAuthentication_Layout() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$this->page->assertTitle('Configuration of authentication');
		$this->page->assertHeader('Authentication');
		$form = $this->query('id:authentication-form')->asForm()->one();

		// Check switcher options and default values.
		$auth_radio = $form->getField('Default authentication')->asSegmentedRadio();
		$this->assertEquals(['Internal', 'LDAP'], $auth_radio->getLabels()->asText());
		$this->assertEquals('Internal', $auth_radio->getSelected());

		$form->checkValue(['Deprovisioned users group' => '']);
		$form->query('button:Select')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('User groups', $dialog->getTitle());
		$this->assertEquals(1, $dialog->getFooter()->query('xpath:.//button')->all()->count());
		$this->assertTrue($dialog->getFooter()->query('button:Cancel')->one()->isClickable());

		$table = $dialog->query('class:list-table')->asTable()->waitUntilVisible()->one();
		$this->assertEquals(1, $table->getRows()->count());
		$this->assertEquals('Disabled', $table->getRow(0)->getColumn('Name')->getText());
		$dialog->close();

		// Summon the Deprovisioned users group hint-box.
		$form->query('xpath://label[text()="Deprovisioned users group"]//a')->one()->click();
		$hint = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilPresent();

		// Assert text.
		$this->assertEquals('Only disabled group can be set for deprovisioned users.', $hint->getText());

		// Close the hint-box.
		$hint->query('xpath:.//button[@class="overlay-close-btn"]')->one()->click();
		$hint->waitUntilNotPresent();

		/*
		 * !!! All password related checks are performed in testPasswordComplexity test !!!
		 */
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
	public function testFormUserAuthentication_Form($data) {
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
		 * !!! All password related checks are performed in testPasswordComplexity test !!!
		 */

	}
}
