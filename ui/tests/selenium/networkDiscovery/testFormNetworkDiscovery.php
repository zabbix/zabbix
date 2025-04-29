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
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup drules
 *
 * @dataSource NetworkDiscovery
 */
class testFormNetworkDiscovery extends CWebTest {

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

	const CANCEL_RULE = 'Discovery rule for cancelling scenario';
	const CLONE_RULE = 'Discovery rule for clone';
	const CHECKS_RULE = 'Discovery rule for changing checks';
	const DELETE_RULES = [
			'success' => 'Discovery rule for successful deleting',
			'action_used' => 'Discovery rule for deleting, used in Action',
			'action_check_used' => 'Discovery rule for deleting, check used in Action'
	];

	/**
	 * Name of discovery rule for update scenario.
	 */
	protected static $update_rule = 'Discovery rule for update';

	/**
	 * Get discovery rules and checks tables hash values.
	 */
	protected static function getHash() {
		return CDBHelper::getHash( 'SELECT * FROM drules').
				CDBHelper::getHash('SELECT * FROM dchecks');
	}

	public function testFormNetworkDiscovery_Layout() {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('New discovery rule', $dialog->getTitle());
		$form = $dialog->asForm();

		// Check that all labels present and visible.
		$this->assertEquals(['Name', 'Discovery by', 'IP range', 'Update interval', 'Maximum concurrent checks per type',
			'Checks', 'Device uniqueness criteria', 'Host name', 'Visible name', 'Enabled'],
			$form->getLabels(CElementFilter::VISIBLE)->asText()
		);

		// Check required fields.
		$this->assertEquals(['Name', 'IP range', 'Update interval', 'Checks'], $form->getRequiredLabels());

		// Check the default values.
		$form->checkValue([
			'Name' => '',
			'Discovery by' => 'Server',
			'IP range' => '192.168.0.1-254',
			'Update interval' => '1h',
			'id:concurrency_max_type' => 'Unlimited',
			'Enabled' => true,
			'id:concurrency_max' => 0
		]);

		// Check Maximum concurrent checks per type segmented radio field.
		$labels = ['One', 'Unlimited', 'Custom'];
		$max_concurrent_field = $form->getField('id:concurrency_max_type')->asSegmentedRadio();
		$this->assertEquals($labels, $max_concurrent_field->getLabels()->asText());

		$custom_checks_field = $form->query('id:concurrency_max');
		foreach ($labels as $label) {
			$max_concurrent_field->select($label);
			$status = ($label === 'Custom') ? true : false;
			$this->assertTrue($custom_checks_field->one()->isVisible($status));
			$this->assertTrue($custom_checks_field->one()->isEnabled());
		}

		// Radio fields are checked separately, because they are unique elements and don't match with any Framework element.
		$radios = [
			'Device uniqueness criteria' => ['IP address' => true],
			'Host name' => ['DNS name' => true, 'IP address' => false],
			'Visible name' => ['Host name' => true, 'DNS name' => false, 'IP address' => false]
		];

		foreach ($radios as $field => $radio_list) {
			$get_field = $form->getField($field);
			$this->assertEquals(array_keys($radio_list),
					$get_field->query('xpath:.//input[@type="radio"]/../label')->all()->asText()
			);
			$this->assertEquals(array_search(true, $radio_list),
					$get_field->query("xpath:.//input[@checked]/../label")->one()->getText()
			);
		}

		foreach (['Name' => 255, 'IP range' => 2048, 'Update interval' => 255, 'id:concurrency_max' => 3] as $name => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($name)->getAttribute('maxlength'));
		}

