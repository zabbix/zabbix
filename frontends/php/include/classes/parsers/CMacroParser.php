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


class CMacroParser extends CParser {

	/**
	 * Macro name.
	 *
	 * @var string
	 */
	private $macro;

	/**
	 * Reference number.
	 *
	 * @var int
	 */
	private $n;

	/**
	 * Macro last part without quotes if option 'allow_quoted_suffix' was defined.
	 *
	 * @var string
	 */
	private $unquoted_suffix;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'allow_reference' => true		support of reference {MACRO<1-9>}
	 *   'allow_quoted_suffix' => true  support of quoted last part of macro {EVENT.TAGS."Jira id"}
	 *
	 * @var array
	 */
	private $options = [
		'allow_reference' => false,
		'allow_quoted_suffix' => false
	];

	/**
	 * Array of strings to search for.
	 *
	 * @param array $macros		the list of macros, for example ['{ITEM.VALUE}', '{HOST.HOST}']
	 * @param array $options
	 */
	public function __construct(array $macros, array $options = []) {
		$this->needles = [];

		foreach ($macros as $macro) {
			$this->needles[] = substr($macro, 1, -1);
		}

		$this->max_match_len = max(array_map('strlen', $this->needles));
		$this->min_match_len = min(array_map('strlen', $this->needles));
		$this->options = $options + $this->options;
	}

	/**
	 * Find one of the given strings at the given position.
	 *
	 * The parser implements a greedy algorithm, i.e., looks for the longest match.
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';
		$this->macro = '';
		$this->n = 0;
		$length = strlen($source);
		$p = $pos;

		if ($p >= $length || $source[$p] != '{') {
			return CParser::PARSE_FAIL;
		}

		$p++;

		if ($this->findMatch($source, $p) == CParser::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}

		$p += strlen($this->macro);

		if ($this->options['allow_quoted_suffix'] && $p < $length && $source[$p] == '"') {
			$p++;

			if ($this->findSuffix($source, $p) == CParser::PARSE_FAIL) {
				$this->macro = '';
				$this->unquoted_suffix = '';

				return CParser::PARSE_FAIL;
			}

			$p += strlen($this->unquoted_suffix) + 2;

			if ($p >= $length || $source[$p] !== '"') {
				$this->macro = '';
				$this->unquoted_suffix = '';

				return CParser::PARSE_FAIL;
			}
		}

		if ($this->options['allow_reference']) {
			if (isset($source[$p]) && $source[$p] >= '1' && $source[$p] <= '9') {
				$this->n = (int) $source[$p];
				$p++;
			}
		}

		if (!isset($source[$p]) || $source[$p] != '}') {
			$this->macro = '';
			$this->unquoted_suffix = '';
			$this->n = 0;

			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return $p < $length ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS;
	}

	/**
	 * Find quoted suffix value for option "allow_quoted_suffix". Return CParser search state.
	 *
	 * @param string $source     Source string.
	 * @param int    $p          Search start position, after quotation mark.
	 * @return int
	 */
	protected function findSuffix($source, $p) {
		$escaped = false;
		$length = strlen($source);
		$pos = $p;

		while ($p < $length && ($escaped || preg_match('/[0-9A-Z_ ]/', $source[$p]) == 1)) {
			if ($escaped || $source[$p] == '\\') {
				$escaped = !$escaped;
			}

			$p++;
		}

		if ($escaped) {
			return CParser::PARSE_FAIL;
		}

		$this->unquoted_suffix = substr($source, $p, $p - $pos - 1);
		return CParser::PARSE_SUCCESS;
	}

	/**
	 * Find desired macro, returns parser state.
	 *
	 * @param string $source     Source string.
	 * @param int    $p          Search start position, after { character.
	 * @return int
	 */
	protected function findMatch($source, $p) {
		$len = $this->max_match_len;

		while ($len >= $this->min_match_len) {
			$needle = substr($source, $p, $len);

			if (in_array($needle, $this->needles)) {
				$this->macro = $needle;

				return CParser::PARSE_SUCCESS;
			}

			$len--;
		}

		return CParser::PARSE_FAIL;
	}

	/**
	 * Returns the macro name like HOST.HOST.
	 *
	 * @return string
	 */
	public function getMacro() {
		return $this->macro;
	}

	/**
	 * Returns the reference.
	 *
	 * @return int
	 */
	public function getN() {
		return $this->n;
	}

	/**
	 * Get uquoted_suffix
	 *
	 * @return string
	 */
	public function getSuffix() {
		return $this->unquoted_suffix;
	}
}
