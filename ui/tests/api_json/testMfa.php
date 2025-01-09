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


require_once dirname(__FILE__) . '/../include/CAPITest.php';

/**
 * @backup mfa, config, usrgrp, users, mfa_totp_secret
 *
 * @onBefore prepareTestData
 *
 * @onAfter cleanTestData
 */
class testMfa extends CAPITest {

	public static $data = [
		'mfaids' => [],
		'mfas' => [
			'TOTP test case 1' => [
				'type' => MFA_TYPE_TOTP,
				'name' => 'TOTP test case 1',
				'hash_function' => TOTP_HASH_SHA1,
				'code_length' => TOTP_CODE_LENGTH_8
			],
			'DUO test case 1' => [
				'type' => MFA_TYPE_DUO,
				'name' => 'DUO test case 1',
				'api_hostname' => 'api-999a9a99.duosecurity.com',
				'clientid' => 'AAA58NOODEGUA6ST7AAA',
				'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
			],
			'DUO test case 2' => [
				'type' => MFA_TYPE_DUO,
				'name' => 'DUO test case 2',
				'api_hostname' => 'api-999a9a99.duosecurity.com',
				'clientid' => 'AAA58NOODEGUA6ST7AAA',
				'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
			]
		],
		'usrgrpids' => [],
		'userids' => []
	];

	public function prepareTestData() {
		$mfaids = CDataHelper::call('mfa.create', array_values(self::$data['mfas']));

		$this->assertArrayHasKey('mfaids', $mfaids);
		self::$data['mfaids'] = array_combine(array_keys(self::$data['mfas']), $mfaids['mfaids']);

		CDataHelper::call('authentication.update', [
			'mfaid' => self::$data['mfaids']['DUO test case 1'],
			'mfa_status' => MFA_ENABLED
		]);

		$usrgrpids = CDataHelper::call('usergroup.create', [
			'name' => 'User group with MFA',
			'mfa_status' => GROUP_MFA_ENABLED,
			'mfaid' => self::$data['mfaids']['DUO test case 2']
		]);
		$this->assertArrayHasKey('usrgrpids', $usrgrpids);
		self::$data['usrgrpids'] = array_combine(['User group with MFA'], $usrgrpids['usrgrpids']);

		$userids = CDataHelper::call('user.create', [
			'username' => 'User with MFA TOTP method',
			'roleid' => 1,
			'passwd' => 'Z@bb1x1234',
			'usrgrps' => [
				['usrgrpid' => 7]
			]
		]);
		$this->assertArrayHasKey('userids', $userids);
		self::$data['userids'] = array_combine(['User with MFA TOTP method'], $userids['userids']);

		DB::insert('mfa_totp_secret', [[
			'mfaid' => self::$data['mfaids']['TOTP test case 1'],
			'userid' => self::$data['userids']['User with MFA TOTP method'],
			'totp_secret' => '123asdf123asdf13asdf123asdf123as',
			'status' => TOTP_SECRET_CONFIRMED
		]]);
	}

	public function resolveids($mfas) {
		$resolved_data = $mfas;

		foreach ($mfas as $key => $mfa) {
			if ($key === 'mfaids') {
				foreach ($mfa as $index => $mfaid) {
					if (array_key_exists($mfaid, self::$data['mfaids'])) {
						$resolved_data[$key][$index] = self::$data['mfaids'][$mfaid];
					}
				}
			}
			else {
				if (array_key_exists($mfa['mfaid'], self::$data['mfaids'])){
					$resolved_data[$key]['mfaid'] = self::$data['mfaids'][$mfa['mfaid']];
				}
			}
		}

		return $resolved_data;
	}

