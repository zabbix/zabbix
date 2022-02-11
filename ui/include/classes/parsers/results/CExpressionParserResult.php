<?php declare(strict_types = 1);
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
 * Class for storing the result returned by the trigger expression parser.
 */
class CExpressionParserResult extends CParserResult {

	const TOKEN_TYPE_OPEN_BRACE = 0;
	const TOKEN_TYPE_CLOSE_BRACE = 1;
	const TOKEN_TYPE_OPERATOR = 2;
	const TOKEN_TYPE_NUMBER = 3;
	const TOKEN_TYPE_MACRO = 4;
	const TOKEN_TYPE_USER_MACRO = 5;
	const TOKEN_TYPE_LLD_MACRO = 6;
	const TOKEN_TYPE_STRING = 7;
	const TOKEN_TYPE_FUNCTIONID_MACRO = 8;
	const TOKEN_TYPE_HIST_FUNCTION = 9;
	const TOKEN_TYPE_MATH_FUNCTION = 10;
	const TOKEN_TYPE_EXPRESSION = 11;

	/**
	 * Array of tokens.
	 *
	 * @var array
	 */
	protected $tokens = [];

	/**
	 * Return the expression tokens.
	 *
	 * @see CExpressionParserResult::$token    for the structure of a token array
	 *
	 * @return array
	 */
	public function getTokens() {
		return $this->tokens;
	}

	/**
	 * Add a token to the result.
	 *
	 * @param CParserResult $token
	 */
	public function addTokens(array $tokens): void {
		$this->tokens = array_merge($this->tokens, $tokens);
	}

	/**
	 * Auxiliary method for getTokensOfTypes().
	 *
	 * @param array  $tokens
	 * @param array  $types
	 *
	 * @return array
	 */
	private static function _getTokensOfTypes(array $tokens, array $types) {
		$result = [];

		foreach ($tokens as $token) {
			if (in_array($token['type'], $types)) {
				$result[] = $token;
			}

			if ($token['type'] == CExpressionParserResult::TOKEN_TYPE_EXPRESSION) {
				$result = array_merge($result, self::_getTokensOfTypes($token['data']['tokens'], $types));
			}
			elseif ($token['type'] == CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION) {
				foreach ($token['data']['parameters'] as $parameter) {
					$result = array_merge($result, self::_getTokensOfTypes($parameter['data']['tokens'], $types));
				}
			}
		}

		return $result;
	}

	/**
	 * Returns all tokens include nested of the given types.
	 *
	 * @param array  $types
	 *
	 * @return array
	 */
	public function getTokensOfTypes(array $types): array {
		return self::_getTokensOfTypes($this->tokens, $types);
	}

	/**
	 * Return list hosts found in parsed trigger expression.
	 *
	 * @return array
	 */
	public function getHosts(): array {
		$hist_functions = $this->getTokensOfTypes([CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION]);
		$hosts = [];

		foreach ($hist_functions as $hist_function) {
			$host = $hist_function['data']['parameters'][0]['data']['host'];
			$hosts[$host] = $host;
		}

		return array_values($hosts);
	}
}
