<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

define('JSON_COMPARE_LEFT', 1);
define('JSON_COMPARE_RIGHT', 2);

define('JSON_ARRAY_COMPARE_LEFT', 4);
define('JSON_ARRAY_COMPARE_RIGHT', 5);

/**
 * Test suite for agents metric collection.
 *
 * @backup history
 */
class testAgentItems extends CIntegrationTest {

	const TEST_FILE_BASE_NAME = 'test_file';
	const TEST_LINK_BASE_NAME = 'test_link';
	const TEST_FILE_NAME = '/tmp/'.self::TEST_FILE_BASE_NAME;
	const TEST_FILE_NAME_ACCESS = '/tmp/'.self::TEST_FILE_BASE_NAME.'_access_test';
	const TEST_LINK_NAME = '/tmp/'.self::TEST_LINK_BASE_NAME;
	const TEST_LINK_NAME2 = '/tmp/'.self::TEST_LINK_BASE_NAME.'2';
	const TEST_DIR_NAME = '/tmp/dir';
	const TEST_DIR1_NAME = 'dir1';
	const TEST_DIR_DIR1_NAME = self::TEST_DIR_NAME.'/'.self::TEST_DIR1_NAME;
	const TEST_DIR_FILE_NAME = self::TEST_DIR_NAME.'/'.self::TEST_FILE_BASE_NAME;
	const TEST_DIR_LINK_NAME = self::TEST_DIR_DIR1_NAME.'/'.self::TEST_LINK_BASE_NAME;

	const TEST_MOD_TIMESTAMP = 1617019149;
	const AGENT_METADATA = 'zabbixtestagent';

	private static $hostids = [];
	private static $itemids = [];
	private static $results = [];

