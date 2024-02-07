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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup scripts
 *
 * @onBefore prepareData
 */
class testFormAlertsScripts extends CWebTest {

	/**
	 * Id of scripts that created for future cloning.
	 *
	 * @var integer
	 */
	protected static $clone_scriptids;

	/**
	 * Id of scripts.
	 *
	 * @var array
	 */
	protected static $ids;

	/**
	 * Id of host.
	 *
	 * @var array
	 */
	protected static $hostid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function prepareData() {
		$response = CDataHelper::call('script.create', [
			[
				'name' => 'Script for Clone',
				'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'command' => 'test',
				'parameters' => [
					[
						'name' => 'name1',
						'value' => 'value1'
					],
					[
						'name' => 'name2',
						'value' => 'value2'
					]
				],
				'description' => 'clone description'
			],
			[
				'name' => 'SSH_api_clone_1',
				'type' => ZBX_SCRIPT_TYPE_SSH,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'username' => 'SSH_username',
				'password' => 'SSH_password',
				'command' => 'test',
				'port' => '80'
			],
			[
				'name' => 'SSH_api_clone_2',
				'type' => ZBX_SCRIPT_TYPE_SSH,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
				'username' => 'SSH_username',
				'privatekey' => 'private_key',
				'publickey' => 'public_key',
				'command' => 'test'
			],
			[
				'name' => 'TELNET_api_clone',
				'type' => ZBX_SCRIPT_TYPE_TELNET,
				'scope' => ZBX_SCRIPT_SCOPE_ACTION,
				'username' => 'TELNET_username',
				'password' => 'TELNET_password',
				'command' => 'test'
			],
			[
				'name' => 'type URL, manual host event for clone',
				'type' => ZBX_SCRIPT_TYPE_URL,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'new_window' => ZBX_SCRIPT_URL_NEW_WINDOW_NO,
				'menu_path' => 'menu/path',
				'url' => 'sysmaps.php'
			],
			[
				'name' => 'type URL, manual action event for clone',
				'type' => ZBX_SCRIPT_TYPE_URL,
				'scope' => ZBX_SCRIPT_SCOPE_EVENT,
				'url' => 'zabbix.com'
			]
		]);
		$this->assertArrayHasKey('scriptids', $response);
		self::$clone_scriptids = $response['scriptids'];

		$scripts = CDataHelper::call('script.create', [
			[
				'name' => 'Script for Update',
				'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'command' => 'test',
				'parameters' => [
					[
						'name' => 'update_name',
						'value' => 'update_value'
					],
					[
						'name' => 'update_name2',
						'value' => 'update_value2'
					]
				],
				'description' => 'update description'
			],
			[
				'name' => 'Script for Delete',
				'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'command' => 'test',
				'description' => 'delete description'
			],
			[
				'name' => 'URI schemes',
				'type' => ZBX_SCRIPT_TYPE_URL,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'url' => 'sysmaps.php'
			]
		]);
		$this->assertArrayHasKey('scriptids', $scripts);
		self::$ids = CDataHelper::getIds('name');

		// Create host and trapper item for manual user input test.
		$host = CDataHelper::createHosts([
			[
				'host' => 'A host for scripts check',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.1.9.1',
						'dns' => '',
						'port' => '10777'
					]
				],
				'groups' => [
					'groupid' => '19' // Applications.
				],
				'items' => [
					[
						'name' => 'Scripts trapper',
						'key_' => 'script_trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);
		self::$hostid = $host['hostids']['A host for scripts check'];

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Attention: script execution is needed',
				'expression' => 'last(/A host for scripts check/script_trap)<>0',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			]
		]);

		// Create problem for manual event action check.
		CDBHelper::setTriggerProblem('Attention: script execution is needed', TRIGGER_VALUE_TRUE);
	}

