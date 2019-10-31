<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralAutoRegistration extends CWebTest {

	/**
	 * Check the default state of page elements, when first time open Auto registration.
	 */
	public function testFormAdministrationGeneralAutoRegistration_checkDefaultState() {
		// Navigate to auto registration page from dashboard page.
		$this->page->login()->open('zabbix.php?action=dashboard.view');
		$this->query('link:Administration')->one()->click();
		$this->query('xpath://nav[@class="top-subnav-container"]//a[text()="General"]')->one()->click();
		$this->query('id:configDropDown')->asDropdown()->one()->select('Auto registration');

		// Check elements dafault state.
		$form = $this->query('id:autoreg-form')->asForm()->one();
		$this->assertEquals(['No encryption'], $form->getField('Encryption level')->getValue());
		$this->assertFalse($form->query('id:tls_psk_identity')->one()->isDisplayed());
		$this->assertFalse($form->query('id:tls_psk')->one()->isDisplayed());
	}

	/**
	 * Check PSK field values, when PSK encryption is set and unset.
	 */
	public function testFormAdministrationGeneralAutoRegistration_PskValues() {
		$data = [
			'Encryption level' => ['PSK'],
			'PSK identity' => 'PSK004',
			'PSK' => '07df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
		];

		$this->page->login()->open('zabbix.php?action=autoreg.edit');

		$form = $this->query('id:autoreg-form')->asForm()->one();
		$form->fill($data);
		// Check entered PSK values.
		$this->assertEquals($data['PSK identity'], $form->getField('PSK identity')->getValue());
		$this->assertEquals($data['PSK'], $form->getField('PSK')->getValue());

		// Uncheck PSK. PSK fields are hidden.
		$form->getField('Encryption level')->set('PSK', false);
		$this->assertFalse($form->query('id:tls_psk_identity')->one()->isDisplayed());
		$this->assertFalse($form->query('id:tls_psk')->one()->isDisplayed());
		// Set PSK again, and check that PSK values remain the same.
		$form->getField('Encryption level')->set('PSK', true);
		$this->assertEquals($data['PSK identity'], $form->getField('PSK identity')->getValue());
		$this->assertEquals($data['PSK'], $form->getField('PSK')->getValue());
	}

	/**
	 * The order of the test cases is important. Test cases depend on each other.
	 */
	public static function getAuditReportData() {
		return [
			// Add PSK encryption.
			[
				[
					'fields' => [
						'Encryption level' => ['PSK'],
						'PSK identity' => 'PSK005',
						'PSK' => '07df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					],
					'audit' => [
						'User' => 'Admin',
						'Resource' => 'Auto registration',
						'Action' => 'Updated',
						'ID' => 1,
						'Details' => "config.tls_accept: 1 => 2\nconfig.tls_psk_identity: ******** => ********\nconfig.tls_psk: ******** => ********"
					]
				]
			],
			// Add "No encryption" level, but without changing PSK data.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
					],
					'audit' => [
						'User' => 'Admin',
						'Resource' => 'Auto registration',
						'Action' => 'Updated',
						'ID' => 1,
						'Details' => 'config.tls_accept: 2 => 3'
					]
				]
			],
			// Remove PSK encryption.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption'],
					],
					'audit' => [
						'User' => 'Admin',
						'Resource' => 'Auto registration',
						'Action' => 'Updated',
						'ID' => 1,
						'Details' => "config.tls_accept: 3 => 1\nconfig.tls_psk_identity: ******** => ********\nconfig.tls_psk: ******** => ********"
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getAuditReportData
	 * @backup-once config
	 *
	 * Check record on Audit report page, after updating auto registration.
	 */
	public function testFormAdministrationGeneralAutoRegistration_Audit($data) {
		// Add encryption.
		$this->page->login()->open('zabbix.php?action=autoreg.edit');
		$form = $this->query('id:autoreg-form')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Configuration updated', $message->getTitle());

		// Check Audit record about auto registration update.
		$this->page->open('auditlogs.php');
		$rows = $this->query('class:list-table')->asTable()->one()->getRows();
		// Get first row data.
		$row = $rows->get(0);
		foreach ($data['audit'] as $column => $value) {
			$text = $row->getColumnData($column, $value);
			$this->assertEquals($value, $text);
		}
	}

	public static function getAutoRegistrationValidationData() {
		return [
			// Auto registration without encryption level.
			[
				[
					'uncheck_all' => true,
					'fields' => [],
					'error' => 'Incorrect value "0" for "tls_accept" field.'
				]
			],
			// Auto registration with empty PSK values.
			[
				[
					'fields' => [
						'Encryption level' => 'PSK'
					],
					'error' => 'Invalid parameter "/tls_psk_identity": cannot be empty.'
				]
			],
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK']
					],
					'error' => 'Invalid parameter "/tls_psk_identity": cannot be empty.'
				]
			],
			[
				[
					'fields' => [
						'Encryption level' => 'PSK',
						'PSK' => '21df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					],
					'error' => 'Invalid parameter "/tls_psk_identity": cannot be empty.'
				]
			],
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK' => '21df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					],
					'error' => 'Invalid parameter "/tls_psk_identity": cannot be empty.'
				]
			],
