<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
require_once __DIR__.'/../include/helpers/CTestDpopHelper.php';
require_once __DIR__.'/../../include/classes/api/helpers/CApiTokenHelper.php';
require_once __DIR__.'/../../include/classes/api/helpers/CApiSettingsHelper.php';

/**
 * @onBefore prepareTestData
 *
 * @onAfter cleanTestData
 */
class testDpopAuthentication extends CAPITest {

	private static string $private_identity_key_pem = '-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIDEjLkFPmkyn3jMC5+60T3pdWQe0jz9MwZO93oK+ziW9oAoGCCqGSM49
AwEHoUQDQgAE03irsjT9U8UHjfQWxRKmO7hRODMWFVYsajjgjBE0FLCjNfOpwqeQ
UrXHxCTF4Vi/euCMvHNipe50AjfMIVJHag==
-----END EC PRIVATE KEY-----
';

	private static string $private_encryption_key_pem = '-----BEGIN EC PRIVATE KEY-----
MHcCAQEEILJTeDFNfHENW5Qo/LWfL7bcjXqDVnddFrSPpgExxD5WoAoGCCqGSM49
AwEHoUQDQgAEjIQIcDocDq/7fdk1hEoEW4Gw6gMVtUP3tUiBLmeu7tyM8HicuolL
KjzHX0EemVt476k9mF1ES35JMrimwv3Yew==
-----END EC PRIVATE KEY-----
';

	private static string $serverid = '';

	private static bool $db_serverid_existed = false;

	private static string $db_server_status = '';

	private static string $server_status_mobile_devices_enabled = '{"configuration":{"enable_mobile_devices":true}}';

	private static $data = [
		'userids' => [
			'superadmin' => null,
			'user_linked_devices_not_allowed' => null,
			'user_device_inactive' => null
		],
		'deviceids' => [
			'superadmin' => null,
			'user_linked_devices_not_allowed' => null,
			'user_device_inactive' => null
		],
		'uuids' => [
			'superadmin' => null,
			'user_linked_devices_not_allowed' => null,
			'user_device_inactive' => null
		],
		'tokenids' => [
			'superadmin' => null,
			'user_linked_devices_not_allowed' => null,
			'user_device_inactive' => null
		],
		'tokens' => [
			'superadmin' => null,
			'user_linked_devices_not_allowed' => null,
			'user_device_inactive' => null
		],
		'keys' => [
			'identity' => null,
			'encryption' => null
		],
		'kids' => [
			'identity' => null,
			'encryption' => null
		]
	];