	/**
	 * Test data for Scripts form.
	 */
	public function getScriptsData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "timeout": value must be one of 1-60.',
					'fields' => [
						'Name' => 'Timeout test 0',
						'Script' => 'java script',
						'Timeout' => '0'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "timeout": value must be one of 1-60.',
					'fields' => [
						'Name' => 'Timeout test 1h',
						'Script' => 'java script',
						'Timeout' => '1h'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "timeout": value must be one of 1-60.',
					'fields' => [
						'Name' => 'Timeout test 70',
						'Script' => 'java script',
						'Timeout' => '70s'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "timeout": a time unit is expected.',
					'fields' => [
						'Name' => 'Timeout test -1',
						'Script' => 'java script',
						'Timeout' => '-1'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "timeout": a time unit is expected.',
					'fields' => [
						'Name' => 'Timeout test character',
						'Script' => 'java script',
						'Timeout' => 'char'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.',
					'fields' => [
						'Name' => 'Test empty parameters',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '',
							'Value' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => '',
							'Value' => ''
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/parameters/2": value (name)=(Param1) already exists.',
					'fields' => [
						'Name' => 'Test empty parameter names',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'Param1',
							'Value' => 'Value1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => 'Param1',
							'Value' => 'Value'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.',
					'fields' => [
						'Name' => 'Test trailing spaces',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '   ',
							'Value' => '   '
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'Webhook Empty script',
						'Script' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Script' => 'Webhook: empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'Script',
						'Commands' => 'Script empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'Script empty command',
						'Type' => 'Script',
						'Commands' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'IPMI',
						'Command' => 'IPMI empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'IPMI empty command',
						'Type' => 'IPMI',
						'Command' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'SSH',
						'Commands' => 'SSH empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'SSH empty command',
						'Type' => 'SSH',
						'Commands' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/username": cannot be empty.',
					'fields' => [
						'Name' => 'SSH empty username',
						'Type' => 'SSH',
						'Commands' => 'SSH empty username',
						'Username' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "name": cannot be empty.',
					'fields' => [
						'Name' => '',
						'Type' => 'Telnet',
						'Commands' => 'Telnet empty name'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/command": cannot be empty.',
					'fields' => [
						'Name' => 'Telnet empty command',
						'Type' => 'Telnet',
						'Commands' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/username": cannot be empty.',
					'fields' => [
						'Name' => 'Telnet empty username',
						'Type' => 'Telnet',
						'Commands' => 'Telnet empty username',
						'Username' => ''
					]
				]
			],
			// URL type.
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/url": cannot be empty.',
					'fields' => [
						'Name' => 'Url empty for host action',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => ''
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/url": cannot be empty.',
					'fields' => [
						'Name' => 'Url empty for event action',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => '     '
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/url": unacceptable URL.',
					'fields' => [
						'Name' => 'invalid uri schema',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'htt://zabbix.com'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/menu_path": directory cannot be empty.',
					'fields' => [
						'Name' => 'invalid menu path',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.com',
						'Menu path' => '/ /'
					]
				]
			],
			// User input fields validation.
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_prompt": cannot be empty.',
					'fields' => [
						'Name' => 'invalid input prompt',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'http://zabbix.com',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input validation rule' => '^$' // should match an empty string.
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_prompt": cannot be empty.',
					'fields' => [
						'Name' => 'invalid input prompt',
						'Scope' => 'Manual event action',
						'Type' => 'Webhook',
						'Script' => 'ping localhost',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => ' ',
						'Input validation rule' => '^$' // should match an empty string.
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_default_value": input does not match the provided pattern: ^$.',
					'fields' => [
						'Name' => 'invalid default input string - not match empty string',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'test empty input',
						'Default input string' => 'test',
						'Input validation rule' => '^$' // should match an empty string.
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_default_value": input does not match the provided pattern: ^.*@.*\..*$.',
					'fields' => [
						'Name' => 'invalid default input string - not match an email input',
						'Scope' => 'Manual host action',
						'Type' => 'SSH',
						'Username' => 'localhost',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'test email input',
						'Default input string' => 'a$a.lv',
						'Input validation rule' => '^.*@.*\..*$' // should match an email.
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": cannot be empty.',
					'fields' => [
						'Name' => 'invalid input validation rule',
						'Scope' => 'Manual event action',
						'Type' => 'Telnet',
						'Username' => 'localhost',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'test'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": cannot be empty.',
					'fields' => [
						'Name' => 'invalid input validation rule',
						'Scope' => 'Manual host action',
						'Type' => 'IPMI',
						'Command' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'test',
						'Input validation rule' => '  '
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": cannot be empty.',
					'fields' => [
						'Name' => 'invalid dropdown options',
						'Scope' => 'Manual host action',
						'Type' => 'Telnet',
						'Username' => 'test',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input type' => 'Dropdown',
						'Input prompt' => 'test'
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": cannot be empty.',
					'fields' => [
						'Name' => 'invalid dropdown options',
						'Scope' => 'Manual event action',
						'Type' => 'SSH',
						'Username' => 'test',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input type' => 'Dropdown',
						'Input prompt' => 'test',
						'Dropdown options' => ' '
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": values must be unique.',
					'fields' => [
						'Name' => 'invalid dropdown options',
						'Scope' => 'Manual host action',
						'Type' => 'Webhook',
						'Script' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input type' => 'Dropdown',
						'Input prompt' => 'test',
						'Dropdown options' => ','
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Invalid parameter "/1/manualinput_validator": values must be unique.',
					'fields' => [
						'Name' => 'invalid dropdown options',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input type' => 'Dropdown',
						'Input prompt' => 'test',
						'Dropdown options' => 'a,,b,,c'
					]
				]
			],
			// Confirmation text validation.
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "confirmation": cannot be empty.',
					'fields' => [
						'Name' => 'invalid confirmation text',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'https://zabbix.com/',
						'Advanced configuration' => true,
						'Enable confirmation' => true
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'details' => 'Incorrect value for field "confirmation": cannot be empty.',
					'fields' => [
						'Name' => 'invalid confirmation text',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'ping 127.0.0.1',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => ''
					]
				]
			],
			// Webhook.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Minimal script',
						'Script' => 'java script'
					]
				]
			],
			// Remove trailing spaces.
			[
				[
					'expected' => TEST_GOOD,
					'trim' => true,
					'fields' => [
						'Name' => 'Test trailing spaces',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => 'name',
							'Value' => '   trimmed    value    '
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '   trimmed     name    ',
							'Value' => 'value'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max webhook',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script',
						'Timeout' => '60s',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Zabbix servers',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => 'Execute script?'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'host',
							'Value' => '{HOST.HOST}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => 'var',
							'Value' => 'Value'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max webhook 2',
						'Scope' => 'Action operation',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script',
						'Timeout' => '60s',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Zabbix servers'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'host',
							'Value' => '{HOST.HOST}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => 'var',
							'Value' => 'Value'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max webhook 3',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script',
						'Timeout' => '60s',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Zabbix servers',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => 'Execute script?'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'host',
							'Value' => '{HOST.HOST}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => 'var',
							'Value' => 'Value'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Test parameters',
						'Type' => 'Webhook',
						'Script' => 'Webhook Script',
						'Timeout' => '1s'
					],
					'Parameters' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '!@#$%^&*()_+<>,.\/',
							'Value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => str_repeat('n', 255),
							'Value' => str_repeat('v', 2048)
						],
						[
							'Name' => '{$MACRO:A}',
							'Value' => '{$MACRO:A}'
						],
						[
							'Name' => '{$USERMACRO}',
							'Value' => ''
						],
						[
							'Name' => '{HOST.HOST}'
						],
						[
							'Name' => 'Имя',
							'Value' => 'Значение'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Webhook false confirmation',
						'Script' => 'webhook',
						'Script' => 'java script',
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Timeout test 1',
						'Script' => 'java script',
						'Timeout' => '1'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Timeout test 60',
						'Script' => 'java script',
						'Timeout' => '60'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Timeout test 1m',
						'Script' => 'java script',
						'Timeout' => '1m'
					]
				]
			],
			// Script.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max script',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server (proxy)',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max script 2',
						'Scope' => 'Action operation',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server (proxy)',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max script 3',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server (proxy)',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			// IPMI.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max IPMI',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'IPMI',
						'Command' => 'IPMI command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Discovered hosts',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => 'Execute script?'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max IPMI 2',
						'Scope' => 'Action operation',
						'Type' => 'IPMI',
						'Command' => 'IPMI command',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Discovered hosts'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max IPMI 3',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'IPMI',
						'Command' => 'IPMI command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Discovered hosts',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => 'Execute script?'
					]
				]
			],
			// SSH.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'SSH',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH 2',
						'Scope' => 'Action operation',
						'Type' => 'SSH',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH 3',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'SSH',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH 4',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'SSH',
						'Authentication method' => 'Public key',
						'Username' => 'test',
						'Public key file' => 'public_key_file',
						'Private key file' => 'private_key_file',
						'Key passphrase' => 'key_passphrase',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH 5',
						'Scope' => 'Action operation',
						'Type' => 'SSH',
						'Authentication method' => 'Public key',
						'Username' => 'test',
						'Public key file' => 'public_key_file',
						'Private key file' => 'private_key_file',
						'Key passphrase' => 'key_passphrase',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max SSH 6',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'SSH',
						'Authentication method' => 'Public key',
						'Username' => 'test',
						'Public key file' => 'public_key_file',
						'Private key file' => 'private_key_file',
						'Key passphrase' => 'key_passphrase',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			// Telnet.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max Telnet',
						'Scope' => 'Manual host action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Telnet',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max Telnet 2',
						'Scope' => 'Action operation',
						'Type' => 'Telnet',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Max Telnet 3',
						'Scope' => 'Manual event action',
						'Menu path' => 'path_1/path_2',
						'Type' => 'Telnet',
						'Username' => 'test',
						'Password' => 'test_password',
						'Port' => '81',
						'Commands' => 'Script command',
						'Description' => 'Test description',
						'User group' => 'Selenium user group',
						'Host group' => 'Selected',
						'xpath://div[@id="groupid"]/..' => 'Hypervisors',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => false
					]
				]
			],
			// URL.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'type URL for manual host action',
						'Scope' => 'Manual host action',
						'Menu path' => 'top_menu/sub_menu/',
						'Type' => 'URL',
						'URL' => 'http://zabbix.com',
						'Open in a new window' => false,
						'Description' => 'selected Url type',
						'Host group' => 'Selected',
						'User group' => 'Zabbix administrators',
						'xpath://div[@id="groupid"]/..' => 'Zabbix servers',
						'Required host permissions' => 'Write',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => 'open url?'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'type URL for manual event action',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=script.list'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'User input type string',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'http://zabbix.com',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Test version?',
						'Default input string' => 'Zabbix 7.0.0 alpha',
						'Input validation rule' => 'Zabbix [0-9]+\.[0-9]\.[0-9]+ alpha'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'User input type string - empty string',
						'Scope' => 'Manual event action',
						'Type' => 'Webhook',
						'Script' => 'ping localhost',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'empty string',
						'Default input string' => '',
						'Input validation rule' => '^$' // should match an empty string.
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'User input type - dropdown',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'dropdown values',
						'Input type' => 'Dropdown',
						'Dropdown options' => '.*,,A'
					]
				]
			],
			// User manual input cases with UTF-8 4-byte characters.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '🔔User input type string with UTF-8 4-byte characters🔔',
						'Scope' => 'Manual event action',
						'Type' => 'SSH',
						'Username' => 'zabbix',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'string values 🚩',
						'Input type' => 'String',
						'Default input string' => '⚠️',
						'Input validation rule' => '⚠️'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '❌User input type dropdown with UTF-8 4-byte characters❌',
						'Scope' => 'Manual host action',
						'Type' => 'Telnet',
						'Username' => 'zabbix',
						'Commands' => 'test',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'dropdown values 📌',
						'Input type' => 'Dropdown',
						'Dropdown options' => '📌,⚠️,❌'
					]
				]
			],
			// User manual input cases with maxlength.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => STRING_255,
						'Scope' => 'Manual host action',
						'Type' => 'IPMI',
						'Command' => 'ping localhost',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => STRING_255,
						'Input type' => 'String',
						'Default input string' => STRING_255,
						'Input validation rule' => str_repeat('.*|.*|.*', 256)
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => str_repeat('TEST_', 51),
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => STRING_2048,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => STRING_255,
						'Input type' => 'Dropdown',
						'Dropdown options' => STRING_128.','.STRING_64.','.str_repeat('tests', 12)
					]
				]
			],
			// User manual input cases with macro.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Open Zabbix page',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'http://localhost/ui/zabbix.php?action={MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Enable confirmation' => true,
						'Input prompt' => 'Zabbix page to open:',
						'Input type' => 'Dropdown',
						'Dropdown options' => 'dashboard.view,discovery.view',
						'Confirmation text' => 'Are you sure you want to open {MANUALINPUT} page?'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Ping count',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.CONN};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Enable confirmation' => true,
						'Input prompt' => 'Add ping count',
						'Input type' => 'String',
						'Default input string' => '5',
						'Input validation rule' => '\b[1-9]\b',
						'Confirmation text' => 'Are you sure you want to execute ping script with value {MANUALINPUT}?'
					]
				]
			],
			// Check that manual input fields leading and trailing spaces and are trimmed.
			[
				[
					'expected' => TEST_GOOD,
					'trim' => true,
					'fields' => [
						'Name' => 'Trim check for dropdown input type',
						'Scope' => 'Manual host action',
						'Type' => 'SSH',
						'Username' => 'test',
						'Commands' => 'ping localhost;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => '  Add spaces  ',
						'Input type' => 'Dropdown',
						'Dropdown options' => ' Q,A '
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'trim' => true,
					'fields' => [
						'Name' => 'Trim check for string input type',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Execute on' => 'Zabbix server',
						'Commands' => 'ping localhost;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => '  Add spaces  ',
						'Input type' => 'String',
						'Default input string' => ' 5 ',
						'Input validation rule' => ' \b[1-9]\b '
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getScriptsData
	 * @backupOnce scripts
	 */
	public function testFormAlertsScripts_Create($data) {
		$this->checkScripts($data, false);
	}

	/**
	 * @dataProvider getScriptsData
	 */
	public function testFormAlertsScripts_Update($data) {
		$this->checkScripts($data, true, self::$ids['Script for Update']);
	}

	/**
	 * Function for checking script configuration form.
	 *
	 * @param array     $data     data provider
	 * @param boolean   $update   is it update case, or not
	 * @param int		$id       id of the script in case of updating
	 */
	private function checkScripts($data, $update, $id = null) {
		if ($data['expected'] === TEST_BAD) {
			$sql = 'SELECT * FROM scripts ORDER BY scriptid';
			$old_hash = CDBHelper::getHash($sql);
		}

		// Open the correct form - either edit existing script, or add new.
		$modal = $this->openScriptForm($id);
		$form = $modal->asForm();
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data, 'Parameters')) {
			// Remove action and index fields for create case.
			if ($update === false) {
				foreach ($data['Parameters'] as &$parameter) {
					unset($parameter['action'], $parameter['index']);
				}
				unset($parameter);
			}

			$modal->query('id:parameters-table')->asMultifieldTable()->one()->fill($data['Parameters']);
		}

		// Check testing confirmation while configuring.
		if (array_key_exists('Enable confirmation', $data['fields'])) {
			$this->checkConfirmation($data, $form);
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($data['expected'] === TEST_BAD) {
			$title = ($update) ? 'Cannot update script' : 'Cannot add script';
			$this->assertMessage(TEST_BAD, $title, $data['details']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
		}
		else {
			$title = ($update) ? 'Script updated' : 'Script added';
			$this->assertMessage(TEST_GOOD, $title);
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr($data['fields']['Name'])));

			// Check the results in form.
			$id = CDBHelper::getValue('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr($data['fields']['Name']));
			$this->openScriptForm($id, false);

			if (array_key_exists('Advanced configuration', $data['fields'])) {
				$form->fill(['Advanced configuration' => true]);
			}

			// Trim trailing and leading spaces in expected values before comparison.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data = CTestArrayHelper::trim($data);
			}

			$form->invalidate();
			$form->checkValue($data['fields']);

			// Check testing confirmation in saved form.
			if (array_key_exists('Enable confirmation', $data['fields'])) {
				$this->checkConfirmation($data, $form);
			}

			if (CTestArrayHelper::get($data, 'Parameters')) {
				// Remove action and index fields for asserting.
				if ($update === true) {
					foreach ($data['Parameters'] as &$parameter) {
						unset($parameter['action'], $parameter['index']);
					}
					unset($parameter);
				}

				$modal->query('id:parameters-table')->asMultifieldTable()->one()->checkValue($data['Parameters']);
			}
		}

		$modal->close();
	}

	/**
	 * Function for checking execution confirmation popup.
	 *
	 * @param array     $data    data provider
	 * @param element   $form    script configuration form
	 */
	private function checkConfirmation($data, $form) {
		if (CTestArrayHelper::get($data['fields'], 'Enable confirmation') === false) {
			$this->assertFalse($form->query('id:confirmation')->one()->isEnabled());
			$this->assertFalse($form->query('id:test_confirmation')->one()->isEnabled());
		}

		if (CTestArrayHelper::get($data['fields'], 'Confirmation text')) {
			$this->query('button:Test confirmation')->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$this->assertEquals($data['fields']['Confirmation text'],
					$dialog->query('xpath:.//span[@class="confirmation-msg"]')->waitUntilVisible()->one()->getText()
			);
			$dialog->query('class:btn-overlay-close')->waitUntilClickable()->one()->click();
		}
	}

	/**
	 * Function for checking script form update cancelling.
	 */
	public function testFormAlertsScripts_CancelUpdate() {
		$sql = 'SELECT * FROM scripts ORDER BY scriptid';
		$old_hash = CDBHelper::getHash($sql);
		$modal = $this->openScriptForm(self::$ids['Script for Update']);
		$modal->asForm()->fill([
			'Name' => 'Cancelled script',
			'Type' => 'Script',
			'Execute on' => 'Zabbix server',
			'Commands' => 'Script command',
			'Description' => 'Cancelled description',
			'User group' => 'Disabled',
			'Host group' => 'Selected',
			'xpath://div[@id="groupid"]/..' => 'Hypervisors',
			'Required host permissions' => 'Write',
			'Advanced configuration' => true,
			'Enable user input' => true,
			'Input prompt' => 'Insert value',
			'Default input string' => 'v1.1',
			'Input validation rule' => 'v[0-9]+\.[0-9]+',
			'Enable confirmation' => true,
			'Confirmation text' => 'Your configuration will be updated. Are you sure?'
		]);
		$modal->query('button:Cancel')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Scripts');
		$this->assertTrue($this->query('button:Create script')->waitUntilVisible()->one()->isReady());
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function for checking script form update without any changes.
	 */
	public function testFormAlertsScripts_SimpleUpdate() {
		$sql = 'SELECT * FROM scripts ORDER BY scriptid';
		$old_hash = CDBHelper::getHash($sql);
		$modal = $this->openScriptForm(self::$ids['Script for Update']);
		$modal->asForm()->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Script updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Function for checking script cloning with only changed name.
	 */
	public function testFormAlertsScripts_Clone() {
		$this->page->login();

		foreach (self::$clone_scriptids as $scriptid) {
			$modal = $this->openScriptForm($scriptid, false);
			$form = $modal->asForm();
			$values = $form->getFields()->asValues();
			$script_name = $values['Name'];
			$this->query('button:Clone')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();

			$form->invalidate();
			$form->fill(['Name' => 'Cloned_'.$script_name]);
			$form->submit();

			$this->assertMessage(TEST_GOOD, 'Script added');
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr($script_name)));
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM scripts WHERE name='.zbx_dbstr('Cloned_'.$script_name)));

			$id = CDBHelper::getValue('SELECT scriptid FROM scripts WHERE name='.zbx_dbstr('Cloned_'.$script_name));
			$this->openScriptForm($id, false);
			$cloned_values = $form->getFields()->asValues();
			$this->assertEquals('Cloned_'.$script_name, $cloned_values['Name']);

			// Field Name removed from arrays.
			unset($cloned_values['Name']);
			unset($values['Name']);
			$this->assertEquals($values, $cloned_values);
			$modal->close();
		}
	}

	/**
	 * Function for testing script delete from configuration form.
	 */
	public function testFormAlertsScripts_Delete() {
		$modal = $this->openScriptForm(self::$ids['Script for Delete']);
		$modal->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Script deleted');
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM scripts WHERE scriptid='.
				zbx_dbstr(self::$ids['Script for Delete']))
		);
	}

	/**
	 * Check all fields default values, lengths, placeholders, element options and table headers.
	 */
	public function testFormAlertsScripts_Layout() {
		$modal = $this->openScriptForm();
		$form = $modal->asForm();

		$default_values = ['Scope' => 'Action operation', 'Type' => 'Webhook', 'Host group' => 'All',
			'User group' => 'All', 'Required host permissions' => 'Read', 'Enable confirmation' => false, 'Timeout' => '30s',
			'Execute on' => 'Zabbix agent', 'Authentication method' => 'Password', 'Open in a new window' => true
		];
		$form->checkValue($default_values);

		// Check table headers.
		$this->assertEquals(['Name', 'Value', 'Action'], $form->query('id:parameters-table')->asTable()->one()->getHeadersText());

		// Check fields' lengths.
		$field_maxlength = [
			'Name' => 255,
			'Timeout' => 32,
			'Description' => 65535,
			'Menu path' => 255,
			'Confirmation text' => 255,
			'Commands' => 65535,
			'URL' => 2048,
			'Username' => 64,
			'Password' => 64,
			'Port' => 64,
			'Public key file' => 64,
			'Private key file' => 64,
			'Key passphrase' => 64,
			'Command' => 65535,
			'Input prompt' => 255,
			'Default input string' => 255,
			'Input validation rule' => 2048,
			'Dropdown options' => 2048
		];
		foreach ($field_maxlength as $input => $value) {
			$this->assertEquals($value, $form->getField($input)->getAttribute('maxlength'));
		}

		// Check fields' placeholders.
		$this->assertEquals('script', $form->getField('Script')->query('xpath:.//input[@type="text"]')->one()->getAttribute('placeholder'));
		$this->assertEquals('<sub-menu/sub-menu/...>', $form->getField('Menu path')->getAttribute('placeholder'));
		$this->assertEquals('regular expression', $form->getField('Input validation rule')->getAttribute('placeholder'));
		$this->assertEquals('comma-separated list', $form->getField('Dropdown options')->getAttribute('placeholder'));

		// Check dropdown options.
		$user_groups = CDBHelper::getColumn('SELECT name FROM usrgrp ORDER BY name', 'name');
		$dropdowns = [
			'Host group' => ['All', 'Selected'],
			'User group' => array_merge(['All'], $user_groups),
			'Authentication method' => ['Password', 'Public key']
		];
		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getOptions()->asText());
		}

		// Check segmented radio element options.
		$segmented_elements = [
			'Scope' => ['Action operation', 'Manual host action', 'Manual event action'],
			'Type' => ['URL', 'Webhook', 'Script', 'SSH', 'Telnet', 'IPMI'],
			'Execute on' => ['Zabbix agent', 'Zabbix server (proxy)', 'Zabbix server'],
			'Required host permissions' => ['Read', 'Write'],
			'Input type' => ['String', 'Dropdown']
		];
		foreach ($segmented_elements as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getLabels()->asText());
		}

		// Check "Script" dialog window.
		$script_dialog = $form->getField('Script')->edit();
		$this->assertEquals('JavaScript', $script_dialog->getTitle());
		$this->assertEquals(65535, $script_dialog->query('tag:textarea')->one()->getAttribute('maxlength'));
		$this->assertEquals('return value', $script_dialog->query('tag:textarea')->one()->getAttribute('placeholder'));
		$this->assertEquals('65535 characters remaining', $script_dialog->query('class:multilineinput-char-count')->one()->getText());
		$script_dialog->query('tag:textarea')->one()->type('aaa');
		$this->assertEquals('65532 characters remaining', $script_dialog->query('class:multilineinput-char-count')->one()->getText());
		$script_dialog->query('button:Cancel')->one()->click();
		$script_dialog->waitUntilNotVisible();
		$form->checkValue(['Script' => '']);

		// Check "Confirmation" and "Manual input" dialog windows.
		$scenarios = [
			'confirmation' => [
				'Scope' => 'Manual host action',
				'Advanced configuration' => true,
				'Enable confirmation' => true,
				'Confirmation text' => 'test'
			],
			'input_string' => [
				'Enable user input' => true,
				'Input prompt' => 'test',
				'Default input string' => 'v1.2',
				'Input validation rule' => 'v[0-9]+\.[0-9]+'
			],
			'input_dropdown' => [
				'Input prompt' => '{HOST.CONN}',
				'Input type' => 'Dropdown',
				'Dropdown options' => 'a,b,,c'
			]
		];
		foreach ($scenarios as $scenario => $parameters) {
			$form->fill($parameters);
			$this->query('button:'.(($scenario === 'confirmation') ? 'Test confirmation' : 'Test user input'))
					->waitUntilClickable()->one()->click();
			$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$this->assertEquals((($scenario === 'confirmation') ? 'Execution confirmation' : 'Manual input'), $dialog->getTitle());

			if ($scenario === 'confirmation') {
				$this->assertEquals($parameters['Confirmation text'], $dialog->query('class:confirmation-msg')
						->one()->getText()
				);
			}
			else {
				$this->assertEquals($parameters['Input prompt'], $dialog->query('class:wordbreak')->one()->getText());
			}

			if ($scenario === 'input_dropdown') {
				$this->assertEquals(['a', 'b', '', 'c'], $dialog->query('name:manualinput')->asDropdown()->one()
						->getOptions()->asText()
				);
			}

			if ($scenario === 'input_string') {
				$this->assertEquals($parameters['Default input string'], $dialog->query('id:manualinput')
						->one()->getValue()
				);
				$this->assertTrue($dialog->query('button:Test')->one()->isEnabled());
			}
			else {
				$this->assertFalse($dialog->query('button:'.(($scenario === 'confirmation') ? 'Execute' : 'Test'))
						->one()->isEnabled()
				);
			}

			$dialog->query('button:Cancel')->one()->click();
			$dialog->waitUntilNotVisible();
		}

		$modal->close();
	}

	/**
	 * Check the visible fields and their default values, and the required class based on the selected scope and type.
	 */
	public function testFormAlertsScripts_VisibleFields() {
		$common_all_scopes = [
			'fields' => ['Name', 'Scope', 'Type', 'Description', 'Host group'],
			'required' => ['Name'],
			'default' => ['Host group' => 'All']
		];
		$common_manual_scope = [
			'fields' => ['Menu path', 'User group', 'Required host permissions', 'Advanced configuration'],
			'default' => ['User group' => 'All', 'Required host permissions' => 'Read', 'Enable user input' => false,
				'Input prompt' => '', 'Input type' => 'String', 'Default input string' => '', 'Input validation rule' => '',
				'Enable confirmation' => false, 'Confirmation text' => ''
			],
			'advanced_fields' => ['Enable user input', 'Input prompt', 'Input type', 'Default input string', 'Input validation rule',
				'Enable confirmation', 'Confirmation text'
			],
			'advanced_fields_dropdown' => ['Enable user input', 'Input prompt', 'Input type', 'Dropdown options',
				'Enable confirmation', 'Confirmation text'
			],
			'input_string_required' => ['Input prompt', 'Input validation rule', 'Confirmation text'],
			'input_dropdown_required' => ['Input prompt', 'Dropdown options', 'Confirmation text']
		];
		$types = [
			'Webhook' => [
				'fields' => ['Parameters', 'Script', 'Timeout'],
				'required' => ['Script', 'Timeout'],
				'default' => ['Timeout' => '30s']
			],
			'Script' => [
				'fields' => ['Execute on', 'Commands'],
				'required' => ['Commands'],
				'default' => ['Execute on' => 'Zabbix agent']
			],
			'SSH' => [
				'fields' => ['Authentication method', 'Username', 'Password', 'Port', 'Commands'],
				'required' => ['Username', 'Commands'],
				'default' => ['Authentication method' => 'Password'],
				'fields_public_key' => ['Authentication method', 'Username', 'Public key file', 'Private key file',
					'Key passphrase', 'Port', 'Commands'
				],
				'required_public_key' => ['Username', 'Public key file', 'Private key file', 'Commands']
			],
			'Telnet' => [
				'fields' => ['Username', 'Password', 'Port', 'Commands'],
				'required' => ['Username', 'Commands'],
				'default' => []
			],
			'IPMI' => [
				'fields' => ['Command'],
				'required' => ['Command'],
				'default' => []
			],
			'URL' => [
				'fields' => ['URL', 'Open in a new window'],
				'required' => ['URL'],
				'default' => ['Open in a new window' => true]
			]
		];

		$modal = $this->openScriptForm();
		$form = $modal->asForm();
		$form->checkValue(['Scope' => 'Action operation', 'Type' => 'Webhook']);

		foreach (['Action operation', 'Manual host action', 'Manual event action'] as $scope) {
			// Merge all common fields based on scope type, manual or action operation.
			if ($scope === 'Action operation') {
				$scope_fields = $common_all_scopes['fields'];
				$scope_default = $common_all_scopes['default'];
				$scope_required = $common_all_scopes['required'];
			}
			else {
				$form->fill(['Scope' => $scope]);

				$scope_fields = array_merge($common_all_scopes['fields'], $common_manual_scope['fields'],
						$common_manual_scope['advanced_fields']
				);
				$scope_fields_dropdown = array_merge($common_all_scopes['fields'], $common_manual_scope['fields'],
						$common_manual_scope['advanced_fields_dropdown']
				);
				$scope_default = array_merge($common_all_scopes['default'], $common_manual_scope['default']);
				$scope_required = array_merge($common_all_scopes['required'], $common_manual_scope['input_string_required']);
				$scope_required_dropdown = array_merge($common_all_scopes['required'], $common_manual_scope['input_dropdown_required']);
			}

			foreach ($types as $type => $type_fields) {
				// Type 'URL' not visible for 'Action operation'.
				if ($scope === 'Action operation' && $type === 'URL') {
					continue;
				}

				$form->fill(['Type' => $type]);

				if ($scope === 'Manual host action' || $scope === 'Manual event action') {
					// Check advanced configuration default value.
					$form->checkValue(['Advanced configuration' => false]);

					// Check that the 'Advanced configuration' additional fields are hidden.
					foreach ($common_manual_scope['advanced_fields'] as $label) {
						$this->assertFalse($form->getLabel($label)->isDisplayed());
					}

					$form->fill(['Advanced configuration' => true]);
				}

				// Check visible fields.
				$this->assertEqualsCanonicalizing(array_merge($scope_fields, $type_fields['fields']),
						$form->getLabels(CElementFilter::VISIBLE)->asText()
				);

				// Check default values.
				$form->checkValue(array_merge($scope_default, $type_fields['default']));

				if ($scope === 'Manual host action' || $scope === 'Manual event action') {
					$form->fill(['Enable user input' => true, 'Enable confirmation' => true]);
				}

				$this->assertEqualsCanonicalizing(array_merge($scope_required, $type_fields['required']),
						$form->getRequiredLabels()
				);

				if ($type === 'SSH') {
					// Check fields with 'Public key' authentication method.
					$form->fill(['Authentication method' => 'Public key']);

					$this->assertEqualsCanonicalizing(array_merge($scope_fields, $type_fields['fields_public_key']),
							$form->getLabels(CElementFilter::VISIBLE)->asText()
					);
					$this->assertEqualsCanonicalizing(array_merge($scope_required, $type_fields['required_public_key']),
							$form->getRequiredLabels()
					);

					// Reset the value of the "Authentication method" field.
					$form->fill(['Authentication method' => 'Password']);
				}

				// Check required fields when 'Input type' is 'Dropdown'.
				if ($scope === 'Manual host action' || $scope === 'Manual event action') {
					$form->fill(['Input type' => 'Dropdown']);


					$this->assertEqualsCanonicalizing(array_merge($scope_fields_dropdown, $type_fields['fields']),
							$form->getLabels(CElementFilter::VISIBLE)->asText()
					);

					if ($type === 'SSH') {
						$form->fill(['Authentication method' => 'Public key']);
						$this->assertEqualsCanonicalizing(array_merge($scope_fields_dropdown, $type_fields['fields_public_key']),
								$form->getLabels(CElementFilter::VISIBLE)->asText()
						);
						$this->assertEqualsCanonicalizing(array_merge($scope_required_dropdown, $type_fields['required_public_key']),
								$form->getRequiredLabels()
						);

						// Reset the value of the "Authentication method" field.
						$form->fill(['Authentication method' => 'Password']);
					}
					else {
						$this->assertEqualsCanonicalizing(array_merge($scope_required_dropdown, $type_fields['required']),
								$form->getRequiredLabels()
						);
					}

					// Change advanced configuration to default state.
					$form->fill([
						'Input type' => 'String',
						'Enable user input' => false,
						'Enable confirmation' => false,
						'Advanced configuration' => false
					]);
				}
			}
		}

		$modal->close();
	}

	/**
	 * Modify the URI scheme validation rules and check the result for the URL type in script form.
	 */
	public function testFormAlertsScripts_UriScheme() {
		$invalid_schemes = ['dns://zabbix.com', 'message://zabbix.com'];
		$default_valid_schemes = ['http://zabbix.com', 'https://zabbix.com', 'ftp://zabbix.com', 'file://zabbix.com',
			'mailto://zabbix.com', 'tel://zabbix.com', 'ssh://zabbix.com'
		];

		$modal = $this->openScriptForm(self::$ids['URI schemes']);
		$form = $modal->asForm();

		// Check default URI scheme rules: http, https, ftp, file, mailto, tel, ssh.
		$this->assertUriScheme($form, $default_valid_schemes);
		$this->assertUriScheme($form, $invalid_schemes, TEST_BAD);

		// Change valid URI schemes on "Other configuration parameters" page.
		$modal->close();
		$this->page->open('zabbix.php?action=miscconfig.edit');
		$config_form = $this->query('name:otherForm')->asForm()->waitUntilVisible()->one();
		$config_form->fill(['id:validate_uri_schemes' => true, 'id:uri_valid_schemes' => 'dns,message']);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->openScriptForm(self::$ids['URI schemes'], false);
		$this->assertUriScheme($form, $default_valid_schemes, TEST_BAD);
		$this->assertUriScheme($form, $invalid_schemes);

		// Disable URI scheme validation.
		$modal->close();
		$this->page->open('zabbix.php?action=miscconfig.edit')->waitUntilReady();
		$config_form->fill(['id:validate_uri_schemes' => false]);
		$config_form->submit();
		$this->assertMessage(TEST_GOOD, 'Configuration updated');

		$this->openScriptForm(self::$ids['URI schemes'], false);
		$this->assertUriScheme($form, array_merge($default_valid_schemes, $invalid_schemes));
		$modal->close();
	}

	/**
	 * Fill in the URL field to check the uri scheme validation rules.
	 *
	 * @param CFormElement $form	form element of script
	 * @param array $data			url field data
	 * @param string $expected		expected result after script form submit, TEST_GOOD or TEST_BAD
	 */
	private function assertUriScheme($form, $data, $expected = TEST_GOOD) {
		foreach ($data as $scheme) {
			$form->fill(['URL' => $scheme]);
			$form->submit();

			if ($expected === TEST_GOOD) {
				$this->assertMessage(TEST_GOOD, 'Script updated');
				$this->openScriptForm(self::$ids['URI schemes'], false);
			}
			else {
				$this->assertMessage(TEST_BAD, 'Cannot update script', 'Invalid parameter "/1/url": unacceptable URL.');
				CMessageElement::find()->one()->close();
			}
		}
	}

	public function getContextMenuData() {
		return [
			// USER.* macros.
			[
				[
					'fields' => [
						'Name' => 'USER macros - manual host',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => '{USER.FULLNAME}, {USER.NAME}, {USER.SURNAME}, {USER.USERNAME}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{USER.FULLNAME}, {USER.NAME}, {USER.SURNAME}, {USER.USERNAME}'
					],
					'resolved_macros' => 'Zabbix Administrator (Admin), Zabbix, Administrator, Admin',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'USER macros - manual event',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => '{USER.FULLNAME}, {USER.NAME}, {USER.SURNAME}, {USER.USERNAME}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{USER.FULLNAME}, {USER.NAME}, {USER.SURNAME}, {USER.USERNAME}'
					],
					'resolved_macros' => 'Zabbix Administrator (Admin), Zabbix, Administrator, Admin',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			],
			// EVENT.* macros.
			[
				[
					'fields' => [
						'Name' => 'EVENT macros - manual host',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => '{EVENT.ID},{EVENT.NAME},{EVENT.NSEVERITY},{EVENT.SEVERITY},{EVENT.STATUS},{EVENT.VALUE}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{EVENT.ID},{EVENT.NAME},{EVENT.NSEVERITY},{EVENT.SEVERITY},'.
								'{EVENT.STATUS},{EVENT.VALUE}'
					],
					'resolved_macros' => '{EVENT.ID},{EVENT.NAME},{EVENT.NSEVERITY},{EVENT.SEVERITY},{EVENT.STATUS},{EVENT.VALUE}',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'EVENT macros - manual event',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => '{EVENT.ID},{EVENT.NAME},{EVENT.NSEVERITY},{EVENT.SEVERITY},{EVENT.STATUS},{EVENT.VALUE}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{EVENT.ID},{EVENT.NAME},{EVENT.NSEVERITY},{EVENT.SEVERITY},'.
								'{EVENT.STATUS},{EVENT.VALUE}'
					],
					'resolved_macros' => '93,Test trigger with tag,2,Warning,PROBLEM,1',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			],
			// HOST.* macros.
			[
				[
					'fields' => [
						'Name' => 'HOST macros - manual host',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => '{HOST.ID},{HOST.CONN},{HOST.DNS},{HOST.HOST},{HOST.IP},{HOST.NAME}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{HOST.ID},{HOST.CONN},{HOST.DNS},{HOST.HOST},{HOST.IP},{HOST.NAME}'
					],
					'resolved_macros' => '10084,127.0.0.1,,Test host,127.0.0.1,ЗАББИКС Сервер',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'HOST macros - manual event',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => '{HOST.ID},{HOST.CONN},{HOST.DNS},{HOST.HOST},{HOST.IP},{HOST.NAME}',
						'Advanced configuration' => true,
						'Enable confirmation' => true,
						'Confirmation text' => '{HOST.ID},{HOST.CONN},{HOST.DNS},{HOST.HOST},{HOST.IP},{HOST.NAME}'
					],
					'resolved_macros' => '10084,127.0.0.1,,Test host,127.0.0.1,ЗАББИКС Сервер',
					'host' => 'ЗАББИКС Сервер',
					'trigger' => 'Test trigger with tag'
				]
			]
		];
	}

	/**
	 * Check resolved macros in Host and Event context menu on Problems page.
	 *
	 * @dataProvider getContextMenuData
	 */
	public function testFormAlertsScripts_ContextMenu($data) {
		$modal = $this->openScriptForm();
		$form = $modal->asForm();

		$form->fill($data['fields']);
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Script added');

		$this->page->open('zabbix.php?action=problem.view');
		$table = $this->query('class:list-table')->asTable()->one();

		$with_script = ($data['fields']['Scope'] === 'Manual host action') ? $data['host'] : $data['trigger'];
		$without_script = ($data['fields']['Scope'] === 'Manual host action') ? $data['trigger'] : $data['host'];

		// Check resolved macros in confirmation popup.
		$table->query('link', $with_script)->one()->click();
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$popup->fill($data['fields']['Name']);
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertEquals('URL opening confirmation', $dialog->getTitle());
		$this->assertEquals($data['resolved_macros'], $dialog->query('class:confirmation-msg')->one()->getText());

		// Check if buttons present and clickable.
		$this->assertEquals(['Cancel', 'Open URL'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
		$dialog->close();

		// Check that script link is not present in the context menu for other manual action.
		$table->query('link', $without_script)->one()->click();
		$this->assertEquals(0, CPopupMenuElement::find()->waitUntilVisible()->one()->getItems()
				->filter(CElementFilter::TEXT_PRESENT, $data['fields']['Name'])->count()
		);
	}

	public function getManualInputData() {
		return [
			// #0 Host url with {MANUALINPUT} macro, confirmation message and input type - string.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host url with {MANUALINPUT} macro, confirmation message and input type - string',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Host id {MANUALINPUT} is selected. Proceed?'
					],
					'manualinput' => '0',
					'prompt' => 'Enter host id',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #1 Event url with {MANUALINPUT} macro, confirmation message and input type - string.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event url with {MANUALINPUT} macro, confirmation message and input type - string',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Host id {MANUALINPUT} is selected. Proceed?'
					],
					'manualinput' => '0',
					'prompt' => 'Enter host id',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #2 Event url without confirmation message (input type - string).
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event url with without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => '999999',
					'prompt' => 'Enter host id',
					'event' => 'Inheritance trigger with tags',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #3 Host url without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host url with {MANUALINPUT} macro and without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => '0',
					'prompt' => 'Enter host id',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #4 Host webhook without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'parameters' => [
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'A',
							'Value' => '{MANUALINPUT}'
						]
					],
					'fields' => [
						'Name' => 'Host webhook without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Webhook',
						'Script' => 'var params = JSON.parse(value); return params.a;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter value for parameter A',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b', // regex 1-9 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => 'a',
					'prompt' => 'Enter value for parameter A',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #5 Event webhook without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'parameters' => [
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'A',
							'Value' => '{MANUALINPUT}'
						]
					],
					'fields' => [
						'Name' => 'Event webhook without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Webhook',
						'Script' => 'var params = JSON.parse(value); return params.a;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter value for parameter A',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b', // regex 1-9 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => '10',
					'prompt' => 'Enter value for parameter A',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #6 Host webhook with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'parameters' => [
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'A',
							'Value' => '{MANUALINPUT}'
						]
					],
					'fields' => [
						'Name' => 'Host webhook with confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Webhook',
						'Script' => 'var params = JSON.parse(value); return params.a;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter value for parameter A',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b', // regex 1-9 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Parameter A will contain {MANUALINPUT} value. Proceed?'
					],
					'manualinput' => '10',
					'prompt' => 'Enter value for parameter A',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #7 Event webhook with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'parameters' => [
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'A',
							'Value' => '{MANUALINPUT}'
						]
					],
					'fields' => [
						'Name' => 'Event webhook with confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Webhook',
						'Script' => 'var params = JSON.parse(value); return params.a;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter value for parameter A',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b', // regex 1-9 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Parameter A will contain {MANUALINPUT} value. Proceed?'
					],
					'manualinput' => '',
					'prompt' => 'Enter value for parameter A',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #8 Host script with {MANUALINPUT} macro, confirmation message and input type - string.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host script with {MANUALINPUT} macro and confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => true,
						'Confirmation text' => 'Ping count: {MANUALINPUT}'
					],
					'manualinput' => '0',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #9 Event script with {MANUALINPUT} macro, confirmation message and input type - string.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event script with {MANUALINPUT} macro and confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => true,
						'Confirmation text' => 'Ping count: {MANUALINPUT}'
					],
					'manualinput' => '0',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #10 Event script without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event script without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => false
					],
					'manualinput' => '10',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #11 Host script without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host script without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => false
					],
					'manualinput' => '',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: \b[1-9]\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #12 Host SSH without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host SSH without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'SSH',
						'Username' => 'zabbix',
						'Commands' => 'ssh zabbix@{MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter hostname',
						'Default input string' => 'Aa',
						'Input validation rule' => '[A-Za-z]', // all letters (uppercase and lowercase).
						'Enable confirmation' => false
					],
					'manualinput' => '11',
					'prompt' => 'Enter hostname',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: [A-Za-z].',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #13 Host SSH with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host SSH with confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'SSH',
						'Username' => 'zabbix',
						'Commands' => 'ssh zabbix@{MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter hostname',
						'Default input string' => 'Aa',
						'Input validation rule' => '[A-Za-z]', // all letters (uppercase and lowercase).
						'Enable confirmation' => true,
						'Confirmation text' => 'Hostname is {MANUALINPUT}'
					],
					'manualinput' => '.',
					'prompt' => 'Enter hostname',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: [A-Za-z].',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #14 Event SSH without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event SSH without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'SSH',
						'Username' => 'zabbix',
						'Commands' => 'ssh zabbix@{MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter hostname',
						'Default input string' => 'Aa',
						'Input validation rule' => '[A-Za-z]', // all letters (uppercase and lowercase).
						'Enable confirmation' => false
					],
					'manualinput' => '?',
					'prompt' => 'Enter hostname',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: [A-Za-z].',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #15 Event SSH with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event SSH with confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'SSH',
						'Username' => 'zabbix',
						'Commands' => 'ssh zabbix@{MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter hostname',
						'Default input string' => 'Aa',
						'Input validation rule' => '[A-Za-z]', // all letters (uppercase and lowercase).
						'Enable confirmation' => true,
						'Confirmation text' => 'Hostname is {MANUALINPUT}'
					],
					'manualinput' => '',
					'prompt' => 'Enter hostname',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: [A-Za-z].',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #16 Host Telnet without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host Telnet without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Telnet',
						'Username' => 'zabbix',
						'Commands' => 'telnet 127.0.0.1 {MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter port',
						'Default input string' => '22',
						'Input validation rule' => '\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 10-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => '1',
					'prompt' => 'Enter port',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #17 Host Telnet with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host Telnet with confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Telnet',
						'Username' => 'zabbix',
						'Commands' => 'telnet 127.0.0.1 {MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter port',
						'Default input string' => '22',
						'Input validation rule' => '\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 10-99999 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Selected port:{MANUALINPUT}. Proceed?'
					],
					'manualinput' => '.',
					'prompt' => 'Enter port',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #18 Event Telnet without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event Telnet without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Telnet',
						'Username' => 'zabbix',
						'Commands' => 'telnet 127.0.0.1 {MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter port',
						'Default input string' => '22',
						'Input validation rule' => '\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 10-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => '?',
					'prompt' => 'Enter port',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #19 Event Telnet with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event Telnet with confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'Telnet',
						'Username' => 'zabbix',
						'Commands' => 'telnet 127.0.0.1 {MANUALINPUT}',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter port',
						'Default input string' => '22',
						'Input validation rule' => '\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 10-99999 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Selected port:{MANUALINPUT}. Proceed?'
					],
					'manualinput' => '',
					'prompt' => 'Enter port',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'\b([1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]|[1-9][0-9][0-9][0-9][0-9])\b.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #20 Host IPMI without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host IPMI without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'IPMI',
						'Command' => 'ipmitool -I lan -H localhost -U zabbix -P {MANUALINPUT} -L user sensor',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
								'one digit, one special character and minimum eight in length',
						'Default input string' => 'Ex@mple7',
						'Input validation rule' => '^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
						'Enable confirmation' => false
					],
					'manualinput' => 'example1',
					'prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
							'one digit, one special character and minimum eight in length',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #21 Host IPMI with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host IPMI with confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'IPMI',
						'Command' => 'ipmitool -I lan -H localhost -U zabbix -P {MANUALINPUT} -L user sensor',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
								'one digit, one special character and minimum eight in length',
						'Default input string' => 'Ex@mple7',
						'Input validation rule' => '^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
						'Enable confirmation' => true,
						'Confirmation text' => 'Are you sure?'
					],
					'manualinput' => '.',
					'prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
							'one digit, one special character and minimum eight in length',
					'host' => 'A host for scripts check',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #22 Event IPMI without confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event IPMI without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'IPMI',
						'Command' => 'ipmitool -I lan -H localhost -U zabbix -P {MANUALINPUT} -L user sensor',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
								'one digit, one special character and minimum eight in length',
						'Default input string' => 'Ex@mple7',
						'Input validation rule' => '^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
						'Enable confirmation' => false
					],
					'manualinput' => '?',
					'prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
							'one digit, one special character and minimum eight in length',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #23 Event IPMI with confirmation message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Event IPMI with confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'IPMI',
						'Command' => 'ipmitool -I lan -H localhost -U zabbix -P {MANUALINPUT} -L user sensor',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
								'one digit, one special character and minimum eight in length',
						'Default input string' => 'Ex@mple7',
						'Input validation rule' => '^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$',
						'Enable confirmation' => true,
						'Confirmation text' => 'Are you sure?'
					],
					'manualinput' => '',
					'prompt' => 'regex will enforce these rules: At least one upper case letter, one lower case letter'.
							'one digit, one special character and minimum eight in length',
					'event' => 'Attention: script execution is needed',
					'error_message' => 'Incorrect value for field "manualinput": input does not match the provided pattern: '.
							'^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$.',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #24 Host url without confirmation message.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host url with without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => 'id',
					'prompt' => 'Enter host id',
					'host' => 'A host for scripts check',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #25 Event url without confirmation message.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Event url without confirmation message',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => false
					],
					'manualinput' => 'id',
					'prompt' => 'Enter host id',
					'event' => 'Attention: script execution is needed',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #26 Event url without confirmation message and with dropdown.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Event url without confirmation message and with dropdown',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter host id',
						'Input prompt' => 'Choose host id',
						'Input type' => 'Dropdown',
						'Dropdown options' => '10080,10084,10081,',
						'Enable confirmation' => false
					],
					'manualinput' => 'id',
					'prompt' => 'Choose host id',
					'event' => 'Attention: script execution is needed',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #27 Host url with confirmation message and with dropdown.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host url with confirmation message and with dropdown',
						'Scope' => 'Manual host action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Choose host id',
						'Input type' => 'Dropdown',
						'Dropdown options' => '10080,10084,10081,',
						'Enable confirmation' => true,
						'Confirmation text' => 'Confirm selected host?'
					],
					'manualinput' => 'id',
					'prompt' => 'Choose host id',
					'confirmation' => 'Confirm selected host?',
					'host' => 'A host for scripts check',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #28 Event url with confirmation message and with input type - string.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Event url with confirmation message and with input type - string',
						'Scope' => 'Manual event action',
						'Type' => 'URL',
						'URL' => 'zabbix.php?action=host.edit&hostid={MANUALINPUT}',
						'Open in a new window' => false,
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Choose host id',
						'Default input string' => '1',
						'Input validation rule' => '\b([1-9]|[1-9][0-9]|[1-9][0-9][0-9]|[1-9][0-9][0-9][0-9]'.
								'|[1-9][0-9][0-9][0-9][0-9])\b', // regex 1-99999 for form validation.
						'Enable confirmation' => true,
						'Confirmation text' => 'Confirm selected host?'
					],
					'manualinput' => 'id',
					'prompt' => 'Choose host id',
					'confirmation' => 'Confirm selected host?',
					'event' => 'Attention: script execution is needed',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #29 Host script with {MANUALINPUT} macro, confirmation message and input type - dropdown.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host script with dropdown and confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Commands' => 'echo test {MANUALINPUT};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Choose supported version',
						'Input type' => 'Dropdown',
						'Dropdown options' => '6.0,6.4,7.0',
						'Enable confirmation' => true,
						'Confirmation text' => 'Confirm {MANUALINPUT} as supported version?'
					],
					'manualinput' => '6.4',
					'prompt' => 'Choose supported version',
					'confirmation' => 'Confirm 6.4 as supported version?',
					'host' => 'A host for scripts check',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #30 Manual host script with {MANUALINPUT} macro, confirmation message and input type - string.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Manual host script with macro and confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => true,
						'Confirmation text' => 'Ping count: {MANUALINPUT}'
					],
					'manualinput' => '2',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'confirmation' => 'Ping count: 2',
					'host' => 'A host for scripts check',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// #31 Manual host script with {MANUALINPUT} macro and without confirmation message (input type - string).
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Manual host script with macro and without confirmation message',
						'Scope' => 'Manual host action',
						'Type' => 'Script',
						'Commands' => 'ping -c {MANUALINPUT} {HOST.HOST};',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Enter 🚩{HOST.HOST}🚩 ping count',
						'Default input string' => '1',
						'Input validation rule' => '\b[1-9]\b',
						'Enable confirmation' => false
					],
					'manualinput' => '2',
					'prompt' => 'Enter 🚩A host for scripts check🚩 ping count',
					'host' => 'A host for scripts check',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Hosts' => 'zabbix.php?action=host.view',
						'Latest data' => 'zabbix.php?action=latest.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			],
			// TODO: uncomment when ZBX-24042 will be fixed.
			// Manual event script without confirmation message (input type - dropdown).
