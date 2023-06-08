<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * @backup drules
 *
 * @dataSource NetworkDiscovery
 */
class testFormNetworkDiscovery extends CWebTest {

	use TableTrait;

	CONST DELETE_RULE = 'Discovery rule to check delete';
	CONST CANCEL_RULE = 'Discovery rule for cancelling scenario';

	/**
	 * Name of discovery rule for update scenario.
	 */
	protected static $update_rule = 'Discovery rule for update';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * Get discovery rules and checks tables hash values.
	 */
	private static function getHash() {
		return CDBHelper::getHash( 'SELECT * FROM drules').
				CDBHelper::getHash('SELECT * FROM dchecks');
	}

	public function testFormNetworkDiscovery_Layout() {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Discovery rules');
		$this->page->assertTitle('Configuration of discovery rules');
		$form = $this->query('id:discoveryForm')->asForm()->one();

		// Check that all labels present and visible.
		$this->assertEquals(['Name', 'Discovery by proxy', 'IP range', 'Update interval', 'Checks',
				'Device uniqueness criteria', 'Host name', 'Visible name', 'Enabled'],
				$form->getLabels(CElementFilter::VISIBLE)->asText()
		);

		// Check required fields.
		$this->assertEquals(['Name', 'IP range', 'Update interval', 'Checks'], $form->getRequiredLabels());

		// Check the default values.
		$form->checkValue([
			'Name' => '',
			'Discovery by proxy' => 'No proxy',
			'IP range' => '192.168.0.1-254',
			'Update interval' => '1h',
			'Enabled' => true
		]);

		// Radio fields are checked separately, because they are unique elements and don't match with any Framework element.
		foreach (['IP address', 'DNS name', 'Host name'] as $label) {
			$this->assertTrue($form->query("xpath:.//label[text()=".CXPathHelper::escapeQuotes($label).
					"]/../input[@checked]")->exists()
			);
		}

		foreach (['Name' => 255, 'IP range' => 2048, 'Update interval' => 255] as $name => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($name)->getAttribute('maxlength'));
		}

		// New check adding dialog.
		$form->getField('Checks')->query('button:Add')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('Discovery check', $dialog->getTitle());
		$checks_form = $dialog->asForm();
		$this->assertEquals(['Check type', 'Port range'], $checks_form->getLabels(CElementFilter::VISIBLE)->asText());
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		$check_types = [
			'FTP' => 21,
			'HTTP' => 80,
			'HTTPS' => 443,
			'ICMP ping' => null,
			'IMAP' => 143,
			'LDAP' => 389,
			'NNTP' => 119,
			'POP' => 110,
			'SMTP' => 25,
			'SNMPv1 agent' => 161,
			'SNMPv2 agent' => 161,
			'SNMPv3 agent' => 161,
			'SSH' => 22,
			'TCP' => 0,
			'Telnet' => 23,
			'Zabbix agent' => 10050
		];
		$this->assertEquals(array_keys($check_types), $checks_form->getField('Check type')->asDropdown()->getOptions()->asText());

