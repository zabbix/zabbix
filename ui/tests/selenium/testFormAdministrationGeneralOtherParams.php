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

require_once dirname(__FILE__).'/common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralOtherParams extends testFormAdministrationGeneral {

	public $config_link = 'zabbix.php?action=miscconfig.edit';
	public $form_selector = 'name:otherForm';

	public $default_values = [
		'Frontend URL' => '',
		'Group for discovered hosts' => 'Empty group',
		'Default host inventory mode' => 'Disabled',
		'User group for database down message' => 'Zabbix administrators',
		'Log unmatched SNMP traps' => true,
		// Authorization.
		'Login attempts' => 5,
		'Login blocking interval' => '30s',
		// Storage of secrets
		'Vault provider' => 'HashiCorp Vault',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'X-Frame-Options HTTP header' => 'SAMEORIGIN',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => ''
	];

	public $db_default_values = [
		'url' => '',
		'discovery_groupid' => 50006,
		'default_inventory_mode' => -1,
		'alert_usrgrpid' => 7,
		'snmptrap_logging' => 1,
		// Authorization.
		'login_attempts' => 5,
		'login_block' => '30s',
		// Storage of secrets
		'vault_provider' => 0,
		// Security.
		'validate_uri_schemes' => 1,
		'uri_valid_schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'x_frame_options' => 'SAMEORIGIN',
		'iframe_sandboxing_enabled' => 1,
		'iframe_sandboxing_exceptions' => ''
	];

	public $custom_values = [
		'Frontend URL' => 'http://zabbix.com',
		'Group for discovered hosts' => 'Hypervisors',
		'Default host inventory mode' => 'Automatic',
		'User group for database down message' => 'Test timezone',
		'Log unmatched SNMP traps' => false,
		// Authorization.
		'Login attempts' => 13,
		'Login blocking interval' => '52s',
		// Storage of secrets
		'Vault provider' => 'CyberArk Vault',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'custom_scheme',
		'X-Frame-Options HTTP header' => 'SOME_NEW_VALUE',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => 'some-new-flag'
	];

	/**
	 * Test for checking form layout.
	 */
	public function testFormAdministrationGeneralOtherParams_CheckLayout() {
		$this->page->login()->open($this->config_link);
		$this->page->assertTitle('Other configuration parameters');
		$this->page->assertHeader('Other configuration parameters');
		$form = $this->query($this->form_selector)->waitUntilReady()->asForm()->one();

		foreach (['Authorization', 'Security'] as $header) {
			$this->assertTrue($this->query('xpath://h4[text()="'.$header.'"]')->one()->isVisible());
		}

		$limits = [
			'url' => 2048,
			'login_attempts' => 2,
			'login_block' => 32,
			'uri_valid_schemes' => 255,
			'x_frame_options' => 255,
			'iframe_sandboxing_exceptions' => 255
		];
		foreach ($limits as $id => $limit) {
			$this->assertEquals($limit, $this->query('id', $id)->one()->getAttribute('maxlength'));
		}

		foreach ([true, false] as $status) {
			$checkboxes = [
				'snmptrap_logging',
				'validate_uri_schemes',
				'iframe_sandboxing_enabled'
			];
			foreach ($checkboxes as $checkbox) {
				$form->getField('id:'.$checkbox)->fill($status);
			}

			foreach (['uri_valid_schemes','iframe_sandboxing_exceptions'] as $input) {
				$this->assertTrue($this->query('id', $input)->one()->isEnabled($status));
			}
		}

		foreach (['Update', 'Reset defaults'] as $button) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled());
		}
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationGeneralOtherParams_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationGeneralOtherParams_ResetButton() {
		$this->executeResetButtonTest(true);
	}

	/**
	 * Test data for Other parameters form.
	 */
	public function getCheckFormData() {
		return [
			// Minimal valid values. In period fields minimal valid time in seconds with 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Frontend URL' => 'a',
						'Group for discovered hosts' => 'Hypervisors',
						'Default host inventory mode' => 'Manual',
						'User group for database down message' => 'Test timezone',
						'Log unmatched SNMP traps' => false,
						// Authorization.
						'Login attempts' => 1,
						'Login blocking interval' => '30s',
						// Security.
						'Validate URI schemes' => false,
						'X-Frame-Options HTTP header' => 'X',
						'Use iframe sandboxing' => false
					],
					'db' => [
						'url' => 'a',
						'discovery_groupid' => 7,
						'default_inventory_mode' => 0,
						'alert_usrgrpid' => 92,
						'snmptrap_logging' => 0,
						// Authorization.
						'login_attempts' => 1,
						'login_block' => '30s',
						// Security.
						'validate_uri_schemes' => 0,
						'x_frame_options' => 'X',
						'iframe_sandboxing_enabled' => 0
					]
				]
			],
			// Minimal valid values. In period fields minimal valid time in seconds without 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Frontend URL' => 'zabbix.php',
						'Default host inventory mode' => 'Automatic',
						'Log unmatched SNMP traps' => true,
						// Authorization.
						'Login blocking interval' => '30',
						// Security.
						'Validate URI schemes' => true,
						'Valid URI schemes' => '',
						'Use iframe sandboxing' => true,
						'Iframe sandboxing exceptions' => ''
					],
					'db' => [
						'url' => 'zabbix.php',
						'default_inventory_mode' => 1,
						'snmptrap_logging' => 1,
						// Authorization.
						'login_block' => '30',
						// Security.
						'validate_uri_schemes' => 1,
						'uri_valid_schemes' => '',
						'iframe_sandboxing_enabled' => 1,
						'iframe_sandboxing_exceptions' => ''
					]
				]
			],
			// In period fields minimal valid time in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '1m'
					],
					'db' => [
						// Authorization.
						'login_block' => '1m'
					]
				]
			],
			// In period fields minimal valid time in hours.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '1h'
					],
					'db' => [
						// Authorization.
						'login_block' => '1h'
					]
				]
			],
			// Maximal valid values in seconds with "s".
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => 32,
						'Login blocking interval' => '3600s',
						// Security.
						'Validate URI schemes' => true,
						'Valid URI schemes' => 'http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,'.
								'https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,'.
								'tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https',
						'X-Frame-Options HTTP header' => 'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,'.
								'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,'.
								'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SA',
						'Use iframe sandboxing' => true,
						'Iframe sandboxing exceptions' => 'some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-'.
								'flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-'.
								'flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-som'
					],
					'db' => [
						// Authorization.
						'login_attempts' => 32,
						'login_block' => '3600s',
						// Security.
						'validate_uri_schemes' => 1,
						'uri_valid_schemes' => 'http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,'.
								'ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,'.
						'http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https',
						'x_frame_options' => 'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,'.
								'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,'.
								'SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SAMEORIGIN,SA',
						'iframe_sandboxing_enabled' => 1,
						'iframe_sandboxing_exceptions' => 'some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag'.
								'-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag'.
								'-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-som'
					]
				]
			],
			// In period fields maximal valid values in seconds without "s".
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '3600'
					],
					'db' => [
						// Authorization.
						'login_block' => '3600'
					]
				]
			],
			// In period fields maximal valid values in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '60m'
					],
					'db' => [
						// Authorization.
						'login_block' => '60m'
					]
				]
			],
			// Symbol trimming in Login attempts.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Login attempts' => '3M'
					],
					'db' => [
						'login_attempts' => 3
					]
				]
			],
			// Invalid empty values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Group for discovered hosts' => '',
						'User group for database down message' => '',
						// Authorization.
						'Login attempts' => '',
						'Login blocking interval' => '',
						// Security.
						'X-Frame-Options HTTP header' => ''
					],
					'details' => [
						'Field "discovery_groupid" is mandatory.',
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "x_frame_options": cannot be empty.'
					]
				]
			],
			// Invalid string values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => 'text',
						'Login blocking interval' => 'text'
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// Invalid special symbol values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => '!@#$%^&*()_+',
						'Login blocking interval' => '!@#$%^&*()_+'
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// Invalid zero values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => 0,
						'Login blocking interval' => 0
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Invalid zero values in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '0s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// In period fields minimal invalid time in seconds without "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '29'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// In period fields minimal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '29s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// In period fields maximal invalid time in seconds without "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => 33,
						'Login blocking interval' => '3601'
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no greater than "32".',
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Maximal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '3601s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Maximal invalid time in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '61m'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Maximal invalid time in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '2h'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Maximal invalid time in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '1w'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Maximal invalid time in Months (Months not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '1M'
					],
					'details' => [
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// Maximal invalid time in years (years not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '1y'
					],
					'details' => [
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// Maximal invalid values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => '99',
						'Login blocking interval' => '99999999999999999999999999999999'
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no greater than "32".',
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// Negative values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => '-1',
						'Login blocking interval' => '-1'
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFormData
	 */
	public function testFormAdministrationGeneralOtherParams_CheckForm($data) {
		$this->executeCheckForm($data, true);
	}
}
