<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CNumberValidator extends CValidator {

	protected ?string $max = null;
	protected ?string $min = null;
	protected bool $usermacros = false;
	protected bool $lldmacros = false;
	protected bool $with_float = true;
	private int $decimal_scale = 0;

	public function __construct(array $options = []) {
		if (array_key_exists('min', $options)) {
			$this->min = $this->processComparisonNumber($options['min']);
		}

		if (array_key_exists('max', $options)) {
			$this->max = $this->processComparisonNumber($options['max']);
		}

		if (array_key_exists('usermacros', $options)) {
			$this->usermacros = $options['usermacros'];
		}

		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = $options['lldmacros'];
		}

		if (array_key_exists('with_float', $options)) {
			$this->with_float = $options['with_float'];
		}
	}

	/**
	 * Checks if the given string is:
	 * - either macro or number with CNumberParser
	 * - if the value is not a macro, then also validates if the value is within min and max constrains.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value): bool {
		$parser = new CNumberParser([
			'usermacros' => $this->usermacros,
			'lldmacros' => $this->lldmacros,
			'with_float' => $this->with_float
		]);
		$result = $parser->parse($value);

		if ($result != CParser::PARSE_SUCCESS) {
			if ($this->with_float) {
				$this->setError(_('value is not a valid floating point number'));
			}
			else {
				$this->setError(_('value is not a valid integer'));
			}

			return false;
		}

		if (($this->usermacros || $this->lldmacros) && $value[0] === '{') {
			return true;
		}

		$value = $this->processComparisonNumber($value);

		if ($this->min !== null && bccomp($value, $this->min, $this->decimal_scale) == -1) {
			$this->setError(_s('value must be greater than or equal to %1$s', $this->min));

			return false;
		}
		elseif ($this->max !== null && bccomp($value, $this->max, $this->decimal_scale) == 1) {
			$this->setError(_s('value must be less than or equal to %1$s', $this->max));

			return false;
		}

		return true;
	}

	private function processComparisonNumber(string|float|int $number): string {
		if (is_int($number)) {
			return (string) $number;
		}

		$decimal_scale = is_float($number) ? getNumDecimals($number) : getStringNumDecimals($number);

		if ($decimal_scale > $this->decimal_scale) {
			$this->decimal_scale = $decimal_scale;
		}

		if (is_float($number)) {
			return number_format($number, $decimal_scale, '.', '');
		}

		return $this->convertNumberToNonScientificNumber($number);
	}

	private function convertNumberToNonScientificNumber(string $number): string {
		preg_match('/^'.ZBX_PREG_NUMBER.'/', $number, $matches);

		if (!array_key_exists('exp', $matches)) {
			return (array_key_exists('frac_only', $matches) ? '0'.$number : $number);
		}

		$add_zeroes = (int) $matches['exp'];
		$int_part = (array_key_exists('int', $matches) ? $matches['int'] : '');

		$float_part = (array_key_exists('frac', $matches) && strlen($matches['frac']) > 0)
			? $matches['frac']
			: (array_key_exists('frac_only', $matches) ? $matches['frac_only'] : '');

		$int_part = ltrim($int_part, '0');
		$float_part = rtrim($float_part, '0');

		if ($add_zeroes > 0) {
			$move_length = strlen($float_part) > $add_zeroes ? $add_zeroes : strlen($float_part);

			if ($move_length > 0) {
				$int_part .= substr($float_part, 0, $move_length);
				$float_part = substr($float_part, $move_length);
				$add_zeroes -= $move_length;
			}

			if ($add_zeroes > 0) {
				$int_part = $int_part.str_repeat('0', $add_zeroes);
			}
		}
		else {
			$add_zeroes = -$add_zeroes;
			$move_length = strlen($int_part) > $add_zeroes ? $add_zeroes : strlen($int_part);

			if ($move_length > 0) {
				$float_part = substr($int_part, -$move_length).$float_part;
				$int_part = substr($int_part, 0, strlen($int_part) - $move_length);
				$add_zeroes -= $move_length;
			}

			if ($add_zeroes > 0) {
				$float_part = str_repeat('0', $add_zeroes).$float_part;
			}
		}

		$int_part = ltrim($int_part, '0');
		$float_part = rtrim($float_part, '0');

		return ($int_part === '' ? '0' : $int_part).($float_part === '' ? '' : '.'.$float_part);
	}
}
