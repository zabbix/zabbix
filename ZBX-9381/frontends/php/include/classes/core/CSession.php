<?php

class CSession implements ArrayAccess {

	/**
	 * Initialize session.
	 * Set cookie path to path to current URI without file.
	 *
	 * @throw Exception if cannot start session
	 */
	public function __construct() {
		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		// remove file name from path
		$path = substr($path, 0, strrpos($path, '/') + 1);

		session_set_cookie_params(0, $path, null, HTTPS);

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
