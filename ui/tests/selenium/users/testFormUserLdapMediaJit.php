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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup users
 *
 * @onBefore prepareJitMedia
 *
 * @dataSource LoginUsers
 */
class testFormUserLdapMediaJit extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const HASH_SQL = 'SELECT * FROM media';
	const WHEN_ACTIVE = '1-2,00:30-12:00';
	const LDAP_SERVER_NAME = 'TEST';
	const MEDIA_MAPPING_UPDATE = 'Media mapping with severity: none';
	const MEDIA_MAPPING_REMOVE = 'Media mapping with severity: all';
	const DELETE_MEDIA = 'Zammad';
	const MEDIA_MAPPING_ZAMMAD = 'Disabled media that is enabled in Media types';
	const MEDIA_MAPPING_ZENDESK = 'Enabled media that is disabled in Media types';
	const MEDIA_MAPPING_OPSGENIE = 'Enabled media type Opsgenie';
	const MEDIA_MAPPING_EDIT_TYPE_1 = 'Media VictorOps for a type update';
	const MEDIA_MAPPING_EDIT_TYPE_2 = 'Media ServiceNow for a type update';
	const MEDIA_MAPPING_EDIT_TYPE_3 = 'Media Rocket.Chat for a different parameter update';
	const MEDIA_MAPPING_EDIT_TYPE_4 = 'Media OTRS CE for multiple media update';
	const MEDIA_MAPPING_EDIT_TYPE_5 = 'Media MantisBT for attribute update';

	/**
	 * Enable media types before test.
	 */
	public function prepareJitMedia() {
		$mediatypeids = CDBHelper::getAll("SELECT mediatypeid FROM media_type WHERE name IN ('iTop', 'SMS',".
				" 'MS Teams Workflow', 'Slack', 'OTRS', 'Opsgenie', 'Brevis.one', 'Discord', 'iLert', 'Jira', 'Line', 'Email',".
				" 'SysAid', 'Pushover', 'Telegram', 'Redmine', 'SIGNL4', 'PagerDuty', 'Zammad', 'Github', 'VictorOps',".
				" 'ServiceNow')"
		);

		foreach ($mediatypeids as $mediatype) {
			CDataHelper::call('mediatype.update', [
				[
					'mediatypeid' => $mediatype['mediatypeid'],
					'status' => 0
				]
			]);
		}

		CDataHelper::call('userdirectory.create', [
			[
				'idp_type' => 1,
				'name' => self::LDAP_SERVER_NAME,
				'host' => 'qa-ldap.zabbix.sandbox',
				'base_dn' => 'dc=zbx,dc=local',
				'port' => 389,
				'search_attribute' => 'uid',
				'bind_password' => PHPUNIT_LDAP_BIND_PASSWORD,
				'provision_status' => 1,
				'provision_groups' => [
					[
						'name' => '*',
						'roleid' => 2, // Admin.
						'user_groups' => [
							[
								'usrgrpid' => 7 //Zabbix administrators.
							]
						]
					]
				],
				'provision_media' => [
					[
						'name' => self::MEDIA_MAPPING_REMOVE,
						'mediatypeid' => 42, // MS Teams Workflow.
						'attribute' => 'uid',
						'severity' => 63 // All severity options selected.
					],
					[
						'name' => self::MEDIA_MAPPING_UPDATE,
						'mediatypeid' => 21, // OTRS.
						'attribute' => 'uid',
						'severity' => 0 // None.
					],
					[
						'name' => self::MEDIA_MAPPING_OPSGENIE,
						'mediatypeid' => 6, // Opsgenie.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_ZENDESK,
						'mediatypeid' => 17, // Zendesk.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_ZAMMAD,
						'mediatypeid' => 19, // Zammad.
						'attribute' => 'uid',
						'active' => 1
					],
					[
						'name' => self::MEDIA_MAPPING_EDIT_TYPE_1,
						'mediatypeid' => 28, // VictorOps.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_EDIT_TYPE_2,
						'mediatypeid' => 18, // ServiceNow.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
						'mediatypeid' => 27, // Rocket.Chat.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_EDIT_TYPE_4,
						'mediatypeid' => 39, // OTRS CE.
						'attribute' => 'uid'
					],
					[
						'name' => self::MEDIA_MAPPING_EDIT_TYPE_5,
						'mediatypeid' => 41, // OTRS CE.
						'attribute' => 'uid'
					]
				]
			]
		]);

		CDataHelper::call('authentication.update', [
				'authentication_type' => 1,
				'ldap_auth_enabled' => 1,
				'disabled_usrgrpid' => 9, // Disabled.
				'ldap_jit_status' => 1
		]);
	}

	public function testFormUserLdapMediaJit_CheckProvisionedMediaLayout() {

		// Media types to appear after the provisioning.
		$media_types = ['MantisBT', 'MS Teams Workflow', 'Opsgenie', 'OTRS', 'OTRS CE', 'Rocket.Chat', 'ServiceNow', 'VictorOps', 'Zammad', 'Zendesk'];

		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit')->waitUntilReady();

		// Check that the informative message about JIT provisioning is present.
		$this->assertEquals('This user is IdP provisioned. Manual changes for provisioned fields are not allowed.',
				$this->query('class:msg-warning')->one()->getText()
		);

		$form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$form->selectTab('Media');
		$media_table = $form->query('id:media-table')->asTable()->one();

		// Check that correct amount of media is provisioned.
		$this->assertEquals(10, $media_table->getRows()->count());

		// Check that count of media is correctly displayed in the tab.
		$this->assertEquals(10, $form->query('xpath:.//a[text()="Media"]')->one()->getAttribute('data-indicator-value'));

		// Check that only edit action is enabled for provisioned media.
		foreach($media_types as $media_type) {
			$row = $media_table->findRow('Type', $media_type);
			$this->assertFalse($row->query('button:Remove')->one()->isClickable());
			$this->assertTrue($row->query('button:Edit')->one()->isClickable());
		}

		// Check hintbox and status for media that is disabled in Media types.
		$row = $media_table->findRow('Type', 'Zendesk', true);
		$this->assertTrue($row->getColumn('Type')->query('xpath:.//button['.CXPathHelper::fromClass('zi-i-warning').']')
				->one()->isValid()
		);

		// Check that correct amount of hintboxes is present in the media table for disabled media.
		$this->assertEquals(4, $media_table->query('xpath:.//button['.CXPathHelper::fromClass('zi-i-warning').']')->count());

		// Check that Type and Send to fields are read-only for provisioned media.
		$media_table->findRow('Type', 'MS Teams Workflow')->getColumn('Actions')->query('button:Edit')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$media_form = $dialog->asForm();

		foreach( ['Type', 'Send to'] as $field) {
			$this->assertTrue($media_form->getField($field)->isEnabled(false));
		}
	}

	public function getMediaEditData() {
		return [
			// Check that When active is a mandatory field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => ''
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// Invalid characters in When active.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => 'test'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// Change editable fields - user macro in When active field.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'When active' => '{$TEST}'
					],
					'media' => 'Zendesk',
					'check_configuration' => [
						'Use if severity' => [
							'Not classified',
							'Information',
							'Warning',
							'Average',
							'High',
							'Disaster'
						],
						'When active' => '{$TEST}',
						'Enabled' => false
					]
				]
			],
			// Change editable fields - select all severities.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Use if severity' => [
							'Not classified',
							'Information',
							'Warning',
							'Average',
							'High',
							'Disaster'
						],
						'Enabled' => true
					],
					'media' => 'OTRS',
					'check_configuration' => [
						'Use if severity' => [
							'Not classified',
							'Information',
							'Warning',
							'Average',
							'High',
							'Disaster'
						],
						'When active' => '1-7,00:00-24:00',
						'Enabled' => true
					]
				]
			],
			// Change editable fields - unselect all severities.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Use if severity' => []
					],
					'media' => 'MS Teams Workflow',
					'check_configuration' => [
						'Use if severity' => [],
						'When active' => '1-7,00:00-24:00',
						'Enabled' => true
					]
				]
			],
			// Change editable fields - disable media which is enabled.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enabled' => false
					],
					'media' => 'Opsgenie',
					'check_configuration' => [
						'Use if severity' => [
							'Not classified',
							'Information',
							'Warning',
							'Average',
							'High',
							'Disaster'
						],
						'When active' => '1-7,00:00-24:00',
						'Enabled' => false
					]
				]
			],
			// Change editable fields - enable previously disabled media.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enabled' => true
					],
					'media' => 'Zammad',
					'check_configuration' => [
						'Use if severity' => [
							'Not classified',
							'Information',
							'Warning',
							'Average',
							'High',
							'Disaster'
						],
						'When active' => '1-7,00:00-24:00',
						'Enabled' => true
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getMediaEditData
	 */
	public function testFormUserLdapMediaJit_CheckEditableFields($data) {

		// Log in as the LDAP provisioned user.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit');

		$old_hash = CDBHelper::getHash(self::HASH_SQL);

		// Close the warning message, to not affect further message check.
		$this->query('class:btn-overlay-close')->one()->click();

		$form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$form->selectTab('Media');
		$media_field = $this->query('name:user_form')->waitUntilVisible()->asForm()->one()->getField('Media')->asTable();
		$row = $media_field->findRow('Type', $data['media']);
		$row->getColumn('Actions')->query('button:Edit')->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$media_form = $dialog->asForm();
		$media_form->fill($data['fields']);
		$media_form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['message']);
			$dialog->close();
		}

		$form->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User updated');

		if ($data['expected'] === TEST_BAD) {
			$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
		}
		else {
			$this->page->logout();

			// Log in as the Super admin and provision the LDAP user.
			$this->page->login()->open('zabbix.php?action=user.list');
			$this->provisionLdapUser();
			$this->page->logout();

			// Log in as the provisioned user, and check that manually changed fields are not affected by the provisioning.
			$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
			$this->page->open('zabbix.php?action=userprofile.edit');
			$this->checkMediaConfiguration($data, $data['media'], PHPUNIT_LDAP_USERNAME, 'check_configuration');
		}
	}

	/**
	 * Check that LDAP provisioned user can add and remove non-provisioned media.
	 */
	public function testFormUserLdapMediaJit_AddRemoveMedia() {

		// Media type configuration.
		$data = [
			'fields' => [
				'Type' => 'SMS',
				'Send to' => 'test',
				'When active' => '1-7,00:00-24:00',
				'Use if severity' => [
					'Not classified',
					'Information',
					'Warning',
					'Average',
					'High',
					'Disaster'
				],
				'Enabled' => true
			]
		];

		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit');

		$form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$form->selectTab('Media');

		$this->query('button:Add')->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$media_form = $dialog->asForm();
		$media_form->fill(['Type' => 'SMS', 'Send to' => 'test']);

		$media_form->submit();
		$form->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User updated');

		// Check the media type that was added.
		$this->page->open('zabbix.php?action=userprofile.edit');
		$this->checkMediaConfiguration($data, $data['fields']['Type'], $data['fields']['Send to']);
		$this->page->logout();

		// Provision LDAP user.
		$this->page->login()->open('zabbix.php?action=user.list');
		$this->provisionLdapUser();
		$this->page->logout();

		// Log in as LDAP user and check that added media is still present.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit');
		$this->checkMediaConfiguration($data, $data['fields']['Type'], $data['fields']['Send to']);

		// Check that media can be removed by LDAP provisioned user.
		$form->selectTab('Media');
		$media_field = $this->query('name:user_form')->waitUntilVisible()->asForm()->one()->getField('Media')->asTable();
		$row = $media_field->findRow('Type', $data['fields']['Type'])->asTableRow();
		$row->getColumn('Actions')->query('button:Remove')->one()->click();
		$form->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User updated');

		// Check that media is no longer present in the list.
		$this->page->open('zabbix.php?action=userprofile.edit');
		$form->selectTab('Media');
		$media_updated = $this->query('name:user_form')->waitUntilVisible()->asForm()->one()->getField('Media')->asTable();
		$this->assertFalse($media_updated->findRow('Type', $data['fields']['Type'])->isPresent());
	}

	public function getUpdateMediaMappings() {
		return [
			// Media type update to other enabled media type.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_1,
							'configuration' => [
								'fields' => [
									'Type' => 'VictorOps',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => true
								]
							],
							'update' => [
								'Media type' => 'Github'
							],
							'expected' => [
								'fields' => [
									'Type' => 'Github',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => true
								]
							]
						]
					]
				]
			],
			// Media type update to other disabled media type.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_2,
							'configuration' => [
								'fields' => [
									'Type' => 'ServiceNow',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => true
								]
							],
							'update' => [
								'Media type' => 'Mattermost'
							],
							'expected' => [
								'fields' => [
									'Type' => 'Mattermost',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// Media type severity update: severity.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
							'configuration' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'Use if severity' => []
							],
							'expected' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// Media type status update.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
							'configuration' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'Create enabled' => true
							],
							'expected' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// Media type update When active update.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
							'configuration' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'When active' => '1-5,00:00-22:00'
							],
							'expected' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// Several media type mapping update.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_4,
							'configuration' => [
								'fields' => [
									'Type' => 'OTRS CE',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'Media type' => 'SysAid'
							],
							'expected' => [
								'fields' => [
									'Type' => 'SysAid',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => true
								]
							]
						],
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
							'configuration' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'Media type' => 'MS Teams',
								'Use if severity' => ['High', 'Disaster']
							],
							'expected' => [
								'fields' => [
									'Type' => 'MS Teams',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// Media type attribute change.
			[
				[
					'media_types' => [
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_5,
							'configuration' => [
								'fields' => [
									'Type' => 'MantisBT',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
									'Enabled' => false
								]
							],
							'update' => [
								'Attribute' => 'not_existing_attribute'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateMediaMappings
	 *
	 * Function to check that provisioned user's media is updated accordingly to media mapping.
	 */
	public function testFormUserLdapMediaJit_UpdateMediaMapping($data) {

		// Log in as the LDAP user, to make sure, that user is provisioned.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->logout();

		// Open media mapping to update.
		$form = $this->openLdapForm();
		$table = $form->query('id:ldap-servers')->asTable()->one();
		$table->query('link:'.self::LDAP_SERVER_NAME)->one()->click();

		foreach ($data['media_types'] as $media_type) {
			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$media_table = $dialog->query('id:ldap-media-type-mapping-table')->asTable()->one();
			$media_table->query('link', $media_type['name'])->one()->click();
			$media_form = COverlayDialogElement::find()->all()->last()->asForm();
			$media_form->fill($media_type['update']);
			$media_form->submit();
		}

		$dialog->query('button:Update')->one()->click();
		$form->submit();

		// Check that no changes are present until user is provisioned.
		$this->page->open('zabbix.php?action=user.list')->waitUntilReady();
		$this->query('link:'.PHPUNIT_LDAP_USERNAME)->one()->click();
		$this->assertEquals(10, $this->getUserMediaTable()->getRows()->count());

		foreach ($data['media_types'] as $media_type) {
			$this->checkMediaConfiguration($media_type['configuration'], $media_type['configuration']['fields']['Type'], PHPUNIT_LDAP_USERNAME);
		}

		// Provision the LDAP user.
		$this->page->open('zabbix.php?action=user.list');
		$this->provisionLdapUser();

		$this->page->open('zabbix.php?action=user.list')->waitUntilReady();
		$this->query('link:'.PHPUNIT_LDAP_USERNAME)->one()->click();
		$user_media_table = $this->getUserMediaTable();

		foreach ($data['media_types'] as $media_type) {
			if (array_key_exists('Attribute', $media_type['update'])) {
				$this->assertFalse($user_media_table->findRow('Type', '	MantisBT', true)->isPresent());
				$this->assertEquals(9, $user_media_table->getRows()->count());
			}
			else {
				$this->checkMediaConfiguration($media_type['expected'], $media_type['expected']['fields']['Type'], PHPUNIT_LDAP_USERNAME);
			}
		}
	}

	public function getNewMediaMappings() {
		return [
			// Media type severity - Not classified.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - Not classified',
						'Media type' => 'SMS',
						'Attribute' => 'uid',
						'Use if severity' => ['Not classified']
					],
					'expected' => [
						'fields' => [
							'Type' => 'SMS',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Not classified'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - Information.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - Information',
						'Media type' => 'Brevis.one',
						'Attribute' => 'uid',
						'Use if severity' => ['Information']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Brevis.one',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Information'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - Warning.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - Warning',
						'Media type' => 'Slack',
						'Attribute' => 'uid',
						'Use if severity' => ['Warning']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Slack',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Warning'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - Average.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - Average',
						'Media type' => 'Jira',
						'Attribute' => 'uid',
						'Use if severity' => ['Average']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Jira',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Average'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - High.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - High',
						'Media type' => 'iLert',
						'Attribute' => 'uid',
						'Use if severity' => ['High']
					],
					'expected' => [
						'fields' => [
							'Type' => 'iLert',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['High'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - Disaster.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - Disaster',
						'Media type' => 'Pushover',
						'Attribute' => 'uid',
						'Use if severity' => ['Disaster']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Pushover',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Disaster'],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - No severity.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - no severity',
						'Media type' => 'Telegram',
						'Attribute' => 'uid',
						'Use if severity' => []
					],
					'expected' => [
						'fields' => [
							'Type' => 'Telegram',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => [],
							'Enabled' => true
						]
					]
				]
			],
			// Media type severity - All severity.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - All severity',
						'Media type' => 'Redmine',
						'Attribute' => 'uid',
						'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Redmine',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
							'Enabled' => true
						]
					]
				]
			],
			// When active - custom value.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - custom when active',
						'Media type' => 'SIGNL4',
						'Attribute' => 'uid',
						'When active' => '5-7,01:00-23:30'
					],
					'expected' => [
						'fields' => [
							'Type' => 'SIGNL4',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '5-7,01:00-23:30',
							'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
							'Enabled' => true
						]
					]
				]
			],
			// When active - user macro syntax.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - when active with user macro',
						'Media type' => 'Discord',
						'Attribute' => 'uid',
						'When active' => '{$TEST}',
						'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']
					],
					'expected' => [
						'fields' => [
							'Type' => 'Discord',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '{$TEST}',
							'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
							'Enabled' => true
						]
					]
				]
			],
			// Create enabled: false.
			[
				[
					'provisioned' => true,
					'mapping' => [
						'Name' => 'Media mapping - disabled in LDAP',
						'Media type' => 'PagerDuty',
						'Attribute' => 'uid',
						'Create enabled' => false
					],
					'expected' => [
						'fields' => [
							'Type' => 'PagerDuty',
							'Send to' => PHPUNIT_LDAP_USERNAME,
							'When active' => '1-7,00:00-24:00',
							'Use if severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
							'Enabled' => false
						]
					]
				]
			],
			// Email type media, which won't be added due to email validation.
			[
				[
					'provisioned' => false,
					'mapping' => [
						'Name' => 'Media mapping - Email',
						'Media type' => 'Email',
						'Attribute' => 'uid',
						'Use if severity' => []
					]
				]
			],
			// Media type with non existing attribute.
			[
				[
					'provisioned' => false,
					'mapping' => [
						'Name' => 'Media mapping - incorrect attribute',
						'Media type' => 'Line',
						'Attribute' => 'test',
						'Use if severity' => []
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getNewMediaMappings
	 *
	 * Function to check that new media is added to the user after updating the mapping and provisioning.
	 */
	public function testFormUserLdapMediaJit_AddMediaMapping($data) {
		$form = $this->openLdapForm();
		$table = $form->query('id:ldap-servers')->asTable()->one();
		$table->query('link:'.self::LDAP_SERVER_NAME)->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$media_table = $dialog->query('id:ldap-media-type-mapping-table')->asTable()->one();
		$media_table->query('button:Add')->one()->click();
		$media_mapping_form = COverlayDialogElement::find()->all()->last()->asForm();

		$media_mapping_form->fill($data['mapping']);
		$media_mapping_form->submit();
		$dialog->query('button:Update')->one()->click();
		$form->submit();

		// Log in as LDAP user to check that media mapping was processed correctly.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit');

		if ($data['provisioned'] === true) {
			$this->checkMediaConfiguration($data['expected'], $data['mapping']['Media type'], PHPUNIT_LDAP_USERNAME);
		}
		else {
			$this->assertFalse($this->getUserMediaTable()->findRow('Type', $data['mapping']['Media type'])->isPresent());
		}
	}

	public function testFormUserLdapMediaJit_DeleteMediaType() {

		// Log in as the LDAP user, to make sure, that user is created.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);

		// Check that media type for deletion is present in user configuration.
		$this->page->open('zabbix.php?action=userprofile.edit')->waitUntilReady();
		$this->assertTrue($this->getUserMediaTable()->findRow('Type', self::DELETE_MEDIA, true)->isPresent());
		$this->page->logout();

		// Delete media type that is used in LDAP media mapping.
		$this->page->login()->open('zabbix.php?action=mediatype.list')->waitUntilReady();
		$table = $this->getTable();
		$table->findRows('Name', self::DELETE_MEDIA)->select();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Media type deleted');

		// Check that media type is removed from LDAP user.
		$this->page->open('zabbix.php?action=user.list')->waitUntilReady();
		$this->query('link:'.PHPUNIT_LDAP_USERNAME)->one()->click();
		$this->assertFalse($this->getUserMediaTable()->findRow('Type', self::DELETE_MEDIA, true)->isPresent());
	}

	public function testFormUserLdapMediaJit_RemoveMediaMapping() {

		// Log in as the LDAP user, to make sure, that user is provisioned.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->logout();

		// Remove media mapping from LDAP configurations.
		$form = $this->openLdapForm();
		$table = $form->query('id:ldap-servers')->asTable()->one();
		$table->query('link:'.self::LDAP_SERVER_NAME)->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$media_table = $dialog->query('id:ldap-media-type-mapping-table')->asTable()->one();
		$media_table->findRow('Name', self::MEDIA_MAPPING_REMOVE, true)->query('button:Remove')->one()->click();
		$dialog->query('button:Update')->one()->click();
		$form->submit();

		// Check that media is not present for LDAP provisioned user.
		$this->page->open('zabbix.php?action=user.list')->waitUntilReady();
		$this->query('link:'.PHPUNIT_LDAP_USERNAME)->one()->click();
		$user_media_table = $this->getUserMediaTable();
		$this->assertFalse($user_media_table->findRow('Type', 'MS Teams Workflow', true)->isPresent());
	}

	/**
	 * Check media type configuration in user configuration form.
	 *
	 * @param array		$data				data provider
	 * @param string	$type				type of the media
	 * @param string	$send_to			send to parameter of the media
	 * @param string	$expected			name of the array with expected result
	 */
	private function checkMediaConfiguration($data, $type, $send_to, $expected = 'fields') {
		// Check media type.
		$media_field = $this->query('name:user_form')->waitUntilVisible()->asForm()->one()->getField('Media')->asTable();
		$row = $media_field->findRow('Type', $type)->asTableRow();

		$this->assertEquals($row->getColumn('Type')->getText(), $type);

		// Check the value of the "Send to" field.
		$this->assertFalse($row->query('xpath:./td[2]/span[@data-hintbox]')->one(false)->isValid());
		$get_send_to = $row->getColumn('Send to')->getText();
		$this->assertEquals($send_to, $get_send_to);

		// Check media active period.
		$when_active = $row->getColumn('When active')->getText();
		$this->assertEquals($when_active, CTestArrayHelper::get($data, $expected.'.When active', '1-7,00:00-24:00'));

		// Check media status.
		$get_status = $row->getColumn('Status')->getText();
		$status = CTestArrayHelper::get($data, $expected.'.Enabled', true) ? 'Enabled' : 'Disabled';
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

		if (array_key_exists('Use if severity', $data[$expected])) {
			// Check that the passed severities are turned on.
			foreach ($data[$expected]['Use if severity'] as $used_severity) {
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

	/**
	 * Function for opening LDAP configuration form.
	 */
	private function openLdapForm() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('LDAP settings');

		return $form;
	}

	/**
	 * Function for provisioning the user.
	 */
	private function provisionLdapUser() {
		$table = $this->getTable();
		$table->findRows('Username', PHPUNIT_LDAP_USERNAME)->select();
		$this->query('button:Provision now')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Provisioning successful.', 'User "'.PHPUNIT_LDAP_USERNAME.'" provisioned.');
	}

	/**
	 * Function for selecting the user and getting the media table.
	 */
	private function getUserMediaTable() {
		$user_form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$user_form->selectTab('Media');
		$user_media_table = $this->query('id:media-table')->asTable()->one();

		return $user_media_table;
	}
}
