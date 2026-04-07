<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Class to generate UUID version 7 (non-monotonic when resolving multiple IDs within 1 millisecond).
 */
class CUuidV7 {

	public static function generate(bool $canonical_format = false): string {
		$data = self::getTimePart().random_bytes(10);

		// Set version: 7th byte to 0111 (0111xxxx)
		$data[6] = chr(ord($data[6]) & 0x0f | 0x70);

		// Set variant: 9th byte to 10 (10xxxxxx)
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

		return self::format($data, $canonical_format);
	}

	private static function getTimePart(): string {
		// 48 bit
		$unix_timestamp = (int) floor(microtime(true) * 1000);

		// 32 bit leftmost bits
		$left_time_part = ($unix_timestamp >> 16) & 0xffffffff;

		// 16 bit rightmost bits
		$right_time_part = $unix_timestamp & 0xffff;

		// 6 bytes time part
		return pack('Nn', $left_time_part, $right_time_part);
	}

	private static function format(string $data, bool $canonical_format): string {
		$hex_data = bin2hex($data);

		if (!$canonical_format) {
			return $hex_data;
		}

		return implode('-', [
			substr($hex_data, 0, 8),
			substr($hex_data, 8, 4),
			substr($hex_data, 12, 4),
			substr($hex_data, 16, 4),
			substr($hex_data, 20, 12),
		]);
	}

	public static function isUuidV7(string $uuid): bool {
		$uuid = strtolower(str_replace('-', '', $uuid));

		if (strlen($uuid) != 32 || !ctype_xdigit($uuid)) {
			return false;
		}

		return $uuid[12] === '7' && in_array($uuid[16], ['8','9','a','b']);
	}
}