	// List of items to check.
	private static $items = [
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %U '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.',group,id]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %g '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %U '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.owner['.self::TEST_FILE_NAME.',group,id]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %g '.self::TEST_FILE_NAME
		],
		[
			'key' => 'kernel.openfiles',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'cat /proc/sys/fs/file-nr | cut -f 1',
			'threshold' => 2000
		],
		[
			'key' => 'kernel.openfiles',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'cat /proc/sys/fs/file-nr | cut -f 1',
			'threshold' => 2000
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 27
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 27
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.',lines]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 3
		],
		[
			'key' => 'vfs.file.size['.self::TEST_FILE_NAME.',lines]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 3
		],
		[
			'key' => 'vfs.file.permissions['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %04a '.self::TEST_FILE_NAME
		],
		[
			'key' => 'vfs.file.permissions['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result_exec' => 'stat -c %04a '.self::TEST_FILE_NAME
		],
		[
			'key' => 'agent.hostmetadata',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => self::AGENT_METADATA
		],
		[
			'key' => 'agent.hostmetadata',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => self::AGENT_METADATA
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 892864536
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',md5]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'f58f72c7ef71556254f409fd7411567d'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',sha256]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'b73a96d498012c84fc2ffa1df3c4461689cb90456ee300654723205c26ec4988'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 892864536
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',md5]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'f58f72c7ef71556254f409fd7411567d'
		],
		[
			'key' => 'vfs.file.cksum['.self::TEST_FILE_NAME.',sha256]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'result' => 'b73a96d498012c84fc2ffa1df3c4461689cb90456ee300654723205c26ec4988'
		],
		[
			'key' => 'vfs.file.get['.self::TEST_FILE_NAME_ACCESS.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
					'type' => 'file',
					'permissions' => 'stat -c %04a '.self::TEST_FILE_NAME_ACCESS,
					'user' => 'stat -c %U '.self::TEST_FILE_NAME_ACCESS,
					'group' => 'stat -c %G '.self::TEST_FILE_NAME_ACCESS,
					'uid' => 'stat -c %u '.self::TEST_FILE_NAME_ACCESS,
					'gid' => 'stat -c %g '.self::TEST_FILE_NAME_ACCESS,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_FILE_NAME_ACCESS,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_FILE_NAME_ACCESS
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_FILE_NAME_ACCESS.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
					'type' => 'file',
					'permissions' => 'stat -c %04a '.self::TEST_FILE_NAME_ACCESS,
					'user' => 'stat -c %U '.self::TEST_FILE_NAME_ACCESS,
					'group' => 'stat -c %G '.self::TEST_FILE_NAME_ACCESS,
					'uid' => 'stat -c %u '.self::TEST_FILE_NAME_ACCESS,
					'gid' => 'stat -c %g '.self::TEST_FILE_NAME_ACCESS,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+03:00'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_FILE_NAME_ACCESS,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_FILE_NAME_ACCESS
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_LINK_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
					'type' => 'sym',
					'permissions' => 'stat -c %04a '.self::TEST_LINK_NAME,
					'user' => 'stat -c %U '.self::TEST_LINK_NAME,
					'group' => 'stat -c %G '.self::TEST_LINK_NAME,
					'uid' => 'stat -c %u '.self::TEST_LINK_NAME,
					'gid' => 'stat -c %g '.self::TEST_LINK_NAME,
					'size' => 14,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_LINK_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_LINK_NAME
					]
				]
		],
		[
			'key' => 'vfs.file.get['.self::TEST_LINK_NAME2.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
					'type' => 'sym',
					'permissions' => 'stat -c %04a '.self::TEST_LINK_NAME2,
					'user' => 'stat -c %U '.self::TEST_LINK_NAME2,
					'group' => 'stat -c %G '.self::TEST_LINK_NAME2,
					'uid' => 'stat -c %u '.self::TEST_LINK_NAME2,
					'gid' => 'stat -c %g '.self::TEST_LINK_NAME2,
					'size' => 14,
					'time' => [
						'modify' => '2021-03-29T14:59:09+03:00'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_LINK_NAME2,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_LINK_NAME2
					]
				]
		],
		[
			'key' => 'net.tcp.socket.count[0.0.0.0,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.',,,listen]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'net.tcp.socket.count[,,127.127.127.127]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0
		],
		[
			'key' => 'net.udp.socket.count[]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'netstat -au --numeric-hosts | grep ^udp | wc -l'
		],
		[
			'key' => 'net.tcp.socket.count[0.0.0.0,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.',,,listen]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'net.tcp.socket.count[,,127.127.127.127]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0
		],
		[
			'key' => 'net.udp.socket.count[]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result_exec' => 'netstat -au --numeric-hosts | grep ^udp | wc -l'
		],
		[
			'key' => 'vfs.dir.get['.self::TEST_DIR_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_ARRAY_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
				[
					'basename' => self::TEST_FILE_BASE_NAME,
					'pathname' =>  self::TEST_DIR_FILE_NAME,
					'dirname' => self::TEST_DIR_NAME,
					'type' => 'file',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_FILE_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_FILE_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_FILE_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_FILE_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_FILE_NAME,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_FILE_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_FILE_NAME
					]
				],
				[
					'basename' => self::TEST_DIR1_NAME,
					'pathname' =>  self::TEST_DIR_DIR1_NAME,
					'dirname' => self::TEST_DIR_NAME,
					'type' => 'dir',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_DIR1_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_DIR1_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_DIR1_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_DIR1_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_DIR1_NAME,
					'size' => 4096,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_DIR1_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_DIR1_NAME
					]
				],
				[
					'basename' => self::TEST_LINK_BASE_NAME,
					'pathname' =>  self::TEST_DIR_LINK_NAME,
					'dirname' => self::TEST_DIR_DIR1_NAME,
					'type' => 'sym',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_LINK_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_LINK_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_LINK_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_LINK_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_LINK_NAME,
					'size' => 18,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_LINK_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_LINK_NAME
					]
				]
			]
		],
		[
			'key' => 'vfs.dir.get['.self::TEST_DIR_NAME.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_ARRAY_COMPARE_LEFT,
			'fields_exec' => ['permissions', 'user', 'group', 'uid', 'gid', 'access', 'change'],
			'result' => [
				[
					'basename' => self::TEST_FILE_BASE_NAME,
					'pathname' =>  self::TEST_DIR_FILE_NAME,
					'dirname' => self::TEST_DIR_NAME,
					'type' => 'file',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_FILE_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_FILE_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_FILE_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_FILE_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_FILE_NAME,
					'size' => 27,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_FILE_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_FILE_NAME
					]
				],
				[
					'basename' => self::TEST_DIR1_NAME,
					'pathname' =>  self::TEST_DIR_DIR1_NAME,
					'dirname' => self::TEST_DIR_NAME,
					'type' => 'dir',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_DIR1_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_DIR1_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_DIR1_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_DIR1_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_DIR1_NAME,
					'size' => 4096,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_DIR1_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_DIR1_NAME
					]
				],
				[
					'basename' => self::TEST_LINK_BASE_NAME,
					'pathname' =>  self::TEST_DIR_LINK_NAME,
					'dirname' => self::TEST_DIR_DIR1_NAME,
					'type' => 'sym',
					'permissions' => 'stat -c %04a '.self::TEST_DIR_LINK_NAME,
					'user' => 'stat -c %U '.self::TEST_DIR_LINK_NAME,
					'group' => 'stat -c %G '.self::TEST_DIR_LINK_NAME,
					'uid' => 'stat -c %u '.self::TEST_DIR_LINK_NAME,
					'gid' => 'stat -c %g '.self::TEST_DIR_LINK_NAME,
					'size' => 18,
					'time' => [
						'modify' => '2021-03-29T14:59:09+0300'
					],
					'timestamp' => [
						'access' => 'stat -c %X '.self::TEST_DIR_LINK_NAME,
						'modify' => self::TEST_MOD_TIMESTAMP,
						'change' => 'stat -c %Z '.self::TEST_DIR_LINK_NAME
					]
				]
			]
		],
		[
			'key' => 'agent.variant',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'agent.variant',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 2
		],
		[
			'key' => 'proc.get[zabbix_agentd]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['user', 'group', 'uid', 'gid', 'threads'],
			'result' => [
				[
					'user' => 'ps --no-headers -o ruser:1 -C zabbix_agentd',
					'group' => 'ps --no-headers -o rgroup:1 -C zabbix_agentd',
					'uid' => 'ps --no-headers -o ruid:1 -C zabbix_agentd',
					'gid' => 'ps --no-headers -o rgid:1 -C zabbix_agentd',
					'threads' => 'ps --no-headers -o nlwp:1 -C zabbix_agentd'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_agentd]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['user', 'group', 'uid', 'gid', 'threads'],
			'result' => [
				[
					'user' => 'ps --no-headers -o ruser:1 -C zabbix_agentd',
					'group' => 'ps --no-headers -o rgroup:1 -C zabbix_agentd',
					'uid' => 'ps --no-headers -o ruid:1 -C zabbix_agentd',
					'gid' => 'ps --no-headers -o rgid:1 -C zabbix_agentd',
					'threads' => 'ps --no-headers -o nlwp:1 -C zabbix_agentd'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_agent2]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['pid', 'ppid', 'cmdline', 'user', 'group', 'uid', 'gid'],
			'result' => [
				[
					'pid' => 'pgrep zabbix_agent2',
					'ppid' => 'ps --no-headers -o ppid:1 -C zabbix_agent2',
					'cmdline' => 'ps --no-headers -o cmd:1 -C zabbix_agent2',
					'user' => 'ps --no-headers -o ruser:1 -C zabbix_agent2',
					'group' => 'ps --no-headers -o rgroup:1 -C zabbix_agent2',
					'uid' => 'ps --no-headers -o ruid:1 -C zabbix_agent2',
					'gid' => 'ps --no-headers -o rgid:1 -C zabbix_agent2'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_agent2]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['pid', 'ppid', 'cmdline', 'user', 'group', 'uid', 'gid'],
			'result' => [
				[
					'pid' => 'pgrep zabbix_agent2',
					'ppid' => 'ps --no-headers -o ppid:1 -C zabbix_agent2',
					'cmdline' => 'ps --no-headers -o cmd:1 -C zabbix_agent2',
					'user' => 'ps --no-headers -o ruser:1 -C zabbix_agent2',
					'group' => 'ps --no-headers -o rgroup:1 -C zabbix_agent2',
					'uid' => 'ps --no-headers -o ruid:1 -C zabbix_agent2',
					'gid' => 'ps --no-headers -o rgid:1 -C zabbix_agent2'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_agent2,,,thread]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['tid'],
			'result' => [
				[
					'name' => 'zabbix_agent2',
					'tname' => 'zabbix_agent2',
					'tid' => 'ps --no-headers -o tid:1 -C zabbix_agent2'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_agent2,,,thread]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['tid'],
			'result' => [
				[
					'name' => 'zabbix_agent2',
					'tname' => 'zabbix_agent2',
					'tid' => 'ps --no-headers -o tid:1 -C zabbix_agent2'
				]
			]
		],
		// Cannot use zabbix_agentd/zabbix_agent2 here since it forks, when executes some metrics.
		// Other processes like systemd may not be available in Docker.
		// So, we are left with the zabbix_server for which the number of processes/threads should be stable.
		[
			'key' => 'proc.get[zabbix_server,,,summary]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['processes', 'threads'],
			'result' => [
				[
					'name' => 'zabbix_server',
					'processes' => 'ps --no-headers -C zabbix_server | wc -l',
					'threads' => 'ps --no-headers -T -C zabbix_server | wc -l'
				]
			]
		],
		[
			'key' => 'proc.get[zabbix_server,,,summary]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_TEXT,
			'json' => JSON_COMPARE_LEFT,
			'fields_exec' => ['processes', 'threads'],
			'result' => [
				[
					'name' => 'zabbix_server',
					'processes' => 'ps --no-headers -C zabbix_server | wc -l',
					'threads' => 'ps --no-headers -T -C zabbix_server | wc -l'
				]
			]
		],
		[
			'key' => 'net.tcp.port[123.123.123.123,111]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0,
			'timeout' => '1s'
		],
		[
			'key' => 'net.tcp.port[123.123.123.123,111]',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 0,
			'timeout' => '1s'
		],
		[
			'key' => 'net.tcp.port[,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		],
		[
			'key' => 'net.tcp.port[,'.PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX.']',
			'type' => ITEM_TYPE_ZABBIX,
			'component' => self::COMPONENT_AGENT2,
			'valueType' => ITEM_VALUE_TYPE_UINT64,
			'result' => 1
		]
	];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$components = [
			self::COMPONENT_AGENT => self::AGENT_PORT_SUFFIX,
			self::COMPONENT_AGENT2 => self::AGENT2_PORT_SUFFIX
		];

		$hosts = [];
		foreach ($components as $component => $port) {
			$hosts[] = [
				'host' => $component,
				'interfaces' => [
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => PHPUNIT_PORT_PREFIX.$port
				],
				'groups' => [
					'groupid' => 4
				],
				'status' => HOST_STATUS_NOT_MONITORED
			];
		}

		$response = $this->call('host.create', $hosts);
		$this->assertArrayHasKey('hostids', $response['result']);

		foreach (array_keys($components) as $i => $component) {
			$this->assertArrayHasKey($i, $response['result']['hostids']);
			self::$hostids[$component] = $response['result']['hostids'][$i];
		}

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'selectInterfaces' => ['interfaceid'],
			'hostids' => self::$hostids
		]);

		$interfaceids = [];
		foreach ($response['result'] as $host) {
			$interfaceids[$host['host']] = $host['interfaces'][0]['interfaceid'];
		}

		// Create items.
		$items = [];
		foreach (self::$items as $item) {
			$data = [
				'name' => $item['key'],
				'key_' => $item['key'],
				'type' => $item['type'],
				'value_type' => $item['valueType'],
				'delay' => '1s'
			];

			if (array_key_exists('timeout', $item))
			{
				$data['timeout'] = $item['timeout'];
			}

			$items[] = array_merge($data, [
				'hostid' => self::$hostids[$item['component']],
				'interfaceid' => $interfaceids[$item['component']]
			]);
		}

		$response = $this->call('item.create', $items);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(count($items), count($response['result']['itemids']));

		// Get item IDs
		$itemids = $response['result']['itemids'];
		foreach (self::$items as $i => $item) {
			self::$itemids[$item['component'].':'.$item['key']] = $itemids[$i];
		}

		// Create test directories
		$this->assertTrue(@exec('rm -rf '.self::TEST_DIR_NAME) !== false);
		$this->assertTrue(@mkdir(self::TEST_DIR_NAME));
		$this->assertTrue(@mkdir(self::TEST_DIR_DIR1_NAME));

		// Write test file
		$this->assertTrue(@file_put_contents(self::TEST_FILE_NAME, "1st line\n2nd line\n3rd line\n") !== false);
		$this->assertTrue(@touch(self::TEST_FILE_NAME, self::TEST_MOD_TIMESTAMP));
		$this->assertTrue(@file_put_contents(self::TEST_FILE_NAME_ACCESS, "1st line\n2nd line\n3rd line\n") !== false);
		$this->assertTrue(@touch(self::TEST_FILE_NAME_ACCESS, self::TEST_MOD_TIMESTAMP));
		$this->assertTrue(@file_put_contents(self::TEST_DIR_FILE_NAME, "1st line\n2nd line\n3rd line\n") !== false);
		$this->assertTrue(@touch(self::TEST_DIR_FILE_NAME, self::TEST_MOD_TIMESTAMP));

		// Write test symlinks
		if (!file_exists(self::TEST_LINK_NAME)) {
			$this->assertTrue(@symlink(self::TEST_FILE_NAME, self::TEST_LINK_NAME));
		}
		$this->assertTrue(@exec('touch -h -a -m -t 202103291459.09 '.self::TEST_LINK_NAME) !== false);
		if (!file_exists(self::TEST_LINK_NAME2)) {
			$this->assertTrue(@symlink(self::TEST_FILE_NAME, self::TEST_LINK_NAME2));
		}
		$this->assertTrue(@exec('touch -h -a -m -t 202103291459.09 '.self::TEST_LINK_NAME2) !== false);
		if (!file_exists(self::TEST_DIR_LINK_NAME)) {
			$this->assertTrue(@symlink(self::TEST_DIR_FILE_NAME, self::TEST_DIR_LINK_NAME));
		}
		$this->assertTrue(@exec('touch -h -a -m -t 202103291459.09 '.self::TEST_DIR_LINK_NAME) !== false);

		$this->assertTrue(@touch(self::TEST_DIR_DIR1_NAME, self::TEST_MOD_TIMESTAMP));

		$this->assertTrue(@exec('touch -h -a -m -t 202103291459.09 '.self::TEST_FILE_NAME_ACCESS) !== false);

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function configurationProvider_checkDataCollection() {
		return [
			self::COMPONENT_SERVER => [
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1,
				'DebugLevel' => 5,
				'LogFileSize' => 0
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::COMPONENT_AGENT,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'AllowKey' => 'system.run[*]',
				'HostMetadata' => self::AGENT_METADATA,
				'DebugLevel' => 5,
				'LogFileSize' => 0
			],
			self::COMPONENT_AGENT2 => [
				'Hostname' => self::COMPONENT_AGENT2,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'AllowKey' => 'system.run[*]',
				'Plugins.Uptime.Capacity' => '10',
				'HostMetadata' => self::AGENT_METADATA,
				'DebugLevel' => 5,
				'LogFileSize' => 0

			]
		];
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @required-components server, agent, agent2
	 * @configurationDataProvider configurationProvider_checkDataCollection
	 * @hosts agentd, agent2
	 */
	public function testAgentItems_checkDataCollection() {
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
				'enabling Zabbix agent checks on host "'.$component.'": interface became available', false
			);
		}

		$this->updateExpectedResults();
		$this->getItemData();
	}

	/**
	 * Item data provider.
	 *
	 * @return array
	 */
	public function getItems() {
		$items = [];
		foreach (self::$items as $item) {
			$items[] = [$item];
		}

		return $items;
	}

	/**
	 * Get values of all items and store them in static variable.
	 *
	 * @return array
	 */
	public function getItemData() {
		static $data = null;

		if ($data !== null) {
			return $data;
		}

		$data = [];
		$wait_iterations = 10;
		$wait_iteration_delay = 2;

		for ($r = 0; $r < $wait_iterations; $r++) {
			$db_items = $this->call('item.get', [
				'output' => ['lastvalue', 'lastclock'],
				'itemids' => self::$itemids,
				'preservekeys' => true
			])['result'];

			$all_collected = true;

			foreach (self::$items as $item) {
				$itemid = self::$itemids[$item['component'].':'.$item['key']];

				if ($db_items[$itemid]['lastclock'] != 0) {
					$data[$item['component'].':'.$item['key']] = $db_items[$itemid]['lastvalue'];
				}
				else {
					$all_collected = false;
				}
			}

			if ($all_collected) {
				break;
			}

			sleep($wait_iteration_delay);
		}

		return $data;
	}

	/**
	 * Update expected result values.
	 *
	 * @return array
	 */
	public function updateExpectedResults() {
		foreach(self::$items as $k => $item) {

			if (array_key_exists('json', $item) && array_key_exists('fields_exec', $item)) {
				self::$results[$item['component'].':'.$item['key']] = self::$items[$k]['result'];
				foreach ($item['fields_exec'] as $dyn) {
					$this->dynupdate(self::$results[$item['component'].':'.$item['key']], $dyn);
				}

			}
			elseif (array_key_exists('result_exec', $item)) {
				self::$results[$item['component'].':'.$item['key']] = exec($item['result_exec']);
			}
			else {
				self::$results[$item['component'].':'.$item['key']] = $item['result'];
			}
		}
	}

	/**
	 * Test if both active and passive go agent checks are processed.
	 *
	 * @depends testAgentItems_checkDataCollection
	 * @dataProvider getItems
	 */
	public function testAgentItems_checkData($item) {
		$data = $this->getItemData();

		if (!array_key_exists($item['component'].':'.$item['key'], $data)) {
			$this->fail('No metrics for item "'.$item['component'].':'.$item['key'].'"');
		}

		$value = $data[$item['component'].':'.$item['key']];
		$result = self::$results[$item['component'].':'.$item['key']];

		switch ($item['valueType']) {
			case ITEM_VALUE_TYPE_TEXT:
				if (array_key_exists('json', $item)) {
					$jsonval = json_decode($value, true);

					if ($item['json'] === JSON_COMPARE_LEFT) {
						$this->arrcmpr($result, $jsonval, $item['key']);
					}
					elseif ($item['json'] === JSON_COMPARE_RIGHT) {
						$this->arrcmpr($jsonval, $result, $item['key']);
					}
					elseif ($item['json'] === JSON_ARRAY_COMPARE_LEFT) {
						foreach ($result as $result_key => $result_value) {
							$found = false;
							foreach ($jsonval as $jsonval_key => $jsonval_value) {
								$found = $found || $this->arrfind($result_value, $jsonval_value);
							}
							self::assertEquals($found, true, 'Value (result_key: '.$result_key.') is not found for '.$item['key']);
						}
					}
					elseif ($item['json'] === JSON_ARRAY_COMPARE_RIGHT) {
						foreach ($jsonval as $jsonval_key => $jsonval_value) {
							$found = false;
							foreach ($result as $result_key => $result_value) {
								$found = $found || $this->arrfind($jsonval_value, $result_value);
							}
							self::assertEquals($found, true, 'Value (jsonval_key: '.$jsonval_key.') is not found for '.$item['key']);
						}
					}
				}
				else {
					if (array_key_exists('threshold', $item) && $item['threshold'] !== 0) {
						$value = substr($value, 0, $item['threshold']);
						$expected = substr($result, 0, $item['threshold']);
					}
					else {
						$expected = $result;
					}

					$this->assertEquals($expected, $value, 'Received value is not expected for '.$item['key']);
				}
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				if (array_key_exists('threshold', $item) && $item['threshold'] !== 0) {
					$diff = abs(abs($value) - abs($result));
					$this->assertTrue($diff <= $item['threshold'], 'Received value ('.$value.') for '.$item['key'].
						' differs more than defined threshold '.$diff.' > '.$item['threshold']
					);
				}
				else {
					$this->assertEquals($result, $value, 'Received value is not expected for '.$item['key']);
				}

				break;
		}
	}

	/**
	 * Compare arrays fields.
	 *
	 * @param array $array  array with mandatory fields
	 * @param array $cmpr	array to compare with
	 * @param string $key	item key
	 */
	public static function arrcmpr(array $array, array $cmpr, string $key) {
		foreach ($array as $array_key => $array_value) {
			self::assertArrayHasKey($array_key, $cmpr, 'Array key "'.$array_key.'" is missing in '.$key);

			if (is_array($array_value)) {
				if (!is_array($cmpr[$array_key])) {
					self::fail('Wrong element type in '.$key);
				}

				self::arrcmpr($array_value, $cmpr[$array_key], $key);
			} else {
				if (is_array($cmpr[$array_key])) {
					self::fail('Wrong element type in '.$key);
				}

				self::assertEquals($array_value, $cmpr[$array_key],
						'Value (array key: '.$array_key.') is not expected for '.
						$key."\n Received: ".json_encode($cmpr[$array_key]).
						".\n But expected: ".json_encode($array_value)."\n");
			}
		}
	}

	/**
	 * Find arrays fields.
	 *
	 * @param array $array  array with mandatory fields
	 * @param array $cmpr_array	array to find object in
	 * @param string $key	item key
	 */
	public static function arrfind(array $array, array $cmpr) {
		foreach ($array as $array_key => $array_value) {
			if(!array_key_exists($array_key, $cmpr)){
				return false;
			}

			if (is_array($array_value)) {
				if (!is_array($cmpr[$array_key])) {
					return false;
				}

				self::arrfind($array_value, $cmpr[$array_key]);
			} else {
				if (is_array($cmpr[$array_key])) {
					return false;
				}

				if ($array_value != $cmpr[$array_key]){
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Update results.
	 *
	 * @param array $result	reference to array with the expected results
	 * @param string $dyn	result field key
	 */
	public static function dynupdate(array & $result, string $dyn) {
		foreach ($result as $k => $res) {
			if (is_array($res)) {
				self::dynupdate($result[$k], $dyn);
			} elseif ($k === $dyn) {
				$result[$k] = exec($res);
			}
		}
	}
}

