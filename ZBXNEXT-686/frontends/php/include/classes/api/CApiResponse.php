<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * This class is used by the API client to return the results of an API call.
 */
class CApiResponse {

	/**
	 * Result returned by the API.
	 *
	 * @var string
	 */
	protected $result;

	/**
	 * Error returned by the API.
	 *
	 * @var array
	 */
	protected $error;

	/**
	 * ID of the request.
	 *
	 * @var
	 */
	protected $id;

	/**
	 * JSON RPC version.
	 *
	 * @var string
	 */
	protected $jsonRpc;

	/**
	 * Debug information.
	 *
	 * @var array
	 */
	protected $debug;

	/**
	 * @param mixed 		$result		result returned by the API
	 * @param array|null 	$error		error returned by the API
	 * @param string 		$id			ID of the request
	 * @param string		$jsonRpc	JSON RPC version
	 * @param array|null 	$debug		debug information
	 */
	public function __construct($result, array $error = null, $id, $jsonRpc, array $debug = null) {
		$this->jsonRpc = $jsonRpc;
		$this->result = $result;
		$this->error = $error;
		$this->id = $id;
		$this->debug = $debug;
	}

	/**
	 * @return mixed
	 */
	public function getResult() {
		return $this->result;
	}

	/**
	 * @return array|null
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * @return mixed
	 */
	public function getDebug() {
		return $this->debug;
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function getJsonRpc() {
		return $this->jsonRpc;
	}

	/**
	 * Returns true if the response contains an error, false otherwise.
	 *
	 * @return bool
	 */
	public function isError() {
		return (bool) $this->error;
	}

	/**
	 * Returns the detailed error message.
	 *
	 * @return string|null
	 */
	public function getErrorData() {
		return $this->isError() ? $this->error['data'] : null;
	}

	/**
	 * Returns the error code.
	 *
	 * @return int|null
	 */
	public function getErrorCode() {
		return $this->isError() ? $this->error['code'] : null;
	}

	/**
	 * Returns the short error message.
	 *
	 * @return string|null
	 */
	public function getErrorMessage() {
		return $this->isError() ? $this->error['message'] : null;
	}

	/**
	 * If the request has been executed successfully, return the contents of "result",
	 * otherwise return the contents of "error".
	 *
	 * @return mixed
	 */
	public function getResponseData() {
		return ($this->isError()) ? $this->error : $this->result;
	}

	/**
	 * Return the body of the response as a JSON-RPC array.
	 *
	 * @return array
	 */
	public function getBody() {
		$body = array(
			'jsonrpc' => $this->jsonRpc,
			'id' => $this->id
		);

		if ($this->isError()) {
			$body['error'] = $this->error;

			if ($this->debug !== null) {
				$body['debug'] = $this->debug;
			}
		}
		else {
			$body['result'] = $this->result;
		}

		return $body;
	}
}
