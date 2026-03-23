<?php declare(strict_types = 1);
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


class CNumberHelper {
	/**
	 * Parses a numeric string into a normalized representation:
	 * - sign: -1 or 1
	 * - digits: a string of decimal digits without leading zeros
	 * - scale: a non-negative integer (the number of digits to the right of the decimal point)
	 *
	 * Supports: decimal and scientific notation.
	 */
	private static function parseDecimal(string $s): array {
		$offset = $s[0] === '-' ? 1 : 0;

		$pos_exp = strcspn($s, 'eE', $offset);
		$mant = substr($s, $offset, $pos_exp);
		$exp = isset($s[$pos_exp + $offset]) ? (int) substr($s, $pos_exp + $offset + 1) : 0;

		$part_int = $mant;
		$part_frac = '';

		if (($pos_dot = strpos($mant, '.')) !== false) {
			$part_int = substr($mant, 0, $pos_dot);
			$part_frac = substr($mant, $pos_dot + 1);
		}

		$digits = $part_int.$part_frac;
		$scale = strlen($part_frac) - $exp;

		if ($scale < 0) {
			$digits .= str_repeat('0', -$scale);
			$scale = 0;
		}

		$digits = ltrim($digits, '0');
		if ($digits === '') {
			$digits = '0';
		}

		return ['sign' => $digits !== '0' && $s[0] === '-' ? -1 : 1, 'digits' => $digits, 'scale' => $scale];
	}

	/**
	 * Compares two strings containing numbers in decimal or scientific notation without losing precision.
	 *
	 * Returns a value less than 0 if $a is less than $b; a value greater than 0 if $a is greater than $b, and 0 if they
	 * are equal.
	 */
	public static function compareNumberStrings(string $a, string $b): int {
		$na = self::parseDecimal($a);
		$nb = self::parseDecimal($b);

		if ($na['sign'] !== $nb['sign']) {
			return $na['sign'] <=> $nb['sign'];
		}

		// Calculate how many zeros need to be appended to the numbers to align their scale.
		$s = min($na['scale'], $nb['scale']);
		$pad_a = $na['digits'] === '0' ? 0 : $nb['scale'] - $s;
		$pad_b = $nb['digits'] === '0' ? 0 : $na['scale'] - $s;

		$len_a = strlen($na['digits']) + $pad_a;
		$len_b = strlen($nb['digits']) + $pad_b;

		// If the resulting lengths of the numbers are not equal, return the result immediately.
		if ($len_a !== $len_b) {
			return $na['sign'] * ($len_a <=> $len_b);
		}

		// Pad the numbers with zeros and compare them lexicographically, since their lengths are equal.
		$a = str_pad($na['digits'], $len_a, '0');
		$b = str_pad($nb['digits'], $len_b, '0');

		return $na['sign'] * strcmp($a, $b);
	}

	/**
	 * Compares two numbers in decimal or scientific notation without losing precision.
	 *
	 * Returns a value less than 0 if $a is less than $b; a value greater than 0 if $a is greater than $b, and 0 if they
	 * are equal.
	 */
	public static function compareNumbers(int|float|string $a, int|float|string $b): int {
		if (is_string($a) || is_string($b)) {
			return self::compareNumberStrings((string) $a, (string) $b);
		}

		return $a <=> $b;
	}
}
