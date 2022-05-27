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
		$this->page->login()->open('zabbix.php?action=audit.settings.edit');
		$form = $this->query('id:audit-settings')->asForm()->one();
		$form->checkValue($this->default_values);

		// Check if field "Data storage period" is disabled when options are false
		$form->fill(['Enable audit logging' => true, 'Enable internal housekeeping' => false]);
		$form->query('class:form-field')->one()->isEnabled(false);

		// Check if buttons in view are clickable
		$this->assertTrue($form->query('button', ['Update', 'Reset defaults'])->one()->isClickable());

		// Check if Header and Title are as expected
		$this->page->assertHeader('Audit log');
		$this->page->assertTitle('Configuration of audit log');
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 **/
	public function testFormAdministrationGeneralAuditLog_CheckResetDefaultButtonsFunctions() {
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
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => false
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1440m'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140000m'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13139999m'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '`!@#$%^&*()_+|'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => 'test'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => ' '
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '¯\_(ツ)_/¯'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '0s'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1s'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1m'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1439m'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140001m'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1h'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219001h'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1M'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1M'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1y'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1y'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": a time unit is expected.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86399s'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400001s'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9126d'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1304w'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
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
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			]
		];
	}

	/**
	 * Function tests all possible variants for the "Data storage period" field, checking boundary values,
	 * and using all possible time units (s/d/h/w/M/y) by submitting different values.
	 * After each data set values are reset, for which a private function is used.
	 *
	 * @dataProvider getUpdateValueData
	 **/
	public function testFormAdministrationGeneralAuditLog_UpdateParameters($data) {
		$this->page->login()->open('zabbix.php?action=audit.settings.edit');
		$form = $this->query('id:audit-settings')->asForm()->one();
		$form->fill($data['fields']);
		$form->submit()->waitUntilReloaded();

		if($data['expected'] === TEST_GOOD) {
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$form->checkValue($data['fields']);
			$form->query('id:resetDefaults')->one()->click();
			COverlayDialogElement::find()->waitUntilVisible()->one()->query('button', 'Reset defaults')->one()->click();
			$form->submit()->waitUntilReloaded();
			$form->checkValue($this->default_values);
		}
		else {
			$this->assertMessage(TEST_BAD, $data['message'], $data['msgdetails']);
		}
	}
}
