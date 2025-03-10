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


require_once __DIR__.'/../common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationAuditLog extends testFormAdministrationGeneral {

	public $form_selector = 'id:audit-settings';
	public $config_link = 'zabbix.php?action=audit.settings.edit';
	public $default_values = [
		'Enable audit logging' => true,
		'Log system actions' => true,
		'Enable internal housekeeping' => true,
		'Data storage period' => '31d'
	];

	public $custom_values = [
		'Enable audit logging' => true,
		'Log system actions' => true,
		'Enable internal housekeeping' => true,
		'Data storage period' => '400d'
	];

	public $db_default_values = [
		'auditlog_enabled' => 1,
		'auditlog_mode' => 1,
		'hk_audit_mode' => 1,
		'hk_audit' => '31d'
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
	public function testFormAdministrationAuditLog_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();
		$form = $this->query('id:audit-settings')->waitUntilPresent()->asForm()->one();
		$form->checkValue($this->default_values);

		// Check if fields "Data storage period" and "Logs system action" are disabled when options are in all possible ways.
		$checkboxes = [
			['audit' => true, 'actions' => true, 'housekeeping' => false],
			['audit' => true, 'actions' => false, 'housekeeping' => false],
			['audit' => false, 'actions' => true, 'housekeeping' => true],
			['audit' => false, 'actions' => true, 'housekeeping' => false],
			['audit' => true, 'actions' => true, 'housekeeping' => true],
			['audit' => false, 'actions' => false, 'housekeeping' => false]
		];

		foreach ($checkboxes as $case) {
			$form->fill(['Log system actions' => $case['actions'], 'Enable audit logging' => $case['audit'],
					'Enable internal housekeeping' => $case['housekeeping']]
			);
			$this->assertTrue($form->getField('Data storage period')->isEnabled($case['housekeeping']));
			$this->assertTrue($form->getField('Log system actions')->isEnabled($case['audit']));
		};

		// Check hintbox.
		$form->getLabel('Log system actions')->query('class:zi-help-filled-small')->one()->click();
		$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

		// Assert text.
		$this->assertEquals('Log changes by low-level discovery, network discovery and autoregistration',
				$hint->one()->getText()
		);

		// Close the hint-box.
		$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint->waitUntilNotPresent();

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
	public function totestFormAdministrationAuditLog_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationAuditLog_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	public static function getUpdateValueData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => false
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '0',
						'hk_audit' => '31d'
					]
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => false
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '0',
						'hk_audit' => '31d'
					]
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '365d'
					]
				]
			],
			// #3.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '365d'
					]
				]
			],
			// #4.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1440m'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1440m'
					]
				]
			],
			// #5.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140000m'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '13140000m'
					]
				]
			],
			// #6.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13139999m'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '13139999m'
					]
				]
			],
			// #7.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '24h'
					]
				]
			],
			// #8.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '219000h'
					]
				]
			],
			// #9.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '218999h'
					]
				]
			],
			// #10.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1d'
					]
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1w'
					]
				]
			],
			// #12.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '86400s'
					]
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '788400000s'
					]
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '788400000s'
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '788399999s'
					]
				]
			],
			// #16.
			[
				[
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					],
					'db' => [
						'auditlog_enabled' => '0',
						'auditlog_mode' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '9125d'
					]
				]
			],
			// #17.
			[
				[
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					],
					'db' => [
						'auditlog_enabled' => '1',
						'auditlog_mode' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1303w'
					]
				]
			],
			// #18.
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
			// #19.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'test'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// #20.
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
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '¯\_(ツ)_/¯'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// #22.
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
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// #24.
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
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1439m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// #26.
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
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1h'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// #28.
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
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86399s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// #30.
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
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9126d'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// #32.
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
			// #33.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1s'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// #34.
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
			// #35.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1w'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// #36.
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
			// #37.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Log system actions' => true,
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
	public function testFormAdministrationAuditLog_UpdateParameters($data) {
		$this->executeCheckForm($data);
	}
}
