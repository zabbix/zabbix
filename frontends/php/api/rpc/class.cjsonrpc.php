<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CJSONrpc {
	const VERSION = '2.0';

	public $json;

	private $_multicall;
	private $_error;
	private $_response;
	private $_error_list;
	private $_zbx2jsonErrors;
	private $_jsonDecoded;

	public function __construct($jsonData) {
		$this->json = new CJSON();
		$this->initErrors();

		$this->_multicall = false;
		$this->_error = false;
		$this->_response = array();

		$this->_jsonDecoded = $this->json->decode($jsonData, true);
		if (!$this->_jsonDecoded) {
			$this->jsonError(null, '-32700', null, null, true);
			return;
		}

		if (!isset($this->_jsonDecoded['jsonrpc'])) {
			$this->multicall = true;
		}
		else {
			$this->_jsonDecoded = array($this->_jsonDecoded);
		}

	}

	public function execute($encoded = true) {
		foreach ($this->_jsonDecoded as $call) {
			// Notification
			if (!isset($call['id'])) {
				$call['id'] = null;
			}

			if (!$this->validate($call)) {
				continue;
			}

			$params = isset($call['params']) ? $call['params'] : null;
			$auth = isset($call['auth']) ? $call['auth'] : null;

			$result = czbxrpc::call($call['method'], $params, $auth);
			$this->processResult($call, $result);
		}

		if (!$encoded) {
			return $this->_response;
		}
		else {
			return $this->json->encode($this->_response);
		}
	}

	public function validate($call) {
		if (!isset($call['jsonrpc'])) {
			$this->jsonError($call['id'], '-32600', _('JSON-rpc version is not specified.'), null, true);
			return false;
		}

		if ($call['jsonrpc'] != self::VERSION) {
			$this->jsonError($call['id'], '-32600', _s('Expecting JSON-rpc version 2.0, "%s" is given.', $call['jsonrpc']), null, true);
			return false;
		}

		if (!isset($call['method'])) {
			$this->jsonError($call['id'], '-32600', _('JSON-rpc method is not defined.'));
			return false;
		}

		if (isset($call['params']) && !is_array($call['params'])) {
			$this->jsonError($call['id'], '-32602', _('JSON-rpc params is not an Array.'));
			return false;
		}

		return true;
	}

	public function processResult($call, $result) {
		if (isset($result['result'])) {
			// Notifications MUST NOT be answered
			if ($call['id'] === null) {
				return;
			}

			$formedResp = array(
				'jsonrpc' => self::VERSION,
				'result' => $result['result'],
				'id' => $call['id']
			);

			if ($this->multicall) {
				$this->_response[] = $formedResp;
			}
			else {
				$this->_response = $formedResp;
			}
		}
		else {
			$result['data'] = isset($result['data']) ? $result['data'] : null;
			$result['debug'] = isset($result['debug']) ? $result['debug'] : null;
			$errno = $this->_zbx2jsonErrors[$result['error']];

			$this->jsonError($call['id'], $errno, $result['data'], $result['debug']);
		}
	}

	public function isError() {
		return $this->_error;
	}

	private function jsonError($id, $errno, $data = null, $debug = null, $force_err = false) {
		// Notifications MUST NOT be answered, but error MUST be generated on JSON parse error
		if (is_null($id) && !$force_err) {
			return;
		}

		$this->_error = true;

		if (!isset($this->_error_list[$errno])) {
			$data = _s('JSON-rpc error generation failed. No such error "%s".', $errno);
			$errno = '-32400';
		}

		$error = $this->_error_list[$errno];

		if (!is_null($data)) {
			$error['data'] = $data;
		}
		if (!is_null($debug)) {
			$error['debug'] = $debug;
		}


		$formed_error = array(
			'jsonrpc' => self::VERSION,
			'error' => $error,
			'id' => $id
		);

		if ($this->multicall) {
			$this->_response[] = $formed_error;
		}
		else {
			$this->_response = $formed_error;
		}
	}

	private function initErrors() {
		$this->_error_list = array(
			'-32700' => array(
				'code' => -32700,
				'message' => _('Parse error'),
				'data' => _('Invalid JSON. An error occurred on the server while parsing the JSON text.')
			),
			'-32600' => array(
				'code' => -32600,
				'message' => _('Invalid Request.'),
				'data' => _('The received JSON is not a valid JSON-RPC Request.')
			),
			'-32601' => array(
				'code' => -32601,
				'message' => _('Method not found.'),
				'data' => _('The requested remote-procedure does not exist / is not available')
			),
			'-32602' => array(
				'code' => -32602,
				'message' => _('Invalid params.'),
				'data' => _('Invalid method parameters.')
			),
			'-32603' => array(
				'code' => -32603,
				'message' => _('Internal error.'),
				'data' => _('Internal JSON-RPC error.')
			),
			'-32500' => array(
				'code' => -32500,
				'message' => _('Application error.'),
				'data' => _('No details')
			),
			'-32400' => array(
				'code' => -32400,
				'message' => _('System error.'),
				'data' => _('No details')
			),
			'-32300' => array(
				'code' => -32300,
				'message' => _('Transport error.'),
				'data' => _('No details')
			)
		);

		$this->_zbx2jsonErrors = array(
			ZBX_API_ERROR_NO_METHOD => '-32601',
			ZBX_API_ERROR_PARAMETERS => '-32602',
			ZBX_API_ERROR_NO_AUTH => '-32602',
			ZBX_API_ERROR_PERMISSIONS => '-32500',
			ZBX_API_ERROR_INTERNAL => '-32500',
		);
	}
}
