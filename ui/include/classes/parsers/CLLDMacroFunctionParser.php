<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
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
 *  A parser for function macros like "{{#LLD}.func()}".
 */
class CLLDMacroFunctionParser extends CParser {

	/**
	 * Parser for LLD macros.
	 *
	 * @var CLLDMacroParser
	 */
	private $lld_macro_parser;

	/**
	 * Parser for trigger functions.
	 *
	 * @var C10FunctionParser
	 */
	private $function_parser;

	/**
	 * Set up necessary parsers.
	 */
	public function __construct() {
		$this->lld_macro_parser = new CLLDMacroParser();
		$this->function_parser = new C10FunctionParser();
	}

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->lld_macro_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->lld_macro_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '.') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->function_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->function_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '}') {
			return self::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
