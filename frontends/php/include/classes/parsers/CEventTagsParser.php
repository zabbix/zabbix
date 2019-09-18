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
	/**
	 * Macro parts, for macro {EVENT.TAGS."Jira Id"} will contain array with entries:
	 * ['EVENT', 'TAGS', 'Jira Id']
	 *
	 * @var array
	 */
	private $parts;

	/**
	 * Find one of the given strings at the given position.
	 *
	 */
	public function parse($source, $pos = 0) {
		$p = $pos;
		$quoted = false;
		$escaped = false;
		$this->length = 0;
		$this->match = '';
		$this->parts = [];

		if ($source[$p] != '{') {
			return CParser::PARSE_FAIL;
		}

		$match = '{';
		$p++;

		while (isset($source[$p])) {
			if ($this->parsePart($source, $p) === CParser::PARSE_FAIL) {
				break;
			}

			$p = $source[$p] == '.'
		}

		if (!isset($source[$p]) || $source[$p] != '}') {
			$this->parts = [];

			return CParser::PARSE_FAIL
		}

		while (isset($source[$p])) {
			$char = $source[$p];

			if ($char == '{' && !($quoted || $escaped)) {
				$this->length = 0;
				$this->match = '';

				return CParser::PARSE_FAIL;
			}

			$match .= $char;

			if ($char == '}' && !($quoted || $escaped)) {
				$p++;
				break;
			}

			$escaped = ($char == '\\') && !$escaped;
			$quoted = (!$escaped && $char == '"') ? !$quoted : $quoted;
			$p++;
		}

		if ($escaped || $quoted) {
			$this->length = 0;
			$this->match = '';

			return CParser::PARSE_FAIL;
		}

		$this->match = $match;
		$this->length = $p - $pos;

		$macro = substr($match, 1, -1);

		$p = 0;
		$quoted = false;
		$escaped = false;
		$part = '';

		while (isset($macro[$p])) {
			$char = $macro[$p];

			if ($char == '.' && !$quoted && !$escaped) {
				$this->parts[] = trim($part, '"');
				$part = '';
			}
			else {
				$part .= $char;
				$escaped = ($char == '\\') && !$escaped;
				$quoted = (!$escaped && $char == '"') ? !$quoted : $quoted;
			}

			$p++;
		}

		if ($part != '') {
			$this->parts[] = trim($part, '"');
		}

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
			$escaped = ($char == '\\') && !$escaped;
			$quoted = (!$escaped && $char == '"') ? !$quoted : $quoted;
		}

		if ($p == $pos || $quoted || $escaped) {
			return CParser::PARSE_FAIL;
		}

		$this->parts[] = trim($match, '"');

		return CParser::PARSE_SUCCESS;
	}
}
