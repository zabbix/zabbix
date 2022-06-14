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
 * @backup users
 */
class testUsers extends CAPITest {

	public static function user_create() {
		return [
			// Check user password.
			[
				'user' => [
					'username' => 'API user create without password',
					'roleid' => 1,
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'Incorrect value for field "passwd": cannot be empty.'
			],
			// Check user username.
			[
				'user' => [
					'passwd' => 'zabbix',
					'roleid' => 1,
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "username" is missing.'
			],
			[
				'user' => [
					'username' => '',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'Admin',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Incorrect value for field "/1/passwd": must be at least 8 characters long.'
			],
			[
				'user' => [
					[
						'username' => 'API create users with the same names',
						'roleid' => 1,
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					],
					[
						'username' => 'API create users with the same names',
						'roleid' => 1,
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (username)=(API create users with the same names)'.
					' already exists.'
			],
			[
				'user' => [
					'username' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwert'.
						'yuioplkjhgfdsazxcvbnm',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7
					]
				],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],
			// Check user group.
			[
				'user' => [
					'username' => 'User without group parameter',
					'roleid' => 1,
					'passwd' => 'zabbix'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "usrgrps" is missing.'
			],
			[
				'user' => [
					'username' => 'User without group',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'Group unexpected parameter',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['userid' => '1']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1": unexpected parameter "userid".'
			],
			[
				'user' => [
					'username' => 'User with empty group id',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User group id not number',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 'abc']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User group id not valid',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '1.1']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User with nonexistent group id',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '123456']
					]
				],
				'expected_error' => 'User group with ID "123456" is not available.'
			],
			[
				'user' => [
					'username' => 'User with two identical user group id',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7'],
						['usrgrpid' => '7']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/2": value (usrgrpid)=(7) already exists.'
			],
			// Roleid is missing.
			[
				'user' => [
					[
						'username' => 'API user create 1',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "roleid" is missing.'
			],
			// Roleid is as a string.
			[
				'user' => [
					[
						'username' => 'API user create 1',
						'roleid' => 'twenty_five',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			// Check successfully creation of user.
			[
				'user' => [
					[
						'username' => 'API user create 1',
						'roleid' => 1,
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => null
			],
			[
				'user' => [
					[
						'username' => '☺',
						'roleid' => 1,
						'passwd' => 'O0o@O0o@',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => null
			],
			[
				'user' => [
					[
						'username' => 'УТФ Юзер',
						'roleid' => 1,
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => null
			],
			[
				'user' => [
					[
						'username' => 'API user create with media',
						'roleid' => 1,
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						],
						'medias' => [
							[
								'mediatypeid' => '1',
								'sendto' => 'api@zabbix.com'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider user_create
	*/
	public function testUsers_Create($user, $expected_error) {
		$result = $this->call('user.create', $user, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['userids'] as $key => $id) {
				$dbResultUser = DBSelect('select * from users where userid='.zbx_dbstr($id));
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['username'], $user[$key]['username']);
				$this->assertEquals($dbRowUser['name'], '');
				$this->assertEquals($dbRowUser['surname'], '');
				$this->assertEquals($dbRowUser['autologin'], 0);
				$this->assertEquals($dbRowUser['autologout'], '15m');
				$this->assertEquals($dbRowUser['lang'], 'default');
				$this->assertEquals($dbRowUser['refresh'], '30s');
				$this->assertEquals($dbRowUser['rows_per_page'], 50);
				$this->assertEquals($dbRowUser['theme'], 'default');
				$this->assertEquals($dbRowUser['url'], '');

				$this->assertEquals(1, CDBHelper::getCount('select * from users_groups where userid='.zbx_dbstr($id).
						' and usrgrpid='.zbx_dbstr($user[$key]['usrgrps'][0]['usrgrpid']))
				);

				if (array_key_exists('medias', $user[$key])) {
					$dbResultMedia = DBSelect('select * from media where userid='.$id);
					$dbRowMedia = DBFetch($dbResultMedia);
					$this->assertEquals($dbRowMedia['mediatypeid'], $user[$key]['medias'][0]['mediatypeid']);
					$this->assertEquals($dbRowMedia['sendto'], $user[$key]['medias'][0]['sendto']);
					$this->assertEquals($dbRowMedia['active'], 0);
					$this->assertEquals($dbRowMedia['severity'], 63);
					$this->assertEquals($dbRowMedia['period'], '1-7,00:00-24:00');
				}
				else {
					$dbResultGroup = 'select * from media where userid='.$id;
					$this->assertEquals(0, CDBHelper::getCount($dbResultGroup));
				}
			}
		}
	}

	/**
	* Create user with multiple email address
	*/
	public function testUsers_CreateUserWithMultipleEmails() {
		$user = [
			'username' => 'API user create with multiple emails',
			'roleid' => 1,
			'passwd' => 'Z@bb1x1234',
			'usrgrps' => [
				['usrgrpid' => 7]
			],
			'medias' => [
				[
					'mediatypeid' => '1',
					'sendto' => ["api1@zabbix.com","Api test <api2@zabbix.com>","АПИ test ☺æų <api2@zabbix.com>"]
				]
			]
		];

		$result = $this->call('user.create', $user);
		$id = $result['result']['userids'][0];
		$this->assertEquals(1, CDBHelper::getCount('select * from users where userid='.zbx_dbstr($id)));

		$dbResultMedia = DBSelect('select * from media where userid='.zbx_dbstr($id));
		$dbRowMedia = DBFetch($dbResultMedia);
		$diff = array_diff($user['medias'][0]['sendto'], explode("\n", $dbRowMedia['sendto']));
		$this->assertEquals(0, count($diff));
	}

	public static function user_update() {
		return [
			// Check user id.
			[
				'user' => [[
					'username' => 'API user update without userid'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "userid" is missing.'
			],
			[
				'user' => [[
					'username' => 'API user update with empty userid',
					'userid' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'username' => 'API user update with nonexistent userid',
					'userid' => '1.1'
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'username' => 'API user update with nonexistent userid',
					'userid' => 'abc'
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'username' => 'API user update with nonexistent userid',
					'userid' => '123456'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => [
					[
						'userid' => '9',
						'username' => 'API update users with the same id1'
					],
					[
						'userid' => '9',
						'username' => 'API update users with the same id2'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (userid)=(9) already exists.'
			],
			// Check user password.
			[
				'user' => [[
					'userid' => '2',
					'passwd' => 'Z@bb1x1234'
				]],
				'expected_error' => 'Not allowed to set password for user "guest".'
			],
			// Check user username.
			[
				'user' => [[
					'userid' => '9',
					'username' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/username": cannot be empty.'
			],
			[
				'user' => [[
					'userid' => '2',
					'username' => 'Try to rename guest'
				]],
				'expected_error' => 'Cannot rename guest user.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'Admin'
				]],
				'expected_error' => 'User with username "Admin" already exists.'
			],
			[
				'user' => [
					[
						'userid' => '9',
						'username' => 'API update users with the same username'
					],
					[
						'userid' => '10',
						'username' => 'API update users with the same username'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value'.
					' (username)=(API update users with the same username) already exists.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwert'.
						'yuioplkjhgfdsazxcvbnm'
				]],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],
			// Check user group.
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User without group',
					'usrgrps' => [
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps": cannot be empty.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'Group unexpected parameter',
					'usrgrps' => [
						['userid' => '1']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1": unexpected parameter "userid".'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User with empty group id',
					'usrgrps' => [
						['usrgrpid' => '']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User group id not number',
					'usrgrps' => [
						['usrgrpid' => 'abc']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User group id not valid',
					'usrgrps' => [
						['usrgrpid' => '1.1']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User with nonexistent group id',
					'usrgrps' => [
						['usrgrpid' => '123456']
					]
				]],
				'expected_error' => 'User group with ID "123456" is not available.'
			],
			[
				'user' => [[
					'userid' => '9',
					'username' => 'User with two identical user group id',
					'usrgrps' => [
						['usrgrpid' => '7'],
						['usrgrpid' => '7']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/2": value (usrgrpid)=(7) already exists.'
			],
			// Check user group, admin can't add himself to a disabled group or a group with disabled GUI access.
			[
				'user' => [[
					'userid' => '1',
					'username' => 'Try to add user to group with disabled GUI access',
					'usrgrps' => [
						['usrgrpid' => '12']
					]
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'user' => [[
					'userid' => '1',
					'username' => 'Try to add user to a disabled group',
					'usrgrps' => [
						['usrgrpid' => '9']
					]
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			// Check user properties, super-admin user type.
			[
				'user' => [[
					'userid' => '1',
					'username' => 'Try to change super-admin user type',
					'roleid' => '2'
				]],
				'expected_error' => 'At least one active user must exist with role "Super admin role".'
			],
			// Successfully user update.
			[
				'user' => [
					[
						'userid' => '9',
						'username' => 'API user updated',
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => null
			],
			[
				'user' => [
					[
						'userid' => '9',
						'username' => 'УТФ Юзер обновлённ',
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					]
				],
				'expected_error' => null
			],
			[
				'user' => [
					[
						'userid' => '9',
						'username' => 'API user update with media',
						'passwd' => 'Z@bb1x1234',
						'usrgrps' => [
							['usrgrpid' => 7]
						],
						'medias' => [
							[
								'mediatypeid' => '1',
								'sendto' => 'api@zabbix.com'
							]
						]
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider user_update
	*/
	public function testUsers_Update($users, $expected_error) {
		foreach ($users as $user) {
			if (array_key_exists('userid', $user) && filter_var($user['userid'], FILTER_VALIDATE_INT)
					&& $expected_error !== null) {
				$sqlUser = "select * from users where userid=".zbx_dbstr($user['userid']);
				$oldHashUser = CDBHelper::getHash($sqlUser);
			}
		}

		$result = $this->call('user.update', $users, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['userids'] as $key => $id) {
				$dbResultUser = DBSelect('select * from users where userid='.zbx_dbstr($id));
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['username'], $users[$key]['username']);
				$this->assertEquals($dbRowUser['name'], '');
				$this->assertEquals($dbRowUser['surname'], '');
				$this->assertEquals($dbRowUser['autologin'], 0);
				$this->assertEquals($dbRowUser['autologout'], '15m');
				$this->assertEquals($dbRowUser['lang'], 'en_US');
				$this->assertEquals($dbRowUser['refresh'], '30s');
				$this->assertEquals($dbRowUser['rows_per_page'], 50);
				$this->assertEquals($dbRowUser['theme'], 'default');
				$this->assertEquals($dbRowUser['url'], '');

				$this->assertEquals(1, CDBHelper::getCount('select * from users_groups where userid='.zbx_dbstr($id).
						' and usrgrpid='.zbx_dbstr($users[$key]['usrgrps'][0]['usrgrpid']))
				);

				if (array_key_exists('medias', $users[$key])) {
					$dbResultMedia = DBSelect('select * from media where userid='.zbx_dbstr($id));
					$dbRowMedia = DBFetch($dbResultMedia);
					$this->assertEquals($dbRowMedia['mediatypeid'], $users[$key]['medias'][0]['mediatypeid']);
					$this->assertEquals($dbRowMedia['sendto'], $users[$key]['medias'][0]['sendto']);
					$this->assertEquals($dbRowMedia['active'], 0);
					$this->assertEquals($dbRowMedia['severity'], 63);
					$this->assertEquals($dbRowMedia['period'], '1-7,00:00-24:00');
				}
				else {
					$dbResultGroup = 'select * from media where userid='.zbx_dbstr($id);
					$this->assertEquals(0, CDBHelper::getCount($dbResultGroup));
				}
			}
		}
		else {
			if (isset($oldHashUser)) {
				$this->assertEquals($oldHashUser, CDBHelper::getHash($sqlUser));
			}
		}
	}

	public static function user_properties() {
		return [
			// Check readonly parameter.
			[
				'user' => [
					'username' => 'Unexpected parameter attempt_clock',
					'passwd' => 'zabbix',
					'attempt_clock' => '0',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "attempt_clock".'
			],
			[
				'user' => [
					'username' => 'Unexpected parameter attempt_failed',
					'passwd' => 'zabbix',
					'attempt_failed' => '3',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "attempt_failed".'
			],
			[
				'user' => [
					'username' => 'Unexpected parameter attempt_ip',
					'passwd' => 'zabbix',
					'attempt_ip' => '127.0.0.1',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "attempt_ip".'
			],
			// Check user properties, name and surname.
			[
				'user' => [
					'username' => 'User with long name',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'name' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm'
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'user' => [
					'username' => 'User with long surname',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'surname' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm'
				],
				'expected_error' => 'Invalid parameter "/1/surname": value is too long.'
			],
			// Check user properties, autologin.
			[
				'user' => [
					'username' => 'User with invalid autologin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologin' => ''
				],
				'expected_error' => 'Invalid parameter "/1/autologin": an integer is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid autologin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologin' => '2'
				],
				'expected_error' => 'Invalid parameter "/1/autologin": value must be one of 0, 1.'
			],
			[
				'user' => [
					'username' => 'User with invalid autologin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologin' => '-1'
				],
				'expected_error' => 'Invalid parameter "/1/autologin": value must be one of 0, 1.'
			],
			// Check user properties, autologout.
			[
				'user' => [
					'username' => 'User with invalid autologout',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologout' => ''
				],
				'expected_error' => 'Invalid parameter "/1/autologout": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with invalid autologout',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologout' => '86401'
				],
				'expected_error' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			],
			[
				'user' => [
					'username' => 'User with invalid autologout',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologout' => '1'
				],
				'expected_error' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			],
			[
				'user' => [
					'username' => 'User with invalid autologout',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologout' => '89'
				],
				'expected_error' => 'Invalid parameter "/1/autologout": value must be one of 0, 90-86400.'
			],
			[
				'user' => [
					'username' => 'User with autologout and autologin together',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologout' => '90',
					'autologin' => '1'
				],
				'expected_error' => 'Auto-login and auto-logout options cannot be enabled together.'
			],
			// Check user properties, lang.
			[
				'user' => [
					'username' => 'User with empty lang',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'lang' => ''
				],
				'expected_error' => 'Invalid parameter "/1/lang": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with invalid lang',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'lang' => '123456'
				],
				'expected_error' => 'Invalid parameter "/1/lang": value must be one of "default", "en_GB", "en_US", "bg_BG", "ca_ES", "zh_CN", "zh_TW", "cs_CZ", "nl_NL", "fi_FI", "fr_FR", "ka_GE", "de_DE", "el_GR", "he_IL", "hu_HU", "id_ID", "it_IT", "ko_KR", "ja_JP", "lv_LV", "lt_LT", "nb_NO", "fa_IR", "pl_PL", "pt_BR", "pt_PT", "ro_RO", "ru_RU", "sk_SK", "es_ES", "sv_SE", "tr_TR", "uk_UA", "vi_VN".'
			],
			// Check user properties, theme.
			[
				'user' => [
					'username' => 'User with empty theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => ''
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of "default", "blue-theme", "dark-theme", "hc-light", "hc-dark".'
			],
			[
				'user' => [
					'username' => 'User with invalid theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => 'classic'
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of "default", "blue-theme", "dark-theme", "hc-light", "hc-dark".'
			],
			[
				'user' => [
					'username' => 'User with invalid theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => 'originalblue'
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of "default", "blue-theme", "dark-theme", "hc-light", "hc-dark".'
			],
			// Check user properties, type.
			[
				'user' => [
					'username' => 'User with empty roleid',
					'roleid' => '',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					]
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid roleid',
					'roleid' => 0,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					]
				],
				'expected_error' => 'User role with ID "0" is not available.'
			],
			[
				'user' => [
					'username' => 'User with invalid roleid',
					'roleid' => '1.1',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					]
				],
				'expected_error' => 'Invalid parameter "/1/roleid": a number is expected.'
			],
			// Check user properties, refresh.
			[
				'user' => [
					'username' => 'User with empty refresh',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'refresh' => ''
				],
				'expected_error' => 'Invalid parameter "/1/refresh": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with invalid refresh',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'refresh' => '3601'
				],
				'expected_error' => 'Invalid parameter "/1/refresh": value must be one of 0-3600.'
			],
			[
				'user' => [
					'username' => 'User with invalid refresh',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'refresh' => '1.1'
				],
				'expected_error' => 'Invalid parameter "/1/refresh": a time unit is expected.'
			],
			// Check user properties, rows_per_page.
			[
				'user' => [
					'username' => 'User with empty rows_per_page',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'rows_per_page' => ''
				],
				'expected_error' => 'Invalid parameter "/1/rows_per_page": an integer is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid rows_per_page',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'rows_per_page' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
			],
			[
				'user' => [
					'username' => 'User with invalid rows_per_page',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'rows_per_page' => '1000000'
				],
				'expected_error' => 'Invalid parameter "/1/rows_per_page": value must be one of 1-999999.'
			],
			// Check user media, mediatypeid.
			[
				'user' => [
					'username' => 'User without medias properties',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [[ ]]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1": the parameter "mediatypeid" is missing.'
			],
			[
				'user' => [
					'username' => 'User with empty mediatypeid',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/mediatypeid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid mediatypeid',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1.1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/mediatypeid": a number is expected.'
			],
			[
				'user' => [
					'username' => 'User with nonexistent media type id',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1234',
							'sendto' => 'api@zabbix.com'
						]
					]
				],
				'expected_error' => 'Media type with ID "1234" is not available.'
			],
			// Check user media, sendto.
			[
				'user' => [
					'username' => 'User without sendto',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1": the parameter "sendto" is missing.'
			],
			[
				'user' => [
					'username' => 'User with empty sendto',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with empty sendto',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => [[]]
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/sendto/1": a character string is expected.'
			],
			[
				'user' => [
					'username' => 'User with empty sendto',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => []
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/sendto": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with empty sendto',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => [""]
						]
					]
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with empty second email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com",""]
						]
					]
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1zabbix.com"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbixcom"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@@zabbix.com"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1 test2@zabbix.com"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["<test1@zabbix.com> test2"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com, a,b"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'username' => 'User with invalid email',
					'roleid' => 1,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com,test2@zabbix.com"]
						]
					]
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			// Check user media, active.
			[
				'user' => [
					'username' => 'User with empty active',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => 'api@zabbix.com',
							'active' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/active": an integer is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid active',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'active' => '1.1'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/active": an integer is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid active',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'active' => '2'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/active": value must be one of 0, 1.'
			],
			// Check user media, severity.
			[
				'user' => [
					'username' => 'User with empty severity',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'severity' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/severity": an integer is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid severity',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'severity' => '64'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/severity": value must be one of 0-63.'
			],
			// Check user media, period.
			[
				'user' => [
					'username' => 'User with empty period',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => ''
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": cannot be empty.'
			],
			[
				'user' => [
					'username' => 'User with string period',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => 'test'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid period, without comma',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7 00:00-24:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid period, with two comma',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-5,09:00-18:00,6-7,10:00-16:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid period, 8 week days',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-8,00:00-24:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid period, zero week day',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '0-7,00:00-24:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid time',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,24:00-00:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid time',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,14:00-13:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid time',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,25:00-26:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'username' => 'User with invalid time',
					'roleid' => 1,
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,13:60-14:00'
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/medias/1/period": a time period is expected.'
			],
			// Successfully user update and create with all parameters.
			[
				'user' => [
					'username' => 'all-parameters',
					'roleid' => 3,
					'passwd' => 'Z@bb1x1234',
					'usrgrps' => [['usrgrpid' => 7]],
					'medias' => [
							[
								'mediatypeid' => '1',
								'sendto' => 'apicreate@zabbix.com',
								'active' => '1',
								'severity' => '60',
								'period' => '1-5,09:00-18:00;5-7,12:00-16:00'
							]
					],
					'name' => 'User with all parameters',
					'surname' => 'User Surname',
					'autologin' => 1,
					'autologout' => 0,
					'lang' => 'en_US',
					'refresh' => 90,
					'theme' => 'dark-theme',
					'rows_per_page' => 25,
					'url' => 'zabbix.php?action=userprofile.edit'
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider user_properties
	*/
	public function testUser_NotRequiredPropertiesAndMedias($user, $expected_error) {
		$methods = ['user.create', 'user.update'];

		foreach ($methods as $method) {
			if ($method == 'user.update') {
				$user['userid'] = '9';
				$user['username'] = 'updated-'.$user['username'];
			}
			$result = $this->call($method, $user, $expected_error);

			if ($expected_error === null) {
				$dbResultUser = DBSelect('select * from users where userid='.zbx_dbstr($result['result']['userids'][0]));
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['username'], $user['username']);
				$this->assertEquals($dbRowUser['name'], $user['name']);
				$this->assertEquals($dbRowUser['surname'], $user['surname']);
				$this->assertEquals($dbRowUser['autologin'], $user['autologin']);
				$this->assertEquals($dbRowUser['autologout'], $user['autologout']);
				$this->assertEquals($dbRowUser['lang'], $user['lang']);
				$this->assertEquals($dbRowUser['refresh'], $user['refresh']);
				$this->assertEquals($dbRowUser['rows_per_page'], $user['rows_per_page']);
				$this->assertEquals($dbRowUser['theme'], $user['theme']);
				$this->assertEquals($dbRowUser['url'], $user['url']);

				$this->assertEquals(1, CDBHelper::getCount('select * from users_groups where userid='.
						zbx_dbstr($result['result']['userids'][0]).' and usrgrpid='.
						zbx_dbstr($user['usrgrps'][0]['usrgrpid']))
				);

				$dbResultMedia = DBSelect('select * from media where userid='.
						zbx_dbstr($result['result']['userids'][0])
				);

				$dbRowMedia = DBFetch($dbResultMedia);
				$this->assertEquals($dbRowMedia['mediatypeid'], $user['medias'][0]['mediatypeid']);
				$this->assertEquals($dbRowMedia['sendto'], $user['medias'][0]['sendto']);
				$this->assertEquals($dbRowMedia['active'], $user['medias'][0]['active']);
				$this->assertEquals($dbRowMedia['severity'], $user['medias'][0]['severity']);
				$this->assertEquals($dbRowMedia['period'], $user['medias'][0]['period']);
			}
			else {
				$this->assertEquals(0, CDBHelper::getCount('select * from users where username='.
					zbx_dbstr($user['username'])
				));
			}
		}
	}

	public static function user_delete() {
		return [
			// Check user id.
			[
				'user' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => ['abc'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => ['1.1'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => ['123456'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => ['9', '9'],
				'expected_error' => 'Invalid parameter "/2": value (9) already exists.'
			],
			// Try delete himself.
			[
				'user' => ['1'],
				'expected_error' => 'User is not allowed to delete himself.'
			],
			// Try delete internal user.
			[
				'user' => ['2'],
				'expected_error' => 'Cannot delete Zabbix internal user "guest", try disabling that user.'
			],
			// Check if deleted users used in actions.
			[
				'user' => ['13'],
				'expected_error' => 'User "api-user-action" is used in "API action with user" action.'
			],
			// Check if deleted users have a map.
			[
				'user' => ['14'],
				'expected_error' => 'User "api-user-map" is map "API map" owner.'
			],
			// Check successfully delete of user.
			[
				'user' => ['10'],
				'expected_error' => null
			],
			[
				'user' => ['11', '12'],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider user_delete
	*/
	public function testUsers_Delete($user, $expected_error) {
		$result = $this->call('user.delete', $user, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['userids'] as $id) {
				$this->assertEquals(0, CDBHelper::getCount('select * from users where userid='.zbx_dbstr($id)));
			}
		}
	}

	public static function user_unblock_data_invalid(): array {
		return [
			[
				'user' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			[
				'user' => [null],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => [true],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => [[]],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => ['1.0'],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => [-1],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			[
				'user' => ['123456'], // Non-existing user.
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => ['15', '15'],
				'expected_error' => 'Invalid parameter "/2": value (15) already exists.'
			]
		];
	}

	public static function user_unblock_data_valid(): array {
		return [
			[
				'user' => ['15'],
				'expected_error' => null
			],
			[
				'user' => ['14', '15'],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider user_unblock_data_invalid
	 * @dataProvider user_unblock_data_valid
	 */
	public function testUsers_Unblock($users, ?string $expected_error): void {
		$response = $this->call('user.unblock', $users, $expected_error);

		if ($expected_error !== null) {
			return;
		}

		$db_users = CDBHelper::getAll(
			'SELECT u.attempt_failed'.
			' FROM users u'.
			' WHERE '.dbConditionId('u.userid', $response['result']['userids']).
			' ORDER BY u.userid ASC'
		);

		foreach ($db_users as $db_user) {
			$this->assertEquals(0, $db_user['attempt_failed']);
		}
	}

	public static function user_permissions() {
		return [
			[
				'method' => 'user.create',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => [
					'username' => 'API user create as zabbix admin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'No permissions to call "user.create".'
			],
			[
				'method' => 'user.update',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => [
					'userid' => '9',
					'username' => 'API user update as zabbix admin without permissions'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'user.delete',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => ['9'],
				'expected_error' => 'No permissions to call "user.delete".'
			],
			[
				'method' => 'user.create',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => [
					'username' => 'API user create as zabbix user',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'No permissions to call "user.create".'
			],
			[
				'method' => 'user.update',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => [
					'userid' => '9',
					'username' => 'API user update as zabbix user without permissions'
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'user.delete',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => ['9'],
				'expected_error' => 'No permissions to call "user.delete".'
			],
			[
				'method' => 'user.unblock',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => ['15'],
				'expected_error' => 'No permissions to call "user.unblock".'
			],
			[
				'method' => 'user.unblock',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => ['15'],
				'expected_error' => 'No permissions to call "user.unblock".'
			]
		];
	}

	/**
	* @dataProvider user_permissions
	*/
	public function testUsers_UserPermissions($method, $login, $user, $expected_error) {
		$this->authorize($login['user'], $login['password']);
		$this->call($method, $user, $expected_error);
	}

	public static function auth_data() {
		return [
			[[
				'jsonrpc' => '2.0',
				'method' => 'user.update',
				'params' =>
					[
						'userid' => '9',
						'username' => 'check authentication'
					],
				'auth' => '12345',
				'id' => '1'
			]],
			[[
				'jsonrpc' => '2.0',
				'method' => 'user.logout',
				'params' => [],
				'auth' => '12345',
				'id' => '1'
			]]
		];
	}

	/**
	* @dataProvider auth_data
	*/
	public function testUsers_Session($data) {
		$this->checkResult($this->callRaw($data), 'Session terminated, re-login, please.');
	}

	public function testUsers_Logout() {
		$this->authorize('Admin', 'zabbix');

		$logout = [
			'jsonrpc' => '2.0',
			'method' => 'user.logout',
			'params' => [],
			'auth' => CAPIHelper::getSessionId(),
			'id' => '1'
		];
		$this->checkResult($this->callRaw($logout));

		$data = [
			'jsonrpc' => '2.0',
			'method' => 'user.update',
			'params' =>
				[
					'userid' => '9',
					'username' => 'check authentication'
				],
			'auth' => CAPIHelper::getSessionId(),
			'id' => '1'
		];
		$this->checkResult($this->callRaw($data), 'Session terminated, re-login, please.');
	}

	public static function login_data() {
		return [
			[
				'login' => [
					'username' => 'Admin',
					'password' => 'zabbix',
					'sessionid' => '123456'
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "sessionid".'
			],
			// Check login
			[
				'login' => [
					'password' => 'zabbix'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "username" is missing.'
			],
			[
				'login' => [
					'username' => '',
					'password' => 'zabbix'
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			[
				'login' => [
					'username' => 'Unknown user',
					'password' => 'zabbix'
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			[
				'login' => [
					'username' => '!@#$%^&\\\'\"""\;:',
					'password' => 'zabbix'
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			// Check password
			[
				'login' => [
					'username' => 'Admin'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "password" is missing.'
			],
			[
				'login' => [
					'username' => 'Admin',
					'password' => ''
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			[
				'login' => [
					'username' => 'Admin',
					'password' => 'wrong password'
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			[
				'login' => [
					'username' => 'Admin',
					'password' => '!@#$%^&\\\'\"""\;:'
				],
				'expected_error' => 'Incorrect user name or password or account is temporarily blocked.'
			],
			// Check disabled user.
			[
				'login' => [
					'username' => 'api-user-action',
					'password' => 'zabbix'
				],
				'expected_error' => 'No permissions for system access.'
			],
			// Successfully login.
			[
				'login' => [
					'username' => 'Admin',
					'password' => 'zabbix'
				],
				'expected_error' => null
			],
			[
				'login' => [
					'username' => 'Admin',
					'password' => 'zabbix',
					'userData' => true
				],
				'expected_error' => null
			],
			[
				'login' => [
					'username' => 'guest',
					'password' => ''
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 *
	 * @dataProvider login_data
	 */
	public function testUsers_Login($user, $expected_error) {
		$this->disableAuthorization();
		$this->call('user.login', $user, $expected_error);
	}

	public function testUsers_AuthTokenIncorrect() {
		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => bin2hex(random_bytes(32)),
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('error', $res));

		['error' => ['data' => $error]] = $res;
		$this->assertEquals($error, 'Not authorized.');
	}

	public function testUsers_AuthTokenDisabled() {
		$token = bin2hex(random_bytes(32));
		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_DISABLED,
			'userid' => 1,
			'name' => 'disabled',
			'token' => hash('sha512', $token)
		]]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('error', $res));

		['error' => ['data' => $error]] = $res;
		$this->assertEquals($error, 'Not authorized.');
	}

	public function testUsers_AuthTokenExpired() {
		$now = time();
		$token = bin2hex(random_bytes(32));
		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 1,
			'name' => 'expired',
			'expires_at' => $now - 1,
			'token' => hash('sha512', $token)
		]]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('error', $res));

		['error' => ['data' => $error]] = $res;
		$this->assertEquals($error, 'API token expired.');
	}

	public function testUsers_AuthTokenNotExpired() {
		$now = time();
		$token = bin2hex(random_bytes(32));

		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 1,
			'name' => 'correct',
			'expires_at'  => $now + 10,
			'token' => hash('sha512', $token)
		]]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('result', $res));
	}

	public function testUsers_AuthTokenDebugModeEnabled() {
		$token = bin2hex(random_bytes(32));

		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 1,
			'name' => 'debug mode',
			'token' => hash('sha512', $token)
		]]);

		DB::update('usrgrp', [
			'values' => ['debug_mode' => GROUP_DEBUG_MODE_ENABLED],
			'where' => ['usrgrpid' => 7]
		]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'inheritedTags' => 'incorrect value',
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		DB::update('usrgrp', [
			'values' => ['debug_mode' => GROUP_DEBUG_MODE_DISABLED],
			'where' => ['usrgrpid' => 7]
		]);

		$this->assertTrue(array_key_exists('error', $res), 'Expected error to occur.');
		$this->assertTrue(array_key_exists('debug', $res['error']), 'Expected debug trace in error.');
	}

	public function testUsers_AuthTokenDebugModeDisabled() {
		$token = bin2hex(random_bytes(32));

		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 1,
			'name' => 'debug mode disabled',
			'token' => hash('sha512', $token)
		]]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'inheritedTags' => 'incorrect value',
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('error', $res), 'Expected error to occur.');
		$this->assertTrue(!array_key_exists('debug', $res['error']), 'Not expected debug trace in error.');
	}

	public function testUsers_AuthTokenLastaccessIsUpdated() {
		$token = bin2hex(random_bytes(32));
		$formeraccess = time() - 1;

		$tokenids = DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 1,
			'lastaccess' => $formeraccess,
			'name' => 'lastaccess updated',
			'token' => hash('sha512', $token)
		]]);

		$this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		[['lastaccess' => $lastaccess]] = DB::select('token', [
			'output' => ['lastaccess'],
			'tokenids' => $tokenids
		]);

		$this->assertTrue($lastaccess > $formeraccess);
	}

	public function testUsers_AuthTokenUserDisabled() {
		$token = bin2hex(random_bytes(32));

		DB::insert('token', [[
			'status' => ZBX_AUTH_TOKEN_ENABLED,
			'userid' => 13,
			'name' => 'user with status "Disabled"',
			'token' => hash('sha512', $token)
		]]);

		$res = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'host.get',
			'params' => [
				'output' => [],
				'limit' => 1
			],
			'auth' => $token,
			'id' => '1'
		]);

		$this->assertTrue(array_key_exists('error', $res), 'Expected error to occur.');
		$this->assertEquals($res['error']['data'], 'Not authorized.');
	}

	public function testUsers_LoginBlocked() {
		$this->disableAuthorization();
		for ($i = 1; $i <= 6; $i++) {
			$result = $this->call('user.login', ['username' => 'Admin', 'password' => 'attempt '.$i], true);
		}

		$this->assertEquals('Incorrect user name or password or account is temporarily blocked.',
			$result['error']['data']
		);
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}
