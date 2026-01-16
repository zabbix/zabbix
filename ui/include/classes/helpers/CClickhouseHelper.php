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


/**
 * A helper class for working with Clickhouse.
 */
class CClickhouseHelper {

	public static function fetch(string $sql, string $endpoint, string $username, string $password): false|array {
		$response = self::request($endpoint, $username, $password, $sql.' FORMAT JSON');

		return $response === false ? false : json_decode($response, true)['data'];
	}

	public static function execute(string $sql, string $endpoint, string $username, string $password): bool {
		$response = self::request($endpoint, $username, $password, $sql);

		return $response === false ? false : true;
	}

	private static function request($endpoint, $username, $password, $sql) {
		$time_start = microtime(true);

		$handle = curl_init();

		curl_setopt_array($handle, [
			CURLOPT_URL => $endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
			CURLOPT_USERPWD => $username.':'.$password,
			CURLOPT_POSTFIELDS => $sql,
			CURLOPT_TIMEOUT => 10
		]);

		$response = curl_exec($handle);

		if (curl_errno($handle)) {
			error(_('ClickHouse connection failed.'));

			curl_close($handle);

			return false;
		}

		$http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

		curl_close($handle);

		if ($http_code != 200) {
			$_response = json_decode($response, true);

			$error_message = is_array($_response) && array_key_exists('exception', $_response)
				? $_response['exception']
				: $response;

			error(_s('ClickHouse error: %1$s.', $error_message));

			return false;
		}

		CProfiler::getInstance()->profileClickhouse(microtime(true) - $time_start, 'POST', $endpoint, $sql);

		return $response;
	}
}
