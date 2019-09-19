<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Represents API or request error.
 */
class CClientError {

	/**
	 * @var string
	 */
	public $reason;

	/**
	 * @var array
	 */
	public $data;

	protected function __construct(string $reason, array $data = []) {
		$this->reason = $reason;
		$this->data = $data;
	}

	/**
	 * @param string $body
	 *
	 * @return CClientError
	 */
	public static function json(string $body) {
		return new self('Server returned not json.', ['actual_response' => $body]);
	}

	/**
	 * @param array $resp  Full actual server response.
	 *
	 * @return CClientError
	 */
	public static function from_resp(array $resp) {
		return new self($resp['error']['message'], $resp['error']);
	}

	/**
	 * @return CClientError
	 */
	public static function no_resp() {
		return new self('Server returned no body.');
	}

	/**
	 * @return string
	 */
	public function __toString() {
		$details = '';
		foreach ($this->data as $key => $value) {
			$details .= $key.':'.$value.PHP_EOL;
		}

		return $this->reason.PHP_EOL.$details;
	}
}

