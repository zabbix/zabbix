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
 * A parser for scheduling intervals.
 */
class CSchedulingIntervalParser extends CParser {

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
	 * Parse the given scheduled interval.
	 *
	 * @param string $source	Source string that needs to be parsed.
	 * @param int    $pos		Position offset.
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
		elseif (!self::parseIntervals($source, $p)) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse multiple intervals.
	 *
	 * @param string	$source
	 * @param int		$pos
	 *
	 * @return bool
	 */
	private static function parseIntervals($source, &$pos) {
		$p = $pos;

		$precedence = 0;
		$prefixes = [
			0 => ['prefix' => 'md', 'min' => 1, 'max' => 31],
			1 => ['prefix' => 'wd', 'min' => 1, 'max' => 7],
			2 => ['prefix' => 'h', 'min' => 0, 'max' => 23],
			3 => ['prefix' => 'm', 'min' => 0, 'max' => 59],
			4 => ['prefix' => 's', 'min' => 0, 'max' => 59]
		];

		while (isset($source[$p])) {
			for ($i = $precedence; $i < count($prefixes); $i++) {
				$prefix = $prefixes[$i];

				if (self::parseInterval($source, $p, $prefix['prefix'], $prefix['min'], $prefix['max'])) {
					$precedence = $i + 1;
					continue 2;
				}
			}
			break;
		}

		$ret = ($p != $pos);
		$pos = $p;

		return $ret;
	}

	/**
	 * Parse single interval.
	 *
	 * @param string	$source
	 * @param int		$pos
	 * @param string	$prefix
	 * @param int		$min
	 * @param int		$max
	 *
	 * @return bool
	 */
	private static function parseInterval($source, &$pos, $prefix, $min, $max) {
		$p = $pos;
		$len = strlen($prefix);

		if (substr($source, $p, $len) !== $prefix) {
			return false;
		}
		$p += $len;

		if (!self::parseFilter($source, $p, $min, $max)) {
			return false;
		}

		$pos = $p;

		return true;
	}

	/**
	 * Detect and move position at the end of filter.
	 */
	private static function parseFilter($source, &$pos, $min, $max) {
		$p = $pos;

		$max_digits = strlen((string) $max);
		$pattern_range = '(?<from>[0-9]{1,'.$max_digits.'})(-(?P<to>[0-9]{1,'.$max_digits.'}))?';
		$pattern_step = '\/(?P<step>[0-9]{1,'.$max_digits.'})';
		$delimiter = '';

		while (isset($source[$p])) {
			$len = 0;

			if (preg_match('/^'.$delimiter.$pattern_range.'/', substr($source, $p), $matches)) {
				$from = $matches['from'];
				$to = (array_key_exists('to', $matches) && $matches['to'] !== '') ? $matches['to'] : $from;

				if ($from < $min || $to > $max || $from > $to) {
					break;
				}

				$len += strlen($matches[0]);
				$delimiter = '';
			}
			else {
				$from = $min;
				$to = $max;
			}

			if (preg_match('/^'.$delimiter.$pattern_step.'/', substr($source, $p + $len), $matches)) {
				if ($matches['step'] >= 1 && $matches['step'] <= $to - $from) {
					$len += strlen($matches[0]);
				}
			}

			if ($len == 0) {
				break;
			}

			$p += $len;
			$delimiter = ',';
		}

		$ret = ($p != $pos);
		$pos = $p;

		return $ret;
	}
}
