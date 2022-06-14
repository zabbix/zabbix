<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * A parser for function id macros.
 */
class CFunctionIdParser extends CParser {

	/**
	 * @param string    $source
	 * @param int       $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		$p = $pos;

		if (!isset($source[$p]) || $source[$p] !== '{') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		for (; isset($source[$p]) && ($source[$p] >= '0' && $source[$p] <= '9'); $p++) {
			// Code is not missing here.
		}

		if ($p == $pos + 1 || !isset($source[$p]) || $source[$p] !== '}') {
			return CParser::PARSE_FAIL;
		}
		$p++;

		$functionid = substr($source, $pos + 1, $p - $pos - 2);

		if (bccomp(1, $functionid) > 0 ||  bccomp($functionid, ZBX_DB_MAX_ID) > 0) {
			return CParser::PARSE_FAIL;
		}

		$this->length = $p - $pos;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
