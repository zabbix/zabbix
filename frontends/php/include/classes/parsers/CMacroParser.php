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

	// Do not allow any references.
	const REFERENCE_NONE = 0;
	// Allow only numeric reference, {HOST.HOST3}
	const REFERENCE_NUMERIC = 1;
	/**
	 * Allow alpha numeric reference, {EVENT.TAGS.test}. Reference can be quoted if it contains non alphanumeric
	 * characters, {EVEN.TAGS."Jira id"}
	 */
	const REFERENCE_ALPHANUMERIC = 2;

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
	 * Macro last part without quotes for option 'allow_quoted_suffix' was defined.
	 *
	 * @var string
	 */
	private $suffix;

	// Allowed reference type.
	private $reference_type;

	/**
	 * Array of strings to search for.
	 *
	 * @param array $macros     The list of macros, for example ['{ITEM.VALUE}', '{HOST.HOST}']
	 * @param int   $ref_type   Allowed reference type: REFERENCE_NONE, REFERENCE_NUMERIC, REFERENCE_ALPHANUMERIC
	 */
	public function __construct(array $macros, $ref_type = CMacroParser::REFERENCE_NONE) {
		$this->needles = [];

		foreach ($macros as $macro) {
			$this->needles[] = substr($macro, 1, -1);
		}

		$this->max_match_len = max(array_map('strlen', $this->needles));
		$this->min_match_len = min(array_map('strlen', $this->needles));
		$this->reference_type = $ref_type;
	}

	/**
	 * Find one of the given strings at the given position.
	 *
	 * The parser implements a greedy algorithm, i.e., looks for the longest match.
	 */
	public function parse($source, $pos = 0) {
		$this->suffix = '';
		$this->match = '';
		$this->macro = '';
		$this->length = 0;
		$this->n = 0;
		$length = mb_strlen($source);
		$p = $pos;

		if ($p >= $length || $source[$p] != '{') {
			return CParser::PARSE_FAIL;
		}

		$p++;

		if ($this->parseMatch($source, $p) == CParser::PARSE_FAIL) {
			return CParser::PARSE_FAIL;
		}

		$p += strlen($this->macro);

		switch ($this->reference_type) {
			case CMacroParser::REFERENCE_NUMERIC:
				if ($p < $length && $source[$p] >= '1' && $source[$p] <= '9') {
					$this->n = (int) $source[$p];
					$p++;
				}
				break;

			case CMacroParser::REFERENCE_ALPHANUMERIC:
				if ($p < $length - 1 && $source[$p] == '.') {
					$p++;

					if ($this->parseSuffix($source, $p) == CParser::PARSE_FAIL) {
						$this->macro = '';

						return CParser::PARSE_FAIL;
					}

					$p += mb_strlen($this->suffix);

					if ($this->suffix[0] == '"') {
						$this->suffix = mb_substr($this->suffix, 1, -1);
					}
				}
				break;

		}

		if ($p >= $length || $source[$p] != '}') {
			$this->macro = '';
			$this->suffix = '';
			$this->n = 0;

			return CParser::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return $p < $length ? CParser::PARSE_SUCCESS_CONT : CParser::PARSE_SUCCESS;
	}

	/**
	 * Parse suffix value for option "allow_quoted_suffix", return CParser search state, in case of success also
	 * will set $this->suffix value. Suffix containing non alphanumeric characters or underscore should be quoted.
	 * Inside quotes only " and \ escape sequences are allowed.
	 *
	 * @param string $source     Source string.
	 * @param int    $p          Search start position, after quotation mark.
	 * @return int
	 */
	protected function parseSuffix($source, $p) {
		$pos = $p;
		$quoted = ($source[$p] == '"');
		$p += $quoted ? 1 : 0;
		$regex = $quoted ? '/(?:\\\\["\\\\]|[^"\\\\])+/' : '/[A-Z0-9_]+/i';
		$match = [];

		if (preg_match($regex, $source, $match, 0, $p) != 1) {
			return CParser::PARSE_FAIL;
		}

		$this->suffix = $quoted ? '"'.$match[0].'"' : $match[0];
		return CParser::PARSE_SUCCESS;
	}

	/**
	 * Find desired macro, returns parser state, on success also set $this->macro value.
	 *
	 * @param string $source     Source string.
	 * @param int    $p          Search start position, after { character.
	 * @return int
	 */
	protected function parseMatch($source, $p) {
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
	 * Get macro suffix.
	 *
	 * @return string
	 */
	public function getSuffix() {
		return $this->suffix;
	}
}
