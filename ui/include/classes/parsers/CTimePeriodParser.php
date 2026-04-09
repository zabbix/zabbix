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


/**
 * A parser for time period.
 */
class CTimePeriodParser extends CParser {

	private array $macro_parsers = [];

	private array $period_parts = [];

	private array $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	public function __construct($options = []) {
		$this->options = $options + $this->options;

		if ($this->options['usermacros']) {
			array_push($this->macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}
		if ($this->options['lldmacros']) {
			array_push($this->macro_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
		}
	}

	/**
	 * Parse the given period.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->period_parts = [];

		$p = $pos;

		foreach ($this->macro_parsers as $macro_parser) {
			if ($macro_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $macro_parser->getLength();
				break;
			}
		}

		if ($p == $pos && !$this->parseTimePeriod($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse time period.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private function parseTimePeriod($source, &$pos) {
		$pattern_wdays = '(?P<wd_from>[1-7])(-(?P<wd_till>[1-7]))?';
		$pattern_hours = '(?P<h_from>[0-9]{1,2}):(?P<m_from>[0-9]{2})-(?P<h_till>[0-9]{1,2}):(?P<m_till>[0-9]{2})';

		if (!preg_match('/^'.$pattern_wdays.','.$pattern_hours.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$matches['wd_till'] = $matches['wd_till'] ?: $matches['wd_from'];

		if ($matches['wd_from'] > $matches['wd_till'] || $matches['m_from'] > 59 || $matches['m_till'] > 59) {
			return false;
		}

		$time_from = $matches['h_from'] * SEC_PER_HOUR + $matches['m_from'] * SEC_PER_MIN;
		$time_till = $matches['h_till'] * SEC_PER_HOUR + $matches['m_till'] * SEC_PER_MIN;

		if ($time_from >= $time_till || $time_till > 24 * SEC_PER_HOUR) {
			return false;
		}

		$this->period_parts = array_filter($matches, static fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);

		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Retrieve the time period parts.
	 *
	 * @return array
	 */
	public function getPeriodParts(): array {
		return $this->period_parts;
	}
}
