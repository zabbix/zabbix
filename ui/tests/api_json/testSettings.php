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

/**
 * @backup settings
 */
class testSettings extends CAPITest {

	public static function dataProviderUpdateValid() {
		yield '"timeout_telemetry_query" value integer in valid range' => [
			['timeout_telemetry_query' => 10],
			null
		];

		yield '"timeout_telemetry_query" value units in valid range' => [
			['timeout_telemetry_query' => '2m'],
			null
		];
	}

	public static function dataProviderUpdateInvalid() {
		yield '"timeout_telemetry_query" value empty string fail' => [
			['timeout_telemetry_query' => ''],
			'Invalid parameter "/timeout_telemetry_query": cannot be empty.'
		];

		yield '"timeout_telemetry_query" value integer less than valid range fail' => [
			['timeout_telemetry_query' => 0],
			'Invalid parameter "/timeout_telemetry_query": value must be one of 1-600.'
		];

		yield '"timeout_telemetry_query" value integer greater than valid range fail' => [
			['timeout_telemetry_query' => 601],
			'Invalid parameter "/timeout_telemetry_query": value must be one of 1-600.'
		];

		yield '"timeout_telemetry_query" value units less than valid range fail' => [
			['timeout_telemetry_query' => '0m'],
			'Invalid parameter "/timeout_telemetry_query": value must be one of 1-600.'
		];

		yield '"timeout_telemetry_query" value units greater than valid range fail' => [
			['timeout_telemetry_query' => '11m'],
			'Invalid parameter "/timeout_telemetry_query": value must be one of 1-600.'
		];
	}

	/**
	 * @dataProvider dataProviderUpdateValid
	 * @dataProvider dataProviderUpdateInvalid
	 */
	public function testSettingsUpdate(array $settings, ?string $expected_error) {
		$this->call('settings.update', $settings, $expected_error);
	}
}
