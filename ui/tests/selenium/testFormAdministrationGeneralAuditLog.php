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
 */
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
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	// Navigate to audit log page from dashboard page.
	private function NavigateToAuditLog(){
		$this->page->login()->open('zabbix.php?action=gui.edit');
		$this->query('id:page-title-general')->asPopupButton()->one()->select('Audit log');
	}

	// Reset and check default values.
	private function ResetValues(){
		$form = $this->query('id:audit-settings')->asForm()->one();
		$form->query('id:resetDefaults')->one()->click();
		COverlayDialogElement::find()->waitUntilVisible()->one()->query('button', 'Reset defaults')->one()->click();
		$form->submit();
		$form->checkValue(['Enable audit logging' => true,'Enable internal housekeeping' => true,
			'Data storage period' => '365d']);
	}

	/**
	* The function's main purpose is to check if the layout of the page is not broken and fields are in their place,
	* Additional checkups are made and committed within the function.
	*/
	public function testFormAdministrationGeneralAuditLog_CheckLayout(){
		$this->page->login()->open('zabbix.php?action=audit.settings.edit');
		$form = $this->query('id:audit-settings')->asForm()->one();
		$form->fill(['Enable audit logging' => true, 'Enable internal housekeeping' => true,
			'Data storage period' => '365d']);
		$form->checkValue(['Enable audit logging' => true, 'Enable internal housekeeping' => true, 
			'Data storage period' => '365d']);
		
		// Check if field "Data storage period" is disabled when options are false
		$form->fill(['Enable audit logging' => true, 'Enable internal housekeeping' => false]);
		$form->query('class:form-field')->one()->isEnabled(false);

		// Check if buttons in view are clickable
		$this->assertTrue($form->query('button:Update')->one()->isClickable());
		$this->assertTrue($form->query('button:Reset defaults')->one()->isClickable());

		// Check if Header and Title are as expected
		$this->page->assertHeader('Audit log');
		$this->page->assertTitle('Configuration of audit log');
	}

	/**
	* Test for checking 'Reset defaults' button.
	*/
	public function testFormAdministrationGeneralAuditLog_CheckResetDefaultButtonsFunctions(){
		$this->executeResetButtonTest();
	}

	/**
	* Test for checking form update without changing any data.
	*/
	public function testFormAdministrationGeneralAuditLog_SimpleUpdate(){
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
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '365d'
					],
					'message' => 'Configuration updated'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1440m'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13140000m'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '13139999m'
					],
					'message' => 'Configuration updated'
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
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '23h'
					],
					'message' => 'Cannot update configuration',
					'msgdetails' => 'Incorrect value for field "hk_audit": value must be one of 86400-788400000.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '24h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '219000h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '218999h'
					],
					'message' => 'Configuration updated'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1w'
					],
					'message' => 'Configuration updated'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '86400s'
					],
					'message' => 'Configuration updated'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788400000s'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '788399999s'
					],
					'message' => 'Configuration updated'
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
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '9125d'
					],
					'message' => 'Configuration updated'
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
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => true,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					],
					'message' => 'Configuration updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Enable audit logging' => false,
						'Enable internal housekeeping' => true,
						'Data storage period' => '1303w'
					],
					'message' => 'Configuration updated'
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
	*/
	public function testFormAdministrationGeneralAuditLog_UpdateParameters($data){
		$this->page->login()->open('zabbix.php?action=audit.settings.edit');
		$form = $this->query('id:audit-settings')->asForm()->one();

		if($data['expected'] === TEST_GOOD){
			$form->fill($data['fields']);
			$form->submit();
			$this->assertMessage(TEST_GOOD, $data['message']);
			$this->ResetValues();
		}
		else{
			$form->fill($data['fields']);
			$form->submit();
			$this->assertMessage(TEST_BAD, $data['message'],$data['msgdetails']);
			$this->query('xpath://output[@class="msg-bad"]/button')->one()->click();
		}
	}
}
