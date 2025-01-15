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


require_once __DIR__.'/../include/CAPITest.php';
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';
require_once __DIR__.'/../../include/classes/helpers/CArrayHelper.php';

require_once __DIR__.'/../../include/classes/api/services/CMediatype.php';
require_once __DIR__.'/../../include/classes/api/services/CUser.php';
require_once __DIR__.'/../../include/classes/api/services/CUserGroup.php';

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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
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
			'userdirectoryid' => '0',
			'ts_provisioned' => '0',
			'provisioned' => '0',
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
					'period' => '1-7,00:00-24:00',
					'userdirectory_mediaid' => '0',
					'provisioned' => '0'
				]
			]
		]
	];

	private static $mediatype = [
		'mediatypeid' => '1',
		'type' => '0',
		'name' => 'Email',
		'status' => '1',
		'description' => '',
		'maxattempts' => '3',
		'smtp_server' => 'mail.example.com',
		'smtp_helo' => 'example.com',
		'smtp_email' => 'zabbix@example.com',
		'exec_path' => '',
		'gsm_modem' => '',
		'username' => '',
		'passwd' => '',
		'smtp_port' => '25',
		'smtp_security' => '0',
		'smtp_verify_peer' => '0',
		'smtp_verify_host' => '0',
		'smtp_authentication' => '0',
		'maxsessions' => '1',
		'attempt_interval' => '10s',
		'script' => '',
		'timeout' => '30s',
		'process_tags' => '0',
		'show_event_menu' => '0',
		'event_menu_url' => '',
		'event_menu_name' => '',
		'provider' => '0',
		'content_type' => '0',
		'parameters' => [],
		'message_format' => '0'
	];

	private static $usergroups = [
		7 => [
			'usrgrpid' => '7',
			'name' => 'Zabbix administrators',
			'gui_access' => '0',
			'users_status' => '0',
			'debug_mode' => '0',
			'mfa_status' => '0',
			'userdirectoryid' => '0',
			'mfaid' => '0'
		],
		11 => [
			'usrgrpid' => '11',
			'name' => 'Enabled debug mode',
			'gui_access' => '0',
			'users_status' => '0',
			'debug_mode' => '1',
			'mfa_status' => '0',
			'userdirectoryid' => '0',
			'mfaid' => '0'
		],
		':user_group:properties.main' => [
			'usrgrpid' => ':user_group:properties.main',
			'name' => 'properties.main',
			'gui_access' => '0',
			'users_status' => '0',
			'debug_mode' => '0',
			'mfa_status' => '1',
			'userdirectoryid' => ':user_directory:user.properties.userdirectory',
			'mfaid' => ':mfa:user.properties.mfa'
		],
		':user_group:properties.other.group' => [
			'usrgrpid' => ':user_group:properties.other.group',
			'name' => 'properties.other.group',
			'gui_access' => '0',
			'users_status' => '0',
			'debug_mode' => '0',
			'mfa_status' => '0',
			'userdirectoryid' => ':user_directory:user.properties.userdirectory',
			'mfaid' => '0'
		]
	];

	public function prepareTestData() {
		$user_fields = ['username', 'name', 'surname', 'url', 'rows_per_page', 'roleid', 'passwd', 'timezone',
			'autologout', 'refresh', 'usrgrps', 'medias'
		];
		$user_except_fields = ['medias.mediaid', 'medias.userdirectory_mediaid', 'medias.provisioned'];

		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'user.properties.group.main']
			],
			'mfas' => [
				['name' => 'user.properties.mfa']
			],
			'user_directories' => [
				['name' => 'user.properties.userdirectory']
			],
			'user_groups' => [
				[
					'name' => 'properties.main',
					'users_status' => GROUP_STATUS_ENABLED,
					'mfa_status' => GROUP_MFA_ENABLED,
					'mfaid' => ':mfa:user.properties.mfa',
					'userdirectoryid' => ':user_directory:user.properties.userdirectory',
					'hostgroup_rights' => [
						'id' => ':host_group:user.properties.group.main',
						'permission' => PERM_READ_WRITE
					]
				],
				[
					'name' => 'properties.other.group',
					'users_status' => GROUP_STATUS_ENABLED,
					'userdirectoryid' => ':user_directory:user.properties.userdirectory',
					'hostgroup_rights' => [
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
		$denied_field = current(array_diff(CUser::OUTPUT_MEDIA_FIELDS, CUser::LIMITED_OUTPUT_MEDIA_FIELDS));
		$denied_media_field_index = array_search($denied_field, CUser::OUTPUT_MEDIA_FIELDS) + 1;

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
			'User gets limited own information' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => 'extend',
					'userids' => ':user:properties.user'
				],
				'expected_result' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]
			],
			'Admin gets limited own information' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => 'extend',
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]
			],
			'Superadmin gets full own information' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => 'extend',
					'userids' => ':user:properties.superadmin'
				],
				'expected_result' => [self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)]
			],
			'User gets own limited information, limited info on group-mates' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => 'extend',
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.superadmin', CUser::LIMITED_OUTPUT_FIELDS)
				]
			],
			'Admin gets own limited information, limited info on group-mates' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.user2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.admin2', CUser::LIMITED_OUTPUT_FIELDS),
					self::getUserFields(':user:properties.superadmin', CUser::LIMITED_OUTPUT_FIELDS)
				]
			],
			'Superadmin gets full info on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => 'extend',
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
					'output' => ['userid', 'rows_per_page'],
					'filter' => ['rows_per_page' => [321]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'rows_per_page'])
				]
			],
			'Admin can use filter with private fields on oneself only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid', 'rows_per_page'],
					'filter' => ['rows_per_page' => [321]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'rows_per_page'])
				]
			],
			'Superadmin can use filter with private fields on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid', 'rows_per_page'],
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
			'User can use filter with private field `autologout` in different time format' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid', 'autologout'],
					'filter' => ['autologout' => [45*60]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'autologout'])
				]
			],
			'Admin can use filter with private field `autologout` in different time format' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid', 'autologout'],
					'filter' => ['autologout' => [45*60]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'autologout'])
				]
			],
			'Superadmin can use filter with field `autologout` in different time format' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid', 'autologout'],
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
					'output' => ['userid', 'refresh'],
					'filter' => ['refresh' => [120]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.user', ['userid', 'refresh'])
				]
			],
			'Admin can use filter with private field `refresh` in different time format' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid', 'refresh'],
					'filter' => ['refresh' => [120]]
				],
				'expected_result' => [
					self::getUserFields(':user:properties.admin', ['userid', 'refresh'])
				]
			],
			'Superadmin can use filter with field `refresh` in different time format' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid', 'refresh'],
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
					'output' => ['userid', 'rows_per_page'],
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
					'output' => ['userid', 'rows_per_page'],
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
					'output' => ['userid', 'rows_per_page'],
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
			'User can use search with private fields on own groups only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['url' => 'www.'],
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user']

				]
			],
			'Admin can use search with private fields on own groups only' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'search' => ['url' => 'www.']
				],
				'expected_result' => [

					['userid' => ':user:properties.admin']
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
			'User denied full info via selectMedias on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => CUser::OUTPUT_MEDIA_FIELDS,
					'userids' => ':user:properties.user'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMedias/'.$denied_media_field_index.
					'": value must be one of "'.implode('", "', CUser::LIMITED_OUTPUT_MEDIA_FIELDS).'".'
			],
			'User denied full info via selectMedias on oneself through filter' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => CUser::OUTPUT_MEDIA_FIELDS,
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMedias/'.$denied_media_field_index.
					'": value must be one of "'.implode('", "', CUser::LIMITED_OUTPUT_MEDIA_FIELDS).'".'
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
			'Admin denied full info via selectMedias on oneself' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => CUser::OUTPUT_MEDIA_FIELDS,
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMedias/'.$denied_media_field_index.
					'": value must be one of "'.implode('", "', CUser::LIMITED_OUTPUT_MEDIA_FIELDS).'".'
			],
			'Admin denied full info via selectMedias on oneself through filter' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => CUser::OUTPUT_MEDIA_FIELDS,
					'filter' => ['userid' => [':user:properties.admin']]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMedias/'.$denied_media_field_index.
					'": value must be one of "'.implode('", "', CUser::LIMITED_OUTPUT_MEDIA_FIELDS).'".'
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
			'Superadmin get full info via selectMedias on everyone' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userid'],
					'selectMedias' => 'extend',
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'medias' => self::getUserMediaFields(':user:properties.user', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.user2', 'medias' => self::getUserMediaFields(':user:properties.user2', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.admin', 'medias' => self::getUserMediaFields(':user:properties.admin', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.admin2', 'medias' => self::getUserMediaFields(':user:properties.admin2', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.superadmin', 'medias' => self::getUserMediaFields(':user:properties.superadmin', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.user.other.group', 'medias' => self::getUserMediaFields(':user:properties.user.other.group', CUser::OUTPUT_MEDIA_FIELDS)],
					['userid' => ':user:properties.admin.other.group', 'medias' => self::getUserMediaFields(':user:properties.admin.other.group', CUser::OUTPUT_MEDIA_FIELDS)]
				]
			],
			'User can use selectMediatypes on oneself' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'filter' => ['userid' => [':user:properties.user']]
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'mediatypes' => [['mediatypeid' => '1']]]
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
			'User can use selectMediatypes on oneself only' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userid'],
					'selectMediatypes' => ['mediatypeid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['userid']
				],
				'expected_result' => [
					['userid' => ':user:properties.user', 'mediatypes' => [['mediatypeid' => '1']]],
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
			array $expected_result, ?string $expected_error = null) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('user.get', $parameters, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertUserReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	public static function data_get_role_properties() {
		$denied_field = current(array_diff(CUser::OUTPUT_FIELDS, CUser::OWN_LIMITED_OUTPUT_FIELDS));
		$denied_own_user_field_index = array_search($denied_field, CUser::OUTPUT_FIELDS) + 1;

		// User and admin requests should not return user info for other than themselves.
		return [
			'User denied own full users info' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.user',
					'selectUsers' => CUser::OUTPUT_FIELDS
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectUsers/'.$denied_own_user_field_index.
					'": value must be one of "'.implode('", "', CUser::OWN_LIMITED_OUTPUT_FIELDS).'".'
			],
			'User gets own limited users info' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.user',
					'selectUsers' => 'extend'
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'User gets own limited users info, no info for others' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => array_column(self::$users, 'roleid'),
					'selectUsers' => 'extend',
					'sortfield' => ['roleid']
				],
				'expected_result' => [
					['roleid' => ':role:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin denied own full users info' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.admin',
					'selectUsers' => CUser::OUTPUT_FIELDS
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectUsers/'.$denied_own_user_field_index.
					'": value must be one of "'.implode('", "', CUser::OWN_LIMITED_OUTPUT_FIELDS).'".'
			],
			'Admin gets own limited users info' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.admin',
					'selectUsers' => 'extend'
				],
				'expected_result' => [
					['roleid' => ':role:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin gets own limited users info, no info for others' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => array_column(self::$users, 'roleid'),
					'selectUsers' => 'extend',
					'sortfield' => ['roleid']
				],
				'expected_result' => [
					['roleid' => ':role:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets own full users info' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['roleid'],
					'roleids' => ':role:properties.superadmin',
					'selectUsers' => 'extend'
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
					'selectUsers' => 'extend',
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
			array $expected_result, ?string $expected_error = null) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('role.get', $parameters, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertRoleReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	public static function data_get_alert_properties() {
		$denied_field = current(array_diff(CUser::OUTPUT_FIELDS, CUser::OWN_LIMITED_OUTPUT_FIELDS));
		$denied_own_user_field_index = array_search($denied_field, CUser::OUTPUT_FIELDS) + 1;

		$denied_field = current(array_diff(CMediatype::OUTPUT_FIELDS, CMediatype::LIMITED_OUTPUT_FIELDS));
		$denied_mediatype_field_index = array_search($denied_field, CMediatype::OUTPUT_FIELDS) + 1;

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
			],
			'User is denied full info through selectUsers' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OUTPUT_FIELDS,
					'userids' => ':user:properties.user'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectUsers/'.$denied_own_user_field_index.
					'": value must be one of "'.implode('", "', CUser::OWN_LIMITED_OUTPUT_FIELDS).'".'
			],
			'User gets own limited info through selectUsers' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OWN_LIMITED_OUTPUT_FIELDS,
					'userids' => ':user:properties.user'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'User gets own limited info through selectUsers=extend' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => 'extend',
					'userids' => ':user:properties.user'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'User gets only own limited info through selectUsers' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin is denied full info through selectUsers' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OUTPUT_FIELDS,
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectUsers/'.$denied_own_user_field_index.
					'": value must be one of "'.implode('", "', CUser::OWN_LIMITED_OUTPUT_FIELDS).'".'
			],
			'Admin gets own limited info through selectUsers' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OWN_LIMITED_OUTPUT_FIELDS,
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin gets own limited info through selectUsers=extend' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => 'extend',
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin gets only own limited info through selectUsers' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OWN_LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets own alerts and full info with selectUsers' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OUTPUT_FIELDS,
					'userids' => ':user:properties.superadmin'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.superadmin', 'users' => [self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets all alerts and full info on all users with selectUsers' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'selectUsers' => CUser::OUTPUT_FIELDS,
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'users' => [self::getUserFields(':user:properties.user', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.user2', 'users' => [self::getUserFields(':user:properties.user2', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin', 'users' => [self::getUserFields(':user:properties.admin', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin2', 'users' => [self::getUserFields(':user:properties.admin2', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.superadmin', 'users' => [self::getUserFields(':user:properties.superadmin', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.user.other.group', 'users' => [self::getUserFields(':user:properties.user.other.group', CUser::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin.other.group', 'users' => [self::getUserFields(':user:properties.admin.other.group', CUser::OUTPUT_FIELDS)]]
				]
			],
			'User gets denied full mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => CMediatype::OUTPUT_FIELDS,
					'userids' => ':user:properties.user'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMediatypes/'.$denied_mediatype_field_index.'": value must be one of "'.
					implode('", "', CMediatype::LIMITED_OUTPUT_FIELDS).'".'
			],
			'User gets own limited mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => ':user:properties.user'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'mediatypes' => [self::getMediatypeFields(CMediatype::LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'User gets only own limited mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'mediatypes' => [self::getMediatypeFields(CMediatype::LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin gets denied full mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => CMediatype::OUTPUT_FIELDS,
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectMediatypes/'.$denied_mediatype_field_index.'": value must be one of "'.
					implode('", "', CMediatype::LIMITED_OUTPUT_FIELDS).'".'
			],
			'Admin gets own limited mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => ':user:properties.admin'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.admin', 'mediatypes' => [self::getMediatypeFields(CMediatype::LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Admin gets only own limited mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.admin', 'mediatypes' => [self::getMediatypeFields(CMediatype::LIMITED_OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets own full mediatype info via selectMediatypes' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => ':user:properties.superadmin'
				],
				'expected_result' => [
					['alertid' => ':alert:properties.superadmin', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]]
				]
			],
			'Superadmin gets full mediatype info for all users via selectMediatypes' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['alertid'],
					'selectMediatypes' => 'extend',
					'userids' => array_column(self::$users, 'userid')
				],
				'expected_result' => [
					['alertid' => ':alert:properties.user', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.user2', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin2', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.superadmin', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.user.other.group', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]],
					['alertid' => ':alert:properties.admin.other.group', 'mediatypes' => [self::getMediatypeFields(CMediatype::OUTPUT_FIELDS)]]
				]
			]
		];
	}

	/**
	 * @dataProvider data_get_alert_properties
	 */
	public function testUserFieldAccess_getAlerts(string $actor, array $parameters,
			array $expected_result, ?string $expected_error = null) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('alert.get', $parameters, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertAlertReferences($expected_result);

		$this->assertEquals($expected_result, $result['result']);
	}

	public static function data_get_usergroup_properties() {
		$denied_field = current(array_diff(CUserGroup::OUTPUT_FIELDS, CUserGroup::LIMITED_OUTPUT_FIELDS));
		$denied_usergroup_field_index = array_search($denied_field, CUserGroup::OUTPUT_FIELDS) + 1;

		// For non-super admin users only own group records are accessible.
		return [
			'User gets own usergoups' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.user',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', ['usrgrpid'])
			],
			'Admin gets own usergoups' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.admin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', ['usrgrpid'])
			],
			'Superadmin gets own usergroups' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.superadmin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', ['usrgrpid'])
			],
			'User gets no editable usergoups' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.user',
					'editable' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => []
			],
			'Admin gets no editable usergoups' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.admin',
					'editable' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => []
			],
			'Superadmin gets all editable usergroups' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => ':user:properties.superadmin',
					'editable' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', ['usrgrpid'])
			],
			'User gets only own usergroups' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', ['usrgrpid'])
			],
			'Admin gets only own usergroups' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', ['usrgrpid'])
			],
			'Superadmin gets all usergroup infos' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => 'extend',
					'userids' => array_column(self::$users, 'userid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => array_values(self::$usergroups)
			],
			'User is denied full usergroup info' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => CUserGroup::OUTPUT_FIELDS,
					'userids' => ':user:properties.user',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/'.$denied_usergroup_field_index.
					'": value must be one of "'.implode('", "', CUserGroup::LIMITED_OUTPUT_FIELDS).'".'
			],
			'Admin is denied full usergroup info' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => CUserGroup::OUTPUT_FIELDS,
					'userids' => ':user:properties.admin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/'.$denied_usergroup_field_index.
					'": value must be one of "'.implode('", "', CUserGroup::LIMITED_OUTPUT_FIELDS).'".'
			],
			'Superadmin gets full usergroup info' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => CUserGroup::OUTPUT_FIELDS,
					'userids' => ':user:properties.superadmin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', CUserGroup::OUTPUT_FIELDS)
			],
			'User is denied full usergroup info via output=extend' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => 'extend',
					'userids' => ':user:properties.user',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', CUserGroup::LIMITED_OUTPUT_FIELDS)
			],
			'Admin is denied full usergroup info via output=extend' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => 'extend',
					'userids' => ':user:properties.admin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', CUserGroup::LIMITED_OUTPUT_FIELDS)
			],
			'Superadmin gets full usergroup info via output=extend' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => CUserGroup::OUTPUT_FIELDS,
					'userids' => ':user:properties.superadmin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', CUserGroup::OUTPUT_FIELDS)
			],
			'User is denied private field in output' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['userdirectoryid'],
					'userids' => ':user:properties.user',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "'.
					implode('", "', CUserGroup::LIMITED_OUTPUT_FIELDS).'".'
			],
			'Admin is denied private field in output' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['userdirectoryid'],
					'userids' => ':user:properties.admin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "'.
					implode('", "', CUserGroup::LIMITED_OUTPUT_FIELDS).'".'
			],
			'Superadmin is not denied private field in output' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['userdirectoryid'],
					'userids' => ':user:properties.superadmin',
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', ['usrgrpid', 'userdirectoryid'])
			],
			'User is denied filtering by mfaid' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'mfaids' => ':mfa:user.properties.mfa'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "mfaids".'
			],
			'Admin is denied filtering by mfaid' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'mfaids' => ':mfa:user.properties.mfa'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "mfaids".'
			],
			'Superadmin can filter by mfaid' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'mfaids' => ':mfa:user.properties.mfa'
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main']
				]
			],
			'User can filter own groups by common field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.user', 'usrgrpid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', ['usrgrpid'])
			],
			'Admin can filter own groups by common field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.admin', 'usrgrpid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', ['usrgrpid'])
			],
			'Superadmin can filter own groups by common field' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.superadmin', 'usrgrpid'),
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', ['usrgrpid'])
			],
			'User can filter only own groups by common field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => ['usrgrpid' => array_column(self::$usergroups, 'usrgrpid')],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', ['usrgrpid'])
			],
			'Admin can filter only own groups by common field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => ['usrgrpid' => array_column(self::$usergroups, 'usrgrpid')],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', ['usrgrpid'])
			],
			'Superadmin can filter all groups by common field' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => 'extend',
					'filter' => ['usrgrpid' => array_column(self::$usergroups, 'usrgrpid')],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => array_values(self::$usergroups)
			],
			'User denied filter using single private field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => ['userdirectoryid' => array_column(self::$usergroups, 'userdirectoryid')],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Admin denied filter using single private field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => ['userdirectoryid' => array_column(self::$usergroups, 'userdirectoryid')],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Superadmin allowed filter using private field' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => ['userdirectoryid' => [':user_directory:user.properties.userdirectory']],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main'],
					['usrgrpid' => ':user_group:properties.other.group']
				]
			],
			'User can filter own group by common field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.user', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main']
				]
			],
			'Admin can filter own group by common field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.admin', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main']
				]
			],
			'Superadmin can filter all groups by common fields' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.superadmin', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main']
				]
			],
			'User denied filter with private and common field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.user', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Admin denied filter with private and common field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.admin', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Superadmin allowed filter with private field' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.superadmin', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main']
				]
			],
			'User can filter own groups by common fields and searchByAny' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.user', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.user', ['usrgrpid'])
			],
			'Admin can filter own groups by common fields and searchByAny' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.admin', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.admin', ['usrgrpid'])
			],
			'Superadmin can filter by common fields and searchByAny' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.superadmin', 'usrgrpid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => self::getUsergroupFields(':user:properties.superadmin', ['usrgrpid'])
			],
			'User denied filter by private field, with searchByAny on it and common field' => [
				'actor' => ':user:properties.user',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.user', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Admin denied filter by private field, with searchByAny on it and common field' => [
				'actor' => ':user:properties.admin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.admin', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "userdirectoryid".'
			],
			'Superadmin can filter by private fields and searchByAny' => [
				'actor' => ':user:properties.superadmin',
				'parameters' => [
					'output' => ['usrgrpid'],
					'filter' => self::getUsergroupFilter(':user:properties.superadmin', 'userdirectoryid') + [
						'name' => 'properties.main'
					],
					'searchByAny' => true,
					'sortfield' => ['usrgrpid']
				],
				'expected_result' => [
					['usrgrpid' => ':user_group:properties.main'],
					['usrgrpid' => ':user_group:properties.other.group']
				]
			]
		];
	}

	/**
	 * @dataProvider data_get_usergroup_properties
	 */
	public function testUserFieldAccess_getUsergroups(string $actor, array $parameters,
			array $expected_result, ?string $expected_error = null) {
		CAPIHelper::setSessionId(self::$sessionids[$actor]);

		CTestDataHelper::resolveRequestReferences($parameters);

		$result = $this->call('usergroup.get', $parameters, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$this->assertArrayHasKey('result', $result);

		CTestDataHelper::convertUserGroupReferences($expected_result);

		CArrayHelper::sort($expected_result, ['usrgrpid']);

		$this->assertEquals(array_values($expected_result), $result['result']);
	}

	private static function getUserFields(string $reference, array $fields, ?array $except_fields = []) {
		return CTestDataHelper::getObjectFields(self::$users[$reference], $fields, $except_fields);
	}

	private static function getMediatypeFields(array $fields) {
		return CTestDataHelper::getObjectFields(self::$mediatype, $fields);
	}

	private static function getUserMediaFields(string $reference, array $fields) {
		$medias = self::$users[$reference]['medias'];

		foreach ($medias as &$media) {
			$media = CTestDataHelper::getObjectFields($media, $fields);
		}
		unset($media);

		return $medias;
	}

	private static function getUsergroupFields(string $reference, array $fields): array {
		$groups = self::$users[$reference]['usrgrps'];

		foreach ($groups as &$group) {
			$group = CTestDataHelper::getObjectFields(self::$usergroups[$group['usrgrpid']], $fields);
		}
		unset($group);

		return $groups;
	}

	private static function getUsergroupFilter(string $reference, string $field): array {
		$groups = array_column(self::$users[$reference]['usrgrps'], 'usrgrpid', 'usrgrpid');
		$groups = array_intersect_key(self::$usergroups, $groups);

		return [$field => array_column($groups, $field)];
	}
}
