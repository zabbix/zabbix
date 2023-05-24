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
 */
class testFormNetworkDiscovery extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
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

	public function getCreateData() {
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
							'Port range' => 'test'  ,
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
						'Incorrect value for field "name": cannot be empty.' ,
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
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormNetworkDiscovery_Create($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * from drules');
		}

		$this->page->login()->open('zabbix.php?action=discovery.edit');
		$form = $this->query('id:discoveryForm')->asForm()->one();

		if (CTestArrayHelper::get($data, 'Checks')) {
			$add_button = $form->getField('Checks')->query('button:Add')->waitUntilClickable()->one();

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
					$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * from drules'));

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
			$this->assertMessage(TEST_BAD, 'Cannot create discovery rule', $data['error_details']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * from drules'));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Discovery rule created');

			// Check saved fields in form.
			$this->query('class:list-table')->asTable()->waitUntilVisible()->one()->findRow('Name', $data['fields']['Name'])
					->query('tag:a')->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);

			// Check radio-fields.
			if (array_key_exists('radios', $data)) {
				foreach ($data['radios'] as $field => $value) {
					$this->assertTrue($form->getFieldContainer($field)->query("xpath:.//label[text()=".
						CXPathHelper::escapeQuotes($value)."]/../input[@checked]")->exists());
				}
			}

			// Check that Discovery rule saved in  DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.
					zbx_dbstr($data['fields']['Name'])
			));


			// Trim trailing and leading spaces in expected values before comparison.
//				if (CTestArrayHelper::get($data, 'trim', false)) {
//					$data['fields']['Group name'] = trim($data['fields']['Group name']);
//				}
//				$form = $this->openForm($data['fields']['Group name']);
//				$form->checkValue($data['fields']['Group name']);
//				// Change group name after succefull update scenario.
//				if ($action === 'update') {
//					static::$update_group = $data['fields']['Group name'];
//				}
		}
	}
}
