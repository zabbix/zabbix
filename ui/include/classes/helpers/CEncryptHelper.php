<?php declare(strict_types = 0);
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
 * Helper class for sign / encrypt data.
 */
class CEncryptHelper {

	/**
	 * Signature algorithm.
	 */
	public const SIGN_ALGO = 'sha256';

	/**
	 * Session secret key.
	 *
	 * @var string
	 */
	private static $key;

	/**
	 * Return session key.
	 *
	 * @return string|null
	 */
	private static function getKey(): ?string {
		if (!self::$key) {
			// This if contain copy in CEncryptedCookieSession class.
			if (CSettingsHelper::getGlobal(CSettingsHelper::SESSION_KEY) === '') {
				self::$key = self::generateKey();

				if (!self::updateKey(self::$key)) {
					return null;
				}

				return self::$key;
			}

			self::$key = CSettingsHelper::getGlobal(CSettingsHelper::SESSION_KEY);
		}

		return self::$key;
	}

	/**
	 * Timing attack safe string comparison.
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
	 * @param string $data
	 *
	 * @return string
	 */
	public static function sign(string $data): string {
		$key = self::getKey();

		return hash_hmac(self::SIGN_ALGO, $data, $key, false);
	}

	/**
	 * Generate random 16 bytes key.
	 *
	 * @return string
	 */
	public static function generateKey(): string {
		return bin2hex(openssl_random_pseudo_bytes(16));
	}

	/**
	 * Update secret session key.
	 *
	 * @param string $key
	 *
	 * @return boolean
	 */
	public static function updateKey(string $key): bool {
		$db_config = DB::select('config', ['output' => ['configid']])[0];

		return DBexecute(
			'UPDATE config'.
			' SET session_key='.zbx_dbstr($key).
			' WHERE '.dbConditionInt('configid', [$db_config['configid']])
		);
	}
}
