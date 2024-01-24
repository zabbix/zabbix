<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once dirname(__FILE__).'/common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralAuditLog extends testFormAdministrationGeneral {

	public $form_selector = 'id:audit-settings';
	public $config_link = 'zabbix.php?action=audit.settings.edit';
	public $default_values = [
		'Enable audit logging' => true,
		'Enable internal housekeeping' => true,
		'Data storage period' => '365d'
	];

	public $custom_values = [
		'Enable audit logging' => true,
		'Enable internal housekeeping' => true,
		'Data storage period' => '400d'
	];

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * The function's main purpose is to check if the layout of the page is not broken and fields are in their place,
	 * Additional checkups are made and committed within the function.
	 */
	public function testFormAdministrationGeneralAuditLog_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();
		$form = $this->query('id:audit-settings')->waitUntilPresent()->asForm()->one();
		$form->checkValue($this->default_values);

		// Check if field "Data storage period" is disabled when options are in all possible ways.
		$checkboxes = [
			['audit' => true, 'housekeeping' => false],
			['audit' => false, 'housekeeping' => true],
			['audit' => false, 'housekeeping' => false],
			['audit' => true, 'housekeeping' => true]
		];

		foreach ($checkboxes as $case) {
			$form->fill(['Enable audit logging' => $case['audit'], 'Enable internal housekeeping' => $case['housekeeping']]);
			$this->assertTrue($form->getField('Data storage period')->isEnabled($case['housekeeping']));
		};

		// Check fields "Data storage period" maxlength.
		$this->assertEquals(32, $form->getField('Data storage period')->getAttribute('maxlength'));

		// Check if buttons in view are clickable.
		$this->assertTrue($form->query('button', ['Update', 'Reset defaults'])->one()->isClickable());

		// Check if Header and Title are as expected.
		$this->page->assertHeader('Audit log');
		$this->page->assertTitle('Configuration of audit log');
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function totestFormAdministrationGeneralAuditLog_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationGeneralAuditLog_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	public static function getUpdateValueData() {
		return [
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => false
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '0',
						'hk_audit' => '365d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => false
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '0',
						'hk_audit' => '365d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '365d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '365d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1440m'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1440m'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140000m'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '13140000m'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13139999m'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '13139999m'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '24h'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '219000h'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '218999h'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1w'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '86400s'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '788400000s'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '788400000s'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '788399999s'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '9125d'
					]
				]
			],
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1303w'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '`!@#$%^&*()_+|'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'test'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => ' '
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '¯\_(ツ)_/¯'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '0s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1439m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140001m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1h'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219001h'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86399s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400001s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9126d'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1304w'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1s'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1d'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1w'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'null'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => ''
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			]
		];
	}

	/**
	 * Function tests all possible variants for the "Data storage period" field, checking boundary values,
	 * and using all possible time units (s/h/d/w/M/y) by submitting different values.
	 * After each data set values are reset, for which a private function is used.
	 *
	 * @dataProvider getUpdateValueData
	 */
	public function testFormAdministrationGeneralAuditLog_UpdateParameters($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash('SELECT * FROM config');
		}

		$this->page->login()->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();
		$form = $this->query('id:audit-settings')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit()->waitUntilReloaded();

		if ($expected === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$form->checkValue($data['fields']);

			// Check DB configuration.
			$this->assertEquals($data['db_check'],  CDBHelper::getRow('SELECT auditlog_enabled, hk_audit_mode, hk_audit FROM config'));

			// Reset back to default values.
			$form->query('id:resetDefaults')->one()->click();
			COverlayDialogElement::find()->waitUntilVisible()->one()->query('button:Reset defaults')->one()->click();
			$form->submit()->waitUntilReloaded();
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot update configuration', $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash('SELECT * FROM config'));
		}
	}
}
