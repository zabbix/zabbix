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
 * Class containing information on math functions.
 */
final class CMathFunctionData {

	/**
	 * Known math functions along with number or range of required parameters.
	 *
	 * @var array
	 */
	private const PARAMETERS = [
		'abs' => 1,
		'acos' => 1,
		'ascii' => 1,
		'asin' => 1,
		'atan' => 1,
		'atan2' => 2,
		'avg' => [1, null],
		'between' => 3,
		'bitand' => 2,
		'bitlength' => 1,
		'bitlshift' => 2,
		'bitnot' => 1,
		'bitor' => 2,
		'bitrshift' => 2,
		'bitxor' => 2,
		'bytelength' => 1,
		'cbrt' => 1,
		'ceil' => 1,
		'char' => 1,
		'concat' => 2,
		'cos' => 1,
		'cosh' => 1,
		'cot' => 1,
		'date' => 0,
		'dayofmonth' => 0,
		'dayofweek' => 0,
		'degrees' => 1,
		'e' => 0,
		'exp' => 1,
		'expm1' => 1,
		'floor' => 1,
		'in' => [2, null],
		'insert' => 4,
		'left' => 2,
		'length' => 1,
		'log' => 1,
		'log10' => 1,
		'ltrim' => [1, 2],
		'max' => [1, null],
		'mid' => 3,
		'min' => [1, null],
		'mod' => 2,
		'now' => 0,
		'pi' => 0,
		'power' => 2,
		'radians' => 1,
		'rand' => 0,
		'repeat' => 2,
		'replace' => 3,
		'right' => 2,
		'rtrim' => [1, 2],
		'round' => 2,
		'sin' => 1,
		'sinh' => 1,
		'signum' => 1,
		'sqrt' => 1,
		'sum' => [1, null],
		'tan' => 1,
		'time' => 0,
		'trim' => [1, 2],
		'truncate' => 2
	];

	/**
	 * Check if function is known math function.
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public function isKnownFunction(string $function): bool {
		return array_key_exists($function, self::PARAMETERS);
	}

	/**
	 * Get known math functions along with number or range of required parameters.
	 *
	 * @return array
	 */
	public function getParameters(): array {
		return self::PARAMETERS;
	}

	/**
	 * Check if function is aggregating it's parameters or the result of aggregating history functions.
	 *
	 * @static
	 *
	 * @param string $function
	 *
	 * @return bool
	 */
	public static function isAggregating(string $function): bool {
		switch ($function) {
			case 'avg':
			case 'max':
			case 'min':
			case 'sum':
				return true;

			default:
				return false;
		}
	}
}
