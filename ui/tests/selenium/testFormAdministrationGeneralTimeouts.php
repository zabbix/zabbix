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
class testFormAdministrationGeneralTimeouts extends testFormAdministrationGeneral {

	public $form_selector = 'id:timeouts';
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
		'Communication' => '123s',
		'Connection' => '15s',
		'Media type test' => '123s',
		'Script execution' => '123s',
		'Item test' => '123s',
		'Scheduled report test' => '123s'
	];

	/**
	 * Test for checking timeouts layout.
	 */
	public function testFormAdministrationGeneralTimeouts_Layout() {
		$this->page->login()->open($this->config_link)->waitUntilReady();
		$form = $this->query($this->form_selector)->waitUntilPresent()->asForm()->one();
		$form->checkValue($this->default_values);

		// Count timeouts headers. Right now there is only 2.
		$count = $form->query('xpath:.//legend/span')->all()->count();

		// Get timeouts headers as text and save as array.
		$timeouts_header = [];
		for ($i = 1; $i <= $count; $i++) {
			$timeouts_header[] = $form->query('xpath:(.//legend/span)['.$i.']')->one()->getText();
		}

		// Compare received headers with provided.
		$this->assertEquals(['Timeouts for item types', 'Network timeouts for UI'], $timeouts_header);

		// Check that all fields are marked as mandatory.
		foreach ($this->default_values as $timeout_label => $value) {
			$this->assertEquals('form-label-asterisk', $form->getLabel($timeout_label)->getAttribute('class'));
		}

		// Check if buttons are clickable.
		$this->assertTrue($form->query('button', ['Update', 'Reset defaults'])->one()->isClickable());

		// Check if Header and Title are as expected.
		$this->page->assertHeader('Timeouts');
		$this->page->assertTitle('Configuration of timeouts');
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
			// #0
			[
				[
					'fields' => [
						'Zabbix agent' => '15s'
					],
					'db_check' => [
						'timeout_zabbix_agent' => '15s'
					]
				]
			],
			// #1
			[
				[
					'fields' => [
						'Simple check' => '15s'
					],
					'db_check' => [
						'timeout_simple_check' => '15s'
					]
				]
			],
			// #2
			[
				[
					'fields' => [
						'SNMP agent' => '15s'
					],
					'db_check' => [
						'timeout_snmp_agent' => '15s'
					]
				]
			],
			// #3
			[
				[
					'fields' => [
						'External check' => '15s'
					],
					'db_check' => [
						'timeout_external_check' => '15s'
					]
				]
			],
			// #4
			[
				[
					'fields' => [
						'Database monitor' => '15s'
					],
					'db_check' => [
						'timeout_db_monitor' => '15s'
					]
				]
			],
			// #5
			[
				[
					'fields' => [
						'HTTP agent' => '15s'
					],
					'db_check' => [
						'timeout_http_agent' => '15s'
					]
				]
			],
			// #6
			[
				[
					'fields' => [
						'SSH agent' => '15s'
					],
					'db_check' => [
						'timeout_ssh_agent' => '15s'
					]
				]
			],
			// #7
			[
				[
					'fields' => [
						'TELNET agent' => '15s'
					],
					'db_check' => [
						'timeout_telnet_agent' => '15s'
					]
				]
			],
			// #8
			[
				[
					'fields' => [
						'Script' => '15s'
					],
					'db_check' => [
						'timeout_script' => '15s'
					]
				]
			],
			// #9
			[
				[
					'fields' => [
						'Communication' => '15s'
					],
					'db_check' => [
						'socket_timeout' => '15s'
					]
				]
			],
			// #10
			[
				[
					'fields' => [
						'Connection' => '15s'
					],
					'db_check' => [
						'connect_timeout' => '15s'
					]
				]
			],
			// #11
			[
				[
					'fields' => [
						'Media type test' => '15s'
					],
					'db_check' => [
						'media_type_test_timeout' => '15s'
					]
				]
			],
			// #12
			[
				[
					'fields' => [
						'Script execution' => '15s'
					],
					'db_check' => [
						'script_timeout' => '15s'
					]
				]
			],
			// #13
			[
				[
					'fields' => [
						'Item test' => '15s'
					],
					'db_check' => [
						'item_test_timeout' => '15s'
					]
				]
			],
			// #14
			[
				[
					'fields' => [
						'Scheduled report test' => '15s'
					],
					'db_check' => [
						'report_test_timeout' => '15s'
					]
				]
			],
			// #15 Update values for all item timeouts.
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
						'Script' => '33s'
					],
					'db_check' => [
						'timeout_zabbix_agent' => '33s',
						'timeout_simple_check' => '33s',
						'timeout_snmp_agent' => '33s',
						'timeout_external_check' => '33s',
						'timeout_db_monitor' => '33s',
						'timeout_http_agent' => '33s',
						'timeout_ssh_agent' => '33s',
						'timeout_telnet_agent' => '33s',
						'timeout_script' => '33s'
					]
				]
			],
			// #16 Update values for all network timeouts.
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
					'db_check' => [
						'socket_timeout' => '29s',
						'connect_timeout' => '29s',
						'media_type_test_timeout' => '29s',
						'script_timeout' => '29s',
						'item_test_timeout' => '29s',
						'report_test_timeout' => '29s'
					]
				]
			],
			// #17 Update values for all timeouts.
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
						'Communication' => '29s',
						'Connection' => '29s',
						'Media type test' => '29s',
						'Script execution' => '29s',
						'Item test' => '29s',
						'Scheduled report test' => '29s'
					],
					'db_check' => [
						'timeout_zabbix_agent' => '33s',
						'timeout_simple_check' => '33s',
						'timeout_snmp_agent' => '33s',
						'timeout_external_check' => '33s',
						'timeout_db_monitor' => '33s',
						'timeout_http_agent' => '33s',
						'timeout_ssh_agent' => '33s',
						'timeout_telnet_agent' => '33s',
						'timeout_script' => '33s',
						'socket_timeout' => '29s',
						'connect_timeout' => '29s',
						'media_type_test_timeout' => '29s',
						'script_timeout' => '29s',
						'item_test_timeout' => '29s',
						'report_test_timeout' => '29s'
					]
				]
			],
			// #18 Update values with macros for all item timeouts.
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
						'Script' => '{$MACROS}'
					],
					'db_check' => [
						'timeout_zabbix_agent' => '{$MACROS}',
						'timeout_simple_check' => '{$MACROS}',
						'timeout_snmp_agent' => '{$MACROS}',
						'timeout_external_check' => '{$MACROS}',
						'timeout_db_monitor' => '{$MACROS}',
						'timeout_http_agent' => '{$MACROS}',
						'timeout_ssh_agent' => '{$MACROS}',
						'timeout_telnet_agent' => '{$MACROS}',
						'timeout_script' => '{$MACROS}'
					]
				]
			],
			// #19 Update all available timeouts with minutes type.
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
						'Communication' => '1m',
						'Media type test' => '1m',
						'Script execution' => '1m',
						'Item test' => '1m',
						'Scheduled report test' => '1m'
					],
					'db_check' => [
						'timeout_zabbix_agent' => '10m',
						'timeout_simple_check' => '10m',
						'timeout_snmp_agent' => '10m',
						'timeout_external_check' => '10m',
						'timeout_db_monitor' => '10m',
						'timeout_http_agent' => '10m',
						'timeout_ssh_agent' => '10m',
						'timeout_telnet_agent' => '10m',
						'timeout_script' => '10m',
						'socket_timeout' => '1m',
						'media_type_test_timeout' => '1m',
						'script_timeout' => '1m',
						'item_test_timeout' => '1m',
						'report_test_timeout' => '1m'
					]
				]
			],
			// #20
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #21
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": value must be one of 1-600.'
				]
			],
			// #22
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #23
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": value must be one of 1-600.'
				]
			],
			// #24
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #25
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": value must be one of 1-600.'
				]
			],
			// #26
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #27
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_external_check": value must be one of 1-600.'
				]
			],
			// #28
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #29
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": value must be one of 1-600.'
				]
			],
			// #30
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #31
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": value must be one of 1-600.'
				]
			],
			// #32
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #33
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": value must be one of 1-600.'
				]
			],
			// #34
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #35
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": value must be one of 1-600.'
				]
			],
			// #36
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '0s'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #37
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => '601s'
					],
					'details' => 'Invalid parameter "/timeout_script": value must be one of 1-600.'
				]
			],
			// #38
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '0s'
					],
					'details' => 'Incorrect value for field "socket_timeout": value must be one of 1-300.'
				]
			],
			// #39
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => '301s'
					],
					'details' => 'Incorrect value for field "socket_timeout": value must be one of 1-300.'
				]
			],
			// #40
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Connection' => '0s'
					],
					'details' => 'Incorrect value for field "connect_timeout": value must be one of 1-30.'
				]
			],
			// #41
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Connection' => '31s'
					],
					'details' => 'Incorrect value for field "connect_timeout": value must be one of 1-30.'
				]
			],
			// #42
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Media type test' => '0s'
					],
					'details' => 'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.'
				]
			],
			// #43
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Media type test' => '301s'
					],
					'details' => 'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.'
				]
			],
			// #44
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script execution' => '0s'
					],
					'details' => 'Incorrect value for field "script_timeout": value must be one of 1-300.'
				]
			],
			// #45
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script execution' => '301s'
					],
					'details' => 'Incorrect value for field "script_timeout": value must be one of 1-300.'
				]
			],
			// #46
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item test' => '0s'
					],
					'details' => 'Incorrect value for field "item_test_timeout": value must be one of 1-600.'
				]
			],
			// #47
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item test' => '601s'
					],
					'details' => 'Incorrect value for field "item_test_timeout": value must be one of 1-600.'
				]
			],
			// #48
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Scheduled report test' => '0s'
					],
					'details' => 'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
				]
			],
			// #49
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Scheduled report test' => '301s'
					],
					'details' => 'Incorrect value for field "report_test_timeout": value must be one of 1-300.'
				]
			],
			// #50 All network timeouts errors at once.
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
			// #51 All fields empty.
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
						'Incorrect value for field "socket_timeout": cannot be empty.',
						'Incorrect value for field "connect_timeout": cannot be empty.',
						'Incorrect value for field "media_type_test_timeout": cannot be empty.',
						'Incorrect value for field "script_timeout": cannot be empty.',
						'Incorrect value for field "item_test_timeout": cannot be empty.',
						'Incorrect value for field "report_test_timeout": cannot be empty.'
					]
				]
			],
			// #52
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Zabbix agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_zabbix_agent": a time unit is expected.'
				]
			],
			// #53
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Simple check' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_simple_check": a time unit is expected.'
				]
			],
			// #54
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SNMP agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_snmp_agent": a time unit is expected.'
				]
			],
			// #55
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'External check' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_external_check": a time unit is expected.'
				]
			],
			// #56
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Database monitor' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_db_monitor": a time unit is expected.'
				]
			],
			// #57
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'HTTP agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_http_agent": a time unit is expected.'
				]
			],
			// #58
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'SSH agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_ssh_agent": a time unit is expected.'
				]
			],
			// #59
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'TELNET agent' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_telnet_agent": a time unit is expected.'
				]
			],
			// #60
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script' => 'test'
					],
					'details' => 'Invalid parameter "/timeout_script": a time unit is expected.'
				]
			],
			// #61
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Communication' => 'test'
					],
					'details' => 'Incorrect value for field "socket_timeout": a time unit is expected.'
				]
			],
			// #62
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Connection' => 'test'
					],
					'details' => 'Incorrect value for field "connect_timeout": a time unit is expected.'
				]
			],
			// #63
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Media type test' => 'test'
					],
					'details' => 'Incorrect value for field "media_type_test_timeout": a time unit is expected.'
				]
			],
			// #64
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Script execution' => 'test'
					],
					'details' => 'Incorrect value for field "script_timeout": a time unit is expected.'
				]
			],
			// #65
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item test' => 'test'
					],
					'details' => 'Incorrect value for field "item_test_timeout": a time unit is expected.'
				]
			],
			// #66
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Scheduled report test' => 'test'
					],
					'details' => 'Incorrect value for field "report_test_timeout": a time unit is expected.'
				]
			],
			// #67 All network timeouts time unit errors at once.
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
			// TODO: uncomment after ZBX-23636. Fields should be trimmed.
