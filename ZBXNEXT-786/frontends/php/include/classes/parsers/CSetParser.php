<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CSetParser extends CParser {

	/**
	 * Array of string to search for with strings as keys.
	 *
	 * @var array
	 */
	protected $needles = [];

	/**
	 * Array of chars that are used in the given strings with chars as keys.
	 *
	 * @var array
	 */
	protected $chars = [];

	/**
	 * Array of strings to search for.
	 *
	 * @param array $needles
	 */
	public function __construct(array $needles) {
		$this->needles = array_flip($needles);

		$this->chars = array_flip(str_split(implode($needles)));
	}

	/**
	 * Find one of the given strings at the given position.
	 *
	 * The parser implements a greedy algorithm, i.e., looks for the longest match.
	 */
	public function parse($source, $startPos = 0) {
		$this->pos = $startPos;

		$match = null;
		$matchPos = null;
		$token = '';
		while (isset($source[$this->pos]) && isset($this->chars[$source[$this->pos]])) {
			$token .= $source[$this->pos];

			$this->pos++;

			// when we found a match, keep looking to see of there may be a longer match
			if (isset($this->needles[$token])) {
				$match = $token;
				$matchPos = $this->pos;
			}
		}

		if ($matchPos === null) {
			return false;
		}

		$result = new CParserResult();
		$result->source = $source;
		$result->match = $match;
		$result->pos = $startPos;
		$result->length = $matchPos - $startPos;

		return $result;
	}
}
