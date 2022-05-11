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
 *  A parser for function macros like "{?<trigger expression>}".
 */
class CExpressionMacroParser extends CParser {

	/**
	 * @var CExpressionParser
	 */
	private $expression_parser;

	/**
	 * @var string
	 */
	private $error = '';

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false    Enable user macros usage in expression.
	 *   'lldmacros' => false     Enable low-level discovery macros usage in expression.
	 *   'host_macro' => false    Allow {HOST.HOST} macro as host name part in the query.
	 *   'host_macro_n' => false  Allow {HOST.HOST} and {HOST.HOST<1-9>} macros as host name part in the query.
	 *   'empty_host' => false    Allow empty hostname in the query string.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'host_macro' => false,
		'host_macro_n' => false,
		'empty_host' => false
	];

	/**
	 * Set up necessary parsers.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		$this->expression_parser = new CExpressionParser([
			'usermacros' => $this->options['usermacros'],
			'lldmacros' => $this->options['lldmacros'],
			'host_macro' => $this->options['host_macro'],
			'host_macro_n' => $this->options['host_macro_n'],
			'empty_host' => $this->options['empty_host']
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
		$this->error = '';

		$p = $pos;

		if (substr($source, $p, 2) !== '{?') {
			return CParser::PARSE_FAIL;
		}
		$p += 2;

		switch ($this->expression_parser->parse($source, $p)) {
			case CParser::PARSE_SUCCESS_CONT:
				$this->error = $this->expression_parser->getError();
				break;

			case CParser::PARSE_FAIL:
				$this->error = $this->expression_parser->getError();
				return CParser::PARSE_FAIL;
		}
		$p += $this->expression_parser->getLength();

		while (isset($source[$p]) && strpos(CExpressionParser::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if (!isset($source[$p])) {
			$this->error = _('unexpected end of expression macro');
		}

		if (!isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->tokens = $this->expression_parser->getResult()->getTokens();

		return (isset($source[$p]) ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS);
	}

	/**
	 * Returns the error message if the expression macro is invalid.
	 *
	 * @return string
	 */
	public function getError(): string {
		return $this->error;
	}

	/**
	 * Returns the expression parser.
	 *
	 * @return CExpressionParser
	 */
	public function getExpressionParser(): CExpressionParser {
		return $this->expression_parser;
	}
}
