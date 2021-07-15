<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class to work with CUID hash. Based on server CUID implementation.
 */
class CCuid {
	private const BASE = 36;
	private const BLOCK_SIZE = 4;
	private const DECIMAL = 10;
	public const CUID_LENGTH = 25;
	private const CUID_PREFIX = 'c';

	private static int $CUID_COUNTER = 0;

	private static function pad(string $value, int $size): string {
		return substr(str_pad(base_convert($value, self::DECIMAL, self::BASE), $size, '0', STR_PAD_LEFT), 0, $size);
	}

	private static function getCounter(): int {
		return self::$CUID_COUNTER++;
	}

	private static function getRandomBlock(): string {
		$rand = floor(mt_rand() / mt_getrandmax() * pow(self::BASE, self::BLOCK_SIZE));

		return self::pad((string) $rand, self::BLOCK_SIZE);
	}

	private static function getCounterBlock(): string {
		return self::pad((string) self::getCounter(), self::BLOCK_SIZE);
	}

	private static function getTimestampBlock(): string {
		return self::pad((string) floor(microtime(true) * 1000), self::BLOCK_SIZE * 2);
	}

	private static function getHostnameAsNumber(): int {
		return array_sum(array_map(function (string $char): int {
			return ord($char);
		}, str_split(gethostname()))) + strlen(gethostname()) + self::BASE;
	}

	private static function getPid(): string {
		return substr(self::pad((string) getmypid(), self::BLOCK_SIZE), -2);
	}

	private static function getFingerprintBlock(): string {
		return self::getPid().self::pad((string) self::getHostnameAsNumber(), self::BLOCK_SIZE / 2);
	}

	public static function cuid(): string {
		return sprintf('%s%s%s%s%s%s', self::CUID_PREFIX, self::getTimestampBlock(), self::getCounterBlock(),
			self::getFingerprintBlock(), self::getRandomBlock(), self::getRandomBlock()
		);
	}

	public static function isCuid(string $hash): bool {
		return $hash[0] === self::CUID_PREFIX;
	}

	public static function isCuidLength(string $hash): bool {
		return strlen($hash) === self::CUID_LENGTH;
	}
}
