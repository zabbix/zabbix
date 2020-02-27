<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

	const REFERENCE_NONE = 0;
	const REFERENCE_NUMERIC = 1;
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
	 * @var int|string
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
	 *   'ref_type' => CMacroParser::REFERENCE_NONE            Default, do not support any reference tyep.
	 *   'ref_type' => CMacroParser::REFERENCE_NUMERIC         Support only numeric reference <1-9>, {HOST.HOST3}.
	 *   'ref_type' => CMacroParser::REFERENCE_ALPHANUMERIC    Allow alpha numeric reference, {EVENT.TAGS.issue_number}.
	 *                                                         Reference can be quoted if it contains non alphanumeric
	 *                                                         characters, {EVEN.TAGS."Jira ID"}.
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
	 *
	 * @param string $source    Search and replace string.
	 * @param int    $pos       Initial position in $source string.
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

		if (!$this->parseReference($source, $p)) {
			return self::PARSE_FAIL;
		}

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
	 * Parse reference of type defined in "ref_type". Reference of type REFERENCE_ALPHANUMERIC containing non
	 * alphanumeric characters or underscore should be quoted. Inside quotes only " and \ escape sequences are allowed.
	 *
	 * @param  string $source
	 * @param  int    $pos
	 */
	private function parseReference($source, &$pos) {
		$p = $pos;

		switch ($this->options['ref_type']) {
			case self::REFERENCE_NUMERIC:
				$this->reference = 0;

				if (isset($source[$p]) && $source[$p] >= '1' && $source[$p] <= '9') {
					$this->reference = (int) $source[$p];
					$p++;
				}
				break;

			case self::REFERENCE_ALPHANUMERIC:
				$pattern_quoted = '"(?:\\\\["\\\\]|[^"\\\\])*"';
				$pattern_unquoted = '[A-Za-z0-9_]+';
				$pattern = '\.(?P<ref>'.$pattern_quoted.'|'.$pattern_unquoted.')';

				if (preg_match('/^'.$pattern.'/', substr($source, $pos), $matches)) {
					$this->reference = ($matches['ref'][0] === '"')
						? str_replace(['\\"', '\\\\'], ['"', '\\'], substr($matches['ref'], 1, -1))
						: $matches['ref'];
					$p += strlen($matches[0]);
				}
				else {
					return false;
				}
				break;
		}

		$pos = $p;

		return true;
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
	 * Returns reference value.
	 *
	 * @return int|string
	 */
	public function getReference() {
		return $this->reference;
	}
}