	public static function createValidDataProvider() {
		return [
			'Create TOTP MFA methods' => [
				'mfas' => [
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 1', 'hash_function' => TOTP_HASH_SHA1,
						'code_length' => TOTP_CODE_LENGTH_6
					],
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 2', 'hash_function' => TOTP_HASH_SHA256,
						'code_length' => TOTP_CODE_LENGTH_8
					],
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 3', 'hash_function' => TOTP_HASH_SHA512,
						'code_length' => TOTP_CODE_LENGTH_8
					]
				],
				'expected_error' => null
			],
			'Create DUO MFA method' => [
				'mfas' => [
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 1', 'api_hostname' => 'api-999a9a99.duosecurity.com',
						'clientid' => 'AAA58NOODEGUA6ST7AAA',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
					],
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 2', 'api_hostname' => 'api-888a8a88.duosecurity.com',
						'clientid' => 'BBB58NOODEGUA6ST7BBB',
						'client_secret' => '1BbBbBbbBbB7OoB4AaQfV547ARiqOqRNxP32Cult'
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function createInvalidDataProvider() {
		return [
			'Duplicate names in one request' => [
				'mfas' => [
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 1', 'hash_function' => TOTP_HASH_SHA1,
						'code_length' => TOTP_CODE_LENGTH_6
					],
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 1', 'hash_function' => TOTP_HASH_SHA1,
						'code_length' => TOTP_CODE_LENGTH_6
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(TOTP 1) already exists.'
			],
			'MFA with already existing name' => [
				'mfas' => [
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 1', 'hash_function' => TOTP_HASH_SHA1,
						'code_length' => TOTP_CODE_LENGTH_6
					]
				],
				'expected_error' => 'MFA method "TOTP 1" already exists.'
			],
			'Missing MFA name' => [
				'mfas' => [
					['type' => MFA_TYPE_TOTP, 'hash_function' => TOTP_HASH_SHA1, 'code_length' => TOTP_CODE_LENGTH_6],
					['type' => MFA_TYPE_DUO, 'api_hostname' => 'api-999a9a99.duosecurity.com',
						'clientid' => 'AAA58NOODEGUA6ST7AAA',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Missing MFA type' => [
				'mfas' => [
					['name' => 'TOTP 3']
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "type" is missing.'
			],
			'Missing MFA DUO api_hostname' => [
				'mfas' => [
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 4', 'clientid' => 'AAA58NOODEGUA6ST7AAA',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "api_hostname" is missing.'
			],
			'Missing MFA DUO clientid' => [
				'mfas' => [
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 4', 'api_hostname' => 'api-999a9a99.duosecurity.com',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "clientid" is missing.'
			],
			'Missing MFA DUO client_secret' => [
				'mfas' => [
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 4', 'api_hostname' => 'api-999a9a99.duosecurity.com',
						'clientid' => 'AAA58NOODEGUA6ST7AAA'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "client_secret" is missing.'
			],
			'Non-default parameter api_hostname with TOTP method' => [
				'mfas' => [
					['type' => MFA_TYPE_TOTP, 'name' => 'TOTP 4', 'hash_function' => TOTP_HASH_SHA1,
						'code_length' => TOTP_CODE_LENGTH_6, 'api_hostname' => 'api-999a9a99.duosecurity.com'
					]
				],
				'expected_error' => 'Invalid parameter "/1/api_hostname": value must be empty.'
			],
			'Non-default parameter has_function with DUO method' => [
				'mfas' => [
					['type' => MFA_TYPE_DUO, 'name' => 'DUO 4', 'api_hostname' => 'api-999a9a99.duosecurity.com',
						'clientid' => 'AAA58NOODEGUA6ST7AAA',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult',
						'hash_function' => TOTP_HASH_SHA256
					]
				],
				'expected_error' => 'Invalid parameter "/1/hash_function": value must be 1.'
			]
		];
	}

	/**
	 * @dataProvider createValidDataProvider
	 * @dataProvider createInvalidDataProvider
	 */
	public function testCreate($mfas, $expected_error) {
		$response = $this->call('mfa.create', $mfas, $expected_error);

		if ($expected_error === null) {
			self::$data['mfaids'] += array_combine(array_column($mfas, 'name'),
				$response['result']['mfaids']
			);
		}
	}

	public static function updateValidDataProvider(): array {
		return [
			'Update TOTP method name' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'name' => 'NEW TOTP test case 1']
				],
				'expected_error' => null
			],
			'Update TOTP method hash_function' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'hash_function' => 2]
				],
				'expected_error' => null
			],
			'Update TOTP method code_length' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'code_length' => 6]
				],
				'expected_error' => null
			],
			'Update Duo method name' => [
				'mfas' => [
					['mfaid' => 'DUO test case 1', 'name' => 'NEW DUO test case 1']
				],
				'expected_error' => null
			],
			'Update Duo method api_hostname' => [
				'mfas' => [
					['mfaid' => 'DUO test case 1', 'api_hostname' => 'new.api.hostname']
				],
				'expected_error' => null
			],
			'Update Duo method clientid' => [
				'mfas' => [
					['mfaid' => 'DUO test case 1', 'clientid' => 'clientidCLIENTIDclientid']
				],
				'expected_error' => null
			],
			'Update Duo method client_secret' => [
				'mfas' => [
					['mfaid' => 'DUO test case 1', 'client_secret' => 'AAABBBCCCaaabbbccc']
				],
				'expected_error' => null
			],
			'Update TOTP method to DUO method' => [
				'mfas' => [
					[
						'mfaid' => 'TOTP test case 1', 'type' => MFA_TYPE_DUO, 'name' => 'DUO test case switch',
						'api_hostname' => 'api-999a9a99.duosecurity.com', 'clientid' => 'AAA58NOODEGUA6ST7AAA',
						'client_secret' => '1AaAaAaaAaA7OoB4AaQfV547ARiqOqRNxP32Cult'
					]
				],
				'expected_error' => null
			],
			'Update DUO method back to TOTP method' => [
				'mfas' => [
					[
						'mfaid' => 'TOTP test case 1', 'type' => MFA_TYPE_TOTP, 'name' => 'TOTP test case 1',
						'hash_function' => TOTP_HASH_SHA1, 'code_length' => TOTP_CODE_LENGTH_8
					]
				],
				'expected_error' => null
			]
		];
	}

	public static function updateInvalidDataProvider(): array {
		return [
			'Update duplicate name' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'name' => 'NEW DUO test case 1']
				],
				'expected_error' => 'MFA method "NEW DUO test case 1" already exists.'
			],
			'Update duplicate names - cross name update' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'name' => 'NEW DUO test case 1'],
					['mfaid' => 'DUO test case 1', 'name' => 'NEW TOTP test case 1']
				],
				'expected_error' => 'MFA method "NEW DUO test case 1" already exists.'
			],
			'Update non-existing MFA' => [
				'mfas' => [
					['mfaid' => 1234, 'name' => 'TOTP']
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Update DUO specific field to TOTP method' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'api_hostname' => 'host.name']
				],
				'expected_error' => 'Invalid parameter "/1/api_hostname": value must be empty.'
			],
			'Update TOTP specific field to DUO method' => [
				'mfas' => [
					['mfaid' => 'DUO test case 1', 'code_length' => TOTP_CODE_LENGTH_8]
				],
				'expected_error' => 'Invalid parameter "/1/code_length": value must be 6.'
			],
			'Update TOTP method with invalid hash_function' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'hash_function' => 99]
				],
				'expected_error' => 'Invalid parameter "/1/hash_function": value must be one of ' .
					implode(', ', [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512]) . "."
			],
			'Update TOTP method with invalid code_length' => [
				'mfas' => [
					['mfaid' => 'TOTP test case 1', 'code_length' => 10]
				],
				'expected_error' => 'Invalid parameter "/1/code_length": value must be one of ' .
					implode(', ', [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8]) . "."
			]
		];
	}

	/**
	 * @dataProvider updateValidDataProvider
	 * @dataProvider updateInvalidDataProvider
	 */
	public function testUpdate(array $mfas, $expected_error) {
		$mfas = $this->resolveids($mfas);
		$this->call('mfa.update', $mfas, $expected_error);
	}

	public static function deleteValidDataProvider(): array {
		return [
			'Test delete MFA method' => [
				'mfas' => [
					'mfaids' => ['TOTP test case 1']
				],
				'expected_error' => null
			]
		];
	}

	public static function deleteInvalidDataProvider(): array {
		return [
			'Test delete MFA with user group' => [
				'mfas' => [
					'mfaids' => ['DUO test case 2']
				],
				'expected_error' =>
					'Cannot delete MFA method "DUO test case 2", because it is used by user group "User group with MFA".'
			],
			'Test delete id does not exists' => [
				'mfas' => [
					'mfaids' => [1234]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			]
		];
	}

	/**
	 * @dataProvider deleteValidDataProvider
	 * @dataProvider deleteInvalidDataProvider
	 */
	public function testDelete(array $mfas, $expected_error): void {
		$mfas = $this->resolveids($mfas);

		$this->assertNotEmpty($mfas, 'No Mfas to test delete');
		$this->call('mfa.delete', $mfas['mfaids'], $expected_error);

		if ($expected_error === null) {
			$mfa_totp_secrets = DB::select('mfa_totp_secret', [
				'output' => ['userid'],
				'filter' => ['userid' => self::$data['userids']['User with MFA TOTP method']]
			]);
			$this->assertEmpty($mfa_totp_secrets, 'The entry in mfa_totp_secret has not been deleted.');
			self::$data['mfaids'] = array_diff(self::$data['mfaids'], $mfas['mfaids']);
		}
	}

	public static function deleteLastDefaultDataProvider(): array {
		return [
			'Test delete default MFA method' => [
				'mfas' => [
					'mfaids' => ['DUO test case 1']
				],
				'expected_error' => 'Cannot delete default MFA method.'
			]
		];
	}

	/**
	 * @dataProvider deleteLastDefaultDataProvider
	 */
	public function testDeleteLastDefaultMfa(array $mfas, $expected_error): void {
		$mfas = $this->resolveids($mfas);

		CDataHelper::call('usergroup.delete', array_values(self::$data['usrgrpids']));

		// Remove other MFA methods to test deletion of the final default MFA method.
		DBexecute(
			'DELETE FROM mfa'.
			' WHERE '.dbConditionId('mfaid', $mfas['mfaids'], true)
		);

		$this->call('mfa.delete', $mfas['mfaids'], $expected_error);
	}

	/**
	 * Remove data created for tests.
	 */
	public static function cleanTestData(): void {
		CDataHelper::call('authentication.update', ['mfaid' => 0, 'mfa_status' => MFA_DISABLED]);
		CDataHelper::call('usergroup.delete', array_values(self::$data['usrgrpids']));
		CDataHelper::call('mfa.delete', array_values(self::$data['mfaids']));
		CDataHelper::call('user.delete', array_values(self::$data['userids']));
	}
}
