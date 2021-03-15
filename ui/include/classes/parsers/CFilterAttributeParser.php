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
 * A parser for filter attributes, tag or group.
 */
class CFilterAttributeParser extends CParser {

	// Error type constants.
	public const ERROR_LEVEL = 1;
	public const ERROR_UNEXPECTED_ENDING = 2;
	public const ERROR_UNPARSED_CONTENT = 3;

	public const TOKEN_TYPE_UNKNOWN = -1;
	public const TOKEN_TYPE_OPEN_BRACE = 0;
	public const TOKEN_TYPE_CLOSE_BRACE = 1;
	public const TOKEN_TYPE_OPERATOR = 2;
	public const TOKEN_TYPE_KEYVALUE = 12;

	/**
	 * Array of supported parsers.
	 *
	 * @var array
	 */
	protected $token_type_parsers = [];

	/**
	 * Parsed tokens array.
	 *
	 * @var array
	 */
	protected $tokens = [];

	/**
	 * Maximum braces nesting level, 0 if not set.
	 *
	 * @var int
	 */
	protected $max_level;

	public function __construct(array $data = []) {
		$data += [
			'max_level' => 0
		];
		$this->max_level = $data['max_level'];
		$this->token_type_parsers[self::TOKEN_TYPE_KEYVALUE] = new CAttributeParser();
		$this->token_type_parsers[self::TOKEN_TYPE_OPERATOR] = new CSetParser(['and', 'or']);
	}

	/**
	 * Parse a filter attributes. Filter should starts with question mark character and should be defined
	 * in square brackets.
	 *
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$start_pos = $pos;
		$tokens = [];
		$this->tokens = [];
		$level = 0;

		if (!isset($source[$pos]) || substr($source, $pos, 2) !== '?[') {
			return CParser::PARSE_FAIL;
		}
		$pos += 2;

		while (isset($source[$pos]) && $source[$pos] !== ']') {
			if ($source[$pos] === ' ') {
				$pos++;
				continue;
			}

			$type = self::TOKEN_TYPE_UNKNOWN;

			switch ($source[$pos]) {
				case '(':
					$level++;
					$type = self::TOKEN_TYPE_OPEN_BRACE;
					$match = $source[$pos];

					if ($this->max_level > 0 && $level > $this->max_level) {
						return CParser::PARSE_FAIL;
					}

					$last_token = end($tokens);

					if (isset($tokens[1]) && $last_token['type'] != self::TOKEN_TYPE_OPEN_BRACE
							&& $last_token['type'] != self::TOKEN_TYPE_OPERATOR) {
						return CParser::PARSE_FAIL;
					}

					break;

				case ')':
					$level--;
					$type = self::TOKEN_TYPE_CLOSE_BRACE;
					$match = $source[$pos];

					if ($level < 0) {
						return CParser::PARSE_FAIL;
					}

					$last_token = end($tokens);

					if (!isset($tokens[1]) || $last_token['type'] == self::TOKEN_TYPE_OPERATOR) {
						return CParser::PARSE_FAIL;
					}

					break;

				default:
					foreach ($this->token_type_parsers as $token_type => $parser) {
						if ($parser->parse($source, $pos) != CParser::PARSE_FAIL) {
							$type = $token_type;
							$match = $parser->getMatch();
							break;
						}
					}

					break;
			}

			if ($type == self::TOKEN_TYPE_UNKNOWN) {
				return CParser::PARSE_FAIL;
			}

			$length = strlen($match);
			$tokens[] = [
				'type' => $type,
				'match' => $match,
				'pos' => $pos,
				'length' => $length
			];
			$pos += $length;
		}

		if ($level > 0 || !isset($source[$pos]) || $source[$pos] !== ']') {
			return CParser::PARSE_FAIL;
		}
		$pos++;

		$this->length = $pos - $start_pos;
		$this->tokens = $tokens;
		$this->match = substr($source, $start_pos, $this->length);

		return isset($source[$pos]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS;
	}

	/**
	 * Get array of parsed tokens.
	 *
	 * @return array
	 */
	public function getTokens(): array {
		return $this->tokens;
	}
}
