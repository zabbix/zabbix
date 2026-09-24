<?php declare(strict_types = 0);
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


class ApmDbClickHouse {

	private static array $instances = [];

	private array $config;
	private CurlShareHandle $curl_share;

	private function __construct(array $config) {
		if (!extension_loaded('curl')) {
			throw new DBException(_('PHP cURL extension is not available. '), DB::INIT_ERROR);
		}

		$this->config = $config;
		$this->curl_share = curl_share_init();

		curl_share_setopt($this->curl_share, CURLSHOPT_SHARE, CURL_LOCK_DATA_CONNECT);
		curl_share_setopt($this->curl_share, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
		curl_share_setopt($this->curl_share, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
	}

	/**
	 * @throws DBException
	 */
	public static function getInstance(array $config): self {
		foreach (self::$instances as $instance) {
			if ($instance->config == $config) {
				return $instance;
			}
		}

		$instance = new self($config);

		self::$instances[] = $instance;

		return $instance;
	}

	/**
	 * @throws DBException
	 */
	public function fetch(string $sql, array $params = []): Generator {
		$curl = curl_init();

		$this->configureCurl($curl, $sql, $params);

		$http_response_validated = false;
		$http_error = false;
		$buffer = '';
		$rows = [];

		curl_setopt_array($curl, [
			CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $header)
					use (&$http_response_validated, &$http_error): int {
				self::processResponseHeader($curl, $header, $http_response_validated, $http_error);

				return strlen($header);
			},
			CURLOPT_WRITEFUNCTION => static function (CurlHandle $curl, string $data) use (&$buffer, &$rows): int {
				self::processResponseBody($data, $buffer, $rows);

				return strlen($data);
			}
		]);

		$curl_multi = curl_multi_init();

		curl_multi_add_handle($curl_multi, $curl);

		$row_key = 0;

		try {
			do {
				$result = curl_multi_exec($curl_multi, $still_running);

				if ($result != CURLM_OK) {
					throw new DBException(
						_s('Unable to load data from ClickHouse: %1$s.', curl_multi_strerror($result)), DB::INIT_ERROR
					);
				}

				if ($http_error) {
					throw new DBException(_('Database error occurred.'), DB::DBEXECUTE_ERROR);
				}

				if ($http_response_validated) {
					while (array_key_exists($row_key, $rows)) {
						$row = $rows[$row_key];

						unset($rows[$row_key]);

						$row_key++;

						if ($row !== '') {
							yield self::decodeRow($row);
						}
					}
				}

				if ($still_running != 0) {
					if (curl_multi_select($curl_multi) == -1) {
						usleep(1000);
					}
				}
			}
			while ($still_running != 0);

			$curl_info = curl_multi_info_read($curl_multi);

			if ($curl_info !== false && $curl_info['result'] != CURLE_OK) {
				throw new DBException(
					_s('Unable to load data from ClickHouse: %1$s.', curl_strerror($curl_info['result'])),
					DB::INIT_ERROR
				);
			}

			if (!$http_response_validated) {
				throw new DBException(_('Database error occurred.'), DB::DBEXECUTE_ERROR);
			}

			if ($buffer !== '') {
				yield self::decodeRow($buffer);
			}
		}
		finally {
			curl_multi_remove_handle($curl_multi, $curl);
			curl_multi_close($curl_multi);
			curl_close($curl);
		}
	}

	private function configureCurl(CurlHandle $curl, string $sql, array $params): void {
		$curl_options = [
			CURLOPT_POST => true,
			CURLOPT_URL => $this->buildUrl(),
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_SHARE => $this->curl_share,
			CURLOPT_SSL_VERIFYPEER => $this->config['ssl_verify_peer'],
			CURLOPT_SSL_VERIFYHOST => $this->config['ssl_verify_host'] ? 2 : 0,
			CURLOPT_HTTPHEADER => ['X-ClickHouse-Format: JSONEachRow']
		];

		if ($params) {
			$post_fields = ['query' => $sql];

			foreach ($params as $name => $value) {
				$post_fields['param_'.$name] = self::encodeParamValue($value);
			}

			$curl_options[CURLOPT_POSTFIELDS] = $post_fields;
		}
		else {
			$curl_options[CURLOPT_POSTFIELDS] = $sql;
		}

		if ($this->config['username'] !== '') {
			if ($this->config['password'] !== '') {
				$curl_options += [
					CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
					CURLOPT_USERPWD  => $this->config['username'] . ':' . $this->config['password']
				];
			}
			else {
				$curl_options[CURLOPT_URL] .= '?' . http_build_query(['user' => $this->config['username']]);
			}
		}

		if ($this->config['ssl_ca_file'] !== '') {
			$curl_options[CURLOPT_CAINFO] = $this->config['ssl_ca_file'];
		}

		if ($this->config['ssl_ca_location'] !== '') {
			$curl_options[CURLOPT_CAPATH] = $this->config['ssl_ca_location'];
		}

		if ($this->config['ssl_cert_file'] !== '') {
			$curl_options[CURLOPT_SSLCERT] = $this->config['ssl_cert_file'];
		}

		if ($this->config['ssl_key_file'] !== '') {
			$curl_options[CURLOPT_SSLKEY] = $this->config['ssl_key_file'];
		}

		if ($this->config['ssl_key_password'] !== '') {
			$curl_options[CURLOPT_KEYPASSWD] = $this->config['ssl_key_password'];
		}

		curl_setopt_array($curl, $curl_options);
	}

	private function buildUrl(): string {
		if ($this->config['db'] === '') {
			return $this->config['url'];
		}

		return (new CUrl($this->config['url']))->setArgument('database', $this->config['db'])->getUrl();
	}

	/**
	 * @throws DBException
	 */
	private static function encodeParamValue(mixed $value): string {
		if (!is_array($value)) {
			return self::encodeScalarParamValue($value);
		}

		$values = [];

		foreach ($value as $item) {
			if (is_array($item)) {
				throw new DBException(
					_('Nested arrays are not supported in ClickHouse query parameters.'),
					DB::DBEXECUTE_ERROR
				);
			}

			$values[] = is_string($item) ? "'".self::escapeString($item)."'" : self::encodeScalarParamValue($item);
		}

		return '['.implode(',', $values).']';
	}

	/**
	 * @throws DBException
	 */
	private static function encodeScalarParamValue(mixed $value): string {
		if (is_string($value)) {
			return self::escapeString($value);
		}

		if (is_bool($value)) {
			return $value ? 'TRUE' : 'FALSE';
		}

		if ($value === null) {
			return '\N';
		}

		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		throw new DBException(_('Unsupported ClickHouse query parameter type.'), DB::DBEXECUTE_ERROR);
	}

	private static function escapeString(string $value): string {
		return strtr($value, ['\\' => '\\\\', "'" => "\\'", "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
	}

	private static function processResponseHeader(CurlHandle $curl, string $header, bool &$http_response_validated,
			bool &$http_error): void {
		if ($header === "\r\n") {
			$http_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

			if ($http_code == 200) {
				$http_response_validated = true;
			}
			elseif ($http_code > 200) {
				$http_error = true;
			}
		}
	}

	private static function processResponseBody(string $data, string &$buffer, array &$rows): void {
		$buffer .= $data;

		$new_line_position = strpos($buffer, "\n");

		while ($new_line_position !== false) {
			$rows[] = substr($buffer, 0, $new_line_position);

			$buffer = substr($buffer, $new_line_position + 1);

			$new_line_position = strpos($buffer, "\n");
		}
	}

	/**
	 * @throws DBException
	 */
	private static function decodeRow(string $row): array {
		$result = json_decode($row, true);

		if (json_last_error() != JSON_ERROR_NONE) {
			throw new DBException(_('Unable to decode data received from ClickHouse.'), DB::DBEXECUTE_ERROR);
		}

		return $result;
	}
}
