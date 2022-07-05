<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 **/
class testFormAdministrationGeneralAuditLog extends testFormAdministrationGeneral {

	public $config_link = 'zabbix.php?action=audit.settings.edit';
	public $form_selector = 'id:audit-settings';
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
	 **/
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	/**
	 * The function's main purpose is to check if the layout of the page is not broken and fields are in their place,
	 * Additional checkups are made and committed within the function.
	 **/
	public function testFormAdministrationGeneralAuditLog_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();
		$form = $this->query('id:audit-settings')->waitUntilPresent()->asForm()->one();
		$form->checkValue($this->default_values);

		// Check if field "Data storage period" is disabled when options are false.
		$form->fill(['Enable audit logging' => true, 'Enable internal housekeeping' => false]);
		$this->assertFalse($form->getField('Data storage period')->isEnabled());

		// Check fields "Data storage period" maxlength
		foreach (['Data storage period' => 32] as $field => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check if buttons in view are clickable.
		$this->assertTrue($form->query('button', ['Update', 'Reset defaults'])->one()->isClickable());

		// Check if Header and Title are as expected.
		$this->page->assertHeader('Audit log');
		$this->page->assertTitle('Configuration of audit log');
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 **/
	public function totestFormAdministrationGeneralAuditLog_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test for checking form update without changing any data.
	 **/
	public function testFormAdministrationGeneralAuditLog_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	public static function getUpdateValueData() {
		return [
			// Data set #0
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #1
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #2
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #3
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #4
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #5
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1440m'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1440m'
					]
				]
			],
			// Data set #6
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140000m'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '13140000m'
					]
				]
			],
			// Data set #7
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #8
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #9
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13139999m'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '13139999m'
					]
				]
			],
			// Data set #10
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #11
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '24h'
					]
				]
			],
			// Data set #12
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '219000h'
					]
				]
			],
			// Data set #13
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #14
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #15
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '218999h'
					]
				]
			],
			// Data set #16
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #17
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1d'
					]
				]
			],
			// Data set #18
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '1w'
					]
				]
			],
			// Data set #19
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #20
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #21
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '86400s'
					]
				]
			],
			// Data set #22
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #23
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #24
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '788399999s'
					]
				]
			],
			// Data set #25
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #26
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					],
					'db_check' => [
						'auditlog_enabled' => '1',
						'hk_audit_mode' => '1',
						'hk_audit' => '9125d'
					]
				]
			],
			// Data set #27
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #28
			[
				[
					'expected' => TEST_GOOD,
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
			// Data set #29
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					],
					'db_check' => [
						'auditlog_enabled' => '0',
						'hk_audit_mode' => '1',
						'hk_audit' => '1303w'
					]
				]
			],
			// Data set #30
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '`!@#$%^&*()_+|'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #31
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
			// Data set #32
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
			// Data set #33
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'test'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #34
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => ' '
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #35
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
			// Data set #36
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
			// Data set #37
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '¯\_(ツ)_/¯'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #38
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '0s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #39
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
			// Data set #40
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
			// Data set #41
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #42
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #43
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
			// Data set #44
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
			// Data set #45
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1439m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #46
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140001m'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #47
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
			// Data set #48
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
			// Data set #49
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1h'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #50
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
			// Data set #51
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219001h'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #52
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1M'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #53
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1M'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #54
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1y'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #55
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1y'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #56
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
			// Data set #57
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86399s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #58
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400001s'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #59
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
			// Data set #60
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
			// Data set #61
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9126d'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #62
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1304w'
					],
					'details' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			// Data set #63
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
			// Data set #64
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
			// Data set #65
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1s'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #66
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1m'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #67
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1m'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #68
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1h'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #69
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1h'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #70
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1d'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #71
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
			// Data set #72
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
			// Data set #73
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1w'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #74
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1M'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #75
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1M'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #76
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1y'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #77
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '-1y'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #78
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'null'
					],
					'details' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			// Data set #79
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
			// Data set #80
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
			],
			// Data set #81
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
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
	 **/
	public function testFormAdministrationGeneralAuditLog_UpdateParameters($data) {
		$hashsql = 'SELECT * FROM token ORDER BY tokenid';
		$old_hash = CDBHelper::getHash($hashsql);
		$this->page->login()->open('zabbix.php?action=audit.settings.edit')->waitUntilReady();
		$form = $this->query('id:audit-settings')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit()->waitUntilReloaded();

		if($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$form->checkValue($data['fields']);

			// Check DB configuration.
			$dbsql = 'SELECT auditlog_enabled, hk_audit_mode, hk_audit FROM config';
			$result = CDBHelper::getRow($dbsql);
			$this->assertEquals($data['db_check'], $result);

			// Reset back to default values.
			$form->query('id:resetDefaults')->one()->click();
			COverlayDialogElement::find()->waitUntilVisible()->one()->query('button:Reset defaults')->one()->click();
			$form->submit()->waitUntilReloaded();

			// Check default values.
			$form->checkValue($this->default_values);
		}
		else {
			$this->assertMessage(TEST_BAD, 'Cannot update configuration', $data['details']);
			$this->assertEquals($old_hash, CDBHelper::getHash($hashsql));
		}
	}
}
