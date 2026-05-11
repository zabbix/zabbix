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


class CJsonRpc {

	const VERSION = '2.0';

	public const AUTH_TYPE_BEARER = 2;
	public const AUTH_TYPE_COOKIE = 3;
	public const AUTH_TYPE_DPOP = 4;

	public const HEADER_AUTHENTICATE_BEARER = 'Bearer';
	public const HEADER_AUTHENTICATE_DPOP = 'DPoP';

	/**
	 * API client to use for making requests.
	 *
	 * @var CApiClient
	 */
	protected $apiClient;

	private $_response;
	private $_error_list;
	private $_zbx2jsonErrors;
	private $_jsonDecoded;

	/**
	 * Constructor.
	 *
	 * @param CApiClient $apiClient
	 * @param string $data
	 */
	public function __construct(CApiClient $apiClient, $data) {
		$this->apiClient = $apiClient;

		$this->initErrors();

		$this->_response = [];
		$this->_jsonDecoded = json_decode($data, true);
	}

	public function execute(CHttpRequest $request): CHttpResponse {
		if (json_last_error()) {
			$this->jsonError([], '-32700', null, null, true);
			return new CHttpResponse(json_encode($this->_response[0], JSON_UNESCAPED_SLASHES));
		}

		if (!is_array($this->_jsonDecoded) || $this->_jsonDecoded === []) {
			$this->jsonError([], '-32600', null, null, true);
			return new CHttpResponse(json_encode($this->_response[0], JSON_UNESCAPED_SLASHES));
		}

		$auth_header = $request->getParsedAuthHeader();

		if ($auth_header['type'] === self::HEADER_AUTHENTICATE_BEARER) {
			$auth['type'] = self::AUTH_TYPE_BEARER;
			$auth['auth'] = $auth_header['auth'];
		}
		elseif ($auth_header['type'] === self::HEADER_AUTHENTICATE_DPOP) {
			$auth['type'] = self::AUTH_TYPE_DPOP;
			$auth['auth'] = $auth_header['auth'];
			$auth['sign'] = (string) $request->header(self::HEADER_AUTHENTICATE_DPOP);
		}
		else {
			$session = new CEncryptedCookieSession();

			$auth['type'] = self::AUTH_TYPE_COOKIE;
			$auth['auth'] = $session->extractSessionId();
		}

		$calls_data = [];

		$authentication_required = false;

		foreach (zbx_toArray($this->_jsonDecoded) as $call) {
			if (!$this->validate($call)) {
				continue;
			}

			[$api, $method] = explode('.', $call['method']) + [1 => ''];

			if (!$authentication_required &&
					(CLocalApiClient::requiresAuthentication($api, $method) ||
						(CLocalApiClient::supportsAuthentication($api, $method) && $auth['auth'] !== null))) {
				$authentication_required = true;
			}

			$calls_data[] = [
				'api' => $api,
				'method' => $method,
				'params' => $call['params'],
				'id' => $call['id']
			];
		}

		$authenticate_response = null;

		if ($authentication_required) {
			$authenticate_response = $this->authenticate($auth, $calls_data);
		}

		if ($authenticate_response === null || $authenticate_response->errorCode === null) {
			foreach ($calls_data as $call) {
				if ($auth['type'] !== CJsonRpc::AUTH_TYPE_COOKIE &&
						!CLocalApiClient::requiresAuthentication($call['api'], $call['method']) &&
						!CLocalApiClient::supportsAuthentication($call['api'], $call['method'])) {
					$result = new CApiClientResponse();

					$error = _('The "%1$s.%2$s" method must be called without authorization header.');

					$result->errorCode = ZBX_API_ERROR_PARAMETERS;
					$result->errorMessage = _params($error, [$call['api'], $call['method']]);
				}
				else {
					$result =
						$this->apiClient->callMethod($call['api'], $call['method'], $call['params'], $auth);
				}

				$this->processResult($call, $result);
			}
		}
		else {
			foreach ($calls_data as $call) {
				$this->processResult($call, $authenticate_response);
			}
		}

		$response = new CHttpResponse();

		$user_data = $this->apiClient->getUserData();

		if ($request->isHpkeHeadersRequested() && $user_data !== null && array_key_exists('uuid', $user_data)) {
			$response->headers[] = 'X-Recipient-ID: '.$user_data['uuid'];
			$response->headers[] = 'X-HPKE-Recipient-KID: '.$user_data['kid'];
			$response->headers[] = 'X-HPKE-Recipient-Pub: '.$user_data['key'];
		}

		if ($this->_response === array_fill(0, count($this->_response), null)) {
			return $response;
		}

		if (is_array($this->_jsonDecoded)
				&& array_keys($this->_jsonDecoded) === range(0, count($this->_jsonDecoded) - 1)) {
			// Return response as encoded batch if $this->_jsonDecoded is associative array.
			$response->body = json_encode(array_values(array_filter($this->_response)), JSON_UNESCAPED_SLASHES);
		}
		else {
			$response->body = ($this->_response[0] !== null)
				? json_encode($this->_response[0], JSON_UNESCAPED_SLASHES)
				: '';
		}

		return $response;
	}

