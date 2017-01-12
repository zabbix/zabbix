<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
	private $options = ['allow_reference' => false];

	/**
	 * Array of strings to search for.
	 *
	 * @param array $macros		the list of macros, for example ['{ITEM.VALUE}', '{HOST.HOST}']
	 * @param array $options
	 */
	public function __construct(array $macros, array $options = []) {
		$this->set_parser = new CSetParser(array_map(function($macro) { return substr($macro, 1, -1); }, $macros));

		if (array_key_exists('allow_reference', $options)) {
			$this->options['allow_reference'] = $options['allow_reference'];
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
		$this->n = 0;

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] != '{') {
			return self::PARSE_FAIL;
		}
		$p++;

		if ($this->set_parser->parse($source, $p) == self::PARSE_FAIL) {
			return self::PARSE_FAIL;
		}
		$p += $this->set_parser->getLength();

		if ($this->options['allow_reference']) {
			if (isset($source[$p]) && $source[$p] >= '1' && $source[$p] <= '9') {
				$this->n = (int) $source[$p];
				$p++;
			}
		}

		if (!isset($source[$p]) || $source[$p] != '}') {
			$this->n = 0;

			return self::PARSE_FAIL;
		}
		$p++;

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);
		$this->macro = $this->set_parser->getMatch();

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
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
}
