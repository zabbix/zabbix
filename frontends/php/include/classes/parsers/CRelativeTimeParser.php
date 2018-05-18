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
 * A parser for relative time in "now[/<yMwdhm>][<+->N<yMwdhms>[/<yMwdhm>]]" format.
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

		if (!self::parseRelativeTime($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse relative time.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private static function parseRelativeTime($source, &$pos) {
		$pattern_precision = '\/[yMwdhm]';
		$pattern_precision1 = '(?P<precision1>'.$pattern_precision.')';
		$pattern_precision2 = '(?P<precision2>'.$pattern_precision.')';
		$pattern_offset = '(?P<offset>[0-9]+[yMwdhms]?)';
		$pattern = 'now'.$pattern_precision1.'?([+-]'.$pattern_offset.$pattern_precision2.'?)?';

		if (!preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
