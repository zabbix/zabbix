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
 * @backup users, media_type
 *
 * @onBefore prepareMediaTypeData
 *
 * @dataSource LoginUsers
 */
class testFormUserMedia extends CWebTest {

	private static $mediatype_sql = 'SELECT * FROM media';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Enable media types before test.
	 */
	public function prepareMediaTypeData() {
		$mediatypeids = CDBHelper::getAll("SELECT mediatypeid FROM media_type WHERE name IN ('Email', 'SMS',".
				"'Test script', 'MS Teams', 'Slack', 'Zendesk')"
		);

		foreach ($mediatypeids as $mediatype) {
			CDataHelper::call('mediatype.update', [
				[
					'mediatypeid' => $mediatype['mediatypeid'],
					'status' => 0
				]
			]);
		}

		CDataHelper::call('user.update', [
			[
				'userid' => 1,
				'medias' => [
					[
						'mediatypeid' => 1, // Email.
						'sendto' => ['test@zabbix.com'],
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 63,
						'period' => '1-7,00:00-24:00'
					],
					[
						'mediatypeid' => 1, // Email.
						'sendto' => ['test2@zabbix.com'],
						'active' => MEDIA_TYPE_STATUS_DISABLED,
						'severity' => 63,
						'period' => '1-7,00:00-24:00'
					],
					[
						'mediatypeid' => 10, // Discord.
						'sendto' => 'user@test.domain1.com',
						'active' => MEDIA_TYPE_STATUS_ACTIVE,
						'severity' => 16,
						'period' => '1-7,00:00-24:00'
					]
				]
			]
		]);
	}

