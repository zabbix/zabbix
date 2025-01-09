<?php
/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

require_once __DIR__.'/../../include/classes/api/services/CUser.php';

/**
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testUserFieldAccess extends CAPITest {

	private static $sessionids = [
		':user:properties.user' => null,
		':user:properties.admin' => null,
		':user:properties.superadmin' => null
	];

	// Reference => User object with nested properties.
	private static $users = [
		':user:properties.user' => [
			'userid' => ':user:properties.user',
			'username' => 'properties.user',
			'name' => 'API test get properties - user',
			'surname' => 'Smith',
			'url' => 'www.user.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.user',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '1',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.main'],
				['usrgrpid' => 11] // Enabled debug mode.
			],
			'medias' => [
				[
					'mediaid' => ':media:user@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['user@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.user2' => [
			'userid' => ':user:properties.user2',
			'username' => 'properties.user2',
			'name' => 'API test get properties - user2',
			'surname' => 'Smith',
			'url' => 'www.user.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.user',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '0',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.main']
			],
			'medias' => [
				[
					'mediaid' => ':media:user2@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['user2@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.admin' => [
			'userid' => ':user:properties.admin',
			'username' => 'properties.admin',
			'name' => 'API test get properties - admin',
			'surname' => 'Smith',
			'url' => 'www.admin.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.admin',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '1',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.main'],
				['usrgrpid' => 7], // Zabbix administrators.
				['usrgrpid' => 11] // Enabled debug mode.
			],
			'medias' => [
				[
					'mediaid' => ':media:admin@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['admin@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.admin2' => [
			'userid' => ':user:properties.admin2',
			'username' => 'properties.admin2',
			'name' => 'API test get properties - admin2',
			'surname' => 'Smith',
			'url' => 'www.admin.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.admin',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '0',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.main'],
				['usrgrpid' => 7] // Zabbix administrators.
			],
			'medias' => [
				[
					'mediaid' => ':media:admin2@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['admin2@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.superadmin' => [
			'userid' => ':user:properties.superadmin',
			'username' => 'properties.superadmin',
			'name' => 'API test get properties - superadmin',
			'surname' => 'Smith',
			'url' => 'www.superadmin.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.superadmin',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '0',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.main']
			],
			'medias' => [
				[
					'mediaid' => ':media:superadmin@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['superadmin@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.user.other.group' => [
			'userid' => ':user:properties.user.other.group',
			'username' => 'properties.user.other.group',
			'name' => 'properties.other',
			'surname' => 'Smith',
			'url' => 'www.user.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.user',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '0',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.other.group']
			],
			'medias' => [
				[
					'mediaid' => ':media:user_other@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['user_other@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		],
		':user:properties.admin.other.group' => [
			'userid' => ':user:properties.admin.other.group',
			'username' => 'properties.admin.other.group',
			'name' => 'API test get properties - admin in other group',
			'surname' => 'Smith',
			'url' => 'www.admin.test',
			'autologin' => '0',
			'autologout' => '45m',
			'lang' => 'default',
			'refresh' => '2m',
			'theme' => 'default',
			'attempt_failed' => '0',
			'attempt_ip' => '',
			'attempt_clock' => '0',
			'rows_per_page' => '321',
			'timezone' => 'Europe/Rome',
			'roleid' => ':role:properties.admin',
			'passwd' => 'zabbix321456',
			'gui_access' => '0',
			'debug_mode' => '0',
			'users_status' => '0',
			'usrgrps' => [
				['usrgrpid' => ':user_group:properties.other.group']
			],
			'medias' => [
				[
					'mediaid' => ':media:admin_other@usertest.com',
					'mediatypeid' => '1',
					'sendto' => ['admin_other@usertest.com'],
					'active' => 0,
					'severity' => 63,
					'period' => '1-7,00:00-24:00'
				]
			]
		]
	];

	public function prepareTestData() {
		$user_fields = ['username', 'name', 'surname', 'url', 'rows_per_page', 'roleid', 'passwd', 'timezone',
			'autologout', 'refresh', 'usrgrps', 'medias'
		];
		$user_except_fields = ['medias.mediaid'];

		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'user.properties.group.main']
			],
			'user_groups' => [
				[
					'name' => 'properties.main',
					'users_status' => GROUP_STATUS_ENABLED,
					'rights' => [
						'id' => ':host_group:user.properties.group.main',
						'permission' => PERM_READ_WRITE
					]
				],
				[
					'name' => 'properties.other.group',
					'users_status' => GROUP_STATUS_ENABLED,
					'rights' => [
						'id' => ':host_group:user.properties.group.main',
						'permission' => PERM_READ_WRITE
					]
				]
			],
			'hosts' => [
				[
					'host' => 'user.properties.h1',
					'groups' => ['groupid' => ':host_group:user.properties.group.main'],
					'items' => [
						['key_' => 'i1']
					]
				]
			],
			'roles' => [
				[
					'name' => 'properties.user',
					'type' => USER_TYPE_ZABBIX_USER
				],
				[
					'name' => 'properties.admin',
					'type' => USER_TYPE_ZABBIX_ADMIN
				],
				[
					'name' => 'properties.superadmin',
					'type' => USER_TYPE_SUPER_ADMIN
				]
			],
			'users' => [
				self::getUserFields(':user:properties.user', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.user2', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.admin', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.admin2', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.superadmin', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.user.other.group', $user_fields, $user_except_fields),
				self::getUserFields(':user:properties.admin.other.group', $user_fields, $user_except_fields)
			],
			'actions' => [
				[
					'name' => 'user.properties.action',
					'eventsource' => EVENT_SOURCE_TRIGGERS,
					'operations' => [
						[
							'operationtype' => OPERATION_TYPE_MESSAGE,
							'opmessage' => [],
							'opmessage_grp' => [
								['usrgrpid' => ':user_group:properties.main']
							]
						]
					]
				]
			],
			'triggers' => [
				'user.properties.action.trigger' => [
					'description' => 'user.properties.action.trigger(user.properties.h1(i1))',
					'expression' => 'last(/user.properties.h1/i1)=0'
				]
			],
			'events' => [
				'user.properties.event' => [
					'name' => 'user.properties.event',
					'source' => EVENT_SOURCE_TRIGGERS
				]
			],
			'alerts' => [
				'properties.user' => [
					'subject' => 'properties.user',
					'userid' => ':user:properties.user',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.user']['medias'][0]['sendto'][0]
				],
				'properties.user2' => [
					'subject' => 'properties.user2',
					'userid' => ':user:properties.user2',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.user2']['medias'][0]['sendto'][0]
				],
				'properties.admin' => [
					'subject' => 'properties.admin',
					'userid' => ':user:properties.admin',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.admin']['medias'][0]['sendto'][0]
				],
				'properties.admin2' => [
					'subject' => 'properties.admin2',
					'userid' => ':user:properties.admin2',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.admin2']['medias'][0]['sendto'][0]
				],
				'properties.superadmin' => [
					'subject' => 'properties.superadmin',
					'userid' => ':user:properties.superadmin',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.superadmin']['medias'][0]['sendto'][0]
				],
				'properties.user.other.group' => [
					'subject' => 'properties.user.other.group',
					'userid' => ':user:properties.user.other.group',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.user.other.group']['medias'][0]['sendto'][0]
				],
				'properties.admin.other.group' => [
					'subject' => 'properties.admin.other.group',
					'userid' => ':user:properties.admin.other.group',
					'mediatypeid' => 1,
					'sendto' => self::$users[':user:properties.admin.other.group']['medias'][0]['sendto'][0]
				]
			]
		]);

		$id = 0;
		foreach (self::$sessionids as $reference => $foo) {
			$actor = self::getUserFields($reference, ['username', 'passwd']);

			$result = CDataHelper::callRaw([
				'jsonrpc' => '2.0',
				'method' => 'user.login',
				'params' => [
					'username' => $actor['username'],
					'password' => $actor['passwd']
				],
				'id' => ++$id
			]);
			$this->assertArrayHasKey('result', $result);

			self::$sessionids[$reference] = $result['result'];
		}
	}

	public static function cleanTestData(): void {
		CTestDataHelper::cleanUp();
	}

	public static function data_get_user_properties() {
		// Exclude deprecated field.
		$user_extend_output = array_diff(CUser::OUTPUT_FIELDS, ['alias']);

		// User and admin requests should not return results for other user group users: user_other and admin_other.
		return [
			'User can get group-mates by userids' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin gets group-mates by userids' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin gets all users by userids' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User gets only own user record via mediaids' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'mediaids' => [
						':media:user@usertest.com',
						':media:user2@usertest.com',
						':media:admin@usertest.com',
						':media:admin2@usertest.com',
						':media:superadmin@usertest.com',
						':media:user_other@usertest.com',
						':media:admin_other@usertest.com'
					],
					'sortfield' => ['userid']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin gets only own user record via mediaids' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'mediaids' => [
						':media:user@usertest.com',
						':media:user2@usertest.com',
						':media:admin@usertest.com',
						':media:admin2@usertest.com',
						':media:superadmin@usertest.com',
						':media:user_other@usertest.com',
						':media:admin_other@usertest.com'
					]
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin gets all users via mediaids' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'mediaids' => [
						':media:user@usertest.com',
						':media:user2@usertest.com',
						':media:admin@usertest.com',
						':media:admin2@usertest.com',
						':media:superadmin@usertest.com',
						':media:user_other@usertest.com',
						':media:admin_other@usertest.com'
					]
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User gets only own user record via mediatypeid' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'mediatypeids' => ['1']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin gets only own user record via mediatypeid' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'mediatypeids' => ['1']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin gets all users via mediatypeid' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'mediatypeids' => ['1']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User gets full own information' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => ':user:properties.user'
				],
				'expected_result' => [self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS)]
			],
			'Admin gets full own information' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS)]
			],
			'Superadmin gets full own information' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => ':user:properties.superadmin'
				],
				'expected_result' => [self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)]
			],
			'User gets full own information, limited info on group-mates' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.superadmin', CUser::LIMITED_OUTPUT_FIELDS)
				]
			],
			'Admin gets full own information, limited info on group-mates' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.superadmin', CUser::LIMITED_OUTPUT_FIELDS)
				]
			],
			'Superadmin gets full info on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => $user_extend_output,
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user2', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin2', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user.other.group', CUser::OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin.other.group', CUser::OUTPUT_FIELDS)
				]
			],
			'User can use simple filter on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['url' => 'www.user.test']
				],
				'expected_result' => [['userid' => ':user:properties.user']]

			],
			'Admin can use simple filter on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['url' => 'www.admin.test']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use simple filter on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['url' => 'www.superadmin.test']
				],
				'expected_result' => [['userid' => ':user:properties.superadmin']]

			],
			'User can use simple filter on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use simple filter on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use simple filter on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use filter with common fields on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['userid' => ':user:properties.user']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use filter with common fields on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['userid' => ':user:properties.admin']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use filter with common fields on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['userid' => ':user:properties.superadmin']
				],
				'expected_result' => [['userid' => ':user:properties.superadmin']]
			],
			'User can use filter with private fields on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'rows_per_page'])
				]
			],
			'Admin can use filter with private fields on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'rows_per_page'])
				]
			],
			'Superadmin can use filter with private fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.user2', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin2', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.superadmin', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.user.other.group', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin.other.group', ['userid', 'rows_per_page'])
				]
			],
			'User can use filter with non-existing field, result defaults to all group-mates' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321]],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use filter with non-existing field, result defaults to all group-mates' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321]],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use filter with non-existing field, result defaults to all users' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321]],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use filter with private field `autologout` in different time format' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['autologout'],
					'filter' => ['autologout' => [45*60]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'autologout'])
				]
			],
			'Admin can use filter with private field `autologout` in different time format' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['autologout'],
					'filter' => ['autologout' => [45*60]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'autologout'])
				]
			],
			'Superadmin can use filter with field `autologout` in different time format' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['autologout'],
					'filter' => ['autologout' => [45*60]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'autologout']),
					self::getUserFields(':user:properties.user2', ['userid', 'autologout']),
					self::getUserFields(':user:properties.admin', ['userid', 'autologout']),
					self::getUserFields(':user:properties.admin2', ['userid', 'autologout']),
					self::getUserFields(':user:properties.superadmin', ['userid', 'autologout']),
					self::getUserFields(':user:properties.user.other.group', ['userid', 'autologout']),
					self::getUserFields(':user:properties.admin.other.group', ['userid', 'autologout'])
				]
			],
			'User can use filter with private field `refresh` in different time format' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['refresh'],
					'filter' => ['refresh' => [120]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'refresh'])
				]
			],
			'Admin can use filter with private field `refresh` in different time format' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['refresh'],
					'filter' => ['refresh' => [120]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'refresh'])
				]
			],
			'Superadmin can use filter with field `refresh` in different time format' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['refresh'],
					'filter' => ['refresh' => [120]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'refresh']),
					self::getUserFields(':user:properties.user2', ['userid', 'refresh']),
					self::getUserFields(':user:properties.admin', ['userid', 'refresh']),
					self::getUserFields(':user:properties.admin2', ['userid', 'refresh']),
					self::getUserFields(':user:properties.superadmin', ['userid', 'refresh']),
					self::getUserFields(':user:properties.user.other.group', ['userid', 'refresh']),
					self::getUserFields(':user:properties.admin.other.group', ['userid', 'refresh'])
				]
			],
			'User can use searchByAny filter on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use searchByAny filter on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use searchByAny filter on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'filter' => ['timezone' => 'Europe/Rome']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use filter with private fields and searchByAny on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]],
					'searchByAny' => true
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'rows_per_page'])
				]
			],
			'Admin can use filter with private fields and searchByAny on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]],
					'searchByAny' => true
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'rows_per_page'])
				]
			],
			'Superadmin can use filter with private fields and searchByAny fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['rows_per_page'],
					'filter' => ['rows_per_page' => [321]],
					'searchByAny' => true
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.user2', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin2', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.superadmin', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.user.other.group', ['userid', 'rows_per_page']),
					self::getUserFields(':user:properties.admin.other.group', ['userid', 'rows_per_page'])
				]
			],
			'User can use search with common fields only on group-mates' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['surname' => 'Smith']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use search with common fields only on group-mates' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['surname' => 'Smith'],
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use search with common fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['surname' => 'Smith']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use search with private fields on group-mates only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['url' => 'www.'],
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use search with private fields on group-mates only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['url' => 'www.']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use search with private fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['url' => 'www.']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use searchByAny search on group-mates only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'search' => ['surname' => 'Smith']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use searchByAny search on group-mates only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid'],
					'search' => ['surname' => 'Smith']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use searchByAny search on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'search' => ['surname' => 'Smith']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use search with private fields and searchByAny on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['timezone' => 'Europe/Rome'],
					'searchByAny' => true
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use search with private fields and searchByAny on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['timezone' => 'Europe/Rome'],
					'searchByAny' => true
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use search with private fields and searchByAny fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['timezone' => 'Europe/Rome'],
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use filter with non-existing field and searchByAny on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321], 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use filter with non-existing field and searchByAny on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321], 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use filter with non-existing and searchByAny field on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'filter' => ['undefined' => [321], 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use search with non-existing field and searchByAny on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['undefined' => '321', 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [['userid' => ':user:properties.user']]
			],
			'Admin can use search with non-existing field and searchByAny on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['undefined' => '321', 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [['userid' => ':user:properties.admin']]
			],
			'Superadmin can use search with non-existing and searchByAny field on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['undefined' => '321', 'timezone' => 'Europe/Rome'],
					'userids' => array_column(self::$users, 'userid'),
					'searchByAny' => true,
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin'],
					['userid' => ':user:properties.user.other.group'],
					['userid' => ':user:properties.admin.other.group']
				]
			],
			'User can use selectMedias on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'medias' => [['mediaid' => ':media:user@usertest.com']]]]
			],
			'Admin can use selectMedias on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'filter' => ['userid' => [':user:properties.admin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.admin', 'medias' => [['mediaid' => ':media:admin@usertest.com']]]
				]
			],
			'Superadmin can use selectMedias on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'filter' => ['userid' => [':user:properties.superadmin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.superadmin', 'medias' => [['mediaid' => ':media:superadmin@usertest.com']]]
				]
			],
			'User can use selectMedias on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'medias' => [['mediaid' => ':media:user@usertest.com']]],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use selectMedias on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin', 'medias' => [['mediaid' => ':media:admin@usertest.com']]],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use selectMedias on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => ['mediaid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'medias' => [['mediaid' => ':media:user@usertest.com']]],
					['userid' => ':user:properties.user2', 'medias' => [['mediaid' => ':media:user2@usertest.com']]],
					['userid' => ':user:properties.admin', 'medias' => [['mediaid' => ':media:admin@usertest.com']]],
					['userid' => ':user:properties.admin2', 'medias' => [['mediaid' => ':media:admin2@usertest.com']]],
					['userid' => ':user:properties.superadmin', 'medias' => [['mediaid' => ':media:superadmin@usertest.com']]],
					['userid' => ':user:properties.user.other.group', 'medias' => [['mediaid' => ':media:user_other@usertest.com']]],
					['userid' => ':user:properties.admin.other.group', 'medias' => [['mediaid' => ':media:admin_other@usertest.com']]]
				]
			],
			'User can use selectMediatypes on oneself (but empty array returned for users)' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'mediatypes' => []]
				]
			],
			'Admin can use selectMediatypes on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'filter' => ['userid' => [':user:properties.admin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.admin', 'mediatypes' => [['mediatypeid' => '1']]]
				]
			],
			'Superadmin can use selectMediatypes on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'filter' => ['userid' => [':user:properties.superadmin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.superadmin', 'mediatypes' => [['mediatypeid' => '1']]]
				]
			],
			'User can use selectMediatypes on oneself only (but empty array returned for users)' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'mediatypes' => []],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use selectMediatypes on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use selectMediatypes on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.user2', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.admin', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.admin2', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.superadmin', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.user.other.group', 'mediatypes' => [['mediatypeid' => '1']]],
					['userid' => ':user:properties.admin.other.group', 'mediatypes' => [['mediatypeid' => '1']]]
				]
			],
			'User can use selectRole on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'role' => ['roleid' => ':role:properties.user']]
				]
			],
			'Admin can use selectRole on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'filter' => ['userid' => [':user:properties.admin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.admin', 'role' => ['roleid' => ':role:properties.admin']]
				]
			],
			'Superadmin can use selectRole on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'filter' => ['userid' => [':user:properties.superadmin']]
				],
				'expected_result' => [
					['userid' => ':user:properties.superadmin', 'role' => ['roleid' => ':role:properties.superadmin']]
				]
			],
			'User can use selectRole only on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'role' => ['roleid' => ':role:properties.user']],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use selectRole only on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin', 'role' => ['roleid' => ':role:properties.admin']],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use selectRole on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectRole' => ['roleid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'role' => ['roleid' => ':role:properties.user']],
					['userid' => ':user:properties.user2', 'role' => ['roleid' => ':role:properties.user']],
					['userid' => ':user:properties.admin', 'role' => ['roleid' => ':role:properties.admin']],
					['userid' => ':user:properties.admin2', 'role' => ['roleid' => ':role:properties.admin']],
					['userid' => ':user:properties.superadmin', 'role' => ['roleid' => ':role:properties.superadmin']],
					['userid' => ':user:properties.user.other.group', 'role' => ['roleid' => ':role:properties.user']],
					['userid' => ':user:properties.admin.other.group', 'role' => ['roleid' => ':role:properties.admin']]
				]
			],
			'User can use getAccess on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'gui_access', 'debug_mode', 'users_status'])
				]
			],
			'Admin can use getAccess on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'filter' => ['userid' => [':user:properties.admin']]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'gui_access', 'debug_mode', 'users_status'])
				]
			],
			'Superadmin can use getAccess on oneself' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'filter' => ['userid' => [':user:properties.superadmin']]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.superadmin', ['userid', 'gui_access', 'debug_mode', 'users_status'])
				]
			],
			'User can use getAccess only on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					['userid' => ':user:properties.user2'],
					['userid' => ':user:properties.admin'],
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Admin can use getAccess only on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user'],
					['userid' => ':user:properties.user2'],
					self::getUserFields(':user:properties.admin', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					['userid' => ':user:properties.admin2'],
					['userid' => ':user:properties.superadmin']
				]
			],
			'Superadmin can use getAccess on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'getAccess' => true,
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.user2', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.admin', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.admin2', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.superadmin', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.user.other.group', ['userid', 'gui_access', 'debug_mode', 'users_status']),
					self::getUserFields(':user:properties.admin.other.group', ['userid', 'gui_access', 'debug_mode', 'users_status'])
				]
			]
		];
	}

	/**
	 * @dataProvider data_get_user_properties
	 */
	public function testUserFieldAccess_getUserProperties(string $actor, array $parameters,
			array $expected_result) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('user.get', $parameters);

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertUserReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	public static function data_get_role_properties() {
		// Exclude deprecated field.
		$user_extend_output = array_diff(CUser::OUTPUT_FIELDS, ['alias']);

		// User and admin requests should not return user info for other than themselves.
		return [
			'User gets own full users info' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.user',
					'selectUsers' => $user_extend_output
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS)]]
				]
			],
			'User gets own full users info, empty for others' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => array_column(self::$users, 'roleid'),
					'selectUsers' => $user_extend_output,
					'sortfield' => ['roleid']
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS)]],
					['roleid' => ':role:properties.admin', 'users' => []],
					['roleid' => ':role:properties.superadmin', 'users' => []]
				]
			],
			'Admin gets own full users info' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.admin',
					'selectUsers' => $user_extend_output
				],
				'expected_result' => [
					['roleid' => ':role:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS)]]
				]
			],
			'Admin gets own full users info, empty for others' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => array_column(self::$users, 'roleid'),
					'selectUsers' => $user_extend_output,
					'sortfield' => ['roleid']
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => []],
					['roleid' => ':role:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS)]],
					['roleid' => ':role:properties.superadmin', 'users' => []]
				]
			],
			'Superadmin gets own full users info' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.superadmin',
					'selectUsers' => $user_extend_output
				],
				'expected_result' => [
					['roleid' => ':role:properties.superadmin', 'users' => [self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets full users info on everyone, by role' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => array_column(self::$users, 'roleid'),
					'selectUsers' => $user_extend_output,
					'sortfield' => ['roleid']
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => [
						self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS),
						self::getUserFields(':user:properties.user2', CUser::OUTPUT_FIELDS),
						self::getUserFields(':user:properties.user.other.group', CUser::OUTPUT_FIELDS)
					]],
					['roleid' => ':role:properties.admin', 'users' => [
						self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS),
						self::getUserFields(':user:properties.admin2', CUser::OUTPUT_FIELDS),
						self::getUserFields(':user:properties.admin.other.group', CUser::OUTPUT_FIELDS)
					]],
					['roleid' => ':role:properties.superadmin', 'users' => [
						self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)
					]]
				]
			]
		];
	}

	/**
	 * @dataProvider data_get_role_properties
	 */
	public function testUserFieldAccess_getRoleProperties(string $actor, array $parameters,
			array $expected_result) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('role.get', $parameters);

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertRoleReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	public static function data_get_alert_properties() {
		// For non-superadmin users only own alerts are accessible.
		return [
			'User gets own alerts' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => ':user:properties.user'
				],
				'expected_result' => [['alertid' => ':alert:properties.user']]
			],
			'Admin gets own alerts' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [['alertid' => ':alert:properties.admin']]
			],
			'Superadmin gets own alerts' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => ':user:properties.superadmin'
				],
				'expected_result' => [['alertid' => ':alert:properties.superadmin']]
			],
			'User gets only own alerts' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [['alertid' => ':alert:properties.user']]
			],
			'Admin gets only own alerts' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [['alertid' => ':alert:properties.admin']]
			],
			'Superadmin gets all alerts' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user'],
					['alertid' => ':alert:properties.user2'],
					['alertid' => ':alert:properties.admin'],
					['alertid' => ':alert:properties.admin2'],
					['alertid' => ':alert:properties.superadmin'],
					['alertid' => ':alert:properties.user.other.group'],
					['alertid' => ':alert:properties.admin.other.group']
				]
			]
		];
	}

	/**
	 * @dataProvider data_get_alert_properties
	 */
	public function testUserFieldAccess_getAlerts(string $actor, array $parameters,
			array $expected_result) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('alert.get', $parameters);

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertAlertReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	private static function getUserFields(string $reference, array $fields, ?array $except_fields = []) {
		return CTestDataHelper::getObjectFields(self::$users[$reference], $fields, $except_fields);
	}
}
