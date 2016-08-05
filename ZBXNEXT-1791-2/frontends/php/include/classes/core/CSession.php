<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
class CSession {

	/**
	 * Flag indicating if session is created.
	 *
	 * @var CSession
	 */
	protected static $session_created = false;

	/**
	 * Initialize session.
	 * Set cookie path to path to current URI without file.
	 *
	 * @throw Exception if cannot start session
	 */
	public static function start() {
		if (!self::$session_created) {
			$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
			// remove file name from path
			$path = substr($path, 0, strrpos($path, '/') + 1);

			ob_start();
			session_set_cookie_params(0, $path, null, HTTPS);

			if (!session_start()) {
				throw new Exception('Cannot start session.');
			}

			session_write_close();
			ob_flush();
			self::$session_created = true;
		}
	}

	/**
	 * Clears and implicitly flushes session.
	 */
	public static function clear() {
		self::open();
		$_SESSION = [];
		self::close();
	}

	/**
	 * Sets session value by key.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 */
	public static function setValue($key, $value) {
		self::open();
		$_SESSION[$key] = $value;
		self::close();
	}

	/**
	 * Returns value stored in session.
	 *
	 * @param mixed $key
	 *
	 * @return mixed|null
	 */
	public static function getValue($key) {
		self::open();
		$result = array_key_exists($key, $_SESSION) ? $_SESSION[$key] : null;
		self::close();

		return $result;
	}

	/**
	 * Checks if session value exists (isset() calls).
	 *
	 * @param mixed $key
	 *
	 * @return bool
	 */
	public static function keyExists($key) {
		self::open();
		$result = array_key_exists($key, $_SESSION);
		self::close();

		return $result;
	}

	/**
	 * Unsets session value (unset() calls).
	 *
	 * @param array $keys
	 */
	public static function unsetValue(array $keys) {
		self::open();
		foreach ($keys as $key) {
			unset($_SESSION[$key]);
		}
		self::close();
	}

	/**
	 * Destroy session
	 */
	public static function destroy() {
		self::open();
		session_destroy();
		self::close();
	}

	/**
	 * Open session for writing
	 */
	private static function open() {
		session_start();
	}

	/**
	 * Close session for writing
	 */
	private static function close() {
		session_write_close();
	}
}
