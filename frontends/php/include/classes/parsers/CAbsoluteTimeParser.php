<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
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
		$pattern_M = '(?P<M>[0-9]{1,2})';
		$pattern_D = '(?P<D>[0-9]{1,2})';
		$pattern_h = '(?P<h>[0-9]{1,2})';
		$pattern_m = '(?P<m>[0-9]{1,2})';
		$pattern_s = '(?P<s>[0-9]{1,2})';
		$pattern_date = $pattern_Y.'(-'.$pattern_M.'(-'.$pattern_D.')?)?';
		$pattern_time = '( +'.$pattern_h.'(:'.$pattern_m.'(:'.$pattern_s.')?)?)?';
		$pattern = $pattern_date.$pattern_time;

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$matches += ['M' => 1, 'D' => 1, 'h' => 0, 'm' => 0, 's' => 0];

		if ($matches['M'] === '') {
			$matches['M'] = 1;
		}

		if ($matches['D'] === '') {
			$matches['D'] = 1;
		}

		if (1 > $matches['M'] || $matches['M'] > 12 || 1 > $matches['D'] || $matches['D'] > 31) {
			return false;
		}

		if ($matches['h'] > 23 || $matches['m'] > 59 || $matches['s'] > 59) {
			return false;
		}

		$date = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches['Y'], $matches['M'], $matches['D'], $matches['h'],
			$matches['m'], $matches['s']
		);

		if (date_create($date) === false) {
			return false;
		}

		$this->date = $date;
		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Returns date in "YYYY-MM-DD hh:mm:ss" format.
	 *
	 * @return DateTime|null
	 */
	public function getDateTime() {
		if ($this->date === '') {
			return null;
		}

		return new DateTime($this->date);
	}
}
