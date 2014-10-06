<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * A parser for custom macros.
 */
class CMacroParser extends CParser {

	/**
	 * A character that must be present right after the opening curly brace.
	 *
	 * For example "$" for user macros like "{$MACRO}".
	 *
	 * @var string
	 */
	protected $prefixChar;

	/**
	 * @param string $prefixChar
	 */
	public function __construct($prefixChar) {
		$this->prefixChar = $prefixChar;
	}

	/**
	 * @param string    $source
	 * @param int       $startPos
	 *
	 * @return bool|CParserResult
	 */
	public function parse($source, $startPos = 0) {
		$this->pos = $startPos;

		if (!isset($source[$this->pos]) || $source[$this->pos] != '{') {
			return false;
		}

		$this->pos++;

		if (!isset($source[$this->pos]) || $source[$this->pos] !== $this->prefixChar) {
			return false;
		}

		$this->pos++;

		// make sure there's at least one valid macro char
		if (!isset($source[$this->pos]) || !$this->isMacroChar($source[$this->pos])) {
			return false;
		}

		$this->pos++;

		// skip the remaining macro chars
		while (isset($source[$this->pos]) && $this->isMacroChar($source[$this->pos])) {
			$this->pos++;
		}

		if (!isset($source[$this->pos]) || $source[$this->pos] != '}') {
			return false;
		}

		$macroLength = $this->pos - $startPos + 1;

		$result = new CParserResult();
		$result->source = $source;
		$result->pos = $startPos;
		$result->length = $macroLength;
		$result->match = substr($source, $startPos, $macroLength);

		return $result;
	}

	/**
	 * Returns true if the char is allowed in the macro, false otherwise
	 *
	 * @param string    $c
	 *
	 * @return bool
	 */
	private function isMacroChar($c) {
		if (($c >= 'A' && $c <= 'Z') || $c == '.' || $c == '_' || ($c >= '0' && $c <= '9')) {
			return true;
		}

		return false;
	}
}
