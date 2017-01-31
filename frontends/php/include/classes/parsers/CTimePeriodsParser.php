<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * A parser for a list of time periods separated by a semicolon.
 */
class CTimePeriodsParser extends CParser {

	private $time_period_parser;

	public function __construct() {
		$this->time_period_parser = new CTimePeriodParser();
	}

	/**
	 * Parse the given periods separated by a semicolon.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int   $pos		Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		for (; isset($source[$p]); $p++) {
			if ($this->time_period_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->time_period_parser->getLength();
			}

			if (isset($source[$p]) && $source[$p] !== ';' && $source[$p] !== '') {
				return self::PARSE_FAIL;
			}
			else {
				continue;
			}
		}

		$this->length = $p - $pos - 1;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$p - 1]) && $source[$p - 1] === ';') ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}
}
