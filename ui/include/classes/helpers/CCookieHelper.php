<?php declare(strict_types = 0);
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


class CCookieHelper {

	/**
	 * Check if cookie exists.
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public static function has(string $name): bool {
		return array_key_exists($name, $_COOKIE);
	}

	/**
	 * Get cookie.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public static function get(string $name) {
		return self::has($name) ? $_COOKIE[$name] : null;
	}

	/**
	 * Add cookie.
	 *
	 * @param string  $name
	 * @param string  $value
	 * @param integer $time
	 *
	 * @return boolean
	 */
	public static function set(string $name, string $value, int $time = 0): bool {
		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$path = rtrim(substr($path, 0, strrpos($path, '/')), '/');

		if (!setcookie($name, $value, $time, $path, '', HTTPS, true)) {
			return false;
		}

		$_COOKIE[$name] = $value;

		return true;
	}

	/**
	 * Delete cookie.
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public static function unset(string $name): bool {
		if (!setcookie($name, '', 0)) {
			return false;
		}

		unset($_COOKIE[$name]);

		return true;
	}

	/**
	 * Get all cookies.
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return $_COOKIE;
	}
}
