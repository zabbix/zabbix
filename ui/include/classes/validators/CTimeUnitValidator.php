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


class CTimeUnitValidator extends CValidator {

	protected ?int $max = 0;
	protected ?int $min = 0;
	protected bool $usermacros = false;
	protected bool $lldmacros = false;
	protected bool $accept_zero = false;
	protected bool $with_year = false;

	public function __construct(array $options = []) {
		if (array_key_exists('min', $options)) {
			$this->min = $options['min'] === null ? null : (int) $options['min'];
		}

		if (array_key_exists('max', $options)) {
			$this->max = $options['max'] === null ? null : (int) $options['max'];
		}

		if (array_key_exists('accept_zero', $options)) {
			$this->accept_zero = (bool) $options['accept_zero'];
		}

		if (array_key_exists('with_year', $options)) {
			$this->with_year = (bool) $options['with_year'];
		}

		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = (bool) $options['lldmacros'];
		}

		if (array_key_exists('usermacros', $options)) {
			$this->usermacros = (bool) $options['usermacros'];
		}
	}

	/**
	 * Checks if the given string is:
	 * - either macro or time unit text with CSimpleIntervalParser
	 * - if value is not a macro, then also validates if value is between provided min and max range
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$interval_parser = new CSimpleIntervalParser(['usermacros' => $this->usermacros,
			'lldmacros' => $this->lldmacros, 'with_year' => $this->with_year
		]);
		$result = $interval_parser->parse($value);

		if ($result != CParser::PARSE_SUCCESS) {
			$this->setError(_('a time unit is expected'));

			return false;
		}

		if (($this->usermacros || $this->lldmacros) && $value[0] === '{') {
			return true;
		}

		$seconds = timeUnitToSeconds($value, $this->with_year);

		if ($this->accept_zero && $seconds == 0) {
			return true;
		}

		if ($this->max !== null && $this->min !== null) {
			if ($seconds > $this->max || $seconds < $this->min) {
				$this->setError(_s('value must be between %1$s and %2$s', $this->getSecondsText($this->min),
					$this->getSecondsText($this->max)
				));

				return false;
			}
		}
		elseif ($this->min !== null && $seconds < $this->min) {
			$this->setError(_s('value must be greater than or equal to %1$s', $this->getSecondsText($this->min)));

			return false;
		}
		elseif ($this->max !== null && $seconds > $this->max) {
			$this->setError(_s('value must be less than or equal to %1$s', $this->getSecondsText($this->max)));

			return false;
		}

		return true;
	}

	private function getSecondsText(int $seconds): string {
		$convert_options = ['with_year' => $this->with_year];

		return $seconds >= 60
			? $seconds._x('s', 'second short').' ('.convertUnitsS($seconds, $convert_options) .')'
			: convertUnitsS($seconds, $convert_options);
	}
}
