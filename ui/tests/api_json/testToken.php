<?php declare(strict_types = 0);
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
						'description' => 'test description'
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
				'expected_error' => 'Invalid parameter "/3": value (userid, name)=(1, the-same-1) already exists.'
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
						'name' => 'test-token-exists',
						'userid' => 2 // Guest user ID.
					]
				],
				'expected_error' => 'API token "test-token-exists" already exists for userid "2".'
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

	public static function token_delete(): array {
		return [
			[
				'tokenids' => [2, 3, 4, 5],
				'expected_error' => 'No permissions to referred object or it does not exist!',
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix'
				]
			],
			[
				'tokenids' => [2, 5],
				'expected_error' => null,
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix'
				]
			],
			[
				'tokenids' => [2, 5],
				'expected_error' => 'No permissions to referred object or it does not exist!',
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix'
				]
			],
			[
				'tokenids' => [2, 3, 4, 5],
				'expected_error' => 'No permissions to referred object or it does not exist!',
				'auth' => [
					'username' => 'Admin',
					'password' => 'zabbix'
				]
			],
			[
				'tokenids' => [3, 4],
				'expected_error' => null,
				'auth' => [
					'username' => 'Admin',
					'password' => 'zabbix'
				]
			],
			[
				'tokenids' => [9, 9],
				'expected_error' => 'Invalid parameter "/2": value (9) already exists.',
				'auth' => [
					'username' => 'Admin',
					'password' => 'zabbix'
				]
			]
		];
	}

	/**
	 * @dataProvider token_delete
	 */
	public function testToken_Delete($tokenids, $expected_error, array $auth = []): void {
		if ($auth) {
			$this->authorize($auth['username'], $auth['password']);
		}

		$db_tokens_before = DB::select('token', [
			'output' => ['tokenid'],
			'tokenids' => $tokenids
		]);

		$result = $this->call('token.delete', $tokenids, $expected_error);

		$db_tokens_after = DB::select('token', [
			'output' => ['tokenid'],
			'tokenids' => $tokenids
		]);

		if ($expected_error === null) {
			$this->assertEquals($result['result']['tokenids'], $tokenids, 'Response tokenids should match the request.');
			$this->assertEmpty($db_tokens_after, 'DB records should be deleted.');
		}
		else {
			$this->assertEquals($db_tokens_after, $db_tokens_before, 'No tokens got deleted.');
		}
	}

	public static function token_get(): array {
		return [
			// Input validation.
			[
				'request' => [
					'output' => [],
					'tokenids' => 'x'
				],
				'expected' => [
					'error' => 'Invalid parameter "/tokenids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'tokenids' => ['x']
				],
				'expected' => [
					'error' => 'Invalid parameter "/tokenids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'userids' => 'x'
				],
				'expected' => [
					'error' => 'Invalid parameter "/userids": an array is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'userids' => ['x']
				],
				'expected' => [
					'error' => 'Invalid parameter "/userids/1": a number is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'token' => ['x']
				],
				'expected' => [
					'error' => 'Invalid parameter "/token": a character string is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'token' => str_repeat('x', 65)
				],
				'expected' => [
					'error' => 'Invalid parameter "/token": value is too long.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'valid_at' => 'x'
				],
				'expected' => [
					'error' => 'Invalid parameter "/valid_at": an integer is expected.',
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'expired_at' => 'x'
				],
				'expected' => [
					'error' => 'Invalid parameter "/expired_at": an integer is expected.',
					'result' => []
				]
			],
			// Input validation, filter object.
			[
				'request' => [
					'output' => [],
					'filter' => [
						'tokenid' => ['x']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'tokenid' => 'x'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'userid' => 'x'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'userid' => ['x']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'lastaccess' => ['x']
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'lastaccess' => 'x'
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => [2]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'status' => 2
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'expires_at' => ["x"]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'expires_at' => "x"
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'created_at' => ["x"]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'created_at' => "x"
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'creator_userid' => "x"
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'filter' => [
						'creator_userid' => ["x"]
					]
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			// Correct output using search.
			[
				'request' => [
					'output' => [],
					'search' => [
						'name' => 'test-get'
					],
					'limit' => 1
				],
				'expected' => [
					'error' => null,
					'result' => [[]]
				]
			],
			[
				'request' => [
					'output' => [],
					'search' => [
						'name' => 'test-get'
					],
					'countOutput' => true,
					'sortfield' => ['name', 'status'] // Should not cause errors when used in conjunction with count.
				],
				'expected' => [
					'error' => null,
					'result' => 5
				]
			],
			// Correct output using property fields select.
			[
				'request' => [
					'output' => [],
					'tokenids' => ["1"]
				],
				'expected' => [
					'error' => null,
					'result' => [[]]
				]
			],
			[
				'request' => [
					'output' => [],
					'userids' => ["12"]
				],
				'expected' => [
					'error' => null,
					'result' => [[], []]
				]
			],
			[
				'request' => [
					'output' => [],
					'token' => 'a26ddc6178485b5189b103e9775763bdc01e8d19fcbe6c7dea99ae2e2d50ae1a'
				],
				'expected' => [
					'error' => null,
					'result' => [[]]
				]
			],
			[
				'request' => [
					'output' => [],
					'valid_at' => "123",
					'tokenids' => "12"
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			[
				'request' => [
					'output' => [],
					'valid_at' => "122",
					'tokenids' => "12"
				],
				'expected' => [
					'error' => null,
					'result' => [[]]
				]
			],
			[
				'request' => [
					'output' => [],
					'expired_at' => "123",
					'tokenids' => "12"
				],
				'expected' => [
					'error' => null,
					'result' => [[]]
				]
			],
			[
				'request' => [
					'output' => [],
					'expired_at' => "122",
					'tokenids' => "12"
				],
				'expected' => [
					'error' => null,
					'result' => []
				]
			],
			// Permission check.
			[
				'request' => [
					'output' => [],
					'search' => [
						'name' => 'test-get'
					],
					'countOutput' => true
				],
				'expected' => [
					'error' => null,
					'result' => 5
				],
				'auth' => [
					'username' => 'Admin',
					'password' => 'zabbix'
				]
			],
			[
				'request' => [
					'output' => [],
					'search' => [
						'name' => 'test-get'
					],
					'countOutput' => true
				],
				'expected' => [
					'error' => null,
					'result' => 2
				],
				'auth' => [
					'username' => 'zabbix-user',
					'password' => 'zabbix'
				]
			]
		];
	}

	/**
	 * @dataProvider token_get
	 */
	public function testToken_Get($request, $expected, array $auth = []): void {
		if ($auth) {
			$this->authorize($auth['username'], $auth['password']);
		}

		$result = $this->call('token.get', $request, $expected['error']);

		if ($expected['error'] === null) {
			$this->assertEquals($result['result'], $expected['result']);
		}
	}

	public static function token_update(): array {
		return [
			'#1 case "tokenid is mandatory"' =>
				[
					'request' => [
						[
							'name' => 'test-name-y'
						]
					],
					'expect_error' => 'Invalid parameter "/1": the parameter "tokenid" is missing.'
				],
			'#2 case "tokenid must be unique"' =>
				[
					'request' => [
						[
							'tokenid' => '1',
							'name' => 'test-name-y'
						],
						[
							'tokenid' => '1',
							'name' => 'test-name-x'
						]
					],
					'expect_error' => 'Invalid parameter "/2": value (tokenid)=(1) already exists.'
				],
			'#3 case "name field can be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'name' => 'update-super-admin-1-updated'
						]
					],
					'expect_error' => null
				],
			'#4 case "description field can be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'description' => 'update-super-admin-1-updated'
						]
					],
					'expect_error' => null
				],
			'#5 case "status field can be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'status' => ZBX_AUTH_TOKEN_DISABLED
						]
					],
					'expect_error' => null
				],
			'#6 case "status field can be updated #2"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'status' => ZBX_AUTH_TOKEN_ENABLED
						]
					],
					'expect_error' => null
				],
			'#7 case "expires_at field can be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'expires_at' => time() + 3600
						]
					],
					'expect_error' => null
				],
			'#8 case "expires_at field can be updated #2"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'expires_at' => 0
						]
					],
					'expect_error' => null
				],
			'#9 case "token field cannot be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'token' => bin2hex(random_bytes(64))
						]
					],
					'expect_error' => 'Invalid parameter "/1": unexpected parameter "token".'
				],
			'#10 case "userid field cannot be updated"' =>
				[
					'request' => [
						[
							'tokenid' => '15',
							'userid' => '4'
						]
					],
					'expect_error' => 'Invalid parameter "/1": unexpected parameter "userid".'
				],
			'#11 case "non-super admin cannot update tokens of other users"' =>
				[
					'request' => [
						[
							'tokenid' => '15', // Belongs to other user.
							'name' => 'update-test-name-x'
						],
						[
							'tokenid' => '18', // Belongs to this user.
							'name' => 'update-test-name-y'
						]
					],
					'expect_error' => 'No permissions to referred object or it does not exist!',
					'auth' => [
						'username' => 'zabbix-user',
						'password' => 'zabbix'
					]
				],
			'#12 case "super admin can update tokens for other users"' =>
				[
					'request' => [
						[
							'tokenid' => '15', // Belongs to this user.
							'name' => 'update-test-name-x'
						],
						[
							'tokenid' => '18', // Belongs to other user.
							'name' => 'update-test-name-y'
						]
					],
					'expect_error' => null
				],
			'#13 case "user can update token name to the same name"' =>
				[
					'request' => [
						[
							'tokenid' => '17',
							'name' => 'update-user-1' // This name exists in DB.
						],
						[
							'tokenid' => '19',
							'name' => 'update-user-3', // This name exists in DB.
							'description' => 'new description'
						]
					],
					'expect_error' => null
				],
			'#14 case "user cannot update token Y name if such name is used in token X"' =>
				[
					'request' => [
						[
							'tokenid' => '20',
							'name' => 'update-user-5' // This user has token (ID: 21) using this name.
						]
					],
					'expect_error' => 'API token "update-user-5" already exists for userid "5".'
				],
			'#15 case "cannot update identical token names within request"' =>
				[
					'request' => [
						[
							'tokenid' => '20',
							'name' => 'update-user-22'
						],
						[
							'tokenid' => '21',
							'name' => 'update-user-22'
						]
					],
					'expected_error' => 'Invalid parameter "/2": value (userid, name)=(5, update-user-22) already exists.'
				]
		];
	}

	/**
	 * @dataProvider token_update
	 */
	public function testToken_Update($tokens, $expect_error = null, array $auth = []): void {
		if ($auth) {
			$this->authorize($auth['username'], $auth['password']);
		}

		$result = $this->call('token.update', $tokens, $expect_error);

		if ($expect_error === null) {
			$db_tokens = DB::select('token', [
				'output' => ['tokenid', 'name', 'description', 'status', 'expires_at'],
				'tokenids' => $result['result']['tokenids'],
				'sortfield' => ['tokenid']
			]);

			foreach ($db_tokens as $index => $db_token) {
				$token = $tokens[$index];

				if (array_key_exists('name', $token)) {
					$this->assertEquals($token['name'], $db_token['name']);
				}

				if (array_key_exists('description', $token)) {
					$this->assertEquals($token['description'], $db_token['description']);
				}

				if (array_key_exists('status', $token)) {
					$this->assertEquals($token['status'], $db_token['status']);
				}

				if (array_key_exists('expires_at', $token)) {
					$this->assertEquals($token['expires_at'], $db_token['expires_at']);
				}
			}
		}
	}

	public function testToken_Generate(): void {
		$adminid = 1;
		$userid = 5;
		$this->authorize('Admin', 'zabbix'); // Super admin role (ID = 1)
		['result' => ['tokenids' => $tokenids]] = $this->call('token.create', [
			['name' => '1', 'userid' => 5],
			['name' => '1', 'userid' => 1]
		], null);

		[$user_tokenid, $admin_tokenid] = $tokenids;

		// Token ids must be unique.
		$this->call('token.generate', [1, 1],
				'Invalid parameter "/2": value (1) already exists.'
		);

		// User role cannot generate other tokens.
		$this->authorize('zabbix-user', 'zabbix'); // User role (ID = 5)
		$this->call('token.generate', [$user_tokenid, $admin_tokenid],
				'No permissions to referred object or it does not exist!'
		);

		// After successful generate call, session user becomes the record creator.
		$this->assertEquals($adminid,
				CDBHelper::getValue('SELECT creator_userid FROM token WHERE tokenid='.zbx_dbstr($user_tokenid))
		);
		['result' => [['token' => $token]]] = $this->call('token.generate', [$user_tokenid]);
		$this->assertEquals($userid,
				CDBHelper::getValue('SELECT creator_userid FROM token WHERE tokenid='.zbx_dbstr($user_tokenid))
		);

		// The generated token hash matches record in DB.
		$this->assertEquals(hash('sha512', $token),
				CDBHelper::getValue('SELECT token FROM token WHERE tokenid='.zbx_dbstr($user_tokenid)),
				'User token value updated'
		);

		// Super admin can generate/regenerate token for anyone.
		$this->authorize('Admin', 'zabbix'); // Super admin role (ID = 1)
		['result' => $tokens] = $this->call('token.generate', [$user_tokenid, $admin_tokenid]);
		[['token' => $user_token], ['token' => $admin_token]] = $tokens;

		// After successful generate call, session user becomes the record creator.
		$this->assertEquals($adminid,
				CDBHelper::getValue('SELECT creator_userid FROM token WHERE tokenid='.zbx_dbstr($user_tokenid))
		);
		$this->assertEquals($adminid,
				CDBHelper::getValue('SELECT creator_userid FROM token WHERE tokenid='.zbx_dbstr($admin_tokenid))
		);

		// The generated token hash matches record in DB.
		$this->assertEquals(hash('sha512', $user_token),
				CDBHelper::getValue('SELECT token FROM token WHERE tokenid='.zbx_dbstr($user_tokenid)),
				'User token value updated'
		);
		$this->assertEquals(hash('sha512', $admin_token),
				CDBHelper::getValue('SELECT token FROM token WHERE tokenid='.zbx_dbstr($admin_tokenid)),
				'Admin token value re-updated'
		);
	}

	private function countAuditActions(int $action): int {
		return count(DB::select('auditlog', ['output' => [], 'filter' => [
				'resourcetype' => 45 /* CAudit::RESOURCE_AUTH_TOKEN */,
				'action' => $action
		]]));
	}

	public function testToken_auditlogs(): void {
		$add_records = $this->countAuditActions(0 /* CAudit::ACTION_ADD */);
		$update_records = $this->countAuditActions(1 /* CAudit::ACTION_UPDATE */);
		$delete_records = $this->countAuditActions(2 /* CAudit::ACTION_DELETE */);

		['result' => ['tokenids' => [$new_id]]] = $this->call('token.create', ['name' => 'audit 1']);
		$this->assertEquals($add_records + 1, $this->countAuditActions(0 /* CAudit::ACTION_ADD */));

		$this->call('token.update', ['tokenid' => $new_id, 'name' => 'audit 2']);
		$this->assertEquals($update_records + 1, $this->countAuditActions(1 /* CAudit::ACTION_UPDATE */));

		$this->call('token.generate', [$new_id]);
		$this->assertEquals($update_records + 2, $this->countAuditActions(1 /* CAudit::ACTION_UPDATE */));

		$this->call('token.delete', [$new_id]);
		$this->assertEquals($delete_records + 1, $this->countAuditActions(2 /* CAudit::ACTION_DELETE */));
	}

	public function testToken_deleteTokenCreator(): void {
		['result' => [['creator_userid' => $creator_userid]]] = $this->call('token.get', [
			'output' => ['creator_userid'],
			'tokenids' => 23
		]);
		$this->assertEquals(20, $creator_userid);
		$this->call('user.delete', [20]);
		['result' => [['creator_userid' => $creator_userid]]] = $this->call('token.get', [
			'output' => ['creator_userid'],
			'tokenids' => 23
		]);
		$this->assertEquals(0, $creator_userid);
	}
}
