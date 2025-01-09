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
 * Trigger expression converter from 2.2 to 2.4.
 */
class C20TriggerConverter extends CConverter {

	/**
	 * A parser for function macros.
	 *
	 * @var C10FunctionMacroParser
	 */
	protected $function_macro_parser;

	/**
	 * A parser for LLD macros.
	 *
	 * @var CLLDMacroParser
	 */
	protected $lld_macro_parser;

	/**
	 * An item key converter.
	 *
	 * @var C20ItemKeyConverter
	 */
	protected $itemKeyConverter;

	public function __construct() {
		$this->function_macro_parser = new C10FunctionMacroParser();
		$this->lld_macro_parser = new CLLDMacroParser();
		$this->itemKeyConverter = new C20ItemKeyConverter();
	}

	/**
	 * Convert a trigger expression from 2.2 format to 2.4.
	 *
	 * The method will replace old operators with their analogues: "&" with "and", "|" - "or" and "#" - "<>".
	 *
	 * @param string $expression
	 *
	 * @return string
	 */
	public function convert($expression) {
		// find all the operators that need to be replaced
		$found_operators = [];
		for ($pos = 0; isset($expression[$pos]); $pos++) {
			switch ($expression[$pos]) {
				case '{':
					// skip function macros
					if ($this->function_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
						$new_expression = '{'.
							$this->function_macro_parser->getHost().':'.
							$this->itemKeyConverter->convert($this->function_macro_parser->getItem()).'.'.
							$this->function_macro_parser->getFunction().
						'}';

						$expression = substr_replace($expression, $new_expression, $pos,
							$this->function_macro_parser->getLength()
						);

						$pos += strlen($new_expression) - 1;
					}
					else {
						// if it's not a function macro, try to parse it as an LLD macro
						if ($this->lld_macro_parser->parse($expression, $pos) != CParser::PARSE_FAIL) {
							$pos += $this->lld_macro_parser->getLength() - 1;
						}
					}
					// otherwise just continue as is, other macros don't contain any of these characters
					break;

				case '&':
				case '|':
				case '#':
					$found_operators[$pos] = $expression[$pos];
					break;
			}
		}

		// replace the operators
		foreach (array_reverse($found_operators, true) as $pos => $operator) {
			switch ($operator) {
				case '&':
					$expression = $this->replaceLogicalOperator($expression, 'and', $pos);

					break;
				case '|':
					$expression = $this->replaceLogicalOperator($expression, 'or', $pos);

					break;
				case '#':
					$expression = substr_replace($expression, '<>', $pos, 1);

					break;
			}
		}

		return $expression;
	}

	/**
	 * Replaces an operator at the given position and removes extra spaces around it.
	 *
	 * @param string $expression
	 * @param string $newOperator
	 * @param int $pos
	 *
	 * @return string
	 */
	protected function replaceLogicalOperator($expression, $newOperator, $pos) {
		$left = substr($expression, 0, $pos);
		$right = substr($expression, $pos + 1);

		return rtrim($left).' '.$newOperator.' '.ltrim($right);
	}

}
