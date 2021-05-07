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
	 * An options array.
	 *
	 * Supported options:
	 *   'parameters' => []  Number of required parameters of known math functions.
	 *
	 * @var array
	 */
	private $options = [
		'parameters' => []
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Validate math function.
	 *
	 * @param array $token  A token of CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION type.
	 *
	 * @return bool
	 */
	public function validate($token) {
		if (!array_key_exists($token['data']['function'], $this->options['parameters'])) {
			$this->setError(_s('unknown function "%1$s"', $token['data']['function']));

			return false;
		}

		$num_required_parameters = $this->options['parameters'][$token['data']['function']];
		$num_parameters = count($token['data']['parameters']);

		if (is_array($num_required_parameters)) {
			$is_valid = ($num_required_parameters[0] === null || $num_parameters >= $num_required_parameters[0])
				&& ($num_required_parameters[1] === null || $num_parameters <= $num_required_parameters[1]);
		}
		else {
			$is_valid = $num_parameters == $num_required_parameters;
		}

		if (!$is_valid) {
			$this->setError(_s('invalid number of parameters in function "%1$s"', $token['data']['function']));
		}

		return $is_valid;
	}
}