		foreach ($check_types as $type => $port) {
			$checks_form->fill(['Check type' => $type]);

			if ($type === 'ICMP ping') {
				$this->assertEquals(['Check type'], $checks_form->getLabels(CElementFilter::VISIBLE)->asText());
			}
			else {
				$checks_form->checkValue(['Port range' => $port]);
				$this->assertEquals(255, $checks_form->getField('Port range')->getAttribute('maxlength'));

				switch ($type) {
					case 'Zabbix agent':
						$this->assertEqualsCanonicalizing(['Check type', 'Port range', 'Key'],
								$checks_form->getLabels(CElementFilter::VISIBLE)->asText()
						);
						$this->assertEquals(['Port range', 'Key'], $checks_form->getRequiredLabels());
						$checks_form->checkValue(['Key' => '']);
						$this->assertEquals(2048, $checks_form->getField('Key')->getAttribute('maxlength'));
						break;

					case 'SNMPv1 agent':
					case 'SNMPv2 agent':
						$this->assertEqualsCanonicalizing(['Check type', 'Port range', 'SNMP community', 'SNMP OID'],
								$checks_form->getLabels(CElementFilter::VISIBLE)->asText()
						);
						$this->assertEquals(['Port range', 'SNMP community', 'SNMP OID'], $checks_form->getRequiredLabels());
						$checks_form->checkValue(['SNMP community' => '', 'SNMP OID' => '']);

						foreach (['SNMP community' => 255, 'SNMP OID' => 512] as $name => $maxlength) {
							$this->assertEquals($maxlength, $checks_form->getField($name)->getAttribute('maxlength'));
						}
						break;

					case 'SNMPv3 agent':
						$fields = [
							'noAuthNoPriv' => [
								'values' => ['SNMP OID' => '', 'Context name' => '', 'Security name' => ''],
								'lengths' => ['SNMP OID' => 512, 'Context name' => 255, 'Security name' => 64],
								'required' => ['Port range', 'SNMP OID']
							],
							'authNoPriv' => [
								'values' => ['SNMP OID' => '', 'Context name' => '', 'Security name' => '',
										'Authentication protocol' => 'MD5', 'Authentication passphrase' => ''
								],
								'lengths' => ['SNMP OID' => 512, 'Context name' => 255, 'Security name' => 64,
										'Authentication passphrase' => 64
								],
								'required' => ['Port range', 'SNMP OID'],
								'Authentication protocol' => ['MD5', 'SHA1', 'SHA224', 'SHA256', 'SHA384', 'SHA512']
							],

							'authPriv' => [
								'values' => ['SNMP OID' => '', 'Context name' => '', 'Security name' => '',
										'Authentication protocol' => 'MD5', 'Authentication passphrase' => '',
										'Privacy protocol' => 'DES', 'Privacy passphrase' => ''
								],
								'lengths' => ['SNMP OID' => 512, 'Context name' => 255, 'Security name' => 64,
										'Authentication passphrase' => 64, 'Privacy passphrase' => 64
								],
								'required' => ['Port range', 'SNMP OID', 'Privacy passphrase'],
								'Authentication protocol' => ['MD5', 'SHA1', 'SHA224', 'SHA256', 'SHA384', 'SHA512'],
								'Privacy protocol' => ['DES', 'AES128', 'AES192', 'AES256', 'AES192C', 'AES256C']
							]
						];

						$this->assertEquals(array_keys($fields),
								$checks_form->getField('Security level')->asDropdown()->getOptions()->asText()
						);

						foreach ($fields as $level => $values) {
							$checks_form->fill(['Security level' => $level]);
							$checks_form->checkValue($values['values']);
							$this->assertEquals($values['required'], $checks_form->getRequiredLabels());
							$this->assertEquals(array_keys($fields),
									$checks_form->getField('Security level')->asDropdown()->getOptions()->asText()
							);

							foreach (['Authentication protocol', 'Privacy protocol'] as $dropdowns) {
								if (array_key_exists($dropdowns, $values)) {
									$this->assertEquals($values[$dropdowns], $checks_form->getField($dropdowns)->asDropdown()
											->getOptions()->asText()
									);
								}
							}
						}
						break;

					default:
						$this->assertEquals(['Port range'], $checks_form->getRequiredLabels());
						break;
				}
			}
		}

