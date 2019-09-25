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
	 * Do not allow any references.
	 */
	const REFERENCE_NONE = 0;
	/**
	 * Allow only numeric reference <1-9>, {HOST.HOST3}.
	 */
	const REFERENCE_NUMERIC = 1;
	/**
	 * Allow alpha numeric reference, {EVENT.TAGS.issue_number}. Reference can be quoted if it contains non alphanumeric
	 * characters, {EVEN.TAGS."Jira ID"}.
	 */
	const REFERENCE_ALPHANUMERIC = 2;

	/**
	 * Macro name.
	 *
	 * @var string
	 */
	private $macro;

	/**
	 * Reference value.
	 *
	 * @var null|int|string
	 */
	private $reference;

	/**
	 * @var CSetParser
	 */
	private $set_parser;

	/**
	 * An options array.
	 *
	 * Supported options:
	 *   'allow_reference' => true		support of reference {MACRO<1-9>}
	 *
	 * @var array
	 */
	private $options = ['ref_type' => self::REFERENCE_NONE];

	/**
	 * Array of strings to search for.
	 *
	 * @param array $macros   The list of macros, for example ['{ITEM.VALUE}', '{HOST.HOST}'].
	 * @param array $options
	 */
	public function __construct(array $macros, array $options = []) {
		$this->set_parser = new CSetParser(array_map(function($macro) { return substr($macro, 1, -1); }, $macros));

		if (array_key_exists('ref_type', $options)) {
			$this->options['ref_type'] = $options['ref_type'];
		}
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
		$this->reference = null;

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->set_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->set_parser->getLength();

		$this->parseReference($source, $p);

		if (!isset($source[$p]) || $source[$p] !== '}') {
			$this->reference = null;

			return self::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->macro = $this->set_parser->getMatch();

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	/**
	 * Parse suffix value for option "allow_quoted_suffix", return CParser search state, in case of success also
	 * will set $this->suffix value. Suffix containing non alphanumeric characters or underscore should be quoted.
	 * Inside quotes only " and \ escape sequences are allowed.
	 *
	 * @param  string $source
	 * @param  int    $pos
	 */
	private function parseReference($source, &$pos) {
		$p = $pos;

		switch ($this->options['ref_type']) {
			case self::REFERENCE_NUMERIC:
				if (isset($source[$p]) && $source[$p] >= '1' && $source[$p] <= '9') {
					$this->reference = (int) $source[$p];
					$p++;
				}
				else {
					$this->reference = 0;
				}
				break;

			case self::REFERENCE_ALPHANUMERIC:
				$pattern_quoted = '"(?:\\\\["\\\\]|[^"\\\\])*"';
				$pattern_unquoted = '[A-Za-z0-9_]+';
				$pattern = '\.(?P<ref>'.$pattern_quoted.'|'.$pattern_unquoted.')';

				if (preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
					$this->reference = (isset($matches['ref'][0]) && $matches['ref'][0] === '"')
						? str_replace(['\\"', '\\\\'], ['"', '\\'], substr($matches['ref'], 1, -1))
						: $matches['ref'];
					$p += strlen($matches[0]);
				}
				break;
		}

		$pos = $p;
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
	 * Returns reference value, for reference of type:
	 * - CMacroParser::REFERENCE_NUMERIC will be of type int.
	 * - CMacroParser::REFERENCE_ALPHANUMERIC will be of type string.
	 * - CMacroParser::REFERENCE_NONE or parsing failed will be null.
	 *
	 * @return null|int|string
	 */
	public function getReference() {
		return $this->reference;
	}
}
