<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 *  A parser for function macros like "{?<trigger expression>}".
 */
class CExpressionMacroParser extends CParser {

	/**
	 * @var CExpressionParser
	 */
	protected $expression_parser;

	/**
	 * Set up necessary parsers.
	 */
	public function __construct() {
		$this->expression_parser = new CExpressionParser([
			'host_macro' => ['{HOST.HOST}'],
			'lldmacros' => true
		]);
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

		if (substr($source, $p, 2) !== '{?') {
			return CParser::PARSE_FAIL;
		}
		$p += 2;

		if ($this->expression_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}
		$p += $this->expression_parser->getLength();;

		while (isset($source[$p]) && strpos(CExpressionParser::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if (!isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$p]) ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS);
	}
}
