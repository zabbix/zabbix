<?php

class CSession implements ArrayAccess {

	public function __construct() {
		session_set_cookie_params(0, parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
		if (!session_start()) {
			throw new Exception('Cannot start session.');
		}
	}

	public function offsetSet($offset, $value) {
		$_SESSION[$offset] = $value;
	}

	public function offsetExists($offset) {
		return isset($_SESSION[$offset]);
	}

	public function offsetUnset($offset) {
		unset($_SESSION[$offset]);
	}

	public function offsetGet($offset) {
		return isset($_SESSION[$offset]) ? $_SESSION[$offset] : null;
	}
}
