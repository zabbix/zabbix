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

	protected static $provisioned_media_count;

	const HASH_SQL = 'SELECT * FROM media';
	const LDAP_SERVER_NAME = 'TEST';
	const MEDIA_MAPPING_REMOVE = 'Media mapping with severity: all';
	const DELETE_MEDIA = 'Zammad';
	const MEDIA_MAPPING_EDIT_TYPE_1 = 'Media VictorOps for a type update';
	const MEDIA_MAPPING_EDIT_TYPE_2 = 'Media ServiceNow for a type update';
	const MEDIA_MAPPING_EDIT_TYPE_3 = 'Media Rocket.Chat for a different parameter update';
	const MEDIA_MAPPING_EDIT_TYPE_4 = 'Media OTRS CE for multiple media update';
	const MEDIA_MAPPING_EDIT_TYPE_5 = 'Media MantisBT for attribute update';

	/**
	 * Enable media types before test.
	 */
	public function prepareJitMedia() {
		$mediatypeids = CDBHelper::getAll('SELECT mediatypeid FROM media_type WHERE name IN (\'iTop\', \'SMS\','.
				' \'MS Teams Workflow\', \'Slack\', \'OTRS\', \'Opsgenie\', \'Brevis.one\', \'Github\', \'Discord\','.
				' \'iLert\', \'SIGNL4\', \'SysAid\', \'Jira\', \'Line\', \'Email\', \'PagerDuty\', \'Pushover\','.
				' \'Telegram\', \'Redmine\', \'Zammad\', \'VictorOps\', \'ServiceNow\')'
		);

		$update_api = [];

		foreach ($mediatypeids as $i => $mediatype) {
			$update_api[$i]['mediatypeid'] = $mediatype['mediatypeid'];
			$update_api[$i]['status'] = 0;
		}

		CDataHelper::call('mediatype.update', $update_api);

		$provisioned_media = [
			[
				'name' => self::MEDIA_MAPPING_REMOVE,
				'mediatypeid' => 42, // MS Teams Workflow.
				'attribute' => 'uid',
				'severity' => 63 // All severity options selected.
			],
			[
				'name' => 'Media mapping with severity: none',
				'mediatypeid' => 21, // OTRS.
				'attribute' => 'uid',
				'severity' => 0 // None.
			],
			[
				'name' => 'Enabled media type Opsgenie',
				'mediatypeid' => 6, // Opsgenie.
				'attribute' => 'uid'
			],
			[
				'name' => 'Enabled media that is disabled in Media types',
				'mediatypeid' => 17, // Zendesk.
				'attribute' => 'uid'
			],
			[
				'name' => 'Disabled media that is enabled in Media types',
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
		];
		self::$provisioned_media_count = count($provisioned_media);

		CDataHelper::call('userdirectory.create', [
			[
				'idp_type' => IDP_TYPE_LDAP,
				'name' => self::LDAP_SERVER_NAME,
				'host' => 'qa-ldap.zabbix.sandbox',
				'base_dn' => 'dc=zbx,dc=local',
				'port' => 389,
				'search_attribute' => 'uid',
				'bind_password' => PHPUNIT_LDAP_BIND_PASSWORD,
				'provision_status' => JIT_PROVISIONING_ENABLED,
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
				'provision_media' => $provisioned_media
			]
		]);

		CDataHelper::call('authentication.update', [
				'authentication_type' => SMTP_AUTHENTICATION_NORMAL,
				'ldap_auth_enabled' => ZBX_AUTH_LDAP_ENABLED,
				'disabled_usrgrpid' => 9, // Disabled.
				'ldap_jit_status' => JIT_PROVISIONING_ENABLED
		]);
	}

	public function testFormUserLdapMediaJit_CheckProvisionedMediaLayout() {
		// Media types to appear after the provisioning.
		$media_types = ['MantisBT', 'MS Teams Workflow', 'Opsgenie', 'OTRS', 'OTRS CE', 'Rocket.Chat', 'ServiceNow',
				'VictorOps', 'Zammad', 'Zendesk'
		];

		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit')->waitUntilReady();

		// Check that the informative message about JIT provisioning is present.
		$this->assertMessage('Warning', null, 'This user is IdP provisioned. Manual changes for provisioned fields'.
				' are not allowed.'
		);

		$form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$form->selectTab('Media');
		$media_table = $form->query('id:media-table')->asTable()->one();

		// Check that correct amount of media is provisioned.
		$this->assertEquals(self::$provisioned_media_count, $media_table->getRows()->count());

		// Check that count of media is correctly displayed in the tab.
		$this->assertEquals(self::$provisioned_media_count, $form->query('xpath:.//a[text()="Media"]')->one()
				->getAttribute('data-indicator-value')
		);

		// Check that only edit action is enabled for provisioned media.
		foreach ($media_types as $media_type) {
			$row = $media_table->findRow('Type', $media_type);
			$this->assertFalse($row->query('button:Remove')->one()->isClickable());
			$this->assertTrue($row->query('button:Edit')->one()->isClickable());
		}

		// Check the pressence and amount of hintboxes in media table for disabled media.
		$media_with_hints = ['MantisBT', 'OTRS CE', 'Rocket.Chat', 'Zendesk'];

		foreach ($media_with_hints as $media_type) {
			$row = $media_table->findRow('Type', $media_type, true);
			$this->assertTrue($row->getColumn('Type')
					->query('xpath:.//button['.CXPathHelper::fromClass('zi-i-warning').']')->one()->isValid()
			);
			$this->assertEquals('Media type disabled by Administration.', $row->getColumn('Type')
					->query('tag:button')->one()->getAttribute('data-hintbox-contents')
			);
		}

		$this->assertEquals(count($media_with_hints),
				$media_table->query('xpath:.//button['.CXPathHelper::fromClass('zi-i-warning').']')->count()
		);

		// Check that Type and Send to fields are read-only for provisioned media.
		$media_table->findRow('Type', 'MS Teams Workflow')->getColumn('Action')->query('button:Edit')->one()->click();
		$media_form = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();

		foreach (['Type', 'Send to'] as $field) {
			$this->assertTrue($media_form->getField($field)->isEnabled(false));
		}
	}

	public function getMediaEditData() {
		return [
			// #0 Check that When active is a mandatory field.
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
			// #1 Invalid characters in When active.
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
			// #2 Invalid When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => '1-8,11:11-22:22'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #3 Invalid When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => '6-5, 11:11-22:22'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #4 Invalid When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => '0-1, 00:00-11:11'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #5 Invalid When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => '1-7, 22:22-22:21'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #6 Invalid When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => '1-7, 00:00-24:01'
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #7 Space used as When active value.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'When active' => ' '
					],
					'message' => 'Incorrect value for field "period": a time period is expected.',
					'media' => 'MS Teams Workflow'
				]
			],
			// #8 Change editable fields - user macro in When active field.
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
			// #9 Change editable fields - select all severities.
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
			// #10 Change editable fields - unselect all severities.
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
			// #11 Change editable fields - disable media which is enabled.
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
			// #12 Change editable fields - enable previously disabled media.
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
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::HASH_SQL);
		}

		// Log in as the LDAP provisioned user.
		$this->page->userLogin(PHPUNIT_LDAP_USERNAME, PHPUNIT_LDAP_USER_PASSWORD);
		$this->page->open('zabbix.php?action=userprofile.edit');

		// Close the warning message, to not affect further message check.
		$this->query('class:btn-overlay-close')->one()->click();

		$form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$form->selectTab('Media');
		$media_field = $form->getField('Media')->asTable();
		$row = $media_field->findRow('Type', $data['media']);
		$row->getColumn('Action')->query('button:Edit')->one()->click();

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

			// Log in as the provisioned user, to check that changed fields are not affected by the provisioning.
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
		$media_form->fill(['Type' => $data['fields']['Type'], 'Send to' => $data['fields']['Send to']]);

		$media_form->submit();
		$dialog->ensureNotPresent();
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
		$row = $media_field->findRow('Type', $data['fields']['Type']);
		$row->getColumn('Action')->query('button:Remove')->one()->click();
		$form->query('button:Update')->one()->click();
		$this->assertMessage(TEST_GOOD, 'User updated');

		// Check that media is no longer present in the list.
		$this->page->open('zabbix.php?action=userprofile.edit');
		$form->selectTab('Media');
		$this->assertFalse($form->getField('Media')->asTable()->findRow('Type', $data['fields']['Type'])->isPresent());
	}

	public function getUpdateMediaMappings() {
		return [
			// #0 Media type update to other enabled media type.
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
							],
							'update' => [
								'Media type' => 'Github'
							],
							'expected' => [
								'fields' => [
									'Type' => 'Github',
									'Send to' => PHPUNIT_LDAP_USERNAME,
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
							]
						]
					]
				]
			],
			// #1 Media type update to other disabled media type.
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
							],
							'update' => [
								'Media type' => 'Mattermost'
							],
							'expected' => [
								'fields' => [
									'Type' => 'Mattermost',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// #2 Media type severity update: severity.
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// #3 Media type status update.
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// #4 Media type update When active update.
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// #5 Several media type mapping update.
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
							]
						],
						[
							'name' => self::MEDIA_MAPPING_EDIT_TYPE_3,
							'configuration' => [
								'fields' => [
									'Type' => 'Rocket.Chat',
									'Send to' => PHPUNIT_LDAP_USERNAME,
									'When active' => '1-7,00:00-24:00',
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
									'Enabled' => false
								]
							]
						]
					]
				]
			],
			// #6 Media type attribute change.
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
									'Use if severity' => [
										'Not classified',
										'Information',
										'Warning',
										'Average',
										'High',
										'Disaster'
									],
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
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		foreach ($data['media_types'] as $media_type) {
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
		$this->assertEquals(self::$provisioned_media_count, $this->getUserMediaTable()->getRows()->count());

		foreach ($data['media_types'] as $media_type) {
			$this->checkMediaConfiguration($media_type['configuration'], $media_type['configuration']['fields']['Type'],
					PHPUNIT_LDAP_USERNAME
			);
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
				$this->assertEquals(self::$provisioned_media_count - 1, $user_media_table->getRows()->count());
			}
			else {
				$this->checkMediaConfiguration($media_type['expected'], $media_type['expected']['fields']['Type'],
						PHPUNIT_LDAP_USERNAME
				);
			}
		}
	}

	public function getNewMediaMappings() {
		return [
			// #0 Media type severity - Not classified.
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
			// #1 Media type severity - Information.
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
			// #2 Media type severity - Warning.
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
			// #3 Media type severity - Average.
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
			// #4 Media type severity - High.
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
			// #5 Media type severity - Disaster.
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
			// #6 Media type severity - No severity.
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
			// #7 Media type severity - All severity.
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
					]
				]
			],
			// #8 When active - custom value.
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
					]
				]
			],
			// #9 When active - user macro syntax.
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
					]
				]
			],
			// #10 Create enabled: false.
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
							'Use if severity' => [
								'Not classified',
								'Information',
								'Warning',
								'Average',
								'High',
								'Disaster'
							],
							'Enabled' => false
						]
					]
				]
			],
			// #11 Email type media, which won't be added due to email validation.
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
			// #12 Media type with non existing attribute.
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
	 * Function to check that new media is added to the user after updating the mapping and provisioning.
	 *
	 * @dataProvider getNewMediaMappings
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
			$this->assertFalse($this->getUserMediaTable()->findRow('Type', $data['mapping']['Media type'])
					->isPresent()
			);
		}
	}

	public function testFormUserLdapMediaJit_DeleteMediaType() {
		// Log in as the LDAP user, to make sure, that user is provisioned.
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
	 * @param string	$media_type			type of the media
	 * @param string	$send_to			send to parameter of the media
	 * @param string	$expected			name of the array with expected result
	 */
	protected function checkMediaConfiguration($data, $media_type, $send_to, $expected = 'fields') {
		// Check media type.
		$row = $this->query('id:media-table')->asTable()->one()->findRow('Type', $media_type);

		$this->assertEquals($row->getColumn('Type')->getText(), $media_type);

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
				$actual_severity = $row->query('xpath:./td[4]/div/span['.$reference_severities[$used_severity].']')
						->one()->getAttribute('data-hintbox-contents');
				$this->assertEquals($actual_severity, $used_severity.' (on)');
				unset($reference_severities[$used_severity]);
			}
			// Check that other severities are turned off.
			foreach ($reference_severities as $name => $unused_severity) {
				$actual_severity = $row->query('xpath:./td[4]/div/span['.$unused_severity.']')->one()
						->getAttribute('data-hintbox-contents');
				$this->assertEquals($name.' (off)', $actual_severity);
			}
		}
	}

	/**
	 * Function for opening LDAP configuration form.
	 *
	 * @return CFormElement
	 */
	protected function openLdapForm() {
		$this->page->login()->open('zabbix.php?action=authentication.edit');
		$form = $this->query('id:authentication-form')->asForm()->one();
		$form->selectTab('LDAP settings');

		return $form;
	}

	/**
	 * Function for provisioning the user.
	 */
	protected function provisionLdapUser() {
		$table = $this->getTable();
		$table->findRows('Username', PHPUNIT_LDAP_USERNAME)->select();
		$this->query('button:Provision now')->one()->click();
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Provisioning successful.', 'User "'.PHPUNIT_LDAP_USERNAME.'" provisioned.');
	}

	/**
	 * Function for selecting the user and getting the media table.
	 *
	 * @return CTableElement
	 */
	protected function getUserMediaTable() {
		$user_form = $this->query('id:user-form')->waitUntilVisible()->asForm()->one();
		$user_form->selectTab('Media');

		return $this->query('id:media-table')->asTable()->one();
	}
}
