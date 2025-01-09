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


/**
 * Class for standard ajax response generation.
 */
class CAjaxResponse {

	private $_result = true;
	private $_data = [];
	private $_errors = [];

	public function __construct($data = null) {
		if ($data !== null) {
			$this->success($data);
		}
	}

	/**
	 * Add error to ajax response. All errors are returned as array in 'errors' part of response.
	 *
	 * @param string $error error text
	 */
	public function error($error) {
		$this->_result = false;
		$this->_errors[] = ['error' => $error];
	}

	/**
	 * Assigns data that is returned in 'data' part of ajax response.
	 * If any error was added previously, this method does nothing.
	 *
	 * @param array $data
	 */
	public function success(array $data) {
		if ($this->_result) {
			$this->_data = $data;
		}
	}

	/**
	 * Output ajax response. If any error was added, 'result' is false, otherwise true.
	 */
	public function send() {
		echo json_encode($this->_result
			? ['result' => true, 'data' => $this->_data]
			: ['result' => false, 'errors' => $this->_errors]
		);
	}
}
