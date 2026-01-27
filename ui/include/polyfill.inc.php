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


/**
 * Function str_decrement is available since PHP 8.3. Whilst PHP 8.5 runtime would emit deprecation warning when
 * decrementing non-numeric string.
 *
 * Replace references to this function once minimum runtime version is raised. Currently PHP 8.2 is lower boundary.
 *
 * Credit: https://github.com/symfony/polyfill-php83/blob/1.x/Php83.php
 */
function str_decrement_polyfill(string $string): string {
	if (is_numeric($string)) {
		return --$string;
	}

	if ($string === '') {
		throw new ValueError('str_decrement(): Argument #1 ($string) cannot be empty');
	}

	if (!preg_match('/^[a-zA-Z0-9]+$/', $string)) {
		throw new ValueError(
			'str_decrement(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters'
		);
	}

	if (preg_match('/\A(?:0[aA0]?|[aA])\z/', $string)) {
		throw new ValueError(sprintf('str_decrement(): Argument #1 ($string) "%s" is out of decrement range', $string));
	}

	if (!in_array(substr($string, -1), ['A', 'a', '0'], true)) {
		return implode('', array_slice(str_split($string), 0, -1)).chr(ord(substr($string, -1)) - 1);
	}

	$carry = '';
	$result = '';

	for ($i = strlen($string) - 1; $i >= 0; --$i) {
		$char = $string[$i];

		switch ($char) {
			case 'A':
				if ($carry !== '') {
					$result = $carry.$result;
					$carry = '';
				}
				$carry = 'Z';
				break;

			case 'a':
				if ($carry !== '') {
					$result = $carry.$result;
					$carry = '';
				}

				$carry = 'z';
				break;

			case '0':
				if ($carry !== '') {
					$result = $carry.$result;
					$carry = '';
				}
				$carry = '9';
				break;

			case '1':
				if ($carry !== '') {
					$result = $carry.$result;
					$carry = '';
				}
				break;

			default:
				if ($carry !== '') {
					$result = $carry.$result;
					$carry = '';
				}

				if (!in_array($char, ['A', 'a', '0'], true)) {
					$result = chr(ord($char) - 1).$result;
				}
		}
	}

	return $result;
}

function str_increment_polyfill(string $string): string {
	if (is_numeric($string)) {
		return ++$string;
	}

	if ($string === '') {
		throw new ValueError('str_increment(): Argument #1 ($string) cannot be empty');
	}

	if (!preg_match('/^[a-zA-Z0-9]+$/', $string)) {
		throw new ValueError(
			'str_increment(): Argument #1 ($string) must be composed only of alphanumeric ASCII characters'
		);
	}

	$last_char = substr($string, -1);

	if (!in_array($last_char, ['Z', 'z', '9'], true)) {
		return substr($string, 0, -1).chr(ord($last_char) + 1);
	}

	$carry = true;
	$result = '';

	for ($i = strlen($string) - 1; $i >= 0; --$i) {
		$char = $string[$i];

		if (!$carry) {
			$result = $char.$result;
			continue;
		}

		switch ($char) {
			case 'Z':
				$result = 'A'.$result;
				break;

			case 'z':
				$result = 'a'.$result;
				break;

			case '9':
				$result = '0'.$result;
				break;

			default:
				$result = chr(ord($char) + 1).$result;
				$carry = false;
		}
	}

	if ($carry) {
		$first_char = $string[0];

		if ($first_char >= 'A' && $first_char <= 'Z') {
			$result = 'A'.$result;
		}
		elseif ($first_char >= 'a' && $first_char <= 'z') {
			$result = 'a'.$result;
		}
		else {
			$result = '1'.$result;
		}
	}

	return $result;
}
