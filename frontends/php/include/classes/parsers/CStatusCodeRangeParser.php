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
 * A parser for status codes.
 */
class CStatusCodeRangeParser extends CParser {

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
	 * Array of status codes.
	 *
	 * @var array
	 */
	private $status_codes = [];

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
	 * Parse the given status code or range.
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
		$this->length = 0;
		$this->match = '';
		$this->status_codes = [];

		$status_codes = [];
		$p = $pos;

		while (isset($source[$p])) {
			if ($this->options['usermacros']
					&& $this->user_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->user_macro_parser->getLength();
				$status_codes[] = $this->user_macro_parser->getMatch();
			}
			elseif ($this->options['lldmacros']
					&& $this->lld_macro_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->lld_macro_parser->getLength();
				$status_codes[] = $this->lld_macro_parser->getMatch();
			}
			elseif ($this->options['lldmacros']
					&& $this->lld_macro_function_parser->parse($source, $p) != self::PARSE_FAIL) {
				$p += $this->lld_macro_function_parser->getLength();
				$status_codes[] = $this->lld_macro_function_parser->getMatch();
			}
			elseif (($digits = self::parseDigits($source, $p)) !== false) {
				$p += $digits['pos'];
				$status_codes[] = $digits['match'];
			}
			elseif ($source[$p] === '-' && $p != $pos && isset($source[$p - 1]) && $source[$p - 1] !== '-'
					&& count($status_codes) < 2) {
				$p++;
			}
			else {
				break;
			}
		}

		if ($p == $pos) {
			return self::PARSE_FAIL;
		}

		if (isset($source[$p - 1]) && $source[$p - 1] === '-') {
			$p--;
		}

		$this->length = $p - $pos;

		$this->match = substr($source, $pos, $this->length);
		$this->status_codes = $status_codes;

		return (isset($source[$p]) || count($status_codes) > 2) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve the status codes.
	 *
	 * @return array
	 */
	public function getStatusCodes() {
		return $this->status_codes;
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
