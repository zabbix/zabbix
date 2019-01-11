<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

		if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(\.[A-Za-z0-9_-]+)*\.?/', substr($source, $p), $matches)) {
			$p += strlen($matches[0]);
		}
		else {
			return self::PARSE_FAIL;
		}

		$length = $p - $pos;

		if ($length > 255) {
			return self::PARSE_FAIL;
		}

		$this->length = $length;
		$this->match = substr($source, $pos, $this->length);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
