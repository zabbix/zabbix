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


/**
 * Session helper.
 */
class CSessionHelper {

	/**
	 * Clear session data.
	 *
	 * @return boolean
	 */
	public static function clear(): bool {
		return session_destroy();
	}

	/**
	 * Check has this key in session.
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
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set(string $key, $value): void {
		$_SESSION[$key] = $value;
	}

	/**
	 * Delete from session.
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
	 * @return string
	 */
	public static function getId(): string {
		return session_id();
	}

	/**
	 * Get all session data.
	 *
	 * @return array
	 */
	public static function getAll(): array {
		return $_SESSION;
	}
}
