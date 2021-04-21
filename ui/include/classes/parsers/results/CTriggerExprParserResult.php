<?php declare(strict_types = 1);
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
	const TOKEN_TYPE_MACRO = 5;
	const TOKEN_TYPE_USER_MACRO = 6;
	const TOKEN_TYPE_LLD_MACRO = 7;
	const TOKEN_TYPE_STRING = 8;
	const TOKEN_TYPE_FUNCTIONID_MACRO = 9;
	const TOKEN_TYPE_FUNCTION = 10;
	const TOKEN_TYPE_QUERY = 11;
	const TOKEN_TYPE_PERIOD = 12;

	/**
	 * Array of tokens.
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
	 * @param CParserResult $token
	 */
	public function addToken(CParserResult $token) {
		$this->tokens[] = $token;
	}

	/**
	 * Returns all tokens of the given types.
	 *
	 * @param array  $types
	 *
	 * @return array
	 */
	public function getTokensOfTypes(array $types): array {
		$params_stack = $this->tokens;
		$result = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
			if (($param instanceof CParserResult) && in_array($param->type, $types)) {
				$result[] = $param;
			}
		}

		usort($result, function ($a, $b) {
			return $a->pos <=> $b->pos;
		});

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
		return (bool) $this->getTokensOfTypes([$type]);
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
			elseif ($param instanceof CTriggerExprTokenResult
					&& $param->type == CTriggerExprParserResult::TOKEN_TYPE_FUNCTIONID_MACRO) {
				$return[] = $param;
			}
			elseif ($param instanceof CFunctionIdParserResult) {
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
		$user_macro_parser = new CUserMacroParser();
		$params_stack = $this->tokens;
		$return = [];

		while ($params_stack) {
			$param = array_shift($params_stack);
			if ($param instanceof CFunctionParserResult) {
				$params_stack = array_merge($params_stack, $param->params_raw['parameters']);
			}
			elseif ($param instanceof CTriggerExprTokenResult
					&& $param->type == CTriggerExprParserResult::TOKEN_TYPE_USER_MACRO) {
				$return[] = $param;
			}
			elseif ($param instanceof CFunctionParameterResult
					&& $user_macro_parser->parse($param->getValue()) == CParser::PARSE_SUCCESS) {
				$return[] = $param;
			}
		}

		return $return;
	}

	/**
	 * Return list item keys found in parsed trigger function.
	 *
	 * @return array
	 */
	public function getItems():array {
		$items = [];
		foreach ($this->tokens as $token) {
			if ($token instanceof CFunctionParserResult) {
				$items = array_merge($items, $token->getItems());
			}
		}

		return array_keys(array_flip($items));
	}
	/**
	 * Return list hosts found in parsed trigger expression.
	 *
	 * @return array
	 */
	public function getHosts():array {
		$hosts = [];
		foreach ($this->tokens as $token) {
			if ($token instanceof CFunctionParserResult) {
				$hosts = array_merge($hosts, $token->getHosts());
			}
		}

		return array_keys(array_flip($hosts));
	}
}
