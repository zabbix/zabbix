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


require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * @backup settings
 * @onBefore prepareTestData
 * @onAfter  cleanTestData
 */
class testHousekeeping extends CAPITest {

	protected static $default_params = [
		'hk_events_mode' => '1',
		'hk_events_trigger' => '200d',
		'hk_events_service' => '2d',
		'hk_events_internal' => '2d',
		'hk_events_discovery' => '2d',
		'hk_events_autoreg' => '2d',
		'hk_services_mode' => '1',
		'hk_services' => '300d',
		'hk_audit_mode' => '1',
		'hk_audit' => '200d',
		'hk_sessions_mode' => '1',
		'hk_sessions' => '200d',
		'hk_history_mode' => '1',
		'hk_history_global' => '1',
		'hk_history' => '69d',
		'hk_trends_mode' => '1',
		'hk_trends_global' => '1',
		'hk_trends' => '200d',
		'compression_status' => '1',
		'compress_older' => '788400000'
	];

	protected static $userids = [];

	public static function prepareTestData(): void {
		$result = CDataHelper::call('user.create', [
			[
				'username' => 'hk_user',
				'passwd' => 'zabbix12345',
				'roleid' => 1,
				'usrgrps' => [['usrgrpid' => 7]]
			],
			[
				'username' => 'hk_admin',
				'passwd' => 'zabbix12345',
				'roleid' => 2,
				'usrgrps' => [['usrgrpid' => 7]]
			]
		]);
		self::$userids = $result['userids'];
	}

	public static function cleanTestData(): void {
		CDataHelper::call('user.delete', self::$userids);
	}

	/**
	 * Update every parameter to default.
	 */
	public function testHousekeeping_Update() {
		$updatedFields = $this->call('housekeeping.update', self::$default_params)['result'];
		$this->assertEquals(array_keys(self::$default_params), $updatedFields);
	}

	/**
	 * Verify that housekeeping.get returns all expected fields.
	 */
	public function testHousekeeping_Get() {
		$response = $this->call('housekeeping.get', ['output' => 'extend']);
		$this->assertArrayHasKey('result', $response);
		$this->assertEquals(self::$default_params, array_intersect_key($response['result'], self::$default_params));
		$this->assertArrayHasKey('db_extension', $response['result']);
	}

	/**
	 * Update with empty parameters.
	 */
	public function testHousekeeping_UpdateEmpty() {
		$updatedFields = $this->call('housekeeping.update', [])['result'];
		$this->assertEquals([], $updatedFields);
	}

	/**
	 * Partial update.
	 */
	public function testHousekeeping_PartialUpdate() {
		$this->call('housekeeping.update', self::$default_params);
		$this->call('housekeeping.update', ['hk_audit_mode' => '0']);
		$after = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
		$this->assertEquals('0', $after['hk_audit_mode']);

		foreach (self::$default_params as $key => $value) {
			if ($key === 'hk_audit_mode') {continue;}
			$this->assertEquals($value, $after[$key], 'Unexpected change in field: '.$key);
		}
	}
 
