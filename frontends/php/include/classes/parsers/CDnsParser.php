<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * A parser for DNS address.
 */
class CDnsParser extends CParser {

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		// The first character must be an alphanumeric character.
		if (!isset($source[$p]) || !self::isalnum($source[$p])) {
			return self::PARSE_FAIL;
		}
		$p++;

		// Periods are only allowed when they serve to delimit components.
		$component = false;

		for (; isset($source[$p]); $p++) {
			if ($source[$p] === '-' || self::isalnum($source[$p])) {
				$component = true;
			}
			elseif ($source[$p] === '.' && $component) {
				$component = false;
			}
			else {
				break;
			}
		}

		// The last character must not be a minus sign.
		if ($source[$p - 1] === '-') {
			$p--;
		}

		$length = $p - $pos;

		// Single character names or nicknames are not allowed.
		if ($length == 1) {
			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}

	private static function isalnum($c) {
		return ('a' <= $c && $c <= 'z') || ('A' <= $c && $c <= 'Z') || ('0' <= $c && $c <= '9');
	}
}
