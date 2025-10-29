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
		'id:validate_uri_schemes' => true,
		'id:uri_valid_schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'id:x_frame_header_enabled' => true,
		'id:x_frame_options' => 'SAMEORIGIN',
		'id:iframe_sandboxing_enabled' => true,
		'id:iframe_sandboxing_exceptions' => ''
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
		'id:validate_uri_schemes' => true,
		'id:uri_valid_schemes' => 'custom_scheme',
		'id:x_frame_header_enabled' => true,
		'id:x_frame_options' => 'SOME-NEW-VALUE',
		'id:iframe_sandboxing_enabled' => true,
		'id:iframe_sandboxing_exceptions' => 'some-new-flag'
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
				'x_frame_header_enabled',
				'iframe_sandboxing_enabled'
			];
			foreach ($checkboxes as $checkbox) {
				$form->getField('id:'.$checkbox)->fill($status);
			}

			foreach (['uri_valid_schemes','iframe_sandboxing_exceptions', 'x_frame_options'] as $input) {
				$this->assertTrue($this->query('id', $input)->one()->isEnabled($status));
			}
		}

		// Check X-Frame-Options hintbox.
		$form->getLabel('Use X-Frame-Options HTTP header')->query('xpath:./button[@data-hintbox]')->one()->waitUntilClickable()->click();
		$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilPresent()->one();

		$hint_text = "X-Frame-Options HTTP header supported values:\n".
				"SAMEORIGIN or 'self' - allows the page to be displayed only in a frame on the same origin as the page itself\n".
				"DENY or 'none' - prevents the page from being displayed in a frame, regardless of the site attempting to do so\n".
				"a string of space-separated hostnames; adding 'self' to the list allows the page to be displayed in a frame on the same origin as the page itself\n".
				"\n".
				"Note that 'self' or 'none' will be regarded as hostnames if used without single quotes.";

		$this->assertEquals($hint_text, $hint->getText());
		$hint->close();

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
			// #0 Minimal valid values. In period fields minimal valid time in seconds with 's'.
			[
				[
					'fields' => [
						'Frontend URL' => 'a',
						'Group for discovered hosts' => 'Hypervisors',
						'Default host inventory mode' => 'Manual',
						'User group for database down message' => 'Test timezone',
						'Log unmatched SNMP traps' => false,
						// Authorization.
						'Login attempts' => 1,
						'Login blocking interval' => '30s',
						// Security.
						'id:validate_uri_schemes' => false,
						'id:x_frame_header_enabled' => true,
						'id:x_frame_options' => 'X',
						'id:iframe_sandboxing_enabled' => false
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
			// #1 Minimal valid values. In period fields minimal valid time in seconds without 's'.
			[
				[
					'fields' => [
						'Frontend URL' => 'zabbix.php',
						'Default host inventory mode' => 'Automatic',
						'Log unmatched SNMP traps' => true,
						// Authorization.
						'Login blocking interval' => '30',
						// Security.
						'id:validate_uri_schemes' => true,
						'id:uri_valid_schemes' => '',
						'id:iframe_sandboxing_enabled' => true,
						'id:iframe_sandboxing_exceptions' => ''
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
			// #2 In period fields minimal valid time in minutes.
			[
				[
					'fields' => [
						// Authorization.
						'Login blocking interval' => '1m'
					],
					'db' => [
						// Authorization.
						'login_block' => '1m'
					]
				]
			],
			// #3 In period fields minimal valid time in hours.
			[
				[
					'fields' => [
						// Authorization.
						'Login blocking interval' => '1h'
					],
					'db' => [
						// Authorization.
						'login_block' => '1h'
					]
				]
			],
			// #4 Maximal valid values in seconds with "s".
			[
				[
					'fields' => [
						// Authorization.
						'Login attempts' => 32,
						'Login blocking interval' => '3600s',
						// Security.
						'id:validate_uri_schemes' => true,
						'id:uri_valid_schemes' => 'http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,'.
								'https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,'.
								'tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https,ftp,file,mailto,tel,ssh,http,https',
						'id:x_frame_header_enabled' => true,
						'id:x_frame_options' => 'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN '.
								'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN '.
								'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SA',
						'id:iframe_sandboxing_enabled' => true,
						'id:iframe_sandboxing_exceptions' => 'some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-'.
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
						'x_frame_options' => 'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN '.
								'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN '.
								'SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SAMEORIGIN SA',
						'iframe_sandboxing_enabled' => 1,
						'iframe_sandboxing_exceptions' => 'some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag'.
								'-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag'.
								'-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-som'
					]
				]
			],
			// #5 In period fields maximal valid values in seconds without "s".
			[
				[
					'fields' => [
						// Authorization.
						'Login blocking interval' => '3600'
					],
					'db' => [
						// Authorization.
						'login_block' => '3600'
					]
				]
			],
			// #6 In period fields maximal valid values in minutes.
			[
				[
					'fields' => [
						// Authorization.
						'Login blocking interval' => '60m'
					],
					'db' => [
						// Authorization.
						'login_block' => '60m'
					]
				]
			],
			// #7 Symbol trimming in Login attempts.
			[
				[
					'fields' => [
						'Login attempts' => '3M'
					],
					'db' => [
						'login_attempts' => 3
					]
				]
			],
			// #8 Invalid empty values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Group for discovered hosts' => '',
						'User group for database down message' => '',
						// Authorization.
						'Login attempts' => '',
						'Login blocking interval' => '',
						// Security.
						'id:x_frame_options' => ''
					],
					'details' => [
						'Field "discovery_groupid" is mandatory.',
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// #9 Invalid string values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
			// #10 Invalid special symbol values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
			// #11 Invalid zero values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
			// #12 Invalid zero values in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '0s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #13 In period fields minimal invalid time in seconds without "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '29'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #14 In period fields minimal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '29s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #15 In period fields maximal invalid time in seconds without "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
			// #16 Maximal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '3601s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #17 Maximal invalid time in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '61m'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #18 Maximal invalid time in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '2h'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #19 Maximal invalid time in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '1w'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.'
					]
				]
			],
			// #20 Maximal invalid time in Months (Months not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '1M'
					],
					'details' => [
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// #21 Maximal invalid time in years (years not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization.
						'Login blocking interval' => '1y'
					],
					'details' => [
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// #22 Maximal invalid values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
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
			// #23 Negative values.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						// Authorization. 'Login attempts' field value is automatically converted to "1" .
						'Login attempts' => '-1',
						'Login blocking interval' => '-1'
					],
					'details' => [
						'Incorrect value for field "login_block": a time unit is expected.'
					]
				]
			],
			// #24 Trimming spaces.
			[
				[
					'trim' => true,
					'fields' => [
						'Frontend URL' => '    zabbix.php    ',
						// Authorization.
						'Login attempts' => ' 5',
						'Login blocking interval' => '    32s   ',
						// Security.
						'id:uri_valid_schemes' => '   mailto,tel,ssh   ',
						'id:x_frame_options' => '    SAMEORIGIN    ',
						'id:iframe_sandboxing_exceptions' => '   test   '
					],
					'db' => [
						'url' => 'zabbix.php',
						// Authorization.
						'login_attempts' => 5,
						'login_block' => '32s',
						// Security.
						'uri_valid_schemes' => 'mailto,tel,ssh',
						'x_frame_options' => 'SAMEORIGIN',
						'iframe_sandboxing_exceptions' => 'test'
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
