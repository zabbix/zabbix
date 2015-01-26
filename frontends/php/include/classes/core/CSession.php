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


/**
 * Session wrapper, currently uses native PHP session.
 */
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

	/**
	 * Clears and implicitly flushes session.
	 */
	public function clear() {
		$_SESSION = array();
		session_write_close();
	}

	/**
	 * Sets session value by key offset.
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		$_SESSION[$offset] = $value;
	}

	/**
	 * Checks if session value exists (isset() calls).
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function offsetExists($offset) {
		return isset($_SESSION[$offset]);
	}

	/**
	 * Unsets session value (unset() calls).
	 *
	 * @param mixed $offset
	 */
	public function offsetUnset($offset) {
		unset($_SESSION[$offset]);
	}

	/**
	 * Returns value stored in session.
	 *
	 * @param mixed $offset
	 *
	 * @return mixed|null
	 */
	public function offsetGet($offset) {
		return isset($_SESSION[$offset]) ? $_SESSION[$offset] : null;
	}
}