//			[
//				[
//					'expected' => TEST_GOOD,
//					'fields' => [
//						'Name' => 'Manual event script with dropdown and without confirmation message',
//						'Scope' => 'Manual event action',
//						'Type' => 'Script',
//						'Commands' => 'echo test;',
//						'Advanced configuration' => true,
//						'Enable user input' => true,
//						'Input prompt' => 'Choose supported version',
//						'Input type' => 'Dropdown',
//						'Dropdown options' => '6.0,6.4,7.0',
//						'Enable confirmation' => false
//					],
//					'manualinput' => '7.0',
//					'prompt' => 'Choose supported version',
//					'event' => 'Attention: script execution is needed',
//					'urls' => [
//						'Problems' => 'zabbix.php?action=problem.view',
//						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
//					]
//				]
//			],
			// #32 Manual event script with confirmation message (input type - string).
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Manual event script with confirmation message and input type - string',
						'Scope' => 'Manual event action',
						'Type' => 'Script',
						'Commands' => 'echo test;',
						'Advanced configuration' => true,
						'Enable user input' => true,
						'Input prompt' => 'Test version?',
						'Default input string' => 'Zabbix 7.0.0',
						'Input validation rule' => 'Zabbix [0-9]+\.[0-9]\.[0-9]+',
						'Enable confirmation' => true,
						'Confirmation text' => 'Selected version is {MANUALINPUT}, proceed?'
					],
					'manualinput' => 'Zabbix 6.4.11',
					'prompt' => 'Test version?',
					'confirmation' => 'Selected version is Zabbix 6.4.11, proceed?',
					'event' => 'Attention: script execution is needed',
					'urls' => [
						'Problems' => 'zabbix.php?action=problem.view',
						'Global view' => 'zabbix.php?action=dashboard.view&dashboardid=1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getManualInputData
	 */
	public function testFormAlertsScripts_ManualUserInput($data) {
		$modal = $this->openScriptForm();
		$form = $modal->asForm();

		if (($data['manualinput'] === 'id') && (array_key_exists('Dropdown options', $data['fields']))) {
			$data['fields']['Dropdown options'] = $data['fields']['Dropdown options'].self::$hostid;
		}

		if (array_key_exists('parameters', $data)) {
			$modal->query('id:parameters-table')->asMultifieldTable()->one()->fill($data['parameters']);
		}
		$form->fill($data['fields'])->submit();
		$this->assertMessage(TEST_GOOD, 'Script added');

		foreach ($data['urls'] as $content => $url) {
			$this->page->open($url)->waitUntilReady();
			$this->page->assertHeader($content);
			$scope = (array_key_exists('host', $data)) ? 'host' : 'event';

			if ($content === 'Latest data') {
				$this->query('link', $data[$scope])->one()->click();
				$this->page->waitUntilReady();
				$table = $this->query('xpath://table[@class="list-table fixed"]')->asTable()->one();
			}
			elseif ($content === 'Global view') {
				$table = CDashboardElement::find()->one()->getWidget('Current problems');
			}
			else {
				$table = $this->query('class:list-table')->asTable()->one();
			}

			$table->query('link', $data[$scope])->one()->click();
			$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
			$popup->fill($data['fields']['Name']);
			$manualinput_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$this->assertEquals('Manual input', $manualinput_dialog->getTitle());
			$this->assertEquals($data['prompt'], $manualinput_dialog->query('class:wordbreak')->one()->getText());

			$manualinput = ($data['manualinput'] === 'id') ? self::$hostid : $data['manualinput'];

			$input_type = (array_key_exists('Input type', $data['fields']))
				? $manualinput_dialog->query('name:manualinput')->asDropdown()->one()->select($manualinput)
				: $manualinput_dialog->query('id:manualinput')->one()->fill($manualinput);

			$action = ($data['fields']['Enable confirmation'] === true) ? 'Continue' : 'Execute';

			// Check if buttons present and clickable.
			$this->assertEquals(['Cancel', $action], $manualinput_dialog->getFooter()->query('button')->all()
					->filter(CElementFilter::CLICKABLE)->asText()
			);
			$manualinput_dialog->getFooter()->query('button', $action)->one()->click();

			if ($data['expected'] === TEST_BAD) {
				$this->assertMessage(TEST_BAD, 'Invalid input', $data['error_message']);
				$manualinput_dialog->close();
			}
			else {
				if (array_key_exists('confirmation', $data)) {
					$confirmation_message = $this->query('class:confirmation-msg')->waitUntilVisible()->one();
					$confirmation_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$title = ($data['fields']['Type'] === 'URL') ? 'URL opening confirmation' : 'Execution confirmation';
					$this->assertEquals($title, $confirmation_dialog->getTitle());
					$this->assertEquals($data['confirmation'], $confirmation_message->getText());
					$action = ($data['fields']['Type'] === 'URL') ? 'Open URL' : 'Execute';

					// Check that confirmation popup buttons present and clickable.
					$this->assertEquals(['Cancel', $action], $confirmation_dialog->getFooter()->query('button')->all()
							->filter(CElementFilter::CLICKABLE)->asText()
					);
					$confirmation_dialog->getFooter()->query('button', $action)->one()->click();
				}

				if ($data['fields']['Type'] === 'URL') {
					COverlayDialogElement::ensureNotPresent();
					$scope = (array_key_exists('host', $data)) ? $data['host'] : 'A host for scripts check';
					$this->assertEquals($scope, $this->query('id:host')->one()->getValue());
				}
				else {
					$this->query('button:Ok')->waitUntilVisible()->one();
					$output_dialog = COverlayDialogElement::find()->waitUntilReady()->one();
					$this->assertEquals($data['fields']['Name'], $output_dialog->getTitle());

					// Check that Zabbix server is down and return error message.
					$error = "Connection to Zabbix server \"localhost:10051\" refused. Possible reasons:\n".
						"1. Incorrect \"NodeAddress\" or \"ListenPort\" in the \"zabbix_server.conf\" or server IP/DNS override in the \"zabbix.conf.php\";\n".
						"2. Security environment (for example, SELinux) is blocking the connection;\n".
						"3. Zabbix server daemon not running;\n".
						"4. Firewall is blocking TCP connection.\n".
						"Connection refused";
					$this->assertMessage(TEST_BAD, 'Cannot execute script.', $error);
					$this->assertEquals(['Ok'], $output_dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
					);
					$output_dialog->close();
				}
			}
		}
	}

	/**
	 * Logs in, opens Script list and opens a Script form for editing or new.
	 *
	 * @param integer  $id          ID of the script to open
	 * @param boolean  $login       is a login needed or not
	 *
	 * @return COverlayDialogElement
	 */
	protected function openScriptForm($id = null, $login = true) {
		if ($login) {
			$this->page->login()->open('zabbix.php?action=script.list');
		}
		else {
			$this->page->open('zabbix.php?action=script.list');
		}

		if ($id) {
			$this->query('xpath://a[@data-scriptid='.CXPathHelper::escapeQuotes($id).']')->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create script')->waitUntilClickable()->one()->click();
		}

		return COverlayDialogElement::find()->one()->waitUntilReady();
	}
}
