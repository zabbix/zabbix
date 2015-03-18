<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
?>
<?php
/**
 * Class for standard ajax response generation.
 */
class AjaxResponse {

	private $_result = true;
	private $_data = array();
	private $_errors = array();

	public function __construct($data = null) {
		if ($data !== null) {
			$this->success($data);
		}
	}

	/**
	 * Add error to ajax response. All errors are returned as array in 'errors' part of response.
	 *
	 * @param string $error error text
	 * @return void
	 */
	public function error($error) {
		$this->_result = false;
		$this->_errors[] = array('error' => $error);
	}

	/**
	 * Assigns data that is returned in 'data' part of ajax response.
	 * If any error was added previously, this method does nothing.
	 *
	 * @param array $data
	 * @return void
	 */
	public function success(array $data) {
		if ($this->_result) {
			$this->_data = $data;
		}
	}

	/**
	 * Output ajax response. If any error was added, 'result' is false, otherwise true.
	 *
	 * @return void
	 */
	public function send() {
		$json = new CJSON();

		if ($this->_result) {
			echo $json->encode(array('result' => true, 'data' => $this->_data));
		}
		else {
			echo $json->encode(array('result' => false, 'errors' => $this->_errors));
		}
	}
}
?>