	public function getMediaData() {
		return [
			// User media with multiple e-mails - all fields specified.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Email',
						'When active' => '1-5,09:00-18:00',
						'Enabled' => true,
						'Use if severity' => ['Average', 'High', 'Disaster']
					],
					'emails' => [
						['email' => '123@456.ttt', 'action' => USER_ACTION_UPDATE, 'index' => 0],
						['email' => 'Mr Email <bestEmail@zabbix.com>'],
						['email' => '∑Ω-symbols <utf-8@zabbix.coom>'],
						['email' => '"Zabbix\@\<H(comment)Q\>" <zabbix@company.com>']
					]
				]
			],
			// User media with only mandatory fields specified.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '+371 66600666'
					],
					'additional media' => [
						[
							'Type' => 'MS Teams',
							'Send to' => 'MS Teams channel 666'
						],
						[
							'Type' => 'Zendesk',
							'Send to' => '192.168.256.256'
						],
						[
							'Type' => 'Test script',
							'Send to' => 'Path to test script'
						]
					]
				]
			],
			// User with multiple "When active" periods.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '+371 66600666',
						'When active' => '{$DATE.TIME};6-7,09:00-15:00',
						'Enabled' => false
					]
				]
			],
			// Empty email address.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => ' ', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Incorrect value for field "sendto_emails": cannot be empty.'
				]
			],
			// Email address without the "@" symbol.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => 'no_at.zabbix.com', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "no_at.zabbix.com".'
				]
			],
			// Email address without the domain.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => 'no_domain@zabbix', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "no_domain@zabbix".'
				]
			],
			// Email address with numbers in domain.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => 'number_in_domain@zabbix.2u', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "number_in_domain@zabbix.2u".'
				]
			],
			// Email address with name and missing "<" and ">" symbols.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => 'Mr Person person@zabbix.com', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "Mr Person person@zabbix.com".'
				]
			],
			// Email address without the recipient specified - just the domain.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => '@zabbix.com', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "@zabbix.com".'
				]
			],
			// Email address that contains a space.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Email'
					],
					'emails' => [
						['email' => 'person @zabbix.com', 'action' => USER_ACTION_UPDATE, 'index' => 0]
					],
					'error_message' => 'Invalid email address "person @zabbix.com".'
				]
			],
			// Empty MS Teams channel name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'MS Teams',
						'Send to' => ''
					],
					'error_message' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			// Empty SMS recipient.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => ''
					],
					'error_message' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			// Empty Test script recipient.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'Test script',
						'Send to' => ''
					],
					'error_message' => 'Incorrect value for field "sendto": cannot be empty.'
				]
			],
			// String in when active.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '192.168.0.1',
						'When active' => 'always'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// Only time period in when active.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '00:00-24:00'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// Only days in when active.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '1-5'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// When active value is set to a specific moment.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '1-5,15:00-15:00'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// When active defined with incorrect order.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '15:00-18:00,1-5'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// When active defined using a regular macro.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '{TIME}'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// When active defined using a LLD macro.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '{#DATE.TIME}'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			],
			// Multiple When active periods separated by comma.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Type' => 'SMS',
						'Send to' => '12345678',
						'When active' => '1-5,09:00-18:00,6-7,12:00-15:00'
					],
					'error_message' => 'Incorrect value for field "period": a time period is expected'
				]
			]
		];
	}

	/**
	 * @dataProvider getMediaData
	 */
	public function testFormUserMedia_Add($data) {
		$old_hash = CDBHelper::getHash(self::$mediatype_sql);

		// Open the user media tab for user-zabbix user.
		$user_form = $this->getUserMediaTab('user-zabbix');

		// Check that no media are configured.
		$this->assertTrue($user_form->getField('Media')->getRows()->count() === 0);

		// Add media.
		$add_button = $this->query('button:Add')->one();
		$add_button->click();
		$this->setMediaValues($data);

		// Check if media was added and its configuration.
		if ($data['expected'] === TEST_GOOD) {
			$this->checkMediaConfiguration($data);

			// Add other media if required.
			foreach (CTestArrayHelper::get($data, 'additional media', []) as $i => $media) {
				$add_button->click();
				$media_form = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();
				$media_form->fill($media);
				$media_form->submit();
				$this->page->waitUntilReady();
				$user_form->invalidate();
				$this->assertEquals($user_form->getField('Media')->asTable()->getRows()->count(), $i + 2);
			}
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
		}
	}

	/**
	 * @dataProvider getMediaData
	 */
	public function testFormUserMedia_Edit($data) {
		$old_hash = CDBHelper::getHash(self::$mediatype_sql);

		// Edit selected media.
		$edit_row = $this->getUserMediaTab('Admin')->asTable()->query('xpath:.//tr[@id="medias_0"]')->one()->asTableRow();
		$original_period = $edit_row->getColumn('When active')->getText();
		$edit_row->query('button:Edit')->one()->click();
		$this->setMediaValues($data);

		// Check if media was updated and its configuration.
		if ($data['expected'] === TEST_GOOD) {
			$this->checkMediaConfiguration($data, $original_period, true);
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
		}
	}

	public function testFormUserMedia_DisabledMediaTypes() {
		// Get Media types table.
		$mediatype_table = $this->getUserMediaTab('Admin')->getField('Media')->asTable();

		// Check that disabled media types have popup icon in Type column.
		$discord_row = $mediatype_table->findRow('Type', 'Discord', true);
		$type_column = $discord_row->getColumn('Type');
		$this->assertTrue($type_column->query('xpath:.//button['.CXPathHelper::fromClass('zi-i-warning').']')->one()->isValid());

		$this->assertEquals('Media type disabled by Administration.', $type_column->query('tag:button')->one()
				->getAttribute('data-hintbox-contents'));

		// Check that status of disabled media types is not clickable.
		$this->assertFalse($discord_row->getColumn('Status')->query('xpath:.//a')->one(false)->isValid());

		// Check that disabled media types are shown in red color in media configuration form.
		$discord_row->query('button:Edit')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('focusable red', $dialog->asForm()->getField('Type')->query('button:Discord')->one()->getAttribute('class'));
		$dialog->close();

		// Check that there is no icon and no hintbox for disabled user media that belong to enabled media type.
		$email_row = $mediatype_table->findRow('Send to', 'test2@zabbix.com');
		$type_column = $email_row->getColumn('Type');

		foreach (["xpath:.//a[".CXPathHelper::fromClass('icon-info')."]", 'class:hint-box'] as $selector) {
			$this->assertFalse($type_column->query($selector)->one(false)->isValid());
		}

		// Check that status of disabled user media is clickable.
		$this->assertTrue($email_row->getColumn('Status')->query('button:Disabled')->one()->isValid());

		// Check that disabled media types are not shown if user media with enabled media type is edited.
		$email_row->query('button:Edit')->one()->click();
		$type_field = COverlayDialogElement::find()->waitUntilReady()->one()->asForm()->getField('Type');

		$this->assertFalse($type_field->query('button:Discord')->one(false)->isValid());
		$this->assertFalse($type_field->query('class:focusable red')->one(false)->isValid());
	}

	public function testFormUserMedia_StatusChangeAndRemove() {
		$old_hash = CDBHelper::getHash(self::$mediatype_sql);

		$this->page->login();

		foreach (['Cancel', 'Update'] as $action) {
			$this->page->open('zabbix.php?action=user.edit&userid=1');
			$this->query('id:tab_mediaTab')->waitUntilVisible()->one()->click();
			$table = $this->query('xpath://ul[@id="userMediaFormList"]//table')->asTable()->one();

			// Change status of one of the media.
			$row = $table->findRow('Send to', 'test@zabbix.com');
			$this->assertEquals($row->getColumn('Status')->getText(), 'Enabled');
			$row->getColumn('Status')->click();
			$this->assertEquals($row->getColumn('Status')->getText(), 'Disabled');

			// Remove one of the media.
			$row->getColumn('Actions')->query('button:Remove')->one()->click();
			$this->assertFalse($table->findRow('Send to', 'test@zabbix.com')->isValid());

			$this->query('button', $action)->one()->click();
			$this->query('link', 'Admin')->waitUntilVisible()->one()->click();
			$this->query('id:tab_mediaTab')->waitUntilVisible()->one()->click();
			if ($action === 'Update') {
				$this->assertFalse($table->findRow('Send to', 'test@zabbix.com')->isValid());
			}
			else {
				$this->assertEquals($old_hash, CDBHelper::getHash(self::$mediatype_sql));
			}
		}
	}

	public function testFormUserMedia_EmailRemoval() {
		$emails = [
			['email' => '0@zabbix.com', 'action' => USER_ACTION_UPDATE, 'index' => 0],
			['email' => '1@zabbix.com'],
			['email' => '2@zabbix.com'],
			['email' => '3@zabbix.com']
		];
		$this->page->login()->open('zabbix.php?action=user.edit&userid=50');
		$user_form = $this->query('name:user_form')->asForm()->waitUntilPresent()->one();
		$user_form->selectTab('Media');

		// Add media with multiple emails.
		$user_form->query('button:Add')->one()->click();
		$media_form = $this->query('name:media_form')->waitUntilVisible()->asForm()->one();
		$media_form->getField('Type')->fill('Email');
		$email_list = $media_form->getField('Send to')->asMultifieldTable();
		$email_list->setFieldMapping(['email'])->fill($emails);

		// Check that all emails are entered in media configuration form.
		$this->assertEquals($email_list->getRows()->count(), count($emails));

		// Remove email 3@zabbix.com and check that it's removed.
		$this->removeEmailFromList('3@zabbix.com');
		$this->checkEmailNotPresent('3@zabbix.com');

		// Edit the media - remove email 2@zabbix.com and check that it's removed.
		$media_list = $user_form->getField('Media')->asTable()->waitUntilVisible();
		$row = $media_list->getRow(0);
		$row->query('button:Edit')->one()->click();
		$this->removeEmailFromList('2@zabbix.com');
		$this->checkEmailNotPresent('3@zabbix.com');
	}

	public function getUserData() {
		return [
			// Create a user with media.
			[
				[
					'action' => 'create',
					'user_fields' => [
						'Username' => 'created-user',
						'Groups' => 'Zabbix administrators',
						'Password' => 'test5678',
						'Password (once again)' => 'test5678'
					],
					'media_fields' => [
						'Type' => 'SMS',
						'Send to' => '+371 74661x'
					],
					'role' => 'Super admin role',
					'expected_message' => 'User added'
				]
			],
			// Update a user with media.
			[
				[
					'action' => 'update',
					'username' => 'user-zabbix',
					'media_fields' => [
						'Type' => 'Slack',
						'Send to' => 'zabbix channel'
					],
					'expected_message' => 'User updated'
				]
			],
			// Delete a user with media.
			[
				[
					'action' => 'delete',
					'username' => 'test-user',
					'expected_message' => 'User deleted'
				]
			]
		];
	}

	/**
	 * @dataProvider getUserData
	 */
	public function testFormUserMedia_UserWithMediaActions($data) {
		$this->page->login()->open('zabbix.php?action=user.list');

		// Fill in user form for the created user or just open an existing one.
		if ($data['action'] === 'create') {
			$this->page->query('button:Create user')->one()->click();
			$user_form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
			$user_form->fill($data['user_fields']);

			$user_form->selectTab('Permissions');
			$user_form->fill(['Role' => $data['role']]);
		}
		else {
			$this->query('link', $data['username'])->waitUntilVisible()->one()->click();
			$user_form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		}

		// Fill in and submit user media form.
		if ($data['action'] !== 'delete') {
			$user_form->selectTab('Media');
			$user_form->query('button:Add')->one()->click();
			$media_form = $this->query('id:media_form')->asForm()->waitUntilVisible()->one();
			$media_form->fill($data['media_fields']);
			$media_form->submit();
			$this->page->waitUntilReady();
		}

		switch ($data['action']) {
			case 'create':
			case 'update':
				$user_form->submit();
				break;

			case 'delete':
				// Get userid for of the user to be deleted to verify media deletion along with the user.
				$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username ='.zbx_dbstr($data['username']));
				$this->query('button:Delete')->one()->click();
				$this->page->acceptAlert();
				break;
		}

		// Check that the action took place.
		$this->assertMessage(TEST_GOOD, $data['expected_message']);

		if ($data['action'] === 'delete') {
			$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM media WHERE userid='.zbx_dbstr($userid)));
		}
		else {
			$user = CTestArrayHelper::get($data, 'user_fields.Username') ? $data['user_fields']['Username'] : $data['username'];
			$this->query('link', $user)->waitUntilVisible()->one()->click();
			$user_form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
			$media_field = $user_form->getField('Media')->asTable();
			$this->assertTrue($media_field->getRows()->count() === 1);
			$row = $media_field->getRow(0);

			// Verify the values of "Type" and "Send to" for the created and updated media.
			foreach ($data['media_fields'] as $field => $value) {
				$this->assertEquals($row->getColumn($field)->getText(), $value);
			}
		}
	}

	/**
	 * Open Media tab of user configuration form for a defined user.
	 *
	 * @param string	$user	User for which the Media tab should be opened.
	 *
	 * @return CFormElement
	 */
	private function getUserMediaTab($user) {
		$this->page->login()->open('zabbix.php?action=user.list');
		$this->query('link', $user)->waitUntilVisible()->one()->click();
		$user_form = $this->query('name:user_form')->asForm()->waitUntilPresent()->one()->selectTab('Media');

		return $user_form;
	}

	/**
	 * Remove the specified Email from the "Send to" field of a media type.
	 *
	 * @param string	$email		Email to be removed from the "Send to" field.
	 */
	private function removeEmailFromList($email) {
		$media_form = $this->query('name:media_form')->waitUntilVisible()->asForm()->one();
		$email_list = $media_form->getField('Send to')->asMultifieldTable(['mapping' => ['email']]);

		// Remove the email from the list.
		$email_list->fill(['action' => USER_ACTION_REMOVE, 'email' => $email]);
		$media_form->submit();
		$this->page->waitUntilReady();
	}

	/**
	 * Check that the removed email is not present in 'Send to' field.
	 *
	 * @param string	$email		Email that should be removed from the "Send to" field.
	 */
	private function checkEmailNotPresent($email) {
		$user_form = $this->query('name:user_form')->asForm()->waitUntilVisible()->one();
		$row = $user_form->getField('Media')->asTable()->getRow(0);
		$this->assertStringNotContainsString($email, $row->getColumn('Send to')->getText());
	}

	/**
	 * Populate the media type configuration form.
	 *
	 * @param array	$data	data provider
	 */
	private function setMediaValues($data) {
		$media_form = $this->query('id:media_form')->waitUntilVisible()->asForm()->one();
		$media_form->fill($data['fields']);

		// Check that there is possibility to add only multiple emails to media.
		$clickable = ($data['fields']['Type'] === 'Email');

		foreach (['id:email_send_to_add', 'button:Remove'] as $selector) {
			$this->assertEquals($clickable, $media_form->query($selector)->one()->isClickable());
		}

		// Fill in e-mails if such exist.
		if (array_key_exists('emails', $data)) {
			$email_list = $media_form->getField('Send to')->asMultifieldTable();
			$email_list->setFieldMapping(['email'])->fill($data['emails']);
		}

		$media_form->submit();
		$this->page->waitUntilReady();
	}

	/**
	 * Check media type configuration in user configuration form.
	 *
	 * @param array		$data				data provider
	 * @param string	$original_period	default media type active period or period set prior to editing the form
	 * @param boolean	$edit_send_to		flag that specifies whether the "Send to" parameter was edited
	 */
	private function checkMediaConfiguration($data, $original_period = '1-7,00:00-24:00', $edit_send_to = true) {
		// Check media type.
		$media_field = $this->query('name:user_form')->asForm()->waitUntilVisible()->one()->getField('Media')->asTable();

		if (!$edit_send_to) {
			$this->assertTrue($media_field->getRows()->count() === 1);
			$row = $media_field->getRow(0);
		}
		else {
			$row = $this->query('xpath://tr[@id="medias_0"]')->asTableRow()->one();
		}
		$this->assertEquals($row->getColumn('Type')->getText(), $data['fields']['Type']);

		// Check the value of the "Send to" field.
		if (array_key_exists('emails', $data)) {
			$row->getColumn('Send to')->hoverMouse();
			$get_send_to = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilVisible()->one()->getText();

			$media_emails = [];
			foreach ($data['emails'] as $email) {
				$media_emails[] = $email['email'];
			}
			$send_to = implode(', ', $media_emails);
		}
		else {
			$this->assertFalse($row->query('xpath:./td[2]/span[@data-hintbox]')->one(false)->isValid());
			$get_send_to = $row->getColumn('Send to')->getText();
			$send_to = $data['fields']['Send to'];
		}
		$this->assertEquals($send_to, $get_send_to);

		// Check media active period.
		$when_active = $row->getColumn('When active')->getText();
		$this->assertEquals($when_active, CTestArrayHelper::get($data, 'fields.When active', $original_period));

		// Check media status.
		$get_status = $row->getColumn('Status')->getText();
		$status = CTestArrayHelper::get($data, 'fields.Enabled', true) ? 'Enabled' : 'Disabled';
		$this->assertEquals($get_status, $status);

		// Check selected severities.
		$reference_severities = [
			'Not classified' => '1',
			'Information' => '2',
			'Warning' => '3',
			'Average' => '4',
			'High' => '5',
			'Disaster' => '6'
		];

		if (array_key_exists('Use if severity', $data['fields'])) {
			// Check that the passed severities are turned on.
			foreach ($data['fields']['Use if severity'] as $used_severity) {
				$actual_severity = $row->query('xpath:./td[4]/div/span['.$reference_severities[$used_severity].']')->one()
						->getAttribute("data-hintbox-contents");
				$this->assertEquals($actual_severity, $used_severity.' (on)');
				unset($reference_severities[$used_severity]);
			}
			// Check that other severities are turned off.
			foreach ($reference_severities as $name => $unused_severity) {
				$actual_severity = $row->query('xpath:./td[4]/div/span['.$unused_severity.']')->one()
						->getAttribute("data-hintbox-contents");
				$this->assertEquals($name.' (off)', $actual_severity);
			}
		}
		else {
			// Check that when no severities are passed - they all are turned on by default.
			for ($i = 1; $i < 7; $i++) {
				$severity =  $row->query('xpath:./td[4]/div/span['.$i.']')->one()->getAttribute("data-hintbox-contents");
				$this->assertStringContainsString('(on)', $severity);
			}
		}
	}
}
