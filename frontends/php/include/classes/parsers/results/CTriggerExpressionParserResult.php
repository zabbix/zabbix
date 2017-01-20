<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
class CTriggerExpressionParserResult extends CParserResult {

	const TOKEN_TYPE_OPEN_BRACE = 0;
	const TOKEN_TYPE_CLOSE_BRACE = 1;
	const TOKEN_TYPE_OPERATOR = 2;
	const TOKEN_TYPE_NUMBER = 3;
	const TOKEN_TYPE_FUNCTION_MACRO = 4;
	const TOKEN_TYPE_MACRO = 5;
	const TOKEN_TYPE_USER_MACRO = 6;
	const TOKEN_TYPE_LLD_MACRO = 7;

	/**
	 * Array of expression tokens.
	 *
	 * Each token contains the following values:
	 * - type   - token type
	 * - value  - the token string itself
	 * - pos    - position of the token in the source string
	 * - length - length of the token
	 * - data   - an array containing additional information about the token
	 *
	 * The following "data" information can be available depending on the type of the token.
	 * For self::TOKEN_TYPE_FUNCTION_MACRO tokens:
	 * - host           - host name
	 * - item           - item key
	 * - function       - the function string, e.g., "function(param1, param2)"
	 * - functionName   - function name without parameters
	 * - functionParams - array of function parameters
	 * For self::TOKEN_TYPE_NUMBER tokens:
	 * - suffix         - a time or byte suffix
	 *
	 * @var array
	 */
	protected $tokens = [];

	/**
	 * Return the expression tokens.
	 *
	 * @see CTriggerExpressionParserResult::$token    for the structure of a token array
	 *
	 * @return array
	 */
	public function getTokens() {
		return $this->tokens;
	}

	/**
	 * Add a token to the result.
	 *
	 * @param string        $type       token type
	 * @param string        $value      token string
	 * @param string        $pos        position of the token in the source string
	 * @param string        $length     length of the token
	 * @param array|null    $data       additional token information
	 */
	public function addToken($type, $value, $pos, $length, array $data = null) {
		$this->tokens[] = [
			'type' => $type,
			'value' => $value,
			'pos' => $pos,
			'length' => $length,
			'data' => $data
		];
	}

	/**
	 * Returns all tokens of the given type.
	 *
	 * @param $type
	 *
	 * @return array
	 */
	public function getTokensByType($type) {
		$result = [];

		foreach ($this->tokens as $token) {
			if ($token['type'] == $type) {
				$result[] = $token;
			}
		}

		return $result;
	}

	/**
	 * Check whether the expression contains at least one token of the given type.
	 *
	 * @param int $type
	 *
	 * @return bool
	 */
	public function hasTokenOfType($type) {
		foreach ($this->tokens as $token) {
			if ($token['type'] == $type) {
				return true;
			}
		}

		return false;
	}
}
