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
 * A parser for a list of time periods separated by a semicolon.
 */
class CTimePeriodsParser extends CParser {

	private $time_period_parser;
	private $user_macro_parser;

	private $periods = [];
	private $options = ['user_macros' => true];

	public function __construct($options = []) {
		if (array_key_exists('user_macros', $options)) {
			$this->options['user_macros'] = $options['user_macros'];
		}

		$this->time_period_parser = new CTimePeriodParser();

		if ($this->options['user_macros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
	}

	/**
	 * Parse the given periods separated by a semicolon.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->periods = [];

		$periods = [];
		$p = $pos;

		while (isset($source[$p])) {
			if ($this->time_period_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->time_period_parser->getLength();
				$periods[] = $this->time_period_parser->getMatch();
			}
			elseif ($this->options['user_macros'] && $this->user_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->user_macro_parser->getLength();
				$periods[] = $this->user_macro_parser->getMatch();
			}
			else {
				return self::PARSE_FAIL;
			}

			if (isset($source[$p])) {
				if ($source[$p] === ';') {
					$p++;
				}
				else {
					return self::PARSE_FAIL;
				}
			}
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->periods = $periods;

		return self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve the time periods.
	 *
	 * @return array
	 */
	public function getPeriods() {
		return $this->periods;
	}
}