	/**
	 * Verify boundary values.
	 */
/**
 * Verify boundary values.
 */
	public function testHousekeeping_BoundaryValues() {
		// compress_older minimum valid value (604800 seconds = 7 days).
		$this->call('housekeeping.update', ['compress_older' => '604800']);
		$result = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
		$this->assertEquals('604800', $result['compress_older']);

		// compress_older maximum valid value (788400000 seconds).
		$this->call('housekeeping.update', ['compress_older' => '788400000']);
		$result = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
		$this->assertEquals('788400000', $result['compress_older']);

		// compress_older below minimum boundary.
		$this->call(
			'housekeeping.update',
			['compress_older' => '604799'],
			'Invalid parameter "/compress_older": value must be one of 604800-788400000.'
		);

		// compress_older above maximum boundary.
		$this->call(
			'housekeeping.update',
			['compress_older' => '788400001'],
			'Invalid parameter "/compress_older": value must be one of 604800-788400000.'
		);

		// hk_events_trigger minimum valid value (86400 seconds = 1 day).
		$this->call('housekeeping.update', ['hk_events_trigger' => '1d']);
		$result = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
		$this->assertEquals('1d', $result['hk_events_trigger']);

		// hk_events_trigger maximum valid value (788400000 seconds = 9125 days).
		$this->call('housekeeping.update', ['hk_events_trigger' => '9125d']);
		$result = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
		$this->assertEquals('9125d', $result['hk_events_trigger']);

		// All time-period fields must reject values below minimum (86400 seconds = 1 day).
		$time_fields = [
			'hk_events_trigger', 'hk_events_service', 'hk_events_internal',
			'hk_events_discovery', 'hk_events_autoreg', 'hk_services',
			'hk_audit', 'hk_sessions'
		];

		foreach ($time_fields as $field) {
			$this->call(
				'housekeeping.update',
				[$field => '0d'],
				'Invalid parameter "/'.$field.'": value must be one of 86400-788400000.'
			);
		}

		// All time-period fields must reject values above maximum (788400000 seconds = 9125 days).
		foreach ($time_fields as $field) {
			$this->call(
				'housekeeping.update',
				[$field => '9126d'],
				'Invalid parameter "/'.$field.'": value must be one of 86400-788400000.'
			);
		}
	}

	/**
	 * Validate that invalid parameter values are rejected by the API.
	 *
	 * @dataProvider invalidParamsProvider
	 */
	public function testHousekeeping_Validation(string $field, $value): void {
		$this->call('housekeeping.update', [$field => $value], true);
	}
 
	public function invalidParamsProvider(): array {
		return [
			// Binary-flag fields must reject values outside {0, 1}.
			['hk_events_mode', '2'],
			['hk_services_mode', '2'],
			['hk_audit_mode', '2'],
			['hk_sessions_mode', '2'],
			['hk_history_mode', '2'],
			['hk_history_global', '2'],
			['hk_trends_mode', '2'],
			['hk_trends_global', '2'],
			['compression_status', '2'],

			// Mode field must reject a non-numeric string value.
			['hk_events_mode', 'wrong_type'],

			// Unknown parameter must be rejected.
			['non_existing_param', '123'],

			// Non-numeric strings for time-period fields.
			['hk_events_trigger', 'abc'],
			['hk_events_service', 'wrong'],
			['hk_events_internal', 'text'],
			['hk_events_discovery', 'test'],
			['hk_events_autoreg', 'abc'],
			['hk_services', 'wrong'],
			['hk_audit', 'text'],
			['hk_sessions', 'test'],
			['hk_history', 'text'],
			['hk_trends', '!!'],

			// Unsupported time unit suffix.
			['hk_events_internal', '10x'],
			['hk_audit', '1y'],

			// Negative values.
			['hk_events_service', '-1d'],
			['hk_sessions', '-5d'],

			// Raw integer without a unit suffix (where a suffix is required).
			['hk_events_autoreg', '999'],

			// compress_older: negative value.
			['compress_older', '-100'],
		];
	}
 
	/**
	 * Verify permissions for housekeeping API methods.
	 */
	public function testHousekeeping_Permissions() {
		// A user must be allowed to get housekeeping settings.
		$this->authorize('hk_user', 'zabbix12345');
		$response = $this->call('housekeeping.get', ['output' => 'extend']);
		$this->assertArrayHasKey('result', $response);
 
		// A user must NOT be allowed to update housekeeping settings.
		$this->call('housekeeping.update', ['hk_events_mode' => '1'],
			'No permissions to call "housekeeping.update".'
		);
 
		// An admin must be allowed to get housekeeping settings.
		$this->authorize('hk_admin', 'zabbix12345');
		$response = $this->call('housekeeping.get', ['output' => 'extend']);
		$this->assertArrayHasKey('result', $response);
 
		// An admin must NOT be allowed to update housekeeping settings.
		$this->call('housekeeping.update', ['hk_events_mode' => '1'],
			'No permissions to call "housekeeping.update".'
		);
	}
}