	public function validate(&$call) {
		$api_input_rules = ['type' => API_OBJECT, 'fields' => [
			'jsonrpc' =>	['type' => API_STRING_UTF8, 'flags' => API_REQUIRED, 'in' => self::VERSION],
			'method' =>		['type' => API_STRING_UTF8, 'flags' => API_REQUIRED],
			'params' =>		['type' => API_JSONRPC_PARAMS, 'flags' => API_REQUIRED],
			'id' =>			['type' => API_JSONRPC_ID]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $call, '/', $error)) {
			$call_id = is_array($call) ? array_intersect_key($call, array_flip(['id'])) : [];
			$api_input_rules = ['type' => API_OBJECT, 'fields' => [
				'id' =>	['type' => API_JSONRPC_ID]
			]];

			if (!CApiInputValidator::validate($api_input_rules, $call_id, '', $err)) {
				$call_id = [];
			}

			$this->jsonError($call_id, '-32600', $error, null, true);

			return false;
		}

		return true;
	}

	public function authenticate(array $auth, array $calls_data): CApiClientResponse {
		global $NO_AUTH_DEBUG_MODE;

		$response = new CApiClientResponse();

		try {
			if ($auth['auth'] === null ||
					($auth['type'] == CJsonRpc::AUTH_TYPE_DPOP && !CTemporaryMobileFeatureHelper::isEnabled())) {
				throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'));
			}

			$user = match ($auth['type']) {
				CJsonRpc::AUTH_TYPE_BEARER => $this->apiClient->serviceFactory->getObject('user')->checkAuthentication(
					strlen($auth['auth']) == 64
						? ['token' => $auth['auth']]
						: ['sessionid' => $auth['auth']]
				),
				CJsonRpc::AUTH_TYPE_COOKIE => $this->apiClient->serviceFactory->getObject('user')->checkAuthentication([
					'sessionid' => $auth['auth']
				]),
				CJsonRpc::AUTH_TYPE_DPOP => $this->apiClient->serviceFactory->getObject('user')
					->checkAuthenticationDpop([
						'token' => $auth['auth'],
						'signature' => $auth['sign'],
						'requested_api_method' => self::makeRequestedApiMethod($calls_data)
					])
			};

			if (array_key_exists('debug_mode', $user)) {
				$this->apiClient->debug = $user['debug_mode'];
			}
		}
		catch (Exception $e) {
			if ($e instanceof APIException) {
				$response->errorCode = $e->getCode();
			}
			elseif ($e instanceof DBException) {
				$response->errorCode = ZBX_API_ERROR_DB;
			}
			else {
				$response->errorCode = ZBX_API_ERROR_INTERNAL;
			}

			$response->errorMessage = $e->getMessage();

			// add debug data
			if ($NO_AUTH_DEBUG_MODE) {
				$response->debug = $e->getTrace();

				if ($e instanceof APIException) {
					$response->errorMessage = $e->getDebugMessage();
				}
			}
		}

		return $response;
	}

