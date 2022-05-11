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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup scripts
 */
class testScripts extends CAPITest {

	public static function script_create_data_invalid() {
		return [
			// Check script type.
			'Test missing type' => [
				'script' => [
					'name' => 'API create script',
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "type" is missing.'
			],
			'Test invalid type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => '',
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => 'abc',
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type' => [
				'script' => [
					'name' => 'API create script',
					'type' => 999999,
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of '.
					implode(', ', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
						ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK
					]).'.'
			],
			// Check script command.
			'Test missing command' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "command" is missing.'
			],
			'Test empty command' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => ''
				],
				'expected_error' => 'Invalid parameter "/1/command": cannot be empty.'
			],
			// Check script name.
			'Test missing name' => [
				'script' => [
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Test empty name' => [
				'script' => [
					'name' => '',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test existing name' => [
				'script' => [
					'name' => 'Ping',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server'
				],
				'expected_error' => 'Script "Ping" already exists.'
			],
			'Test duplicate name' => [
				'script' => [
					[
						'name' => 'Scripts with the same name',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server'
					],
					[
						'name' => 'Scripts with the same name',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(Scripts with the same name) already exists.'
			],
			// Check script scope.
			'Test invalid scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ''
				],
				'expected_error' => 'Invalid parameter "/1/scope": an integer is expected.'
			],
			'Test invalid scope (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/scope": an integer is expected.'
			],
			'Test invalid scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => 0
				],
				'expected_error' => 'Invalid parameter "/1/scope": value must be one of '.
					implode(', ', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]).'.'
			],
			// Check script menu path.
			'Test invalid menu_path for host scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test invalid menu_path for event scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test unexpected menu_path for action scope (default)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			'Test unexpected menu_path for action scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'menu_path' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			'Test unexpected menu_path for action scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			// Check script host access.
			'Test unexpected host_access for action scope (default, string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'host_access' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test unexpected host_access for action scope (default, int)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "host_access".'
			],
			'Test unexpected host_access for action scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "host_access".'
			],
			'Test invalid host_access for host scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'host_access' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for host scope (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'host_access' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access host scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			'Test invalid host_access for event scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'host_access' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for event scope (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'host_access' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access event scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			// Check script user group.
			'Test unexpected usrgrpid for action scope (default, string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'usrgrpid' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test unexpected usrgrpid for action scope (default, int)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'usrgrpid' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			'Test unexpected usrgrpid for action scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'usrgrpid' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			'Test invalid usrgrpid for host scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for host scope (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'usrgrpid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for host scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			'Test invalid usrgrpid for host scope (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for event scope (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'usrgrpid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for event scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			// Check script confirmation.
			'Test unexpected confirmation for action scope (default)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'confirmation' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "confirmation".'
			],
			'Test unexpected confirmation for action scope' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'confirmation' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "confirmation".'
			],
			// Check script host group.
			'Test invalid host group (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'groupid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			'Test invalid host group (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'groupid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			'Test invalid host group' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'groupid' => 999999
				],
				'expected_error' => 'Host group with ID "999999" is not available.'
			],
			// Check unexpected fields in script.
			'Test unexpected field' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'unexpected_field' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "unexpected_field".'
			],
			// Check script execute_on.
			'Test invalid execute_on (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'execute_on' => ''
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test invalid execute_on (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'execute_on' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test invalid execute_on' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'execute_on' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": value must be one of '.
					implode(', ', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER,
						ZBX_SCRIPT_EXECUTE_ON_PROXY
					]).'.'
			],
			'Test unexpected execute_on field for IPMI type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'execute_on' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test unexpected execute_on field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for SSH type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			// Check script port.
			'Test invalid port (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'port' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/port": an integer is expected.'
			],
			'Test invalid port' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'port' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/port": value must be one of '.
					ZBX_MIN_PORT_NUMBER.'-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test unexpected port field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'port' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			// Check script auth type.
			'Test invalid authtype (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'authtype' => ''
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test invalid authtype (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'authtype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test invalid authtype' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'authtype' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/authtype": value must be one of '.
					implode(', ', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]).'.'
			],
			'Test unexpected authtype field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'authtype' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test unexpected authtype field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			// Check script username.
			'Test missing username for SSH type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "username" is missing.'
			],
			'Test missing username for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "username" is missing.'
			],
			'Test empty username for SSH type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			'Test empty username for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			'Test unexpected username field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			// Check script password.
			'Test unexpected password field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'password' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			// Check script public key.
			'Test missing publickey' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'username' => 'John',
					'authtype' => ITEM_AUTHTYPE_PUBLICKEY
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "publickey" is missing.'
			],
			'Test empty publickey' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'username' => 'John',
					'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1/publickey": cannot be empty.'
			],
			'Test unexpected publickey field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			// Check script private key.
			'Test missing privatekey' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'username' => 'John',
					'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "privatekey" is missing.'
			],
			'Test empty privatekey' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'username' => 'John',
					'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
					'publickey' => 'secretpubkey',
					'privatekey' => ''
				],
				'expected_error' => 'Invalid parameter "/1/privatekey": cannot be empty.'
			],
			'Test unexpected privatekey field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'privatekey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for Javascript type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			// Check script timeout.
			'Test invalid timeout' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'timeout' => '100'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": value must be one of 1-'.SEC_PER_MIN.'.'
			],
			'Test unsupported macros in timeout' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'timeout' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a time unit is expected.'
			],
			'Test unexpected timeout field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'timeout' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for SSH type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			// Check script parameters.
			'Test invalid parameters' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'parameters' => ''
				],
				'expected_error' => 'Invalid parameter "/1/parameters": an array is expected.'
			],
			'Test missing name in parameters' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'parameters' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1": the parameter "name" is missing.'
			],
			'Test empty name in parameters' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => ''
					]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.'
			],
			'Test missing value in parameters' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => 'param1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1": the parameter "value" is missing.'
			],
			'Test duplicate parameters' => [
				'script' => [
					'name' => 'Webhook validation with params',
					'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
					'command' => 'Script command',
					'parameters' => [
						[
							'name' => 'param1',
							'value' => 'value1'
						],
						[
							'name' => 'param1',
							'value' => 'value1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/2": value (name)=(param1) already exists.'
			],
			'Test unexpected parameters field for custom script type (empty)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'parameters' => []
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for custom script type (empty sub-params)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'parameters' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for custom script type (string)' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'parameters' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/parameters": an array is expected.'
			],
			'Test unexpected parameters field for custom script type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for IPMI type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_IPMI,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for SSH type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for Telnet type' => [
				'script' => [
					'name' => 'API create script',
					'type' => ZBX_SCRIPT_TYPE_TELNET,
					'command' => 'reboot server',
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			]
		];
	}

	public static function script_create_data_valid() {
		return [
			'Test successful UTF-8 name' => [
				'script' => [
					[
						'name' => 'Апи скрипт создан утф-8',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server 1'
					]
				],
				'expected_error' => null
			],
			'Test successful multiple scripts' => [
				'script' => [
					[
						'name' => 'API create one script',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server 1'
					],
					[
						'name' => 'æų',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'æų'
					]
				],
				'expected_error' => null
			],
			'Test successful menu path for host scope (empty)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 1)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => ''
					]
				],
				'expected_error' => null
			],
			'Test successful menu path for event scope (empty)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 2)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_EVENT,
						'menu_path' => ''
					]
				],
				'expected_error' => null
			],
			'Test successful menu path (empty root)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 3)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => '/'
					]
				],
				'expected_error' => null
			],
			'Test successful menu path (preceding slash)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 4)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => '/folder1/folder2'
					]
				],
				'expected_error' => null
			],
			'Test successful menu path (trailing slash)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 5)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => 'folder1/folder2/'
					]
				],
				'expected_error' => null
			],
			'Test successful menu path (preceding and trailing slash)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 6)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => '/folder1/folder2/'
					]
				],
				'expected_error' => null
			],
			'Test successful menu path (no slash)' => [
				'script' => [
					[
						'name' => 'API create script (menu path test 7)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => 'folder1/folder2'
					]
				],
				'expected_error' => null
			],
			'Test successful custom script with random non-default parameters' => [
				'script' => [
					[
						'name' => 'API create custom script with random non-default parameters',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'command' => 'reboot server',
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
						'scope' => ZBX_SCRIPT_SCOPE_EVENT,
						'description' => 'custom event script that executes on server for all user groups and Zabbix servers host group with write permissions',
						'usrgrpid' => 0,
						'groupid' => 4,
						'host_access' => PERM_READ_WRITE,
						'confirmation' => 'confirmation text',
						'menu_path' => 'folder1/folder2'
					]
				],
				'expected_error' => null
			],
			'Test successful SSH script with random non-default parameters' => [
				'script' => [
					[
						'name' => 'API create SSH script with random non-default parameters',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'description' =>
							'SSH host script for Zabbix administrators and all host groups with write permissions',
						'usrgrpid' => 7,
						'groupid' => 0,
						'host_access' => PERM_READ_WRITE,
						'confirmation' => 'confirmation text',
						'port' => '{$MACRO}',
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'username' => 'John',
						'password' => 'Ada',
						'publickey' => 'secret_public_key',
						'privatekey' => 'secret_private_key',
						'menu_path' => 'folder1/folder2'
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet script with random non-default parameters' => [
				'script' => [
					[
						'name' => 'API create Telnet script with random non-default parameters',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_EVENT,
						'description' => 'Telnet event script for Zabbix administrators and Zabbix servers host groups with write permissions',
						'usrgrpid' => 7,
						'groupid' => 4,
						'host_access' => PERM_READ_WRITE,
						'confirmation' => 'confirmation text',
						'port' => 456,
						'username' => 'John',
						'password' => 'Ada',
						'menu_path' => 'folder1/folder2'
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript script with random non-default parameters' => [
				'script' => [
					[
						'name' => 'API create Javascript script with random non-default parameters',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'command' => 'reboot server',
						'scope' => ZBX_SCRIPT_SCOPE_EVENT,
						'description' => 'Javascript event script with for Zabbix administrators and Zabbix servers host groups with write permissions',
						'usrgrpid' => 7,
						'groupid' => 4,
						'host_access' => PERM_READ_WRITE,
						'confirmation' => 'confirmation text',
						'timeout' => '10',
						'menu_path' => 'folder1/folder2',
						'parameters' => [
							[
								'name' => '!@#$%^&*()_+<>,.\/',
								'value' => '!@#$%^&*()_+<>,.\/'
							],
							[
								'name' => str_repeat('n', 255),
								'value' => str_repeat('v', 2048)
							],
							[
								'name' => '{$MACRO:A}',
								'value' => '{$MACRO:A}'
							],
							[
								'name' => '{$USERMACRO}',
								'value' => ''
							],
							[
								'name' => '{HOST.HOST}',
								'value' => '{EVENT.NAME}'
							],
							[
								'name' => 'Имя',
								'value' => 'Значение'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function script_get() {
		return [
			// No fields are returned on empty selection.
			[
				'params' => [
					'output' => [],
					'groupids' => ['90020']
				],
				'expect' => [
					'error' => null,
					'result_keys' => []
				]
			],
			// Strict validation is used.
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90020'],
					'groupids' => ['no such id']
				],
				'expect' => [
					'error' => 'Invalid parameter "/groupids/1": a number is expected.'
				]
			],
			// 90020 is top group, nothing to inherit from
			[
				'params' => [
					'output' => ['scriptid'],
					'groupids' => ['90020']
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['90020'],
					'result_keys' => ['scriptid']
				]
			],
			// group 90021 is child group of 90020 and script from parent group is inherited
			[
				'params' => [
					'output' => ['scriptid'],
					'groupids' => ['90021']
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['90021', '90020'],
					'result_keys' => ['scriptid']
				]
			],
			// host 90021 is in group 90021 that is child a group of 90020 and script from parent group is inherited
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90021']
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['90021', '90020'],
					'result_keys' => ['scriptid']
				]
			],
			// child host has 2 inherited scripts but only one of them may not be invoked on parent group
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'groupids' => ['90020']
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['90020'],
					'!has.scriptid' => ['90021'],
					'result_keys' => ['scriptid']
				]
			],
			// child group has 2 inherited scripts but only one of them may not be invoked on parent group host
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90020'],
					'groupids' => ['90021']
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['90020'],
					'!has.scriptid' => ['90021'],
					'result_keys' => ['scriptid']
				]
			],
			// selectHosts test
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'preservekeys' => true,
					'selectHosts' => ['hostid']
				],
				'expect' => [
					'error' => null,
					'has.scriptid:hostid' => [
						'90020' => ['90020', '90021', '90022', '90023'],
						'90021' => ['90021', '90022', '90023']
					],
					'result_keys' => ['hosts', 'scriptid']
				]
			],
			// selectHosts test
			// user has no write permission for group 90021 AND script 90021 requires that permission
			[
				'params' => [
					'__auth' => ['90000', 'zabbix'],
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'preservekeys' => true,
					'selectHosts' => ['hostid']
				],
				'expect' => [
					'error' => null,
					'has.scriptid:hostid' => [
						'90020' => ['90020', '90021', '90022', '90023'],
						'90021' => ['90022', '90023']
					],
					'!has.scriptid:hostid' => [
						'90020' => [],
						'90021' => ['90021']
					],
					'result_keys' => ['hosts', 'scriptid']
				]
			],
			// selectGroups test
			[
				'params' => [
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'preservekeys' => true,
					'selectGroups' => ['groupid']
				],
				'expect' => [
					'error' => null,
					'has.scriptid:groupid' => [
						'90020' => ['90020', '90021', '90022', '90023'],
						'90021' => ['90021', '90022', '90023']
					],
					'result_keys' => ['groups', 'scriptid']
				]
			],
			// selectGroups test
			// user has no write permission for group 90021, that group is not shown
			[
				'params' => [
					'__auth' => ['90000', 'zabbix'],
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'preservekeys' => true,
					'selectGroups' => ['groupid']
				],
				'expect' => [
					'error' => null,
					'has.scriptid:groupid' => [
						'90020' => ['90020', '90021', '90022', '90023'],
						'90021' => ['90022', '90023']
					],
					'!has.scriptid:groupid' => [
						'90020' => [],
						'90021' => ['90021']
					],
					'result_keys' => ['groups', 'scriptid']
				]
			],
			// selectGroups test
			// no extra output is present
			[
				'params' => [
					'__auth' => ['90000', 'zabbix'],
					'output' => ['scriptid'],
					'hostids' => ['90021'],
					'preservekeys' => true,
					'selectGroups' => ['flags']
				],
				'expect' => [
					'error' => null,
					'groupsObjectProperties' => ['flags'],
					'result_keys' => ['groups', 'scriptid']
				]
			],
			// Get scripts parameters.
			'Test get scripts parameters' => [
				'params' => [
					'__auth' => ['Admin', 'zabbix'],
					'output' => ['parameters'],
					'scriptids' => 59
				],
				'expect' => [
					'error' => null,
					'result_keys' => ['parameters'],
					'parameters' => [
						[
							'name' => 'param 1',
							'value' => 'value 1'
						],
						[
							'name' => 'param 2',
							'value' => 'value 2'
						]
					]
				]
			],
			// Filter webhooks.
			'Test filter webhooks' => [
				'params' => [
					'__auth' => ['Admin', 'zabbix'],
					'output' => [ 'scriptid', 'parameters'],
					'scriptids' => [59, 60],
					'filter' => ['type' => ZBX_SCRIPT_TYPE_WEBHOOK]
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['59'],
					'!has.scriptid' => ['60'],
					'result_keys' => ['scriptid', 'parameters'],
					'parameters' => [
						[
							'name' => 'param 1',
							'value' => 'value 1'
						],
						[
							'name' => 'param 2',
							'value' => 'value 2'
						]
					]
				]
			],
			// Filter IPMI.
			'Test filter IPMI' => [
				'params' => [
					'__auth' => ['Admin', 'zabbix'],
					'output' => ['scriptid'],
					'scriptids' => [59, 60],
					'filter' => ['type' => ZBX_SCRIPT_TYPE_IPMI]
				],
				'expect' => [
					'error' => null,
					'has.scriptid' => ['60'],
					'!has.scriptid' => ['59'],
					'result_keys' => ['scriptid']
				]
			]
		];
	}

	/**
	 * @dataProvider script_get
	 */
	public function testScripts_Get($params, $expect) {
		if (array_key_exists('__auth', $params)) {
			$this->authorize($params['__auth'][0], $params['__auth'][1]);
			unset($params['__auth']);
		}

		$response = $this->call('script.get', $params, $expect['error']);
		$this->enableAuthorization();

		if ($expect['error'] !== null) {
			return;
		}

		if (array_key_exists('has.scriptid', $expect)) {
			$ids = array_column($response['result'], 'scriptid');
			$this->assertEmpty(array_diff($expect['has.scriptid'], $ids));
		}

		if (array_key_exists('!has.scriptid', $expect)) {
			$ids = array_column($response['result'], 'scriptid');
			$this->assertEquals($expect['!has.scriptid'], array_diff($expect['!has.scriptid'], $ids));
		}

		if (array_key_exists('has.scriptid:hostid', $expect)) {
			foreach ($expect['has.scriptid:hostid'] as $scriptid => $hostids) {
				$this->assertTrue(array_key_exists($scriptid, $response['result']), 'expected script id '.$scriptid);
				$ids = array_column($response['result'][$scriptid]['hosts'], 'hostid');
				$this->assertEmpty(array_diff($hostids, $ids), 'Expected ids: '.implode(',', $hostids));
			}
		}

		if (array_key_exists('!has.scriptid:hostid', $expect)) {
			foreach ($expect['!has.scriptid:hostid'] as $scriptid => $hostids) {
				$this->assertTrue(array_key_exists($scriptid, $response['result']), 'expected script id '.$scriptid);
				$ids = array_column($response['result'][$scriptid]['hosts'], 'hostid');
				$this->assertEquals($hostids, array_diff($hostids, $ids));
			}
		}

		if (array_key_exists('has.scriptid:groupid', $expect)) {
			foreach ($expect['has.scriptid:groupid'] as $scriptid => $groupids) {
				$this->assertTrue(array_key_exists($scriptid, $response['result']), 'expected script id '.$scriptid);
				$ids = array_column($response['result'][$scriptid]['groups'], 'groupid');
				$this->assertEmpty(array_diff($groupids, $ids), 'Expected ids: '.implode(',', $groupids));
			}
		}

		if (array_key_exists('!has.scriptid:groupid', $expect)) {
			foreach ($expect['!has.scriptid:groupid'] as $scriptid => $groupids) {
				$this->assertTrue(array_key_exists($scriptid, $response['result']), 'expected script id '.$scriptid);
				$ids = array_column($response['result'][$scriptid]['groups'], 'groupid');
				$this->assertEquals($groupids, array_diff($groupids, $ids));
			}
		}

		if (array_key_exists('groupsObjectProperties', $expect)) {
			sort($expect['groupsObjectProperties']);
			foreach ($response['result'] as $script) {
				foreach ($script['groups'] as $group) {
					ksort($group);
					$this->assertEquals($expect['groupsObjectProperties'], array_keys($group));
				}
			}
		}

		if (array_key_exists('result_keys', $expect)) {
			foreach ($response['result'] as $script) {
				sort($expect['result_keys']);
				ksort($script);
				$this->assertEquals($expect['result_keys'], array_keys($script));
				if (array_key_exists('parameters', $expect)) {
					$this->assertEquals($expect['parameters'], $script['parameters']);
				}
			}
		}
	}

	/**
	 * @dataProvider script_create_data_invalid
	 * @dataProvider script_create_data_valid
	 */
	public function testScript_Create(array $scripts, $expected_error) {
		$result = $this->call('script.create', $scripts, $expected_error);

		// Accept single and multiple scripts just like API method. Work with multi-dimensional array in result.
		if (!array_key_exists(0, $scripts)) {
			$scripts = zbx_toArray($scripts);
		}

		if ($expected_error === null) {
			foreach ($result['result']['scriptids'] as $num => $id) {
				$db_script = CDBHelper::getRow(
					'SELECT s.scriptid,s.name,s.command,s.host_access,s.usrgrpid,s.groupid,s.description,'.
							's.confirmation,s.type,s.execute_on,s.timeout,s.scope,s.port,s.authtype,s.username,'.
							's.password,s.publickey,s.privatekey,s.menu_path'.
					' FROM scripts s'.
					' WHERE s.scriptid='.zbx_dbstr($id)
				);

				$db_script_parameters = CDBHelper::getAll(
					'SELECT sp.script_paramid,sp.name,sp.value'.
					' FROM script_param sp'.
					' WHERE sp.scriptid='.zbx_dbstr($id)
				);

				// Required fields.
				$this->assertNotEmpty($db_script['name']);
				$this->assertSame($db_script['name'], $scripts[$num]['name']);
				$this->assertEquals($db_script['type'], $scripts[$num]['type']);
				$this->assertNotEmpty($db_script['command']);
				$this->assertSame($db_script['command'], $scripts[$num]['command']);

				// Check scope.
				if (array_key_exists('scope', $scripts[$num])) {
					$this->assertEquals($db_script['scope'], $scripts[$num]['scope']);
				}
				else {
					$this->assertEquals($db_script['scope'], DB::getDefault('scripts', 'scope'));
				}

				// Check menu path.
				if ($db_script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
					$this->assertEmpty($db_script['menu_path']);
					$this->assertSame($db_script['usrgrpid'], '0');
					$this->assertEquals($db_script['host_access'], DB::getDefault('scripts', 'host_access'));
					$this->assertEmpty($db_script['confirmation']);
				}
				else {
					// Check menu path.
					if (array_key_exists('menu_path', $scripts[$num])) {
						$this->assertSame($db_script['menu_path'], $scripts[$num]['menu_path']);
					}
					else {
						$this->assertEmpty($db_script['menu_path']);
					}

					// Check user group.
					if (array_key_exists('usrgrpid', $scripts[$num])) {
						$this->assertSame($db_script['usrgrpid'], strval($scripts[$num]['usrgrpid']));
					}
					else {
						// Despite the default in DB is NULL, getting value from DB gets us 0 as string.
						$this->assertSame($db_script['usrgrpid'], '0');
					}

					// Check host access.
					if (array_key_exists('host_access', $scripts[$num])) {
						$this->assertEquals($db_script['host_access'], $scripts[$num]['host_access']);
					}
					else {
						$this->assertEquals($db_script['host_access'], DB::getDefault('scripts', 'host_access'));
					}

					// Check confirmation.
					if (array_key_exists('confirmation', $scripts[$num])) {
						$this->assertSame($db_script['confirmation'], $scripts[$num]['confirmation']);
					}
					else {
						$this->assertEmpty($db_script['confirmation']);
					}
				}

				// Optional common fields for all script types.
				if (array_key_exists('groupid', $scripts[$num])) {
					$this->assertSame($db_script['groupid'], strval($scripts[$num]['groupid']));
				}
				else {
					// Despite the default in DB is NULL, getting value from DB gets us 0 as string.
					$this->assertSame($db_script['groupid'], '0');
				}

				if (array_key_exists('description', $scripts[$num])) {
					$this->assertSame($db_script['description'], $scripts[$num]['description']);
				}
				else {
					$this->assertEmpty($db_script['description']);
				}

				if ($scripts[$num]['type']) {
					switch ($scripts[$num]['type']) {
						case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
							// Check execute on.
							if (array_key_exists('execute_on', $scripts[$num])) {
								$this->assertEquals($db_script['execute_on'], $scripts[$num]['execute_on']);
							}
							else {
								$this->assertEquals($db_script['execute_on'], DB::getDefault('scripts', 'execute_on'));
							}

							// Check other fields.
							$this->assertSame($db_script['timeout'], DB::getDefault('scripts', 'timeout'));
							$this->assertEmpty($db_script['port']);
							$this->assertEquals($db_script['authtype'], DB::getDefault('scripts', 'authtype'));
							$this->assertEmpty($db_script['username']);
							$this->assertEmpty($db_script['password']);
							$this->assertEmpty($db_script['publickey']);
							$this->assertEmpty($db_script['privatekey']);
							$this->assertEmpty($db_script_parameters);
							break;

						case ZBX_SCRIPT_TYPE_IPMI:
							$this->assertEquals($db_script['execute_on'], DB::getDefault('scripts', 'execute_on'));
							$this->assertSame($db_script['timeout'], DB::getDefault('scripts', 'timeout'));
							$this->assertEmpty($db_script['port']);
							$this->assertEquals($db_script['authtype'], DB::getDefault('scripts', 'authtype'));
							$this->assertEmpty($db_script['username']);
							$this->assertEmpty($db_script['password']);
							$this->assertEmpty($db_script['publickey']);
							$this->assertEmpty($db_script['privatekey']);
							$this->assertEmpty($db_script_parameters);
							break;

						case ZBX_SCRIPT_TYPE_SSH:
							// Check username.
							$this->assertNotEmpty($db_script['username']);
							$this->assertSame($db_script['username'], $scripts[$num]['username']);

							// Check port.
							if (array_key_exists('port', $scripts[$num])) {
								$this->assertSame($db_script['port'], strval($scripts[$num]['port']));
							}
							else {
								$this->assertEmpty($db_script['port']);
							}

							// Check auth type.
							if (array_key_exists('authtype', $scripts[$num])) {
								$this->assertEquals($db_script['authtype'], $scripts[$num]['authtype']);

								if ($scripts[$num]['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
									$this->assertNotEmpty($db_script['publickey']);
									$this->assertNotEmpty($db_script['privatekey']);
									$this->assertSame($db_script['publickey'], $scripts[$num]['publickey']);
									$this->assertSame($db_script['privatekey'], $scripts[$num]['privatekey']);
								}
								else {
									$this->assertEmpty($db_script['publickey']);
									$this->assertEmpty($db_script['privatekey']);
								}
							}
							else {
								$this->assertEquals($db_script['authtype'], DB::getDefault('scripts', 'authtype'));
								$this->assertEmpty($db_script['publickey']);
								$this->assertEmpty($db_script['privatekey']);
							}

							// Check password.
							if (array_key_exists('password', $scripts[$num])) {
								$this->assertSame($db_script['password'], $scripts[$num]['password']);
							}
							else {
								$this->assertEmpty($db_script['password']);
							}

							// Check other fields.
							$this->assertEquals($db_script['execute_on'], DB::getDefault('scripts', 'execute_on'));
							$this->assertSame($db_script['timeout'], DB::getDefault('scripts', 'timeout'));
							$this->assertEmpty($db_script_parameters);
							break;

						case ZBX_SCRIPT_TYPE_TELNET:
							// Check username.
							$this->assertNotEmpty($db_script['username']);
							$this->assertSame($db_script['username'], $scripts[$num]['username']);

							// Check password.
							if (array_key_exists('password', $scripts[$num])) {
								$this->assertSame($db_script['password'], $scripts[$num]['password']);
							}
							else {
								$this->assertEmpty($db_script['password']);
							}

							// Check port.
							if (array_key_exists('port', $scripts[$num])) {
								$this->assertSame($db_script['port'], strval($scripts[$num]['port']));
							}
							else {
								$this->assertEmpty($db_script['port']);
							}

							// Check other fields.
							$this->assertEquals($db_script['execute_on'], DB::getDefault('scripts', 'execute_on'));
							$this->assertSame($db_script['timeout'], DB::getDefault('scripts', 'timeout'));
							$this->assertEquals($db_script['authtype'], DB::getDefault('scripts', 'authtype'));
							$this->assertEmpty($db_script['publickey']);
							$this->assertEmpty($db_script['privatekey']);
							$this->assertEmpty($db_script_parameters);
							break;

						case ZBX_SCRIPT_TYPE_WEBHOOK:
							// Check timeout.
							if (array_key_exists('timeout', $scripts[$num])) {
								$this->assertSame($db_script['timeout'], $scripts[$num]['timeout']);
							}
							else {
								$this->assertSame($db_script['timeout'], DB::getDefault('scripts', 'timeout'));
							}

							// Check parameters.
							if (array_key_exists('parameters', $scripts[$num])) {
								if ($scripts[$num]['parameters']) {
									// Check newly added parameters.
									$this->assertNotEmpty($db_script_parameters);

									foreach ($scripts[$num]['parameters'] as $sp_num => $parameter) {
										$db_script_parameter = CDBHelper::getRow(
											'SELECT sp.script_paramid,sp.name,sp.value'.
											' FROM script_param sp'.
											' WHERE sp.scriptid='.zbx_dbstr($id).
												' AND sp.name='.zbx_dbstr($parameter['name'])
										);

										$this->assertNotEmpty($db_script_parameter['name']);
										$this->assertSame($db_script_parameter['name'], $parameter['name']);
										$this->assertSame($db_script_parameter['value'], $parameter['value']);
									}
								}
								else {
									// Check that parameters are removed.
									$this->assertEmpty($db_script_parameters);
								}
							}
							else {
								// Check that parameters not even added.
								$this->assertEmpty($db_script_parameters);
							}

							// Check other fields.
							$this->assertEquals($db_script['execute_on'], DB::getDefault('scripts', 'execute_on'));
							$this->assertEmpty($db_script['port']);
							$this->assertEquals($db_script['authtype'], DB::getDefault('scripts', 'authtype'));
							$this->assertEmpty($db_script['username']);
							$this->assertEmpty($db_script['password']);
							$this->assertEmpty($db_script['publickey']);
							$this->assertEmpty($db_script['privatekey']);
							break;
					}
				}
			}
		}
	}

	public static function script_update_data_invalid() {
		return [
			// Check script ID.
			'Test missing ID' => [
				'script' => [[
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "scriptid" is missing.'
			],
			'Test empty ID' => [
				'script' => [[
					'scriptid' => '',
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			'Test invalid ID (string)' => [
				'script' => [[
					'scriptid' => 'abc',
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			'Test invalid ID (decimal)' => [
				'script' => [[
					'scriptid' => '1.1',
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'expected_error' => 'Invalid parameter "/1/scriptid": a number is expected.'
			],
			'Test invalid ID (non-existent)' => [
				'script' => [[
					'scriptid' => 123456,
					'name' => 'API updated script',
					'command' => 'reboot'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test same ID' => [
				'script' => [
					[
						'scriptid' => 15,
						'name' => 'Scripts with the same id 1'
					],
					[
						'scriptid' => 15,
						'name' => 'Scripts with the same id 2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (scriptid)=(15) already exists.'
			],
			// Check script name.
			'Test empty name' => [
				'script' => [[
					'scriptid' => 15,
					'name' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test existing name' => [
				'script' => [[
					'scriptid' => 15,
					'name' => 'Ping'
				]],
				'expected_error' => 'Script "Ping" already exists.'
			],
			'Test same name' => [
				'script' => [
					[
						'scriptid' => 15,
						'name' => 'Scripts with the same name'
					],
					[
						'scriptid' => 16,
						'name' => 'Scripts with the same name'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(Scripts with the same name) already exists.'
			],
			// Check script command.
			'Test empty command' => [
				'script' => [[
					'scriptid' => 15,
					'command' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/command": cannot be empty.'
			],
			// Check script type.
			'Test invalid type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ''
				],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type (string)' => [
				'script' => [
					'scriptid' => 15,
					'type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/type": an integer is expected.'
			],
			'Test invalid type' => [
				'script' => [
					'scriptid' => 15,
					'type' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of '.
					implode(', ', [ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT, ZBX_SCRIPT_TYPE_IPMI, ZBX_SCRIPT_TYPE_SSH,
						ZBX_SCRIPT_TYPE_TELNET, ZBX_SCRIPT_TYPE_WEBHOOK
					]).'.'
			],
			// Check script scope.
			'Test invalid scope (empty)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ''
				],
				'expected_error' => 'Invalid parameter "/1/scope": an integer is expected.'
			],
			'Test invalid scope (string)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/scope": an integer is expected.'
			],
			'Test invalid scope' => [
				'script' => [
					'scriptid' => 15,
					'scope' => 0
				],
				'expected_error' => 'Invalid parameter "/1/scope": value must be one of '.
					implode(', ', [ZBX_SCRIPT_SCOPE_ACTION, ZBX_SCRIPT_SCOPE_HOST, ZBX_SCRIPT_SCOPE_EVENT]).'.'
			],
			'Test scope change assigned to action' => [
				'script' => [
					'scriptid' => 11,
					'scope' => ZBX_SCRIPT_SCOPE_HOST
				],
				'expected_error' => 'Cannot update script scope. Script "API script in action" is used in action "API action with script".'
			],
			// Check script menu path.
			'Test invalid menu_path for host scope' => [
				'script' => [
					'scriptid' => 16,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test invalid menu_path for event scope' => [
				'script' => [
					'scriptid' => 17,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test invalid menu_path for host scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test invalid menu_path for event scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1/menu_path": directory cannot be empty.'
			],
			'Test unexpected menu_path for action scope (empty)' => [
				'script' => [
					'scriptid' => 15,
					'menu_path' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			'Test unexpected menu_path for action scope' => [
				'script' => [
					'scriptid' => 15,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			'Test unexpected menu_path for action scope (change of scope)' => [
				'script' => [
					'scriptid' => 16,
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'menu_path' => 'folder1/folder2/'.'/folder4'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "menu_path".'
			],
			// Check script host access.
			'Test unexpected host_access for action scope (string)' => [
				'script' => [
					'scriptid' => 15,
					'host_access' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test unexpected host_access for action scope (int)' => [
				'script' => [
					'scriptid' => 15,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "host_access".'
			],
			'Test unexpected host_access for action scope (change of scope)' => [
				'script' => [
					'scriptid' => 16,
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "host_access".'
			],
			'Test invalid host_access for host scope (empty)' => [
				'script' => [
					'scriptid' => 16,
					'host_access' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for host scope (string)' => [
				'script' => [
					'scriptid' => 16,
					'host_access' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for host scope' => [
				'script' => [
					'scriptid' => 16,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			'Test invalid host_access for host scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			'Test invalid host_access for event scope (empty)' => [
				'script' => [
					'scriptid' => 17,
					'host_access' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for event scope (string)' => [
				'script' => [
					'scriptid' => 17,
					'host_access' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host_access": an integer is expected.'
			],
			'Test invalid host_access for event scope' => [
				'script' => [
					'scriptid' => 17,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			'Test invalid host_access for event scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'host_access' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/host_access": value must be one of '.
					implode(', ', [PERM_READ, PERM_READ_WRITE]).'.'
			],
			// Check script user group.
			'Test unexpected usrgrpid for action scope (string)' => [
				'script' => [
					'scriptid' => 15,
					'usrgrpid' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test unexpected usrgrpid for action scope (int)' => [
				'script' => [
					'scriptid' => 15,
					'usrgrpid' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			'Test unexpected usrgrpid for action scope (change of scope)' => [
				'script' => [
					'scriptid' => 16,
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'usrgrpid' => 999999
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "usrgrpid".'
			],
			'Test invalid usrgrpid for host scope (empty)' => [
				'script' => [
					'scriptid' => 16,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for host scope (string)' => [
				'script' => [
					'scriptid' => 16,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for host scope' => [
				'script' => [
					'scriptid' => 16,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			'Test invalid usrgrpid for host scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_HOST,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			'Test invalid usrgrpid for event scope (empty)' => [
				'script' => [
					'scriptid' => 17,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for event scope (string)' => [
				'script' => [
					'scriptid' => 17,
					'usrgrpid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/usrgrpid": a number is expected.'
			],
			'Test invalid usrgrpid for event scope' => [
				'script' => [
					'scriptid' => 17,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			'Test invalid usrgrpid for event scope (change of scope)' => [
				'script' => [
					'scriptid' => 15,
					'scope' => ZBX_SCRIPT_SCOPE_EVENT,
					'usrgrpid' => 999999
				],
				'expected_error' => 'User group with ID "999999" is not available.'
			],
			// Check script confirmation.
			'Test unexpected confirmation for action scope' => [
				'script' => [
					'scriptid' => 15,
					'confirmation' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "confirmation".'
			],
			'Test unexpected confirmation for action scope (change of scope)' => [
				'script' => [
					'scriptid' => 16,
					'scope' => ZBX_SCRIPT_SCOPE_ACTION,
					'confirmation' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "confirmation".'
			],
			// Check script host group.
			'Test invalid host group (empty)' => [
				'script' => [
					'scriptid' => 15,
					'groupid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			'Test invalid host group (string)' => [
				'script' => [
					'scriptid' => 15,
					'groupid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/groupid": a number is expected.'
			],
			'Test invalid host group' => [
				'script' => [
					'scriptid' => 15,
					'groupid' => 999999
				],
				'expected_error' => 'Host group with ID "999999" is not available.'
			],
			// Check unexpected fields in script.
			'Test unexpected field' => [
				'script' => [
					'scriptid' => 15,
					'unexpected_field' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "unexpected_field".'
			],
			// Check script execute_on.
			'Test invalid execute_on (empty)' => [
				'script' => [
					'scriptid' => 15,
					'execute_on' => ''
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test invalid execute_on (string)' => [
				'script' => [
					'scriptid' => 15,
					'execute_on' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test invalid execute_on' => [
				'script' => [
					'scriptid' => 15,
					'execute_on' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/execute_on": value must be one of '.
					implode(', ', [ZBX_SCRIPT_EXECUTE_ON_AGENT, ZBX_SCRIPT_EXECUTE_ON_SERVER,
						ZBX_SCRIPT_EXECUTE_ON_PROXY
					]).'.'
			],
			'Test unexpected execute_on field for IPMI type (empty)' => [
				'script' => [
					'scriptid' => 20,
					'execute_on' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/execute_on": an integer is expected.'
			],
			'Test unexpected execute_on field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for SSH type' => [
				'script' => [
					'scriptid' => 21,
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			'Test unexpected execute_on field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "execute_on".'
			],
			// Check script port.
			'Test invalid port (string)' => [
				'script' => [
					'scriptid' => 21,
					'port' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/port": an integer is expected.'
			],
			'Test invalid port' => [
				'script' => [
					'scriptid' => 21,
					'port' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/port": value must be one of '.
					ZBX_MIN_PORT_NUMBER.'-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test unexpected port field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'port' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			'Test unexpected port field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'port' => 0
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "port".'
			],
			// Check script auth type.
			'Test invalid authtype (empty)' => [
				'script' => [
					'scriptid' => 21,
					'authtype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test invalid authtype (string)' => [
				'script' => [
					'scriptid' => 21,
					'authtype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test invalid authtype' => [
				'script' => [
					'scriptid' => 21,
					'authtype' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/authtype": value must be one of '.
					implode(', ', [ITEM_AUTHTYPE_PASSWORD, ITEM_AUTHTYPE_PUBLICKEY]).'.'
			],
			'Test unexpected authtype field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'authtype' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test unexpected authtype field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			'Test unexpected authtype field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'authtype' => ITEM_AUTHTYPE_PASSWORD
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "authtype".'
			],
			// Check script username.
			'Test empty username for SSH type' => [
				'script' => [
					'scriptid' => 21,
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			'Test empty username for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			'Test unexpected username field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			'Test unexpected username field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'username' => 'John'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "username".'
			],
			// Check script password.
			'Test unexpected password field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'password' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			'Test unexpected password field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'password' => 'psswd'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "password".'
			],
			// Check script public key.
			'Test empty publickey' => [
				'script' => [
					'scriptid' => 22,
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1/publickey": cannot be empty.'
			],
			'Test unexpected publickey for SSH password type' => [
				'script' => [
					'scriptid' => 21,
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test unexpected publickey field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			// Check script private key.
			'Test empty privatekey' => [
				'script' => [
					'scriptid' => 22,
					'privatekey' => ''
				],
				'expected_error' => 'Invalid parameter "/1/privatekey": cannot be empty.'
			],
			'Test unexpected privatekey for SSH password type' => [
				'script' => [
					'scriptid' => 21,
					'privatekey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'privatekey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			'Test unexpected privatekey field for Javascript type' => [
				'script' => [
					'scriptid' => 24,
					'privatekey' => 'secretprivkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "privatekey".'
			],
			// Check script timeout.
			'Test invalid timeout' => [
				'script' => [
					'scriptid' => 24,
					'timeout' => '100'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": value must be one of 1-'.SEC_PER_MIN.'.'
			],
			'Test unsupported macros in timeout' => [
				'script' => [
					'scriptid' => 24,
					'timeout' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a time unit is expected.'
			],
			'Test unexpected timeout field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'timeout' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for SSH type' => [
				'script' => [
					'scriptid' => 21,
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			'Test unexpected timeout field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'timeout' => '30s'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "timeout".'
			],
			// Check script parameters.
			'Test invalid parameters' => [
				'script' => [
					'scriptid' => 25,
					'parameters' => ''
				],
				'expected_error' => 'Invalid parameter "/1/parameters": an array is expected.'
			],
			'Test missing name in parameters' => [
				'script' => [
					'scriptid' => 25,
					'parameters' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1": the parameter "name" is missing.'
			],
			'Test empty name in parameters' => [
				'script' => [
					'scriptid' => 25,
					'parameters' => [[
						'name' => ''
					]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1/name": cannot be empty.'
			],
			'Test missing value in parameters' => [
				'script' => [
					'scriptid' => 25,
					'parameters' => [[
						'name' => 'param x'
					]]
				],
				'expected_error' => 'Invalid parameter "/1/parameters/1": the parameter "value" is missing.'
			],
			'Test unexpected parameters field for custom script type (empty)' => [
				'script' => [
					'scriptid' => 15,
					'parameters' => []
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for custom script type (empty sub-params)' => [
				'script' => [
					'scriptid' => 15,
					'parameters' => [[]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for custom script type (string)' => [
				'script' => [
					'scriptid' => 15,
					'parameters' => ''
				],
				// Must be changed in future if CApiInputValidator is improved.
				'expected_error' => 'Invalid parameter "/1/parameters": an array is expected.'
			],
			'Test unexpected parameters field for custom script type' => [
				'script' => [
					'scriptid' => 15,
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for IPMI type' => [
				'script' => [
					'scriptid' => 20,
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for SSH type' => [
				'script' => [
					'scriptid' => 21,
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			'Test unexpected parameters field for Telnet type' => [
				'script' => [
					'scriptid' => 23,
					'parameters' => [[
						'name' => 'param1',
						'value' => 'value1'
					]]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "parameters".'
			],
			// Check required fields on type change. (Changing Custom to IPMI or Javascript does not require any other required fields).
			'Test custom change to SSH (missing username)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_SSH
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "username" is missing.'
			],
			'Test custom change to SSH (empty username)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'username' => ''
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			'Test custom change to SSH (unexpected publickey, empty)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'username' => 'John',
					'publickey' => ''
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test custom change to SSH (unexpected publickey)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'username' => 'John',
					'publickey' => 'secretpubkey'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "publickey".'
			],
			'Test custom change to SSH (missing publickey)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_SSH,
					'username' => 'John',
					'authtype' => ITEM_AUTHTYPE_PUBLICKEY
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "publickey" is missing.'
			],
			'Test custom change to Telnet (missing username)' => [
				'script' => [
					'scriptid' => 15,
					'type' => ZBX_SCRIPT_TYPE_TELNET
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "username" is missing.'
			]
		];
	}

	public static function script_update_data_valid() {
		return [
			'Test successful custom script update without changes' => [
				'script' => [
					[
						'scriptid' => 6
					]
				],
				'expected_error' => null
			],
			'Test successful multiple custom script updates' => [
				'script' => [
					[
						'scriptid' => 6,
						'name' => 'API script custom execute on agent (action scope) updated',
						'command' => 'reboot server 1'
					],
					[
						'scriptid' => 7,
						'name' => 'API script custom execute on agent (host scope) updated',
						'command' => 'reboot server 2'
					]
				],
				'expected_error' => null
			],
			// Check existing script field updates.
			'Test successful custom script update' => [
				'script' => [
					[
						'scriptid' => 15,
						'name' => 'Апи скрипт обнавлён утф-8',
						'command' => 'reboot',
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
						'groupid' => 4,
						'description' => 'Check successful update'
					]
				],
				'expected_error' => null
			],
			'Test successful custom script update with menu path' => [
				'script' => [
					[
						'scriptid' => 16,
						'name' => 'API custom script update with menu path',
						'command' => 'reboot',
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'menu_path' => '/root/folder1/'
					]
				],
				'expected_error' => null
			],
			'Test successful IPMI update' => [
				'script' => [
					[
						'scriptid' => 20,
						'name' => 'API script IPMI update',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?'
					]
				],
				'expected_error' => null
			],
			'Test successful SSH update with password' => [
				'script' => [
					[
						'scriptid' => 21,
						'name' => 'API script SSH password update',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'port' => '{$MACRO}',
						'username' => 'Jill',
						'password' => 'Barry'
					]
				],
				'expected_error' => null
			],
			'Test successful SSH update with public key' => [
				'script' => [
					[
						'scriptid' => 22,
						'name' => 'API script SSH public key update',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'port' => '{$MACRO}',
						'username' => 'Jill',
						'password' => 'Barry',
						'publickey' => 'updatedpubkey',
						'privatekey' => 'updatedprivkey'
					]
				],
				'expected_error' => null
			],
			'Test successful SSH update and authtype change to password' => [
				'script' => [
					[
						// "username" and "password" and the rest of fields that are not given are left unchanged, but publickey and privatekey should be cleared.
						'scriptid' => 22,
						'authtype' => ITEM_AUTHTYPE_PASSWORD,
						'name' => 'API script SSH public key update and change to password'
					]
				],
				'expected_error' => null
			],
			'Test successful SSH update and authtype change to public key' => [
				'script' => [
					[
						// Fields that are not given, are not changed, but publickey and privatekey are not added.
						'scriptid' => 21,
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'name' => 'API script SSH password update and change to public key',
						'publickey' => 'updatedpubkey',
						'privatekey' => 'updatedprivkey'
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet update' => [
				'script' => [
					[
						'scriptid' => 23,
						'name' => 'API script Telnet update',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'port' => '{$MACRO}',
						'username' => 'Barry'
					]
				],
				'expected_error' => null
			],
			// Check Javascript parameter changes add, remove and update.
			'Test successful Javascript update by adding parameters' => [
				'script' => [
					[
						'scriptid' => 24,
						'name' => 'API script Webhook no params updated with params',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'parameters' => [
							[
								'name' => 'param1',
								'value' => 'value1'
							],
							[
								'name' => 'param2',
								'value' => 'value2'
							]
						]
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript update by removing parameters' => [
				'script' => [
					[
						'scriptid' => 25,
						'name' => 'API script Webhook with params updated but no more params',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'parameters' => []
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript update by changing parameters' => [
				'script' => [
					[
						'scriptid' => 26,
						'name' => 'API script Webhook with params to change updated with new params',
						'command' => 'reboot',
						'host_access' => PERM_READ_WRITE,
						'usrgrpid' => 7,
						'groupid' => 4,
						'description' => 'Check successful update',
						'confirmation' => 'Do you want to reboot it?',
						'parameters' => [
							[
								'name' => 'new_param_1',
								'value' => 'new_value_1'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Check custom script type change and new parameters. "execute_on" must reset to default.
			'Test successful custom script type change to IPMI' => [
				'script' => [
					[
						// Fields that are not given should stay the same, but execute_on should change to default.
						'scriptid' => 27,
						'name' => 'API script custom changed to IPMI',
						'type' => ZBX_SCRIPT_TYPE_IPMI
					]
				],
				'expected_error' => null
			],
			'Test successful custom script type change to SSH with password' => [
				'script' => [
					[
						'scriptid' => 28,
						'name' => 'API script custom changed to SSH with password',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful custom script type change to SSH with public key' => [
				'script' => [
					[
						'scriptid' => 29,
						'name' => 'API script custom changed to SSH with public key',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456,
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'publickey' => 'newsecretepublickey',
						'privatekey' => 'newsecreteprivatekey'
					]
				],
				'expected_error' => null
			],
			'Test successful custom script type change to Telnet' => [
				'script' => [
					[
						'scriptid' => 30,
						'name' => 'API script custom changed to Telnet',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful custom script type change to Javascript' => [
				'script' => [
					[
						'scriptid' => 31,
						'name' => 'API script custom changed to Javascript',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'timeout' => '60s',
						'parameters' => [
							[
								'name' => 'username',
								'value' => 'Admin'
							],
							[
								'name' => 'password',
								'value' => 'zabbix'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Check IPMI type change and new parameters. Just check if new fields are properly written to DB.
			'Test successful IPMI type change to custom script' => [
				'script' => [
					[
						'scriptid' => 32,
						'name' => 'API script IPMI changed to custom script (with execute on agent)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
					]
				],
				'expected_error' => null
			],
			'Test successful IPMI type change to SSH with password' => [
				'script' => [
					[
						'scriptid' => 33,
						'name' => 'API script IPMI changed to SSH with password',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful IPMI type change to SSH with public key' => [
				'script' => [
					[
						'scriptid' => 34,
						'name' => 'API script IPMI changed to SSH with public key',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456,
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'publickey' => 'newsecretepublickey',
						'privatekey' => 'newsecreteprivatekey'
					]
				],
				'expected_error' => null
			],
			'Test successful IPMI type change to Telnet' => [
				'script' => [
					[
						'scriptid' => 35,
						'name' => 'API script IPMI changed to Telnet',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful IPMI type change to Javascript' => [
				'script' => [
					[
						'scriptid' => 36,
						'name' => 'API script IPMI changed to Javascript',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'timeout' => '60s',
						'parameters' => [
							[
								'name' => 'username',
								'value' => 'Admin'
							],
							[
								'name' => 'password',
								'value' => 'zabbix'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Check SSH type change and new parameters. "username", "password" and "port" can remain for Telnet, but "publickey" and "privatekey" must be removed.
			'Test successful SSH with password type change to custom script' => [
				'script' => [
					[
						'scriptid' => 37,
						'name' => 'API script SSH with password changed to custom script (with execute on agent)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with password type change to IPMI' => [
				'script' => [
					[
						'scriptid' => 38,
						'name' => 'API script SSH with password changed to IPMI',
						'type' => ZBX_SCRIPT_TYPE_IPMI
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with password type change to Telnet' => [
				'script' => [
					[
						'scriptid' => 39,
						'name' => 'API script SSH with password changed to Telnet',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with password type change to Javascript' => [
				'script' => [
					[
						'scriptid' => 40,
						'name' => 'API script SSH with password changed to Javascript',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'timeout' => '60s',
						'parameters' => [
							[
								'name' => 'username',
								'value' => 'Admin'
							],
							[
								'name' => 'password',
								'value' => 'zabbix'
							]
						]
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with public key type change to custom script' => [
				'script' => [
					[
						'scriptid' => 41,
						'name' => 'API script SSH with public key changed to custom script (with execute on agent)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with public key type change to IPMI' => [
				'script' => [
					[
						'scriptid' => 42,
						'name' => 'API script SSH with public key changed to IPMI',
						'type' => ZBX_SCRIPT_TYPE_IPMI
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with public key type change to Telnet' => [
				'script' => [
					[
						'scriptid' => 43,
						'name' => 'API script SSH with public key changed to Telnet',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful SSH with public key type change to Javascript' => [
				'script' => [
					[
						'scriptid' => 44,
						'name' => 'API script SSH with public key changed to Javascript',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'timeout' => '60s',
						'parameters' => [
							[
								'name' => 'username',
								'value' => 'Admin'
							],
							[
								'name' => 'password',
								'value' => 'zabbix'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Check Telnet type change and new parameters. "username", "password" and "port" can remain for SSH, and other fields should be properly written to DB.
			'Test successful Telnet type change to custom script' => [
				'script' => [
					[
						'scriptid' => 45,
						'name' => 'API script Telnet changed to custom script (with execute on agent)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet type change to SSH with password' => [
				'script' => [
					[
						'scriptid' => 46,
						'name' => 'API script Telnet changed to SSH with password',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet type change to SSH with public key' => [
				'script' => [
					[
						'scriptid' => 47,
						'name' => 'API script Telnet changed to SSH with public key',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456,
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'publickey' => 'newsecretepublickey',
						'privatekey' => 'newsecreteprivatekey'
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet type change to IPMI' => [
				'script' => [
					[
						'scriptid' => 48,
						'name' => 'API script Telnet changed to IPMI',
						'type' => ZBX_SCRIPT_TYPE_IPMI
					]
				],
				'expected_error' => null
			],
			'Test successful Telnet type change to Javascript' => [
				'script' => [
					[
						'scriptid' => 49,
						'name' => 'API script Telnet changed to Javascript',
						'type' => ZBX_SCRIPT_TYPE_WEBHOOK,
						'timeout' => '60s',
						'parameters' => [
							[
								'name' => 'username',
								'value' => 'Admin'
							],
							[
								'name' => 'password',
								'value' => 'zabbix'
							]
						]
					]
				],
				'expected_error' => null
			],
			// Check Javascript type change and new parameters. "parameters" should be removed and other fields should be properly written to DB.
			'Test successful Javascript type change to custom script' => [
				'script' => [
					[
						'scriptid' => 50,
						'name' => 'API script Javascript changed to custom script (with execute on agent)',
						'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
						'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript type change to SSH with password' => [
				'script' => [
					[
						'scriptid' => 51,
						'name' => 'API script Javascript changed to SSH with password',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript type change to SSH with public key' => [
				'script' => [
					[
						'scriptid' => 52,
						'name' => 'API script Javascript changed to SSH with public key',
						'type' => ZBX_SCRIPT_TYPE_SSH,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456,
						'authtype' => ITEM_AUTHTYPE_PUBLICKEY,
						'publickey' => 'newsecretepublickey',
						'privatekey' => 'newsecreteprivatekey'
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript type change to IPMI' => [
				'script' => [
					[
						'scriptid' => 53,
						'name' => 'API script Javascript changed to IPMI',
						'type' => ZBX_SCRIPT_TYPE_IPMI
					]
				],
				'expected_error' => null
			],
			'Test successful Javascript type change to Telnet' => [
				'script' => [
					[
						'scriptid' => 54,
						'name' => 'API script Javascript changed to Telnet',
						'type' => ZBX_SCRIPT_TYPE_TELNET,
						'username' => 'Admin',
						'password' => 'zabbix',
						'port' => 456
					]
				],
				'expected_error' => null
			],
			// Check scope field update.
			'Test successful parameter update of host scope' => [
				'script' => [
					[
						'scriptid' => 56,
						'menu_path' => '/new_folder1/new_folder2/',
						'usrgrpid' => 7,
						'confirmation' => 'confirmation text updated',
						'host_access' => PERM_READ_WRITE
					]
				],
				'expected_error' => null
			],
			'Test successful parameter update of event scope' => [
				'script' => [
					[
						'scriptid' => 57,
						'menu_path' => '/new_folder1/new_folder2/',
						'usrgrpid' => 7,
						'confirmation' => 'confirmation text updated',
						'host_access' => PERM_READ_WRITE
					]
				],
				'expected_error' => null
			],
			'Test successful parameter update of action scope (scope change)' => [
				'script' => [
					[
						'scriptid' => 55,
						'scope' => ZBX_SCRIPT_SCOPE_HOST,
						'menu_path' => '/new_folder1/new_folder2/',
						'usrgrpid' => 7,
						'confirmation' => 'confirmation text updated',
						'host_access' => PERM_READ_WRITE
					]
				],
				'expected_error' => null
			],
			'Test successful parameter reset when scope changes to action' => [
				'script' => [
					[
						'scriptid' => 58,
						'scope' => ZBX_SCRIPT_SCOPE_ACTION
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider script_update_data_invalid
	 * @dataProvider script_update_data_valid
	 */
	public function testScript_Update($scripts, $expected_error) {
		if ($expected_error === null) {
			// Before updating, collect old scripts and script parameters for Javascript type.
			$scriptids = [];

			if (array_key_exists(0, $scripts)) {
				foreach ($scripts as $script) {
					$scriptids[$script['scriptid']] = true;
				}
			}
			else {
				$scriptids[$scripts['scriptid']] = true;
			}

			$db_scripts = CDBHelper::getAll(
				'SELECT s.scriptid,s.name,s.command,s.type,s.host_access,s.usrgrpid,s.groupid,s.description,'.
					's.confirmation,s.type,s.execute_on,s.timeout,s.scope,s.port,s.authtype,s.username,s.password,'.
					's.publickey,s.privatekey,s.menu_path'.
				' FROM scripts s'.
				' WHERE '.dbConditionId('s.scriptid', array_keys($scriptids)).
				' ORDER BY s.scriptid ASC'
			);
			$db_scripts = zbx_toHash($db_scripts, 'scriptid');

			$webhook_scriptids = [];
			$db_scripts_parameters = [];

			foreach ($db_scripts as $db_script) {
				if ($db_script['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
					$webhook_scriptids[$db_script['scriptid']] = true;
				}
			}

			if ($webhook_scriptids) {
				$db_scripts_parameters = CDBHelper::getAll(
					'SELECT sp.script_paramid,sp.scriptid,sp.name,sp.value'.
					' FROM script_param sp'.
					' WHERE '.dbConditionId('sp.scriptid', array_keys($webhook_scriptids)).
					' ORDER BY sp.script_paramid ASC'
				);
			}

			$this->call('script.update', $scripts, $expected_error);

			$db_upd_scripts = CDBHelper::getAll(
				'SELECT s.scriptid,s.name,s.command,s.type,s.host_access,s.usrgrpid,s.groupid,s.description,'.
					's.confirmation,s.type,s.execute_on,s.timeout,s.scope,s.port,s.authtype,s.username,s.password,'.
					's.publickey,s.privatekey,s.menu_path'.
				' FROM scripts s'.
				' WHERE '.dbConditionId('s.scriptid', array_keys($scriptids)).
				' ORDER BY s.scriptid ASC'
			);
			$db_upd_scripts = zbx_toHash($db_upd_scripts, 'scriptid');

			$webhook_scriptids = [];
			$db_upd_scripts_parameters = [];

			foreach ($db_upd_scripts as $db_script) {
				if ($db_script['type'] == ZBX_SCRIPT_TYPE_WEBHOOK) {
					$webhook_scriptids[$db_script['scriptid']] = true;
				}
			}

			if ($webhook_scriptids) {
				$db_upd_scripts_parameters = CDBHelper::getAll(
					'SELECT sp.script_paramid,sp.scriptid,sp.name,sp.value'.
					' FROM script_param sp'.
					' WHERE '.dbConditionId('sp.scriptid', array_keys($webhook_scriptids)).
					' ORDER BY sp.script_paramid ASC'
				);
			}

			// Accept single and multiple scripts just like API method. Work with multi-dimensional array in result.
			if (!array_key_exists(0, $scripts)) {
				$scripts = zbx_toArray($scripts);
			}

			// Compare records from DB before and after API call.
			foreach ($scripts as $script) {
				// Old record and parameters.
				$db_script = $db_scripts[$script['scriptid']];
				$db_script['parameters'] = [];

				foreach ($db_scripts_parameters as $db_script_parameter) {
					if (bccomp($db_script_parameter['scriptid'], $script['scriptid']) == 0) {
						$db_script['parameters'][] = [
							'name' => $db_script_parameter['name'],
							'value' => $db_script_parameter['value']
						];

						CTestArrayHelper::usort($db_script['parameters'], ['name']);
					}
				}

				// New record and parameters.
				$db_upd_script = $db_upd_scripts[$script['scriptid']];
				$db_upd_script['parameters'] = [];

				foreach ($db_upd_scripts_parameters as $db_upd_script_parameter) {
					if (bccomp($db_upd_script_parameter['scriptid'], $script['scriptid']) == 0) {
						$db_upd_script['parameters'][] = [
							'name' => $db_upd_script_parameter['name'],
							'value' => $db_upd_script_parameter['value']
						];

						CTestArrayHelper::usort($db_upd_script['parameters'], ['name']);
					}
				}

				// Check name.
				$this->assertNotEmpty($db_upd_script['name']);
				if (array_key_exists('name', $script)) {
					$this->assertSame($db_upd_script['name'], $script['name']);
				}
				else {
					$this->assertSame($db_script['name'], $db_upd_script['name']);
				}

				// Check type.
				if (array_key_exists('type', $script)) {
					$this->assertEquals($db_upd_script['type'], $script['type']);

					if ($db_script['type'] != $db_upd_script['type']) {
						switch ($db_upd_script['type']) {
							case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
								// Check execute on.
								if (array_key_exists('execute_on', $script)) {
									$this->assertEquals($db_upd_script['execute_on'], $script['execute_on']);
								}
								else {
									$this->assertEquals($db_upd_script['execute_on'],
										DB::getDefault('scripts', 'execute_on')
									);
								}

								// Check other fields.
								$this->assertSame($db_upd_script['timeout'], DB::getDefault('scripts', 'timeout'));
								$this->assertEmpty($db_upd_script['port']);
								$this->assertEquals($db_upd_script['authtype'], DB::getDefault('scripts', 'authtype'));
								$this->assertEmpty($db_upd_script['username']);
								$this->assertEmpty($db_upd_script['password']);
								$this->assertEmpty($db_upd_script['publickey']);
								$this->assertEmpty($db_upd_script['privatekey']);
								$this->assertEmpty($db_upd_script['parameters']);
								break;

							case ZBX_SCRIPT_TYPE_IPMI:
								$this->assertEquals($db_upd_script['execute_on'],
									DB::getDefault('scripts', 'execute_on')
								);
								$this->assertSame($db_upd_script['timeout'], DB::getDefault('scripts', 'timeout'));
								$this->assertEmpty($db_upd_script['port']);
								$this->assertEquals($db_upd_script['authtype'], DB::getDefault('scripts', 'authtype'));
								$this->assertEmpty($db_upd_script['username']);
								$this->assertEmpty($db_upd_script['password']);
								$this->assertEmpty($db_upd_script['publickey']);
								$this->assertEmpty($db_upd_script['privatekey']);
								$this->assertEmpty($db_upd_script['parameters']);
								break;

							case ZBX_SCRIPT_TYPE_SSH:
								// Check username.
								$this->assertNotEmpty($db_upd_script['username']);
								if (array_key_exists('username', $script)) {
									$this->assertSame($db_upd_script['username'], $script['username']);
								}
								else {
									$this->assertSame($db_script['username'], $db_upd_script['username']);
								}

								// Check Port.
								if (array_key_exists('port', $script)) {
									$this->assertSame($db_upd_script['port'], strval($script['port']));
								}
								else {
									$this->assertSame($db_script['port'], $db_upd_script['port']);
								}

								// Check auth type.
								if (array_key_exists('authtype', $script)) {
									$this->assertEquals($db_upd_script['authtype'], $script['authtype']);

									if ($script['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
										// Check public and private keys.
										$this->assertNotEmpty($db_upd_script['publickey']);
										$this->assertNotEmpty($db_upd_script['privatekey']);

										// Check public key.
										if (array_key_exists('publickey', $script)) {
											$this->assertSame($db_upd_script['publickey'], $script['publickey']);
										}
										else {
											$this->assertSame($db_script['publickey'], $db_upd_script['publickey']);
										}

										// Check private key.
										if (array_key_exists('privatekey', $script)) {
											$this->assertSame($db_upd_script['privatekey'], $script['privatekey']);
										}
										else {
											$this->assertSame($db_script['privatekey'], $db_upd_script['privatekey']);
										}
									}
									else {
										// Check password type.
										$this->assertEmpty($db_script['publickey']);
										$this->assertEmpty($db_script['privatekey']);
									}
								}
								else {
									$this->assertEquals($db_script['authtype'], $db_upd_script['authtype']);

									if ($db_script['authtype'] == ITEM_AUTHTYPE_PUBLICKEY) {
										$this->assertNotEmpty($db_upd_script['publickey']);
										$this->assertNotEmpty($db_upd_script['privatekey']);
										$this->assertSame($db_script['publickey'], $db_upd_script['publickey']);
										$this->assertSame($db_script['privatekey'], $db_upd_script['privatekey']);
									}
									else {
										$this->assertEmpty($db_script['publickey']);
										$this->assertEmpty($db_script['privatekey']);
									}
								}

								// Check password.
								if (array_key_exists('password', $script)) {
									$this->assertSame($db_upd_script['password'], $script['password']);
								}
								else {
									$this->assertSame($db_script['password'], $db_upd_script['password']);
								}

								// Check other fields.
								$this->assertEquals($db_upd_script['execute_on'],
									DB::getDefault('scripts', 'execute_on')
								);
								$this->assertSame($db_upd_script['timeout'], DB::getDefault('scripts', 'timeout'));
								$this->assertEmpty($db_upd_script['parameters']);
								break;

							case ZBX_SCRIPT_TYPE_TELNET:
								// Check username.
								$this->assertNotEmpty($db_upd_script['username']);
								if (array_key_exists('username', $script)) {
									$this->assertSame($db_upd_script['username'], $script['username']);
								}
								else {
									$this->assertSame($db_script['username'], $db_upd_script['username']);
								}

								// Check password.
								if (array_key_exists('password', $script)) {
									$this->assertSame($db_upd_script['password'], $script['password']);
								}
								else {
									$this->assertSame($db_script['password'], $db_upd_script['password']);
								}

								// Check port.
								if (array_key_exists('port', $script)) {
									$this->assertSame($db_upd_script['port'], strval($script['port']));
								}
								else {
									$this->assertSame($db_script['port'], $db_upd_script['port']);
								}

								// Check other fields.
								$this->assertEquals($db_upd_script['execute_on'],
									DB::getDefault('scripts', 'execute_on')
								);
								$this->assertSame($db_upd_script['timeout'], DB::getDefault('scripts', 'timeout'));
								$this->assertEquals($db_upd_script['authtype'], DB::getDefault('scripts', 'authtype'));
								$this->assertEmpty($db_upd_script['publickey']);
								$this->assertEmpty($db_upd_script['privatekey']);
								$this->assertEmpty($db_upd_script['parameters']);
								break;

							case ZBX_SCRIPT_TYPE_WEBHOOK:
								// Check timeout.
								if (array_key_exists('timeout', $script)) {
									$this->assertSame($db_upd_script['timeout'], $script['timeout']);
								}
								else {
									$this->assertSame($db_script['timeout'], $db_upd_script['timeout']);
								}

								// Check parameters.
								if (array_key_exists('parameters', $script)) {
									if ($script['parameters']) {
										$this->assertNotEmpty($db_upd_script['parameters']);

										foreach ($script['parameters'] as $sp_num => $parameter) {
											$db_upd_script_parameter = CDBHelper::getRow(
												'SELECT sp.script_paramid,sp.name,sp.value'.
												' FROM script_param sp'.
												' WHERE sp.scriptid='.zbx_dbstr($script['scriptid']).
													' AND sp.name='.zbx_dbstr($parameter['name'])
											);

											$this->assertNotEmpty($db_upd_script_parameter['name']);
											$this->assertSame($db_upd_script_parameter['name'], $parameter['name']);
											$this->assertSame($db_upd_script_parameter['value'], $parameter['value']);
										}
									}
									else {
										$this->assertEmpty($db_upd_script['parameters']);
									}
								}
								else {
									$this->assertEmpty($db_upd_script['parameters']);
								}

								// Check other fields.
								$this->assertEquals($db_upd_script['execute_on'],
									DB::getDefault('scripts', 'execute_on')
								);
								$this->assertEmpty($db_upd_script['port']);
								$this->assertEquals($db_upd_script['authtype'], DB::getDefault('scripts', 'authtype'));
								$this->assertEmpty($db_upd_script['username']);
								$this->assertEmpty($db_upd_script['password']);
								$this->assertEmpty($db_upd_script['publickey']);
								$this->assertEmpty($db_upd_script['privatekey']);
								break;
						}
					}
				}
				else {
					$this->assertEquals($db_script['type'], $db_upd_script['type']);

					// Check parameters if type stays the same.
					if (array_key_exists('parameters', $script)) {
						// Javascript type.
						if ($script['parameters']) {
							// Check newly added parameters.
							$this->assertNotEmpty($db_upd_script['parameters']);

							foreach ($script['parameters'] as $sp_num => $parameter) {
								$db_upd_script_parameter = CDBHelper::getRow(
									'SELECT sp.script_paramid,sp.name,sp.value'.
									' FROM script_param sp'.
									' WHERE sp.scriptid='.zbx_dbstr($script['scriptid']).
										' AND sp.name='.zbx_dbstr($parameter['name'])
								);

								$this->assertNotEmpty($db_upd_script_parameter['name']);
								$this->assertSame($db_upd_script_parameter['name'], $parameter['name']);
								$this->assertSame($db_upd_script_parameter['value'], $parameter['value']);
							}
						}
						else {
							// Check that parameters are removed.
							$this->assertEmpty($db_upd_script['parameters']);
						}
					}
					else {
						// Javascript type or other script type. Can be empty or the same as before.
						$this->assertEquals($db_script['parameters'], $db_upd_script['parameters']);
					}
				}

				// Check command.
				$this->assertNotEmpty($db_upd_script['command']);
				if (array_key_exists('command', $script)) {
					$this->assertSame($db_upd_script['command'], $script['command']);
				}
				else {
					$this->assertSame($db_script['command'], $db_upd_script['command']);
				}

				// Check scope.
				if (array_key_exists('scope',$script)) {
					$this->assertEquals($db_upd_script['scope'], $script['scope']);
				}
				else {
					$this->assertEquals($db_script['scope'], $db_upd_script['scope']);
				}

				// Check scope dependent fields.
				if ($db_upd_script['scope'] == ZBX_SCRIPT_SCOPE_ACTION) {
					$this->assertEmpty($db_upd_script['menu_path']);
					$this->assertSame($db_upd_script['usrgrpid'], '0');
					$this->assertEquals($db_upd_script['host_access'], DB::getDefault('scripts', 'host_access'));
					$this->assertEmpty($db_upd_script['confirmation']);
				}
				else {
					// Check menu path.
					if (array_key_exists('menu_path', $script)) {
						$this->assertSame($db_upd_script['menu_path'], $script['menu_path']);
					}
					else {
						$this->assertSame($db_script['menu_path'], $db_upd_script['menu_path']);
					}

					// Check user group.
					if (array_key_exists('usrgrpid', $script)) {
						$this->assertSame($db_upd_script['usrgrpid'], strval($script['usrgrpid']));
					}
					else {
						$this->assertSame($db_script['usrgrpid'], $db_upd_script['usrgrpid']);
					}

					// Check host_access.
					if (array_key_exists('host_access', $script)) {
						$this->assertEquals($db_upd_script['host_access'], $script['host_access']);
					}
					else {
						$this->assertEquals($db_script['host_access'], $db_upd_script['host_access']);
					}

					// Check confirmation.
					if (array_key_exists('confirmation', $script)) {
						$this->assertSame($db_upd_script['confirmation'], $script['confirmation']);
					}
					else {
						$this->assertSame($db_script['confirmation'], $db_upd_script['confirmation']);
					}
				}

				// Check host group.
				if (array_key_exists('groupid', $script)) {
					$this->assertSame($db_upd_script['groupid'], strval($script['groupid']));
				}
				else {
					$this->assertSame($db_script['groupid'], $db_upd_script['groupid']);
				}

				// Check description.
				if (array_key_exists('description', $script)) {
					$this->assertSame($db_upd_script['description'], $script['description']);
				}
				else {
					$this->assertSame($db_script['description'], $db_upd_script['description']);
				}
			}
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('script.update', $scripts, $expected_error);
		}
	}

	public static function script_delete_data_invalid() {
		return [
			// Check script id.
			[
				'script' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'script' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'script' => ['8', '8'],
				'expected_error' => 'Invalid parameter "/2": value (8) already exists.'
			],
			// Check if deleted scripts used in actions.
			[
				'script' => ['11'],
				'expected_error' => 'Cannot delete scripts. Script "API script in action" is used in action operation "API action with script".'
			]
		];
	}

	public static function script_delete_data_valid() {
		return [
			// Successfully delete scripts.
			[
				'script' => ['8'],
				'expected_error' => null
			],
			[
				'script' => ['9', '10'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider script_delete_data_invalid
	 * @dataProvider script_delete_data_valid
	 */
	public function testScript_Delete($script, $expected_error) {
		$result = $this->call('script.delete', $script, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['scriptids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT s.scriptid FROM scripts s WHERE s.scriptid='.zbx_dbstr($id)
				));
				// Regardless of script type, script params must not exist after delete.
				$this->assertEquals(0, CDBHelper::getCount(
					'SELECT sp.script_paramid FROM script_param sp WHERE sp.scriptid='.zbx_dbstr($id)
				));
			}
		}
	}

	public static function script_execute() {
		return [
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '10084',
					'value' => 'test'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "value".'
			],
			// Check script id.
			[
				'script' => [
					'hostid' => '10084'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "scriptid" is missing.'
			],
			[
				'script' => [
					'scriptid' => '',
					'hostid' => '10084'
				],
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => 'abc',
					'hostid' => '10084'
				],
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1.1',
					'hostid' => '10084'
				],
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => 'æų',
					'hostid' => '10084'
				],
				'expected_error' => 'Invalid parameter "/scriptid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '123456',
					'hostid' => '10084'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check host ID.
			[
				'script' => [
					'scriptid' => '1'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "eventid" is missing.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => ''
				],
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '1.1'
				],
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => 'æų'
				],
				'expected_error' => 'Invalid parameter "/hostid": a number is expected.'
			],
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '123456'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check event ID.
			[
				'script' => [
					'scriptid' => '1',
					'hostid' => '10084',
					'eventid' => '123456'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "eventid".'
			],
			[
				'script' => [
					'scriptid' => '1',
					'eventid' => '0'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check script peremissions for host group. Host belongs to the host group that hasn't permission to execute current script
			[
				'script' => [
					'scriptid' => '4',
					'hostid' => '50009'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	 * @dataProvider script_execute
	 */
	public function testScripts_Execute($script, $expected_error) {
		$result = $this->call('script.execute', $script, $expected_error);

		if ($expected_error === null) {
			$this->assertEquals('success', $result['result']['response']);
		}
	}

	public static function script_permissions() {
		return [
			// User have permissions to host, but not to script (script can execute only specific user group).
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '12',
					'hostid' => '50009'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// User have permissions to script, but not to host (script can execute only on specific host group).
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '13',
					'hostid' => '10084'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// User have deny permissions to host, but script required read permissions for the host.
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '1',
					'hostid' => '50014'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check zabbix admin permissions to create, update, delete and execute script.
			[
				'method' => 'script.create',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'name' => 'API script create as zabbix admin',
					'command' => 'reboot server 1'
				],
				'expected_error' => 'No permissions to call "script.create".'
			],
			[
				'method' => 'script.update',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '6',
					'name' => 'API script update as zabbix admin'
				],
				'expected_error' => 'No permissions to call "script.update".'
			],
			[
				'method' => 'script.delete',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => ['7'],
				'expected_error' => 'No permissions to call "script.delete".'
			],
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '1',
					'hostid' => '10084'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			// Check zabbix user permissions to create, update, delete and execute script.
			[
				'method' => 'script.create',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
					'name' => 'API script create as zabbix user',
					'command' => 'reboot server 1'
				],
				'expected_error' => 'No permissions to call "script.create".'
			],
			[
				'method' => 'script.update',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '6',
					'name' => 'API script update as zabbix user'
				],
				'expected_error' => 'No permissions to call "script.update".'
			],
			[
				'method' => 'script.delete',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => ['7'],
				'expected_error' => 'No permissions to call "script.delete".'
			],
			[
				'method' => 'script.execute',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'script' => [
					'scriptid' => '1',
					'hostid' => '10084'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	 * @dataProvider script_permissions
	 */
	public function testScripts_UserPermissions($method, $login, $params, $expected_error) {
		$this->authorize($login['user'], $login['password']);
		$this->call($method, $params, $expected_error);
	}
}
