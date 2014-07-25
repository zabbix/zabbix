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
	 * Request parameters
	 *
	 * @var array
	 */
	protected $params;

	public function __construct($method, array $params = array(), $id = null, array $request = array()) {
		$this->method = $method;
		$this->params = $params;
		$this->id = is_null($id) ? rand() : $id;
		$this->version = isset($request['version']) ? $request['version'] : '2.0';
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
}