	public static function makeRequestedApiMethod(array $calls_data): string {
		$api_methods = [];

		foreach ($calls_data as $call) {
			$api_methods[] = $call['api'].'.'.$call['method'];
		}

		return implode(',', $api_methods);
	}

	public function processResult(array $call, CApiClientResponse $response) {
		if ($response->errorCode) {
			$user_type = CUser::$userData === null ? USER_TYPE_ZABBIX_USER : CUser::$userData['type'];

			if ($response->errorCode == ZBX_API_ERROR_DB && $user_type != USER_TYPE_SUPER_ADMIN) {
				$response->errorMessage = _('System error occurred. Please contact Zabbix administrator.');
			}

			$errno = $this->_zbx2jsonErrors[$response->errorCode];

			$this->jsonError($call, $errno, $response->errorMessage, $response->debug);
		}
		else {
			// Notifications (request object without an "id" member) MUST NOT be answered.
			$this->_response[] = array_key_exists('id', $call)
				? [
					'jsonrpc' => self::VERSION,
					'result' => $response->data,
					'id' => $call['id']
				]
				: null;
		}
	}

	private function jsonError(array $call, $errno, $data = null, $debug = null, $force_err = false) {
		// Notifications MUST NOT be answered, but error MUST be generated on JSON parse error
		if (!$force_err && !array_key_exists('id', $call)) {
			$this->_response[] = null;
			return;
		}

		if (!array_key_exists($errno, $this->_error_list)) {
			$data = _s('JSON-RPC error generation failed. No such error "%1$s".', $errno);
			$errno = '-32400';
		}

		$error = $this->_error_list[$errno];

		if ($data !== null) {
			$error['data'] = $data;
		}

		if ($debug !== null) {
			$error['debug'] = $debug;
		}

		$this->_response[] = [
			'jsonrpc' => self::VERSION,
			'error' => $error,
			'id' => array_key_exists('id', $call) ? $call['id'] : null
		];
	}

	private function initErrors() {
		$this->_error_list = [
			'-32700' => [
				'code' => -32700,
				'message' => _('Parse error'),
				'data' => _('Invalid JSON. An error occurred on the server while parsing the JSON text.')
			],
			'-32600' => [
				'code' => -32600,
				'message' => _('Invalid request.'),
				'data' => _('The received JSON is not a valid JSON-RPC request.')
			],
			'-32601' => [
				'code' => -32601,
				'message' => _('Method not found.'),
				'data' => _('The requested remote-procedure does not exist / is not available')
			],
			'-32602' => [
				'code' => -32602,
				'message' => _('Invalid params.'),
				'data' => _('Invalid method parameters.')
			],
			'-32603' => [
				'code' => -32603,
				'message' => _('Internal error.'),
				'data' => _('Internal JSON-RPC error.')
			],
			'-32500' => [
				'code' => -32500,
				'message' => _('Application error.'),
				'data' => _('No details')
			],
			'-32400' => [
				'code' => -32400,
				'message' => _('System error.'),
				'data' => _('No details')
			],
			'-32300' => [
				'code' => -32300,
				'message' => _('Transport error.'),
				'data' => _('No details')
			]
		];

		$this->_zbx2jsonErrors = [
			ZBX_API_ERROR_NO_METHOD => '-32601',
			ZBX_API_ERROR_PARAMETERS => '-32602',
			ZBX_API_ERROR_NO_AUTH => '-32602',
			ZBX_API_ERROR_PERMISSIONS => '-32500',
			ZBX_API_ERROR_INTERNAL => '-32500',
			ZBX_API_ERROR_DB => '-32500',
			ZBX_API_ERROR_NO_ENTITY => '-32500'
		];
	}
}
