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
 * A class for implementing conversions used by the trigger wizard.
 */
class CTextTriggerConstructor {

	const EXPRESSION_TYPE_MATCH = 0;
	const EXPRESSION_TYPE_NO_MATCH = 1;

	/**
	 * Parser used for parsing trigger expressions.
	 *
	 * @var CTriggerExpression
	 */
	protected $triggerExpression;

	/**
	 * @param CTriggerExpression $triggerExpression     trigger expression parser
	 */
	public function __construct(CTriggerExpression $triggerExpression) {
		$this->triggerExpression = $triggerExpression;
	}

	/**
	 * Create a trigger expression from the given expression parts.
	 *
	 * Most of this function was left unchanged to preserve the current behavior of the constructor.
	 * Feel free to rewrite and correct it if necessary.
	 *
	 * @param string    $host                       host name
	 * @param string    $itemKey                    item key
	 * @param array     $expressions                array of expression parts
	 * @param string    $expressions[]['value']     expression string
	 * @param int       $expressions[]['type']      whether the string should match the expression; supported values:
	 *                                              self::EXPRESSION_TYPE_MATCH and self::EXPRESSION_TYPE_NO_MATCH
	 *
	 * @return bool|string
	 */
	public function getExpressionFromParts($host, $itemKey, array $expressions) {
		$result = '';
		$prefix = $host.':'.$itemKey.'.';

		if (empty($expressions)) {
			error(_('Expression cannot be empty'));
			return false;
		}

		// regexp used to split an expressions into tokens
		$ZBX_PREG_EXPESSION_FUNC_FORMAT = '^(['.ZBX_PREG_PRINT.']*) (and|or) (not )?(-)? ?[(]*(([a-zA-Z_.\$]{6,7})(\\((['.ZBX_PREG_PRINT.']+?){0,1}\\)))(['.ZBX_PREG_PRINT.']*)$';
		$functions = array('regexp' => 1, 'iregexp' => 1);
		$expr_array = array();
		$cexpor = 0;
		$startpos = -1;

		foreach ($expressions as $expression) {
			if ($expression['type'] == self::EXPRESSION_TYPE_MATCH) {
				if (!empty($result)) {
					$result.=' or ';
				}
				if ($cexpor == 0) {
					$startpos = mb_strlen($result);
				}
				$cexpor++;
				$eq_global = '<>0';
			}
			else {
				if (($cexpor > 1) & ($startpos >= 0)) {
					$head = mb_substr($result, 0, $startpos);
					$tail = mb_substr($result, $startpos);
					$result = $head.'('.$tail.')';
				}
				$cexpor = 0;
				$eq_global = '=0';
				if (!empty($result)) {
					$result.=' and ';
				}
			}

			$expr = ' and '.$expression['value'];

			// strip extra spaces around "and" and "or" operators
			$expr = preg_replace('/\s+(and|or)\s+/U', ' $1 ', $expr);

			$expr_array = array();
			$sub_expr_count=0;
			$sub_expr = '';
			$multi = preg_match('/.+(and|or).+/', $expr);

			// split an expression into separate tokens
			// start from the first part of the expression, then move to the next one
			while (preg_match('/'.$ZBX_PREG_EXPESSION_FUNC_FORMAT.'/i', $expr, $arr)) {
				$arr[6] = strtolower($arr[6]);
				if (!isset($functions[$arr[6]])) {
					error(_('Incorrect function is used').'. ['.$expression['value'].']');
					return false;
				}
				$expr_array[$sub_expr_count]['eq'] = trim($arr[2]);
				$expr_array[$sub_expr_count]['not'] = trim($arr[3]);
				$expr_array[$sub_expr_count]['minus'] = trim($arr[4]);
				$expr_array[$sub_expr_count]['regexp'] = $arr[6].$arr[7];

				$sub_expr_count++;
				$expr = $arr[1];
			}

			if (empty($expr_array)) {
				error(_('Incorrect trigger expression').'. ['.$expression['value'].']');
				return false;
			}

			$expr_array[$sub_expr_count-1]['eq'] = '';

			$sub_eq = '';
			if ($multi > 0) {
				$sub_eq = $eq_global;
			}

			foreach ($expr_array as $id => $expr) {
				$eq = ($expr['eq'] === '') ? '' : ' '.$expr['eq'].' ';
				$not = ($expr['not'] === '') ? '' : $expr['not'].' ';
				if ($multi > 0) {
					$sub_expr = $eq.'('.$not.$expr['minus'].'{'.$prefix.$expr['regexp'].'})'.$sub_eq.$sub_expr;
				}
				else {
					$sub_expr = $eq.$expr['eq'].$not.$expr['minus'].'{'.$prefix.$expr['regexp'].'}'.$sub_eq.$sub_expr;
				}
			}

			if ($multi > 0) {
				$result .= '('.$sub_expr.')';
			}
			else {
				$result .= '(('.$sub_expr.')'.$eq_global.')';
			}
		}

		if (($cexpor > 1) & ($startpos >= 0)) {
			$head = mb_substr($result, 0, $startpos);
			$tail = mb_substr($result, $startpos);
			$result = $head.'('.$tail.')';
		}

		return $result;
	}

