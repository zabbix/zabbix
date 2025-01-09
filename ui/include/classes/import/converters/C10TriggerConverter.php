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
 * A converted for converting 1.8 trigger expressions.
 */
class C10TriggerConverter extends CConverter {

	/**
	 * A parser for function macros.
	 *
	 * @var C10FunctionMacroParser
	 */
	protected $function_macro_parser;

	/**
	 * Converted used to convert simple check item keys.
	 *
	 * @var CConverter
	 */
	protected $itemKeyConverter;

	public function __construct() {
		$this->function_macro_parser = new C10FunctionMacroParser(['18_simple_checks' => true]);
		$this->itemKeyConverter = new C10ItemKeyConverter();
	}

	/**
	 * Converts simple check item keys used in trigger expressions.
	 *
	 * @param string $expression
	 *
	 * @return string
	 */
	public function convert($expression) {
		$new_expression = '';

		for ($pos = 0; isset($expression[$pos]); $pos++) {
			if ($this->function_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
				$new_expression .= '{'.
					$this->function_macro_parser->getHost().':'.
					$this->itemKeyConverter->convert($this->function_macro_parser->getItem()).'.'.
					$this->function_macro_parser->getFunction().
				'}';

				$pos += $this->function_macro_parser->getLength() - 1;
			}
			else {
				$new_expression .= $expression[$pos];
			}
		}

		return $new_expression;
	}
}
