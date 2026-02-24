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

	/**
	 * Query CickHouse
	 *
	 * @param string $query    SQL like query to be sent.
	 * @param array  $storage  ClickHouse storage configuration.
	 */
	public static function query(string $query, array $storage): ?array {
		$url = (new CUrl($storage['url']))
			->setArgument('database', $storage['db'])
			->setArgument('default_format', 'JSON')
			->getUrl();

		$handle = curl_init();

		curl_setopt_array($handle, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
			CURLOPT_USERPWD => $storage['username'].':'.$storage['password'],
			CURLOPT_POSTFIELDS => $query,
			CURLOPT_TIMEOUT => 10
		]);

		$result = null;
		$time_start = microtime(true);
		$response = curl_exec($handle);

		if (curl_errno($handle)) {
			error(_('ClickHouse connection failed.'));
		}
		else {
			$result = json_decode($response, true);

			if (curl_getinfo($handle, CURLINFO_HTTP_CODE) != 200) {
				$error_message = is_array($result) && array_key_exists('exception', $result)
					? $result['exception']
					: $response;

				error(_s('ClickHouse error: %1$s.', $error_message), true);
			}
		}

		CProfiler::getInstance()->profileClickhouse(microtime(true) - $time_start, 'POST', $url, $query);
		unset($handle);

		return $result !== null ? $result['data'] : null;
	}
}
