<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralOtherParams extends CWebTest {

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	private $default = [
		'Refresh unsupported items' => '10m',
		'Group for discovered hosts' => 'Empty group',
		'Default host inventory mode' => 'Disabled',
		'User group for database down message' => 'Zabbix administrators',
		'Log unmatched SNMP traps' => true,
		// Authorization.
		'Login attempts' => 5,
		'Login blocking interval' => '30s',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'X-Frame-Options HTTP header' => 'SAMEORIGIN',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => '',
		// Communication with Zabbix server.
		'Network timeout' => '3s',
		'Connection timeout' => '3s',
		'Network timeout for media type test' => '65s',
		'Network timeout for script execution' => '60s',
		'Network timeout for item test' => '60s'
	];

	private $db_default = [
		'refresh_unsupported' => '10m',
		'discovery_groupid' => 50006,
		'default_inventory_mode' => -1,
		'alert_usrgrpid' => 7,
		'snmptrap_logging' => 1,
		// Authorization.
		'login_attempts' => 5,
		'login_block' => '30s',
		// Security.
		'validate_uri_schemes' => 1,
		'uri_valid_schemes' => 'http,https,ftp,file,mailto,tel,ssh',
		'x_frame_options' => 'SAMEORIGIN',
		'iframe_sandboxing_enabled' => 1,
		'iframe_sandboxing_exceptions' => '',
		// Communication with Zabbix server.
		'socket_timeout' => '3s',
		'connect_timeout' => '3s',
		'media_type_test_timeout' => '65s',
		'script_timeout' => '60s',
		'item_test_timeout' => '60s'
	];

	private $custom = [
		'Refresh unsupported items' => '99m',
		'Group for discovered hosts' => 'Hypervisors',
		'Default host inventory mode' => 'Automatic',
		'User group for database down message' => 'Test timezone',
		'Log unmatched SNMP traps' => false,
		// Authorization.
		'Login attempts' => 13,
		'Login blocking interval' => '52s',
		// Security.
		'Validate URI schemes' => true,
		'Valid URI schemes' => 'custom_scheme',
		'X-Frame-Options HTTP header' => 'SOME_NEW_VALUE',
		'Use iframe sandboxing' => true,
		'Iframe sandboxing exceptions' => 'some-new-flag',
		// Communication with Zabbix server.
		'Network timeout' => '7s',
		'Connection timeout' => '4s',
		'Network timeout for media type test' => '91s',
		'Network timeout for script execution' => '46s',
		'Network timeout for item test' => '76s'
	];

	/**
	 * Test for checking form layout.
	 */
	public function testFormAdministrationGeneralOtherParams_CheckLayout() {
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$this->assertPageTitle('Other configuration parameters');
		$this->assertPageHeader('Other configuration parameters');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();

		foreach (['Authorization', 'Security', 'Communication with Zabbix server'] as $header) {
			$this->assertTrue($this->query('xpath://h4[text()="'.$header.'"]')->one()->isVisible());
		}

		$limits = [
			'refresh_unsupported' => 32,
			'login_attempts' => 2,
			'login_block' => 32,
			'uri_valid_schemes' => 255,
			'x_frame_options' => 255,
			'iframe_sandboxing_exceptions' => 255,
			'socket_timeout' => 32,
			'connect_timeout' => 32,
			'media_type_test_timeout' => 32,
			'script_timeout' => 32,
			'item_test_timeout' => 32
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
				$this->assertTrue($this->query('id', $checkbox)->one()->isEnabled());
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
	 * Test for checking 'Reset defaults' button.
	 */
	public function testFormAdministrationGeneralOtherParams_ResetButton() {
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		// Reset form in case of some previous scenario.
		$this->resetConfiguration($form, $this->default, 'Reset defaults');
		$default_sql = CDBHelper::getRow('SELECT * FROM config');

		// Reset form after customly filled data and check that values are reset to default or reset is cancelled.
		foreach (['Reset defaults', 'Cancel'] as $action) {
			// Fill form with custom data.
			$form->fill($this->custom);
			$form->submit();
			$this->assertMessage(TEST_GOOD, 'Configuration updated');
			$custom_sql = CDBHelper::getRow('SELECT * FROM config');
			// Check custom data in form.
			$this->page->refresh();
			$this->page->waitUntilReady();
			$form->invalidate();
			$form->checkValue($this->custom);
			$this->resetConfiguration($form, $this->default, $action, $this->custom);
			$sql = ($action === 'Reset defaults') ? $default_sql : $custom_sql;
			$this->assertEquals($sql, CDBHelper::getRow('SELECT * FROM config'));
		}
	}

	/**
	 * Test for checking form update without changing any data.
	 */
	public function testFormAdministrationGeneralOtherParams_SimpleUpdate() {
		$sql = CDBHelper::getRow('SELECT * FROM config ORDER BY configid');
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		$values = $form->getFields()->asValues();
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->page->refresh();
		$this->page->waitUntilReady();
		$form->invalidate();
		// Check that DBdata is not changed.
		$this->assertEquals($sql, CDBHelper::getRow('SELECT * FROM config ORDER BY configid'));
		// Check that Frontend form is not changed.
		$this->assertEquals($values, $form->getFields()->asValues());
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
						'Refresh unsupported items' => '0s',
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
						'Use iframe sandboxing' => false,
						// Communication with Zabbix server.
						'Network timeout' => '1s',
						'Connection timeout' => '1s',
						'Network timeout for media type test' => '1s',
						'Network timeout for script execution' => '1s',
						'Network timeout for item test' => '1s'
					],
					'db' => [
						'refresh_unsupported' => '0s',
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
						'iframe_sandboxing_enabled' => 0,
						// Communication with Zabbix server.
						'socket_timeout' => '1s',
						'connect_timeout' => '1s',
						'media_type_test_timeout' => '1s',
						'script_timeout' => '1s',
						'item_test_timeout' => '1s'
					]
				]
			],
			// Minimal valid values. In period fields minimal valid time in seconds without 's'.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '0',
						'Default host inventory mode' => 'Automatic',
						'Log unmatched SNMP traps' => true,
						// Authorization.
						'Login blocking interval' => '30',
						// Security.
						'Validate URI schemes' => true,
						'Valid URI schemes' => '',
						'Use iframe sandboxing' => true,
						'Iframe sandboxing exceptions' => '',
						// Communication with Zabbix server.
						'Network timeout' => '1',
						'Connection timeout' => '1',
						'Network timeout for media type test' => '1',
						'Network timeout for script execution' => '1',
						'Network timeout for item test' => '1'
					],
					'db' => [
						'refresh_unsupported' => '0',
						'default_inventory_mode' => 1,
						'snmptrap_logging' => 1,
						// Authorization.
						'login_block' => '30',
						// Security.
						'validate_uri_schemes' => 1,
						'uri_valid_schemes' => '',
						'iframe_sandboxing_enabled' => 1,
						'iframe_sandboxing_exceptions' => '',
						// Communication with Zabbix server.
						'socket_timeout' => '1',
						'connect_timeout' => '1',
						'media_type_test_timeout' => '1',
						'script_timeout' => '1',
						'item_test_timeout' => '1'
					]
				]
			],
			// In period fields minimal valid time in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '0m',
						// Authorization.
						'Login blocking interval' => '1m',
						// Communication with Zabbix server.
						'Network timeout' => '1m',
						'Network timeout for media type test' => '1m',
						'Network timeout for script execution' => '1m',
						'Network timeout for item test' => '1m'
					],
					'db' => [
						'refresh_unsupported' => '0m',
						// Authorization.
						'login_block' => '1m',
						// Communication with Zabbix server.
						'socket_timeout' => '1m',
						'media_type_test_timeout' => '1m',
						'script_timeout' => '1m',
						'item_test_timeout' => '1m'
					]
				]
			],
			// In period fields minimal valid time in hours.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '0h',
						// Authorization.
						'Login blocking interval' => '1h',
					],
					'db' => [
						'refresh_unsupported' => '0h',
						// Authorization.
						'login_block' => '1h'
					]
				]
			],
			// In period fields minimal valid time in days.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '0d'
					],
					'db' => [
						'refresh_unsupported' => '0d'
					]
				]
			],
			// In period fields minimal valid time in weeks.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '0w'
					],
					'db' => [
						'refresh_unsupported' => '0w'
					]
				]
			],
			// Maximal valid values in seconds with "s".
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '86400s',
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
								'flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-som',
						// Communication with Zabbix server.
						'Network timeout' => '300s',
						'Connection timeout' => '30s',
						'Network timeout for media type test' => '300s',
						'Network timeout for script execution' => '300s',
						'Network timeout for item test' => '300s'
					],
					'db' => [
						'refresh_unsupported' => '86400s',
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
								'-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-some-new-flag-som',
						// Communication with Zabbix server.
						'socket_timeout' => '300s',
						'connect_timeout' => '30s',
						'media_type_test_timeout' => '300s',
						'script_timeout' => '300s',
						'item_test_timeout' => '300s'
					]
				]
			],
			// In period fields maximal valid values in seconds without "s".
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '86400',
						// Authorization.
						'Login blocking interval' => '3600',
						// Communication with Zabbix server.
						'Network timeout' => '300',
						'Connection timeout' => '30',
						'Network timeout for media type test' => '300',
						'Network timeout for script execution' => '300',
						'Network timeout for item test' => '300'
					],
					'db' => [
						'refresh_unsupported' => '86400',
						// Authorization.
						'login_block' => '3600',
						// Communication with Zabbix server.
						'socket_timeout' => '300',
						'connect_timeout' => '30',
						'media_type_test_timeout' => '300',
						'script_timeout' => '300',
						'item_test_timeout' => '300'
					]
				]
			],
			// In period fields maximal valid values in minutes.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '1440m',
						// Authorization.
						'Login blocking interval' => '60m',
						// Communication with Zabbix server.
						'Network timeout' => '5m',
						'Network timeout for media type test' => '5m',
						'Network timeout for script execution' => '5m',
						'Network timeout for item test' => '5m'
					],
					'db' => [
						'refresh_unsupported' => '1440m',
						// Authorization.
						'login_block' => '60m',
						// Communication with Zabbix server.
						'socket_timeout' => '5m',
						'media_type_test_timeout' => '5m',
						'script_timeout' => '5m',
						'item_test_timeout' => '5m'
					]
				]
			],
			// In period fields maximal valid values in hours.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '24h'
					],
					'db' => [
						'refresh_unsupported' => '24h'
					]
				]
			],
			// In period fields maximal valid values in days.
			[
				[
					'expected' => TEST_GOOD,
					'fields' =>  [
						'Refresh unsupported items' => '24h'
					],
					'db' => [
						'refresh_unsupported' => '24h'
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
			// Ivalid empty values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '',
						'Group for discovered hosts' => '',
						'User group for database down message' => '',
						// Authorization.
						'Login attempts' => '',
						'Login blocking interval' => '',
						// Security.
						'X-Frame-Options HTTP header' => '',
						// Communication with Zabbix server.
						'Network timeout' => '',
						'Connection timeout' => '',
						'Network timeout for media type test' => '',
						'Network timeout for script execution' => '',
						'Network timeout for item test' => ''
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "x_frame_options": cannot be empty.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			],
			// Invalid sting values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => 'text',
						// Authorization.
						'Login attempts' => 'text',
						'Login blocking interval' => 'text',
						// Communication with Zabbix server.
						'Network timeout' => 'text',
						'Connection timeout' => 'text',
						'Network timeout for media type test' => 'text',
						'Network timeout for script execution' => 'text',
						'Network timeout for item test' => 'text'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			],
			// Invalid special symbol values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '!@#$%^&*()_+',
						// Authorization.
						'Login attempts' => '!@#$%^&*()_+',
						'Login blocking interval' => '!@#$%^&*()_+',
						// Communication with Zabbix server.
						'Network timeout' => '!@#$%^&*()_+',
						'Connection timeout' => '!@#$%^&*()_+',
						'Network timeout for media type test' => '!@#$%^&*()_+',
						'Network timeout for script execution' => '!@#$%^&*()_+',
						'Network timeout for item test' => '!@#$%^&*()_+'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			],
			// Ivalid zero values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login attempts' => 0,
						'Login blocking interval' => 0,
						// Communication with Zabbix server.
						'Network timeout' => 0,
						'Connection timeout' => 0,
						'Network timeout for media type test' => 0,
						'Network timeout for script execution' => 0,
						'Network timeout for item test' => 0
					],
					'details' => [
						'Incorrect value for field "login_attempts": value must be no less than "1".',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Ivalid zero values in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						// Authorization.
						'Login blocking interval' => '0s',
						// Communication with Zabbix server.
						'Network timeout' => '0s',
						'Connection timeout' => '0s',
						'Network timeout for media type test' => '0s',
						'Network timeout for script execution' => '0s',
						'Network timeout for item test' => '0s'
					],
					'details' => [
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
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
						'Refresh unsupported items' => '86401',
						// Authorization.
						'Login attempts' => 33,
						'Login blocking interval' => '3601',
						// Communication with Zabbix server.
						'Network timeout' => '301',
						'Connection timeout' => '31',
						'Network timeout for media type test' => '301',
						'Network timeout for script execution' => '301',
						'Network timeout for item test' => '301'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_attempts": value must be no greater than "32".',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Maximal invalid time in seconds with "s".
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '86401s',
						// Authorization.
						'Login blocking interval' => '3601s',
						// Communication with Zabbix server.
						'Network timeout' => '301s',
						'Connection timeout' => '31s',
						'Network timeout for media type test' => '301s',
						'Network timeout for script execution' => '301s',
						'Network timeout for item test' => '301s'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Maximal invalid time in minutes.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '1441m',
						// Authorization.
						'Login blocking interval' => '61m',
						// Communication with Zabbix server.
						'Network timeout' => '6m',
						'Connection timeout' => '1m',
						'Network timeout for media type test' => '6m',
						'Network timeout for script execution' => '6m',
						'Network timeout for item test' => '6m'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Maximal invalid time in hours.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '25h',
						// Authorization.
						'Login blocking interval' => '2h',
						// Communication with Zabbix server.
						'Network timeout' => '1h',
						'Connection timeout' => '1h',
						'Network timeout for media type test' => '1h',
						'Network timeout for script execution' => '1h',
						'Network timeout for item test' => '1h'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Maximal invalid time in weeks.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '1w',
						// Authorization.
						'Login blocking interval' => '1w',
						// Communication with Zabbix server.
						'Network timeout' => '1w',
						'Connection timeout' => '1w',
						'Network timeout for media type test' => '1w',
						'Network timeout for script execution' => '1w',
						'Network timeout for item test' => '1w'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Maximal invalid time in Months (Months not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '1M',
						// Authorization.
						'Login blocking interval' => '1M',
						// Communication with Zabbix server.
						'Network timeout' => '1M',
						'Connection timeout' => '1M',
						'Network timeout for media type test' => '1M',
						'Network timeout for script execution' => '1M',
						'Network timeout for item test' => '1M'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			],
			// Maximal invalid time in years (years not supported).
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '1y',
						// Authorization.
						'Login blocking interval' => '1y',
						// Communication with Zabbix server.
						'Network timeout' => '1y',
						'Connection timeout' => '1y',
						'Network timeout for media type test' => '1y',
						'Network timeout for script execution' => '1y',
						'Network timeout for item test' => '1y'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			],
			// Maximal invalid values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '99999999999999999999999999999999',
						// Authorization.
						'Login attempts' => '99',
						'Login blocking interval' => '99999999999999999999999999999999',
						// Communication with Zabbix server.
						'Network timeout' => '99999999999999999999999999999999',
						'Connection timeout' => '99999999999999999999999999999999',
						'Network timeout for media type test' => '99999999999999999999999999999999',
						'Network timeout for script execution' => '99999999999999999999999999999999',
						'Network timeout for item test' => '99999999999999999999999999999999'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": value must be one of 0-86400.',
						'Incorrect value for field "login_attempts": value must be no greater than "32".',
						'Incorrect value for field "login_block": value must be one of 30-3600.',
						'Incorrect value for field "socket_timeout": value must be one of 1-300.',
						'Incorrect value for field "connect_timeout": value must be one of 1-30.',
						'Incorrect value for field "media_type_test_timeout": value must be one of 1-300.',
						'Incorrect value for field "script_timeout": value must be one of 1-300.',
						'Incorrect value for field "item_test_timeout": value must be one of 1-300.'
					]
				]
			],
			// Negative values.
			[
				[
					'expected' => TEST_BAD,
					'fields' =>  [
						'Refresh unsupported items' => '-1',
						// Authorization.
						'Login attempts' => '-1',
						'Login blocking interval' => '-1',
						// Communication with Zabbix server.
						'Network timeout' => '-1',
						'Connection timeout' => '-1',
						'Network timeout for media type test' => '-1',
						'Network timeout for script execution' => '-1',
						'Network timeout for item test' => '-1'
					],
					'details' => [
						'Incorrect value for field "refresh_unsupported": a time unit is expected.',
						'Incorrect value for field "login_block": a time unit is expected.',
						'Incorrect value for field "socket_timeout": a time unit is expected.',
						'Incorrect value for field "connect_timeout": a time unit is expected.',
						'Incorrect value for field "media_type_test_timeout": a time unit is expected.',
						'Incorrect value for field "script_timeout": a time unit is expected.',
						'Incorrect value for field "item_test_timeout": a time unit is expected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckFormData
	 */
	public function testFormAdministrationGeneralOtherParams_CheckForm($data) {
		$this->page->login()->open('zabbix.php?action=miscconfig.edit');
		$form = $this->query('name:otherForm')->waitUntilPresent()->asForm()->one();
		// Reset form in case of previous test case.
		$this->resetConfiguration($form, $this->default, 'Reset defaults');
		// Fill form with new data.
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();
		$message = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD
			? 'Configuration updated'
			: 'Cannot update configuration';
		$this->assertMessage($data['expected'], $message, CTestArrayHelper::get($data, 'details'));
		// Check saved configuration in frontend.
		$this->page->open('zabbix.php?action=miscconfig.edit');
		$form->invalidate();
		// Check trimming symbols in Login attempts field.
		if ((CTestArrayHelper::get($data['fields'], 'Login attempts')) === '3M') {
			$data['fields']['Login attempts'] = '3';
		}
		$values = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD ? $data['fields'] : $this->default;
		$form->checkValue($values);
		// Check saved configuration in database.
		$sql = CDBHelper::getRow('SELECT * FROM config');
		$db = (CTestArrayHelper::get($data, 'expected')) === TEST_GOOD
			? CTestArrayHelper::get($data, 'db', [])
			: $this->db_default;
		foreach ($db as $key => $value) {
			$this->assertArrayHasKey($key, $sql);
			$this->assertEquals($value, $sql[$key]);
		}
	}

	/**
	 * Function for configuration resetting.
	 *
	 * @param element  $form      Settings configuration form
	 * @param array    $default   Default form values
	 * @param string   $action    Reset defaults or Cancel
	 * @param array    $custom    Custom values for filling into settings form
	 */
	private function resetConfiguration($form, $default, $action, $custom = null) {
		$form->query('button:Reset defaults')->one()->click();
		COverlayDialogElement::find()->waitUntilPresent()->one()->query('button', $action)->one()->click();
		switch ($action) {
			case 'Reset defaults':
				// In Other parameters form these fields have no default value, so can be filled with anyting.
				$form->checkValue(
					[
						'Group for discovered hosts' => null,
						'User group for database down message' => null
					]
				);
				$form->fill(
					[
						'Group for discovered hosts' => 'Empty group',
						'User group for database down message' => 'Zabbix administrators'
					]
				);
				$form->submit();
				$this->assertMessage(TEST_GOOD, 'Configuration updated');
				$this->page->refresh();
				$this->page->waitUntilReady();
				$form->invalidate();
				// Check reset form.
				$form->checkValue($default);
				break;
			case 'Cancel':
				$form->checkValue($custom);
				break;
		}
	}
}
