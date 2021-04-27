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


class CFilterParser extends CParser {

	// For parsing of filter expressions.
	private const STATE_AFTER_OPEN_BRACE = 1;
	private const STATE_AFTER_LOGICAL_OPERATOR = 2;
	private const STATE_AFTER_NOT_OPERATOR = 3;
	private const STATE_AFTER_CLOSE_BRACE = 4;
	private const STATE_AFTER_PAIR = 5;

	// For parsing od keyword/string pairs.
	private const TOKEN_STRING = 1;
	private const TOKEN_KEYWORD = 2;

	/**
	 * Chars that should be treated as spaces.
	 */
	public const WHITESPACES = " \r\n\t";

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

		if (substr($source, $p, 2) !== '?[') {
			return self::PARSE_FAIL;
		}
		$p += 2;

		if (!self::parseExpression($source, $p)) {
			return self::PARSE_FAIL;
		}

		if (!isset($source[$p]) || $source[$p] !== ']') {
			return self::PARSE_FAIL;
		}
		$p++;

		$len = $p - $pos;

		$this->length = $len;
		$this->match = substr($source, $pos, $len);

		return isset($source[$p]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	private static function parseExpression(string $source, int &$pos): bool {
		$logical_operator_parser = new CSetParser(['and', 'or']);
		$not_operator_parser = new CSetParser(['not']);

		$state = self::STATE_AFTER_OPEN_BRACE;
		$after_space = false;
		$level = 0;
		$is_valid = false;
		$p = $pos;

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
							break;

						default:
							if ($not_operator_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $not_operator_parser->getLength() - 1;
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parsePair($source, $p)) {
								$state = self::STATE_AFTER_PAIR;

								if ($level == 0) {
									$pos = $p + 1;
									$is_valid = true;
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
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if ($not_operator_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $not_operator_parser->getLength() - 1;
								$state = self::STATE_AFTER_NOT_OPERATOR;
							}
							elseif (self::parsePair($source, $p)) {
								$state = self::STATE_AFTER_PAIR;

								if ($level == 0) {
									$pos = $p + 1;
									$is_valid = true;
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

							if ($level == 0) {
								$pos = $p + 1;
								$is_valid = true;
							}
							break;

						default:
							if ($logical_operator_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $logical_operator_parser->getLength() - 1;
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

							if ($level == 0) {
								$pos = $p + 1;
								$is_valid = true;
							}
							break;

						default:
							if ($after_space && $logical_operator_parser->parse($source, $p) != CParser::PARSE_FAIL) {
								$p += $logical_operator_parser->getLength() - 1;
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
							break;

						default:
							if (!$after_space) {
								break 3;
							}

							if (self::parsePair($source, $p, $_tokens)) {
								$state = self::STATE_AFTER_PAIR;

								if ($level == 0) {
									$pos = $p + 1;
									$is_valid = true;
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

		if ($is_valid) {
			// Including trailing whitespaces as part of the expression.
			while (isset($source[$pos]) && strpos(self::WHITESPACES, $source[$pos]) !== false) {
				$pos++;
			}
		}

		return $is_valid;
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
	 *
	 * @return bool  Returns true if parsed successfully, false otherwise.
	 */
	private static function parsePair(string $source, int &$pos): bool {
		$keyword_parser = new CSetParser(['tag', 'group']);
		$binary_operator_parser = new CSetParser(['=', '<>']);

		$p = $pos;

		if ($keyword_parser->parse($source, $p) != CParser::PARSE_FAIL) {
			$p += $keyword_parser->getLength();
			$pending_token = self::TOKEN_STRING;
		}
		elseif (self::parseString($source, $p)) {
			$pending_token = self::TOKEN_KEYWORD;
		}
		else {
			return false;
		}

		while (isset($source[$p]) && strpos(self::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if ($binary_operator_parser->parse($source, $p) == CParser::PARSE_FAIL) {
			return false;
		}
		$p += $binary_operator_parser->getLength();

		while (isset($source[$p]) && strpos(self::WHITESPACES, $source[$p]) !== false) {
			$p++;
		}

		if ($pending_token == self::TOKEN_KEYWORD && $keyword_parser->parse($source, $p) != CParser::PARSE_FAIL) {
			$p += $keyword_parser->getLength();
		}
		else if ($pending_token != self::TOKEN_STRING || !self::parseString($source, $p)) {
			return false;
		}

		$pos = $p;

		return true;
	}

	/**
	 * Parses a quoted string constant in the expression.
	 *
	 * @param string  $source
	 * @param int     $pos
	 *
	 * @return bool returns true if parsed successfully, false otherwise
	 */
	private static function parseString(string $source, int &$pos): bool {
		if (!preg_match('/^"([^"\\\\]|\\\\["\\\\])*"/', substr($source, $pos), $matches)) {
			return false;
		}

		$len = strlen($matches[0]);
		$pos += $len - 1;

		return true;
	}
}
