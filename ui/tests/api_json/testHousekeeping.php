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

define('TEST_GOOD', 0);
define('TEST_BAD', 1);

/**
 * @backup settings
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

	/**
	 * Update every parameter to default.
	 */
	public function testHousekeeping_Update() {
		$updatedFields = $this->call('housekeeping.update', self::$default_params)['result'];
		$this->assertEquals(array_keys(self::$default_params), $updatedFields);
	}

	/**
	 * Verify that housekeeping.get returns all expected fields.
	 *
	 * @depends testHousekeeping_Update
	 */
	public function testHousekeeping_Get() {
		// Extend output returns all fields.
		$response = $this->call('housekeeping.get', ['output' => 'extend']);
		$this->assertEquals(self::$default_params, array_intersect_key($response['result'], self::$default_params));
		$this->assertEquals('', $response['result']['db_extension']);

		// Limited output returns only requested fields.
		$response = $this->call('housekeeping.get', ['output' => ['hk_events_mode', 'hk_history']]);
		$this->assertEquals('1', $response['result']['hk_events_mode']);
		$this->assertEquals('69d', $response['result']['hk_history']);
		$this->assertArrayNotHasKey('hk_trends', $response['result']);
		$this->assertArrayNotHasKey('compress_older', $response['result']);
		$this->assertArrayNotHasKey('db_extension', $response['result']);
	}

	/**
	 * Update with empty parameters.
	 */
	public function testHousekeeping_UpdateEmpty() {
		$sql = 'SELECT * FROM settings';
		$old_hash = CDBHelper::getHash($sql);
		$updatedFields = $this->call('housekeeping.update', [])['result'];
		$this->assertEquals([], $updatedFields);
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	/**
	 * Update housekeeping parameters with valid and invalid values.
	 *
	 * @dataProvider updateParamsProvider
	 */
	public function testHousekeeping_UpdateParams($data) {
		$this->call('housekeeping.update', $data['params'],
			($data['expected'] === TEST_BAD) ? $data['error'] ?? true : null
		);

		if ($data['expected'] === TEST_GOOD) {
			$update_output = $this->call('housekeeping.get', ['output' => 'extend'])['result'];
			foreach ($data['params'] as $key => $value) {
				$this->assertEquals($value, $update_output[$key], 'Field mismatch: '.$key);
			}
		}
	}

	public function updateParamsProvider() {
		return [
			// Partial update.
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['hk_audit_mode' => '0']
				]
			],
			// Parameter compress_older minimum valid value (604800 seconds = 7 days).
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['compress_older' => '604800']
				]
			],
			// Parameter compress_older maximum valid value (788400000 seconds).
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['compress_older' => '788400000']
				]
			],
			// Parameter hk_events_trigger minimum valid value (86400 seconds = 1 day).
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['hk_events_trigger' => '1d']
				]
			],
			// Parameter hk_events_trigger maximum valid value (788400000 seconds = 9125 days).
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['hk_events_trigger' => '9125d']
				]
			],
			// hk_history accepts 0 and minimum valid value (3600 seconds = 1 hour).
			[['expected' => TEST_GOOD, 'params' => ['hk_history' => '0']]],
			[['expected' => TEST_GOOD, 'params' => ['hk_history' => '1h']]],
			// hk_trends accepts 0 and minimum valid value (86400 seconds = 1 day).
			[['expected' => TEST_GOOD, 'params' => ['hk_trends' => '0']]],
			[['expected' => TEST_GOOD, 'params' => ['hk_trends' => '1d']]],
			// History storage period can be updated even when history is disabled.
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['hk_history_mode' => '0', 'hk_history' => '90d']
				]
			],
			// Trends storage period can be updated even when trends is disabled.
			[
				[
					'expected' => TEST_GOOD,
					'params' => ['hk_trends_mode' => '0', 'hk_trends' => '90d']
				]
			],
			// Parameter compress_older below minimum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['compress_older' => '604799'],
					'error' => 'Invalid parameter "/compress_older": value must be one of 604800-788400000.'
				]
			],
			// Parameter compress_older above maximum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['compress_older' => '788400001'],
					'error' => 'Invalid parameter "/compress_older": value must be one of 604800-788400000.'
				]
			],
			// Parameter hk_events_trigger below minimum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_events_trigger' => '0d'],
					'error' => 'Invalid parameter "/hk_events_trigger": value must be one of 86400-788400000.'
				]
			],
			// Parameter hk_events_trigger above maximum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_events_trigger' => '9126d'],
					'error' => 'Invalid parameter "/hk_events_trigger": value must be one of 86400-788400000.'
				]
			],
			// hk_history below minimum boundary (min is 3600 seconds = 1 hour).
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_history' => '3599s'],
					'error' => 'Invalid parameter "/hk_history": value must be one of 0, 3600-788400000.'
				]
			],
			// hk_history above maximum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_history' => '9126d'],
					'error' => 'Invalid parameter "/hk_history": value must be one of 0, 3600-788400000.'
				]
			],
			// hk_trends below minimum boundary (min is 86400 seconds = 1 day).
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_trends' => '3600s'],
					'error' => 'Invalid parameter "/hk_trends": value must be one of 0, 86400-788400000.'
				]
			],
			// hk_trends above maximum boundary.
			[
				[
					'expected' => TEST_BAD,
					'params' => ['hk_trends' => '9126d'],
					'error' => 'Invalid parameter "/hk_trends": value must be one of 0, 86400-788400000.'
				]
			],
			// Binary-flag fields must reject values outside {0, 1}.
			[['expected' => TEST_BAD, 'params' => ['hk_events_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_history_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_history_global' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_trends_mode' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['hk_trends_global' => '2']]],
			[['expected' => TEST_BAD, 'params' => ['compression_status' => '2']]],
			// Mode field must reject a non-numeric string value.
			[['expected' => TEST_BAD, 'params' => ['hk_events_mode' => 'wrong_type']]],
			// Unknown parameter must be rejected.
			[['expected' => TEST_BAD, 'params' => ['non_existing_param' => '123']]],
			// Non-numeric strings for time-period fields.
			[['expected' => TEST_BAD, 'params' => ['hk_events_trigger' => 'abc']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_service' => 'wrong']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_internal' => 'text']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_discovery' => 'test']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_autoreg' => 'abc']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services' => 'wrong']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit' => 'text']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions' => 'test']]],
			[['expected' => TEST_BAD, 'params' => ['hk_history' => 'text']]],
			[['expected' => TEST_BAD, 'params' => ['hk_trends' => '!!']]],
			// Unsupported time unit suffix for all time-period fields.
			[['expected' => TEST_BAD, 'params' => ['hk_events_trigger' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_service' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_internal' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_discovery' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_autoreg' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_history' => '10x']]],
			[['expected' => TEST_BAD, 'params' => ['hk_trends' => '10x']]],
			// Negative values for all time-period fields.
			[['expected' => TEST_BAD, 'params' => ['hk_events_trigger' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_service' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_internal' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_discovery' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_autoreg' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_history' => '-1d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_trends' => '-1d']]],
			// Out of range values for all time-period fields (below minimum 86400 = 1d).
			[['expected' => TEST_BAD, 'params' => ['hk_events_service' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_internal' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_discovery' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_autoreg' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit' => '0d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions' => '0d']]],
			// Out of range values for all time-period fields (above maximum 788400000 = 9125d).
			[['expected' => TEST_BAD, 'params' => ['hk_events_trigger' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_service' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_internal' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_discovery' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_events_autoreg' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_services' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_audit' => '9126d']]],
			[['expected' => TEST_BAD, 'params' => ['hk_sessions' => '9126d']]],
			// Parameter compress_older: negative value.
			[['expected' => TEST_BAD, 'params' => ['compress_older' => '-100']]]
		];
	}

	/**
	 * Verify permissions for housekeeping API methods.
	 */
	public function testHousekeeping_Permissions() {
		foreach (['user-zabbix', 'admin-zabbix'] as $user) {
			// Both user and admin must be allowed to get housekeeping settings.
			$this->authorize($user, 'zabbix');
			$response = $this->call('housekeeping.get', ['output' => 'extend']);
			$this->assertArrayHasKey('result', $response);

			// Both user and admin must NOT be allowed to update housekeeping settings.
			$this->call('housekeeping.update', ['hk_events_mode' => '1'],
				'No permissions to call "housekeeping.update".'
			);
		}
	}
}
