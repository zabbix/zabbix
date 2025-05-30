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


class CTimeUnitValidator extends CValidator {

	protected int $max = 0;
	protected int $min = 0;
	protected bool $usermacros = false;
	protected bool $lldmacros = false;

	public function __construct(array $options = []) {
		if (array_key_exists('min', $options)) {
			$this->min = (int) $options['min'];
		}

		if (array_key_exists('max', $options)) {
			$this->max = (int) $options['max'];
		}

		if (array_key_exists('lldmacros', $options)) {
			$this->lldmacros = (bool) $options['lldmacros'];
		}

		if (array_key_exists('usermacros', $options)) {
			$this->usermacros = (bool) $options['usermacros'];
		}
	}

	public function validate($value) {
		$interval_parser = new CSimpleIntervalParser(['usermacros' => $this->usermacros,
			'lldmacros' => $this->lldmacros
		]);
		$result = $interval_parser->parse($value);

		if ($result != CParser::PARSE_SUCCESS) {
			$this->setError(_('a time unit is expected'));

			return false;
		}

		if (($this->usermacros || $this->lldmacros) && $value[0] === '{') {
			return true;
		}

		$seconds = timeUnitToSeconds($value, false);

		if ($seconds > $this->max || $seconds < $this->min) {
			$this->setError(_s('value must be one of %1$s', $this->min.'-'.$this->max));

			return false;
		}

		return true;
	}
}
