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
 * Helper class for sign / encrypt data.
 */
class CEncryptHelper {

	/**
	 * Signature algorithm.
	 */
	public const SIGN_ALGO = 'aes-256-ecb';

	/**
	 * Session secret key.
	 *
	 * @static
	 *
	 * @var string
	 */
	private static $key;

	/**
	 * Return session key.
	 *
	 * @static
	 *
	 * @return string
	 */
	private static function getKey(): string {
		if (!self::$key) {
			$config = select_config();
			if (!array_key_exists('session_key', $config)) {
				throw new \Exception(_('Session secret not defined.'));
			}

			self::$key = $config['session_key'];
		}

		return self::$key;
	}

	/**
	 * Timing attack safe string comparison.
	 *
	 * @static
	 *
	 * @param string $known_string
	 * @param string $user_string
	 *
	 * @return boolean
	 */
	public static function checkSign(string $known_string, string $user_string): bool {
		return hash_equals($known_string, $user_string);
	}

	/**
	 * Encrypt string with session key.
	 *
	 * @static
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	public static function sign(string $data): string {
		$key = self::getKey();

		return openssl_encrypt($data, self::SIGN_ALGO, $key);
	}
}
