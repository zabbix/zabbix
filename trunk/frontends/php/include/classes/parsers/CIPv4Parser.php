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
 * A parser for IPv4 address.
 */
class CIPv4Parser extends CParser {

	/**
	 * @param string $source
	 * @param int    $pos
	 *
	 * @return int
	 */
	public function parse($source, $pos = 0) {
		$this->length = 0;
		$this->match = '';

		if (!preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)/', substr($source, $pos), $matches)) {
			return self::PARSE_FAIL;
		}

		for ($i = 1; $i <= 4; $i++) {
			if (strlen($matches[$i]) > 3 || $matches[$i] > 255) {
				return self::PARSE_FAIL;
			}
		}

		$this->match = $matches[0];
		$this->length = strlen($this->match);

		return (isset($source[$pos + $this->length]) ? self::PARSE_SUCCESS_CONT : self::PARSE_SUCCESS);
	}
}
