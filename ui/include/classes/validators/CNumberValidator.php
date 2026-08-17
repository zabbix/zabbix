<?php
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


class CNumberValidator extends CValidator {

	protected ?string $max = null;
	protected ?string $min = null;
	protected bool $usermacros = false;
	protected bool $lldmacros = false;
	protected bool $with_float = true;

	/**
	 * @param array $options
	 * @param int|float|string $options[max]        Maximum value in decimal or scientific notation.
	 * @param int|float|string $options[min]        Minimum value in decimal or scientific notation.
	 * @param bool             $options[usermacros] Enable user macros.
	 * @param bool             $options[lldmacros]  Enable low-level discovery macros.
	 * @param bool             $options[with_float] Enable floats.
	 */
	public function __construct(array $options = []) {
		if (array_key_exists('min', $options)) {
			$this->min = (string) $options['min'];
		}

		if (array_key_exists('max', $options)) {
			$this->max = (string) $options['max'];
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

		if ($this->min !== null && CNumberHelper::compareNumbers($value, $this->min) < 0) {
			$this->setError(_s('value must be greater than or equal to %1$s', $this->min));

			return false;
		}

		if ($this->max !== null && CNumberHelper::compareNumbers($value, $this->max) > 0) {
			$this->setError(_s('value must be less than or equal to %1$s', $this->max));

			return false;
		}

		return true;
	}
}