		// New check adding dialog.
		$form->getField('Checks')->query('button:Add')->waitUntilClickable()->one()->click();
		$checks_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$this->assertEquals('Discovery check', $checks_dialog->getTitle());
		$checks_form = $checks_dialog->asForm();
		$this->assertEquals(['Check type', 'Port range'], $checks_form->getLabels(CElementFilter::VISIBLE)->asText());
		$this->assertEquals(['Add', 'Cancel'],
				$checks_dialog->getFooter()->query('button')->all()->filter(CElementFilter::CLICKABLE)->asText()
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
				$this->assertEquals(['Check type', 'Allow redirect'],
						array_values($checks_form->getLabels(CElementFilter::VISIBLE)->asText())
				);
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

		$checks_dialog->query('xpath:.//button[@title="Close"]')->one()->waitUntilClickable()->click();
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
					'dialog_error' => 'Incorrect value for field "ports": cannot be empty.'
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Local network'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Discovery rule "Local network" already exists.'
				]
			],
			// #2.
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
			// #3.
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
			// #4.
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
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative port for SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv2 agent',
							'Port range' => -1,
							'SNMP community' => 'test',
							'SNMP OID' => 'test'
						]
					],
					'dialog_error' => 'Incorrect port range.'
				]
			],
			// #6.
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
			// #7.
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
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Same default check validation'
					],
					'Checks' => [
						[
							'default' => true
						],
						[
							'default' => true
						]
					],
					'dialog_error' => 'Check already exists.'
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Same SNMP check validation'
					],
					'Checks' => [
						[
							'Check type' => 'HTTPS',
							'Port range' => 0
						],
						[
							'Check type' => 'SNMPv3 agent',
							'Port range' => 200,
							'SNMP OID' => 1,
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'SHA256',
							'Authentication passphrase' => 1
						],
						[
							'Check type' => 'SNMPv3 agent',
							'Port range' => 200,
							'SNMP OID' => 1,
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'SHA256',
							'Authentication passphrase' => 1
						]
					],
					'dialog_error' => 'Check already exists.'
				]
			],
			// #10.
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
			// #11.
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
			// #12.
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
			// #13.
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
			// #14.
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
			// #15.
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
			// #16.
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
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty IP range',
						'IP range' => ''
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": cannot be empty.'
				]
			],
			// #18.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Numbers in IP range',
						'IP range' => 12345
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "12345".'
				]
			],
			// #19.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Text in IP range',
						'IP range' => 'Text 😀'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "Text 😀".'
				]
			],
			// #20.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV4 in IP range',
						'IP range' => '192.168.4.300-305'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "192.168.4.300-305".'
				]
			],
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV4 mask in IP range',
						'IP range' => '192.168.4.0/5'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": IP range "192.168.4.0/5" exceeds "65536" address limit.'
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV4 mask in IP range _2',
						'IP range' => '192.168.4.0/111'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "/111".'
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV4 mask in IP range _3',
						'IP range' => '192.168.4.0/129'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "/129".'
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV6 mask in IP range',
						'IP range' => '2001:DB8:0000:0000:244:17FF:FEB6:D37D/64'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": IP range "2001:DB8:0000:0000:244:17FF:FEB6:D37D/64" exceeds "65536" address limit.'
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong IPV6 mask in IP range _2',
						'IP range' => '2001:db8::/130'
					],
					'Checks' => [['default' => true]],
					'error_details' => 'Incorrect value for field "iprange": incorrect address starting from "/130".'
				]
			],
			// #26.
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
			// #27.
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
			// #28.
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
			// #29.
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
			// #30.
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
			// #31.
			[
				[
					'fields' => [
						'Name' => 'Minimal fields create'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #32.
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
			// #33.
			[
				[
					'fields' => [
						'Name' => 'List in IP range',
						'IP range' => '192.168.1.1-255, 192.168.2.1-100, 192.168.2.200, 192.168.4.0/24, 192.167.1-10.1-253'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #34.
			[
				[
					'fields' => [
						'Name' => 'IPv6 in IP range',
						'IP range' => '2001:db8:3333:4444:CCCC:DDDD:EEEE:FFFF'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #35.
			[
				[
					'fields' => [
						'Name' => 'Empty IPv6 in IP range',
						'IP range' => '::'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #36.
			[
				[
					'fields' => [
						'Name' => 'All zeros IPv6 in IP range',
						'IP range' => '0:0:0:0:0:0:0:0'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #37.
			[
				[
					'fields' => [
						'Name' => 'IPv6 mask in IP range',
						'IP range' => '2001:DB8:0000:0000:244:17FF:FEB6:D37D/113'
					],
					'Checks' => [['default' => true]]
				]
			],
			// #38.
			[
				[
					'fields' => [
						'Name' => 'IPv6 list in IP range',
						'IP range' => "2001:db8:3333:4444:5555:6666:7777:8888,".
							"\n::1234:5678,".
							"\n2001:db8::,".
							"\n2001:db8::1234:5678,".
							"\n2001:0db8:0001:0000:0000:0ab9:C0A8:0102,".
							"\n2001:DB8:0000:0000:244:17FF:FEB6:D37D/115"
					],
					'Checks' => [['default' => true]]
				]
			],
			// #39.
			[
				[
					'fields' => [
						'Name' => 'All fields',
						'id:discovery_by' => 'Proxy',
						'xpath:.//div[@id="proxyid"]/..' => 'Test Proxy',
						'IP range' => '192.168.251.253-254',
						'id:concurrency_max_type' => 'One',
						'Update interval' => 604800,
						'Enabled' => false
					],
					'radios' => [
						'Device uniqueness criteria' => 'Zabbix agent (100-500) "test"',
						'Host name' => 'IP address',
						'Visible name' => 'DNS name'
					],
					'Checks' => [
						[
							'Check type' => 'HTTPS',
							'Port range' => 0
						],
						[
							'Check type' => 'ICMP ping',
							'Allow redirect' => true
						],
						[
							'Check type' => 'IMAP',
							'Port range' => 1
						],
						[
							'Check type' => 'Zabbix agent',
							'Port range' => '100-500',
							'Key' => 'test'
						]
					]
				]
			],
			// #40.
			[
				[
					'fields' => [
						'Name' => 'All checks SNMPv3'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => 1,
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'MD5'
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => 1,
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'SHA1'
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => 1,
							'Security level' => 'authNoPriv',
							'Authentication protocol' => 'SHA512'
						]
					]
				]
			],
			// #41.
			[
				[
					'fields' => [
						'Name' => 'SNMP agents checks',
						'Update interval' => '7d',
						'IP range' => '192.168.1.33',
						'id:concurrency_max_type' => 'Custom',
						'id:concurrency_max' => 999,
						'Enabled' => true
					],
					'radios' => [
						'Device uniqueness criteria' => 'Zabbix agent "key[param1, param2]"',
						'Host name' => 'SNMPv1 agent (9999,10-200) "😀"',
						'Visible name' => 'SNMPv3 agent "😀"'
					],
					'Checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => '9999,10-200',
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
			// #42.
			[
				[
					'trim' => true,
					'fields' => [
						'Name' => '         Spaces in name     ',
						'IP range' => '        192.168.1-10.1-255              ',
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
			],
			// #43.
			[
				[
					'check_radio_labels' => true,
					'fields' => [
						'Name' => 'All possible checks',
						'IP range' => '192.168.4.0/24'
					],
					'Checks' => [
						[
							'Check type' => 'FTP',
							'Port range' => '22-45,55,155-888'
						],
						[
							'Check type' => 'HTTP',
							'Port range' => '666,22'
						],
						[
							'Check type' => 'HTTPS',
							'Port range' => 333
						],
						[
							'Check type' => 'ICMP ping',
							'Allow redirect' => true
						],
						[
							'Check type' => 'IMAP',
							'Port range' => '65535-65535,65535-65535,65535'
						],
						['Check type' => 'LDAP'],
						['Check type' => 'NNTP'],
						['Check type' => 'POP'],
						['Check type' => 'SMTP'],
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => '0-65535',
							'SNMP community' => 'V1 community',
							'SNMP OID' => 'SNMP OID'
						],
						[
							'Check type' => 'SNMPv2 agent',
							'SNMP community' => 'V2 Community',
							'SNMP OID' => 123456789
						],
						[
							'Check type' => 'SNMPv3 agent',
							'SNMP OID' => 123,
							'Context name' => 'Context',
							'Security name' => 'Security',
							'Security level' => 'authPriv',
							'Authentication passphrase' => 'PassphraSE',
							'Privacy passphrase' => 'Privacy'
						],
						['Check type' => 'SSH'],
						['Check type' => 'TCP'],
						['Check type' => 'Telnet'],
						[
							'Check type' => 'Zabbix agent',
							'Key' => 'key[parameter_1, parameter_2]'
						]
					]
				]
			]
		];
	}

	public function getCreateData() {
		return [
			// #44.
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
			// #44.
			[
				[
					'fields' => [
						// Minus 6 symbols for "update" suffix.
						'Name' => substr(STRING_255, 0, 249)
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

	protected function checkDiscoveryRuleForm($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$this->page->login()->open('zabbix.php?action=discovery.list');

		if ($update) {
			$this->query('link', self::$update_rule)->waitUntilClickable()->one()->click();
			$old_name = self::$update_rule;
		}
		else {
			$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		if ($update && CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$data['fields']['Name'] = CTestArrayHelper::get($data, 'trim', false)
				? $data['fields']['Name'].'update       '
				: $data['fields']['Name'].'update';
		}

		// Clear all checks from discovery rule to change them to new ones from data provider.
		if ($update) {
			foreach ($form->getFieldContainer('Checks')->asTable()->getRows() as $row) {
				$row->query('button:Remove')->one()->waitUntilClickable()->click();
				$row->waitUntilNotPresent();
			}
		}

		if (CTestArrayHelper::get($data, 'Checks')) {
			$radio_labels = ['Device uniqueness criteria', 'Host name', 'Visible name'];
			$add_button = $form->getField('Checks')->query('button:Add')->waitUntilClickable()->one();
			$expected_checks = [];

			foreach ($data['Checks'] as $i => $check) {
				$add_button->click();
				$check_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$checks_form = $check_dialog->asForm();

				if (!CTestArrayHelper::get($check, 'default')) {
					$checks_form->fill($check);
				}

				// Trim Check fields for expected comparison.
				if (CTestArrayHelper::get($data, 'trim', false)) {
					$check = array_map('trim', $check);
				}

				if (array_key_exists('Port range', $check)) {
					$check['Port range'] = ($check['Port range'] === 0) ? null : $check['Port range'];
				}

				// Submit Discovery check dialog.
				$checks_form->submit();

				// If there is more than 1 check, dialog error will appear only in the last check.
				if (CTestArrayHelper::get($data, 'dialog_error') && ($i + 1 === count($data['Checks']))) {
					$this->assertMessage(TEST_BAD, null, $data['dialog_error']);
					$this->assertEquals($old_hash, $this->getHash());

					// After checking error in overlay no need to test further form.
					$check_dialog->close();
					$dialog->close();
					return;
				}

				$check_dialog->waitUntilNotVisible();

				// Ensure that Discovery check is added to the table.
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

						case 'ICMP ping':
							$type_text = CTestArrayHelper::get($check, 'Allow redirect')
								? 'ICMP ping "allow redirect"'
								: 'ICMP ping';
							break;

						default:
							$type_text = $check['Check type'];
							break;
					}
				}

				if (CTestArrayHelper::get($check, 'default')) {
					$check['Check type'] = 'FTP';
				}

				// Ensure that corresponding checks and their parameters appear or don't appear in the "radio" fields.
				if (CTestArrayHelper::get($data, 'check_radio_labels', false)) {
					foreach ($radio_labels as $label) {
						$field = $form->getField($label);
						$this->assertEquals(in_array($check['Check type'],
								['Zabbix agent', 'SNMPv1 agent', 'SNMPv2 agent', 'SNMPv3 agent']),
								$field->query('xpath:.//input[@type="radio"]/../label[text()='.
								CXPathHelper::escapeQuotes($type_text).']')->exists()
						);
					}
				}

				$expected_checks[] = $type_text;
			}

			$this->assertEquals($expected_checks, $this->getTableColumnData('Type', 'id:dcheckList'));
		}

		$form->fill($data['fields']);

		// Fill radio-fields.
		if (array_key_exists('radios', $data)) {
			foreach ($data['radios'] as $label => $value) {
				$form->getFieldContainer($label)->query('class:list-check-radio')->one()->asSegmentedRadio()->fill($value);
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
				foreach ($data['radios'] as $label => $value) {
					$this->assertEquals($value, $form->getFieldContainer($label)->query('class:list-check-radio')->one()
							->asSegmentedRadio()->getValue()
					);
				}
			}

			// Compare checks table to ensure that Discovery checks are saved correctly.
			if (CTestArrayHelper::get($data, 'Checks')) {
				$this->assertEquals($expected_checks, $this->getTableColumnData('Type', 'id:dcheckList'));

				// Write default check to the array for comparison.
				if ($data['Checks'] === [['default' => true]]) {
					$data['Checks'] = [['Check type' => 'FTP', 'Port range' => 21]];
				}

				// Trim Check fields for expected comparison.
				if (CTestArrayHelper::get($data, 'trim', false)) {
					foreach ($data['Checks'] as &$check) {
						$check = array_map('trim', $check);
					}
					unset($check);
				}

				// Compare Discovery rule's Checks form.
				$this->compareChecksFormValues($data['Checks'], $form);
			}

			// Check that Discovery rule saved in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.
					zbx_dbstr($data['fields']['Name'])
			));

			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($old_name)));
			}

			if ($update && CTestArrayHelper::get($data, 'trim')) {
				self::$update_rule = str_replace('     ', ' ', self::$update_rule);
			}
		}

		$dialog->close();
	}

	public function getChecksData() {
		return [
			// #0 Change checks fields without changing type.
			[
				[
					'Checks' => [
						[
							// SNMPv1 agent.
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Port range' => 200,
							'SNMP community' => 'new_test_community',
							'SNMP OID' => 'new test SNMP OID'
						],
						[
							// SNMPv3 agent.
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
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
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'Port range' => 205
						]
					],
					'expected_checks' => [
						'SNMPv1 agent (200) "new test SNMP OID"',
						'SNMPv3 agent (9999) "new test SNMP OID _2"',
						'Telnet (205)'
					] ,
					'expected_radios' => [
						'Device uniqueness criteria' => [
							'IP address' => false,
							'SNMPv1 agent (200) "new test SNMP OID"' => false,
							'SNMPv3 agent (9999) "new test SNMP OID _2"' => true
						],
						'Host name' => [
							'DNS name' => false,
							'IP address' => false,
							'SNMPv1 agent (200) "new test SNMP OID"' => true,
							'SNMPv3 agent (9999) "new test SNMP OID _2"' => false
						],
						'Visible name' => [
							'Host name' => false,
							'DNS name' => false,
							'IP address' => true,
							'SNMPv1 agent (200) "new test SNMP OID"' => false,
							'SNMPv3 agent (9999) "new test SNMP OID _2"' => false
						]
					]
				]
			],
			// #1 Change SNMP to other type of checks.
			[
				[
					'Checks' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Check type' => 'ICMP ping',
							'Allow redirect' => true
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Check type' => 'POP',
							'Port range' => 2020
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'Check type' => 'Zabbix agent',
							'Key' => 'test_key'
						]
					],
					'expected_checks' => [
						'ICMP ping "allow redirect"',
						'POP (2020)',
						'Zabbix agent "test_key"'
					],
					'expected_radios' => [
						'Device uniqueness criteria' => [
							'IP address' => true,
							'Zabbix agent "test_key"' => false
						],
						'Host name' => [
							'DNS name' => true,
							'IP address' => false,
							'Zabbix agent "test_key"' => false
						],
						'Visible name' => [
							'Host name' => false,
							'DNS name' => false,
							'IP address' => true,
							'Zabbix agent "test_key"' => false
						]
					]
				]
			],
			// #2 Delete two checks, one left.
			[
				[
					'Checks' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 2
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 1
						]
					],
					'expected_checks' => [
						'ICMP ping "allow redirect"'

					],
					'expected_radios' => [
						'Device uniqueness criteria' => [
							'IP address' => true
						],
						'Host name' => [
							'DNS name' => true,
							'IP address' => false
						],
						'Visible name' => [
							'Host name' => false,
							'DNS name' => false,
							'IP address' => true
						]
					]
				]
			],
			// #3 Add one additional check.
			[
				[
					'Checks' => [
						[
							'action' =>  USER_ACTION_ADD,
							'Check type' => 'SNMPv2 agent',
							'Port range' => 903,
							'SNMP community' => 'v2_test_community',
							'SNMP OID' => ' v2 new test SNMP OID'
						]
					],
					'expected_checks' => [
						'ICMP ping "allow redirect"',
						'SNMPv2 agent (903) "v2 new test SNMP OID"'
					],
					'expected_radios' => [
						'Device uniqueness criteria' => [
							'IP address' => true,
							'SNMPv2 agent (903) "v2 new test SNMP OID"' => false
						],
						'Host name' => [
							'DNS name' => true,
							'IP address' => false,
							'SNMPv2 agent (903) "v2 new test SNMP OID"' => false
						],
						'Visible name' => [
							'Host name' => false,
							'DNS name' => false,
							'IP address' => true,
							'SNMPv2 agent (903) "v2 new test SNMP OID"' => false
						]
					]
				]
			],
			// #4 Delete all checks.
			[
				[
					'expected' => TEST_BAD,
					'Checks' => [
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					],
					'expected_radios' => [
						'Device uniqueness criteria' => [
							'IP address' => true
						],
						'Host name' => [
							'DNS name' => true,
							'IP address' => false
						],
						'Visible name' => [
							'Host name' => false,
							'DNS name' => false,
							'IP address' => true
						]
					],
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
		$this->query('link', self::CHECKS_RULE)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$this->changeDiscoveryChecks($data['Checks'], $form);

		// Compare changed radio fields before save.
		$this->compareRadioFields($data['expected_radios'], $form);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update discovery rule', $data['error_details']);

			$this->assertEquals($old_hash, $this->getHash());
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Discovery rule updated');

			// Compare Checks table with the expected result.
			$this->query('link', self::CHECKS_RULE)->waitUntilClickable()->one()->click();
			COverlayDialogElement::find()->one()->waitUntilReady();
			$this->assertTableDataColumn($data['expected_checks'], 'Type', 'id:dcheckList');

			// Compare changed radio fields after save.
			$this->compareRadioFields($data['expected_radios'], $form);
		}

		$dialog->close();
	}

	public static function getCloneData() {
		return [
			[
				[
					'simple' => true,
					'expected_fields' => [
						'Name' => 'New cloned name, no changes',
						'id:discovery_by' => 'Proxy',
						'xpath:.//div[@id="proxyid"]/..' => 'Proxy for Network discovery',
						'IP range' => '192.168.2.3-255',
						'Update interval' => '25h',
						'id:concurrency_max_type' => 'Unlimited'
					],
					'expected_checks_table' => [
						'LDAP (555)',
						'SNMPv1 agent (165) ".1.9.6.1.10.1.9.9.9"',
						'SNMPv3 agent (130) ".1.3.6.1.2.1.1.1.999"',
						'TCP (9988)'
					],
					'expected_checks' => [
						[
							'Check type' => 'LDAP',
							'Port range' => 555
						],
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => 165,
							'SNMP OID' => '.1.9.6.1.10.1.9.9.9',
							'SNMP community'=> 'original SNMP community'
						],
						[
							'Check type' => 'SNMPv3 agent',
							'Port range' => 130,
							'SNMP OID' => '.1.3.6.1.2.1.1.1.999',
							'Context name' => 'original_context_name',
							'Security name' => 'original_security_name',
							'Security level' => 'authPriv',
							'Authentication protocol' => 'SHA384',
							'Authentication passphrase' => 'original_authpassphrase',
							'Privacy protocol' => 'AES256C',
							'Privacy passphrase' => 'original_privpassphrase'
						],
						[
							'Check type' => 'TCP',
							'Port range' => 9988
						]
					],
					'radios' => [
						'Device uniqueness criteria' => 'SNMPv1 agent (165) ".1.9.6.1.10.1.9.9.9"',
						'Host name' => 'SNMPv3 agent (130) ".1.3.6.1.2.1.1.1.999"',
						'Visible name' => 'IP address'
					]
				]
			],
			[
				[
					'expected_fields' => [
						'Name' => 'New cloned name with changes',
						'id:discovery_by' => 'Proxy',
						'xpath:.//div[@id="proxyid"]/..' => 'Proxy for cloning Network discovery',
						'IP range' => '192.168.2.3-255',
						'Update interval' => '25h',
						'id:concurrency_max_type' => 'Unlimited',
						'Enabled' => true
					],
					'checks' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Check type' => 'SNMPv2 agent',
							'Port range' => 113,
							'SNMP community' => 'v2_cloned_community',
							'SNMP OID' => 'v2 new cloned SNMP OID'
						],
						[
							'action' => USER_ACTION_ADD,
							'Check type' => 'Zabbix agent',
							'Key' => 'key[cloned_param1, cloned_param2]'
						]
					],
					'expected_checks_table' => [
						'SNMPv1 agent (165) ".1.9.6.1.10.1.9.9.9"',
						'SNMPv2 agent (113) "v2 new cloned SNMP OID"',
						'TCP (9988)',
						'Zabbix agent "key[cloned_param1, cloned_param2]"'
					],
					'expected_checks' => [
						[
							'Check type' => 'SNMPv1 agent',
							'Port range' => 165,
							'SNMP OID' => '.1.9.6.1.10.1.9.9.9',
							'SNMP community'=> 'original SNMP community'
						],
						[
							'Check type' => 'SNMPv2 agent',
							'Port range' => 113,
							'SNMP community' => 'v2_cloned_community',
							'SNMP OID' => 'v2 new cloned SNMP OID'
						],
						[
							'Check type' => 'TCP',
							'Port range' => 9988
						]
					],
					'radios' => [
						'Device uniqueness criteria' => 'Zabbix agent "key[cloned_param1, cloned_param2]"',
						'Host name' => 'IP address',
						'Visible name' => 'SNMPv2 agent (113) "v2 new cloned SNMP OID"'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCloneData
	 */
	public function testFormNetworkDiscovery_Clone($data) {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link', self::CLONE_RULE)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$form = $dialog->asForm();

		if (CTestArrayHelper::get($data, 'simple')) {
			$form->fill(['Name' => $data['expected_fields']['Name']]);
		}
		else {
			// Fill simple fields.
			$form->fill($data['expected_fields']);

			// Fill Network discovery checks.
			$this->changeDiscoveryChecks($data['checks'], $form);

			// Fill radios.
			foreach ($data['radios'] as $label => $value) {
				$form->getFieldContainer($label)->query('class:list-check-radio')->one()->asSegmentedRadio()->fill($value);
			}
		}

		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Discovery rule created');

		$this->query('link', $data['expected_fields']['Name'])->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->invalidate();

		// Compare form's simple fields.
		$form->checkValue($data['expected_fields']);

		// Compare Discovery rule's Checks table.
		$this->assertEquals($data['expected_checks_table'], $this->getTableColumnData('Type', 'id:dcheckList'));

		// Compare Discovery rule's Checks form.
		$this->compareChecksFormValues($data['expected_checks'], $form);

		// Compare form's radios.
		foreach ($data['radios'] as $label => $value) {
			$this->assertEquals($value, $form->getFieldContainer($label)->query('class:list-check-radio')->one()
					->asSegmentedRadio()->getValue()
			);
		}

		$dialog->close();

		// Check Discovery rules in DB.
		foreach([self::CLONE_RULE, $data['expected_fields']['Name']] as $name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($name)));
		}
	}

	public static function getDeleteData() {
		return [
			[
				[
					'discovery' => self::DELETE_RULES['success']
				]
			],
			[
				[
					'discovery' => self::DELETE_RULES['action_used'],
					'error' => 'Cannot delete discovery rule '.CXPathHelper::escapeQuotes(self::DELETE_RULES['action_used']).
							': action "Action with discovery rule" uses this discovery rule.'
				]
			],
			[
				[
					'discovery' => self::DELETE_RULES['action_check_used'],
					'error' => 'Cannot delete discovery check "Telnet (15)" of discovery rule '.
							CXPathHelper::escapeQuotes(self::DELETE_RULES['action_check_used']).
							': action "Action with discovery check" uses this discovery check.'
				]
			]
		];
	}

	/**
	 * @dataProvider getDeleteData
	 */
	public function testFormNetworkDiscovery_Delete($data) {
		if (CTestArrayHelper::get($data, 'error')) {
			// Add actions table to hash to check that dependent Action is also not changed.
			$old_hash = $this->getHash().CDBHelper::getHash('SELECT * FROM actions');
		}

		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link', $data['discovery'])->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$dialog->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->assertEquals('Delete discovery rule?', $this->page->getAlertText());
		$this->page->acceptAlert();

		if (CTestArrayHelper::get($data, 'error')) {
			$this->assertMessage(TEST_BAD, 'Cannot delete discovery rule', $data['error']);
			$this->assertEquals($old_hash, $this->getHash().CDBHelper::getHash('SELECT * FROM actions'));
			$dialog->close();
		}
		else {
			$dialog->ensureNotPresent();
			$this->assertMessage(TEST_GOOD, 'Discovery rule deleted');
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($data['discovery'])));
		}
	}

	/**
	 * Function for testing Discovery rule's checks validation when similar, but not the same
	 * checks are removed and added again, but in opposite order. Issue was first discovered in ZBX-22640.
	 */
	public function testFormNetworkDiscovery_DuplicateChecksValidation() {
		$discovery_name = 'Double checks validation';

		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('button:Create discovery rule')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill(['Name' => $discovery_name]);

		// Add SNMPv3 checks.
		$this->changeDiscoveryChecks(
			[
				[
					'action' => USER_ACTION_ADD,
					'Check type' => 'SNMPv3 agent',
					'SNMP OID' => 1
				],
				[
					'action' => USER_ACTION_ADD,
					'Check type' => 'SNMPv3 agent',
					'SNMP OID' => 1,
					'Context name' => 1
				]
			], $form
		);

		// Remove just added checks.
		$this->changeDiscoveryChecks(
			[
				[
					'action' => USER_ACTION_REMOVE,
					'index' => 1
				],
				[
					'action' => USER_ACTION_REMOVE,
					'index' => 0
				]
			], $form
		);

		// Add SNMP checks again in the opposite order.
		$this->changeDiscoveryChecks(
			[
				[
					'action' => USER_ACTION_ADD,
					'Check type' => 'SNMPv3 agent',
					'SNMP OID' => 1,
					'Context name' => 1
				],
				[
					'action' => USER_ACTION_ADD,
					'Check type' => 'SNMPv3 agent',
					'SNMP OID' => 1
				]
			], $form
		);

		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Discovery rule created');
		$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM drules WHERE name='.zbx_dbstr($discovery_name)));
		$this->query('link', $discovery_name)->waitUntilClickable()->one()->click();
		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->invalidate();
		$this->assertTableDataColumn(['SNMPv3 agent "1"', 'SNMPv3 agent "1"'], 'Type', 'id:dcheckList');
		$dialog->close();
	}

	public static function getNoChangesData() {
		return [
			[
				[
					'action' => 'Simple update'
				]
			],
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
	 * Test for checking Discovery rule form's actions cancelling.
	 *
	 * @dataProvider getNoChangesData
	 */
	public function testFormNetworkDiscovery_NoChanges($data) {
		// Add actions table to hash to check that dependent Action is also not changed.
		$old_hash = $this->getHash().CDBHelper::getHash('SELECT * FROM actions');
		$new_name = microtime(true).' Cancel '.self::CANCEL_RULE;

		$this->page->login()->open('zabbix.php?action=discovery.list');
		$selector = ($data['action'] === 'Add') ? 'button:Create discovery rule' : ('link:'.self::CANCEL_RULE);
		$this->query($selector)->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		if ($data['action'] === 'Delete') {
			$dialog->query('button:Delete')->waitUntilClickable()->one()->click();
			$this->page->dismissAlert();
			$dialog->close();
		}
		else {
			if ($data['action'] !== 'Simple update') {
				// Fill form's fields.
				$form->fill([
					'Name' => $new_name,
					'id:discovery_by' => 'Proxy',
					'xpath:.//div[@id="proxyid"]/..' => 'Test Proxy',
					'Update interval' => '15s',
					'Enabled' => false
				]);

				$form->getFieldContainer('Checks')->query('button', $data['action'] === 'Add' ? 'Add' : 'Edit')
					->waitUntilClickable()->one()->click();
				$checks_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$checks_form = $checks_dialog->asForm();
				$checks_form->fill([
					'Check type' => 'SNMPv2 agent',
					'Port range' => 99,
					'SNMP community' => 'new cancel community',
					'SNMP OID' => 'new cancel OID'
				]);
				$checks_form->submit();
				$checks_dialog->waitUntilNotVisible();

				$radios = [
					'Device uniqueness criteria' => 'SNMPv2 agent (99) "new cancel OID"',
					'Host name' => 'IP address',
					'Visible name' => 'DNS name'
				];

				foreach ($radios as $label => $value) {
					$form->getFieldContainer($label)->query('class:list-check-radio')->one()->asSegmentedRadio()->fill($value);
				}

				if ($data['action'] === 'Clone') {
					$dialog->query('button', $data['action'])->one()->click();
				}
			}

			$dialog->query('button', ($data['action'] === 'Simple update') ? 'Update' : 'Cancel')
				->waitUntilClickable()->one()->click();
		}

		$dialog->waitUntilNotVisible();
		$this->page->assertHeader('Discovery rules');
		$this->assertEquals($old_hash, $this->getHash().CDBHelper::getHash('SELECT * FROM actions'));
	}

	/**
	 * Function for filling Network discovery's checks.
	 *
	 * @param array $data           filled values
	 * @param CFormElement $form    discovery rule's form
	 */
	protected function changeDiscoveryChecks($data, $form) {
		$table = $form->getField('Checks')->asTable();

		foreach ($data as $check) {
			switch ($check['action']) {
				case USER_ACTION_ADD:
				case USER_ACTION_UPDATE:
					if ($check['action'] === USER_ACTION_UPDATE) {
						$table->getRow($check['index'])->query('button:Edit')->one()->waitUntilClickable()->click();
						unset($check['index'], $check['action']);
					}
					else {
						$table->query('button:Add')->waitUntilClickable()->one()->click();
						unset($check['action']);
					}

					$checks_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$checks_form = $checks_dialog->asForm();
					$checks_form->fill($check);
					$checks_form->submit();
					$checks_dialog->waitUntilNotVisible();
					break;

				case USER_ACTION_REMOVE:
					$row = $table->getRow($check['index']);
					$row->query('button:Remove')->one()->waitUntilClickable()->click();
					$row->waitUntilNotPresent();
					break;
			}
		}
	}

	/**
	 * Function that opens every Network discovery check and asserts form's values.
	 *
	 * @param array $data           checked values
	 * @param CFormElement $form    discovery rule's edit form
	 */
	protected function compareChecksFormValues($data, $form) {
		$table = $form->getField('Checks')->asTable();

		foreach ($data as $i => $check) {
			$table->getRow($i)->query('button:Edit')->one()->click();
			$check_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$check_dialog->asForm()->checkValue($check);
			$check_dialog->query('button:Cancel')->one()->waitUntilClickable()->click();
			$check_dialog->waitUntilNotVisible();
		}
	}

	/**
	 * Function for checking Network discovery's radio fields.
	 *
	 * @param array $data           checked values
	 * @param CFormElement $form    discovery rule's edit form
	 */
	protected function compareRadioFields($data, $form) {
		foreach ($data as $label => $values) {
			$field = $form->getFieldContainer($label)->query('class:list-check-radio')->one()->asSegmentedRadio();

			// Check all expected radio labels.
			$this->assertEquals(array_keys($values), $field->getLabels()->asText());

			// Check selected radio label.
			$this->assertEquals(array_keys(array_filter($values)), [$field->getValue()]);
		}
	}

	/**
	 * Checks for the presence of a tooltip icon and disabled button for the discovery check if it is used in Action.
	 */
	public function testFormNetworkDiscovery_LayoutForUsedInAction() {
		$this->page->login()->open('zabbix.php?action=discovery.list');
		$this->query('link:Discovery rule for deleting, check used in Action')->waitUntilClickable()->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		// Check if button is disabled
		$this->assertFalse($dialog->query('button:Remove')->waitUntilVisible()->one()->isEnabled());

		// Compare hint text
		$dialog->query('class:zi-i-warning')->one()->click();
		$this->assertEquals('This check cannot be removed, as it is used as a condition in 1 discovery action.',
				$this->query('class:hintbox-wrap')->WaitUntilVisible()->one()->getText()
		);

		$dialog->close();
	}
}
