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

/**
 * @onBefore prepareTestData
 *
 * @onAfter clearData
 */
class testConnector extends CAPITest {

	/**
	 * Non-existent ID.
	 */
	private const INVALID_ID = self::INVALID_NUMBER;

	/**
	 * Invalid protocol, data_type, status etc.
	 */
	private const INVALID_NUMBER = 999999;

	/**
	 * @var array
	 */
	private static array $data = [
		'connectorids' => [],

		// Created connectors during connector.create test (deleted at the end).
		'created' => []
	];

	/**
	 * Prepare data for tests.
	 */
	public function prepareTestData(): void {
		$connectors = [
			'get_custom_defaults' => [
				'name' => 'API test connector.get with custom defaults',
				'url' => 'http://localhost/',
				'description' => 'Custom description'
			],
			'get_data_type_events' => [
				'name' => 'API test connector.get with data type (events)',
				'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS,
				'url' => 'http://localhost/'
			],
			'get_url' => [
				'name' => 'API test connector.get with URL (user macro)',
				'url' => '{$URL}'
			],
			'get_http_proxy' => [
				'name' => 'API test connector.get with HTTP proxy (user macro)',
				'url' => 'http://localhost/',
				'http_proxy' => '{$HTTP_PROXY}'
			],
			'get_authtype_basic' => [
				'name' => 'API test connector.get with authtype (basic), username and password',
				'url' => 'http://localhost/',
				'authtype' => ZBX_HTTP_AUTH_BASIC,
				'username' => 'test',
				'password' => '12345678'
			],
			'get_authtype_bearer' => [
				'name' => 'API test connector.get with authtype (bearer)',
				'url' => 'http://localhost/',
				'authtype' => ZBX_HTTP_AUTH_BEARER,
				'token' => '{$BEARER_TOKEN}'
			],
			'get_status_disabled' => [
				'name' => 'API test connector.get with status (disabled)',
				'url' => 'http://localhost/',
				'status' => ZBX_CONNECTOR_STATUS_DISABLED
			],
			'get_tags' => [
				'name' => 'API test connector.get with two tags',
				'url' => 'http://localhost/',
				'tags' => [
					[
						'tag' => 'abc',
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '123'
					],
					[
						'tag' => 'xyz',
						'operator' => CONDITION_OPERATOR_EXISTS
					]
				]
			],
			'update_custom_defaults' => [
				'name' => 'API test connector.update with custom defaults',
				'url' => 'http://localhost/',
				'ssl_cert_file' => 'ssl_cert_file',
				'ssl_key_file' => 'ssl_key_file',
				'ssl_key_password' => 'ssl_key_password'
			],
			'update_authtype_basic' => [
				'name' => 'API test connector.update with authtype (basic), username and password',
				'url' => 'http://localhost/',
				'authtype' => ZBX_HTTP_AUTH_BASIC,
				'username' => 'test',
				'password' => '12345678'
			],
			'update_tags' => [
				'name' => 'API test connector.update with evaltype (or) and two tags',
				'url' => 'http://localhost/',
				'tags_evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'tags' => [
					[
						'tag' => 'abc',
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => '123'
					],
					[
						'tag' => 'xyz',
						'operator' => CONDITION_OPERATOR_EXISTS
					]
				]
			],
			'delete_single' => [
				'name' => 'API test connector.delete - single',
				'url' => 'http://localhost/'
			],
			'delete_multiple_1' => [
				'name' => 'API test connector.delete - multiple 1',
				'url' => 'http://localhost/'
			],
			'delete_multiple_2' => [
				'name' => 'API test connector.delete - multiple 2',
				'url' => 'http://localhost/'
			]
		];
		$db_connectors = CDataHelper::call('connector.create', array_values($connectors));

		$this->assertArrayHasKey('connectorids', $db_connectors,
			__FUNCTION__.'() failed: Could not create connectors.'
		);

