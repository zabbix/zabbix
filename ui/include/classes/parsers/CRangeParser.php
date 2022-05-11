<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * A parser for ranges like status codes.
 */
class CRangeParser extends CParser {

	/**
	 * User macro parser.
	 *
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * LLD macro parser.
	 *
	 * @var CLLDMacroParser
	 */
	private $lld_macro_parser;

	/**
	 * LLD macro function parser.
	 *
	 * @var CLLDMacroFunctionParser
	 */
	private $lld_macro_function_parser;

	/**
	 * Number parser.
	 *
	 * @var CNumberParser
	 */
	private $number_parser;

	/**
	 * Array of range strings. Range value with suffix will be stored as string of calculated value, value "1K"
	 * will be stored as "1024".
	 *
	 * @var array
	 */
	private $range = [];

	/**
	 * Options to initialize other parsers.
	 *
	 * usermacros   Allow usermacros in ranges.
	 * lldmacros    Allow lldmacros in ranges.
	 * with_minus   Allow negative ranges.
	 * with_float   Allow float number ranges.
	 * with_suffix  Allow number ranges with size and time suffixes.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'with_minus' => false,
		'with_float' => false,
		'with_suffix' => false
	];

	/**
	 * @param array $options   An array of options to initialize other parsers.
	 */
	public function __construct($options = []) {
		$this->options = $options + $this->options;
		$this->number_parser = new CNumberParser([
			'with_minus' => $this->options['with_minus'],
			'with_float' => $this->options['with_float'],
			'with_size_suffix' => $this->options['with_suffix'],
			'with_time_suffix' => $this->options['with_suffix']
		]);

		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
	}

	/**
	 * Parse the given range.
	 * Examples:
	 *   200
	 *   400-500
	 *   {$M}
	 *   {$M}-{$M}
	 *   {#M}-{#M}
	 *   {$M}-{{#M}.regsub("^([0-9]+)", "{#M}: \1")}
	 *   -200--10
	 *   -2.5--1.35
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->range = [];

		// Skip spaces, tabs and new lines.
		$trim = " \t\n\r";
		$p = $pos;

		while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
			$p++;
		}

		if ($this->parseConstant($source, $p) === false) {
			return CParser::PARSE_FAIL;
		}

		while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
			$p++;
		}

		if (isset($source[$p]) && $source[$p] === '-') {
			$p_tmp = $p;
			$p++;

			while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
				$p++;
			}

			if ($this->parseConstant($source, $p) === false) {
				$p = $p_tmp;
			}
			else {
				while (isset($source[$p]) && strpos($trim, $source[$p]) !== false) {
					$p++;
				}
			}
		}

		if ($pos == $p) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve the range.
	 *
	 * @return array
	 */
	public function getRange() {
		return $this->range;
	}

	/**
	 * Parse user macro, or LLD macro or digits.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool
	 */
	private function parseConstant($source, &$pos) {
		if ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->user_macro_parser->getLength();
			$this->range[] = $this->user_macro_parser->getMatch();

			return true;
		}

		if ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();
			$this->range[] = $this->lld_macro_parser->getMatch();

			return true;
		}

		if ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_function_parser->getLength();
			$this->range[] = $this->lld_macro_function_parser->getMatch();

			return true;
		}

		return $this->parseDigits($source, $pos);
	}

	/**
	 * Parse digits.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool|array     Returns false if non-numeric character found else returns array of position and match.
	 */
	private function parseDigits($source, &$pos) {
		if ($this->number_parser->parse($source, $pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$value = $this->number_parser->calcValue();

		if ($value > ZBX_MAX_INT32) {
			return false;
		}

		// Second value must be greater than or equal to first one.
		if ($this->range && is_numeric($this->range[0]) && $this->range[0] > $value) {
			return false;
		}

		$pos += $this->number_parser->getLength();
		$this->range[] = ($this->number_parser->getSuffix() === null)
			? $this->number_parser->getMatch()
			: strval($value);

		return true;
	}
}
