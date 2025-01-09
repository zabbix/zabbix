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
 * A parser for Prometheus pattern.
 */
class CPrometheusPatternParser extends CParser {

	/**
	 * @var array
	 */
	private $macro_parsers = [];

	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	public function __construct($options = []) {
		$this->options = $options + $this->options;

		if ($this->options['usermacros']) {
			array_push($this->macro_parsers, new CUserMacroParser, new CUserMacroFunctionParser);
		}
		if ($this->options['lldmacros']) {
			array_push($this->macro_parsers, new CLLDMacroParser, new CLLDMacroFunctionParser);
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

		$has_metric_name = false;

		$p = $pos;

		if ($this->parseMetric($source, $p)) {
			$has_metric_name = true;
		}

		$p_tmp = $p;

		if ($has_metric_name) {
			self::skipWhitespaces($source, $p_tmp);
		}

		if ($this->parseLabelsValues($source, $p_tmp, $has_metric_name)) {
			$p = $p_tmp;
		}
		elseif (!$has_metric_name) {
			return self::PARSE_FAIL;
		}

		$p_tmp = $p;

		self::skipWhitespaces($source, $p_tmp);

		if ($this->parseComparisonOperator($source, $p_tmp)) {
			self::skipWhitespaces($source, $p_tmp);

			if ($this->parseNumber($source, $p_tmp)) {
				$p = $p_tmp;
			}
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Move pointer at the end of whitespaces.
	 *
	 * @param string $source  [IN]      Source string that needs to be parsed.
	 * @param int    $pos     [IN/OUT]  Position offset.
	 *
	 * @return bool
	 */
	private static function skipWhitespaces($source, &$pos) {
		while (isset($source[$pos]) && ($source[$pos] === ' ' || $source[$pos] === "\t")) {
			$pos++;
		}
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

		foreach ($this->macro_parsers as $macro_parser) {
			if ($macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
				$pos += $macro_parser->getLength();
				return true;
			}
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
	 * @param string $source            [IN]     Source string that needs to be parsed.
	 * @param int    $pos               [IN/OUT] Position offset.
	 * @param bool   $has_metric_label  [IN/OUT] Returns true if __name__ is present.
	 *
	 * @return bool
	 */
	private function parseLabelValuePair($source, &$pos, &$has_metric_label) {
		$p = $pos;

		// Parse label name.
		if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', substr($source, $p), $matches)) {
			if ($matches[0] === '__name__') {
				if ($has_metric_label) {
					return false;
				}

				$has_metric_label = true;
			}

			$p += strlen($matches[0]);
		}
		else {
			foreach ($this->macro_parsers as $macro_parser) {
				if ($macro_parser->parse($source, $p) != self::PARSE_FAIL) {
					$p += $macro_parser->getLength();
					break;
				}
			}
		}

		if ($p == $pos) {
			return false;
		}

		self::skipWhitespaces($source, $p);

		// Parse operator.
		if (!isset($source[$p]) || !isset($source[$p + 1])) {
			// Even if $p + 1 is not part of the operator, we still must have a character there
			return false;
		}

		if ($source[$p] === '=') {
			if ($source[$p + 1] === '~') {
				$p += 2; // =~
			} else {
				$p += 1; // =
			}
		}
		elseif ($source[$p] === '!') {
			if ($source[$p + 1] !== '=' && $source[$p + 1] !== '~') {
				return false;
			}
			$p += 2; // != or !~
		}
		else {
			return false;
		}

		self::skipWhitespaces($source, $p);

		// Parse label value.
		if (!isset($source[$p]) || $source[$p] !== '"') {
			return false;
		}
		$p++;

		while (isset($source[$p])) {
			switch ($source[$p]) {
				case '\\':
					switch (isset($source[$p + 1]) ? $source[$p + 1] : null) {
						case '\\':
						case '"':
						case 'n':
							$p++;
							break;
						default:
							return false;
					}
					break;

				case '"':
					break 2;
			}
			$p++;
		}

		if (!isset($source[$p]) || $source[$p] !== '"') {
			return false;
		}
		$p++;

		$pos = $p;

		return true;
	}

	/**
	 * Parse label names and label value pairs as one parameter that is wrapped in curly braces. Spaces are trimmed
	 * before and after each curly braces and label value pairs. Two __name__ labels are not allowed.
	 *
	 * @param string $source           [IN]     Source string that needs to be parsed.
	 * @param int    $pos              [IN/OUT] Position offset.
	 * @param bool   $has_metric_name  [IN]     Metric name is present in the pattern.
	 *
	 * @return bool
	 */
	private function parseLabelsValues($source, &$pos, $has_metric_name) {
		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return false;
		}
		$p++;

		$has_metric_label = false;

		while (true) {
			self::skipWhitespaces($source, $p);

			if (!$this->parseLabelValuePair($source, $p, $has_metric_label)) {
				break;
			}

			self::skipWhitespaces($source, $p);

			if (!isset($source[$p]) || $source[$p] !== ',') {
				break;
			}
			$p++;
		}

		if ($has_metric_name && $has_metric_label) {
			return false;
		}

		if (!isset($source[$p]) || $source[$p] !== '}') {
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
		$pattern_num = '[+-]?([0-9]+(\.[0-9]*)?|\.[0-9]+)(e[+-]?[0-9]+)?';
		$pattern_inf = '[+-]?inf';
		$pattern_nan = 'nan';

		if (preg_match('/^('.$pattern_num.'|'.$pattern_inf.'|'.$pattern_nan.')/i', substr($source, $pos), $matches)) {
			$pos += strlen($matches[0]);
			return true;
		}

		foreach ($this->macro_parsers as $macro_parser) {
			if ($macro_parser->parse($source, $pos) != self::PARSE_FAIL) {
				$pos += $macro_parser->getLength();
				return true;
			}
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
