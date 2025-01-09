<?php declare(strict_types = 0);
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


class CFilterParser extends CParser {

	// For parsing of filter expressions.
	private const STATE_AFTER_OPEN_BRACE = 1;
	private const STATE_AFTER_LOGICAL_OPERATOR = 2;
	private const STATE_AFTER_NOT_OPERATOR = 3;
	private const STATE_AFTER_CLOSE_BRACE = 4;
	private const STATE_AFTER_PAIR = 5;

	// Token types.
	public const TOKEN_TYPE_OPEN_BRACE = 0;
	public const TOKEN_TYPE_CLOSE_BRACE = 1;
	public const TOKEN_TYPE_OPERATOR = 2;
	public const TOKEN_TYPE_KEYWORD = 3;
	public const TOKEN_TYPE_USER_MACRO = 4;
	public const TOKEN_TYPE_LLD_MACRO = 5;
	public const TOKEN_TYPE_STRING = 6;

	/**
	 * Chars that should be treated as spaces.
	 */
	public const WHITESPACES = " \r\n\t";

	/**
	 * Array of tokens.
	 *
	 * @var array
	 */
	protected $tokens = [];

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'usermacros' => false  Enable user macros usage in filter expression.
	 *   'lldmacros' => false   Enable low-level discovery macros usage in filter expression.
	 *
	 * @var array
	 */
	private $options = [
		'usermacros' => false,
		'lldmacros' => false
	];

