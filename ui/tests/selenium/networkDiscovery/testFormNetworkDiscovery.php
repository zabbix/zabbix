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
					'fields' => [
						'Name' => 'Mimimal fields create'
					],
					'Checks' => []
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Name + 1 check'
					],
					'Checks' => [
						'Check type' => 'HTTP',
						'Port range' => '65535'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty port'
					],
					'Checks' => [
						'Port range' => ''
					],
					'dialog_error' => 'Incorrect value for field "ports": cannot be empty.',
					'error_details' => 'Field "dchecks" is mandatory.'
				]
			],
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
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data, 'Checks')) {
			$form->getField('Checks')->query('button:Add')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
			$checks_form = $dialog->asForm();

			if ($data['Checks'] !== [])  {
				$checks_form->fill($data['Checks']);
			}

			// Submit checks dialog.
			$checks_form->submit();

			if (CTestArrayHelper::get($data, 'dialog_error')) {
				$this->assertMessage(TEST_BAD, null, $data['dialog_error']);
				$dialog->query('button:Cancel')->waitUntilClickable()->one()->click();
			}

			$dialog->ensureNotPresent();

			// Submit Discovery rule form.
			$form->submit();

			if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
				$this->assertMessage(TEST_BAD, 'Cannot create discovery rule', $data['error_details']);
				$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * from drules'));
			}
			else {
				$this->assertMessage(TEST_GOOD, 'Discovery rule created');

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
}
