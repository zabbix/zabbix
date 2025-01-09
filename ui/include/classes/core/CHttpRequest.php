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
 * Access the HTTP Request
 */
class CHttpRequest {

	/**
	 * additional HTTP headers not prefixed with HTTP_ in $_SERVER superglobal
	 */
	private $add_headers = ['CONTENT_TYPE', 'CONTENT_LENGTH', 'Authorization', 'PATH-INFO'];

	private $body;
	private $method;
	private $protocol;
	private $request_method;
	private $headers;
	private $raw;

	/**
	 * Retrieve HTTP Body
	 * @param Array Additional Headers to retrieve
	 */
	public function __construct($add_headers = false) {
		$this->retrieve_headers($add_headers);
		$this->body = @file_get_contents('php://input');
	}

	/**
	 * Retrieve the HTTP request headers from the $_SERVER superglobal
	 * @param Array Additional Headers to retrieve
	 */
	public function retrieve_headers($add_headers = false) {
		if ($add_headers) {
			$this->add_headers = array_merge($this->add_headers, $add_headers);
		}

		if (isset($_SERVER['HTTP_METHOD'])) {
			$this->method = $_SERVER['HTTP_METHOD'];
			unset($_SERVER['HTTP_METHOD']);
		}
		else {
			$this->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : false;
		}

		$this->protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : false;
		$this->request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : false;

		$this->headers = [];

		if (function_exists('getallheaders')) {
			$headers = getallheaders();

			if (array_key_exists('Authorization', $headers)) {
				$this->headers['AUTHORIZATION'] = $headers['Authorization'];
			}
		}

		foreach ($_SERVER as $i => $val) {
			if (strpos($i, 'HTTP_') === 0 || in_array($i, $this->add_headers)) {
				$name = str_replace(['HTTP_', '_'], ['', '-'], $i);
				$this->headers[strtoupper($name)] = $val;
			}
		}
	}

	/**
	 * Retrieve HTTP Method
	 */
	public function method() {
		return $this->method;
	}

	/**
	 * Retrieve HTTP Body
	 */
	public function body() {
		return $this->body;
	}

	/**
	 * Retrieve an HTTP Header
	 * @param string Case-Insensitive HTTP Header Name (eg: "User-Agent")
	 */
	public function header($name) {
		$name = strtoupper($name);
		return isset($this->headers[$name]) ? $this->headers[$name] : false;
	}

	/**
	 * Retrieve all HTTP Headers
	 * @return array HTTP Headers
	 */
	public function headers() {
		return $this->headers;
	}

	/**
	 * Get authentication header bearer value, return null when no authentication header exists or
	 * authentication method is not bearer type.
	 *
	 * @return string|null
	 */
	public function getAuthBearerValue() {
		$auth = $this->header('AUTHORIZATION');

		if (is_string($auth) && substr($auth, 0, 7) === ZBX_API_HEADER_AUTHENTICATE_PREFIX) {
			return substr($auth, 7);
		}

		return null;
	}

	/**
	 * Get request PATH-INFO segment by index. Return empty string if non-existing index requested.
	 *
	 * @param int $index  PATH-INFO segment index.
	 *
	 * @return string
	 */
	public function getPathInfoSegment(int $index): string {
		$pathinfo_segments = explode('/', substr($this->header('PATH-INFO'), 1));
		return array_key_exists($index, $pathinfo_segments) ? $pathinfo_segments[$index] : '';
	}

	/**
	 * Get argument passed in $_GET. Returns default value when argument not set.
	 *
	 * @param string $name     Argument's name.
	 * @param mixed  $default  Default value to return when requested argument not set.
	 *
	 * @return mixed
	 */
	public function getUrlArgument(string $name, $default = null) {
		return array_key_exists($name, $_GET) ? $_GET[$name] : $default;
	}

	/**
	 * Checks if argument exists in $_GET request.
	 *
	 * @param string $name  Argument's name.
	 *
	 * @return bool
	 */
	public function hasUrlArgument(string $name): bool {
		return array_key_exists($name, $_GET);
	}

	/**
	 * Return Raw HTTP Request (note: This is incomplete)
	 * @param bool ReBuild the Raw HTTP Request
	 */
	public function raw($refresh = false) {
		if (isset($this->raw) && !$refresh) {
			return $this->raw; // return cached
		}

		$headers = $this->headers();
		$this->raw = $this->method."\r\n";

		foreach ($headers as $i=>$header) {
			$this->raw .= $i.': '.$header."\r\n";
		}

		$this->raw .= "\r\n".$this->body;

		return $this->raw;
	}
}
