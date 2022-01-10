<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Cookie helper.
 */
class CCookieHelper {

	/**
	 * Check cookie exists.
	 *
	 * @static
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public static function has(string $name): bool {
		return array_key_exists($name, $_COOKIE);
	}

	/**
	 * Get cookie value.
	 *
	 * @static
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
	 * @static
	 *
	 * @param string  $name
	 * @param string  $value
	 * @param integer $time
	 *
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public static function set(string $name, string $value, int $time = 0): bool {
		if (headers_sent()) {
			throw new \Exception(_('Headers already sent.'));
		}

		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$path = rtrim(substr($path, 0, strrpos($path, '/')), '/');

		if (mb_strlen($value) === 0) {
			throw new \Exception(_('Value cannot be empty.'));
		}

		if (!setcookie($name, $value, $time, $path, '', HTTPS, true)) {
			return false;
		}

		$_COOKIE[$name] = $value;

		return true;
	}

	/**
	 * Delete cookie.
	 *
	 * @static
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public static function unset(string $name): bool {
		if (!self::has($name)) {
			return false;
		}

		if (headers_sent()) {
			throw new \Exception(_('Headers already sent.'));
		}

		unset($_COOKIE[$name]);

		return setcookie($name, '', 0);
	}

	/**
	 * Get all cookies.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return $_COOKIE;
	}
}
