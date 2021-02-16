<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
class CTriggerExprParserResult extends CParserResult {

	const TOKEN_TYPE_OPEN_BRACE = 0;
	const TOKEN_TYPE_CLOSE_BRACE = 1;
	const TOKEN_TYPE_OPERATOR = 2;
	const TOKEN_TYPE_NUMBER = 3;
	const TOKEN_TYPE_FUNCTION_MACRO = 4;
	const TOKEN_TYPE_MACRO = 5;
	const TOKEN_TYPE_USER_MACRO = 6;
	const TOKEN_TYPE_LLD_MACRO = 7;
	const TOKEN_TYPE_STRING = 8;
	const TOKEN_TYPE_FUNCTIONID_MACRO = 9;
	const TOKEN_TYPE_FUNCTION = 10;

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
	 * @see CTriggerExprParserResult::$token    for the structure of a token array
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
	 * Add function a token to the result.
	 *
	 * @param CParserResult  $fn_result
	 */
	public function addFunctionToken(CParserResult $fn_result) {
		$this->tokens[] = $fn_result;
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
			if ($token['type'] == $type) { // TODO miks: $this->tokens may contain objects.
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
			if ($token['type'] == $type) { // TODO miks: $this->tokens may contain objects.
				return true;
			}
		}

		return false;
	}

	/**
	 * Return array containing all tokens of type CFunctionParserResult.
	 *
	 * @return array
	 */
	public function getFunctions(): array {
		$params_stack = $this->tokens;
		$functions = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$functions[] = $param;
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
		}

		return $functions;
	}

	/**
	 * Return array containing all tokens of type CFunctionIdParserResult.
	 *
	 * @return array
	 */
	public function getFunctionIds(): array {
		$params_stack = $this->tokens;
		$return = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
			elseif ($param instanceof CFunctionIdParserResult
					|| (is_array($param) && $param['type'] == CTriggerExprParserResult::TOKEN_TYPE_FUNCTIONID_MACRO )) {
				$return[] = $param;
			}
		}

		return $return;
	}

	/**
	 * Return array containing all user macros found in trigger expression.
	 *
	 * @return array
	 */
	public function getUserMacros(): array {
		$params_stack = $this->tokens;
		$return = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
			elseif (is_array($param) && $param['type'] == CTriggerExprParserResult::TOKEN_TYPE_USER_MACRO) {
				$return[] = $param;
			}
		}

		return $return;
	}

	/**
	 * Return list hosts found in parsed trigger expression.
	 *
	 * @return array
	 */
	public function getHosts():array {
		$hosts = [];
		foreach ($this->params_raw['parameters'] as $param) {
			if ($param instanceof CFunctionParserResult) {
				$hosts = array_merge($hosts, $param->getHosts());
			}
			elseif ($param instanceof CQueryParserResult) {
				$hosts[] = $param->host;
			}
		}

		return array_keys(array_flip($hosts));
	}

	/**
	 * Return array containing items found in parsed trigger expression grouped by host.
	 *
	 * Example:
	 * [
	 *   'host1' => [
	 *     'item1' => 'item1',
	 *     'item2' => 'item2'
	 *   ]
	 * ],
	 * [
	 *   'host2' => [
	 *     'item3' => 'item3',
	 *   ]
	 * ]
	 *
	 * @return array
	 */
	public function getItemsGroupedByHosts():array {
		$params_stack = $this->tokens;
		$hosts = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);

				foreach ($param->getItemsGroupedByHosts() as $host => $items) {
					if (!array_key_exists($host, $hosts)) {
						$hosts[$host] = [
							'hostid' => null,
							'host' => $host,
							'status' => null,
							'keys' => []
						];
					}

					foreach ($items as $item) {
						$hosts[$host]['keys'][$item] = [
							'itemid' => null,
							'key' => $item,
							'value_type' => null,
							'flags' => null
						];
					}
				}
			}
		}

		return $hosts;
	}
}
