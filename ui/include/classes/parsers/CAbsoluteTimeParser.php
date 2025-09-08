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
 * A parser for absolute time in "YYYY[-MM[-DD]][ hh[:mm[:ss]]]" format.
 */
class CAbsoluteTimeParser extends CParser {

	/**
	 * Full date in "YYYY-MM-DD hh:mm:ss" format.
	 *
	 * @var string $date
	 */
	private $date;

	/**
	 * @var array $tokens
	 */
	private $tokens;

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->tokens = [];
		$this->length = 0;
		$this->match = '';
		$this->date = '';

		$p = $pos;

		if (!$this->parseAbsoluteTime($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse absolute time.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parseAbsoluteTime($source, &$pos) {
		$pattern_Y = '(?P<Y>[12][0-9]{3})';
		$pattern_m = '(?P<m>[0-9]{1,2})';
		$pattern_d = '(?P<d>[0-9]{1,2})';
		$pattern_H = '(?P<H>[0-9]{1,2})';
		$pattern_i = '(?P<i>[0-9]{1,2})';
		$pattern_s = '(?P<s>[0-9]{1,2})';
		$pattern = $pattern_Y.'(-'.$pattern_m.'(-'.$pattern_d.'( +'.$pattern_H.'(:'.$pattern_i.'(:'.$pattern_s.')?)?)?)?)?';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		foreach (['Y', 'm', 'd', 'H', 'i', 's'] as $key) {
			if (array_key_exists($key, $matches)) {
				$this->tokens[$key] = $matches[$key];
			}
		}

		$matches += ['m' => 1, 'd' => 1, 'H' => 0, 'i' => 0, 's' => 0];

		$date = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches['Y'], $matches['m'], $matches['d'], $matches['H'],
			$matches['i'], $matches['s']
		);

		$datetime = date_create($date);

		if ($datetime === false) {
			return false;
		}

		$datetime_errors = $datetime->getLastErrors();

		if ($datetime_errors !== false && $datetime_errors['warning_count'] != 0) {
			return false;
		}

		$this->date = $date;
		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Get DateTime object with its value set to either start or end of the period derived from the date/time specified.
	 *
	 * @param bool              $is_start
	 * @param DateTimeZone|null $timezone
	 *
	 * @return DateTime|null
	 */
	public function getDateTime(bool $is_start, ?DateTimeZone $timezone = null): ?DateTime {
		if ($this->date === '') {
			return null;
		}

		$date = new DateTime($this->date, $timezone);

		if ($is_start) {
			return $date;
		}

		if (!array_key_exists('m', $this->tokens)) {
			return $date->modify('last day of December this year 23:59:59');
		}

		if (!array_key_exists('d', $this->tokens)) {
			return $date->modify('last day of this month 23:59:59');
		}

		if (!array_key_exists('H', $this->tokens)) {
			return $date->modify('23:59:59');
		}

		if (!array_key_exists('i', $this->tokens)) {
			return new DateTime($date->format('Y-m-d H:59:59'), $timezone);
		}

		if (!array_key_exists('s', $this->tokens)) {
			return new DateTime($date->format('Y-m-d H:i:59'), $timezone);
		}

		return $date;
	}
}
