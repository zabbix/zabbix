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
 * A parser for time period.
 */
class CRelativeTimeParser extends CParser {

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!self::parseAbsoluteDate($source, $p) && !self::parseRelativeDate($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse absolute date in "YYYY[-MM[-DD]][ hh[:mm[:ss]]]" format.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private static function parseAbsoluteDate($source, &$pos) {
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

		if (!array_key_exists('M', $matches) || $matches['M'] === '') {
			$matches['M'] = 1;
		}

		if (!array_key_exists('D', $matches) || $matches['D'] === '') {
			$matches['D'] = 1;
		}

		if (!array_key_exists('h', $matches)) {
			$matches['h'] = 0;
		}

		if (!array_key_exists('m', $matches)) {
			$matches['m'] = 0;
		}

		if (!array_key_exists('s', $matches)) {
			$matches['s'] = 0;
		}

		if (1 > $matches['M'] || $matches['M'] > 12 || 1 > $matches['D'] || $matches['D'] > 31) {
			return false;
		}

		if ($matches['h'] > 24 || $matches['m'] > 59 || $matches['s'] > 59) {
			return false;
		}

		$date = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches['Y'], $matches['M'], $matches['D'], $matches['h'],
			$matches['m'], $matches['s']
		);

		if (date_create($date) === false) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Parse relative date in "now[/<yMwdhm>][<+->N<yMwdhms>[/<yMwdhm>]] format".
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private static function parseRelativeDate($source, &$pos) {
		$pattern_precision = '\/[yMwdhm]';
		$pattern_precision1 = '(?P<precision1>'.$pattern_precision.')';
		$pattern_precision2 = '(?P<precision2>'.$pattern_precision.')';
		$pattern_offset = '(?P<offset>[0-9]+[yMwdhms])';
		$pattern = 'now'.$pattern_precision1.'?([+-]'.$pattern_offset.$pattern_precision2.'?)?';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
