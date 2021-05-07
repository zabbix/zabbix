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
 * Class for validating math functions.
 */
class CMathFunctionValidator extends CValidator {

	/**
	 * Math functions along with number of required parameters (or -1 for number of required parameters >= 1).
	 *
	 * @var array
	 */
	private const MATH_FUNCTIONS = [
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
	 * Validate math function.
	 *
	 * @param array $token  A token of CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION type.
	 *
	 * @return bool
	 */
	public function validate($token) {
		if (!array_key_exists($token['data']['function'], self::MATH_FUNCTIONS)) {
			$this->setError(_s('unknown function "%1$s"', $token['data']['function']));

			return false;
		}

		$num_required_parameters = self::MATH_FUNCTIONS[$token['data']['function']];
		$num_parameters = count($token['data']['parameters']);

		if (($num_required_parameters == -1 && $num_parameters == 0)
				|| ($num_required_parameters != -1 && $num_parameters != $num_required_parameters)) {
			$this->setError(_s('invalid number of parameters in function "%1$s"', $token['data']['function']));

			return false;
		}

		return true;
	}
}
