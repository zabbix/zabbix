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
 * Session helper.
 */
class CSessionHelper {

	/**
	 * Clear session data.
	 *
	 * @static
	 *
	 * @return boolean
	 */
	public static function clear(): bool {
		return session_destroy();
	}

	/**
	 * Check has this key in session.
	 *
	 * @static
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public static function has(string $key): bool {
		return array_key_exists($key, $_SESSION);
	}

	/**
	 * Get session data.
	 *
	 * @static
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public static function get(string $key) {
		return self::has($key) ? $_SESSION[$key] : null;
	}

	/**
	 * Add to session.
	 *
	 * @static
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set(string $key, $value): void {
		$_SESSION[$key] = $value;
	}

	/**
	 * Delete from session.
	 *
	 * @static
	 *
	 * @param array $keys
	 */
	public static function unset(array $keys): void {
		foreach ($keys as $key) {
			unset($_SESSION[$key]);
		}
	}

	/**
	 * Get session id.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function getId(): string {
		return session_id();
	}

	/**
	 * Get all session data.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return $_SESSION;
	}
}
