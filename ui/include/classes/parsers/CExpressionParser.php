<?php declare(strict_types = 0);
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


class CExpressionParser extends CParser {

	// For parsing of expressions.
	private const STATE_AFTER_OPEN_BRACE = 1;
	private const STATE_AFTER_BINARY_OPERATOR = 2;
	private const STATE_AFTER_LOGICAL_OPERATOR = 3;
	private const STATE_AFTER_NOT_OPERATOR = 4;
	private const STATE_AFTER_UNARY_MINUS = 5;
	private const STATE_AFTER_CLOSE_BRACE = 6;
	private const STATE_AFTER_CONSTANT = 7;

	// For parsing of math function parameters.
	private const STATE_NEW = 1;
	private const STATE_END = 2;
	private const STATE_END_OF_PARAMS = 3;

	private const MAX_MATH_FUNCTION_DEPTH = 32;

	/**
	 * An error message if trigger expression is not valid
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false            Enable user macros usage in expression.
	 *   'lldmacros' => false             Enable low-level discovery macros usage in expression.
	 *   'collapsed_expression' => false  Short trigger expression.
	 *                                       For example: {439} > {$MAX_THRESHOLD} or {439} < {$MIN_THRESHOLD}
	 *   'calculated' => false            Parse calculated item formula instead of trigger expression.
	 *   'host_macro' => false            Allow {HOST.HOST} macro as host name part in the query.
	 *   'host_macro_n' => false          Allow {HOST.HOST} and {HOST.HOST<1-9>} macros as host name part in the query.
	 *   'empty_host' => false            Allow empty hostname in the query string.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false,
		'collapsed_expression' => false,
		'calculated' => false,
		'host_macro' => false,
		'host_macro_n' => false,
		'empty_host' => false
	];

	/**
	 * Object containing the results of parsing.
	 *
	 * @var null|CExpressionParserResult
	 */
	private $result;

	/**
	 * Chars that should be treated as spaces.
	 */
	public const WHITESPACES = " \r\n\t";

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;