		$dialog->close();
	}

	public function getCommonData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty port'
					],
					'Checks' => [
						['Port range' => '']
					],
					'dialog_error' => 'Incorrect value for field "ports": cannot be empty.',
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty fields for SNMPv1'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => ''
						]
					],
					'dialog_error' => [
						'Incorrect value for field "ports": cannot be empty.',
						'Incorrect value for field "snmp_community": cannot be empty.',
						'Incorrect value for field "snmp_oid": cannot be empty.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation priority for SNMPv1'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => 'test',
							'SNMP community' => 'test',
							'SNMP OID' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Too big port for SNMPv2'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv2 agent',
							'Port range' => 65536,
							'SNMP community' => 'test',
							'SNMP OID' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in port for SNMPv2'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv2 agent',
							'Port range' => 'text',
							'SNMP community' => 'test',
							'SNMP OID' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation priority for SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv3 agent',
							'Port range' => 'text'
						]
					],
					'dialog_error' => 'Incorrect value for field "snmp_oid": cannot be empty.'
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation fields for SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv3 agent',
							'Port range' => 'text',
							'SNMP OID' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation authPriv for SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv3 agent',
							'Security level' => 'authNoPriv'
						]
					],
					'dialog_error' => 'Incorrect value for field "snmp_oid": cannot be empty.'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation authPriv for SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv3 agent',
							'Security level' => 'authPriv'
						]
					],
					'dialog_error' => [
						'Incorrect value for field "snmp_oid": cannot be empty.',
						'Incorrect value for field "snmpv3_privpassphrase": cannot be empty.'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation key for Zabbix agent'
					],
					'Checks' => [
						[
							'Check type' => 'Zabbix agent',
							'Key' => '😀'
						]
					],
					'dialog_error' => 'Invalid key "😀": incorrect syntax near "😀".'
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty fields for Zabbix agent'
					],
					'Checks' => [
						[
							'Check type' => 'Zabbix agent',
							'Port range' => ''
						]
					],
					'dialog_error' => [
						'Incorrect value for field "ports": cannot be empty.',
						'Incorrect value for field "key_": cannot be empty.'
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation priority for Zabbix agent'
					],
					'Checks' => [
						[
							'Check type' => 'Zabbix agent',
							'Port range' => 'test'
						]
					],
					'dialog_error' => 'Incorrect value for field "key_": cannot be empty.'
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Validation for Zabbix agent'
					],
					'Checks' => [
						[
							'Check type' => 'Zabbix agent',
							'Port range' => 'test',
							'Key' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ''
					],
					'error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Field "dchecks" is mandatory.'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty interval validation',
						'Update interval' => ''
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "delay": cannot be empty.'
				]
			],
			// #15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text interval validation',
						'Update interval' => 'text'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "delay": a time unit is expected.'
				]
			],
			// #16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Special symbols in interval validation',
						'Update interval' => '😀'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "delay": a time unit is expected.'
				]
			],
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative interval validation',
						'Update interval' => -1
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "delay": a time unit is expected.'
				]
			],
			// #18.
			[
				[
					'fields' => [
						'Name' => 'Mimimal fields create'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #19.
			[
				[
					'fields' => [
						'Name' => 'Name + 1 check'
					],
					'Checks' => [
						[
							'Check type' => 'HTTP',
							'Port range' => '65535'
						]
					]
				]
			],
			// #20.
			[
				[
					'fields' => [
						'Name' => 'All fields',
						'Discovery by proxy' => 'Active proxy 1',
						'IP range' => '192.168.251.253-254',
						'Update interval' => 604800,
						'Enabled' => false
					],
					'radios' => [
						'Device uniqueness criteria' => 'Zabbix agent (100) "test"',
						'Host name' => 'IP address',
						'Visible name' => 'DNS name'
					],
					'Checks' => [
						[
							'Check type' => 'HTTPS',
							'Port range' => 0
						],
						[
							'Check type' => 'ICMP ping'
						],
						[
							'Check type' => 'IMAP',
							'Port range' => 1
						],
						[
							'Check type' => 'Zabbix agent',
							'Port range' => 100,
							'Key' => 'test'
						],
					]
				]
			],
			// #21.
			[
				[
					'fields' => [
						'Name' => 'SNMP agents checks',
						'Update interval' => '7d',
						'Enabled' => true
					],
					'radios' => [
						'Device uniqueness criteria' => 'Zabbix agent "key[param1, param2]"',
						'Host name' => 'SNMPv1 agent (9999) "😀"',
						'Visible name' => 'SNMPv3 agent "😀"'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => 9999,
							'SNMP community' => 'test_community',
							'SNMP OID' => '😀'
						],
						[
							'Check type' => 'SNMPv2 agent',
							'SNMP community' => 'test_community',
							'SNMP OID' => 123456789
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => '😀',
							'Context name' => '😀',
							'Security name' => '😀',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA224',
							'Privacy protocol' => 'AES192C',
							'Privacy passphrase' => '😀'
						],
						[
							'Check type' => 'Zabbix agent',
							'Key' => 'key[param1, param2]'
						]
					]
				]
			],
			// #22.
			[
				[
					'trim' => true,
					'fields' => [
						'Name' => '         Spaces in name     ',
						'IP range' => '        192.168.251.253-254              ',
						'Update interval' => '       1h         '
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => '    9999     ',
							'SNMP community' => '   test_community        ',
							'SNMP OID' => '   test_oid       '
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => '   test_oid       ',
							'Context name' => '   test_context       ',
							'Security name' => '   test_security       ',
							'Security level' => 'authPriv',
							'Authentication passphrase' => '       auth_pass          ',
							'Privacy passphrase' => '      priv_pass          '
						],
						[
							'Check type' => 'Zabbix agent',
							'Key' => '     key[param1, param2]        '
						]
					]
				]
			]
		];
	}

	public function getCreateData() {
		return [
			// #23.
			[
				[
					'fields' => [
						'Name' => STRING_255
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'SNMP community' => STRING_255,
							'SNMP OID' => STRING_512
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => STRING_512,
							'Context name' => STRING_255,
							'Security name' => STRING_64,
							'Security level' => 'authPriv',
							'Authentication passphrase' => STRING_64,
							'Privacy passphrase' => STRING_64
						],
						[
							'Check type' => 'Zabbix agent',
							'Key' => STRING_2048
						]
					]
				]
			]
		];
	}

	public function getUpdateData() {
		return [
			// #23.
			[
				[
					'fields' => [
						'Name' => 'long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_string_long_stri'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'SNMP community' => STRING_255,
							'SNMP OID' => STRING_512
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => STRING_512,
							'Context name' => STRING_255,
							'Security name' => STRING_64,
							'Security level' => 'authPriv',
							'Authentication passphrase' => STRING_64,
							'Privacy passphrase' => STRING_64
						],
						[
							'Check type' => 'Zabbix agent',
							'Key' => STRING_2048
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCommonData
	 * @dataProvider getCreateData
	 */
	public function testFormNetworkDiscovery_Create($data) {
		$this->checkDiscoveryRuleForm($data);
	}

	/**
	 * @dataProvider getCommonData
	 * @dataProvider getUpdateData
	 */
	public function testFormNetworkDiscovery_Update($data) {
		$this->checkDiscoveryRuleForm($data, true);
	}

	private function checkDiscoveryRuleForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		if ($update) {
			$this->page->login()->open('zabbix.php?action=discovery.list');
			$this->query('link', self::$update_rule)->waitUntilClickable()->one()->click();
			$old_name = self::$update_rule;
		}
		else {
			$this->page->login()->open('zabbix.php?action=discovery.edit');
		}

		$form = $this->query('id:discoveryForm')->asForm()->one();

		if ($update && !CTestArrayHelper::get($data, 'expected')) {
			$data['fields']['Name'] = !CTestArrayHelper::get($data, 'trim')
				? $data['fields']['Name'].'update'
				: $data['fields']['Name'].'update       ';
		}

		// Clear all checks from discovery rule to change them to new ones from data provider.
		if ($update) {
			$this->removeAllChecks($form);
		}

		if (CTestArrayHelper::get($data, 'Checks')) {
			$add_button = $form->getField('Checks')->query('button:Add')->waitUntilClickable()->one();

			if (CTestArrayHelper::get($data, 'trim', false)) {
				foreach ($data['Checks'] as $check) {
					$check = array_map('trim', $check);
					$new_checks[] = $check;
				}
				$data['Checks'] = $new_checks;
			}

			foreach ($data['Checks'] as $i => $check) {
				$add_button->click();
				$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
				$checks_form = $dialog->asForm();

				if (!CTestArrayHelper::get($check, 'default')) {
					$checks_form->fill($check);
				}

				// Submit Discovery check dialog.
				$checks_form->submit();

				// After checking error in overlay no need to test further form.
				if (CTestArrayHelper::get($data, 'dialog_error')) {
					$this->assertMessage(TEST_BAD, null, $data['dialog_error']);
					$this->assertEquals($old_hash, $this->getHash());

					return;
				}

				$dialog->ensureNotPresent();

				// Ensure that Discovery check is added to table.
				if (CTestArrayHelper::get($check, 'default')) {
					$type_text = 'FTP';
				}
				elseif (CTestArrayHelper::get($check, 'Port range') !== null) {
					switch ($check['Check type']) {
						case 'Zabbix agent':
							$type_text = $check['Check type'].' ('.$check['Port range'].') "'.$check['Key'].'"';
							break;

						case 'SNMPv1 agent':
						case 'SNMPv2 agent':
						case 'SNMPv3 agent':
							$type_text = $check['Check type'].' ('.$check['Port range'].') "'.$check['SNMP OID'].'"';
							break;

						default:
							$type_text = $check['Check type'].' ('.$check['Port range'].')';
							break;
					}
				}
				else {
					switch ($check['Check type']) {
						case 'Zabbix agent':
							$type_text = $check['Check type'].' "'.$check['Key'].'"';
							break;

						case 'SNMPv1 agent':
						case 'SNMPv2 agent':
						case 'SNMPv3 agent':
							$type_text = $check['Check type'].' "'.$check['SNMP OID'].'"';
							break;

						default:
							$type_text = $check['Check type'];
							break;
					}
				}

				$this->assertEquals($type_text,
						$form->getFieldContainer('Checks')->asTable()->getRow($i)->getColumn('Type')->getText()
				);
			}
		}

		$form->fill($data['fields']);

		// Fill radio-fields.
		if (array_key_exists('radios', $data)) {
			foreach ($data['radios'] as $field => $value) {
				$form->getFieldContainer($field)->query("xpath:.//label[text()=".
						CXPathHelper::escapeQuotes($value)."]/../input")->one()->click();
			}
		}

		// Submit Discovery rule form.
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot'.($update ? ' update': ' create').' discovery rule',
					$data['error_details']
			);
			$this->assertEquals($old_hash, $this->getHash());
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Discovery rule'. ($update ? ' updated': ' created'));

			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields'] = array_map('trim', $data['fields']);
			}

			// Write new name for the next cases.
			if ($update) {
				self::$update_rule = $data['fields']['Name'];
			}

			// Check saved fields in form.
			$this->query('class:list-table')->asTable()->waitUntilVisible()->one()->findRow('Name',
					$data['fields']['Name'])->query('tag:a')->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);

			// Check radio-fields.
			if (array_key_exists('radios', $data)) {
				foreach ($data['radios'] as $field => $value) {
					$this->assertTrue($form->getFieldContainer($field)->query("xpath:.//label[text()=".
							CXPathHelper::escapeQuotes($value)."]/../input[@checked]")->exists()
					);
				}
			}

			// Check that Discovery rule saved in  DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.
					zbx_dbstr($data['fields']['Name'])
			));

			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($old_name)));
			}

			// Trim spaces inside the string, so that it is possible to click link on the list table.
			if ($update && CTestArrayHelper::get($data, 'trim')) {
				self::$update_rule = str_replace('     ', ' ', self::$update_rule);
			}
		}
	}

	public function getChecksData() {
		return [
			// #0 Change checks fields without changing type.
			[
				[
					'Checks' => [
						[
							// SNMPv1 agent.
							'Port range' => 200,
							'SNMP community' => 'new_test_community',
							'SNMP OID' => 'new test SNMP OID'
						],
						[
							// SNMPv3 agent.
							'Port range' => 9999,
							'SNMP OID' => 'new test SNMP OID _2',
							'Context name' => 'new test context name',
							'Security name' => 'new test security name',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA224',
							'Authentication passphrase' => 'new test auth passphrase',
							'Privacy protocol' => 'AES256',
							'Privacy passphrase' => 'new test privacy passphrase'
						],
						[
							// Telnet.
							'Port range' => 205
						]
					],
					'expected_checks' => [
						'SNMPv1 agent (200) "new test SNMP OID"',
						'SNMPv3 agent (9999) "new test SNMP OID _2"',
						'Telnet (205)',
						'Add'
					]
				]
			],
			// #1 Change checks fields with changing type and delete some.
			[
				[
					'Checks' => [
						[
							'Check type' => 'ICMP ping'
						],
						[
							'Check type' => 'POP',
							'Port range' => 2020
						],
						[
							'remove' => true
						]
					],
					'expected_checks' => [
						'ICMP ping',
						'POP (2020)',
						'Add'
					]
				]
			],
			// #2 Add one additional check.
			[
				[
					'Checks' => [
						[
							'add' => true,
							'Check type' => 'SNMPv2 agent',
							'Port range' => 903,
							'SNMP community' => 'v2_test_community',
							'SNMP OID' => ' v2 new test SNMP OID'
						]
					],
					'expected_checks' => [
						'ICMP ping',
						'POP (2020)',
						'SNMPv2 agent (903) "v2 new test SNMP OID"',
						'Add'
					]
				]
			],
			// #3 Delete all checks.
			[
				[
					'expected' => TEST_BAD,
					'Checks' => 'remove all',
					'error_details' => 'Field "dchecks" is mandatory.'
				]
			]
		];
	}

	/**
	 * Test scenario for editing just checks without changing any other field in the Discovery rule.
	 *
	 * @dataProvider getChecksData
	 */
	public function testFormNetworkDiscovery_ChangeChecks($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link:Discovery rule for changing checks')->waitUntilClickable()->one()->click();
		$form = $this->query('id:discoveryForm')->asForm()->one();
		$table = $form->query('id:dcheckList')->asTable()->one();

		if ($data['Checks'] === 'remove all') {
			$this->removeAllChecks($form);
		}
		else {
			foreach ($data['Checks'] as $i => $check) {
				if (CTestArrayHelper::get($check, 'add')) {
					$form->getField('Checks')->query('button:Add')->waitUntilClickable()->one()->click();
					$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
					$checks_form = $dialog->asForm();
					unset ($check['add']);
					$checks_form->fill($check);
					$checks_form->submit();
					$dialog->ensureNotPresent();
				}
				elseif (CTestArrayHelper::get($check, 'remove')) {
					$row = $table->getRow($i);
					$type_text = $row->getColumn('Type')->getText();
					$row->query('button:Remove')->one()->waitUntilClickable()->click();
					$this->assertFalse($table->query('xpath:.//div[text()='.CXPathHelper::escapeQuotes($type_text).']')->exists());
				}
				else {
					$this->query('id:dcheckList')->asTable()->one()->getRow($i)
							->query('button:Edit')->one()->waitUntilClickable()->click();
					$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
					$checks_form = $dialog->asForm();
					$checks_form->fill($check);
					$checks_form->submit();
					$dialog->ensureNotPresent();
				}
			}

			$form->submit();

			if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
				$this->assertMessage(TEST_BAD, 'Cannot update discovery rule', $data['error_details']);
				$this->assertEquals($old_hash, $this->getHash());
			}
			else {
				$this->assertMessage(TEST_GOOD, 'Discovery rule updated');

				// Compare Checks table with the expected result.
				$this->query('class:list-table')->asTable()->waitUntilVisible()->one()->findRow('Name',
						'Discovery rule for changing checks')->query('tag:a')->waitUntilClickable()->one()->click();
				$this->assertTableDataColumn($data['expected_checks'], 'Type', 'id:dcheckList');
			}
		}
	}

	public function testFormNetworkDiscovery_Clone() {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link:Discovery rule for changing checks')->waitUntilClickable()->one()->click();
		$form = $this->query('id:discoveryForm')->asForm()->one();

		$original_field_values = $form->getFields()->asValues();
		$original_checks = $this->getTableResult('Type', 'id:dcheckList');

		foreach ($form->query('xpath:.//input[@checked]/../label')->all() as $checked_radio) {
			$original_radios[] = $checked_radio->getText();
		}

		$form->query('button:Clone')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$new_name = 'Cloned Discovery Rule';
		$form->fill(['Name' => $new_name]);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Discovery rule created');

		$this->query('class:list-table')->asTable()->waitUntilVisible()->one()->findRow('Name',
				$new_name)->query('tag:a')->waitUntilClickable()->one()->click();
		$form->invalidate();

		// Compare form's simple fields.
		$original_field_values['Name'] = $new_name;
		$this->assertEquals($original_field_values, $form->getFields()->asValues());

		// Compare Discovery rule's Checks.
		$this->assertEquals($original_checks, $this->getTableResult('Type', 'id:dcheckList'));

		// Compare form's radios.
		foreach ($form->query('xpath:.//input[@checked]/../label')->all() as $checked_radio) {
			$new_radios[] = $checked_radio->getText();
		}
		$this->assertEquals($original_radios, $new_radios);

		// Check Discovery rules in DB.
		foreach(['Discovery rule for clone', $new_name] as $name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($name)));
		}
	}

	public function testFormNetworkDiscovery_Delete() {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link', self::DELETE_RULE)->waitUntilClickable()->one()->click();
		$form = $this->query('id:discoveryForm')->asForm()->one();
		$form->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
		$this->page->acceptAlert();
		$this->assertMessage(TEST_GOOD, 'Discovery rule deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr(self::DELETE_RULE)));
	}

	public static function getCancelData() {
		return [
			[
				[
					'action' => 'Add'
				]
			],
			[
				[
					'action' => 'Update'
				]
			],
			[
				[
					'action' => 'Clone'
				]
			],
			[
				[
					'action' => 'Delete'
				]
			]
		];
	}

	/**
	 * Test for checking Dicrovery rule form's actions cancelling.
	 *
	 * @dataProvider getCancelData
	 */
	public function testFormNetworkDiscovery_Cancel($data) {
		$old_hash = $this->getHash();
		$new_name = microtime(true).' Cancel '.self::CANCEL_RULE;

		$this->page->login()->open('zabbix.php?action=discovery.list');
		$selector = ($data['action'] === 'Add') ? 'button:Create discovery rule' : ('link:'.self::CANCEL_RULE);
		$this->query($selector)->waitUntilClickable()->one()->click();

		$form = $this->query('id:discoveryForm')->asForm()->one();

		if ($data['action'] === 'Delete') {
			$form->query('button:Delete')->waitUntilClickable()->one()->click();
			$this->page->dismissAlert();
		}
		else {
			// Fill form's fields.
			$form->fill([
				'Name' => $new_name,
				'Discovery by proxy' => 'Passive proxy 1',
				'Update interval' => '15s',
				'Enabled' => false
			]);

			$form->getFieldContainer('Checks')->query('button', $data['action'] === 'Add' ? 'Add' : 'Edit')
					->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$checks_form = $dialog->asForm();
			$checks_form->fill([
				'Check type' => 'SNMPv2 agent',
				'Port range' => 99,
				'SNMP community' => 'new cancel community',
				'SNMP OID' => 'new cancel OID'
			]);
			$checks_form->submit();
			$dialog->ensureNotPresent();

			$radios = [
				'Device uniqueness criteria' => 'SNMPv2 agent (99) "new cancel OID"',
				'Host name' => 'IP address',
				'Visible name' => 'DNS name'
			];

			foreach ($radios as $label => $value) {
				$form->getFieldContainer($label)->query("xpath:.//label[text()=".
					CXPathHelper::escapeQuotes($value)."]/../input")->one()->click();
			}

			if ($data['action'] === 'Clone') {
				$form->query('button', $data['action'])->one()->click();
			}
		}

		$form->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->assertHeader('Discovery rules');
		$this->assertEquals($old_hash, $this->getHash());
	}

	/**
	 * @param CFormElement $form    discovery rule's edit form
	 */
	private function removeAllChecks($form) {
		$checks_container = $form->getFieldContainer('Checks');
		$checks_count = $checks_container->query('xpath:.//td[contains(@id, "dcheckCell_")]')->count();

		for ($i = 0; $i < $checks_count; $i++) {
			// After each deletion checks buttons reset their position, so upper items locator is always [1].
			$remove_button = $checks_container->query('xpath:(.//button[text()="Remove"])[1]')->one();
			$remove_button->waitUntilClickable()->click();
			$remove_button->waitUntilNotPresent();
		}
	}
}
