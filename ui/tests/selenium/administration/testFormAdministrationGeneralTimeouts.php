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


require_once dirname(__FILE__).'/../common/testFormAdministrationGeneral.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralTimeouts extends testFormAdministrationGeneral {

	public $form_selector = 'id:timeouts-form';
	public $config_link = 'zabbix.php?action=timeouts.edit';
	public $default_values = [
		'Zabbix agent' => '3s',
		'Simple check' => '3s',
		'SNMP agent' => '3s',
		'External check' => '3s',
		'Database monitor' => '3s',
		'HTTP agent' => '3s',
		'SSH agent' => '3s',
		'TELNET agent' => '3s',
		'Script' => '3s',
		'Browser' => '60s',
		'Communication' => '3s',
		'Connection' => '3s',
		'Media type test' => '65s',
		'Script execution' => '60s',
		'Item test' => '60s',
		'Scheduled report test' => '60s'
	];

	public $custom_values = [
		'Zabbix agent' => '123s',
		'Simple check' => '123s',
		'SNMP agent' => '123s',
		'External check' => '123s',
		'Database monitor' => '123s',
		'HTTP agent' => '123s',
		'SSH agent' => '123s',
		'TELNET agent' => '123s',
		'Script' => '123s',
		'Browser' => '123s',
		'Communication' => '123s',
		'Connection' => '15s',
		'Media type test' => '123s',
		'Script execution' => '123s',
		'Item test' => '123s',
		'Scheduled report test' => '123s'
	];

	public $db_default_values = [
		'timeout_zabbix_agent' => '3s',
		'timeout_simple_check' => '3s',
		'timeout_snmp_agent' => '3s',
		'timeout_external_check' => '3s',
		'timeout_db_monitor' => '3s',
		'timeout_http_agent' => '3s',
		'timeout_ssh_agent' => '3s',
		'timeout_telnet_agent' => '3s',
		'timeout_script' => '3s',
		'timeout_browser' => '60s',
		'socket_timeout' => '3s',
		'connect_timeout' => '3s',
		'media_type_test_timeout' => '65s',
		'script_timeout' => '60s',
		'item_test_timeout' => '60s',
		'report_test_timeout' => '60s'
	];

	/**
	 * Test for checking timeouts layout.
	 */
	public function testFormAdministrationGeneralTimeouts_Layout() {
		$maxlengths = [
			'Zabbix agent' => 255,
			'Simple check' => 255,
			'SNMP agent' => 255,
			'External check' => 255,
			'Database monitor' => 255,
			'HTTP agent' => 255,
			'SSH agent' => 255,
			'TELNET agent' => 255,
			'Script' => 255,
			'Browser' => 255,
			'Communication' => 32,
			'Connection' => 32,
			'Media type test' => 32,
			'Script execution' => 32,
			'Item test' => 32,
			'Scheduled report test' => 32
		];

		$this->page->login()->open($this->config_link)->waitUntilReady();

		// Check if Header and Title are as expected.
		$this->page->assertHeader('Timeouts');
		$this->page->assertTitle('Configuration of timeouts');
		$form = $this->query($this->form_selector)->waitUntilPresent()->asForm()->one();
		$form->checkValue($this->default_values);

		// Check that timeouts headers exists.
		$this->assertEquals(['Timeouts for item types', 'Network timeouts for UI'],
				$form->query('xpath:.//legend/span')->all()->asText()
		);

		// Check that all fields are marked as mandatory.
		$this->assertEquals(array_keys($this->default_values), $form->getRequiredLabels());

		// Check if buttons are clickable.
		$this->assertTrue($form->query('button', ['Update', 'Reset defaults'])->one()->isClickable());

		// Fields are visible, can be edited and maxlength checked.
		foreach ($maxlengths as $label => $maxlength) {
			$this->assertTrue($form->getField($label)->isClickable());
			$this->assertEquals($maxlength, $form->getField($label)->getAttribute('maxlength'));
		}
	}

	/**
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationGeneralTimeouts_ResetButton() {
		$this->executeResetButtonTest();
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationGeneralTimeouts_SimpleUpdate() {
		$this->executeSimpleUpdate();
	}

	public static function getUpdateValueData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Zabbix agent' => '15s'
					],
					'db' => [
						'timeout_zabbix_agent' => '15s'
					]
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Simple check' => '15s'
					],
					'db' => [
						'timeout_simple_check' => '15s'
					]
				]
			],
			// #2.
			[
				[
					'fields' => [
						'SNMP agent' => '15s'
					],
					'db' => [
						'timeout_snmp_agent' => '15s'
					]
				]
			],
			// #3.
			[
				[
					'fields' => [
						'External check' => '15s'
					],
					'db' => [
						'timeout_external_check' => '15s'
					]
				]
			],
			// #4.
			[
				[
					'fields' => [
						'Database monitor' => '15s'
					],
					'db' => [
						'timeout_db_monitor' => '15s'
					]
				]
			],
			// #5.
			[
				[
					'fields' => [
						'HTTP agent' => '15s'
					],
					'db' => [
						'timeout_http_agent' => '15s'
					]
				]
			],
			// #6.
			[
				[
					'fields' => [
						'SSH agent' => '15s'
					],
					'db' => [
						'timeout_ssh_agent' => '15s'
					]
				]
			],
			// #7.
			[
				[
					'fields' => [
						'TELNET agent' => '15s'
					],
					'db' => [
						'timeout_telnet_agent' => '15s'
					]
				]
			],
			// #8.
			[
				[
					'fields' => [
						'Script' => '15s'
					],
					'db' => [
						'timeout_script' => '15s'
					]
				]
			],
			// #9.
			[
				[
					'fields' => [
						'Browser' => '15s'
					],
					'db' => [
						'timeout_browser' => '15s'
					]
				]
			],
			// #10.
			[
				[
					'fields' => [
						'Communication' => '15s'
					],
					'db' => [
						'socket_timeout' => '15s'
					]
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Connection' => '15s'
					],
					'db' => [
						'connect_timeout' => '15s'
					]
				]
			],
			// #12.
			[
				[
					'fields' => [
						'Media type test' => '15s'
					],
					'db' => [
						'media_type_test_timeout' => '15s'
					]
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Script execution' => '15s'
					],
					'db' => [
						'script_timeout' => '15s'
					]
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Item test' => '15s'
					],
					'db' => [
						'item_test_timeout' => '15s'
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Scheduled report test' => '15s'
					],
					'db' => [
						'report_test_timeout' => '15s'
					]
				]
			],
			// #16 Update values for all item timeouts.
			[
				[
					'fields' => [
						'Zabbix agent' => '33s',
						'Simple check' => '33s',
						'SNMP agent' => '33s',
						'External check' => '33s',
						'Database monitor' => '33s',
						'HTTP agent' => '33s',
						'SSH agent' => '33s',
						'TELNET agent' => '33s',
						'Script' => '33s',
						'Browser' => '33s'
					],
					'db' => [
						'timeout_zabbix_agent' => '33s',
						'timeout_simple_check' => '33s',
						'timeout_snmp_agent' => '33s',
						'timeout_external_check' => '33s',
						'timeout_db_monitor' => '33s',
						'timeout_http_agent' => '33s',
						'timeout_ssh_agent' => '33s',
						'timeout_telnet_agent' => '33s',
						'timeout_script' => '33s',
						'timeout_browser' => '33s'
					]
				]
			],
			// #17 Update values for all network timeouts.
			[
				[
					'fields' => [
						'Communication' => '29s',
						'Connection' => '29s',
						'Media type test' => '29s',
						'Script execution' => '29s',
						'Item test' => '29s',
						'Scheduled report test' => '29s'
					],
					'db' => [
						'socket_timeout' => '29s',
						'connect_timeout' => '29s',
						'media_type_test_timeout' => '29s',
						'script_timeout' => '29s',
						'item_test_timeout' => '29s',
						'report_test_timeout' => '29s'
					]
				]
			],
			// #18 Update values for all timeouts.
			[
				[
					'fields' => [
						'Zabbix agent' => '22s',
						'Simple check' => '22s',
						'SNMP agent' => '22s',
						'External check' => '22s',
						'Database monitor' => '22s',
						'HTTP agent' => '22s',
						'SSH agent' => '22s',
						'TELNET agent' => '22s',
						'Script' => '22s',
						'Browser' => '22s',
						'Communication' => '25s',
						'Connection' => '25s',
						'Media type test' => '25s',
						'Script execution' => '25s',
						'Item test' => '25s',
						'Scheduled report test' => '25s'
					],
					'db' => [
						'timeout_zabbix_agent' => '22s',
						'timeout_simple_check' => '22s',
						'timeout_snmp_agent' => '22s',
						'timeout_external_check' => '22s',
						'timeout_db_monitor' => '22s',
						'timeout_http_agent' => '22s',
						'timeout_ssh_agent' => '22s',
						'timeout_telnet_agent' => '22s',
						'timeout_script' => '22s',
						'timeout_browser' => '22s',
						'socket_timeout' => '25s',
						'connect_timeout' => '25s',
						'media_type_test_timeout' => '25s',
						'script_timeout' => '25s',
						'item_test_timeout' => '25s',
						'report_test_timeout' => '25s'
					]
				]
			],
			// #19 Update values for all timeouts.
			[
				[
					'fields' => [
						'Zabbix agent' => '33',
						'Simple check' => '33',
						'SNMP agent' => '33',
						'External check' => '33',
						'Database monitor' => '33',
						'HTTP agent' => '33',
						'SSH agent' => '33',
						'TELNET agent' => '33',
						'Script' => '33',
						'Browser' => '33',
						'Communication' => '29',
						'Connection' => '29',
						'Media type test' => '29',
						'Script execution' => '29',
						'Item test' => '29',
						'Scheduled report test' => '29'
					],
					'db' => [
						'timeout_zabbix_agent' => '33',
						'timeout_simple_check' => '33',
						'timeout_snmp_agent' => '33',
						'timeout_external_check' => '33',
						'timeout_db_monitor' => '33',
						'timeout_http_agent' => '33',
						'timeout_ssh_agent' => '33',
						'timeout_telnet_agent' => '33',
						'timeout_script' => '33',
						'timeout_browser' => '33',
						'socket_timeout' => '29',
						'connect_timeout' => '29',
						'media_type_test_timeout' => '29',
						'script_timeout' => '29',
						'item_test_timeout' => '29',
						'report_test_timeout' => '29'
					]
				]
			],
			// #20 Update values with macros for all item timeouts.
			[
				[
					'fields' => [
						'Zabbix agent' => '{$MACROS}',
						'Simple check' => '{$MACROS}',
						'SNMP agent' => '{$MACROS}',
						'External check' => '{$MACROS}',
						'Database monitor' => '{$MACROS}',
						'HTTP agent' => '{$MACROS}',
						'SSH agent' => '{$MACROS}',
						'TELNET agent' => '{$MACROS}',
						'Script' => '{$MACROS}',
						'Browser' => '{$MACROS}'
					],
					'db' => [
						'timeout_zabbix_agent' => '{$MACROS}',
						'timeout_simple_check' => '{$MACROS}',
						'timeout_snmp_agent' => '{$MACROS}',
						'timeout_external_check' => '{$MACROS}',
						'timeout_db_monitor' => '{$MACROS}',
						'timeout_http_agent' => '{$MACROS}',
						'timeout_ssh_agent' => '{$MACROS}',
						'timeout_telnet_agent' => '{$MACROS}',
						'timeout_script' => '{$MACROS}',
						'timeout_browser' => '{$MACROS}'
					]
				]
			],
			// #21 Update all available timeouts with minutes type.
			[
				[
					'fields' => [
						'Zabbix agent' => '10m',
						'Simple check' => '10m',
						'SNMP agent' => '10m',
						'External check' => '10m',
						'Database monitor' => '10m',
						'HTTP agent' => '10m',
						'SSH agent' => '10m',
						'TELNET agent' => '10m',
						'Script' => '10m',
						'Browser' => '10m',
						'Communication' => '1m',
						'Media type test' => '1m',
						'Script execution' => '1m',
						'Item test' => '1m',
						'Scheduled report test' => '1m'
					],
					'db' => [
						'timeout_zabbix_agent' => '10m',
						'timeout_simple_check' => '10m',
						'timeout_snmp_agent' => '10m',
						'timeout_external_check' => '10m',
						'timeout_db_monitor' => '10m',
						'timeout_http_agent' => '10m',
						'timeout_ssh_agent' => '10m',
						'timeout_telnet_agent' => '10m',
						'timeout_script' => '10m',
						'timeout_browser' => '10m',
						'socket_timeout' => '1m',
						'media_type_test_timeout' => '1m',
						'script_timeout' => '1m',
						'item_test_timeout' => '1m',
						'report_test_timeout' => '1m'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #31.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #32.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #33.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #34.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #35.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #36.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #37.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #38.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #39.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #40.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_browser": value must be one of 1-600.'
				]
			],
			// #41.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_browser": value must be one of 1-600.'
				]
			],
			// #42 All network timeouts errors at once - less than available.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '0s',
						'Connection' => '0s',
						'Media type test' => '0s',
						'Script execution' => '0s',
						'Item test' => '0s',
						'Scheduled report test' => '0s'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-600.',
						'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// #43 All network timeouts errors at once - higher than available.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '301s',
						'Connection' => '31s',
						'Media type test' => '301s',
						'Script execution' => '301s',
						'Item test' => '601s',
						'Scheduled report test' => '301s'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-600.',
						'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// #44 All fields empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '',
						'Simple check' => '',
						'SNMP agent' => '',
						'External check' => '',
						'Database monitor' => '',
						'HTTP agent' => '',
						'SSH agent' => '',
						'TELNET agent' => '',
						'Script' => '',
						'Browser' => '',
						'Communication' => '',
						'Connection' => '',
						'Media type test' => '',
						'Script execution' => '',
						'Item test' => '',
						'Scheduled report test' => ''
					],
					'details' => [
						'Incorrect value for field "timeout_zabbix_agent": cannot be empty.',
						'Incorrect value for field "timeout_simple_check": cannot be empty.',
						'Incorrect value for field "timeout_snmp_agent": cannot be empty.',
						'Incorrect value for field "timeout_external_check": cannot be empty.',
						'Incorrect value for field "timeout_db_monitor": cannot be empty.',
						'Incorrect value for field "timeout_http_agent": cannot be empty.',
						'Incorrect value for field "timeout_ssh_agent": cannot be empty.',
						'Incorrect value for field "timeout_telnet_agent": cannot be empty.',
						'Incorrect value for field "timeout_script": cannot be empty.',
						'Incorrect value for field "timeout_browser": cannot be empty.',
						'Incorrect value for field "socket_timeout": cannot be empty.',
						'Incorrect value for field "connect_timeout": cannot be empty.',
						'Incorrect value for field "media_type_test_timeout": cannot be empty.',
						'Incorrect value for field "script_timeout": cannot be empty.',
						'Incorrect value for field "item_test_timeout": cannot be empty.',
						'Incorrect value for field "report_test_timeout": cannot be empty.'
					]
				]
			],
			// #45.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a time unit is expected.'
				]
			],
			// #46.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a time unit is expected.'
				]
			],
			// #47.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a time unit is expected.'
				]
			],
			// #48.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a time unit is expected.'
				]
			],
			// #49.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a time unit is expected.'
				]
			],
			// #50.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a time unit is expected.'
				]
			],
			// #51.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a time unit is expected.'
				]
			],
			// #52.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a time unit is expected.'
				]
			],
			// #53.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_script": a time unit is expected.'
				]
			],
			// #54.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_browser": a time unit is expected.'
				]
			],
			// #55 All network timeouts time unit errors at once.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => 'test',
						'Connection' => 'test',
						'Media type test' => 'test',
						'Script execution' => 'test',
						'Item test' => 'test',
						'Scheduled report test' => 'test'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #56 Check 1h time validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '1h',
						'Connection' => '1h',
						'Media type test' => '1h',
						'Script execution' => '1h',
						'Item test' => '1h',
						'Scheduled report test' => '1h'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-600.',
						'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// #57 Check 1d time validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '1d',
						'Connection' => '1d',
						'Media type test' => '1d',
						'Script execution' => '1d',
						'Item test' => '1d',
						'Scheduled report test' => '1d'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-600.',
						'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// #58 All network timeouts errors for 1w validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '1w',
						'Connection' => '1w',
						'Media type test' => '1w',
						'Script execution' => '1w',
						'Item test' => '1w',
						'Scheduled report test' => '1w'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-600.',
						'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// #59 All network timeouts errors for 1M validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '1M',
						'Connection' => '1M',
						'Media type test' => '1M',
						'Script execution' => '1M',
						'Item test' => '1M',
						'Scheduled report test' => '1M'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #60 All network timeouts errors for 1y validation.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '1y',
						'Connection' => '1y',
						'Media type test' => '1y',
						'Script execution' => '1y',
						'Item test' => '1y',
						'Scheduled report test' => '1y'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #61.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #62.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #63.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a time unit is expected.'
				]
			],
			// #64.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #65.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #66.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a time unit is expected.'
				]
			],
			// #67.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #68.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #69.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a time unit is expected.'
				]
			],
			// #70.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #71.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #72.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a time unit is expected.'
				]
			],
			// #73.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #74.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #75.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a time unit is expected.'
				]
			],
			// #76.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #77.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #78.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a time unit is expected.'
				]
			],
			// #79.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #80.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #81.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a time unit is expected.'
				]
			],
			// #82.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #83.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #84.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a time unit is expected.'
				]
			],
			// #85.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #86.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #87.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_script": a time unit is expected.'
				]
			],
			// #88.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '1d'
					],
					'details' => 'Invalid parameter "/timeout_browser": value must be one of 1-600.'
				]
			],
			// #89.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '1w'
					],
					'details' => 'Invalid parameter "/timeout_browser": value must be one of 1-600.'
				]
			],
			// #90.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '1M'
					],
					'details' => 'Invalid parameter "/timeout_browser": a time unit is expected.'
				]
			],
			// #91.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a number is too large.'
				]
			],
			// #92.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a number is too large.'
				]
			],
			// #93.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a number is too large.'
				]
			],
			// #94.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a number is too large.'
				]
			],
			// #95.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a number is too large.'
				]
			],
			// #96.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a number is too large.'
				]
			],
			// #97.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a number is too large.'
				]
			],
			// #98.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a number is too large.'
				]
			],
			// #99.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_script": a number is too large.'
				]
			],
			// #100.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '123456789123456789'
					],
					'details' => 'Invalid parameter "/timeout_browser": a number is too large.'
				]
			],
			// #101.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a time unit is expected.'
				]
			],
			// #102.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a time unit is expected.'
				]
			],
			// #103.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a time unit is expected.'
				]
			],
			// #104.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a time unit is expected.'
				]
			],
			// #105.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a time unit is expected.'
				]
			],
			// #106.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a time unit is expected.'
				]
			],
			// #107.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a time unit is expected.'
				]
			],
			// #108.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a time unit is expected.'
				]
			],
			// #109.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a time unit is expected.'
				]
			],
			// #110.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a time unit is expected.'
				]
			],
			// #111.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a time unit is expected.'
				]
			],
			// #112.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a time unit is expected.'
				]
			],
			// #113.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a time unit is expected.'
				]
			],
			// #114.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a time unit is expected.'
				]
			],
			// #115.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a time unit is expected.'
				]
			],
			// #116.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a time unit is expected.'
				]
			],
			// #117.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_script": a time unit is expected.'
				]
			],
			// #118.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_script": a time unit is expected.'
				]
			],
			// #119.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '{HOST.HOST}'
					],
					'details' => 'Invalid parameter "/timeout_browser": a time unit is expected.'
				]
			],
			// #120.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Browser' => '{#LDD_MACROS}'
					],
					'details' => 'Invalid parameter "/timeout_browser": a time unit is expected.'
				]
			],
			// #121 All network timeouts errors with LLD macros.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '{#LDD_MACROS}',
						'Connection' => '{#LDD_MACROS}',
						'Media type test' => '{#LDD_MACROS}',
						'Script execution' => '{#LDD_MACROS}',
						'Item test' => '{#LDD_MACROS}',
						'Scheduled report test' => '{#LDD_MACROS}'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #122 All network timeouts errors with global macros.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '{HOST.HOST}',
						'Connection' => '{HOST.HOST}',
						'Media type test' => '{HOST.HOST}',
						'Script execution' => '{HOST.HOST}',
						'Item test' => '{HOST.HOST}',
						'Scheduled report test' => '{HOST.HOST}'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #123 All network timeouts errors with user macros.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '{$MACROS}',
						'Connection' => '{$MACROS}',
						'Media type test' => '{$MACROS}',
						'Script execution' => '{$MACROS}',
						'Item test' => '{$MACROS}',
						'Scheduled report test' => '{$MACROS}'
					],
					'details' => [
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.',
						'Incorrect value for field "report_test_timeout": a time unit is expected.'
					]
				]
			],
			// #124.
			[
				[
					'trim' => true,
					'fields' => [
						'Zabbix agent' => '   15s   ',
						'Simple check' => '   15s   ',
						'SNMP agent' => '   15s   ',
						'External check' => '   15s   ',
						'Database monitor' => '   15s   ',
						'HTTP agent' => '   15s   ',
						'SSH agent' => '   15s   ',
						'TELNET agent' => '   15s   ',
						'Script' => '   15s   ',
						'Browser' => '   15s   ',
						'Communication' => '   15s   ',
						'Connection' => '   15s   ',
						'Media type test' => '   15s   ',
						'Script execution' => '   15s   ',
						'Item test' => '   15s   ',
						'Scheduled report test' => '   15s   '
					],
					'db' => [
						'timeout_zabbix_agent' => '15s',
						'timeout_simple_check' => '15s',
						'timeout_snmp_agent' => '15s',
						'timeout_external_check' => '15s',
						'timeout_db_monitor' => '15s',
						'timeout_http_agent' => '15s',
						'timeout_ssh_agent' => '15s',
						'timeout_telnet_agent' => '15s',
						'timeout_script' => '15s',
						'timeout_browser' => '15s',
						'socket_timeout' => '15s',
						'connect_timeout' => '15s',
						'media_type_test_timeout' => '15s',
						'script_timeout' => '15s',
						'item_test_timeout' => '15s',
						'report_test_timeout' => '15s'
					]
				]
			]
		];
	}

	/**
	 * Update Timeouts values.
	 *
	 * @dataProvider getUpdateValueData
	 */
	public function testFormAdministrationGeneralTimeouts_UpdateParameters($data) {
		$this->executeCheckForm($data, false, true);
	}
}
