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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralAutoregistration extends CWebTest {

	/**
	 * Check the default state of page elements, when first time open Autoregistration.
	 */
	public function testFormAdministrationGeneralAutoregistration_checkDefaultState() {
		// Navigate to autoregistration page from dashboard page.
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Autoregistration');

		// Check elements default state.
		$form = $this->query('id:autoreg-form')->asForm()->one();
		$this->assertEquals(['No encryption'], $form->getField('Encryption level')->getValue());
		$this->assertFalse($form->query('id:tls_psk_identity')->one()->isDisplayed());
		$this->assertFalse($form->query('id:tls_psk')->one()->isDisplayed());
	}

	/**
	 * Check PSK field values, when PSK encryption is set and unset.
	 */
	public function testFormAdministrationGeneralAutoregistration_PskValues() {
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
						'Resource' => 'Autoregistration',
						'Action' => 'Update',
						'ID' => 1,
						'Details' => [
							'Details',
							'autoregistration.tls_accept: 1 => 2',
							'autoregistration.tls_psk: ****** => ******'
						]
					],
					'detail_button' => [
						'autoregistration.tls_accept: 1 => 2',
						'autoregistration.tls_psk: ****** => ******',
						'autoregistration.tls_psk_identity: ****** => ******'
					]
				]
			],
			// Add "No encryption" level, but without changing PSK data.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK']
					],
					'audit' => [
						'User' => 'Admin',
						'Resource' => 'Autoregistration',
						'Action' => 'Update',
						'ID' => 1,
						'Details' => ['autoregistration.tls_accept: 2 => 3']
					]
				]
			],
			// Remove PSK encryption.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption']
					],
					'audit' => [
						'User' => 'Admin',
						'Resource' => 'Autoregistration',
						'Action' => 'Update',
						'ID' => 1,
						'Details' => [
							'Details',
							'autoregistration.tls_accept: 3 => 1',
							'autoregistration.tls_psk: ****** => ******'
						]
					],
					'detail_button' => [
						'autoregistration.tls_accept: 3 => 1',
						'autoregistration.tls_psk: ****** => ******',
						'autoregistration.tls_psk_identity: ****** => ******'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getAuditReportData
	 * @backupOnce config
	 *
	 * Check record on Audit report page, after updating autoregistration.
	 */
	public function testFormAdministrationGeneralAutoregistration_Audit($data) {
		// Add encryption.
		$this->page->login()->open('zabbix.php?action=autoreg.edit');
		// Added sleep, because sorting on Audit page is by time,
		// but sometimes there is no time difference between test cases and they are sorted unpredictably
		sleep(1);
		$form = $this->query('id:autoreg-form')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Configuration updated', $message->getTitle());

		// Check Audit record about autoregistration update.
		$this->page->open('zabbix.php?action=auditlog.list');

		// Click on Filter tab if it is not selected.
		if ($this->query('xpath://li[@aria-labelledby="ui-id-2" and @aria-selected="false"]')->exists()) {
			$this->query('id:ui-id-2')->one()->click();
		}

		// Reset filter to delete deependencies from previous tests.
		$this->query('button:Reset')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$rows = $this->query('class:list-table')->asTable()->one()->getRows();

		// Get first row data.
		$row = $rows->get(0);
		foreach ($data['audit'] as $column => $value) {
			$text = $row->getColumn($column)->getText();
			if (is_array($value)) {
				$text = explode("\n", $text);
				sort($text);
				sort($value);
			}
			$this->assertEquals($value, $text);
		}
		if (array_key_exists('detail_button', $data)) {
			$row->getColumn('Details')->query('link:Details')->one()->click();
			$details = COverlayDialogElement::find()->waitUntilVisible()->one();
			$details_text = explode("\n", $details->query('xpath://textarea')->one()->getText());
			sort($details_text);
			$this->assertEquals($details_text, $data['detail_button']);
		}
	}

	public static function getAutoregistrationValidationData() {
		return [
			// Autoregistration without encryption level.
			[
				[
					'uncheck_all' => true,
					'fields' => [],
					'error' => 'Incorrect value "0" for "tls_accept" field.'
				]
			],
			// Autoregistration with empty PSK values.
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
	 * @dataProvider getAutoregistrationValidationData
	 * @backupOnce config
	 *
	 * Check autoregistration validation on first update.
	 */
	public function testFormAdministrationGeneralAutoregistration_Validation($data) {
		$this->executeValidation($data);
	}

	/**
	 * Fields validation when updating autoregistration or changing PSK fields.
	 *
	 * @param array   $data    Values of autoregistration from data provider.
	 * @param boolean $change  Change existing values of PSK.
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
	 * Successfully update autoregistration.
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
			$this->assertFalse($form->query('button:Change PSK')->one(false)->isValid());

			// Check encryption level and empty PSK values in DB.
			$this->assertEquals(HOST_ENCRYPTION_NONE, CDBHelper::getValue('SELECT autoreg_tls_accept FROM config'));
			$tls_bd = CDBHelper::getRow('SELECT * FROM config_autoreg_tls WHERE autoreg_tlsid=1');
			$this->assertEquals('', $tls_bd['tls_psk_identity']);
			$this->assertEquals('', $tls_bd['tls_psk']);
		}
		// Check the results, if selected PSK.
		else {
			$this->assertTrue($form->query('button:Change PSK')->one()->isDisplayed());
			$this->assertFalse($form->query('id:tls_psk_identity')->one(false)->isValid());
			$this->assertFalse($form->query('id:tls_psk')->one(false)->isValid());

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

	public static function getAutoregistrationUpdateData() {
		return [
			// Autoregistration with default values.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption']
					]
				]
			],
			// Autoregistration with PSK only.
			[
				[
					'fields' => [
						'Encryption level' => ['PSK'],
						'PSK identity' => 'PSK001',
						'PSK' => '21df83bf21bf0be663090bb8d4128558ab9b95fba66a6dbf834f8b91ae5e08ae'
					]
				]
			],
			// Autoregistration with PSK and 'No encryption' levels.
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
	 * @dataProvider getAutoregistrationUpdateData
	 * @backup config
	 *
	 * First time update Autoregistration data.
	 */
	public function testFormAdministrationGeneralAutoregistration_Update($data) {
		$this->executeUpdate($data);

		// Check PSK values in DB.
		if (in_array('PSK', $data['fields']['Encryption level'])) {
			$tls_bd = CDBHelper::getRow('SELECT * FROM config_autoreg_tls WHERE autoreg_tlsid=1');
			$this->assertEquals($data['fields']['PSK identity'], $tls_bd['tls_psk_identity']);
			$this->assertEquals($data['fields']['PSK'], $tls_bd['tls_psk']);
		}
	}

	/**
	 * Add PSK encryption to then verify for changes in autoregistration.
	 */
	public function testFormAdministrationGeneralAutoregistration_AddPskEncryption() {
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
	 * @dataProvider getAutoregistrationValidationData
	 * @depends testFormAdministrationGeneralAutoregistration_AddPskEncryption
	 * @backupOnce config
	 *
	 * Check autoregistration validation, when change PSK values.
	 */
	public function testFormAdministrationGeneralAutoregistration_ValidationChangePsk($data) {
		$this->executeValidation($data, true);
	}

	public static function getAutoregistrationChangeData() {
		return [
			// Add "No encryption" level, but unchange PSK data.
			[
				[
					'fields' => [
						'Encryption level' => ['No encryption', 'PSK']
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
	 * @dataProvider getAutoregistrationChangeData
	 * @depends testFormAdministrationGeneralAutoregistration_AddPskEncryption
	 * @backup config
	 *
	 * Change autoregistration data.
	 */
	public function testFormAdministrationGeneralAutoregistration_ChangePSK($data) {
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
