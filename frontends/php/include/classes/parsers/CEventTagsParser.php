<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CEventTagsParser extends CParser {
	// Option: Remove quote marks from every parsed part.
	const TRIM_QUOTE_MARKS = 0x1;

	private $options;

	/**
	 * Macro parts, for macro {EVENT.TAGS."Jira Id"} will contain array with entries:
	 * ['EVENT', 'TAGS', 'Jira Id']
	 *
	 * @var array
	 */
	private $parts;

	public function __construct($options) {
		$this->options = $options;
	}
	/**
	 * Find one of the given strings at the given position.
	 *
	 * @param string $source    Source string.
	 * @param int    $pos       Start position in string for parser.
	 */
	public function parse($source, $pos = 0) {
		$p = $pos;
		$quoted = false;
		$escaped = false;
		$this->length = 0;
		$this->match = '';
		$this->parts = [];

		if (isset($source[$p]) && $source[$p] == '{') {
			$p++;
		}

		while (isset($source[$p]) && $this->parsePart($source, $p) == CParser::PARSE_SUCCESS) {
			$this->match[] = $source[$p];
			$p += ($source[$p] == '.') ? 1 : 0;
		}

		if (isset($source[$p]) && $source[$p] != '}') {
			return CParser::PARSE_FAIL;
		}

		$this->length = 1 + $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return CParser::PARSE_SUCCESS;
	}

	/**
	 * Macro parts, for macro {EVENT.TAGS."Jira Id"} will contain array with entries:
	 *   ['EVENT', 'TAGS', 'Jira Id']
	 *
	 * @return array
	 */
	public function getParts() {
		return $this->parts;
	}

	/**
	 * Parse single part of macro, will add parsed part to $parts array and return PARSE_SUCCESS on success.
	 *
	 * @param string $source
	 * @param int    $p
	 * @return CParser::PARSE_SUCCESS|CParser::PARSE_FAIL
	 */
	private function parsePart($source, &$p) {
		$pos = $p;
		$match = '';
		$quoted = false;
		$escaped = false;

		while (isset($source[$p])) {
			$char = $source[$p];

			if (($char == '{' || $char == '}' || $char == '.') && !($quoted || $escaped)) {
				break;
			}

			$p++;
			$match .= $char;
			$quoted = (!$escaped && $char == '"') ? !$quoted : $quoted;
			$escaped = ($char == '\\') && !$escaped;
		}

		if ($p == $pos || $quoted || $escaped) {
			return CParser::PARSE_FAIL;
		}

		$this->parts[] = $this->options & static::TRIM_QUOTE_MARKS ? trim($match, '"') : $match;

		return CParser::PARSE_SUCCESS;
	}
}
