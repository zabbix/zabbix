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
 * A parser for absolute time in "YYYY[-MM[-DD]]" format.
 */
class CAbsoluteDateParser extends CParser {

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		if (!$this->parseAbsoluteDate($source, $pos)) {
			return self::PARSE_FAIL;
		}

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse absolute time.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parseAbsoluteDate($source, &$pos) {
		$pattern_Y = '(?P<Y>[12][0-9]{3})';
		$pattern_m = '(?P<m>[0-9]{1,2})';
		$pattern_d = '(?P<d>[0-9]{1,2})';

		$pattern = $pattern_Y.'(-'.$pattern_m.'(-'.$pattern_d.')?)?';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$matches += ['m' => 1, 'd' => 1];

		$date = sprintf('%04d-%02d-%02d', $matches['Y'], $matches['m'], $matches['d']);

		$datetime = date_create($date);

		if ($datetime === false) {
			return false;
		}

		$datetime_errors = $datetime->getLastErrors();

		if ($datetime_errors !== false && $datetime_errors['warning_count'] != 0) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
