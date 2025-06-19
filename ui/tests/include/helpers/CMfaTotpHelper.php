<?php
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
 * Class for generating MFA TOTP tokens. This helper simulates an authenticator app a user would have on their phone.
 */
class CMfaTotpHelper {
	// TOTP window time in seconds.
	const TOTP_WINDOW_SIZE = 30;
	// Characters for validating TOTP secrets.
	const VALID_BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	// Maps Zabbix API hash algorithms to PHP hash_algos().
	protected static $algorithms_map = [
		TOTP_HASH_SHA1 => 'sha1',
		TOTP_HASH_SHA256 => 'sha256',
		TOTP_HASH_SHA512 => 'sha512'
	];

	/**
	 * Generates a Time-based One-Time Password (TOTP) based on the provided secret.
	 *
	 * @param string $secret            Base32-encoded secret used to generate the TOTP.
	 * @param int    $code_length       Number of digits for the TOTP (6 or 8).
	 * @param int    $algorithm         Hash function in Zabbix API format (1 = sha1, 2 = sha256, 3 = sha512).
	 * @param int    $time_step_offset  This is added to the time step. 1 means 30 seconds in the future.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException
	 */
	public static function generateTotp($secret, $code_length = TOTP_CODE_LENGTH_6, $algorithm = TOTP_HASH_SHA1,
			$time_step_offset = 0) {
		// Validate the code length.
		if (!in_array($code_length, [TOTP_CODE_LENGTH_6, TOTP_CODE_LENGTH_8])) {
			throw new InvalidArgumentException('TOTP length must be either 6 or 8, unsupported value: '.$code_length);
		}

		// Validate the provided hash function.
		if (!in_array($algorithm, [TOTP_HASH_SHA1, TOTP_HASH_SHA256, TOTP_HASH_SHA512])) {
			throw new InvalidArgumentException('Unsupported TOTP hash: '.$algorithm);
		}

		// Calculate the current time step. The TOTP changes every 30 seconds.
		$time_step = floor(time() / self::TOTP_WINDOW_SIZE) + $time_step_offset;
		// Convert the time step to a binary string representing an "unsigned long long" (64-bit).
		$time_step_binary = pack('J', $time_step);

		// Convert the secret key from Base32 to binary.
		$secret_binary = self::base32Decode($secret);

		// Generate the hash that the TOTP is extracted from.
		$hash_binary = hash_hmac(self::$algorithms_map[$algorithm], $time_step_binary, $secret_binary, true);

		/*
		 * Determine the offset for TOTP extraction.
		 *
		 * The offset is determined by the last 4 bits of the hash and is dynamic.
		 * This is done to add randomness to the TOTP generation, making it harder reverse-engineer the TOTP secret.
		 *
		 * The offset is a number from 0 to 15.
		 *
		 * For more information refer to the RFC 4226: https://datatracker.ietf.org/doc/html/rfc4226#section-5.3
		 */
		$offset = ord($hash_binary[strlen($hash_binary) - 1]) & 0xf;

		/*
		 * Extract the TOTP from the binary hash.
		 *
		 * The TOTP is contained in 4 bytes (32 bits) of the calculated hash.
		 * The position of those bytes is determined by the offset calculated above.
		 * These 4 bytes are extracted, combined into a single 32-bit integer and the last digits are used as the TOTP.
		 *
		 * For more information refer to the RFC 4226: https://datatracker.ietf.org/doc/html/rfc4226#section-5.3
		 */
		$totp = (
				(ord($hash_binary[$offset + 0]) & 0x7f) << 24 |
				(ord($hash_binary[$offset + 1]) & 0xff) << 16 |
				(ord($hash_binary[$offset + 2]) & 0xff) << 8 |
				(ord($hash_binary[$offset + 3]) & 0xff)
			) % pow(10, $code_length); // limit to the specified code length

		// Zero pad and return.
		return str_pad($totp, $code_length, '0', STR_PAD_LEFT);
	}

	/**
	 * Waits until the current TOTP window is safely far from changing.
	 * This prevents issues where a TOTP is generated too close to a window change and becomes
	 * invalid by the time it's used. It also prevents a scenario where server and client time mismatches
	 * slightly and the client generates a future code that the server rejects.
	 *
	 * @param float $buffer  Minimum time in seconds from a time window change.
	 */
	public static function waitForSafeTotpWindow($buffer = 1) {
		// Calculate the remaining time in the current TOTP window.
		$time_in_window = fmod(microtime(true), self::TOTP_WINDOW_SIZE);
		$remaining_time = self::TOTP_WINDOW_SIZE - $time_in_window;

		// Wait if in the buffer zone.
		if ($remaining_time < $buffer) {
			// Sleep until next window starts, and then another buffer amount, to safely be in a TOTP window.
			usleep((int) (($remaining_time + $buffer) * 1000000));
		}
		elseif ($time_in_window < $buffer) {
			// Second case - the window has just started, wait to be outside the buffer zone.
			usleep((int) (($buffer - $time_in_window) * 1000000));
		}
	}

	/**
	 * Checks if a string is a valid TOTP secret (in the context of Zabbix).
	 * The secret must be between 16 and 32 characters long and consist only of allowed Base32 characters.
	 *
	 * @param string $secret  The secret string that must be validated.
	 *
	 * @return bool
	 */
	public static function isValidSecretString($secret) {
		$pattern = '/^['.self::VALID_BASE32_CHARS.']{16,32}$/';
		return preg_match($pattern, $secret) === 1;
	}

	/**
	 * TOTP secrets are encoded in Base32. This takes a Base32 encoded string,
	 * converts it to a raw data string and returns it.
	 *
	 * @param string $base32_secret  Base32 secret string to be decoded.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException
	 */
	private static function base32Decode($base32_secret) {
		$base32_secret = strtoupper($base32_secret);
		$decoded_secret = '';
		$buffer = 0; // buffer for temporarily storing binary data
		$buffer_bits = 0; // number of unconverted bits currently in the buffer

		foreach (str_split($base32_secret) as $char) {
			$index = strpos(self::VALID_BASE32_CHARS, $char);
			if ($index === false) {
				throw new InvalidArgumentException('Invalid Base32 character in TOTP secret: '.$char);
			}

			/*
			 * Each character in Base32 represents 5 bits.
			 * Shift the buffer 5 bits left and append the current character's numeric value.
			 */
			$buffer = ($buffer << 5) | $index;
			$buffer_bits += 5;

			// Once more than 8 bits are in the buffer, the 8 most significant bits must be converted to a character.
			if ($buffer_bits >= 8) {
				$buffer_bits -= 8;
				// Shift the most significant 8 bits to the right and mask all other bits before converting to a char.
				$decoded_secret .= chr(($buffer >> $buffer_bits) & 0xFF);
			}
		}

		return $decoded_secret;
	}
}
