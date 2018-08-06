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
	 * Array of ranges.
	 *
	 * @var array
	 */
	private $ranges = [];

	/**
	 * Options to initialize other parsers.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	/**
	 * Source string to parse.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Position in source string.
	 *
	 * @var string
	 */
	private $pos;

	/**
	 * @param array $options   An array of options to initialize other parsers.
	 */
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
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->source = $source;
		$this->pos = $pos;
		$this->length = 0;
		$this->match = '';
		$this->ranges = [];

		// Skip spaces, tabs and new lines.
		$trim = [' ', "\t", "\n", "\r"];

		while (isset($this->source[$this->pos]) && in_array($this->source[$this->pos], $trim, true)) {
			$this->pos++;
		}

		if ($this->parseConstant() === false) {
			return CParser::PARSE_FAIL;
		}

		while (isset($this->source[$this->pos]) && in_array($this->source[$this->pos], $trim, true)) {
			$this->pos++;
		}

		if (isset($this->source[$this->pos]) && $this->source[$this->pos] === '-') {
			$p = $this->pos;
			$this->pos++;

			while (isset($this->source[$this->pos]) && in_array($this->source[$this->pos], $trim, true)) {
				$this->pos++;
			}

			if ($this->parseConstant() === false) {
				$this->pos = $p;
			}
			else {
				while (isset($this->source[$this->pos]) && in_array($this->source[$this->pos], $trim, true)) {
					$this->pos++;
				}
			}
		}

		if ($pos == $this->pos) {
			return self::PARSE_FAIL;
		}

		$this->length = $this->pos - $pos;
		$this->match = substr($this->source, $pos, $this->length);

		return isset($this->source[$this->pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve the ranges.
	 *
	 * @return array
	 */
	public function getRanges() {
		return $this->ranges;
	}

	/**
	 * Parse user macro, or LLD macro or digits.
	 *
	 * @return bool
	 */
	private function parseConstant() {
		if ($this->options['usermacros']
				&& $this->user_macro_parser->parse($this->source, $this->pos) != self::PARSE_FAIL) {
			$this->pos += $this->user_macro_parser->getLength();
			$this->ranges[] = $this->user_macro_parser->getMatch();

			return true;
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_parser->parse($this->source, $this->pos) != self::PARSE_FAIL) {
			$this->pos += $this->lld_macro_parser->getLength();
			$this->ranges[] = $this->lld_macro_parser->getMatch();

			return true;
		}
		elseif ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($this->source, $this->pos) != self::PARSE_FAIL) {
			$this->pos += $this->lld_macro_function_parser->getLength();
			$this->ranges[] = $this->lld_macro_function_parser->getMatch();

			return true;
		}
		elseif (($digits = self::parseDigits($this->source, $this->pos)) !== false) {
			$this->pos += $digits['pos'];
			$this->ranges[] = $digits['match'];

			return true;
		}

		return false;
	}

	/**
	 * Parse digits.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool|array     Returns false if non-numeric character found else returns array of position and match.
	 */
	private static function parseDigits($source, $pos) {
		if (!preg_match('/^([0-9]+)/', substr($source, $pos), $matches)) {
			return false;
		}

		return [
			'pos' => strlen($matches[0]),
			'match' => $matches[0]
		];
	}
}
