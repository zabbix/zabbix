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
 *  A parser for function macros like "{{?<trigger expression>}.func()}".
 */
class CExpressionMacroFunctionParser extends CParser {

	/**
	 * @var CExpressionMacroParser
	 */
	protected $expression_macro_parser;

	/**
	 * @var C10FunctionParser
	 */
	protected $function_parser;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false    Enable user macros usage in expression.
	 *   'lldmacros' => false     Enable low-level discovery macros usage in expression.
	 *   'host_macro' => false    Allow {HOST.HOST} macro as host name part in the query.
	 *   'host_macro_n' => false  Allow {HOST.HOST} and {HOST.HOST<1-9>} macros as host name part in the query.
	 *   'empty_host' => false    Allow empty hostname in the query string.
	 *   'macros_n' => []         Array of strings having supported reference macros.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'host_macro' => false,
		'host_macro_n' => false,
		'empty_host' => false,
		'macros_n' => []
	];

	/**
	 * Set up necessary parsers.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->expression_macro_parser = new CExpressionMacroParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros'],
			'host_macro' => $this->options['host_macro'],
			'host_macro_n' => $this->options['host_macro_n'],
			'empty_host' => $this->options['empty_host'],
			'macros_n' => $this->options['macros_n']
		]);
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
			return CParser::PARSE_FAIL;
		}
		$p++;

		if ($this->expression_macro_parser->parse($source, $p) != CParser::PARSE_SUCCESS_CONT) {
			return CParser::PARSE_FAIL;
		}
		$p += $this->expression_macro_parser->getLength();

		if ($source[$p] !== '.') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		if ($this->function_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}
		$p += $this->function_parser->getLength();

		if (!isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$p]) ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS);
	}

	/**
	 * Returns the expression parser.
	 *
	 * @return CExpressionMacroParser
	 */
	public function getExpressionMacroParser(): CExpressionMacroParser {
		return $this->expression_macro_parser;
	}

	/**
	 * Returns function parser.
	 *
	 * @return C10FunctionParser
	 */
	public function getFunctionParser() {
		return $this->function_parser;
	}
}
