<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

/**
 * @backup users
 */
class testUsers extends CZabbixTest {

	public static function user_create() {
		return [
			// Check user password.
			[
				'user' => [
					'alias' => 'API user create without password'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "passwd" is missing.'
			],
			// Check user alias.
			[
				'user' => [
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "alias" is missing.'
			],
			[
				'user' => [
					'alias' => '',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'Invalid parameter "/1/alias": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'Admin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 7]
					]
				],
				'expected_error' => 'User with alias "Admin" already exists.'
			],
			[
				'user' => [
					[
						'alias' => 'API create users with the same names',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					],
					[
						'alias' => 'API create users with the same names',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						]
					],
				],
				'expected_error' => 'Invalid parameter "/2": value (alias)=(API create users with the same names) already exists.'
			],
			[
				'user' => [
					'alias' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7
					]
				],
				'expected_error' => 'Invalid parameter "/1/alias": value is too long.'
			],
			// Check user group.
			[
				'user' => [
					'alias' => 'User without group parameter',
					'passwd' => 'zabbix',
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "usrgrps" is missing.'
			],
			[
				'user' => [
					'alias' => 'User without group',
					'passwd' => 'zabbix',
					'usrgrps' => [
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'Group unexpected parameter',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['userid' => '1']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1": unexpected parameter "userid".'
			],
			[
				'user' => [
					'alias' => 'User with empty group id',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User group id not number',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => 'abc']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User group id not valid',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '1.1']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with nonexistent group id',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '123456']
					]
				],
				'expected_error' => 'User group with ID "123456" is not available.'
			],
			[
				'user' => [
					'alias' => 'User with two identical user group id',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7'],
						['usrgrpid' => '7']
					]
				],
				'expected_error' => 'Invalid parameter "/1/usrgrps/2": value (usrgrpid)=(7) already exists.'
			],
			// Check successfully creation of user.
			[
				'user' => [
					[
						'alias' => 'API user create 1',
						'passwd' => 'zabbix',
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
						'alias' => '☺',
						'passwd' => '☺',
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
						'alias' => 'УТФ Юзер',
						'passwd' => 'zabbix',
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
						'alias' => 'API user create with media',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						],
						'user_medias' => [
							[
								'mediatypeid' => '1',
								'sendto' => 'api@zabbix.com',
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
				$this->assertEquals($dbRowUser['alias'], $user[$key]['alias']);
				$this->assertEquals($dbRowUser['passwd'], md5($user[$key]['passwd']));
				$this->assertEquals($dbRowUser['name'], '');
				$this->assertEquals($dbRowUser['surname'], '');
				$this->assertEquals($dbRowUser['autologin'], 0);
				$this->assertEquals($dbRowUser['autologout'], '15m');
				$this->assertEquals($dbRowUser['lang'], 'en_GB');
				$this->assertEquals($dbRowUser['refresh'], '30s');
				$this->assertEquals($dbRowUser['rows_per_page'], 50);
				$this->assertEquals($dbRowUser['theme'], 'default');
				$this->assertEquals($dbRowUser['url'], '');

				$this->assertEquals(1, DBcount('select * from users_groups where userid='.zbx_dbstr($id).
						' and usrgrpid='.zbx_dbstr($user[$key]['usrgrps'][0]['usrgrpid']))
				);

				if (array_key_exists('user_medias', $user[$key])) {
					$dbResultMedia = DBSelect('select * from media where userid='.$id);
					$dbRowMedia = DBFetch($dbResultMedia);
					$this->assertEquals($dbRowMedia['mediatypeid'], $user[$key]['user_medias'][0]['mediatypeid']);
					$this->assertEquals($dbRowMedia['sendto'], $user[$key]['user_medias'][0]['sendto']);
					$this->assertEquals($dbRowMedia['active'], 0);
					$this->assertEquals($dbRowMedia['severity'], 63);
					$this->assertEquals($dbRowMedia['period'], '1-7,00:00-24:00');
				}
				else {
					$dbResultGroup = 'select * from media where userid='.$id;
					$this->assertEquals(0, DBcount($dbResultGroup));
				}
			}
		}
	}

	/**
	* Create user with multiple email address
	*/
	public function testUsers_CreateUserWithMultipleEmails() {
		$user = [
			'alias' => 'API user create with multiple emails',
			'passwd' => 'zabbix',
			'usrgrps' => [
				['usrgrpid' => 7]
			],
			'user_medias' => [
				[
					'mediatypeid' => '1',
					'sendto' => ["api1@zabbix.com","Api test <api2@zabbix.com>","АПИ test ☺æų <api2@zabbix.com>"],
				]
			]
		];

		$result = $this->call('user.create', $user);
		$id = $result['result']['userids'][0];
		$this->assertEquals(1, DBcount('select * from users where userid='.zbx_dbstr($id)));

		$dbResultMedia = DBSelect('select * from media where userid='.zbx_dbstr($id));
		$dbRowMedia = DBFetch($dbResultMedia);
		$diff = array_diff($user['user_medias'][0]['sendto'], explode("\n", $dbRowMedia['sendto']));
		$this->assertEquals(0, count($diff));
	}

	public static function user_update() {
		return [
			// Check user id.
			[
				'user' => [[
					'alias' => 'API user update without userid'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "userid" is missing.'
			],
			[
				'user' => [[
					'alias' => 'API user update with empty userid',
					'userid' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'alias' => 'API user update with nonexistent userid',
					'userid' => '1.1'
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'alias' => 'API user update with nonexistent userid',
					'userid' => 'abc'
				]],
				'expected_error' => 'Invalid parameter "/1/userid": a number is expected.'
			],
			[
				'user' => [[
					'alias' => 'API user update with nonexistent userid',
					'userid' => '123456'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'user' => [
					[
						'userid' => '9',
						'alias' => 'API update users with the same id1'
					],
					[
						'userid' => '9',
						'alias' => 'API update users with the same id2'
					],
				],
				'expected_error' => 'Invalid parameter "/2": value (userid)=(9) already exists.'
			],
			// Check user password.
			[
				'user' => [[
					'userid' => '2',
					'passwd' => 'zabbix'
				]],
				'expected_error' => 'Not allowed to set password for user "guest".'
			],
			// Check user alias.
			[
				'user' => [[
					'userid' => '9',
					'alias' => ''
				]],
				'expected_error' => 'Invalid parameter "/1/alias": cannot be empty.'
			],
			[
				'user' => [[
					'userid' => '2',
					'alias' => 'Try rename guest'
				]],
				'expected_error' => 'Cannot rename guest user.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'Admin'
				]],
				'expected_error' => 'User with alias "Admin" already exists.'
			],
			[
				'user' => [
					[
						'userid' => '9',
						'alias' => 'API update users with the same alias'
					],
					[
						'userid' => '10',
						'alias' => 'API update users with the same alias'
					],
				],
				'expected_error' => 'Invalid parameter "/2": value (alias)=(API update users with the same alias) already exists.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm'
				]],
				'expected_error' => 'Invalid parameter "/1/alias": value is too long.'
			],
			// Check user group.
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User without group',
					'usrgrps' => [
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps": cannot be empty.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'Group unexpected parameter',
					'usrgrps' => [
						['userid' => '1']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1": unexpected parameter "userid".'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User with empty group id',
					'usrgrps' => [
						['usrgrpid' => '']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User group id not number',
					'usrgrps' => [
						['usrgrpid' => 'abc']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User group id not valid',
					'usrgrps' => [
						['usrgrpid' => '1.1']
					]
				]],
				'expected_error' => 'Invalid parameter "/1/usrgrps/1/usrgrpid": a number is expected.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User with nonexistent group id',
					'usrgrps' => [
						['usrgrpid' => '123456']
					]
				]],
				'expected_error' => 'User group with ID "123456" is not available.'
			],
			[
				'user' => [[
					'userid' => '9',
					'alias' => 'User with two identical user group id',
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
					'alias' => 'Try add user to group with disabled GUI access',
					'usrgrps' => [
						['usrgrpid' => '12']
					]
				]],
				'expected_error' => 'User cannot add himself to a disabled group or a group with disabled GUI access.'
			],
			[
				'user' => [[
					'userid' => '1',
					'alias' => 'Try add user to a disabled group',
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
					'alias' => 'Try to change super-admin user type',
					'type' => '2'
				]],
				'expected_error' => 'User cannot change their user type.'
			],
			// Successfully user update.
			[
				'user' => [
					[
						'userid' => '9',
						'alias' => 'API user updated',
						'passwd' => 'zabbix1',
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
						'alias' => 'УТФ Юзер обновлённ',
						'passwd' => 'zabbix',
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
						'alias' => 'API user update with media',
						'passwd' => 'zabbix',
						'usrgrps' => [
							['usrgrpid' => 7]
						],
						'user_medias' => [
							[
								'mediatypeid' => '1',
								'sendto' => 'api@zabbix.com',
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
				$oldHashUser = DBhash($sqlUser);
			}
		}

		$result = $this->call('user.update', $users, $expected_error);

		if ($expected_error === null) {
			foreach ($result['result']['userids'] as $key => $id) {
				$dbResultUser = DBSelect('select * from users where userid='.zbx_dbstr($id));
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['alias'], $users[$key]['alias']);
				$this->assertEquals($dbRowUser['passwd'], md5($users[$key]['passwd']));
				$this->assertEquals($dbRowUser['name'], '');
				$this->assertEquals($dbRowUser['surname'], '');
				$this->assertEquals($dbRowUser['autologin'], 0);
				$this->assertEquals($dbRowUser['autologout'], '15m');
				$this->assertEquals($dbRowUser['lang'], 'en_GB');
				$this->assertEquals($dbRowUser['refresh'], '30s');
				$this->assertEquals($dbRowUser['rows_per_page'], 50);
				$this->assertEquals($dbRowUser['theme'], 'default');
				$this->assertEquals($dbRowUser['url'], '');

				$this->assertEquals(1, DBcount('select * from users_groups where userid='.zbx_dbstr($id).
						' and usrgrpid='.zbx_dbstr($users[$key]['usrgrps'][0]['usrgrpid']))
				);

				if (array_key_exists('user_medias', $users[$key])) {
					$dbResultMedia = DBSelect('select * from media where userid='.zbx_dbstr($id));
					$dbRowMedia = DBFetch($dbResultMedia);
					$this->assertEquals($dbRowMedia['mediatypeid'], $users[$key]['user_medias'][0]['mediatypeid']);
					$this->assertEquals($dbRowMedia['sendto'], $users[$key]['user_medias'][0]['sendto']);
					$this->assertEquals($dbRowMedia['active'], 0);
					$this->assertEquals($dbRowMedia['severity'], 63);
					$this->assertEquals($dbRowMedia['period'], '1-7,00:00-24:00');
				}
				else {
					$dbResultGroup = 'select * from media where userid='.zbx_dbstr($id);
					$this->assertEquals(0, DBcount($dbResultGroup));
				}
			}
		}
		else {
			if (isset($oldHashUser)) {
				$this->assertEquals($oldHashUser, DBhash($sqlUser));
			}
		}
	}

	public static function user_properties() {
		return [
			// Check readonly parameter.
			[
				'user' => [
					'alias' => 'Unexpected parameter attempt_clock',
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
					'alias' => 'Unexpected parameter attempt_failed',
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
					'alias' => 'Unexpected parameter attempt_ip',
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
					'alias' => 'User with long name',
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
					'alias' => 'User with long surname',
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
					'alias' => 'User with invalid autologin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'autologin' => ''
				],
				'expected_error' => 'Invalid parameter "/1/autologin": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid autologin',
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
					'alias' => 'User with invalid autologin',
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
					'alias' => 'User with invalid autologout',
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
					'alias' => 'User with invalid autologout',
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
					'alias' => 'User with invalid autologout',
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
					'alias' => 'User with invalid autologout',
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
					'alias' => 'User with autologout and autologin together',
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
					'alias' => 'User with empty lang',
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
					'alias' => 'User with invalid lang',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'lang' => '123456'
				],
				'expected_error' => 'Invalid parameter "/1/lang": value is too long.'
			],
			// Check user properties, theme.
			[
				'user' => [
					'alias' => 'User with empty theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => ''
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of default, blue-theme, dark-theme, hc-light, hc-dark.'
			],
			[
				'user' => [
					'alias' => 'User with invalid theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => 'classic'
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of default, blue-theme, dark-theme, hc-light, hc-dark.'
			],
			[
				'user' => [
					'alias' => 'User with invalid theme',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'theme' => 'originalblue'
				],
				'expected_error' => 'Invalid parameter "/1/theme": value must be one of default, blue-theme, dark-theme, hc-light, hc-dark.'
			],
			// Check user properties, type.
			[
				'user' => [
					'alias' => 'User with empty type',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'type' => ''
				],
				'expected_error' => 'Invalid parameter "/1/type": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid type',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'type' => '0'
				],
				'expected_error' => 'Invalid parameter "/1/type": value must be one of 1, 2, 3.'
			],
			[
				'user' => [
					'alias' => 'User with invalid type',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'type' => '1.1'
				],
				'expected_error' => 'Invalid parameter "/1/type": a number is expected.'
			],
			// Check user properties, refresh.
			[
				'user' => [
					'alias' => 'User with empty refresh',
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
					'alias' => 'User with invalid refresh',
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
					'alias' => 'User with invalid refresh',
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
					'alias' => 'User with empty rows_per_page',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'rows_per_page' => ''
				],
				'expected_error' => 'Invalid parameter "/1/rows_per_page": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid rows_per_page',
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
					'alias' => 'User with invalid rows_per_page',
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
					'alias' => 'User without user_medias properties',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [[ ]],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1": the parameter "mediatypeid" is missing.'
			],
			[
				'user' => [
					'alias' => 'User with empty mediatypeid',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => ''
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/mediatypeid": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid mediatypeid',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1.1'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/mediatypeid": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with nonexistent media type id',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '10',
							'sendto' => 'api@zabbix.com'
						]
					],
				],
				'expected_error' => 'Media type with ID "10" is not available.'
			],
			// Check user media, sendto.
			[
				'user' => [
					'alias' => 'User without sendto',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1": the parameter "sendto" is missing.'
			],
			[
				'user' => [
					'alias' => 'User with empty sendto',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ''
						]
					],
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'User with empty sendto',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => [[]]
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/sendto/1": a character string is expected.'
			],
			[
				'user' => [
					'alias' => 'User with empty sendto',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => []
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/sendto": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'User with empty sendto',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => [""]
						]
					],
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'User with empty second email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com",""]
						]
					],
				],
				'expected_error' => 'Invalid parameter "sendto": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1zabbix.com"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbixcom"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@@zabbix.com"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1 test2@zabbix.com"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["<test1@zabbix.com> test2"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com, a,b"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			[
				'user' => [
					'alias' => 'User with invalid email',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => ["test1@zabbix.com,test2@zabbix.com"]
						]
					],
				],
				'expected_error' => 'Invalid email address for media type with ID "1".'
			],
			// Check user media, active.
			[
				'user' => [
					'alias' => 'User with empty active',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
							'mediatypeid' => '1',
							'sendto' => 'api@zabbix.com',
							'active' => ''
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/active": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid active',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'active' => '1.1'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/active": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid active',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'active' => '2'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/active": value must be one of 0, 1.'
			],
			// Check user media, severity.
			[
				'user' => [
					'alias' => 'User with empty severity',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'severity' => ''
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/severity": a number is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid severity',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'severity' => '64'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/severity": value must be one of 0-63.'
			],
			// Check user media, period.
			[
				'user' => [
					'alias' => 'User with empty period',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => ''
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": cannot be empty.'
			],
			[
				'user' => [
					'alias' => 'User with string period',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => 'test'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid period, without comma',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7 00:00-24:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid period, with two comma',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-5,09:00-18:00,6-7,10:00-16:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid period, 8 week days',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-8,00:00-24:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid period, zero week day',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '0-7,00:00-24:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid time',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,24:00-00:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid time',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,14:00-13:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid time',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,25:00-26:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			[
				'user' => [
					'alias' => 'User with invalid time',
					'passwd' => 'zabbix',
					'usrgrps' => [
						['usrgrpid' => '7']
					],
					'user_medias' => [
						[
						'mediatypeid' => '1',
						'sendto' => 'api@zabbix.com',
						'period' => '1-7,13:60-14:00'
						]
					],
				],
				'expected_error' => 'Invalid parameter "/1/user_medias/1/period": a time period is expected.'
			],
			// Successfully user update and create with all parameters.
			[
				'user' => [
					'alias' => 'all-parameters',
					'passwd' => 'zabbix',
					'usrgrps' => [['usrgrpid' => 7]],
					'user_medias' => [
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
					'type' => 3,
					'theme' => 'dark-theme',
					'rows_per_page' => 25,
					'url' => 'profile.php'
				],
				'expected_error' => null
			],
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
				$user['alias'] = 'updated-'.$user['alias'];
			}
			$result = $this->call($method, $user, $expected_error);

			if ($expected_error === null) {
				$dbResultUser = DBSelect('select * from users where userid='.zbx_dbstr($result['result']['userids'][0]));
				$dbRowUser = DBFetch($dbResultUser);
				$this->assertEquals($dbRowUser['alias'], $user['alias']);
				$this->assertEquals($dbRowUser['passwd'], md5($user['passwd']));
				$this->assertEquals($dbRowUser['name'], $user['name']);
				$this->assertEquals($dbRowUser['surname'], $user['surname']);
				$this->assertEquals($dbRowUser['autologin'], $user['autologin']);
				$this->assertEquals($dbRowUser['autologout'], $user['autologout']);
				$this->assertEquals($dbRowUser['lang'], $user['lang']);
				$this->assertEquals($dbRowUser['refresh'], $user['refresh']);
				$this->assertEquals($dbRowUser['rows_per_page'], $user['rows_per_page']);
				$this->assertEquals($dbRowUser['theme'], $user['theme']);
				$this->assertEquals($dbRowUser['url'], $user['url']);

				$this->assertEquals(1, DBcount('select * from users_groups where userid='.
						zbx_dbstr($result['result']['userids'][0]).' and usrgrpid='.
						zbx_dbstr($user['usrgrps'][0]['usrgrpid']))
				);

				$dbResultMedia = DBSelect('select * from media where userid='.
						zbx_dbstr($result['result']['userids'][0])
				);

				$dbRowMedia = DBFetch($dbResultMedia);
				$this->assertEquals($dbRowMedia['mediatypeid'], $user['user_medias'][0]['mediatypeid']);
				$this->assertEquals($dbRowMedia['sendto'], $user['user_medias'][0]['sendto']);
				$this->assertEquals($dbRowMedia['active'], $user['user_medias'][0]['active']);
				$this->assertEquals($dbRowMedia['severity'], $user['user_medias'][0]['severity']);
				$this->assertEquals($dbRowMedia['period'], $user['user_medias'][0]['period']);
			}
			else {
				$this->assertEquals(0, DBcount('select * from users where alias='.zbx_dbstr($user['alias'])));
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
			// Check if deleted users have a screen.
			[
				'user' => ['15'],
				'expected_error' => 'User "api-user-screen" is screen "API screen" owner.'
			],
			// Check if deleted users have a slide show.
			[
				'user' => ['16'],
				'expected_error' => 'User "api-user-slideshow" is slide show "API slide show" owner.'
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
				$this->assertEquals(0, DBcount('select * from users where userid='.zbx_dbstr($id)));
			}
		}
	}

	public static function user_permissions() {
		return [
			[
				'method' => 'user.create',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => [
							'alias' => 'API user create as zabbix admin',
							'passwd' => 'zabbix',
							'usrgrps' => [
								['usrgrpid' => 7]
							]
						],
				'expected_error' => 'You do not have permissions to create users.'
			],
			[
				'method' => 'user.update',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => [
							'userid' => '9',
							'alias' => 'API user update as zabbix admin without permissions',
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'user.delete',
				'login' => ['user' => 'zabbix-admin', 'password' => 'zabbix'],
				'user' => ['9'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'user.create',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => [
							'alias' => 'API user create as zabbix user',
							'passwd' => 'zabbix',
							'usrgrps' => [
								['usrgrpid' => 7]
							]
						],
				'expected_error' => 'You do not have permissions to create users.'
			],
			[
				'method' => 'user.update',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => [
							'userid' => '9',
							'alias' => 'API user update as zabbix user without permissions',
						],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			[
				'method' => 'user.delete',
				'login' => ['user' => 'zabbix-user', 'password' => 'zabbix'],
				'user' => ['9'],
				'expected_error' => 'No permissions to referred object or it does not exist!'
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
						'alias' => 'check authentication',
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
			]],
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
			'auth' => $this->session,
			'id' => '1'
		];
		$this->checkResult($this->callRaw($logout));

		$data = [
			'jsonrpc' => '2.0',
			'method' => 'user.update',
			'params' =>
				[
					'userid' => '9',
					'alias' => 'check authentication',
				],
			'auth' => $this->session,
			'id' => '1'
		];
		$this->checkResult($this->callRaw($data), 'Session terminated, re-login, please.');
	}

	public static function login_data() {
		return [
			[
				'login' => [
					'user' => 'Admin',
					'password' => 'zabbix',
					'sessionid' => '123456',
				],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "sessionid".'
			],
			// Check login
			[
				'login' => [
					'password' => 'zabbix'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "user" is missing.'
			],
			[
				'login' => [
					'user' => '',
					'password' => 'zabbix'
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			[
				'login' => [
					'user' => 'Unknown user',
					'password' => 'zabbix'
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			[
				'login' => [
					'user' => '!@#$%^&\\\'\"""\;:',
					'password' => 'zabbix'
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			// Check password
			[
				'login' => [
					'user' => 'Admin'
				],
				'expected_error' => 'Invalid parameter "/": the parameter "password" is missing.'
			],
			[
				'login' => [
					'user' => 'Admin',
					'password' => ''
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			[
				'login' => [
					'user' => 'Admin',
					'password' => 'wrong password'
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			[
				'login' => [
					'user' => 'Admin',
					'password' => '!@#$%^&\\\'\"""\;:'
				],
				'expected_error' => 'Login name or password is incorrect.'
			],
			// Check disabled user.
			[
				'login' => [
					'user' => 'api-user-action',
					'password' => 'zabbix'
				],
				'expected_error' => 'No permissions for system access.'
			],
			// Successfully login.
			[
				'login' => [
					'user' => 'Admin',
					'password' => 'zabbix'
				],
				'expected_error' => null
			],
			[
				'login' => [
					'user' => 'Admin',
					'password' => 'zabbix',
					'userData' => true
				],
				'expected_error' => null
			],
			[
				'login' => [
					'user' => 'guest',
					'password' => ''
				],
				'expected_error' => null
			]
		];
	}

	/**
	* @dataProvider login_data
	*/
	public function testUsers_Login($user, $expected_error) {
		$this->disableAuthorization();
		$this->call('user.login', $user, $expected_error);
	}

	public function testUsers_LoginBlocked() {
		$this->disableAuthorization();
		for ($i = 1; $i <= 6; $i++) {
			$result = $this->call('user.login', ['user' => 'Admin', 'password' => 'attempt '.$i], true);
		}

		$this->assertRegExp('/Account is blocked for (2[5-9]|30) seconds./', $result['error']['data']);
	}
}
