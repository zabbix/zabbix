<?php

namespace Zabbix\Test;

class APITestRequest {
	/**
	 * JSON-RPC version
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * JSON-RPC method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * Id of a request.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Current API auth token
	 *
	 * @var string
	 */
	protected $token;

	/**
	 * Method names that does not require authorization
	 *
	 * @var array
	 */
	protected $publicMethods = array(
		'user.login'
	);

	/**
	 * Request parameters
	 *
	 * @var array
	 */
	protected $params;

	public function __construct($method, $params = array(), $id = null, array $request = array()) {
		$this->method = $method;
		$this->params = $params;
		$this->id = is_null($id) ? rand() : $id;
		$this->version = array_key_exists('version', $request) ? $request['version'] : '2.0';
		$this->token = isset($request['token']) ? $request['token'] : null;
	}

	public function getId() {
		return $this->id;
	}

	public function getMethod() {
		return $this->method;
	}

	public function getParams() {
		return $this->params;
	}

	public function isSecure() {
		return !in_array($this->method, $this->publicMethods);
	}

	public function setToken($token) {
		$this->token = $token;
	}

	public function getToken() {
		return $this->token;
	}

	public function getBody() {
		$body = array(
			'method' => $this->method,
			'params' => $this->params,
			'id' => $this->id,
			'jsonrpc' => $this->version
		);

		if ($this->token) {
			$body['auth'] = $this->token;
		}

		return json_encode($body);
	}

}