	public function prepareTestData(): void {
		$db_settings = CApiSettingsHelper::getParameters(['server_status', 'serverid'], false);

		self::$db_server_status = $db_settings['server_status'];

		DB::update('settings', [
			'values' => ['value_str' => self::$server_status_mobile_devices_enabled],
			'where' => ['name' => 'server_status']
		]);

		if (array_key_exists('serverid', $db_settings)) {
			self::$serverid = $db_settings['serverid'];
			self::$db_serverid_existed = true;
		}
		else {
			self::$serverid = generateUuidV7();

			DB::insertBatch('settings', [[
				'name' => 'serverid',
				'type' => 1,
				'value_str' => self::$serverid
			]], false);
		}

		//Create users.
		$users = CDataHelper::call('user.create', [
			[
				'username' => 'superadmin',
				'passwd' => 'ap!userPassw0rd',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 3
			],
			[
				'username' => 'user_linked_devices_not_allowed',
				'passwd' => 'ap!userPassw0rd',
				'usrgrps' => [['usrgrpid' => 11]],
				'roleid' => 1
			],
			[
				'username' => 'superadmin2',
				'passwd' => 'ap!userPassw0rd',
				'usrgrps' => [['usrgrpid' => 7]],
				'roleid' => 3
			]
		]);

		$this->assertArrayHasKey('userids', $users);

		self::$data['userids']['superadmin'] = $users['userids'][0];
		self::$data['userids']['user_linked_devices_not_allowed'] = $users['userids'][1];
		self::$data['userids']['user_device_inactive'] = $users['userids'][2];

		// Create devices for users.
		self::$data['uuids']['superadmin'] = generateUuidV7();
		self::$data['uuids']['user_linked_devices_not_allowed'] = generateUuidV7();
		self::$data['uuids']['user_device_inactive'] = generateUuidV7();

		$deviceid = DB::reserveIds('device', 3);

		$ins_devices = [
			[
				'deviceid' => $deviceid,
				'userid' => self::$data['userids']['superadmin'],
				'uuid' => self::$data['uuids']['superadmin'],
				'name' => 'superadmin',
				'status' => ZBX_DEVICE_STATUS_ACTIVATED
			],
			[
				'deviceid' => bcadd($deviceid, 1, 0),
				'userid' => self::$data['userids']['user_linked_devices_not_allowed'],
				'uuid' => self::$data['uuids']['user_linked_devices_not_allowed'],
				'name' => 'superadmin',
				'status' => ZBX_DEVICE_STATUS_ACTIVATED
			],
			[
				'deviceid' => bcadd($deviceid, 2, 0),
				'userid' => self::$data['userids']['user_device_inactive'],
				'uuid' => self::$data['uuids']['user_device_inactive'],
				'name' => 'superadmin2',
				'status' => ZBX_DEVICE_STATUS_ORPHANED
			]
		];

		DB::insertBatch('device', $ins_devices, false);

		self::$data['deviceids']['superadmin'] = $deviceid;
		self::$data['deviceids']['user_linked_devices_not_allowed'] = bcadd($deviceid, 1, 0);
		self::$data['deviceids']['user_device_inactive'] = bcadd($deviceid, 2, 0);

		// Create DPoP tokens for users.
		self::$data['tokens']['superadmin'] = CApiTokenHelper::generateToken();
		self::$data['tokens']['user_linked_devices_not_allowed'] = CApiTokenHelper::generateToken();
		self::$data['tokens']['user_device_inactive'] = CApiTokenHelper::generateToken();

		$tokenid = DB::reserveIds('token', 3);

		$ins_tokens = [
			[
				'tokenid' => $tokenid,
				'name' => self::$data['uuids']['superadmin'],
				'userid' => self::$data['userids']['superadmin'],
				'token' => CApiTokenHelper::hashToken(self::$data['tokens']['superadmin']),
				'auth_scheme' => ZBX_AUTH_SCHEME_DPOP
			],
			[
				'tokenid' => bcadd($tokenid, 1, 0),
				'name' => self::$data['uuids']['user_linked_devices_not_allowed'],
				'userid' => self::$data['userids']['user_linked_devices_not_allowed'],
				'token' => CApiTokenHelper::hashToken(self::$data['tokens']['user_linked_devices_not_allowed']),
				'auth_scheme' => ZBX_AUTH_SCHEME_DPOP
			],
			[
				'tokenid' => bcadd($tokenid, 2, 0),
				'name' => self::$data['uuids']['user_device_inactive'],
				'userid' => self::$data['userids']['user_device_inactive'],
				'token' => CApiTokenHelper::hashToken(self::$data['tokens']['user_device_inactive']),
				'auth_scheme' => ZBX_AUTH_SCHEME_DPOP
			]
		];

		DB::insertBatch('token', $ins_tokens, false);

		self::$data['tokenids']['superadmin'] = $tokenid;
		self::$data['tokenids']['user_linked_devices_not_allowed'] = bcadd($tokenid, 1, 0);
		self::$data['tokenids']['user_device_inactive'] = bcadd($tokenid, 2, 0);

		// Create token_device records.
		$ins_token_devices = [
			[
				'tokenid' => self::$data['tokenids']['superadmin'],
				'deviceid' => self::$data['deviceids']['superadmin']
			],
			[
				'tokenid' => self::$data['tokenids']['user_linked_devices_not_allowed'],
				'deviceid' => self::$data['deviceids']['user_linked_devices_not_allowed']
			],
			[
				'tokenid' => self::$data['tokenids']['user_device_inactive'],
				'deviceid' => self::$data['deviceids']['user_device_inactive']
			]
		];

		DB::insertBatch('token_device', $ins_token_devices, false);

		// Create device keys.
		self::$data['keys']['identity']= self::makeJWK(self::$private_identity_key_pem);
		self::$data['kids']['identity'] = CTestDpopHelper::base64UrlEncode(
			hash('sha256', json_encode(self::$data['keys']['identity'], JSON_UNESCAPED_SLASHES), true)
		);

		self::$data['keys']['encryption']= self::makeJWK(self::$private_encryption_key_pem);
		self::$data['kids']['encryption'] = CTestDpopHelper::base64UrlEncode(
			hash('sha256', json_encode(self::$data['keys']['encryption'], JSON_UNESCAPED_SLASHES), true)
		);

		$current_time = time();

		$ins_device_keys = [
			[
				'deviceid' => self::$data['deviceids']['superadmin'],
				'scope' => MOBILE_KEY_SCOPE_IDENTITY,
				'kid' => self::$data['kids']['identity'],
				'key_' => json_encode(self::$data['keys']['identity']),
				'active' => 0, // CDevice::DEVICE_KEY_ACTIVE
				'created_at' => $current_time
			],
			[
				'deviceid' => self::$data['deviceids']['superadmin'],
				'scope' => MOBILE_KEY_SCOPE_ENCRYPTION,
				'kid' => self::$data['kids']['encryption'],
				'key_' => json_encode(self::$data['keys']['encryption']),
				'active' => 0, // CDevice::DEVICE_KEY_ACTIVE
				'created_at' => $current_time
			],
			[
				'deviceid' => self::$data['deviceids']['user_linked_devices_not_allowed'],
				'scope' => MOBILE_KEY_SCOPE_IDENTITY,
				'kid' => self::$data['kids']['identity'],
				'key_' => json_encode(self::$data['keys']['identity']),
				'active' => 0, // CDevice::DEVICE_KEY_ACTIVE
				'created_at' => $current_time
			],
			[
				'deviceid' => self::$data['deviceids']['user_linked_devices_not_allowed'],
				'scope' => MOBILE_KEY_SCOPE_ENCRYPTION,
				'kid' => self::$data['kids']['encryption'],
				'key_' => json_encode(self::$data['keys']['encryption']),
				'active' => 0, // CDevice::DEVICE_KEY_ACTIVE
				'created_at' => $current_time
			]
		];

		DB::insertBatch('device_key', $ins_device_keys);
	}

