<?php declare(strict_types=0);
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


if (!function_exists('str_increment')) {
	/**
	 * Function is available since PHP 8.3. Whilst PHP 8.5 runtime would emit deprecation warning when
	 * decrementing non-numeric string.
	 *
	 * Replace references to this function once minimum runtime version is raised. Currently PHP 8.0 is lower boundary.
	 *
	 * Caution, this is not a complete polyfill - only A-z characters are incremented.
	 */
	function str_increment(string $string): string {
		$size = strlen($string);
		$change_index = 0;

		while (--$size >= 0) {
			$char_code = ord($string[$size]);
			$case = $char_code > 96 ? 32 : 0;
			$symbol = $char_code % 32;

			if ($symbol < 26) {
				$affix = $change_index == 0 ? '' : substr($string, $change_index);

				return substr($string, 0, $size).chr($symbol + 1 + 64 + $case).$affix;
			}

			$string[$size] = chr(65 + $case);
			$change_index = $size;

			if ($size == 0) {
				return chr(65 + $case).$string;
			}
		}

		throw new ValueError('String argument cannot not be empty.');

		return '';
	}
}
