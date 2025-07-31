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


/**
 * A parser for absolute date in "YYYY[-MM[-DD]]" format.
 */
class CAbsoluteDateParser extends CParser {

	/**
	 * Date in "YYYY-MM-DD" format.
	 */
	private string $date = '';

	/**
	 * Parse the given date.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$pattern_Y = '(?P<Y>[12][0-9]{3})';
		$pattern_m = '(?P<m>[0-9]{1,2})';
		$pattern_d = '(?P<d>[0-9]{1,2})';
		$pattern = '^'.$pattern_Y.'(-'.$pattern_m.'(-'.$pattern_d.')?)?$';
		$subject = substr($source, $pos);

		if (!preg_match('/'.$pattern.'/', $subject, $matches)) {
			return self::PARSE_FAIL;
		}

		$matches += ['m' => 1, 'd' => 1];
		$date = sprintf('%04d-%02d-%02d', $matches['Y'], $matches['m'], $matches['d']);
		$datetime = date_create($date);

		if ($datetime === false || $datetime->getLastErrors() !== false) {
			return self::PARSE_FAIL;
		}

		$this->date = $date;
		$this->match = $subject;
		$this->length = strlen($subject);

		return self::PARSE_SUCCESS;
	}

	public function getDateTime(?DateTimeZone $timezone = null): ?DateTime {
		if ($this->date === '') {
			return null;
		}

		return new DateTime($this->date, $timezone);
	}
}