//			TODO: wait fix ZBX-16742
//			[
//				[
//					'fields' => [
//						'Encryption level' => ['No encryption', 'PSK'],
//						'PSK identity' => ' ',
//						'PSK' => '21df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
//					],
//					'error' => 'Invalid parameter "/tls_psk_identity": cannot be empty.'
//				]
//			],
			[
				[
					'fields' => [
						'Encryption level' => 'PSK',
						'PSK identity' => 'PSK001'
					],
					'error' => 'Invalid parameter "/tls_psk": cannot be empty.'
				]
			],
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK identity' => 'PSK001'
					],
					'error' => 'Invalid parameter "/tls_psk": cannot be empty.'
				]
			],
			// Check PSK field validation.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK identity' => 'PSK001',
						'PSK' => 'a'
					],
					'error' => 'Invalid parameter "/tls_psk": minimum length is 32 characters.'
				]
			],
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK identity' => 'PSK001',
						'PSK' => '1234567891234567891234567891234Z'
					],
					'error' => 'Invalid parameter "/tls_psk": an even number of hexadecimal characters is expected.'
				]
			]
		];
	}

	/**
	 * @dataProvider getAutoRegistrationValidationData
	 * @backup-once config
	 *
	 * Check auto registration validation on first update.
	 */
	public function testFormAdministrationGeneralAutoRegistration_Validation($data) {
		$this->executeValidation($data);
	}

	/**
	 * Fields validation when updating auto registration or changing PSK fields.
	 *
	 * @param array $data			values of auto registration from data provider
	 * @param boolean $change		change existing values of PSK
	 */
	private function executeValidation($data, $change = false) {
		$sql_config = 'SELECT * FROM config';
		$sql_autoreg = 'SELECT * FROM config_autoreg_tls';
		$old_config_hash = CDBHelper::getHash($sql_config);
		$old_autoreg_hash = CDBHelper::getHash($sql_autoreg);

		$this->page->login()->open('zabbix.php?action=autoreg.edit');
		$form = $this->query('id:autoreg-form')->asForm()->one();
		if (array_key_exists('uncheck_all', $data)) {
			$form->getField('Encryption level')->uncheckAll();
		}
		elseif ($change) {
			$form->query('button:Change PSK')->one()->click();
		}
		$form->fill($data['fields']);
		$form->submit();

		// Check the result in frontend.
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isBad());
		$this->assertEquals('Cannot update configuration', $message->getTitle());
		$this->assertTrue($message->hasLine($data['error']));
		// Check that DB entries aren't changed.
		$this->assertEquals($old_config_hash, CDBHelper::getHash($sql_config));
		$this->assertEquals($old_autoreg_hash, CDBHelper::getHash($sql_autoreg));
	}

	/**
	 * Successfully update auto registration.
	 */
	private function executeUpdate($data) {
		$this->page->login()->open('zabbix.php?action=autoreg.edit');
		$form = $this->query('id:autoreg-form')->asForm()->one();

		// Modify existing PSK values.
		if (array_key_exists('change_psk', $data)) {
			$form->query('button:Change PSK')->one()->click();
			// Check that PSK values are empty.
			$this->assertEquals('', $form->getField('PSK identity')->getValue());
			$this->assertEquals('', $form->getField('PSK')->getValue());
		}
		$form->fill($data['fields']);
		$form->submit();

		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Configuration updated', $message->getTitle());

		$form->invalidate();

		// Check selected encryption level.
		$this->assertEquals($data['fields']['Encryption level'], $form->getField('Encryption level')->getValue());

		// Check the results, if selected only "No encryption" level.
		if (in_array('No encryption', $data['fields']['Encryption level']) && count($data['fields']['Encryption level']) === 1) {
			$this->assertFalse($form->query('id:tls_psk_identity')->one()->isDisplayed());
			$this->assertFalse($form->query('id:tls_psk')->one()->isDisplayed());
			$this->assertTrue($form->query('button:Change PSK')->one(false) === null);

			// Check encryption level and empty PSK values in DB.
			$this->assertEquals(HOST_ENCRYPTION_NONE, CDBHelper::getValue('SELECT autoreg_tls_accept FROM config'));
			$tls_bd = CDBHelper::getRow('SELECT * FROM config_autoreg_tls WHERE autoreg_tlsid=1');
			$this->assertEquals('', $tls_bd['tls_psk_identity']);
			$this->assertEquals('', $tls_bd['tls_psk']);
		}
		// Check the results, if selected PSK.
		else {
			$this->assertTrue($form->query('button:Change PSK')->one()->isDisplayed());
			$this->assertTrue($form->query('id:tls_psk_identity')->one(false) === null);
			$this->assertTrue($form->query('id:tls_psk')->one(false) === null);

			// Check encryption level in DB.
			if (count($data['fields']['Encryption level']) === 1) {
				// Only PSK was selected.
				$this->assertEquals(HOST_ENCRYPTION_PSK, CDBHelper::getValue('SELECT autoreg_tls_accept FROM config'));
			}
			else {
				// PSK and "No encryption" levels were selected.
				$this->assertEquals((HOST_ENCRYPTION_PSK|HOST_ENCRYPTION_NONE), CDBHelper::getValue('SELECT autoreg_tls_accept FROM config'));
			}
		}
	}

	public static function getAutoRegistrationUpdateData() {
		return [
			// Auto registration with default values.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption']
					]
				]
			],
			// Auto registration with PSK only.
			[
				[
					'fields' => [
						'Encryption level' => ['PSK'],
						'PSK identity' => 'PSK001',
						'PSK' => '21df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					]
				]
			],
			// Auto registration with PSK and 'No encryption' levels.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK identity' => 'PSK002',
						'PSK' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getAutoRegistrationUpdateData
	 * @backup config
	 *
	 * First time update Auto registration data.
	 */
	public function testFormAdministrationGeneralAutoRegistration_Update($data) {
		$this->executeUpdate($data);

		// Check PSK values in DB.
		if (in_array('PSK', $data['fields']['Encryption level'])) {
			$tls_bd = CDBHelper::getRow('SELECT * FROM config_autoreg_tls WHERE autoreg_tlsid=1');
			$this->assertEquals($data['fields']['PSK identity'], $tls_bd['tls_psk_identity']);
			$this->assertEquals($data['fields']['PSK'], $tls_bd['tls_psk']);
		}
	}

	/**
	 * Add PSK encryption to then verify for changes in auto registration.
	 */
	public function testFormAdministrationGeneralAutoRegistration_AddPskEncryption() {
		$data = [
			'Encryption level' => ['PSK'],
			'PSK identity' => 'PSK003',
			'PSK' => '00df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
		];

		$this->page->login()->open('zabbix.php?action=autoreg.edit');
		$form = $this->query('id:autoreg-form')->asForm()->one();
		$form->fill($data);
		$form->submit();

		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Configuration updated', $message->getTitle());
	}

	/**
	 * @dataProvider getAutoRegistrationValidationData
	 * @depends testFormAdministrationGeneralAutoRegistration_AddPskEncryption
	 * @backup-once config
	 *
	 * Check auto registration validation, when change PSK values.
	 */
	public function testFormAdministrationGeneralAutoRegistration_ValidationChangePsk($data) {
		$this->executeValidation($data, true);
	}

	public static function getAutoRegistrationChangeData() {
		return [
			// Add "No encryption" level, but unchange PSK data.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
					]
				]
			],
			// Change PSK data, without changing encryption level.
			[
				[
					'change_psk' => true,
					'fields' => [
						'Encryption level' => ['PSK'],
						'PSK identity' => 'PSK004',
						'PSK' => '10df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					]
				]
			],
			// Add "No encryption" level and change PSK data.
			[
				[
					'change_psk' => true,
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK'],
						'PSK identity' => 'PSK005',
						'PSK' => '11df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					]
				]
			],
			// Remove PSK encryption.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getAutoRegistrationChangeData
	 * @depends testFormAdministrationGeneralAutoRegistration_AddPskEncryption
	 * @backup config
	 *
	 * Change auto registration data.
	 */
	public function testFormAdministrationGeneralAutoRegistration_ChangePSK($data) {
		$this->executeUpdate($data);

		if (in_array('PSK', $data['fields']['Encryption level'])) {
			$tls_bd = CDBHelper::getRow('SELECT * FROM config_autoreg_tls WHERE autoreg_tlsid=1');
			// If PSK values isn't changed, than should be used data from previous test case.
			$identity = CTestArrayHelper::get($data, 'fields.PSK identity', 'PSK003');
			$psk = CTestArrayHelper::get($data, 'fields.PSK', '00df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae');
			$this->assertEquals($identity, $tls_bd['tls_psk_identity']);
			$this->assertEquals($psk, $tls_bd['tls_psk']);
		}
	}
}
