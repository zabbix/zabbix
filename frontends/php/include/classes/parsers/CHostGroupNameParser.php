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
 * A parser for host group names and host prototype group names.
 */
class CHostGroupNameParser extends CParser {

	private $options = [
		'lldmacros' => false
	];

	/**
	 * Array of macros found in string.
	 *
	 * @var array
	 */
	private $macros = [];

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

	public function __construct($options = []) {
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
			$this->lld_macro_function_parser = new CLLDMacroFunctionParser();
		}
	}

	/**
	 * Parse the host group name or host prototype group name.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->macros = [];

		$p = $pos;

		// Check first chacater. Cannot be /.
		if (isset($source[$p]) && $source[$p] === '/') {
			return self::PARSE_FAIL;
		}

		while (isset($source[$p])) {
			if ($this->parseConstant($source, $p) === false) {
				break;
			}
		}

		// Check last character. Cannot be /.
		if (isset($source[$p - 1]) && $source[$p - 1] === '/') {
			$p--;
		}

		if ($pos == $p) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Retrieve macros found in source string.
	 *
	 * @return array
	 */
	public function getMacros() {
		return $this->macros;
	}

	/**
	 * Retrieve matching group name.
	 *
	 * @return array
	 */
	public function getMatch() {
		return $this->match;
	}

	/**
	 * Parse LLD macro or any character.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool
	 */
	private function parseConstant($source, &$pos) {
		if ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();
			$this->macros[] = $this->lld_macro_parser->getMatch();

			return true;
		}

		if ($this->options['lldmacros']
				&& $this->lld_macro_function_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_function_parser->getLength();
			$this->macros[] = $this->lld_macro_function_parser->getMatch();

			return true;
		}

		return $this->parseCharacter($source, $pos);
	}

	/**
	 * Parse group name characters.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool
	 */
	private function parseCharacter($source, &$pos) {
		// Accept any chacater, but check previous character. Name cannot contain two slashes next to one another.
		if ($source[$pos] === '/' && isset($source[$pos - 1]) && $source[$pos - 1] === '/') {
			$pos--;

			return false;
		}

		$pos++;

		return true;
	}
}