	/**
	 * Break a trigger expression generated by the constructor.
	 *
	 * To be successfully parsed, each item function macro must be wrapped in additional parentheses, for example,
	 * (({item.item.regex(param)})=0)
	 *
	 * Most of this function was left unchanged to preserve the current behavior of the constructor.
	 * Feel free to rewrite and correct it if necessary.
	 *
	 * @param string $expression    trigger expression
	 *
	 * @return array    an array of expression parts, see self::getExpressionFromParts() for the structure of the part
	 *                  array
	 */
	public function getPartsFromExpression($expression) {
		// strip extra parentheses
		$expression = preg_replace('/\(\(\((.+?)\)\) and/i', '(($1) and', $expression);
		$expression = preg_replace('/\(\(\((.+?)\)\)$/i', '(($1)', $expression);

		$parseResult = $this->triggerExpression->parse($expression);

		$expressions = array();
		$splitTokens = $this->splitTokensByFirstLevel($parseResult->getTokens());
		foreach($splitTokens as $key => $tokens) {
			$expr = array();

			// replace whole function macros with their functions
			foreach ($tokens as $token) {
				$value = $token['value'];
				switch ($token['type']) {
					case CTriggerExpressionParserResult::TOKEN_TYPE_FUNCTION_MACRO:
						$value = $token['data']['function'];

						break;
					case CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR:
						if ($token['value'] === 'and' || $token['value'] === 'or' || $token['value'] === 'not') {
							$value = ' '.$token['value'].' ';
						}

						break;
				}

				$expr[] = $value;
			}

			$expr = implode($expr);

			// trim surrounding parentheses
			$expr = preg_replace('/^\((.*)\)$/u', '$1', $expr);

			// trim parentheses around item function macros
			$value = preg_replace('/\((.*)\)(=|<>)0/U', '$1', $expr);

			// trim surrounding parentheses
			$value = preg_replace('/^\((.*)\)$/u', '$1', $value);

			$expressions[$key]['value'] = trim($value);
			$expressions[$key]['type'] = (strpos($expr, '<>0', mb_strlen($expr) - 4) === false)
				? self::EXPRESSION_TYPE_NO_MATCH
				: self::EXPRESSION_TYPE_MATCH;
		}

		return $expressions;
	}

	/**
	 * Split the trigger expression tokens into separate arrays.
	 *
	 * The tokens are split at the first occurrence of the "and" or "or" operators with respect to parentheses.
	 *
	 * @param array $tokens     an array of tokens from the CTriggerExpressionParserResult
	 *
	 * @return array    an array of token arrays grouped by expression
	 */
	protected function splitTokensByFirstLevel(array $tokens) {
		$expresions = array();
		$currentExpression = array();

		$level = 0;
		foreach ($tokens as $token) {
			switch ($token['type']) {
				case CTriggerExpressionParserResult::TOKEN_TYPE_OPERATOR:
					// look for an "or" or "and" operator on the top parentheses level
					// if such an expression is found, save all of the tokens before it as a separate expression
					if ($level == 0 && ($token['value'] === 'or' || $token['value'] === 'and')) {
						$expresions[] = $currentExpression;
						$currentExpression = array();

						// continue to the next token
						continue 2;
					}

					break;
				case CTriggerExpressionParserResult::TOKEN_TYPE_OPEN_BRACE:
					$level++;

					break;
				case CTriggerExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE:
					$level--;

					break;
			}

			$currentExpression[] = $token;
		}

		$expresions[] = $currentExpression;

		return $expresions;
	}

}
