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
class CTimePeriodParser extends CParser {

	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	private $user_macro_parser;
	private $lld_macro_parser;

	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
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

		$p = $pos;

		if ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->user_macro_parser->getLength();
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
			$p += $this->lld_macro_parser->getLength();
		}
		elseif (!self::parseTimePeriod($source, $p)) {
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
	private static function parseTimePeriod($source, &$pos) {
		$pattern_wdays = '(?P<w_from>[1-7])(-(?P<w_till>[1-7]))?';
		$pattern_hours = '(?P<h_from>[0-9]{1,2}):(?P<m_from>[0-9]{2})-(?P<h_till>[0-9]{1,2}):(?P<m_till>[0-9]{2})';

		if (!preg_match('/^'.$pattern_wdays.','.$pattern_hours.'/', substr($source, $pos), $matches)) {
			return false;
		}

		if (($matches['w_till'] !== '' && $matches['w_from'] > $matches['w_till'])
				|| $matches['m_from'] > 59 || $matches['m_till'] > 59) {
			return false;
		}

		$time_from = $matches['h_from'] * SEC_PER_HOUR + $matches['m_from'] * SEC_PER_MIN;
		$time_till = $matches['h_till'] * SEC_PER_HOUR + $matches['m_till'] * SEC_PER_MIN;

		if ($time_from >= $time_till || $time_till > 24 * SEC_PER_HOUR) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}
}