	public function makeJwk(string $private_key_pem): array {
		$private_key = openssl_pkey_get_private($private_key_pem);

		$this->assertNotEquals(false, $private_key, 'Invalid private key.');

		$details = openssl_pkey_get_details($private_key);

		$this->assertArrayHasKey('type', $details, 'Not a key.');
		$this->assertEquals(OPENSSL_KEYTYPE_EC, $details['type'], 'Not an EC key.');

		$ec = $details['ec'];

		return [
			'kty' => 'EC',
			'crv' => 'P-256',
			'x' => CTestDpopHelper::base64UrlEncode($ec['x']),
			'y' => CTestDpopHelper::base64UrlEncode($ec['y'])
		];
	}

	public static function createDpopRequestDataProvider(): array {
		return [
			'Request successful' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => null
			],
			'Request failed due to a missed kid' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Missing kid in DPoP header.'
			],
			'Request failed due to an invalid kid' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'encryption'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Unknown identity key for provided kid.'
			],
			'Request failed due to a missed htu' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Missing htu claim.'
			],
			'Request failed due to an invalid htu' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.create',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Invalid htu value.'
			],
			'Request failed due to a missed ath' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Missing ath claim.'
			],
			'Request failed due to an invalid ath' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'user_linked_devices_not_allowed'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Invalid ath value.'
			],
			'Request failed due to a missed iat' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Missing iat claim.'
			],
			'Request failed due to a missed exp' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Missing exp claim.'
			],
			'Request failed due to iat exceeds exp' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 1,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. iat exceeds exp.'
			],
			'Request failed due to JWT token issued in the future' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 4,
					'exp' => 5,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Invalid iat: JWT token issued in the future beyond allowed skew.'
			],
			'Request failed due to JWT token expired' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => -5,
					'exp' => -3,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. JWT token expired.'
			],
			'Request failed due to an invalid iat (too old)' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => -63,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. JWT token exceeded maximum allowed lifetime.'
			],
			'Request failed due to a missed jti' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0
				],
				'expected_error' => 'Not authorized. Missing jti claim.'
			],
			'Request successful with static jti' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 5,
					'jti' => md5('jti_static'.getmypid())
				],
				'expected_error' => null
			],
			'Request failed due to the same jti' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => md5('jti_static'.getmypid())
				],
				'expected_error' => 'Not authorized. jti already used.'
			],
			'Request failed due to failed signature verification' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'encryption',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Signature verification failed.'
			],
			'Request failed due to linked devices not allowed' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'user_linked_devices_not_allowed'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'user_linked_devices_not_allowed'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'No permissions to call "user.get".'
			],
			'Request failed due to device inactive' => [
				'request_data' => [
					'api_method' => 'user.get',
					'token' => ['tokens', 'user_device_inactive'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'user_device_inactive'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				],
				'expected_error' => 'Not authorized. Device inactive.'
			]
		];
	}

	/**
	 * @dataProvider createDpopRequestDataProvider
	 */
	public function testDpopAuthentication_validJwtStructure(array $request_data, ?string $expected_error = null) {
		$dpop_jwt = self::makeDpopJwt(array_diff_key($request_data, array_flip(['api_method', 'token'])));

		$data = [
			'jsonrpc' => '2.0',
			'method' => $request_data['api_method'],
			'params' => [],
			'id' => '1'
		];

		$token = self::$data[$request_data['token'][0]][$request_data['token'][1]];

		$this->checkResult($this->callRaw($data, $token, $dpop_jwt), $expected_error);
	}

	public static function createMultipleDpopRequestsDataProvider(): array {
		return [
			'Both of requests are successful' => [
				'request_data' => [
					'calls' => [
						'1' => ['api_method' => 'user.get', 'response' => 'result'],
						'2' => ['api_method' => 'apiinfo.version', 'response' => 'result']
					],
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get,apiinfo.version',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				]
			],
			'First request is successful but second gets error' => [
				'request_data' => [
					'calls' => [
						'1' => ['api_method' => 'user.get', 'response' => 'result'],
						'2' => ['api_method' => 'user.login', 'response' => 'error']
					],
					'token' => ['tokens', 'superadmin'],
					'private_key_pem' => 'identity',
					'ath_token' => ['tokens', 'superadmin'],
					'jwk' => ['keys', 'identity'],
					'kid' => ['kids', 'identity'],
					'htu_api_method' => 'user.get,user.login',
					'iat' => 0,
					'exp' => 0,
					'jti' => bin2hex(random_bytes(16))
				]
			]
		];
	}

	/**
	 * @dataProvider createMultipleDpopRequestsDataProvider
	 */
	public function testDpopAuthentication_multipleRequests(array $request_data) {
		$dpop_jwt = self::makeDpopJwt(array_diff_key($request_data, array_flip(['calls', 'token'])));

		$data = [];

		foreach ($request_data['calls'] as $call_id => $call) {
			$data[] = [
				'jsonrpc' => '2.0',
				'method' => $call['api_method'],
				'params' => [],
				'id' => $call_id
			];
		}

		$token = self::$data[$request_data['token'][0]][$request_data['token'][1]];

		$response = $this->callRaw($data, $token, $dpop_jwt);

		$this->assertIsArray($response, 'Batched response is not an array.');

		foreach ($response as $call_result) {
			$this->assertIsArray($call_result);
			$this->assertArrayHasKey('id', $call_result);
			$this->assertArrayHasKey($request_data['calls'][$call_result['id']]['response'], $call_result);
		}
	}

	/**
	 * @param array  $dpop_data
	 * @param string $dpop_data['private_key_pem']
	 * @param string $dpop_data['ath_token']
	 * @param array  $dpop_data['jwk']
	 * @param string $dpop_data['kid']
	 * @param string $dpop_data['htu_api_method']
	 * @param string $dpop_data['iat']
	 * @param string $dpop_data['exp']
	 * @param string $dpop_data['jti']
	 */
	public function makeDpopJwt(array $dpop_data): string {
		$head = [
			'typ' => 'dpop+jwt',
			'alg' => 'ES256',
			'jwk' => self::$data[$dpop_data['jwk'][0]][$dpop_data['jwk'][1]]
		];

		if (array_key_exists('kid', $dpop_data)) {
			$head['kid'] = self::$data[$dpop_data['kid'][0]][$dpop_data['kid'][1]];
		}

		$payload = [];

		$time = time();

		if (array_key_exists('htu_api_method', $dpop_data)) {
			$payload['htu'] =
				'urn:zbx:'.self::$serverid.':'.$dpop_data['htu_api_method'];
		}

		if (array_key_exists('iat', $dpop_data)) {
			$payload['iat'] = $time + $dpop_data['iat'];
		}

		if (array_key_exists('exp', $dpop_data)) {
			$payload['exp'] = $time + $dpop_data['exp'];
		}

		if (array_key_exists('jti', $dpop_data)) {
			$payload['jti'] = $dpop_data['jti'];
		}

		if (array_key_exists('ath_token', $dpop_data)) {
			$token = self::$data[$dpop_data['ath_token'][0]][$dpop_data['ath_token'][1]];

			$payload['ath'] = CTestDpopHelper::base64UrlEncode(hash('sha256', $token, true));
		}

		$private_key_pem = $dpop_data['private_key_pem'] === 'identity'
			? self::$private_identity_key_pem
			: self::$private_encryption_key_pem;

		return CTestDpopHelper::makeJwt($head, $payload, $private_key_pem);
	}

	public static function cleanTestData(): void {
		DB::update('settings', [
			'values' => ['value_str' => self::$db_server_status],
			'where' => ['name' => 'server_status']
		]);

		if (!self::$db_serverid_existed) {
			DB::delete('settings', ['name' => 'serverid']);
		}

		CDataHelper::call('user.delete', array_values(self::$data['userids']));

		DB::delete('device', ['deviceid' => array_values(self::$data['deviceids'])]);
	}
}
