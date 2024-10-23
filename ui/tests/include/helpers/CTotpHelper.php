<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Class for generating MFA TOTP tokens.
 */
class CTotpHelper {
	const VALID_BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Generate a Time-based One-Time Password (TOTP) based on the provided secret.
	 *
	 * @param string $secret    Base32-encoded secret used to generate the TOTP.
	 *
	 * @return string    The 6-digit TOTP.
	 */
	public static function generateTotp($secret) {
		// Calculate the current time step. The TOTP changes every 30 seconds
		$time_step = floor(time() / 30);
		// Convert time step to a 64 bit binary timestamp.
		$time_step_binary = pack('J', $time_step);

		// Convert the secret key from Base32 to binary.
		$secret_binary = self::base32Decode($secret);

		// Generate the hash that the TOTP is extracted from.
		$hash_binary = hash_hmac('sha1', $time_step_binary, $secret_binary, true);

		// Determine the offset for TOTP extraction.
		$offset = ord($hash_binary[19]) & 0xf;

		// Extract the TOTP from the hash
		$totp = (((
				(ord($hash_binary[$offset + 0]) & 0x7f) << 24 |
				(ord($hash_binary[$offset + 1]) & 0xff) << 16 |
				(ord($hash_binary[$offset + 2]) & 0xff) << 8 |
				(ord($hash_binary[$offset + 3]) & 0xff)
			) % 1000000)); // Limit to 6 digits

		// Zero pad and return.
		return str_pad($totp, 6, '0', STR_PAD_LEFT);
	}

	/**
	 * TOTP secrets are encoded in Base32. To generate a token, the secret needs to be decoded.
	 *
	 * @param string $base32_secret    Base32 secret string to be decoded.
	 *
	 * @return string    Decoded secret binary data.
	 * @throws InvalidArgumentException   If the input contains invalid Base32 characters.
	 */
	private static function base32Decode($base32_secret) {
		$base32_secret = strtoupper($base32_secret);
		$decoded_secret = '';
		$buffer = 0; // buffer for temporarily storing binary data
		$buffer_bits = 0; // number of unconverted bits currently in the buffer

		foreach (str_split($base32_secret) as $char) {
			$index = strpos(self::VALID_BASE32_CHARS, $char);
			if ($index === false) {
				throw new InvalidArgumentException('TOTP secret contains an invalid Base32 character: '.$char);
			}

			// Each character in Base32 represents 5 bits.
			// Shift the buffer 5 bits left and append the current character's numeric value.
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
