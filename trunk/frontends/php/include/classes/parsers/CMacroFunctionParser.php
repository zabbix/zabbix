<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 *  A parser for function macros like "{{ITEM.VALUE}.func()}".
 */
class CMacroFunctionParser extends CParser {

	/**
	 * Parser for generic macros.
	 *
	 * @var CMacroParser
	 */
	private $macro_parser;

	/**
	 * Parser for trigger functions.
	 *
	 * @var CFunctionParser
	 */
	private $function_parser;

	/**
	 * @param array $macros  the list of macros, for example ['{ITEM.VALUE}', '{ITEM.LASTVALUE}']
	 * @param array $options
	 */
	public function __construct(array $macros, array $options = []) {
		$this->macro_parser = new CMacroParser($macros, $options);
		$this->function_parser = new CFunctionParser();
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

		if ($this->macro_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->macro_parser->getLength();

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
	public function getMacroParser() {
		return $this->macro_parser;
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
