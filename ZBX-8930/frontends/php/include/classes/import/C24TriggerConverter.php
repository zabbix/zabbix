<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Trigger expression converter from 2.2 to 2.4.
 */
class C24TriggerConverter {

	/**
	 * A parser for function macros.
	 *
	 * @var CFunctionMacroParser
	 */
	protected $functionMacroParser;

	/**
	 * A parser for LLD macros.
	 *
	 * @var CMacroParser
	 */
	protected $lldMacroParser;

	/**
	 * @param CFunctionMacroParser  $functionMacroParser
	 * @param CMacroParser          $lldMacroParser
	 */
	public function __construct(CFunctionMacroParser $functionMacroParser, CMacroParser $lldMacroParser) {
		$this->functionMacroParser = $functionMacroParser;
		$this->lldMacroParser = $lldMacroParser;
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
		// don't try to parse the expression if there's nothing to replace
		if (strpos($expression, '#') === false
				&& strpos($expression, '&') === false
				&& strpos($expression, '|') === false) {

			return $expression;
		}

		// find all the operators that need to be replaced
		$foundOperators = array();
		$pos = 0;
		while (isset($expression[$pos])) {
			switch ($expression[$pos]) {
				case '{':
					// skip function macros
					$result = $this->functionMacroParser->parse($expression, $pos);

					// if it's not a function macro, try to parse it as an LLD macro
					if (!$result) {
						$result = $this->lldMacroParser->parse($expression, $pos);
					}

					if ($result) {
						$pos += $result->length - 1;
					}

					// otherwise just continue as is, other macros don't contain any of these characters
					break;
				case '&':
				case '|':
				case '#':
					$foundOperators[$pos] = $expression[$pos];

					break;
			}

			$pos++;
		}

		// replace the operators
		foreach (array_reverse($foundOperators, true) as $pos => $operator) {
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
