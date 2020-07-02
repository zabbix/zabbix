<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Helper to store succes / error messages.
 */
class CMessages {

	/**
	 * Messages array.
	 *
	 * @static
	 *
	 * @var array
	 */
	private static $msgs = [];
	/**
	 * Success message.
	 *
	 * @static
	 *
	 * @var string
	 */
	private static $success;
	/**
	 * Error message.
	 *
	 * @static
	 *
	 * @var string
	 */
	private static $error;

	/**
	 * Add messages.
	 *
	 * @static
	 *
	 * @param array $msg
	 *
	 * @return boolean
	 */
	public static function add(array $msg): bool {
		self::$msgs[] = $msg;

		return true;
	}

	/**
	 * Add success message.
	 *
	 * @static
	 *
	 * @param string $msg
	 *
	 * @return boolean
	 */
	public static function addSuccess(string $msg): bool {
		self::$success = $msg;

		return true;
	}

	/**
	 * Add error message.
	 *
	 * @static
	 *
	 * @param string $msg
	 *
	 * @return boolean
	 */
	public static function addError(string $msg): bool {
		self::$error = $msg;

		return true;
	}

	/**
	 * Get messages.
	 *
	 * @static
	 *
	 * @return array
	 */
	public static function get(): array {
		return self::$msgs;
	}

	/**
	 * Get success message.
	 *
	 * @static
	 *
	 * @return string|null
	 */
	public static function getSuccess(): ?string {
		return self::$success;
	}

	/**
	 * Get error message.
	 *
	 * @static
	 *
	 * @return string|null
	 */
	public static function getError(): ?string {
		return self::$error;
	}

	/**
	 * Clear messages.
	 *
	 * @static
	 *
	 * @return boolean
	 */
	public static function clear(): bool {
		self::$msgs = [];

		return true;
	}
}
