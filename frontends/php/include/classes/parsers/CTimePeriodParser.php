<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
class CTimePeriodParser extends CParser {

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$pattern_wdays = '(?P<w_from>[1-7])(-(?P<w_till>[1-7]))?';
		$pattern_hours = '(?P<h_from>[0-9]{1,2}):(?P<m_from>[0-9]{1,2})-(?P<h_till>[0-9]{1,2}):(?P<m_till>[0-9]{1,2})';

		if (!preg_match('/^'.$pattern_wdays.','.$pattern_hours.'/', substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		if (($matches['w_till'] !== '' && $matches['w_from'] > $matches['w_till'])
				|| $matches['m_from'] > 59 || $matches['m_till'] > 59) {
			return self::PARSE_FAIL;
		}

		$time_from = $matches['h_from'] * SEC_PER_HOUR + $matches['m_from'] * SEC_PER_MIN;
		$time_till = $matches['h_till'] * SEC_PER_HOUR + $matches['m_till'] * SEC_PER_MIN;

		if ($time_from >= $time_till || $time_till > 24 * SEC_PER_HOUR) {
			return self::PARSE_FAIL;
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