	/**
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		$this->options = $options + $this->options;
	}

	/**
	 * Parse a filter expression.
	 *
	 * Examples:
	 *   ?[tag = "Service:MySQL" and group = "Database servers"]
	 *
	 * @param string $expression
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		// initializing local variables
		$this->match = '';
		$this->length = 0;

		$p = $pos;
		$tokens = [];

		if (substr($source, $p, 2) !== '?[') {
			return self::PARSE_FAIL;
		}
		$p += 2;

		if (!self::parseExpression($source, $p, $tokens, $this->options)) {
			return self::PARSE_FAIL;
		}

		if (!isset($source[$p]) || $source[$p] !== ']') {
			return self::PARSE_FAIL;
		}
		$p++;

		$len = $p - $pos;

		$this->length = $len;
		$this->match = substr($source, $pos, $len);
		$this->tokens = $tokens;

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Parses an expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseExpression(string $source, int &$pos, array &$tokens, array $options): bool {
		$logical_operator_parser = new CSetParser(['and', 'or']);
		$not_operator_parser = new CSetParser(['not']);

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
						case '(':
							$level++;
							$_tokens[] = [
								'type' => self::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (self::parseUsing($not_operator_parser, $source, $p, $_tokens,
									self::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parsePair($source, $p, $_tokens, $options)) {
								$state = self::STATE_AFTER_PAIR;

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

				case self::STATE_AFTER_LOGICAL_OPERATOR:
					switch ($char) {
						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => self::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parseUsing($not_operator_parser, $source, $p, $_tokens,
									self::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parsePair($source, $p, $_tokens, $options)) {
								$state = self::STATE_AFTER_PAIR;

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
								'type' => self::TOKEN_TYPE_CLOSE_BRACE,
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
							if (self::parseUsing($logical_operator_parser, $source, $p, $_tokens,
									self::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
								break;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_PAIR:
					switch ($char) {
						case ')':
							if ($level == 0) {
								break 3;
							}
							$level--;
							$state = self::STATE_AFTER_CLOSE_BRACE;
							$_tokens[] = [
								'type' => self::TOKEN_TYPE_CLOSE_BRACE,
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
							if ($after_space && self::parseUsing($logical_operator_parser, $source, $p, $_tokens,
									self::TOKEN_TYPE_OPERATOR)) {
								$state = self::STATE_AFTER_LOGICAL_OPERATOR;
							}
							else {
								break 3;
							}
					}
					break;

				case self::STATE_AFTER_NOT_OPERATOR:
					switch ($char) {
						case '(':
							$level++;
							$state = self::STATE_AFTER_OPEN_BRACE;
							$_tokens[] = [
								'type' => self::TOKEN_TYPE_OPEN_BRACE,
								'pos' => $p,
								'match' => $char,
								'length' => 1
							];
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parsePair($source, $p, $_tokens, $options)) {
								$state = self::STATE_AFTER_PAIR;

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

		if ($tokens) {
			// Including trailing whitespaces as part of the expression.
			while (isset($source[$pos]) && strpos(self::WHITESPACES, $source[$pos]) !== false) {
				$pos++;
			}
		}

		return (bool) $tokens;
	}

	/**
	 * Parses a constant in the expression.
	 *
	 * The pair can be:
	 *  - <keyword> <operator> <quoted string>
	 *  - <quoted string> <operator> <keyword>
	 *
	 *  <operator> - =|<>
	 *  <keyword> - tag|group
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parsePair(string $source, int &$pos, array &$tokens, array $options): bool {
		$keyword_parser = new CSetParser(['tag', 'group']);
		$binary_operator_parser = new CSetParser(['=', '<>']);

		$p = $pos;
		$_tokens = [];
		$keywords = 0;

		if (self::parseUsing($keyword_parser, $source, $p, $_tokens, self::TOKEN_TYPE_KEYWORD)) {
			$keywords++;
		}
		elseif (!self::parseConstant($source, $p, $_tokens, $options)) {
			return false;
		}
		$p++;

		while (isset($source[$p]) && strpos(self::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if (!self::parseUsing($binary_operator_parser, $source, $p, $_tokens, self::TOKEN_TYPE_OPERATOR)) {
			return false;
		}
		$p++;

		while (isset($source[$p]) && strpos(self::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if (self::parseUsing($keyword_parser, $source, $p, $_tokens, self::TOKEN_TYPE_KEYWORD)) {
			$keywords++;
		}
		else if (!self::parseConstant($source, $p, $_tokens, $options)) {
			return false;
		}
		$p++;

		if ($keywords > 1) {
			return false;
		}

		$pos = $p - 1;
		$tokens = array_merge($tokens, $_tokens);

		return true;
	}


	/**
	 * Parses a constant in the expression.
	 *
	 * The constant can be:
	 *  - string
	 *  - user macros like {$MACRO} and {{$MACRO}.func())}
	 *  - LLD macros like {#LLD} and {{#LLD}.func())}
	 *
	 * @param string  $source
	 * @param int     $pos
	 * @param array   $tokens
	 * @param array   $options
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parseConstant(string $source, int &$pos, array &$tokens, array $options): bool {
		if (self::parseString($source, $pos, $tokens)) {
			return true;
		}

		if ($options['usermacros'] && self::parseUsing(new CUserMacroParser(), $source, $pos, $tokens,
				self::TOKEN_TYPE_USER_MACRO)) {
			return true;
		}

		if ($options['usermacros'] && self::parseUsing(new CUserMacroFunctionParser(), $source, $pos, $tokens,
				self::TOKEN_TYPE_USER_MACRO)) {
			return true;
		}

		if ($options['lldmacros'] && self::parseUsing(new CLLDMacroParser(), $source, $pos, $tokens,
				self::TOKEN_TYPE_LLD_MACRO)) {
			return true;
		}

		if ($options['lldmacros'] && self::parseUsing(new CLLDMacroFunctionParser(), $source, $pos, $tokens,
				self::TOKEN_TYPE_LLD_MACRO)) {
			return true;
		}

		return false;
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
			'type' => self::TOKEN_TYPE_STRING,
			'pos' => $pos,
			'match' => $matches[0],
			'length' => $len
		];
		$pos += $len - 1;

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
	 * Return the expression tokens.
	 *
	 * @return array
	 */
	public function getTokens(): array {
		return $this->tokens;
	}

	/**
	 * Returns tokens of the given types.
	 *
	 * @param array  $types
	 *
	 * @return array
	 */
	public function getTokensOfTypes(array $types): array {
		$result = [];

		foreach ($this->tokens as $token) {
			if (in_array($token['type'], $types)) {
				$result[] = $token;
			}
		}

		return $result;
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
	 * Quoting string $value.
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public static function quoteString(string $value): string {
		return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"']).'"';
	}
}
