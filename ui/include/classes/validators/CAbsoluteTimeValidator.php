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


class CAbsoluteTimeValidator extends CValidator {

	protected ?int $max = null;
	protected ?int $min = null;
	protected bool $date_only = false;

	public function __construct(array $options = []) {
		if (array_key_exists('date_only', $options)) {
			$this->date_only = (bool) $options['date_only'];
		}

		if (array_key_exists('min', $options)) {
			$this->min = (int) $options['min'];
		}

		if (array_key_exists('max', $options)) {
			$this->max = (int) $options['max'];
		}
	}

	/**
	 * Checks if the given string is:
	 * - valid absolute time with CAbsoluteTimeParser
	 * - is between provided min and max timestamps
	 *
	 * @param string $value
	 */
	public function validate($value): bool {
		$parser = new CAbsoluteTimeParser(['date_only' => $this->date_only]);

		if ($parser->parse($value) != CParser::PARSE_SUCCESS) {
			$this->setError(_('invalid date'));

			return false;
		}

		$timestamp = $parser->getDateTime(true)->getTimestamp();

		if ($timestamp < 0) {
			$this->setError(_('invalid date'));

			return false;
		}

		if ($this->min !== null && $timestamp < $this->min) {
			$this->setError(_s('value must be greater than or equal to %1$s', date(ZBX_FULL_DATE_TIME, $this->min)));

			return false;
		}
		elseif ($this->max !== null && $timestamp > $this->max) {
			$this->setError(_s('value must be less than or equal to %1$s', date(ZBX_FULL_DATE_TIME, $this->max)));

			return false;
		}

		return true;
	}
}