		self::$data['connectorids'] = array_combine(array_keys($connectors), $db_connectors['connectorids']);
	}

	/**
	 * Data provider for connector.create. Array contains invalid connectors.
	 *
	 * @return array
	 */
	public static function getConnectorCreateDataInvalid(): array {
		return [
			'Test connector.create: empty request' => [
				'connector' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test connector.create: unexpected parameter' => [
				'connector' => [
					'abc' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "abc".'
			],

			// Check "name".
			'Test connector.create: missing "name"' => [
				'connector' => [
					'description' => ''
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Test connector.create: invalid "name" (empty string)' => [
				'connector' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test connector.create: invalid "name" (too long)' => [
				'connector' => [
					'name' => str_repeat('a', DB::getFieldLength('connector', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			'Test connector.create: multiple connectors with the same "name"' => [
				'connector' => [
					[
						'name' => 'API create connector',
						'url' => 'http://localhost/'
					],
					[
						'name' => 'API create connector',
						'url' => 'http://localhost/'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API create connector) already exists.'
			],
			'Test connector.create: invalid "name" (duplicate)' => [
				'connector' => [
					'name' => 'API test connector.get with custom defaults',
					'url' => 'http://localhost/'
				],
				'expected_error' => 'Connector "API test connector.get with custom defaults" already exists.'
			],

			// Check "protocol".
			'Test connector.create: invalid "protocol" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'protocol' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/protocol": an integer is expected.'
			],
			'Test connector.create: invalid "protocol" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'protocol' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/protocol": value must be 0.'
			],

			// Check "data_type".
			'Test connector.create: invalid "data_type" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'data_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/data_type": an integer is expected.'
			],
			'Test connector.create: invalid "data_type" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'data_type' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/data_type": value must be one of '.
					implode(', ', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]).'.'
			],

			// Check "url".
			'Test connector.create: missing "url"' => [
				'connector' => [
					'name' => 'API create connector'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "url" is missing.'
			],
			'Test connector.create: invalid "url" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => false
				],
				'expected_error' => 'Invalid parameter "/1/url": a character string is expected.'
			],
			'Test connector.create: invalid "url" (empty string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => ''
				],
				'expected_error' => 'Invalid parameter "/1/url": cannot be empty.'
			],
			'Test connector.create: invalid "url"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'javascript:alert(123);'
				],
				'expected_error' => 'Invalid parameter "/1/url": unacceptable URL.'
			],
			'Test connector.create: invalid "url" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => str_repeat('a', DB::getFieldLength('connector', 'url') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/url": value is too long.'
			],

			// Check "item_value_type".
			'Test connector.create: invalid "item_value_type" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES,
					'url' => 'http://localhost/',
					'item_value_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": an integer is expected.'
			],
			'Test connector.create: invalid "item_value_type" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'item_value_type' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": value must be one of 1-63.'
			],
			'Test connector.create: invalid "item_value_type" (not in range) where "data_type" equals 1' => [
				'connector' => [
					'name' => 'API create connector',
					'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS,
					'url' => 'http://localhost/',
					'item_value_type' => 27
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": value must be 31.'
			],
			'Test connector.create: invalid "item_value_type" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES,
					'url' => 'http://localhost/',
					'item_value_type' => false
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": an integer is expected.'
			],

			// Check "authtype".
			'Test connector.create: invalid "authtype" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test connector.create: invalid "authtype" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/authtype": value must be one of '.
					implode(', ', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
						ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER
					]).'.'
			],

			// Check "username".
			'Test connector.create: invalid "username" (must be empty)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'username' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/username": value must be empty.'
			],
			'Test connector.create: invalid "username" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'username' => false
				],
				'expected_error' => 'Invalid parameter "/1/username": a character string is expected.'
			],
			'Test connector.create: invalid "username" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_NTLM,
					'username' => str_repeat('a', DB::getFieldLength('connector', 'username') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],

			// Check "password".
			'Test connector.create: invalid "password" (must be empty)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'password' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/password": value must be empty.'
			],
			'Test connector.create: invalid "password" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'password' => false
				],
				'expected_error' => 'Invalid parameter "/1/password": a character string is expected.'
			],
			'Test connector.create: invalid "password" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_NTLM,
					'password' => str_repeat('a', DB::getFieldLength('connector', 'password') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/password": value is too long.'
			],

			// Check "token".
			'Test connector.create: invalid "token" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'token' => false
				],
				'expected_error' => 'Invalid parameter "/1/token": a character string is expected.'
			],
			'Test connector.create: invalid "token" (incompatible authtype)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'token' => '{$BEARER_TOKEN}'
				],
				'expected_error' => 'Invalid parameter "/1/token": value must be empty.'
			],
			'Test connector.create: invalid "token" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BEARER,
					'token' => str_repeat('a', DB::getFieldLength('connector', 'token') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/token": value is too long.'
			],

			// Check "max_records".
			'Test connector.create: invalid "max_records" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_records' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_records": an integer is expected.'
			],
			'Test connector.create: invalid "max_records" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_records' => -1
				],
				'expected_error' => 'Invalid parameter "/1/max_records": value must be one of 0-'.ZBX_MAX_INT32.'.'
			],

			// Check "max_senders".
			'Test connector.create: invalid "max_senders" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_senders' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_senders": an integer is expected.'
			],
			'Test connector.create: invalid "max_senders" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_senders' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/max_senders": value must be one of 1-100.'
			],

			// Check "max_attempts".
			'Test connector.create: invalid "max_attempts" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_attempts": an integer is expected.'
			],
			'Test connector.create: invalid "max_attempts" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/max_attempts": value must be one of 1-5.'
			],

			// Check "attempt_interval".
			'Test connector.create: invalid "attempt_interval" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 2,
					'attempt_interval' => false
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a character string is expected.'
			],
			'Test connector.create: invalid "attempt_interval" (empty)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 2,
					'attempt_interval' => ''
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": cannot be empty.'
			],
			'Test connector.create: invalid "attempt_interval" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 2,
					'attempt_interval' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
			],
			'Test connector.create: invalid "attempt_interval" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 2,
					'attempt_interval' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-10.'
			],
			'Test connector.create: invalid "attempt_interval" (boolean) where "max_attempts" equals 1' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 1,
					'attempt_interval' => false
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a character string is expected.'
			],
			'Test connector.create: invalid "attempt_interval" (empty) 1 where "max_attempts" equals 1' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 1,
					'attempt_interval' => ''
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be "5s".'
			],
			'Test connector.create: invalid "attempt_interval" (not default) where "max_attempts" equals 1' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'max_attempts' => 1,
					'attempt_interval' => '10s'
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be "5s".'
			],

			// Check "timeout".
			'Test connector.create: invalid "timeout" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'timeout' => false
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a character string is expected.'
			],
			'Test connector.create: invalid "timeout" (empty)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'timeout' => ''
				],
				'expected_error' => 'Invalid parameter "/1/timeout": cannot be empty.'
			],
			'Test connector.create: invalid "timeout" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'timeout' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a time unit is expected.'
			],
			'Test connector.create: invalid "timeout" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'timeout' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/timeout": value must be one of 1-'.SEC_PER_MIN.'.'
			],

			// Check "http_proxy".
			'Test connector.create: invalid "http_proxy" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'http_proxy' => false
				],
				'expected_error' => 'Invalid parameter "/1/http_proxy": a character string is expected.'
			],
			'Test connector.create: invalid "http_proxy" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'http_proxy' => str_repeat('a', DB::getFieldLength('connector', 'http_proxy') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/http_proxy": value is too long.'
			],

			// Check "verify_peer".
			'Test connector.create: invalid "verify_peer" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'verify_peer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/verify_peer": an integer is expected.'
			],
			'Test connector.create: invalid "verify_peer" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'verify_peer' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/verify_peer": value must be one of '.
					implode(', ', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]).'.'
			],

			// Check "verify_host".
			'Test connector.create: invalid "verify_host" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'verify_host' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/verify_host": an integer is expected.'
			],
			'Test connector.create: invalid "verify_host" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'verify_host' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/verify_host": value must be one of '.
					implode(', ', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]).'.'
			],

			// Check "ssl_cert_file".
			'Test connector.create: invalid "ssl_cert_file" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_cert_file": a character string is expected.'
			],
			'Test connector.create: invalid "ssl_cert_file" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => str_repeat('a', DB::getFieldLength('connector', 'ssl_cert_file') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_cert_file": value is too long.'
			],

			// Check "ssl_key_file".
			'Test connector.create: invalid "ssl_key_file" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => 'ssl_cert_file',
					'ssl_key_file' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_file": a character string is expected.'
			],
			'Test connector.create: invalid "ssl_key_file" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => 'ssl_cert_file',
					'ssl_key_file' => str_repeat('a', DB::getFieldLength('connector', 'ssl_key_file') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_file": value is too long.'
			],

			// Check "ssl_key_password".
			'Test connector.create: invalid "ssl_key_password" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => 'ssl_cert_file',
					'ssl_key_file' => 'ssl_key_file',
					'ssl_key_password' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_password": a character string is expected.'
			],
			'Test connector.create: invalid "ssl_key_password" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'ssl_cert_file' => 'ssl_cert_file',
					'ssl_key_file' => 'ssl_key_file',
					'ssl_key_password' => str_repeat('a', DB::getFieldLength('connector', 'ssl_key_password') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_password": value is too long.'
			],

			// Check "description".
			'Test connector.create: invalid "description" (boolean)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test connector.create: invalid "description" (too long)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'description' => str_repeat('a', DB::getFieldLength('connector', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "status".
			'Test connector.create: invalid "status" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test connector.create: invalid "status" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'status' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]).'.'
			],

			// Check "tags_evaltype".
			'Test connector.create: invalid "tags_evaltype" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags_evaltype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tags_evaltype": an integer is expected.'
			],
			'Test connector.create: invalid "tags_evaltype" (not in range)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags_evaltype' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tags_evaltype": value must be one of '.
					implode(', ', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]).'.'
			],

			// Check "tags".
			'Test connector.create: invalid "tags" (string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			'Test connector.create: invalid "tags" (array with string)' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => ['abc']
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			'Test connector.create: missing "tag" for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			'Test connector.create: unexpected parameter for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['abc' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": unexpected parameter "abc".'
			],
			'Test connector.create: invalid "tag" (boolean) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			'Test connector.create: invalid "tag" (empty string) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
			],
			'Test connector.create: invalid "tag" (too long) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => str_repeat('a', DB::getFieldLength('connector_tag', 'tag') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": value is too long.'
			],
			'Test connector.create: invalid "operator" (boolean) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": an integer is expected.'
			],
			'Test connector.create: invalid "operator" (not in range) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => self::INVALID_NUMBER]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": value must be one of '.
					implode(', ', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]).'.'
			],
			'Test connector.create: invalid "value" (boolean) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			'Test connector.create: invalid "value" (not empty) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EXISTS, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value must be empty.'
			],
			'Test connector.create: invalid "value" (not empty) for "tags" 2' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_NOT_EXISTS, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value must be empty.'
			],
			'Test connector.create: invalid "value" (too long) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						[
							'tag' => 'abc',
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => str_repeat('a', DB::getFieldLength('connector_tag', 'value') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value is too long.'
			],
			'Test connector.create: invalid "tag" (duplicate) for "tags"' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '123'],
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(abc, 0, 123) already exists.'
			],
			'Test connector.create: overly long username' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'username' => str_repeat('z', 256)
				],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],
			'Test connector.create: overly long password' => [
				'connector' => [
					'name' => 'API create connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'password' => str_repeat('z', 256)
				],
				'expected_error' => 'Invalid parameter "/1/password": value is too long.'
			]
		];
	}

	/**
	 * Data provider for connector.create. Array contains valid connectors.
	 *
	 * @return array
	 */
	public static function getConnectorCreateDataValid(): array {
		return [
			'Test connector.create: single connector' => [
				'connector' => [
					'name' => 'API create single connector',
					'url' => 'http://localhost/'
				],
				'expected_error' => null
			],
			'Test connector.create: multiple connectors' => [
				'connector' => [
					[
						'name' => 'API create first connector',
						'url' => 'http://localhost/',
						'item_value_type' => 30
					],
					[
						'name' => 'API create second connector',
						'url' => 'http://localhost/',
						'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES,
						'item_value_type' => ZBX_CONNECTOR_ITEM_VALUE_TYPE_FLOAT
					]
				],
				'expected_error' => null
			],
			'Test connector.create: longest username' => [
				'connector' => [
					'name' => 'API longest username connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'username' => str_repeat('z', 255)
				],
				'expected_error' => null
			],
			'Test connector.create: longest password' => [
				'connector' => [
					'name' => 'API longest password connector',
					'url' => 'http://localhost/',
					'authtype' => ZBX_HTTP_AUTH_BASIC,
					'password' => str_repeat('z', 255)
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test connector.create with errors like missing fields, optional invalid fields and valid fields.
	 *
	 * @dataProvider getConnectorCreateDataInvalid
	 * @dataProvider getConnectorCreateDataValid
	 */
	public function testConnector_Create(array $connectors, ?string $expected_error): void {
		// Accept single and multiple connectors just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $connectors)) {
			$connectors = zbx_toArray($connectors);
		}

		$sql_connectors = 'SELECT NULL FROM connector';
		$old_hash_connectors = CDBHelper::getHash($sql_connectors);

		$result = $this->call('connector.create', $connectors, $expected_error);

		if ($expected_error === null) {
			// Something was changed in DB.
			$this->assertNotSame($old_hash_connectors, CDBHelper::getHash($sql_connectors));
			$this->assertEquals(count($connectors), count($result['result']['connectorids']));

			// Add connector IDs to create array, so they can be deleted after tests are complete.
			self::$data['created'] = array_merge(self::$data['created'], $result['result']['connectorids']);

			$db_connectors = $this->getConnectors($result['result']['connectorids']);
			$db_defaults = DB::getDefaults('connector');

			// Check individual fields.
			foreach ($result['result']['connectorids'] as $num => $connectorid) {
				$connector = $connectors[$num];
				$db_connector = $db_connectors[$connectorid];

				// Required fields.
				$this->assertNotEmpty($db_connector['name']);
				$this->assertSame($connector['name'], $db_connector['name']);
				$this->assertNotEmpty($db_connector['url']);
				$this->assertSame($connector['url'], $db_connector['url']);

				// Numeric fields.
				foreach (['protocol', 'data_type', 'authtype', 'max_records', 'max_senders', 'max_attempts',
						'verify_peer', 'verify_host', 'status', 'tags_evaltype'] as $field) {
					if (array_key_exists($field, $connector)) {
						$this->assertEquals($connector[$field], $db_connector[$field]);
					}
					else {
						$this->assertEquals($db_defaults[$field], $db_connector[$field]);
					}
				}

				if ($db_connector['data_type'] == ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES
						&& array_key_exists('item_value_type', $connector)) {
					$this->assertEquals($connector['item_value_type'], $db_connector['item_value_type']);
				}
				else {
					$this->assertEquals($db_defaults['item_value_type'], $db_connector['item_value_type']);
				}

				// Text fields.
				if (array_key_exists('timeout', $connector)) {
					$this->assertSame($connector['timeout'], $db_connector['timeout']);
				}
				else {
					$this->assertSame($db_defaults['timeout'], $db_connector['timeout']);
				}

				if ($db_connector['max_attempts'] > 1 && array_key_exists('attempt_interval', $connector)) {
					$this->assertSame($connector['attempt_interval'], $db_connector['attempt_interval']);
				}
				else {
					$this->assertSame($db_defaults['attempt_interval'], $db_connector['attempt_interval']);
				}

				foreach (['http_proxy', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'description'] as $field) {
					if (array_key_exists($field, $connector)) {
						$this->assertSame($connector[$field], $db_connector[$field]);
					}
					else {
						$this->assertEmpty($db_connector[$field]);
					}
				}

				if (in_array($db_connector['authtype'], [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM,
						ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])) {
					foreach (['username', 'password'] as $field) {
						if (array_key_exists($field, $connector)) {
							$this->assertSame($connector[$field], $db_connector[$field]);
						}
						else {
							$this->assertEmpty($db_connector[$field]);
						}
					}
				}
				else {
					$this->assertEmpty($db_connector['username']);
					$this->assertEmpty($db_connector['password']);
				}

				if ($db_connector['authtype'] == ZBX_HTTP_AUTH_BEARER) {
					$this->assertNotEmpty($db_connector['token']);
					$this->assertSame($connector['token'], $db_connector['token']);
				}
				else {
					$this->assertEmpty($db_connector['token']);
				}

				// Tags.
				if (array_key_exists('tags', $connector) && $connector['tags']) {
					$this->assertEqualsCanonicalizing($connector['tags'], $db_connector['tags']);
				}
				else {
					$this->assertEmpty($db_connector['tags']);
				}
			}
		}
		else {
			$this->assertSame($old_hash_connectors, CDBHelper::getHash($sql_connectors));
		}
	}

	/**
	 * Data provider for connector.get. Array contains invalid connector parameters.
	 *
	 * @return array
	 */
	public static function getConnectorGetDataInvalid(): array {
		return [
			// Check unexpected params.
			'Test connector.get: unexpected parameter' => [
				'request' => [
					'abc' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "abc".'
			],

			// Check "connectorids" field.
			'Test connector.get: invalid "connectorids" (empty string)' => [
				'request' => [
					'connectorids' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/connectorids": an array is expected.'
			],
			'Test connector.get: invalid "connectorids" (array with empty string)' => [
				'request' => [
					'connectorids' => ['']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/connectorids/1": a number is expected.'
			],

			// Check filter.
			'Test connector.get: invalid "filter" (empty string)' => [
				'request' => [
					'filter' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in filter.
			'Test connector.get: unexpected parameter in "filter"' => [
				'request' => [
					'filter' => [
						'description' => 'description'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "description".'
			],

			// Check "search" option.
			'Test connector.get: invalid "search" (string)' => [
				'request' => [
					'search' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in search.
			'Test connector.get: unexpected parameter in "search"' => [
				'request' => [
					'search' => [
						'connectorid' => 'connectorid'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": unexpected parameter "connectorid".'
			],

			// Check "output" option.
			'Test connector.get: invalid parameter "output" (string)' => [
				'request' => [
					'output' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test connector.get: invalid parameter "output" (array with string)' => [
				'request' => [
					'output' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "connectorid", "name", "protocol", "data_type", "url", "item_value_type", "authtype", "username", "password", "token", "max_records", "max_senders", "max_attempts", "attempt_interval", "timeout", "http_proxy", "verify_peer", "verify_host", "ssl_cert_file", "ssl_key_file", "ssl_key_password", "description", "status", "tags_evaltype".'
			],

			// Check "selectTags" option.
			'Test connector.get: invalid parameter "selectTags" (string)' => [
				'request' => [
					'selectTags' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectTags": value must be one of "'.API_OUTPUT_EXTEND.'", "'.API_OUTPUT_COUNT.'".'
			],
			'Test connector.get: invalid parameter "selectTags" (array with string)' => [
				'request' => [
					'selectTags' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectTags/1": value must be one of "tag", "operator", "value".'
			],

			// Check common fields that are not flags, but require strict validation.
			'Test connector.get: invalid parameter "searchByAny" (string)' => [
				'request' => [
					'searchByAny' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
			],
			'Test connector.get: invalid parameter "searchWildcardsEnabled" (string)' => [
				'request' => [
					'searchWildcardsEnabled' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
			],
			'Test connector.get: invalid parameter "sortfield" (bool)' => [
				'request' => [
					'sortfield' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield": an array is expected.'
			],
			'Test connector.get: invalid parameter "sortfield"' => [
				'request' => [
					'sortfield' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of "connectorid", "name", "data_type", "status".'
			],
			'Test connector.get: invalid parameter "sortorder" (bool)' => [
				'request' => [
					'sortorder' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			'Test connector.get: invalid parameter "sortorder" (not in range)' => [
				'request' => [
					'sortorder' => 'abc'
				],
				'expected_result' => [],
				'expected_error' =>
					'Invalid parameter "/sortorder": value must be one of "'.ZBX_SORT_UP.'", "'.ZBX_SORT_DOWN.'".'
			],
			'Test connector.get: invalid parameter "limit" (bool)' => [
				'request' => [
					'limit' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/limit": an integer is expected.'
			],
			'Test connector.get: invalid parameter "preservekeys" (string)' => [
				'request' => [
					'preservekeys' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
			]
		];
	}

	/**
	 * Data provider for connector.get. Array contains valid connector parameters.
	 *,
	 * @return array
	 */
	public static function getConnectorGetDataValid(): array {
		return [
			// Check validity of "connectorids" without getting any results.
			'Test connector.get: empty "connectorids"' => [
				'request' => [
					'connectorids' => []
				],
				'expected_result' => [],
				'expected_error' => null
			],

			// Check no fields are returned on empty selection.
			'Test connector.get: empty "output"' => [
				'request' => [
					'output' => [],
					'connectorids' => ['get_custom_defaults', 'get_status_disabled']
				],
				'expected_result' => [
					[],
					[]
				],
				'expected_error' => null
			],

			// Check only specific fields are returned.
			'Test connector.get: specific field "output"' => [
				'request' => [
					'output' => ['connectorid', 'name', 'url'],
					'connectorids' => ['get_custom_defaults']
				],
				'expected_result' => [
					[
						'connectorid' => 'get_custom_defaults',
						'name' => 'API test connector.get with custom defaults',
						'url' => 'http://localhost/'
					]
				],
				'expected_error' => null
			],

			// Filter by data type.
			'Test connector.get: filter by "data_type"' => [
				'request' => [
					'output' => ['name', 'data_type'],
					'connectorids' => ['get_custom_defaults', 'get_data_type_events'],
					'filter' => [
						'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with data type (events)',
						'data_type' => (string) ZBX_CONNECTOR_DATA_TYPE_EVENTS
					]
				],
				'expected_error' => null
			],

			// Filter by authentication type.
			'Test connector.get: filter by "authtype"' => [
				'request' => [
					'output' => ['name', 'authtype', 'username', 'password'],
					'connectorids' => ['get_custom_defaults', 'get_authtype_basic'],
					'filter' => [
						'authtype' => ZBX_HTTP_AUTH_BASIC
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with authtype (basic), username and password',
						'authtype' => (string) ZBX_HTTP_AUTH_BASIC,
						'username' => 'test',
						'password' => '12345678'
					]
				],
				'expected_error' => null
			],

			// Filter by status.
			'Test connector.get: filter by "status"' => [
				'request' => [
					'output' => ['name', 'status'],
					'connectorids' => ['get_custom_defaults', 'get_status_disabled'],
					'filter' => [
						'status' => ZBX_CONNECTOR_STATUS_DISABLED
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with status (disabled)',
						'status' => (string) ZBX_CONNECTOR_STATUS_DISABLED
					]
				],
				'expected_error' => null
			],

			// Search by name.
			'Test connector.get: search by "name"' => [
				'request' => [
					'output' => ['name'],
					'search' => [
						'name' => 'API test connector.get with custom defaults'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with custom defaults'
					]
				],
				'expected_error' => null
			],

			// Search by URL.
			'Test connector.get: search by "url"' => [
				'request' => [
					'output' => ['name', 'url'],
					'search' => [
						'url' => '{$URL}'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with URL (user macro)',
						'url' => '{$URL}'
					]
				],
				'expected_error' => null
			],

			// Search by HTTP proxy.
			'Test connector.get: search by "http_proxy"' => [
				'request' => [
					'output' => ['name', 'http_proxy'],
					'search' => [
						'http_proxy' => '{$HTTP_PROXY}'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with HTTP proxy (user macro)',
						'http_proxy' => '{$HTTP_PROXY}'
					]
				],
				'expected_error' => null
			],

			// Search by username.
			'Test connector.get: search by "username"' => [
				'request' => [
					'output' => ['name', 'authtype', 'username', 'password'],
					'connectorids' => ['get_custom_defaults', 'get_authtype_basic'],
					'search' => [
						'username' => 'test'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with authtype (basic), username and password',
						'authtype' => (string) ZBX_HTTP_AUTH_BASIC,
						'username' => 'test',
						'password' => '12345678'
					]
				],
				'expected_error' => null
			],

			// Search by Bearer token.
			'Test connector.get: search by "token"' => [
				'request' => [
					'output' => ['name', 'authtype', 'token'],
					'search' => [
						'token' => '{$BEARER_TOKEN}'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with authtype (bearer)',
						'authtype' => (string) ZBX_HTTP_AUTH_BEARER,
						'token' => '{$BEARER_TOKEN}'
					]
				],
				'expected_error' => null
			],

			// Search by description.
			'Test connector.get: search by "description"' => [
				'request' => [
					'output' => ['name', 'description'],
					'search' => [
						'description' => 'Custom description'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test connector.get with custom defaults',
						'description' => 'Custom description'
					]
				],
				'expected_error' => null
			],

			// Check tags are returned.
			'Test connector.get: tags' => [
				'request' => [
					'output' => ['connectorid', 'name'],
					'selectTags' => ['tag', 'operator', 'value'],
					'connectorids' => ['get_custom_defaults', 'get_tags']
				],
				'expected_result' => [
					[
						'connectorid' => 'get_custom_defaults',
						'name' => 'API test connector.get with custom defaults',
						'tags' => []
					],
					[
						'connectorid' => 'get_tags',
						'name' => 'API test connector.get with two tags',
						'tags' => [
							[
								'tag' => 'abc',
								'operator' => (string) CONDITION_OPERATOR_EQUAL,
								'value' => '123'
							],
							[
								'tag' => 'xyz',
								'operator' => (string) CONDITION_OPERATOR_EXISTS,
								'value' => ''
							]
						]
					]
				],
				'expected_error' => null
			],

			// Check tag count is returned.
			'Test connector.get: tag count' => [
				'request' => [
					'output' => ['connectorid', 'name'],
					'selectTags' => API_OUTPUT_COUNT,
					'connectorids' => ['get_custom_defaults', 'get_tags']
				],
				'expected_result' => [
					[
						'connectorid' => 'get_custom_defaults',
						'name' => 'API test connector.get with custom defaults',
						'tags' => '0'
					],
					[
						'connectorid' => 'get_tags',
						'name' => 'API test connector.get with two tags',
						'tags' => '2'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test connector.get with all options.
	 *
	 * @dataProvider getConnectorGetDataInvalid
	 * @dataProvider getConnectorGetDataValid
	 */
	public function testConnector_Get(array $request, array $expected_result, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		$request = self::resolveIds($request);

		foreach ($expected_result as &$connector) {
			$connector = self::resolveIds($connector);
		}
		unset($connector);

		$result = $this->call('connector.get', $request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($expected_result, $result['result']);
		}
	}

	/**
	 * Data provider for connector.update. Array contains invalid connector parameters.
	 *
	 * @return array
	 */
	public static function getConnectorUpdateDataInvalid(): array {
		return [
			'Test connector.update: empty request' => [
				'connector' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],

			// Check "connectorid".
			'Test connector.update: missing "connectorid"' => [
				'connector' => [
					'name' => 'API update connector'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "connectorid" is missing.'
			],
			'Test connector.update: invalid "connectorid" (empty string)' => [
				'connector' => [
					'connectorid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/connectorid": a number is expected.'
			],
			'Test connector.update: invalid "connectorid" (non-existent)' => [
				'connector' => [
					'connectorid' => self::INVALID_ID
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test connector.update: multiple connectors with the same "connectorid"' => [
				'connector' => [
					['connectorid' => 0],
					['connectorid' => 0]
				],
				'expected_error' => 'Invalid parameter "/2": value (connectorid)=(0) already exists.'
			],

			// Check "name".
			'Test connector.update: invalid "name" (empty string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test connector.update: invalid "name" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'name' => str_repeat('a', DB::getFieldLength('connector', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],

			// Check "protocol".
			'Test connector.update: invalid "protocol" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'protocol' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/protocol": an integer is expected.'
			],
			'Test connector.update: invalid "protocol" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'protocol' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/protocol": value must be 0.'
			],

			// Check "data_type".
			'Test connector.update: invalid "data_type" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'data_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/data_type": an integer is expected.'
			],
			'Test connector.update: invalid "data_type" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'data_type' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/data_type": value must be one of '.
					implode(', ', [ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES, ZBX_CONNECTOR_DATA_TYPE_EVENTS]).'.'
			],

			// Check "url".
			'Test connector.update: invalid "url" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'url' => false
				],
				'expected_error' => 'Invalid parameter "/1/url": a character string is expected.'
			],
			'Test connector.update: invalid "url" (empty string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'url' => ''
				],
				'expected_error' => 'Invalid parameter "/1/url": cannot be empty.'
			],
			'Test connector.update: invalid "url"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'url' => 'javascript:alert(123);'
				],
				'expected_error' => 'Invalid parameter "/1/url": unacceptable URL.'
			],
			'Test connector.update: invalid "url" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'url' => str_repeat('a', DB::getFieldLength('connector', 'url') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/url": value is too long.'
			],

			// Check "item_value_type".
			'Test connector.update: invalid "item_value_type" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'data_type' => ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES,
					'item_value_type' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": an integer is expected.'
			],
			'Test connector.update: invalid "item_value_type" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'item_value_type' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": value must be one of 1-63.'
			],
			'Test connector.update: invalid "item_value_type" (not in range) where "data_type" equals 1' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'data_type' => ZBX_CONNECTOR_DATA_TYPE_EVENTS,
					'item_value_type' => 27
				],
				'expected_error' => 'Invalid parameter "/1/item_value_type": value must be 31.'
			],

			// Check "authtype".
			'Test connector.update: invalid "authtype" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'authtype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/authtype": an integer is expected.'
			],
			'Test connector.update: invalid "authtype" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'authtype' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/authtype": value must be one of '.
					implode(', ', [ZBX_HTTP_AUTH_NONE, ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM, ZBX_HTTP_AUTH_KERBEROS,
						ZBX_HTTP_AUTH_DIGEST, ZBX_HTTP_AUTH_BEARER
					]).'.'
			],

			// Check "username".
			'Test connector.update: invalid "username" (must be empty)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'username' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/username": value must be empty.'
			],
			'Test connector.update: invalid "username" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'username' => false
				],
				'expected_error' => 'Invalid parameter "/1/username": a character string is expected.'
			],
			'Test connector.update: invalid "username" (too long)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'username' => str_repeat('a', DB::getFieldLength('connector', 'username') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/username": value is too long.'
			],

			// Check "password".
			'Test connector.update: invalid "password" (must be empty)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'password' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/password": value must be empty.'
			],
			'Test connector.update: invalid "password" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'password' => false
				],
				'expected_error' => 'Invalid parameter "/1/password": a character string is expected.'
			],
			'Test connector.update: invalid "password" (too long)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'password' => str_repeat('a', DB::getFieldLength('connector', 'password') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/password": value is too long.'
			],

			// Check "token".
			'Test connector.update: invalid "token" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'token' => false
				],
				'expected_error' => 'Invalid parameter "/1/token": a character string is expected.'
			],
			'Test connector.update: invalid "token" (incompatible authtype)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'token' => '{$BEARER_TOKEN}'
				],
				'expected_error' => 'Invalid parameter "/1/token": value must be empty.'
			],
			'Test connector.update: invalid "token" (too long)' => [
				'connector' => [
					'connectorid' => 'update_authtype_basic',
					'authtype' => ZBX_HTTP_AUTH_BEARER,
					'token' => str_repeat('a', DB::getFieldLength('connector', 'token') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/token": value is too long.'
			],

			// Check "max_records".
			'Test connector.update: invalid "max_records" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_records' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_records": an integer is expected.'
			],
			'Test connector.update: invalid "max_records" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_records' => -1
				],
				'expected_error' => 'Invalid parameter "/1/max_records": value must be one of 0-'.ZBX_MAX_INT32.'.'
			],

			// Check "max_senders".
			'Test connector.update: invalid "max_senders" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_senders' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_senders": an integer is expected.'
			],
			'Test connector.update: invalid "max_senders" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_senders' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/max_senders": value must be one of 1-100.'
			],

			// Check "max_attempts".
			'Test connector.update: invalid "max_attempts" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/max_attempts": an integer is expected.'
			],
			'Test connector.update: invalid "max_attempts" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/max_attempts": value must be one of 1-5.'
			],

			// Check "attempt_interval".
			'Test connector.update: invalid "attempt_interval" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 2,
					'attempt_interval' => false
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a character string is expected.'
			],
			'Test connector.update: invalid "attempt_interval" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 2,
					'attempt_interval' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a time unit is expected.'
			],
			'Test connector.update: invalid "attempt_interval" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 2,
					'attempt_interval' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be one of 0-10.'
			],
			'Test connector.update: invalid "attempt_interval" (boolean) where "max_attempts" equals 1' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 1,
					'attempt_interval' => false
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": a character string is expected.'
			],
			'Test connector.update: invalid "attempt_interval" (empty) where "max_attempts" equals 1' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 1,
					'attempt_interval' => ''
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be "5s".'
			],
			'Test connector.update: invalid "attempt_interval" (not in range) where "max_attempts" equals 1' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'max_attempts' => 1,
					'attempt_interval' => '10s'
				],
				'expected_error' => 'Invalid parameter "/1/attempt_interval": value must be "5s".'
			],

			// Check "timeout".
			'Test connector.update: invalid "timeout" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'timeout' => false
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a character string is expected.'
			],
			'Test connector.update: invalid "timeout" (empty)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'timeout' => ''
				],
				'expected_error' => 'Invalid parameter "/1/timeout": cannot be empty.'
			],
			'Test connector.update: invalid "timeout" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'timeout' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/timeout": a time unit is expected.'
			],
			'Test connector.update: invalid "timeout" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'timeout' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/timeout": value must be one of 1-'.SEC_PER_MIN.'.'
			],

			// Check "http_proxy".
			'Test connector.update: invalid "http_proxy" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'http_proxy' => false
				],
				'expected_error' => 'Invalid parameter "/1/http_proxy": a character string is expected.'
			],
			'Test connector.update: invalid "http_proxy" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'http_proxy' => str_repeat('a', DB::getFieldLength('connector', 'http_proxy') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/http_proxy": value is too long.'
			],

			// Check "verify_peer".
			'Test connector.update: invalid "verify_peer" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'verify_peer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/verify_peer": an integer is expected.'
			],
			'Test connector.update: invalid "verify_peer" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'verify_peer' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/verify_peer": value must be one of '.
					implode(', ', [ZBX_HTTP_VERIFY_PEER_OFF, ZBX_HTTP_VERIFY_PEER_ON]).'.'
			],

			// Check "verify_host".
			'Test connector.update: invalid "verify_host" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'verify_host' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/verify_host": an integer is expected.'
			],
			'Test connector.update: invalid "verify_host" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'verify_host' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/verify_host": value must be one of '.
					implode(', ', [ZBX_HTTP_VERIFY_HOST_OFF, ZBX_HTTP_VERIFY_HOST_ON]).'.'
			],

			// Check "ssl_cert_file".
			'Test connector.update: invalid "ssl_cert_file" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_cert_file' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_cert_file": a character string is expected.'
			],
			'Test connector.update: invalid "ssl_cert_file" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_cert_file' => str_repeat('a', DB::getFieldLength('connector', 'ssl_cert_file') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_cert_file": value is too long.'
			],

			// Check "ssl_key_file".
			'Test connector.update: invalid "ssl_key_file" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_key_file' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_file": a character string is expected.'
			],
			'Test connector.update: invalid "ssl_key_file" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_key_file' => str_repeat('a', DB::getFieldLength('connector', 'ssl_key_file') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_file": value is too long.'
			],

			// Check "ssl_key_password".
			'Test connector.update: invalid "ssl_key_password" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_key_password' => false
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_password": a character string is expected.'
			],
			'Test connector.update: invalid "ssl_key_password" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'ssl_key_password' => str_repeat('a', DB::getFieldLength('connector', 'ssl_key_password') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/ssl_key_password": value is too long.'
			],

			// Check "description".
			'Test connector.update: invalid "description" (boolean)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test connector.update: invalid "description" (too long)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'description' => str_repeat('a', DB::getFieldLength('connector', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "status".
			'Test connector.update: invalid "status" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test connector.update: invalid "status" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'status' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [ZBX_CONNECTOR_STATUS_DISABLED, ZBX_CONNECTOR_STATUS_ENABLED]).'.'
			],

			// Check "tags_evaltype".
			'Test connector.update: invalid "tags_evaltype" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags_evaltype' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tags_evaltype": an integer is expected.'
			],
			'Test connector.update: invalid "tags_evaltype" (not in range)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags_evaltype' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tags_evaltype": value must be one of '.
					implode(', ', [CONDITION_EVAL_TYPE_AND_OR, CONDITION_EVAL_TYPE_OR]).'.'
			],

			// Check "tags".
			'Test connector.update: invalid "tags" (string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tags": an array is expected.'
			],
			'Test connector.update: invalid "tags" (array with string)' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => ['abc']
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": an array is expected.'
			],
			'Test connector.update: missing "tag" for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": the parameter "tag" is missing.'
			],
			'Test connector.update: unexpected parameter for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['abc' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1": unexpected parameter "abc".'
			],
			'Test connector.update: invalid "tag" (boolean) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": a character string is expected.'
			],
			'Test connector.update: invalid "tag" (empty string) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": cannot be empty.'
			],
			'Test connector.update: invalid "tag" (too long) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => str_repeat('a', DB::getFieldLength('connector_tag', 'tag') + 1)]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/tag": value is too long.'
			],
			'Test connector.update: invalid "operator" (boolean) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": an integer is expected.'
			],
			'Test connector.update: invalid "operator" (not in range) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => self::INVALID_NUMBER]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/operator": value must be one of '.
					implode(', ', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
						CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_EXISTS, CONDITION_OPERATOR_NOT_EXISTS
					]).'.'
			],
			'Test connector.update: invalid "value" (boolean) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => false]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": a character string is expected.'
			],
			'Test connector.update: invalid "value" (not empty) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EXISTS, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value must be empty.'
			],
			'Test connector.update: invalid "value" (not empty) for "tags" 2' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_NOT_EXISTS, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value must be empty.'
			],
			'Test connector.update: invalid "value" (too long) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						[
							'tag' => 'abc',
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => str_repeat('a', DB::getFieldLength('connector_tag', 'value') + 1)
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/1/value": value is too long.'
			],
			'Test connector.update: invalid "tag" (duplicate) for "tags"' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults',
					'tags' => [
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '123'],
						['tag' => 'abc', 'operator' => CONDITION_OPERATOR_EQUAL, 'value' => '123']
					]
				],
				'expected_error' => 'Invalid parameter "/1/tags/2": value (tag, operator, value)=(abc, 0, 123) already exists.'
			]
		];
	}

	/**
	 * Data provider for connector.update. Array contains valid connector parameters.
	 *
	 * @return array
	 */
	public static function getConnectorUpdateDataValid(): array {
		return [
			'Test connector.update: update single connector without changes' => [
				'connector' => [
					'connectorid' => 'update_custom_defaults'
				],
				'expected_error' => null
			],
			'Test connector.update: update multiple connectors' => [
				'connector' => [
					[
						'connectorid' => 'update_custom_defaults',
						'name' => 'API test connector.update - first connector'
					],
					[
						'connectorid' => 'update_authtype_basic',
						'name' => 'API test connector.update - second connector'
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test connector.update method.
	 *
	 * @dataProvider getConnectorUpdateDataInvalid
	 * @dataProvider getConnectorUpdateDataValid
	 */
	public function testConnector_Update(array $connectors, ?string $expected_error): void {
		// Accept single and multiple connectors just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $connectors)) {
			$connectors = zbx_toArray($connectors);
		}

		// Replace ID placeholders with real IDs.
		foreach ($connectors as &$connector) {
			$connector = self::resolveIds($connector);
		}
		unset($connector);

		$sql_connectors = 'SELECT NULL FROM connector';
		$old_hash_connectors = CDBHelper::getHash($sql_connectors);

		if ($expected_error === null) {
			$connectorids = array_column($connectors, 'connectorid');
			$db_connectors = $this->getConnectors($connectorids);
			$db_defaults = DB::getDefaults('connector');

			$this->call('connector.update', $connectors, $expected_error);

			$connectors_upd = $this->getConnectors($connectorids);

			// Compare records from DB before and after API call.
			foreach ($connectors as $connector) {
				$db_connector = $db_connectors[$connector['connectorid']];
				$connector_upd = $connectors_upd[$connector['connectorid']];

				// Required fields.
				$this->assertNotEmpty($connector_upd['name']);
				$this->assertNotEmpty($connector_upd['url']);

				// Numeric fields.
				foreach (['protocol', 'data_type', 'authtype', 'max_records', 'max_senders', 'max_attempts',
						'verify_peer', 'verify_host', 'status', 'tags_evaltype'] as $field) {
					if (array_key_exists($field, $connector)) {
						$this->assertEquals($connector[$field], $connector_upd[$field]);
					}
					else {
						$this->assertEquals($db_connector[$field], $connector_upd[$field]);
					}
				}

				if ($connector_upd['data_type'] == ZBX_CONNECTOR_DATA_TYPE_ITEM_VALUES
						&& array_key_exists('item_value_type', $connector)) {
					$this->assertEquals($connector['item_value_type'], $connector_upd['item_value_type']);
				}
				else {
					$this->assertEquals($db_defaults['item_value_type'], $connector_upd['item_value_type']);
				}

				// Text fields.
				foreach (['timeout', 'http_proxy', 'ssl_cert_file', 'ssl_key_file', 'ssl_key_password', 'description']
						as $field) {
					if (array_key_exists($field, $connector)) {
						$this->assertSame($connector[$field], $connector_upd[$field]);
					}
					else {
						$this->assertSame($db_connector[$field], $connector_upd[$field]);
					}
				}

				if ($connector_upd['max_attempts'] > 1 && array_key_exists('attempt_interval', $connector)) {
						$this->assertSame($connector['attempt_interval'], $connector_upd['attempt_interval']);
				}
				else {
					$this->assertSame($db_defaults['attempt_interval'], $connector_upd['attempt_interval']);
				}

				if (in_array($connector_upd['authtype'], [ZBX_HTTP_AUTH_BASIC, ZBX_HTTP_AUTH_NTLM,
						ZBX_HTTP_AUTH_KERBEROS, ZBX_HTTP_AUTH_DIGEST])) {
					foreach (['username', 'password'] as $field) {
						if (array_key_exists($field, $connector)) {
							$this->assertSame($connector[$field], $connector_upd[$field]);
						}
						else {
							$this->assertSame($db_connector[$field], $connector_upd[$field]);
						}
					}
				}
				else {
					$this->assertEmpty($connector_upd['username']);
					$this->assertEmpty($connector_upd['password']);
				}

				if ($connector_upd['authtype'] == ZBX_HTTP_AUTH_BEARER) {
					$this->assertNotEmpty($connector_upd['token']);

					if (array_key_exists($field, $connector)) {
						$this->assertSame($connector['token'], $connector_upd['token']);
					}
					else {
						$this->assertSame($db_connector['token'], $connector_upd['token']);
					}
				}
				else {
					$this->assertEmpty($connector_upd['token']);
				}

				// Tags.
				if (array_key_exists('tags', $connector)) {
					if ($connector['tags']) {
						$this->assertNotEmpty($connector_upd['tags']);
						$this->assertEqualsCanonicalizing($connector['tags'], $connector_upd['tags']);
					}
					else {
						$this->assertEmpty($connector_upd['tags']);
					}
				}
				else {
					$this->assertEqualsCanonicalizing($db_connector['tags'], $connector_upd['tags']);
				}
			}

			// Restore connector original data after each test.
			$this->call('connector.update', $db_connectors);
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('connector.update', $connectors, $expected_error);

			// Make sure nothing has changed as well.
			$this->assertSame($old_hash_connectors, CDBHelper::getHash($sql_connectors));
		}
	}

	/**
	 * Data provider for connector.delete. Array contains invalid connectors that are not possible to delete.
	 *
	 * @return array
	 */
	public static function getConnectorDeleteDataInvalid(): array {
		return [
			// Check connector IDs.
			'Test connector.delete: empty ID' => [
				'connectorids' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Test connector.delete: non-existent ID' => [
				'connectorids' => [self::INVALID_ID],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test connector.delete: with two same IDs' => [
				'connectorids' => [0, 0],
				'expected_error' => 'Invalid parameter "/2": value (0) already exists.'
			]
		];
	}

	/**
	 * Data provider for connector.delete. Array contains valid connectors.
	 *
	 * @return array
	 */
	public static function getConnectorDeleteDataValid(): array {
		return [
			'Test connector.delete: delete single connector' => [
				'connector' => ['delete_single'],
				'expected_error' => null
			],
			'Test connector.delete: delete multiple connectors' => [
				'connector' => [
					'delete_multiple_1',
					'delete_multiple_2'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test connector.delete method.
	 *
	 * @dataProvider getConnectorDeleteDataInvalid
	 * @dataProvider getConnectorDeleteDataValid
	 */
	public function testConnector_Delete(array $connectorids, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		foreach ($connectorids as &$connectorid) {
			if (self::isValidIdPlaceholder($connectorid)) {
				$connectorid = self::$data['connectorids'][$connectorid];
			}
		}
		unset($connectorid);

		$sql_connectors = 'SELECT NULL FROM connector';
		$old_hash_connectors = CDBHelper::getHash($sql_connectors);

		$this->call('connector.delete', $connectorids, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_connectors, CDBHelper::getHash($sql_connectors));
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT c.connectorid FROM connector c WHERE '.dbConditionId('c.connectorid', $connectorids)
			));

			// connector.delete checks if given IDs exist, so they need to be removed from self::$data['connectorids']
			foreach ($connectorids as $connectorid) {
				$key = array_search($connectorid, self::$data['connectorids']);

				if ($key !== false) {
					unset(self::$data['connectorids'][$key]);
				}
			}
		}
		else {
			$this->assertSame($old_hash_connectors, CDBHelper::getHash($sql_connectors));
		}
	}

	/**
	 * Get the original connectors before update.
	 *
	 * @param array $connectorids
	 *
	 * @return array
	 */
	private function getConnectors(array $connectorids): array {
		$response = $this->call('connector.get', [
			'output' => ['connectorid', 'name', 'protocol', 'data_type', 'url', 'item_value_type', 'authtype',
				'username', 'password', 'token', 'max_records', 'max_senders', 'max_attempts', 'attempt_interval',
				'timeout', 'http_proxy', 'verify_peer', 'verify_host', 'ssl_cert_file', 'ssl_key_file',
				'ssl_key_password', 'description', 'status', 'tags_evaltype'
			],
			'selectTags' => ['tag', 'operator', 'value'],
			'connectorids' => $connectorids,
			'preservekeys' => true
		]);

		return $response['result'];
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete connectors.
		$connectorids = array_values(self::$data['connectorids']);
		$connectorids = array_merge($connectorids, self::$data['created']);
		CDataHelper::call('connector.delete', $connectorids);
	}

	/**
	 * Helper method to convert placeholders to real IDs.
	 *
	 * @param array $request
	 *
	 * @return array
	 */
	private static function resolveIds(array $request): array {
		if (array_key_exists('connectorids', $request)) {
			if (is_array($request['connectorids'])) {
				foreach ($request['connectorids'] as &$id_placeholder) {
					if (self::isValidIdPlaceholder($id_placeholder)) {
						$id_placeholder = self::$data['connectorids'][$id_placeholder];
					}
				}
				unset($id_placeholder);
			}
			elseif (self::isValidIdPlaceholder($request['connectorids'])) {
				$request['connectorids'] = self::$data['connectorids'][$request['connectorids']];
			}
		}
		elseif (array_key_exists('connectorid', $request) && self::isValidIdPlaceholder($request['connectorid'])) {
			$request['connectorid'] = self::$data['connectorids'][$request['connectorid']];
		}

		return $request;
	}

	/**
	 * Helper method to check ID placeholder.
	 *
	 * @param $id_placeholder
	 *
	 * @return bool
	 */
	private static function isValidIdPlaceholder($id_placeholder): bool {
		// Do not compare != 0 (it will not work) or !== 0 or !== '0' (avoid type check here).
		return !is_array($id_placeholder) && $id_placeholder != '0' && $id_placeholder !== ''
			&& $id_placeholder !== null && $id_placeholder != self::INVALID_ID;
	}
}