//			[
//				[
//					'fields' => [
//						'Zabbix agent' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_zabbix_agent' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Simple check' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_simple_check' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'SNMP agent' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_snmp_agent' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'External check' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_external_check' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Database monitor' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_db_monitor' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'HTTP agent' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_http_agent' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'SSH agent' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_ssh_agent' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'TELNET agent' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_telnet_agent' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Script' => '   15s   '
//					],
//					'db_check' => [
//						'timeout_script' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Communication' => '   15s   '
//					],
//					'db_check' => [
//						'socket_timeout' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Connection' => '   15s   '
//					],
//					'db_check' => [
//						'connect_timeout' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Media type test' => '   15s   '
//					],
//					'db_check' => [
//						'media_type_test_timeout' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Script execution' => '   15s   '
//					],
//					'db_check' => [
//						'script_timeout' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Item test' => '   15s   '
//					],
//					'db_check' => [
//						'item_test_timeout' => '15s'
//					]
//				]
//			],
//			[
//				[
//					'fields' => [
//						'Scheduled report test' => '   15s   '
//					],
//					'db_check' => [
//						'report_test_timeout' => '15s'
//					]
//				]
//			]
		];
	}

	/**
	 * Update Timeouts values.
	 *
	 * @dataProvider getUpdateValueData
	 */
	public function testFormAdministrationGeneralTimeouts_UpdateParameters($data) {
		$this->executeUpdate($data);
	}
}
