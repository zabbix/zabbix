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


class CRangeTimeValidator extends CValidator {
	protected ?int $min = null;
	protected bool $min_in_future = false;

	public function __construct(array $options = []) {
		if (array_key_exists('min', $options)) {
			$this->min = (int) $options['min'];
		}

		if (array_key_exists('min_in_future', $options)) {
			$this->min_in_future = (bool) $options['min_in_future'];
		}

		if ($this->min !== null && $this->min_in_future) {
			exit('Incompatible options.');
		}
	}

	/**
	 * Checks if the given string is
	 * - either:
	 * 		a) absolute time in format YYYY[-MM[-DD]][ hh[:mm[:ss]]]
	 * 		b) relative time
	 * - after min timestamp (if provided)
	 *
	 * @param string $value
	 * @return bool
	 */
	public function validate($value): bool {
		$min_timestamp = ($this->min_in_future) ? time() + 1 : $this->min;

		$parser = new CRangeTimeParser();
		$result = $parser->parse($value);

		if ($result != CParser::PARSE_SUCCESS) {
			$this->setError(_('invalid time'));

			return false;
		}

		$timestamp = $parser->getDateTime(false)->getTimestamp();

		if ($timestamp < 0 || $timestamp > ZBX_MAX_DATE) {
			$this->setError(_('invalid time'));

			return false;
		}

		if ($min_timestamp !== null && $timestamp < $min_timestamp) {
			$this->setError(
				_s('value must be greater than or equal to %1$s', date(ZBX_FULL_DATE_TIME, $min_timestamp))
			);

			return false;
		}

		return true;
	}
}
