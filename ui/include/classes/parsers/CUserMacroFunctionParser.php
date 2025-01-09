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
 *  A parser for function macros like "{{$MACRO}.func()}".
 */
class CUserMacroFunctionParser extends CParser {

	/**
	 * Parser for user macros.
	 *
	 * @var CUserMacroParser
	 */
	private $user_macro_parser;

	/**
	 * Parser for trigger functions.
	 *
	 * @var C10FunctionParser
	 */
	private $function_parser;

	public function __construct() {
		$this->user_macro_parser = new CUserMacroParser();
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

		if ($this->user_macro_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->user_macro_parser->getLength();

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

	/**
	 * Returns macro parser.
	 *
	 * @return string
	 */
	public function getUserMacroParser() {
		return $this->user_macro_parser;
	}

	/**
	 * Returns function parser.
	 *
	 * @return string
	 */
	public function getFunctionParser() {
		return $this->function_parser;
	}
}
