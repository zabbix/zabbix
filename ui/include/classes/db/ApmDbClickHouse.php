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


class ApmDbClickHouse extends ApmDb {

	public function connect(): CurlHandle {
		$curl_options = [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_ENCODING => '',
			CURLOPT_USERPWD => sprintf('%s:%s', $this->settings['user'] !== null ? $this->settings['user'] : 'default',
				$this->settings['password'] !== null ? $this->settings['password'] : ''
			)
		];

		if ($this->settings['encryption']) {
			$curl_options[CURLOPT_SSL_VERIFYHOST] = $this->settings['verify_host'] ? 2 : 0;

			if ($this->settings['ca_file'] !== null) {
				$curl_options[CURLOPT_SSL_VERIFYPEER] = true;
				$curl_options[CURLOPT_CAINFO] = $this->settings['ca_file'];
			}

			if ($this->settings['cert_file'] !== null) {
				$curl_options[CURLOPT_SSLCERT] = $this->settings['cert_file'];
			}

			if ($this->settings['key_file'] !== null) {
				$curl_options[CURLOPT_SSLKEY] = $this->settings['key_file'];
			}

			if ($this->settings['cipher_list'] !== null) {
				$curl_options[CURLOPT_SSL_CIPHER_LIST] = $this->settings['cipher_list'];
			}

			$scheme = 'https';
			$port = $this->settings['port'] == 0 ? 8443 : $this->settings['port'];
		}
		else {
			$scheme = 'http';
			$port = $this->settings['port'] == 0 ? 8123 : $this->settings['port'];
		}

		$database = $this->settings['database'] === null ? 'default' : rawurlencode($this->settings['database']);

		$curl_options[CURLOPT_URL] =
			sprintf('%s://%s:%d/?database=%s', $scheme, $this->settings['server'], $port, $database);

		$ch = curl_init();

		curl_setopt_array($ch, $curl_options);

		return $ch;
	}

	public function select(string $sql): array {
		$sql .= ' FORMAT JSON';

		$ch = $this->getConnection();

		curl_setopt($ch, CURLOPT_POSTFIELDS, $sql);

		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			curl_close($ch);

			throw new Exception(_('ClickHouse connection failed'));
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		$_response = json_decode($response, true);

		if ($http_code != 200) {
			$error_message = is_array($_response) ? $_response['exception'] : $response;

			throw new Exception($error_message);
		}

		return $_response;
	}

	public function test(): bool {
		try {
			$response = $this->select('SELECT 1');

			return isset($response['data'][0]['1']) && $response['data'][0]['1'] == 1;
		}
		catch (Exception $e) {
			return false;
		}
	}
}
