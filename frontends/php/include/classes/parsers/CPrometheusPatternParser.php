<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * A parser for Prometheus pattern.
 */
class CPrometheusPatternParser extends CParser {

	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	private $user_macro_parser;
	private $lld_macro_parser;
	private $trim = " \t";

	public function __construct($options = []) {
		if (array_key_exists('usermacros', $options)) {
			$this->options['usermacros'] = $options['usermacros'];
		}
		if (array_key_exists('lldmacros', $options)) {
			$this->options['lldmacros'] = $options['lldmacros'];
		}

		if ($this->options['usermacros']) {
			$this->user_macro_parser = new CUserMacroParser();
		}
		if ($this->options['lldmacros']) {
			$this->lld_macro_parser = new CLLDMacroParser();
		}
	}

	/**
	 * Parse the given source string.
	 *
	 * metric { label1 =~ "value1" , label2 =" value2" } == number
	 *
	 * @param string $source  Source string that needs to be parsed.
	 * @param int    $pos     Position offset.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$p = $pos;

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		if ($this->parseMetric($source, $p) === true) {
			while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
				$p++;
			}

			$p_tmp = $p;

			if ($this->parseLabelsValues($source, $p) === true) {
				while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
					$p++;
				}

				if (isset($source[$p])) {
					$p_tmp = $p;

					if ($this->parseComparisonOperator($source, $p) === true) {
						while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
							$p++;
						}

						if ($this->parseNumber($source, $p) === false) {
							$p = $p_tmp;
						}

					}
					else {
						$p = $p_tmp;
					}
				}
			}
			elseif ($this->parseComparisonOperator($source, $p) === true) {
				while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
					$p++;
				}

				if ($this->parseNumber($source, $p) === false) {
					$p = $p_tmp;
				}
			}
			else {
				$p = $p_tmp;
			}
		}
		elseif ($this->parseLabelsValues($source, $p) === true) {
			while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
				$p++;
			}

			$p_tmp = $p;

			if ($this->parseComparisonOperator($source, $p) === true) {
				while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
					$p++;
				}

				if ($this->parseNumber($source, $p) === false) {
					$p = $p_tmp;
				}
			}
		}
		else {
			return self::PARSE_FAIL;
		}

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		if ($pos == $p) {
			return self::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parse metric parameter. Must follow the [a-zA-Z_:][a-zA-Z0-9_:]* regular expression. User macros and LLD macros
	 * are allowed.
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseMetric($source, &$pos) {
		if (preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]*/', substr($source, $pos), $matches)) {
			$pos += strlen($matches[0]);

			return true;
		}
		elseif ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->user_macro_parser->getLength();

			return true;
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();

			return true;
		}

		return false;
	}

	/**
	 * Parse label names and label values. Label name must follow the [a-zA-Z_][a-zA-Z0-9_]* regular expression. After
	 * label name, an operator must follow. Allowed operators are: = and =~ After operator a quoted value must follow.
	 * Value can contain any string and can be empty. Each label name and label value pair can be separated by a comma.
	 * Trailing comma is allowed. Spaces are trimmed before and after each unit (label name, operator, label value
	 * and comma).
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseLabelValuePairs($source, &$pos) {
		$p = $pos;

		// Parse label name.
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($source, $p), $matches)) {
			return false;
		}

		$p += strlen($matches[0]);

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		// Parse operator.
		if (!isset($source[$p]) || $source[$p] !== '=') {
			return false;
		}
		$p++;

		if (isset($source[$p]) && $source[$p] === '~') {
			$p++;
		}

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		// Parse label value.
		if (!isset($source[$p]) || $source[$p] !== '"') {
			return false;
		}
		$p++;

		while (isset($source[$p])) {
			if ($source[$p] === '"' && $source[$p - 1] !== '\\') {
				$p++;
				break;
			}
			$p++;
		}

		if ($source[$p - 1] !== '"' && $source[$p - 2] !== '\\') {
			return false;
		}

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		if (!isset($source[$p])) {
			return false;
		}

		if (isset($source[$p]) && $source[$p] === ',') {
			$p++;

			while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
				$p++;
			}

			if (isset($source[$p]) && $source[$p] !== '}') {
				$p_tmp = $p;

				// recursion
				if ($this->parseLabelValuePairs($source, $p) === false) {
					$p = $p_tmp;
				}
			}
		}

		if (!isset($source[$p])) {
			return false;
		}

		if ($pos == $p) {
			return false;
		}

		$pos = $p;

		return true;
	}

	/**
	 * Parse label names and label value pairs as one parameter that is wrapped in curly braces. Spaces are trimmed
	 * before and after each curly braces and label value pairs.
	 * and comma).
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseLabelsValues($source, &$pos) {
		$p = $pos;

		if (isset($source[$p]) && $source[$p] !== '{') {
			return false;
		}
		$p++;

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		$p_tmp = $p;

		if ($this->parseLabelValuePairs($source, $p) === false) {
			$pos = $p_tmp;

			return false;
		}

		while (isset($source[$p]) && strpos($this->trim, $source[$p]) !== false) {
			$p++;
		}

		if (isset($source[$p]) && $source[$p] !== '}') {
			return false;
		}
		$p++;

		$pos = $p;

		return true;
	}

	/**
	 * Parse number. It can be with plus or minus sign, can use scientific notation, decimals points, can even be
	 * not a number or infinity. User and LLD macros are allowed.
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseNumber($source, &$pos) {
		if (preg_match('/^(\+|\-)?([0-9]+(\.[0-9]*)?|\.[0-9]+)([E|e][+|-]?[0-9]+)?/', substr($source, $pos),
				$matches)) {
			$pos += strlen($matches[0]);

			return true;
		}
		elseif (substr($source, $pos, 4) === '+Inf' || substr($source, $pos, 4) === '-Inf') {
			$pos += 4;

			return true;
		}
		elseif (substr($source, $pos, 3) === 'Nan') {
			$pos += 3;

			return true;
		}
		elseif ($this->options['usermacros'] && $this->user_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->user_macro_parser->getLength();

			return true;
		}
		elseif ($this->options['lldmacros'] && $this->lld_macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
			$pos += $this->lld_macro_parser->getLength();

			return true;
		}

		return false;
	}

	/**
	 * Parse the comparison operator. Currently only one comparison operator is allowed: ==
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private function parseComparisonOperator($source, &$pos) {
		if (isset($source[$pos]) && substr($source, $pos, 2) === '==') {
			$pos += 2;

			return true;
		}

		return false;
	}
}
