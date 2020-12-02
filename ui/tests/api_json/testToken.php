<?php declare(strict_types=1);
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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup token
 */
class testToken extends CAPITest {

	protected static $unique_counter = 1;
	protected static function uniqueName(): string {
		return 'name'.static::$unique_counter ++;
	}

	public static function token_create(): array {
		return [
			// Name field validation.
			[
				'tokens' => [
					[
						'name' => str_repeat('a', DB::getFieldLength('token', 'name'))
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => str_repeat('a', DB::getFieldLength('token', 'name') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			[
				'tokens' => [
					[
						'name' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			// Description field validation.
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'description' => str_repeat('a', DB::getFieldLength('token', 'description'))
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'description' => str_repeat('a', DB::getFieldLength('token', 'description') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'description' => ''
					],
					[
						'name' => static::uniqueName(),
						'description' => 'test desctiption'
					]
				],
				'expected_error' => null
			],
			// Userid field validation.
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'userid' => 90001 // Non-existing user.
					]
				],
				'expected_error' => 'User with ID "90001" is not available.'
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'userid' => 90000
					]
				],
				'expected_error' => null
			],
			// Status field validation.
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'status' => ZBX_AUTH_TOKEN_ENABLED
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'status' => ZBX_AUTH_TOKEN_DISABLED
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'status' => 2
					]
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of 0, 1.'
			],
			// Expires_at field validation.
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'expires_at' => time() + 3600
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'expires_at' => 0
					]
				],
				'expected_error' => null
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'expires_at' => -20
					]
				],
				'expected_error' => null
			],
			// Successful multiple objects insert.
			[
				'tokens' => [
					[
						'name' => static::uniqueName()
					],
					[
						'name' => static::uniqueName()
					]
				],
				'expected_error' => null
			],
			// Unexpected fields.
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'token' => 'attempted token'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "token".'
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'lastaccess' => time()
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "lastaccess".'
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'created_at' => time()
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "created_at".'
			],
			[
				'tokens' => [
					[
						'name' => static::uniqueName(),
						'creator_userid' => 1
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "creator_userid".'
			],
			// Token name uniqueness within unique users.
			[
				'tokens' => [
					[
						'name' => 'the-same-1',
						'userid' => 1
					],
					[
						'name' => 'not-the-same',
						'userid' => 1
					],
					[
						'name' => 'the-same-1',
						'userid' => 1
					]
				],
				'expected_error' => 'API token "the-same-1" already exists for user "1".'
			],
			[
				'tokens' => [
					[
						'name' => 'the-same-2',
						'userid' => 1
					],
					[
						'name' => 'the-same-2',
						'userid' => 90000
					]
				],
				'expected_error' => null
			],
			// Token name uniqueness with DB lookup.
			[
				'tokens' => [
					[
						'name' => self::uniqueName()
					],
					[
						'name' => 'token-exists',
						'userid' => 2 // Guest user ID.
					]
				],
				'expected_error' => 'API token "token-exists" already exists for user "2".'
			],
			// Admin role.
			[
				'tokens' => [
					[
						'name' => self::uniqueName()
						// 'userid' => 4 // Correct ID should be implied from session.
					]
				],
				'expected_error' => null,
				'auth' => [
					'username' => 'zabbix-admin',
					'password' => 'zabbix',
					'userid' => 4
				]
			],
			[
				'tokens' => [
					[
						'name' => self::uniqueName(),
						'userid' => 5
					]
				],
				'expected_error' => 'User with ID "5" is not available.',
				'auth' => [
					'username' => 'zabbix-admin',
					'password' => 'zabbix',
					'userid' => 4
				]
			],
			// User role.
			[
				'tokens' => [
					[
						'name' => self::uniqueName(),
						'userid' => 4
					]
				],
				'expected_error' => 'User with ID "4" is not available.',
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix',
					'userid' => 5
				]
			],
			[
				'tokens' => [
					[
						'name' => self::uniqueName()
						// 'userid' => 5 // Correct ID should be implied from session.
					]
				],
				'expected_error' => null,
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix',
					'userid' => 5
				]
			],
			// Super admin role.
			[
				'tokens' => [
					[
						'name' => self::uniqueName(),
						'userid' => 2 // Guest user id.
					],
					[
						'name' => self::uniqueName(),
						'userid' => 4
					],
					[
						'name' => self::uniqueName(),
						'userid' => 5
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * @dataProvider token_create
	 */
	public function testToken_Create($tokens, $expected_error, array $auth = []): void {
		if ($auth) {
			$this->authorize($auth['username'], $auth['password']);
			$session_userid = $auth['userid'];
		}
		else {
			$session_userid = 1;
		}

		$result = $this->call('token.create', $tokens, $expected_error);

		if ($expected_error === null) {
			$this->assertEquals(count($result['result']['tokenids']), count($tokens));

			$db_tokens = DB::select('token', [
				'output' => ['name', 'description', 'userid', 'token', 'lastaccess', 'status', 'expires_at',
					'created_at', 'creator_userid'
				],
				'tokenids' => $result['result']['tokenids']
			]);

			foreach ($db_tokens as $index => $db_token) {
				$token = $tokens[$index];

				$this->assertEquals($token['name'], $db_token['name']);

				if (array_key_exists('description', $token)) {
					$this->assertEquals($token['description'], $db_token['description']);
				}
				else {
					$this->assertEquals('', $db_token['description']);
				}

				if (array_key_exists('userid', $token)) {
					$this->assertEquals($token['userid'], $db_token['userid']);
				}
				else {
					$this->assertEquals($session_userid, $db_token['userid'], 'Session user should be the default.');
				}

				$this->assertEquals('0', $db_token['token'], 'Token should be set to NULL.');
				$this->assertEquals('0', $db_token['lastaccess'], 'Token lastaccess be set to 0.');

				if (array_key_exists('status', $token)) {
					$this->assertEquals($token['status'], $db_token['status']);
				}
				else {
					$this->assertEquals(ZBX_AUTH_TOKEN_ENABLED, $db_token['status'], 'Token is enabled by default.');
				}

				if (array_key_exists('expires_at', $token)) {
					$this->assertEquals($token['expires_at'], $db_token['expires_at']);
				}
				else {
					$this->assertEquals('0', $db_token['expires_at'], 'Token never expires by default.');
				}

				$this->assertTrue(abs($db_token['created_at'] - time()) < 2, 'Expected created_at to be almost NOW().');
				$this->assertEquals($session_userid, $db_token['creator_userid'], 'Session user should be the creator');
			}
		}
	}
}
