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
	 * Known math functions along with number of required parameters (-1 for number of required parameters >= 1).
	 *
	 * @var array
	 */
	private const PARAMETERS = [
		'abs' => 1,
		'avg' => -1,
		'bitand' => 2,
		'date' => 0,
		'dayofmonth' => 0,
		'dayofweek' => 0,
		'length' => 1,
		'max' => -1,
		'min' => -1,
		'now' => 0,
		'sum' => -1,
		'time' => 0
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
	 * Get known math functions along with number of required parameters (-1 for number of required parameters >= 1).
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
