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
 * This class should be used for calling API services.
 */
abstract class CApiClient {

	/**
	 * @var CJson	a json parser for decoding/encoding JSON.
	 */
	private $json;

	/**
	 * @param CJson $json
	 */
	public function __construct(CJson $json) {
		$this->json = $json;
	}

	/**
	 * Call the given method and return the response.
	 *
	 * @param string 		$method		method to call
	 * @param mixed 		$params		method params
	 * @param string|null	$auth		authentication token
	 * @param string|null	$id			request id, if omitted it will be generated randomly
	 * @param string		$jsonRpc	JSON RPC version
	 *
	 * @return CApiResponse
	 */
	abstract public function callMethod($method, $params, $auth = null, $id = null, $jsonRpc = '2.0');

	/**
	 * Executed the JSON RPC request encoded as a JSON string.
	 *
	 * @param string $jsonString
	 *
	 * @return CApiResponse
	 */
	public function callJson($jsonString) {
		$request = $this->decode($jsonString);

		if (!$request) {
			return $this->createErrorResponse(-32700, _('Incorrect JSON string.'), null, null);
		}

		$request = array_merge(array(
			'id' => null,
			'method' => null,
			'params' => null,
			'auth' => null,
			'jsonrpc' => null
		), $request);

		return $this->callMethod($request['method'], $request['params'], $request['auth'], $request['id'], $request['jsonrpc']);
	}

	/**
	 * Returns true if calling the given method requires a valid authentication token.
	 *
	 * @param $method
	 *
	 * @return bool
	 */
	public function requiresAuthentication($method) {
		return !in_array(strtolower($method), array('user.login', 'user.checkauthentication', 'apiinfo.version'), true);
	}

	/**
	 * Encode array as a JSON string.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	protected function encode(array $data) {
		return $this->json->encode($data);
	}

	/**
	 * Decode JSON string as an array.
	 *
	 * @param string $json
	 *
	 * @return array
	 */
	protected function decode($json) {
		return $this->json->decode($json, true);
	}

	/**
	 * Create any response object.
	 *
	 * @param array 	$response
	 *
	 * @return CApiResponse
	 */
	protected function createResponse(array $response) {
		return new CApiResponse(
			isset($response['result']) ? $response['result'] : null,
			isset($response['error']) ? $response['error'] : null,
			$response['id'],
			$response['jsonrpc'],
			isset($response['debug']) ? $response['debug'] : null
		);
	}

	/**
	 * Create a response containing an error.
	 *
	 * @param string 	$errorCode
	 * @param string 	$errorData
	 * @param string	$id
	 * @param string	$version
	 * @param array 	$debug
	 *
	 * @return CApiResponse
	 */
	protected function createErrorResponse($errorCode, $errorData, $id, $version, array $debug = null) {
		return $this->createResponse(array(
			'error' => array(
				'code' => $errorCode,
				'message' => $this->getJsonRpcErrorMessage($errorCode),
				'data' => $errorData
			),
			'id' => $id,
			'jsonrpc' => $version,
			'debug' => $debug
		));
	}

	/**
	 * Create a response for a successfully executed request.
	 *
	 * @param mixed 	$result
	 * @param string 	$id
	 * @param string	$version
	 *
	 * @return CApiResponse
	 */
	protected function createResultResponse($result, $id, $version) {
		return $this->createResponse(array(
			'result' => $result,
			'id' => $id,
			'jsonrpc' => $version
		));
	}

	/**
	 * Get a JSON RPC error message for the given error code.
	 *
	 * @param int $jsonRpcErrorCode
	 *
	 * @return string
	 */
	protected function getJsonRpcErrorMessage($jsonRpcErrorCode) {
		$errors = array(
			'-32700' => _('Parse error'),
			'-32600' => _('Invalid Request.'),
			'-32601' => _('Method not found.'),
			'-32602' => _('Invalid params.'),
			'-32603' => _('Internal error.'),
			'-32500' => _('Application error.'),
			'-32400' => _('System error.'),
			'-32300' => _('Transport error.')
		);

		if (isset($errors[$jsonRpcErrorCode])) {
			return $errors[$jsonRpcErrorCode];
		}

		return _s('JSON-rpc error generation failed. No such error "%s".', $jsonRpcErrorCode);
	}
}