		if ($this->options['collapsed_expression']
				&& ($this->options['host_macro'] || $this->options['host_macro_n'])) {
			exit('Incompatible options.');
		}
	}

	/**
	 * Parse an expression and set public variables $this->error, $this->result
	 *
	 * Examples:
	 *   last(/Zabbix server/agent.ping,0) = 1 and {TRIGGER.VALUE} = {$MACRO}
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		// initializing local variables
		$this->error = '';
		$this->match = '';
		$this->length = 0;

		$p = $pos;
		$tokens = [];
		$parsed_pos = 0;

		if (self::parseExpression($source, $p, $tokens, $this->options, $parsed_pos)) {
			// Including trailing whitespaces as part of the expression.
			if (preg_match('/^['.self::WHITESPACES.']+$/', substr($source, $p), $matches)) {
				$p += strlen($matches[0]);
			}
			$len = $p - $pos;

			$this->length = $len;
			$this->match = substr($source, $pos, $len);

			$this->result = new CExpressionParserResult();
			$this->result->addTokens($tokens);
			$this->result->pos = $pos;

			if (isset($source[$p])) {
				$this->error = _s('incorrect expression starting from "%1$s"', substr($source, $parsed_pos));

				return self::PARSE_SUCCESS_CONT;
			}

			return self::PARSE_SUCCESS;
		}

		$this->error = _s('incorrect expression starting from "%1$s"', substr($source, $parsed_pos));

		return self::PARSE_FAIL;
	}

	/**
	 * Parses an expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 * @param int     $parsed_pos
	 * @param int     $depth
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseExpression(string $source, int &$pos, array &$tokens, array $options,
			int &$parsed_pos = null, int $depth = 0): bool {
		$binary_operator_parser = new CSetParser(['<', '>', '<=', '>=', '+', '-', '/', '*', '=', '<>']);
		$logical_operator_parser = new CSetParser(['and', 'or']);

		if ($depth++ > self::MAX_MATH_FUNCTION_DEPTH) {
			return false;
		}

		$state = self::STATE_AFTER_OPEN_BRACE;
		$after_space = false;
		$level = 0;
		$p = $pos;
		$_tokens = [];

		while (isset($source[$p])) {
			$char = $source[$p];

			if (strpos(self::WHITESPACES, $char) !== false) {
				$after_space = true;
				$p++;
				continue;
			}

			switch ($state) {
				case self::STATE_AFTER_OPEN_BRACE:
					switch ($char) {
						case '-':
							$state = self::STATE_AFTER_UNARY_MINUS;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						case '(':
							$level++;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (self::parseNot($source, $p, $_tokens)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parseConstant($source, $p, $_tokens, $options, $depth)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$tokens = $_tokens;
								}
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_BINARY_OPERATOR:
					switch ($char) {
						case '-':
							$state = self::STATE_AFTER_UNARY_MINUS;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (self::parseConstant($source, $p, $_tokens, $options, $depth)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$tokens = $_tokens;
								}
								break;
							}

							if ($after_space && self::parseNot($source, $p, $_tokens)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_LOGICAL_OPERATOR:
					switch ($char) {
						case '-':
							if (!$after_space) {
								break 3;
							}
							$state = self::STATE_AFTER_UNARY_MINUS;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseNot($source, $p, $_tokens)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parseConstant($source, $p, $_tokens, $options, $depth)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$tokens = $_tokens;
								}
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_CLOSE_BRACE:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$level--;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];

							if ($level == 0) {
								$pos = $p + 1;
								$tokens = $_tokens;
							}
							break;

						default:
							if (self::parseUsing($binary_operator_parser, $source, $p, $_tokens,
									CExpressionParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if (self::parseUsing($logical_operator_parser, $source, $p, $_tokens,
									CExpressionParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
								break;
							}
							break 3;
					}
					break;

				case self::STATE_AFTER_CONSTANT:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$level--;
							$state = self::STATE_AFTER_CLOSE_BRACE;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_CLOSE_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];

							if ($level == 0) {
								$pos = $p + 1;
								$tokens = $_tokens;
							}
							break;

						default:
							if (self::parseUsing($binary_operator_parser, $source, $p, $_tokens,
									CExpressionParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_BINARY_OPERATOR;
								break;
							}

							if ($after_space && self::parseUsing($logical_operator_parser, $source, $p, $_tokens,
									CExpressionParserResult::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_NOT_OPERATOR:
					switch ($char) {
						case '-':
							if (!$after_space) {
								break 3;
							}
							$state = self::STATE_AFTER_UNARY_MINUS;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseConstant($source, $p, $_tokens, $options, $depth)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$tokens = $_tokens;
								}
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_UNARY_MINUS:
					switch ($char) {
						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (self::parseConstant($source, $p, $_tokens, $options, $depth)) {
								$state = self::STATE_AFTER_CONSTANT;

								if ($level == 0) {
									$pos = $p + 1;
									$tokens = $_tokens;
								}
							}
							else {
								break 3;
							}
					}
					break;
			}

			$after_space = false;
			$p++;
		}

		$parsed_pos = $p;

		return (bool) $tokens;
	}

	/**
	 * Parse unary "not".
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 *
	 * @return bool
	 */
	private static function parseNot(string $source, int &$pos, array &$tokens): bool {
		if (substr($source, $pos, 3) !== 'not' || !isset($source[$pos + 3])
				|| strpos(self::WHITESPACES.'(', $source[$pos + 3]) === false) {
			return false;
		}

		$tokens[] = [
			'type' => CExpressionParserResult::TOKEN_TYPE_OPERATOR,
			'pos' => $pos,
			'match' => 'not',
			'length' => 3
		];
		$pos += 2;

		return true;
	}

	/**
	 * Parse the string using the given parser. If a match has been found, move the cursor to the last symbol of the
	 * matched string.
	 *
	 * @param CParser $parser
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param int     $token_type
	 *
	 * @return bool
	 */
	private static function parseUsing(CParser $parser, string $source, int &$pos, array &$tokens,
			int $token_type): bool {
		if ($parser->parse($source, $pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$tokens[] = [
			'type' => $token_type,
			'pos' => $pos,
			'match' => $parser->getMatch(),
			'length' => $parser->getLength()
		];
		$pos += $parser->getLength() - 1;

		return true;
	}

	/**
	 * Parses a constant in the expression.
	 *
	 * The constant can be (depending on options):
	 *  - function like func(<expression>)
	 *  - function like func(/host/item,<params>)
	 *  - floating point number; can be with suffix [KMGTsmhdw]
	 *  - string
	 *  - macro like {TRIGGER.VALUE}
	 *  - user macro like {$MACRO}
	 *  - LLD macro like {#LLD}
	 *  - LLD macro with function like {{#LLD}.func())}
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 * @param int     $depth
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseConstant(string $source, int &$pos, array &$tokens, array $options, int $depth): bool {
		if (self::parseNumber($source, $pos, $tokens) || self::parseString($source, $pos, $tokens)) {
			return true;
		}

		if (!$options['calculated']) {
			$macro_parser = new CMacroParser(['macros' => ['{TRIGGER.VALUE}']]);

			if (self::parseUsing($macro_parser, $source, $pos, $tokens, CExpressionParserResult::TOKEN_TYPE_MACRO)) {
				return true;
			}
		}

		if ($options['collapsed_expression']) {
			$functionid_parser = new CFunctionIdParser();

			if (self::parseUsing($functionid_parser, $source, $pos, $tokens,
					CExpressionParserResult::TOKEN_TYPE_FUNCTIONID_MACRO)) {
				return true;
			}
		}
		elseif (self::parseHistFunction($source, $pos, $tokens, $options)) {
			return true;
		}

		if (self::parseMathFunction($source, $pos, $tokens, $options, $depth)) {
			return true;
		}

		if ($options['usermacros']) {
			$user_macro_parser = new CUserMacroParser();

			if (self::parseUsing($user_macro_parser, $source, $pos, $tokens,
					CExpressionParserResult::TOKEN_TYPE_USER_MACRO)) {
				return true;
			}
		}

		if ($options['lldmacros']) {
			$lld_macro_parser = new CLLDMacroParser();

			if (self::parseUsing($lld_macro_parser, $source, $pos, $tokens,
					CExpressionParserResult::TOKEN_TYPE_LLD_MACRO)) {
				return true;
			}

			$lld_macro_function_parser = new CLLDMacroFunctionParser();

			if (self::parseUsing($lld_macro_function_parser, $source, $pos, $tokens,
					CExpressionParserResult::TOKEN_TYPE_LLD_MACRO)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parses a historical function constant in the expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseHistFunction(string $source, int &$pos, array &$tokens, array $options): bool {
		$hist_function_parser = new CHistFunctionParser([
			'usermacros' => $options['usermacros'],
			'lldmacros' => $options['lldmacros'],
			'calculated' => $options['calculated'],
			'host_macro' => $options['host_macro'],
			'host_macro_n' => $options['host_macro_n'],
			'empty_host' => $options['empty_host']
		]);

		if ($hist_function_parser->parse($source, $pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$len = $hist_function_parser->getLength();
		$tokens[] = [
			'type' => CExpressionParserResult::TOKEN_TYPE_HIST_FUNCTION,
			'pos' => $pos,
			'match' => $hist_function_parser->getMatch(),
			'length' => $len,
			'data' => [
				'function' => $hist_function_parser->getFunction(),
				'parameters' => $hist_function_parser->getParameters()
			]
		];
		$pos += $len - 1;

		return true;
	}

	/**
	 * Parses a math function constant in the expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 * @param int     $depth
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseMathFunction(string $source, int &$pos, array &$tokens, array $options,
			int $depth): bool {
		$p = $pos;

		if (!preg_match('/^([a-z0-9_]+)\(/', substr($source, $p), $matches)) {
			return false;
		}

		$p += strlen($matches[0]);
		$p2 = $p - 1;
		$_tokens = [];
		$state = self::STATE_NEW;

		while (isset($source[$p])) {
			switch ($state) {
				case self::STATE_NEW:
					switch ($source[$p]) {
						case ' ':
							break;

						case ')':
							if (!$_tokens) {
								$state = self::STATE_END_OF_PARAMS;
								break;
							}
							break 3;

						default:
							$_p = $p;
							$expression_tokens = [];
							$parsed_pos = 0;

							if (!self::parseExpression($source, $_p, $expression_tokens, $options, $parsed_pos,
									$depth)) {
								break 3;
							}

							$len = $_p - $p;
							$_tokens[] = [
								'type' => CExpressionParserResult::TOKEN_TYPE_EXPRESSION,
								'pos' => $p,
								'match' => substr($source, $p, $len),
								'length' => $len,
								'data' => [
									'tokens' => $expression_tokens
								]
							];
							$p = $_p - 1;
							$state = self::STATE_END;
					}
					break;

				case self::STATE_END:
					switch ($source[$p]) {
						case ' ':
							break;

						case ')':
							$state = self::STATE_END_OF_PARAMS;
							break;

						case ',':
							$state = self::STATE_NEW;
							break;

						default:
							break 3;
					}
					break;

				case self::STATE_END_OF_PARAMS:
					break 2;
			}

			$p++;
		}

		if ($state != self::STATE_END_OF_PARAMS) {
			return false;
		}

		$len = $p - $pos;
		$tokens[] = [
			'type' => CExpressionParserResult::TOKEN_TYPE_MATH_FUNCTION,
			'pos' => $pos,
			'match' => substr($source, $pos, $len),
			'length' => $len,
			'data' => [
				'function' => $matches[1],
				'parameters' => $_tokens
			]
		];
		$pos += $len - 1;

		return true;
	}

	/**
	 * Parses a number constant in the expression and moves a current position on a last symbol of the number.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private static function parseNumber(string $source, int &$pos, array &$tokens): bool {
		$number_parser = new CNumberParser([
			'with_minus' => false,
			'with_size_suffix' => true,
			'with_time_suffix' => true
		]);

		if ($number_parser->parse($source, $pos) == CParser::PARSE_FAIL) {
			return false;
		}

		$value = $number_parser->calcValue();
		if (abs($value) == INF) {
			return false;
		}

		$len = $number_parser->getLength();
		$tokens[] = [
			'type' => CExpressionParserResult::TOKEN_TYPE_NUMBER,
			'pos' => $pos,
			'match' => $number_parser->getMatch(),
			'length' => $len,
			'data' => ['suffix' => $number_parser->getSuffix()]
		];
		$pos += $len - 1;

		return true;
	}

	/**
	 * Parses a quoted string constant in the expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private static function parseString(string $source, int &$pos, array &$tokens): bool {
		if (!preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', substr($source, $pos), $matches)) {
			return false;
		}

		$len = strlen($matches[0]);
		$tokens[] = [
			'type' => CExpressionParserResult::TOKEN_TYPE_STRING,
			'pos' => $pos,
			'match' => $matches[0],
			'length' => $len
		];
		$pos += $len - 1;

		return true;
	}

	/**
	 * Unquoting quoted string $value.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public static function unquoteString(string $value): string {
		return strtr(substr($value, 1, -1), ['\\"' => '"', '\\\\' => '\\']);
	}

	/**
	 * Quoting $value if it contains a non numeric value.
	 *
	 * @param string $value
	 * @param bool   $allow_macros
	 * @param bool   $force
	 *
	 * @return string
	 */
	public static function quoteString(string $value, bool $allow_macros = true, bool $force = false): string {
		if (!$force) {
			$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

			if ($number_parser->parse($value) == CParser::PARSE_SUCCESS) {
				return $value;
			}

			if ($allow_macros) {
				$user_macro_parser = new CUserMacroParser();
				$macro_parser = new CMacroParser(['macros' => ['{TRIGGER.VALUE}']]);
				$lld_macro_parser = new CLLDMacroParser();
				$lld_macro_function_parser = new CLLDMacroFunctionParser;

				if ($user_macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $lld_macro_parser->parse($value) == CParser::PARSE_SUCCESS
						|| $lld_macro_function_parser->parse($value) == CParser::PARSE_SUCCESS) {
					return $value;
				}
			}
		}

		return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"']).'"';
	}

	/**
	 * Returns an expression parser result.
	 *
	 * @return null|CExpressionParserResult
	 */
	public function getResult(): ?CExpressionParserResult {
		return $this->result;
	}

	/**
	 * Returns a friendly error message or empty string if expression was parsed successfully.
	 *
	 * @return string
	 */
	public function getError(): string {
		return $this->error;
	}
}
