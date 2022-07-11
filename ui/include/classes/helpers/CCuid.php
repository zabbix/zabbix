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
 * Class to work with CUID hash. Based on server CUID implementation.
 */
class CCuid {
	private const BASE36 = 36;
	private const BLOCK_SIZE = 4;
	private const DECIMAL = 10;
	private const PREFIX = 'c';
	private const DISCRETE_VALUES = 1679616;

	public const LENGTH = 25;

	private static $counter = -1;

	public static function generate(): string {
		return sprintf('%s%s%s%s%s%s', self::PREFIX, self::getTimestampBlock(), self::getCounterBlock(),
			self::getFingerprintBlock(), self::getRandomBlock(), self::getRandomBlock()
		);
	}

	public static function isCuid(string $hash): bool {
		return $hash[0] === self::PREFIX;
	}

	public static function checkLength(string $hash): bool {
		return strlen($hash) === self::LENGTH;
	}

	private static function pad(string $value, int $size): string {
		return substr(str_pad(base_convert($value, self::DECIMAL, self::BASE36), $size, '0', STR_PAD_LEFT), -$size);
	}

	private static function next(): int {
		self::$counter++;

		if (self::$counter >= self::DISCRETE_VALUES) {
			self::$counter = 0;
		}

		return self::$counter;
	}

	private static function getRandomBlock(): string {
		$rand = floor(mt_rand() / mt_getrandmax() * self::DISCRETE_VALUES);

		return self::pad((string) $rand, self::BLOCK_SIZE);
	}

	private static function getCounterBlock(): string {
		return self::pad((string) self::next(), self::BLOCK_SIZE);
	}

	private static function getTimestampBlock(): string {
		return self::pad((string) floor(microtime(true) * 1000), self::BLOCK_SIZE * 2);
	}

	private static function getHostnameSubBlock(): string {
		$sum = strlen(gethostname()) + self::BASE36;

		foreach (str_split(gethostname()) as $char) {
			$sum += ord($char);
		}

		return self::pad((string) $sum, self::BLOCK_SIZE / 2);
	}

	private static function getPidSubBlock(): string {
		return self::pad((string) getmypid(), self::BLOCK_SIZE / 2);
	}

	private static function getFingerprintBlock(): string {
		return self::getPidSubBlock().self::getHostnameSubBlock();
	}
}
