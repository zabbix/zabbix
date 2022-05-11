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
 * A parser for host names and host prototype names.
 */
class CHostNameParser extends CParser {

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
	 * Parse the host name or host prototype name.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->macros = [];

		$p = $pos;

		if (isset($source[$p]) && $source[$p] === ' ') {
			return self::PARSE_FAIL;
		}

		while (isset($source[$p])) {
			if (self::parseCharacters($source, $p)) {
				continue;
			}

			if ($this->options['lldmacros'] && $this->parseLLDMacro($source, $p)) {
				continue;
			}

			break;
		}

		if ($pos == $p) {
			return self::PARSE_FAIL;
		}

		while ($source[$p - 1] === ' ') {
			$p--;
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
	 * Parse host name characters.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool
	 */
	private static function parseCharacters($source, &$pos) {
		if (!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'/', substr($source, $pos), $matches)) {
			return false;
		}

		$pos += strlen($matches[0]);

		return true;
	}

	/**
	 * Parse LLD macros.
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 *
	 * @return bool
	 */
	private function parseLLDMacro($source, &$pos) {
		if ($this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();
			$this->macros[] = $this->lld_macro_parser->getMatch();

			return true;
		}

		if ($this->lld_macro_function_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_function_parser->getLength();
			$this->macros[] = $this->lld_macro_function_parser->getMatch();

			return true;
		}

		return false;
	}
}
